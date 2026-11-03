<?php

/**
 * NL Design CSS Injection Service.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Service
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/css-architecture/spec.md
 * @spec openspec/specs/custom-fonts/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use Throwable;

/**
 * Injects the nldesign stylesheet cascade for a themed render context.
 *
 * The body of {@see inject()} is the former `Application::injectThemeCSS()`
 * moved verbatim (css-architecture layers 1-8 + conditionals, including the
 * custom-font stylesheet link added by the custom-font-upload change), now
 * driven by render events instead of `Application::boot()` — see
 * `openspec/changes/render-event-injection`. The only behavior addition is
 * context gating via the `themed_contexts` appconfig key; every stylesheet,
 * its cascade position, and its loading condition are otherwise unchanged.
 *
 * The two `\OCP\Util::addStyle()` / `\OCP\Util::addHeader()` calls are
 * wrapped in the protected `emitStyle()` / `emitStylesheetLink()` seams purely so
 * unit tests can assert the exact stylesheet sequence via a partial mock —
 * the real static calls delegate to the server-private `\OC_Util`, which is
 * not resolvable outside a full Nextcloud bootstrap. Production code always
 * runs through these two one-line wrappers, so no behavior changes.
 *
 * @spec openspec/specs/css-architecture/spec.md
 *
 * The set-dependent layers are listed by {@see TokenSetLayers::forSet()}; this
 * class emits that list and the set-independent layers after it.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) - every collaborator here answers "does this layer load, and with what". A layer
 *   whose source lived behind a facade would be a layer whose position in the cascade is decided somewhere other than inject()
 *   and TokenSetLayers::forSet(), which is exactly what the two exist to prevent.
 */
class CssInjectionService {

	/**
	 * The appconfig key holding the JSON render-context allow-list.
	 *
	 * @var string
	 */
	private const string THEMED_CONTEXTS_KEY = 'themed_contexts';

	/**
	 * The render-context names the `themed_contexts` allow-list recognizes.
	 *
	 * Any other context value (e.g. an unmapped/future `renderAs`) always
	 * fails open to themed — see {@see isContextThemed()}.
	 *
	 * @var string[]
	 */
	private const array VALID_CONTEXTS = ['user', 'login', 'guest', 'public', 'error'];

	/** @var TokenSetLayers The set-dependent layers, in cascade order. */
	private TokenSetLayers $setLayers;

	/** @var LayoutOptionsService Resolves the workplace layout and the brand stripe for a token set. */
	private LayoutOptionsService $layoutOptions;

	/**
	 * Constructor.
	 *
	 * @param CssInjectionContext $context The app config, the design systems, the URL generator and the log.
	 * @param CssInjectionLayers $layers The custom CSS, the fonts, the preview banner, and the set and brand per request.
	 * @param CssInjectionTokenFiles $files The stock tokens, the runtime files, the logo layer and the internal scopes.
	 * @param CssInjectionChrome $chrome The workplace layout and the top bar parts.
	 */
	public function __construct(
		private readonly CssInjectionContext $context,
		private readonly CssInjectionLayers $layers,
		private readonly CssInjectionTokenFiles $files,
		private readonly CssInjectionChrome $chrome = new CssInjectionChrome(),
	) {
		$this->layoutOptions = ($chrome->layoutOptions ?? new LayoutOptionsService(
			config: $context->config,
			designSystemService: $context->designSystemService
		));
		$this->setLayers = self::setLayersFrom(context: $context, files: $files);
	}//end __construct()

