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
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileLocator;
use OCP\IConfig;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;
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
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) - this class IS the cascade: one branch per layer the page may emit, and the order of
 *   those branches is the specification. Splitting it would spread the load order across files, which is the defect the single
 *   designSystemLayers() list exists to prevent.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) - for the same reason: every collaborator here answers "does this layer load, and with
 *   what". A layer whose source lived behind a facade would be a layer whose position in the cascade is decided somewhere other than
 *   designSystemLayers(), which is exactly what this class exists to prevent.
 */
class CssInjectionService {

	/**
	 * The appconfig key holding the JSON render-context allow-list.
	 *
	 * @var string
	 */
	private const THEMED_CONTEXTS_KEY = 'themed_contexts';

	/**
	 * The render-context names the `themed_contexts` allow-list recognizes.
	 *
	 * Any other context value (e.g. an unmapped/future `renderAs`) always
	 * fails open to themed — see {@see isContextThemed()}.
	 *
	 * @var string[]
	 */
	private const VALID_CONTEXTS = ['user', 'login', 'guest', 'public', 'error'];

	/**
	 * The application configuration service.
	 *
	 * @var IConfig
	 */
	private IConfig $config;

	/**
	 * Resolves which design system a token set uses and its stylesheet order.
	 *
	 * @var DesignSystemService
	 */
	private DesignSystemService $designSystemService;

	/**
	 * Gates and reads the freeform custom CSS layer.
	 *
	 * @var CustomCssService
	 */
	private CustomCssService $customCssService;

	/**
	 * Resolves admin-uploaded custom fonts.
	 *
	 * @var FontService
	 */
	private FontService $fontService;

	/**
	 * Builds the URL to the generated custom-fonts stylesheet route.
	 *
	 * @var IURLGenerator
	 */
	private IURLGenerator $urlGenerator;

	/**
	 * Resolves the effective token set for the requesting user (per-group
	 * mapping, falling back to the instance default).
	 *
	 * @var GroupThemingService
	 */
	private GroupThemingService $groupThemingService;

	/**
	 * Injects the admin theme-preview banner (assets + initial state).
	 *
	 * @var ThemePreviewBannerService
	 */
	private ThemePreviewBannerService $previewBannerService;

	/**
	 * Records a layer that failed, so a skipped layer is never silent.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Resolves the `nextcloud` set from the running instance rather than from
	 * the shipped snapshot of it.
	 *
	 * @var StockTokensService
	 */
	private StockTokensService $stockTokens;

	/**
	 * @var RuntimeFileLocator Finds runtime files: uploaded sets, dark variants, overrides, custom CSS.
	 */
	private RuntimeFileLocator $runtimeFiles;

	/** @var AppBrandService The brand per app (openspec/specs/per-app-theming/spec.md). */
	private AppBrandService $appBrands;

	/** @var array<string, string>|null The rendered app's logo layer for this request, when its brand applies. */
	private ?array $brandLogo = null;

	/**
	 * Builds the rules that carry a set's internal tokens onto their components.
	 *
	 * @var InternalScopesService
	 */
	private InternalScopesService $internalScopes;

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The config service.
	 * @param DesignSystemService $designSystemService The design system resolver.
	 * @param CustomCssService $customCssService The freeform custom CSS service.
	 * @param FontService $fontService The custom font resolver.
	 * @param IURLGenerator $urlGenerator The URL generator.
	 * @param GroupThemingService $groupThemingService The per-group token-set resolver.
	 * @param ThemePreviewBannerService $previewBannerService The theme-preview banner injector.
	 * @param LoggerInterface $logger The logger for skipped layers.
	 * @param StockTokensService $stockTokens Resolves the `nextcloud` set from the running instance.
	 * @param RuntimeFileLocator $runtimeFiles Finds files thematiq wrote at runtime, which live in app data.
	 * @param LogoLayerService $logoLayer Resolves the active set's logo layer.
	 * @param AppBrandService $appBrands The brand per app.
	 * @param InternalScopesService|null $internalScopes Builds the internal scopes; defaults to one reading through $runtimeFiles.
	 *
	 * @SuppressWarnings(PHPMD.ExcessiveParameterList) - Nextcloud's container injects through the constructor and nothing else, so the
	 *   parameter count is the collaborator count; see the class note on why that count is what it is.
	 */
	public function __construct(
		IConfig $config,
		DesignSystemService $designSystemService,
		CustomCssService $customCssService,
		FontService $fontService,
		IURLGenerator $urlGenerator,
		GroupThemingService $groupThemingService,
		ThemePreviewBannerService $previewBannerService,
		LoggerInterface $logger,
		StockTokensService $stockTokens,
		RuntimeFileLocator $runtimeFiles,
		private readonly LogoLayerService $logoLayer,
		AppBrandService $appBrands,
		?InternalScopesService $internalScopes = null,
	) {
		$this->config = $config;
		$this->designSystemService = $designSystemService;
		$this->customCssService = $customCssService;
		$this->fontService = $fontService;
		$this->urlGenerator = $urlGenerator;
		$this->groupThemingService = $groupThemingService;
		$this->previewBannerService = $previewBannerService;
		$this->logger = $logger;
		$this->stockTokens = $stockTokens;
		$this->runtimeFiles = $runtimeFiles;
		$this->appBrands = $appBrands;
		$this->internalScopes = ($internalScopes ?? new InternalScopesService(files: $runtimeFiles));
	}//end __construct()

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
			$this->logger->warning(
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

		// The active set is the per-group resolution (group mapping → instance
		// default). With no mapping configured this is the plain appconfig
		// value, so behaviour is byte-identical to a single-tenant instance.
		//
		// This block is the one PREREQUISITE, not a layer: every layer below
		// is a function of it, so there is nothing to degrade to if it fails.
		// It is still isolated, so a resolver failure logs and renders the
		// page unthemed instead of throwing into the listener's catch-all.
		try {
			$brand = $this->appBrands->brandFor(appId: $appId);
			$tokenSet = $this->groupThemingService->resolveTokenSetForRequest(appBrandSet: ($brand['tokenSet'] ?? null));
			$this->brandLogo = $this->appBrands->logoLayer(brand: $brand, tokenSet: $tokenSet, styleId: self::LOGO_STYLE_ID);

			$tokenSetMeta = $this->designSystemService->getTokenSetMeta(tokenSetId: $tokenSet);
			$designSystemId = $tokenSetMeta['design_system'] ?? 'nldesign';
		} catch (Throwable $e) {
			$this->logger->warning(
				'nldesign: could not resolve the active token set; no stylesheet layer was injected.',
				[
					'app' => Application::APP_ID,
					'exception' => $e,
				]
			);
			return;
		}

		// EVERY LAYER BELOW IS INDEPENDENT — see runLayer() and nldesign#264.
		// One failing layer must not be able to cancel the layers after it.
		// 2/2b/3. Design-system stylesheets, Marianne, token + contrast layers.
		$this->runLayer(
			layer: 'design-system-styles',
			work: fn () => $this->injectDesignSystemStyles(designSystemId: $designSystemId, tokenSet: $tokenSet)
		);

		// 4/4.1. Custom overrides, then freeform custom CSS.
		$this->runLayer(
			layer: 'override-styles',
			work: fn () => $this->injectOverrideStyles(tokenSet: $tokenSet, designSystemId: $designSystemId)
		);

		// 4.5. Custom fonts.
		$this->runLayer(
			layer: 'custom-font-link',
			work: fn () => $this->injectCustomFontLink(designSystemId: $designSystemId)
		);

		// 5. Conditional stylesheets.
		$this->runLayer(layer: 'conditional-styles', work: fn () => $this->injectConditionalStyles());

		// 6. Preview banner — ONLY when a theme preview is active for this
		// request's user. Every other user (and every anonymous render) pays
		// nothing: no script, no style, no initial state.
		$this->runLayer(
			layer: 'preview-banner',
			work: fn () => $this->previewBannerService->inject(tokenSet: $tokenSet, tokenSetMeta: $tokenSetMeta)
		);
	}//end inject()

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
		foreach ($this->designSystemLayers(designSystemId: $designSystemId, tokenSet: $tokenSet) as $entry) {
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
		$version = $this->config->getAppValue(Application::APP_ID, 'installed_version', '0');

		return $this->urlGenerator->linkTo(appName: Application::APP_ID, file: 'css/' . $file . '.css') . '?v=' . rawurlencode($version);
	}//end staticLayerUrl()