	/**
	 * The set-dependent layers, read through the token files.
	 *
	 * @param CssInjectionContext $context The app config, the design systems and the log.
	 * @param CssInjectionTokenFiles $files The stock tokens, the runtime files, the logo layer and the internal scopes.
	 *
	 * @return TokenSetLayers
	 */
	private static function setLayersFrom(CssInjectionContext $context, CssInjectionTokenFiles $files): TokenSetLayers {
		$entries = new TokenSetLayerEntries(
			runtimeFiles: $files->runtimeFiles,
			stockTokens: $files->stockTokens,
			internalScopes: ($files->internalScopes ?? new InternalScopesService(files: $files->runtimeFiles)),
			logger: $context->logger
		);

		return new TokenSetLayers(
			designSystemService: $context->designSystemService,
			config: $context->config,
			logoLayer: $files->logoLayer,
			entries: $entries
		);
	}//end setLayersFrom()

	/**
	 * Run one cascade layer, isolated from every other layer.
	 *
	 * WHY THIS EXISTS (nldesign#264)
	 * ------------------------------
	 * The layers below used to be plain sequential calls, and
	 * `ThemeInjectionListener` swallowed anything that escaped `inject()`. So a
	 * failure in ANY layer silently cancelled EVERY LATER LAYER. The observed
	 * case: layer 4 writes `css/custom-overrides.css` inside the app
	 * directory, which throws on a read-only or non-www-data-owned install —
	 * and that took custom fonts, the conditional hide-slogan / menu-labels
	 * stylesheets and the theme-preview banner down with it. The earlier
	 * layers were already in the page, so the instance looked correctly themed
	 * and only the LAST features were missing, which reads as "one feature is
	 * broken" rather than "injection aborted".
	 *
	 * A layer is presentation. One failing layer must degrade only itself, and
	 * must say so — the failure used to reach no log at any level.
	 *
	 * @param string $layer A short name for the layer, used in the log line.
	 * @param callable $work The layer body.
	 *
	 * @return void
	 */
	private function runLayer(string $layer, callable $work): void {
		try {
			$work();
		} catch (Throwable $e) {
			$this->context->logger->warning(
				'nldesign: stylesheet layer "' . $layer . '" was skipped; the rest of the cascade still ran.',
				[
					'app' => Application::APP_ID,
					'layer' => $layer,
					'exception' => $e,
				]
			);
		}
	}//end runLayer()

	/**
	 * Inject the full nldesign stylesheet cascade for a render context.
	 *
	 * A no-op when the context is gated out by the `themed_contexts`
	 * appconfig (see {@see isContextThemed()}). Otherwise this is the
	 * verbatim former `Application::injectThemeCSS()` body: design-system
	 * stylesheets in declared order, token set CSS, icon/error contrast
	 * fixes, the component scopes, custom overrides, custom fonts, then
	 * conditional hide-slogan/show-menu-labels/primary-lock stylesheets.
	 *
	 * @param string $context One of `user`/`login`/`guest`/`public`/`error`,
	 *                        or any other value (always themed — fail open).
	 * @param string|null $appId The rendered app, for its brand (openspec/specs/per-app-theming/spec.md).
	 *
	 * @return void
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 * @spec openspec/specs/custom-fonts/spec.md
	 * @spec openspec/specs/marianne-font/spec.md
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public function inject(string $context, ?string $appId = null): void {
		if ($this->isContextThemed(context: $context) === false) {
			return;
		}

		$page = $this->resolvePage(appId: $appId);
		if ($page === null) {
			return;
		}

		// EVERY LAYER IS INDEPENDENT: see runLayer() and nldesign#264.
		$this->injectStylesheetLayers(page: $page);
		$this->injectPageLayers(page: $page, appId: $appId);
	}//end inject()

	/**
	 * The active token set of this page and what it wears.
	 *
	 * The active set is the per-group resolution (group mapping, then the
	 * instance default). With no mapping configured this is the plain
	 * appconfig value, so behaviour is byte-identical to a single-tenant instance.
	 *
	 * This is the one PREREQUISITE, not a layer: every layer is a function of
	 * it, so there is nothing to degrade to if it fails. It is still isolated,
	 * so a resolver failure logs and renders the page unthemed instead of
	 * throwing into the listener's catch-all.
	 *
	 * @param string|null $appId The rendered app, for its brand.
	 *
	 * @return array{tokenSet: string, tokenSetMeta: array, designSystemId: mixed}|null The page, or null when unresolved.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	private function resolvePage(?string $appId): ?array {
		try {
			$resolved = $this->layers->brands->forApp(appId: $appId, styleId: self::LOGO_STYLE_ID);
			$tokenSet = $resolved['tokenSet'];
			$this->setLayers->useBrandLogo(brandLogo: $resolved['logo']);
			$tokenSetMeta = $this->context->designSystemService->getTokenSetMeta(tokenSetId: $tokenSet);
		} catch (Throwable $e) {
			$this->context->logger->warning(
				'nldesign: could not resolve the active token set; no stylesheet layer was injected.',
				['app' => Application::APP_ID, 'exception' => $e]
			);
			return null;
		}

		return ['tokenSet' => $tokenSet, 'tokenSetMeta' => $tokenSetMeta, 'designSystemId' => ($tokenSetMeta['design_system'] ?? 'nldesign')];
	}//end resolvePage()

	/**
	 * The stylesheet layers: 2/2b/3 design-system, Marianne, token and contrast
	 * layers; 4/4.1 custom overrides, then freeform custom CSS; 4.5 custom
	 * fonts; 5 the conditional stylesheets.
	 *
	 * @param array{tokenSet: string, tokenSetMeta: array, designSystemId: mixed} $page The page.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	private function injectStylesheetLayers(array $page): void {
		$tokenSet = $page['tokenSet'];
		$designSystemId = $page['designSystemId'];
		$this->runLayer(
			layer: 'design-system-styles',
			work: fn () => $this->injectDesignSystemStyles(designSystemId: $designSystemId, tokenSet: $tokenSet)
		);
		$this->runLayer(layer: 'override-styles', work: fn () => $this->injectOverrideStyles(tokenSet: $tokenSet, designSystemId: $designSystemId));
		$this->runLayer(layer: 'custom-font-link', work: fn () => $this->injectCustomFontLink(designSystemId: $designSystemId));
		$this->runLayer(layer: 'conditional-styles', work: fn () => $this->injectConditionalStyles(tokenSet: $tokenSet));
	}//end injectStylesheetLayers()

	/**
	 * The person the workplace top bar names, its apps menu and search words,
	 * then (6) the preview banner: only with a preview active for this user.
	 *
	 * @param array{tokenSet: string, tokenSetMeta: array, designSystemId: mixed} $page The page.
	 * @param string|null $appId The rendered app, for its search words.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	private function injectPageLayers(array $page, ?string $appId): void {
		$tokenSet = $page['tokenSet'];
		$this->runLayer(layer: 'header-user', work: fn () => $this->chrome->headerUser?->inject(tokenSet: $tokenSet, layoutOptions: $this->layoutOptions));
		$this->runLayer(layer: 'app-menu', work: fn () => $this->chrome->appMenu?->inject(tokenSet: $tokenSet, layoutOptions: $this->layoutOptions));
		$this->runLayer(layer: 'search-placeholder', work: fn () => $this->chrome->searchPlaceholder?->inject(appId: $appId));
		$this->runLayer(
			layer: 'preview-banner',
			work: fn () => $this->layers->previewBannerService->inject(tokenSet: $tokenSet, tokenSetMeta: $page['tokenSetMeta'])
		);
	}//end injectPageLayers()

	/**
	 * Emit the design-system stylesheets and, for every system that reads
	 * `--nldesign-*` variables, the token and contrast layers on top of them.
	 *
	 * @param string $designSystemId The resolved design system id.
	 * @param string $tokenSet The active token set id.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 * @spec openspec/specs/marianne-font/spec.md
	 */
	private function injectDesignSystemStyles(string $designSystemId, string $tokenSet): void {
		// Nextcloud prints every addStyle() stylesheet before every header. A
		// runtime layer is a header, so once one is emitted, the static layers
		// after it are linked as headers too, or they would move ahead of it.
		$linked = false;
		foreach ($this->setLayers->forSet(designSystemId: $designSystemId, tokenSet: $tokenSet) as $entry) {
			$linked = ($linked === true || $entry['kind'] === 'runtime');
			if ($linked === true && $entry['kind'] === 'file') {
				$this->emitStylesheetLink(url: $this->staticLayerUrl(file: (string)$entry['file']));
				continue;
			}

			$this->emitLayer(entry: $entry);
		}
	}//end injectDesignSystemStyles()