	/**
	 * The set-dependent part of the cascade, as DATA.
	 *
	 * Every layer whose presence or content depends on the active token set
	 * (or on the design system that set belongs to) is listed here, in cascade
	 * order, and {@see self::injectDesignSystemStyles()} emits the list
	 * verbatim. {@see self::getStylesheetManifest()} hands the SAME list to the
	 * admin panel, which is how a set can be applied to the page the admin is
	 * looking at without a reload: the client removes the elements this list
	 * produced for the old set and inserts the ones it produces for the new
	 * one. One owner for the cascade, so the two can never disagree about
	 * which files make up a set.
	 *
	 * The set-INDEPENDENT layers — custom-overrides.css, custom-css.css, the
	 * hide-slogan / show-menu-labels toggles, the preview banner — are
	 * deliberately not here: they are emitted after this list by `inject()`,
	 * do not change when the set changes, and must keep their position AFTER
	 * the set layers so an admin's overrides still win.
	 *
	 * Returns ordered entries: `kind` is `file` (a stylesheet under `css/`, `file` without
	 * extension) or `inline` (a `<style>` block, `css`, with the element `id` the client
	 * replaces).
	 *
	 * @param string $designSystemId The resolved design system id.
	 * @param string $tokenSet The token set id.
	 *
	 * @return array<int, array{layer: string, kind: string, file?: string, name?: string, css?: string, id?: string}>
	 *
	 * @SuppressWarnings(PHPMD.NPathComplexity) - the path count IS the number of
	 *   layer combinations a page can emit, and each branch here is one
	 *   documented condition. Extracting halves would move part of the load
	 *   order out of the one list that states it.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 * @spec openspec/changes/apply-without-reload/specs/css-architecture/spec.md
	 */
	private function designSystemLayers(string $designSystemId, string $tokenSet): array {
		$designSystem = $this->designSystemService->getDesignSystem(id: $designSystemId);
		$layers = [];

		// 2. Load design system stylesheets in declared order.
		// For "none" (stock Nextcloud) this array is empty — no CSS loads.
		foreach ($designSystem['stylesheets'] as $stylesheet) {
			$layers[] = ['layer' => 'design-system', 'kind' => 'file', 'file' => $stylesheet];
		}

		// 2b. Marianne (French State typeface) — gated, inert-by-default
		// self-hosted font layer, emitted directly after the design-system
		// stylesheets above (which already include the base
		// systems/lasuite/fonts layer) so its real @font-face declarations
		// exist. See marianneLayer() for the gate condition.
		$marianne = $this->marianneLayer(designSystemId: $designSystemId);
		if ($marianne !== null) {
			$layers[] = $marianne;
		}

		// 3. Load token values (only when a design system reads --nldesign-* vars).
		if ($designSystemId === 'none') {
			return array_merge($layers, $this->noDesignSystemLayers(tokenSet: $tokenSet));
		}

		// 3a-1. The `nextcloud` set normally takes the `none` branch above. It
		// reaches this line only when its metadata could not be read and the
		// design system defaulted, and then the resolved block still beats the
		// shipped snapshot. See stockTokenLayer().
		$tokenLayer = $this->fileLayer(layer: 'tokens', file: 'tokens/' . $tokenSet);
		if ($tokenSet === self::STOCK_TOKEN_SET) {
			$tokenLayer = ($this->stockTokenLayer() ?? $tokenLayer);
		}

		$layers[] = $tokenLayer;

		// 3a0. The logo as an ABSOLUTE url, overriding the relative one the token
		// file declares. See LogoLayerService::layer() — a relative url() inside a custom
		// property is resolved against the stylesheet that USES it, and the use
		// sites sit at different depths.
		$logo = ($this->brandLogo ?? $this->logoLayer->layer(tokenSet: $tokenSet));
		if ($logo !== null) {
			$layers[] = $logo;
		}

		// 3a. Element overrides belonging to this token set, directly after its
		// tokens so they win the cascade over the design system's shared
		// element-overrides.css (emitted in step 2). Kept OUT of the token file
		// because a shipped token file is exactly one flat `:root { }` block and
		// the scoped-application contract depends on that shape.
		if (is_file($this->appPath() . '/css/token-overrides/' . $tokenSet . '.css') === true) {
			$layers[] = ['layer' => 'token-overrides', 'kind' => 'file', 'file' => 'token-overrides/' . $tokenSet];
		}

		// 3b. Generated dark-mode variant, directly after the light layer
		// so its media-query/attribute-scoped rules override it — only
		// when the toggle is on AND a generated file exists for this set.
		// A disabled toggle or a set without a variant adds nothing.
		$withDark = $this->hasDarkVariantLayer(tokenSet: $tokenSet);
		if ($withDark === true) {
			$layers[] = $this->fileLayer(layer: 'dark-variant', file: 'tokens/dark/' . $tokenSet);
		}

		// Functional contrast fix shared by all design systems: app icons
		// that carry their white fill on <path> vanish on light surfaces
		// in the NC 34 app-management list (see css/icon-contrast.css).
		$layers[] = ['layer' => 'contrast', 'kind' => 'file', 'file' => 'icon-contrast'];
		// Functional contrast fix shared by all design systems: our error
		// fill is a saturated brand red where Nextcloud's is pale, so the
		// components painting --color-error-text on it lose all contrast
		// (see css/error-contrast.css).
		$layers[] = ['layer' => 'contrast', 'kind' => 'file', 'file' => 'error-contrast'];

		// 3.9. Component scopes, last of the set layers: it captures the `:root`
		// declarations the layers above make, and the admin's own overrides come
		// after it and are allowed to move the brand values those captures resolve
		// to. It rides with the set layers rather than beside them so `none` stays
		// stock — that branch returns above — and so the manifest carries it, which
		// is what lets the client add and remove it without a reload.
		$layers[] = ['layer' => 'theme-scopes', 'kind' => 'file', 'file' => 'theme-scopes'];
		$layers[] = ['layer' => 'component-scopes', 'kind' => 'file', 'file' => 'component-scopes'];

		return array_merge(
			$layers,
			$this->internalScopesLayer(tokenSet: $tokenSet, designSystemId: $designSystemId, withDark: $withDark)
		);
	}//end designSystemLayers()

	/**
	 * The set layers of a token set on the `none` design system.
	 *
	 * STOCK STILL GETS THE COMPONENT LAYER, and that is the difference between
	 * a themable instance and an inert one.
	 *
	 * `none` means "no design system", not "the app does nothing". Every
	 * instance STARTS on the stock `nextcloud` set, so without this the token
	 * editor and the playground could not move a single colour until an admin
	 * had already picked some other theme — you had to have a theme before you
	 * could make one.
	 *
	 * Emitting it costs nothing visually. This layer only REDIRECTS Nextcloud's
	 * own variables inside a component's subtree, and every component token is
	 * undeclared until somebody sets one, so an untouched stock instance
	 * resolves each redirect straight back to the captured global and renders
	 * byte-identically to stock. What it buys is that the overrides file —
	 * which is emitted after this list whatever the design system — finally has
	 * something reading the tokens it writes.
	 *
	 * A CUSTOM set on `none` is a theme saved off stock Nextcloud, and its file
	 * is the only place its values live — the header colour an admin set and
	 * saved as a theme, for one. The stock set itself has no file to load: it
	 * IS the running Nextcloud. Leaving the custom file out too put the saved
	 * theme in the dropdown and none of it on the page.
	 *
	 * THE STOCK SET'S TOKENS ARE THE INSTANCE'S OWN. Its `--nldesign-*` values
	 * are resolved from the running theme (see stockTokenLayer()), so the page
	 * declares what this Nextcloud actually wears. Nothing on a `none` page
	 * paints from them, so stock still renders as stock; what reads them is the
	 * admin panel, which compares the live values against the set an admin is
	 * about to apply. When the instance cannot be read nothing is emitted:
	 * the shipped snapshot is never the stock set's layer (thematiq#620).
	 *
	 * @param string $tokenSet The token set id.
	 *
	 * @return array<int, array{layer: string, kind: string, file?: string, name?: string, css?: string, id?: string}>
	 *                                                                                                                 The entries, in cascade order.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 * @spec openspec/changes/component-playground/specs/nextcloud-variable-mapping/spec.md
	 */
	private function noDesignSystemLayers(string $tokenSet): array {
		$layers = [];
		if ($tokenSet !== self::STOCK_TOKEN_SET) {
			$layers[] = $this->fileLayer(layer: 'tokens', file: 'tokens/' . $tokenSet);
		}

		if ($tokenSet === self::STOCK_TOKEN_SET) {
			$stock = $this->stockTokenLayer();
			if ($stock !== null) {
				$layers[] = $stock;
			}
		}

		$layers[] = ['layer' => 'theme-scopes', 'kind' => 'file', 'file' => 'theme-scopes'];
		$layers[] = ['layer' => 'component-scopes', 'kind' => 'file', 'file' => 'component-scopes'];

		return array_merge(
			$layers,
			// This branch injects no dark variant, so none is read for the scopes either.
			$this->internalScopesLayer(tokenSet: $tokenSet, designSystemId: 'none', withDark: false)
		);
	}//end noDesignSystemLayers()