	/**
	 * The URL of a static app stylesheet, with the installed version as cache-buster.
	 *
	 * @param string $file The stylesheet path relative to `css/`, without extension.
	 *
	 * @return string The URL.
	 */
	private function staticLayerUrl(string $file): string {
		$version = $this->context->config->getAppValue(Application::APP_ID, 'installed_version', '0');

		return $this->context->urlGenerator->linkTo(appName: Application::APP_ID, file: 'css/' . $file . '.css') . '?v=' . rawurlencode($version);
	}//end staticLayerUrl()

	/**
	 * Emit one entry from {@see TokenSetLayers::forSet()}.
	 *
	 * @param array{layer: string, kind: string, file?: string, name?: string, css?: string, id?: string} $entry The layer entry.
	 *
	 * @return void
	 */
	private function emitLayer(array $entry): void {
		if ($entry['kind'] === 'inline') {
			$this->emitInlineStyle(css: (string)$entry['css'], id: ($entry['id'] ?? null));
			return;
		}

		if ($entry['kind'] === 'runtime') {
			$this->emitStylesheetLink(url: $this->files->runtimeFiles->routeUrl(name: (string)$entry['name']));
			return;
		}

		$this->emitStyle(file: (string)$entry['file']);
	}//end emitLayer()

	/**
	 * The stylesheet manifest for a token set: what the page would carry for
	 * it, resolved to URLs, so the admin panel can swap one set for another
	 * without a reload.
	 *
	 * Built from {@see TokenSetLayers::forSet()} — the list `inject()` emits
	 * — so the client never has to guess which `<link>`s belong to Thematiq or
	 * in which order they go. The set-independent layers (custom overrides,
	 * freeform CSS, the toggles) are not part of the manifest: they do not
	 * change when the set changes and stay where the server put them. Also
	 * includes the custom-font link, which exists only when the design system
	 * reads token variables and therefore appears or disappears with the set.
	 *
	 * The `href` values are the path the page's own `<link>`s carry (without
	 * the cache-busting query), plus a `?v=` derived from the installed app
	 * version so a freshly inserted link is not served from a stale cache.
	 * The client matches existing elements by pathname, never by query string.
	 *
	 * @param string $tokenSet The token set id (validated by the caller).
	 *
	 * @return array{
	 *     tokenSet: string,
	 *     designSystem: string,
	 *     layers: array<int, array{layer: string, kind: string, href?: string, css?: string, id?: string}>,
	 *     layout: array<string, bool|int|string|null>
	 * }
	 *
	 * @spec openspec/changes/apply-without-reload/specs/css-architecture/spec.md
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-layout-defaults
	 */
	public function getStylesheetManifest(string $tokenSet): array {
		$tokenSetMeta = $this->context->designSystemService->getTokenSetMeta(tokenSetId: $tokenSet);
		$designSystemId = (string)($tokenSetMeta['design_system'] ?? 'nldesign');
		$layers = array_map(
			fn (array $entry): array => $this->manifestLayer(entry: $entry),
			$this->setLayers->forSet(designSystemId: $designSystemId, tokenSet: $tokenSet)
		);

		return [
			'tokenSet' => $tokenSet,
			'designSystem' => $designSystemId,
			'layers' => array_merge($layers, $this->customFontLayers(designSystemId: $designSystemId)),
			// Not layers of the set: the two conditional stylesheets that can follow it.
			'layout' => $this->layoutOptions->resolved(tokenSet: $tokenSet),
		];
	}//end getStylesheetManifest()

	/**
	 * One set layer as the manifest carries it: inline CSS with its element
	 * id, or a stylesheet resolved to its URL.
	 *
	 * @param array{layer: string, kind: string, file?: string, name?: string, css?: string, id?: string} $entry The layer entry.
	 *
	 * @return array{layer: string, kind: string, href?: string, css?: string, id?: string}
	 *
	 * @spec openspec/changes/apply-without-reload/specs/css-architecture/spec.md
	 */
	private function manifestLayer(array $entry): array {
		if ($entry['kind'] === 'inline') {
			return ['layer' => $entry['layer'], 'kind' => 'inline', 'id' => (string)($entry['id'] ?? ''), 'css' => (string)$entry['css']];
		}

		if ($entry['kind'] === 'runtime') {
			return ['layer' => $entry['layer'], 'kind' => 'file', 'href' => $this->files->runtimeFiles->routeUrl(name: (string)$entry['name'])];
		}

		return ['layer' => $entry['layer'], 'kind' => 'file', 'href' => $this->staticLayerUrl(file: (string)$entry['file'])];
	}//end manifestLayer()

	/**
	 * The generated custom-fonts stylesheet (4.5), only for a design system
	 * that reads token variables, and only when fonts exist; so it appears or
	 * disappears with the set.
	 *
	 * @param string $designSystemId The resolved design system id.
	 *
	 * @return array<int, array{layer: string, kind: string, href: string}> Zero or one entry.
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	private function customFontLayers(string $designSystemId): array {
		if ($designSystemId === 'none' || $this->layers->fontService->hasFonts() === false) {
			return [];
		}

		$href = $this->context->urlGenerator->linkToRoute('thematiq.font.css') . '?v=' . $this->layers->fontService->getRevision();

		return [['layer' => 'custom-font', 'kind' => 'file', 'href' => $href]];
	}//end customFontLayers()

	/**
	 * Emit the admin-authored override layers: the always-present
	 * custom-overrides stylesheet, then the freeform custom CSS.
	 *
	 * @param string $tokenSet The token set this page renders.
	 * @param string $designSystemId The design system that set wears.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - CustomOverridesService::fileFor() is a pure lookup
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 */
	private function injectOverrideStyles(string $tokenSet, string $designSystemId): void {
		// 4. Custom overrides — admin-defined token overrides, always loaded last.
		//
		// A set on no design system has an overrides file of its own and never
		// loads the shared one, so nothing pinned on another theme can reach a
		// page that is meant to be Nextcloud plus that set — see
		// CustomOverridesService.
		$overridesFile = CustomOverridesService::fileFor(tokenSet: $tokenSet, designSystemId: $designSystemId);
		//
		// Kept in app data and linked only once saved: writing it on render
		// is what gave every instance a code integrity warning.
		$overridesName = 'css/' . $overridesFile . '.css';
		if ($this->files->runtimeFiles->store()->exists(name: $overridesName) === true) {
			$this->emitStylesheetLink(url: $this->files->runtimeFiles->routeUrl(name: $overridesName));
		}

		// 4.1 Freeform custom CSS — admin-authored arbitrary rules. Emitted
		// AFTER custom-overrides so administrator intent wins the cascade, and
		// only when the feature is switched on AND something is actually
		// stored, so an instance that never opts in loads nothing at all.
		if ($this->layers->customCssService->isEnabled() === true
			&& $this->layers->customCssService->hasContent() === true
		) {
			$this->emitStylesheetLink(url: $this->files->runtimeFiles->routeUrl(name: CustomCssService::FILE));
		}
	}//end injectOverrideStyles()