	/**
	 * The internal scopes as an inline layer, after the component scopes, or
	 * nothing when the set and the overrides give no internal token a value.
	 *
	 * Its own failure degrades only itself: the set's other layers still emit.
	 *
	 * @param string $tokenSet The token set.
	 * @param string $designSystemId The design system the set wears.
	 * @param bool $withDark Whether the set's dark variant is part of the cascade.
	 *
	 * @return array<int, array{layer: string, kind: string, css: string, id: string}> Zero or one entry.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 */
	private function internalScopesLayer(string $tokenSet, string $designSystemId, bool $withDark): array {
		try {
			$css = $this->internalScopes->forSet(tokenSet: $tokenSet, designSystemId: $designSystemId, withDark: $withDark);
		} catch (Throwable $e) {
			$this->logger->warning(
				'thematiq: the internal scopes layer was skipped; the rest of the cascade still ran.',
				['app' => Application::APP_ID, 'exception' => $e]
			);
			return [];
		}

		if ($css === '') {
			return [];
		}

		return [['layer' => 'internal-scopes', 'kind' => 'inline', 'css' => $css, 'id' => self::INTERNAL_SCOPES_STYLE_ID]];
	}//end internalScopesLayer()

	/**
	 * The `nextcloud` set's token layer, resolved from the running instance.
	 *
	 * The `nextcloud` set is the one set whose values belong to something
	 * else: it exists to reproduce the appearance of the running instance, and
	 * that appearance changes with every Nextcloud release. A shipped file can
	 * only ever hold a snapshot of one version, so it is resolved from the
	 * instance instead. See StockTokensService for the measurements and for
	 * why a stylesheet cannot do this with var().
	 *
	 * @return array{layer: string, kind: string, css: string, id: string}|null The inline
	 *                                                                          layer, or null when the instance could not be read.
	 *
	 * @spec openspec/changes/component-playground/specs/nextcloud-variable-mapping/spec.md
	 */
	private function stockTokenLayer(): ?array {
		$css = $this->stockTokens->getCss();
		if ($css === null) {
			return null;
		}

		return [
			'layer' => 'tokens',
			'kind' => 'inline',
			'css' => $css,
			'id' => self::STOCK_TOKENS_STYLE_ID,
		];
	}//end stockTokenLayer()

	/**
	 * Emit one entry from {@see self::designSystemLayers()}.
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
			$this->emitStylesheetLink(url: $this->runtimeFiles->routeUrl(name: (string)$entry['name']));
			return;
		}

		$this->emitStyle(file: (string)$entry['file']);
	}//end emitLayer()

	/**
	 * A stylesheet layer: a shipped static file, or a runtime file linked by route.
	 *
	 * @param string $layer The layer name.
	 * @param string $file The stylesheet path relative to `css/`, without extension.
	 *
	 * @return array{layer: string, kind: string, file?: string, name?: string} The layer entry.
	 */
	private function fileLayer(string $layer, string $file): array {
		$name = 'css/' . $file . '.css';
		if ($this->runtimeFiles->inStore(name: $name) === true) {
			return ['layer' => $layer, 'kind' => 'runtime', 'name' => $name];
		}

		return ['layer' => $layer, 'kind' => 'file', 'file' => $file];
	}//end fileLayer()

	/**
	 * The stylesheet manifest for a token set: what the page would carry for
	 * it, resolved to URLs, so the admin panel can swap one set for another
	 * without a reload.
	 *
	 * Built from {@see self::designSystemLayers()} — the list `inject()` emits
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
	 *     layers: array<int, array{layer: string, kind: string, href?: string, css?: string, id?: string}>
	 * }
	 *
	 * @spec openspec/changes/apply-without-reload/specs/css-architecture/spec.md
	 */
	public function getStylesheetManifest(string $tokenSet): array {
		$tokenSetMeta = $this->designSystemService->getTokenSetMeta(tokenSetId: $tokenSet);
		$designSystemId = (string)($tokenSetMeta['design_system'] ?? 'nldesign');
		$layers = [];
		foreach ($this->designSystemLayers(designSystemId: $designSystemId, tokenSet: $tokenSet) as $entry) {
			if ($entry['kind'] === 'inline') {
				$layers[] = [
					'layer' => $entry['layer'],
					'kind' => 'inline',
					'id' => (string)($entry['id'] ?? ''),
					'css' => (string)$entry['css'],
				];
				continue;
			}

			if ($entry['kind'] === 'runtime') {
				$layers[] = [
					'layer' => $entry['layer'],
					'kind' => 'file',
					'href' => $this->runtimeFiles->routeUrl(name: (string)$entry['name']),
				];
				continue;
			}

			$layers[] = ['layer' => $entry['layer'], 'kind' => 'file', 'href' => $this->staticLayerUrl(file: (string)$entry['file'])];
		}

		// 4.5 Custom fonts — see injectCustomFontLink(): only for a design
		// system that reads token variables, and only when fonts exist.
		if ($designSystemId !== 'none' && $this->fontService->hasFonts() === true) {
			$layers[] = [
				'layer' => 'custom-font',
				'kind' => 'file',
				'href' => $this->urlGenerator->linkToRoute('thematiq.font.css') . '?v=' . $this->fontService->getRevision(),
			];
		}

		return [
			'tokenSet' => $tokenSet,
			'designSystem' => $designSystemId,
			'layers' => $layers,
		];
	}//end getStylesheetManifest()

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
		if ($this->runtimeFiles->store()->exists(name: $overridesName) === true) {
			$this->emitStylesheetLink(url: $this->runtimeFiles->routeUrl(name: $overridesName));
		}

		// 4.1 Freeform custom CSS — admin-authored arbitrary rules. Emitted
		// AFTER custom-overrides so administrator intent wins the cascade, and
		// only when the feature is switched on AND something is actually
		// stored, so an instance that never opts in loads nothing at all.
		if ($this->customCssService->isEnabled() === true
			&& $this->customCssService->hasContent() === true
		) {
			$this->emitStylesheetLink(url: $this->runtimeFiles->routeUrl(name: CustomCssService::FILE));
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
		if ($designSystemId === 'none' || $this->fontService->hasFonts() === false) {
			return;
		}

		$cssUrl = $this->urlGenerator->linkToRoute('thematiq.font.css') . '?v=' . $this->fontService->getRevision();
		$this->emitStylesheetLink(url: $cssUrl);
	}//end injectCustomFontLink()