	/**
	 * Emit the generated custom-fonts stylesheet link, when the active design
	 * system reads token variables and at least one font is configured.
	 *
	 * @param string $designSystemId The resolved design system id.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	private function injectCustomFontLink(string $designSystemId): void {
		// 4.5 Custom fonts — admin-uploaded, self-hosted webfonts. Injected as
		// a <link rel="stylesheet"> (not \OCP\Util::addStyle(), because the
		// CSS is generated dynamically by FontController::css(), not a static
		// file under css/) AFTER the token-set styles so the font tokens win
		// the cascade, and only when at least one font is configured, so a
		// themed instance with zero uploaded fonts issues no extra request.
		foreach ($this->customFontLayers(designSystemId: $designSystemId) as $layer) {
			$this->emitStylesheetLink(url: $layer['href']);
		}
	}//end injectCustomFontLink()

	/**
	 * Emit the appconfig-gated hide-slogan, show-menu-labels, workplace-layout,
	 * brand-stripe and primary-lock stylesheets. The layout pair is resolved for
	 * this page's token set, which may carry its defaults ({@see LayoutOptionsService}).
	 *
	 * `primary-lock` is emitted LAST on purpose. It and `custom-overrides.css`
	 * both write `--nldesign-component-*` at `:root` with `!important`, so the
	 * later of the two wins — and while the setting is on, the brand primary is
	 * meant to beat a per-component value the admin stored earlier. Nothing is
	 * deleted: turning the setting off drops this layer and the stored values
	 * take effect again.
	 *
	 * @param string $tokenSet The token set this page renders with.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 * @spec openspec/specs/component-tokens/spec.md
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-the-light-layout-is-one-conditional-stylesheet
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/brand-stripe/spec.md#requirement-the-stripe-is-one-conditional-stylesheet
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md#requirement-each-newer-option-is-one-conditional-stylesheet
	 */
	private function injectConditionalStyles(string $tokenSet): void {
		// Headers, not addStyle(): Nextcloud prints every addStyle() stylesheet
		// before every header, and the saved overrides are a header, so as
		// addStyle() entries the toggles landed before custom-overrides. The
		// spec puts them after it (css-architecture, hide-slogan, menu-labels),
		// which is also where the admin page appends them on a toggle.
		if ($this->context->config->getAppValue(Application::APP_ID, 'hide_slogan', '0') === '1') {
			$this->emitStylesheetLink(url: $this->staticLayerUrl(file: 'hide-slogan'));
		}

		if ($this->context->config->getAppValue(Application::APP_ID, 'show_menu_labels', '0') === '1') {
			$this->emitStylesheetLink(url: $this->staticLayerUrl(file: 'show-menu-labels'));
		}

		foreach ($this->layoutOptions->stylesheets(tokenSet: $tokenSet) as $file) {
			$this->emitStylesheetLink(url: $this->staticLayerUrl(file: $file));
		}

		array_map(fn (array $row) => $this->emitInlineStyle(css: $row['css'], id: $row['id']), $this->layoutOptions->inlineStyles(tokenSet: $tokenSet));

		// A header, so it follows the overrides and custom CSS links: it must
		// come last, or a stored value would beat the lock.
		if ($this->context->config->getAppValue(Application::APP_ID, 'primary_drives_components', '0') === '1') {
			$this->emitStylesheetLink(url: $this->staticLayerUrl(file: 'primary-lock'));
		}
	}//end injectConditionalStyles()

	/**
	 * The `id` the logo `<style>` carries on the page.
	 *
	 * A `<link>` can be found again by its href; an inline `<style>` has
	 * nothing to be found by, so it gets a fixed id and the client replaces it
	 * by that id when the set changes.
	 *
	 * @var string
	 */
	public const string LOGO_STYLE_ID = 'nldesign-logo-url';

	/**
	 * The `id` the dark logo `<style>` carries on the page (see
	 * {@see LogoLayerService::darkLayer()}), on the same contract as
	 * {@see self::LOGO_STYLE_ID}.
	 *
	 * @var string
	 */
	public const string DARK_LOGO_STYLE_ID = 'thematiq-dark-logo-url';