	/**
	 * Emit the appconfig-gated hide-slogan, show-menu-labels and primary-lock
	 * stylesheets.
	 *
	 * `primary-lock` is emitted LAST on purpose. It and `custom-overrides.css`
	 * both write `--nldesign-component-*` at `:root` with `!important`, so the
	 * later of the two wins — and while the setting is on, the brand primary is
	 * meant to beat a per-component value the admin stored earlier. Nothing is
	 * deleted: turning the setting off drops this layer and the stored values
	 * take effect again.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 * @spec openspec/specs/component-tokens/spec.md
	 */
	private function injectConditionalStyles(): void {
		// Headers, not addStyle(): Nextcloud prints every addStyle() stylesheet
		// before every header, and the saved overrides are a header, so as
		// addStyle() entries the toggles landed before custom-overrides. The
		// spec puts them after it (css-architecture, hide-slogan, menu-labels),
		// which is also where the admin page appends them on a toggle.
		if ($this->config->getAppValue(Application::APP_ID, 'hide_slogan', '0') === '1') {
			$this->emitStylesheetLink(url: $this->staticLayerUrl(file: 'hide-slogan'));
		}

		if ($this->config->getAppValue(Application::APP_ID, 'show_menu_labels', '0') === '1') {
			$this->emitStylesheetLink(url: $this->staticLayerUrl(file: 'show-menu-labels'));
		}

		// A header, so it follows the overrides and custom CSS links: it must
		// come last, or a stored value would beat the lock.
		if ($this->config->getAppValue(Application::APP_ID, 'primary_drives_components', '0') === '1') {
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
	public const LOGO_STYLE_ID = 'nldesign-logo-url';

	/**
	 * The token set that means "look like this Nextcloud", and therefore the
	 * one set that cannot be described by a file checked into this repository.
	 *
	 * @var string
	 */
	public const STOCK_TOKEN_SET = 'nextcloud';

	/**
	 * The `id` the resolved stock-tokens `<style>` carries on the page.
	 *
	 * Same contract as {@see self::LOGO_STYLE_ID}: an inline block has no href
	 * to be found by, so the client replaces it by id when the set changes.
	 *
	 * @var string
	 */
	public const STOCK_TOKENS_STYLE_ID = 'nldesign-stock-tokens';

	/**
	 * The id of the inline `<style>` carrying the internal scopes.
	 */
	public const INTERNAL_SCOPES_STYLE_ID = 'thematiq-internal-scopes';

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
	 * The app's own directory on disk.
	 *
	 * @return string The absolute app path.
	 *
	 * @spec openspec/specs/app-token-set-selection/spec.md
	 */
	protected function appPath(): string {
		return dirname(__DIR__, 2);
	}//end appPath()

	/**
	 * Add the generated dark-mode stylesheet, when ALL of: the `dark_variants`
	 * app config is enabled, and a generated `css/tokens/dark/{set}.css` file
	 * exists for the active set. A missing file or a disabled toggle simply
	 * adds nothing — never an error.
	 *
	 * @param string $tokenSet The active token set id.
	 *
	 * @return bool True when the dark-variant layer is part of this set's cascade.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function hasDarkVariantLayer(string $tokenSet): bool {
		$darkVariantsEnabled = ($this->config->getAppValue(Application::APP_ID, 'dark_variants', '1') === '1');
		if ($darkVariantsEnabled === false) {
			return false;
		}

		return $this->designSystemService->hasGeneratedDarkVariant(tokenSetId: $tokenSet);
	}//end hasDarkVariantLayer()

	/**
	 * Add the gated, self-hosted Marianne (French State typeface) stylesheet,
	 * when BOTH the active design system is `lasuite` AND an admin has
	 * acknowledged eligibility via the `marianne_enabled` appconfig flag
	 * (default `'0'`). While the flag is off — or the design system is not
	 * `lasuite` — this adds nothing, so no `@font-face` `url()` source for
	 * Marianne exists and the fonts.css family stack falls through to Inter.
	 *
	 * @param string $designSystemId The resolved design system id for the active token set.
	 *
	 * @return array{layer: string, kind: string, file: string}|null The layer entry, or null when gated off.
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 */
	private function marianneLayer(string $designSystemId): ?array {
		if ($designSystemId !== 'lasuite') {
			return null;
		}

		if ($this->config->getAppValue(Application::APP_ID, 'marianne_enabled', '0') !== '1') {
			return null;
		}

		return ['layer' => 'marianne', 'kind' => 'file', 'file' => 'systems/lasuite/marianne'];
	}//end marianneLayer()

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
	private function isContextThemed(string $context): bool {
		if (in_array($context, self::VALID_CONTEXTS, true) === false) {
			return true;
		}

		$raw = $this->config->getAppValue(Application::APP_ID, self::THEMED_CONTEXTS_KEY, '[]');
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