	/**
	 * The token set that means "look like this Nextcloud", and therefore the
	 * one set that cannot be described by a file checked into this repository.
	 *
	 * @var string
	 */
	public const string STOCK_TOKEN_SET = TokenSetLayers::STOCK_TOKEN_SET;

	/**
	 * The `id` the resolved stock-tokens `<style>` carries on the page.
	 *
	 * Same contract as {@see self::LOGO_STYLE_ID}: an inline block has no href
	 * to be found by, so the client replaces it by id when the set changes.
	 *
	 * @var string
	 */
	public const string STOCK_TOKENS_STYLE_ID = TokenSetLayerEntries::STOCK_TOKENS_STYLE_ID;

	/**
	 * The id of the inline `<style>` carrying the internal scopes.
	 */
	public const string INTERNAL_SCOPES_STYLE_ID = TokenSetLayerEntries::INTERNAL_SCOPES_STYLE_ID;

	/**
	 * Emit one inline `<style>` block into the page head.
	 *
	 * Indirected for the same reason as `emitStyle()`: it is a side effect on a
	 * Nextcloud static, and a test can capture it only if it is overridable.
	 *
	 * @param string $css The stylesheet body.
	 * @param string|null $id Element id, so the client can find and replace this
	 *                        exact block when a set is applied without a reload.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) \OCP\Util::addHeader() is the Nextcloud API for header injection.
	 *
	 * @spec openspec/specs/app-token-set-selection/spec.md
	 */
	protected function emitInlineStyle(string $css, ?string $id = null): void {
		$attributes = [];
		if ($id !== null && $id !== '') {
			$attributes['id'] = $id;
		}

		\OCP\Util::addHeader(tag: 'style', attributes: $attributes, text: $css);
	}//end emitInlineStyle()

	/**
	 * Whether a render context must receive nldesign CSS.
	 *
	 * A context outside {@see VALID_CONTEXTS} (an unmapped or future
	 * `renderAs` value) is never gated by configuration — it always resolves
	 * to themed, so a forward-compatibility gap can never silently strip
	 * theming. For a recognized context, an absent, empty, or unparseable
	 * `themed_contexts` value themes ALL contexts (byte-identical to the
	 * previous boot-time injection); a non-empty valid list themes only the
	 * contexts it names.
	 *
	 * @param string $context The render context to check.
	 *
	 * @return bool True when the context must receive nldesign CSS.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 */
	public function isContextThemed(string $context): bool {
		if (in_array($context, self::VALID_CONTEXTS, true) === false) {
			return true;
		}

		$raw = $this->context->config->getAppValue(Application::APP_ID, self::THEMED_CONTEXTS_KEY, '[]');
		$decoded = json_decode($raw, true);
		if (is_array($decoded) === false || empty($decoded) === true) {
			return true;
		}

		return in_array($context, $decoded, true);
	}//end isContextThemed()

	/**
	 * Emit a static nldesign stylesheet via the Nextcloud style registry.
	 *
	 * @param string $file The stylesheet path relative to `css/` (no extension).
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - \OCP\Util::addStyle() is the Nextcloud API for CSS injection
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 */
	protected function emitStyle(string $file): void {
		\OCP\Util::addStyle(application: Application::APP_ID, file: $file);
	}//end emitStyle()

	/**
	 * Emit a stylesheet served by a route, not a static `css/` file, as a `<link>`
	 * header: the generated custom-fonts stylesheet and every runtime file.
	 *
	 * @param string $url The route URL, with its `?v=` revision.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - \OCP\Util::addHeader() is the Nextcloud API for header injection
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	protected function emitStylesheetLink(string $url): void {
		\OCP\Util::addHeader(
			tag: 'link',
			attributes: [
				'rel' => 'stylesheet',
				'href' => $url,
			]
		);
	}//end emitStylesheetLink()

}//end class
