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
 * wrapped in the protected `emitStyle()` / `emitFontLink()` seams purely so
 * unit tests can assert the exact stylesheet sequence via a partial mock —
 * the real static calls delegate to the server-private `\OC_Util`, which is
 * not resolvable outside a full Nextcloud bootstrap. Production code always
 * runs through these two one-line wrappers, so no behavior changes.
 *
 * @spec openspec/specs/css-architecture/spec.md
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
	 * Ensures the custom-overrides.css file exists before it is loaded.
	 *
	 * @var CustomOverridesService
	 */
	private CustomOverridesService $overridesService;

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
	 * Constructor.
	 *
	 * @param IConfig $config The config service.
	 * @param DesignSystemService $designSystemService The design system resolver.
	 * @param CustomOverridesService $overridesService The custom overrides file service.
	 * @param CustomCssService $customCssService The freeform custom CSS service.
	 * @param FontService $fontService The custom font resolver.
	 * @param IURLGenerator $urlGenerator The URL generator.
	 * @param GroupThemingService $groupThemingService The per-group token-set resolver.
	 * @param ThemePreviewBannerService $previewBannerService The theme-preview banner injector.
	 * @param LoggerInterface $logger The logger for skipped layers.
	 */
	public function __construct(
		IConfig $config,
		DesignSystemService $designSystemService,
		CustomOverridesService $overridesService,
		CustomCssService $customCssService,
		FontService $fontService,
		IURLGenerator $urlGenerator,
		GroupThemingService $groupThemingService,
		ThemePreviewBannerService $previewBannerService,
		LoggerInterface $logger,
	) {
		$this->config = $config;
		$this->designSystemService = $designSystemService;
		$this->overridesService = $overridesService;
		$this->customCssService = $customCssService;
		$this->fontService = $fontService;
		$this->urlGenerator = $urlGenerator;
		$this->groupThemingService = $groupThemingService;
		$this->previewBannerService = $previewBannerService;
		$this->logger = $logger;
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
	 * fixes, custom overrides, custom fonts, then conditional
	 * hide-slogan/show-menu-labels stylesheets.
	 *
	 * @param string $context One of `user`/`login`/`guest`/`public`/`error`,
	 *                        or any other value (always themed — fail open).
	 *
	 * @return void
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 * @spec openspec/specs/custom-fonts/spec.md
	 * @spec openspec/specs/marianne-font/spec.md
	 */
	public function inject(string $context): void {
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
			$tokenSet = $this->groupThemingService->resolveTokenSetForRequest();
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
		$this->runLayer(layer: 'override-styles', work: fn () => $this->injectOverrideStyles());

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
		foreach ($this->designSystemLayers(designSystemId: $designSystemId, tokenSet: $tokenSet) as $entry) {
			$this->emitLayer(entry: $entry);
		}
	}//end injectDesignSystemStyles()

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
	 * @param string $designSystemId The resolved design system id.
	 * @param string $tokenSet       The token set id.
	 *
	 * @return array<int, array{layer: string, kind: string, file?: string, css?: string, id?: string}>
	 *         Ordered entries: `kind` is `file` (a stylesheet under `css/`, `file` without extension)
	 *         or `inline` (a `<style>` block, `css`, with the element `id` the client replaces).
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
			return $layers;
		}

		$layers[] = ['layer' => 'tokens', 'kind' => 'file', 'file' => 'tokens/' . $tokenSet];
		// 3a0. The logo as an ABSOLUTE url, overriding the relative one the token
		// file declares. See logoUrlLayer() — a relative url() inside a custom
		// property is resolved against the stylesheet that USES it, and the use
		// sites sit at different depths.
		$logo = $this->logoUrlLayer(tokenSet: $tokenSet);
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
		if ($this->hasDarkVariantLayer(tokenSet: $tokenSet) === true) {
			$layers[] = ['layer' => 'dark-variant', 'kind' => 'file', 'file' => 'tokens/dark/' . $tokenSet];
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

		return $layers;
	}//end designSystemLayers()

	/**
	 * Emit one entry from {@see self::designSystemLayers()}.
	 *
	 * @param array{layer: string, kind: string, file?: string, css?: string, id?: string} $entry The layer entry.
	 *
	 * @return void
	 */
	private function emitLayer(array $entry): void {
		if ($entry['kind'] === 'inline') {
			$this->emitInlineStyle(css: (string)$entry['css'], id: ($entry['id'] ?? null));
			return;
		}

		$this->emitStyle(file: (string)$entry['file']);
	}//end emitLayer()

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
		$version = $this->config->getAppValue(Application::APP_ID, 'installed_version', '0');

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

			$layers[] = [
				'layer' => $entry['layer'],
				'kind' => 'file',
				'href' => $this->urlGenerator->linkTo(appName: Application::APP_ID, file: 'css/' . $entry['file'] . '.css')
					. '?v=' . rawurlencode($version),
			];
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
	 * @return void
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 */
	private function injectOverrideStyles(): void {
		// 4. Custom overrides — admin-defined token overrides, always loaded last.
		//
		// `ensureExists()` WRITES `css/custom-overrides.css` INSIDE THE APP
		// DIRECTORY, which is exactly the write a read-only or
		// root-owned-checkout deployment refuses (nldesign#264). It throws only
		// when the file is absent AND could not be created, so on failure there
		// is no file to link — emitting the tag anyway would add a guaranteed
		// 404 to every page. The freeform layer below is unrelated and still
		// runs, and the skip is logged rather than silent.
		$overridesReady = true;
		try {
			$this->overridesService->ensureExists();
		} catch (Throwable $e) {
			$overridesReady = false;
			$this->logger->warning(
				'nldesign: css/custom-overrides.css is absent and could not be created, so the custom-overrides '
				. 'layer was skipped. The app directory is not writable by the web server; generated CSS belongs '
				. 'in appdata (see nldesign#264).',
				[
					'app' => Application::APP_ID,
					'exception' => $e,
				]
			);
		}

		if ($overridesReady === true) {
			$this->emitStyle(file: 'custom-overrides');
		}

		// 4.1 Freeform custom CSS — admin-authored arbitrary rules. Emitted
		// AFTER custom-overrides so administrator intent wins the cascade, and
		// only when the feature is switched on AND something is actually
		// stored, so an instance that never opts in loads nothing at all.
		if ($this->customCssService->isEnabled() === true
			&& $this->customCssService->hasContent() === true
		) {
			$this->emitStyle(file: 'custom-css');
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
		$this->emitFontLink(url: $cssUrl);
	}//end injectCustomFontLink()

	/**
	 * Emit the appconfig-gated hide-slogan and show-menu-labels stylesheets.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 */
	private function injectConditionalStyles(): void {
		if ($this->config->getAppValue(Application::APP_ID, 'hide_slogan', '0') === '1') {
			$this->emitStyle(file: 'hide-slogan');
		}

		if ($this->config->getAppValue(Application::APP_ID, 'show_menu_labels', '0') === '1') {
			$this->emitStyle(file: 'show-menu-labels');
		}
	}//end injectConditionalStyles()

	/**
	 * Re-declare the active set's logo as an ABSOLUTE url.
	 *
	 * 🔴 A RELATIVE `url()` INSIDE A CUSTOM PROPERTY IS RESOLVED AGAINST THE
	 * STYLESHEET THAT USES IT, not the one that declares it. The token files
	 * declare `url('../../img/logos/<set>.svg')`, which is correct relative to
	 * `css/tokens/` — but the property is consumed in
	 * `css/systems/nldesign/theme.css` and in `css/token-overrides/*.css`, which
	 * sit at DIFFERENT depths, so no single relative path can be right for both.
	 *
	 * Measured: the browser asked for `…/css/img/logos/rijkshuisstijl.svg` (the
	 * `theme.css` depth) and got a 404. A 404 is re-requested on every
	 * recompute, including on an OS dark/light flip — which is why this
	 * presented itself as an e2e failure asserting that the OS switch issues no
	 * requests. The switch was pure CSS; a broken image was not.
	 *
	 * `linkTo()` resolves the install root, so this works under `custom_apps`
	 * and under `apps` without either being hard-coded.
	 *
	 * A SET THAT SHIPS NO LOGO GETS NEXTCLOUD'S OWN. `theme.css` blanks the
	 * stock logo (`background-image: var(--nldesign-logo-url, none)`) so a set's
	 * artwork can take its place; for the ~20 shipped sets with no
	 * `img/logos/<id>.svg` that resolved to `none`, and the header simply had a
	 * 56px hole where every stock installation shows the Nextcloud logo. There
	 * is no CSS-only fix: a `!important` declaration whose `var()` chain ends
	 * unresolved is still the winning declaration and computes to `unset`, so
	 * Nextcloud's own rule never comes back — and its fallback URL is relative
	 * to `core/css/server.css`, a depth this app cannot spell. So the fallback
	 * chain is emitted here, where the webroot is known: the theming app's own
	 * `--image-logoheader` / `--image-logo` first (an admin-uploaded logo is
	 * still an admin-uploaded logo), then core's `logo.svg`.
	 *
	 * WHICH fallback depends on whether the ADMIN uploaded a logo, which is why
	 * the branch is taken here and not in CSS:
	 *
	 *  - Uploaded logo → it is brand artwork and is shown as it is, through the
	 *    theming app's own `--image-logoheader` / `--image-logo`, with no filter.
	 *  - No uploaded logo → core's `logo.svg`, which is WHITE, drawn for
	 *    Nextcloud's dark-blue header. A `filter` cannot tint an image to an
	 *    arbitrary colour, and this app's shipped sets paint headers from white
	 *    (Rijkshuisstijl, Amsterdam, Cunningham) to saturated (Zwolle's blue,
	 *    Rotterdam's green), so no single filter is right for all of them. It
	 *    is MASKED instead — the SVG becomes the alpha channel and the
	 *    background paints `--nldesign-color-header-text`, which is by
	 *    definition the colour this set says is legible on its own header.
	 *    That is the technique
	 *    `css/systems/lasuite/element-overrides.css` already documents for the
	 *    same image.
	 *
	 * A set that ships its own artwork is not touched here at all and keeps
	 * whatever `--nldesign-logo-filter` its token file declares.
	 *
	 * @param string $tokenSet The selected token set id.
	 *
	 * @return array{layer: string, kind: string, css: string, id: string}|null The inline layer, or null when
	 *         nothing can be said about the logo (core's logo.svg unresolvable).
	 *
	 * @spec openspec/specs/app-token-set-selection/spec.md
	 */
	private function logoUrlLayer(string $tokenSet): ?array {
		// A converter-extracted logo may be any raster or vector type Nextcloud's
		// ImageManager accepts; a shipped set's is an .svg. First match wins.
		$relative = null;
		foreach (['svg', 'png', 'jpg', 'gif', 'webp'] as $extension) {
			$candidate = 'img/logos/' . $tokenSet . '.' . $extension;
			if (is_file($this->appPath() . '/' . $candidate) === true) {
				$relative = $candidate;
				break;
			}
		}

		// UNQUOTED on purpose, here and below. `Util::addHeader()` HTML-escapes
		// its text, so a quoted `url("…")` reaches the page as
		// `url(&quot;…&quot;)` and the declaration is invalid — measured in the
		// browser. An app path carries no spaces, parentheses or quotes, so
		// unquoted is both valid and safe.
		if ($relative !== null) {
			return $this->inlineLayer(
				css: ':root{--nldesign-logo-url:url('
					. $this->urlGenerator->linkTo(appName: Application::APP_ID, file: $relative)
					. ')}'
			);
		}

		// The same appconfig keys `ThemingDefaults` reads to decide whether a
		// custom logo exists at all.
		$hasUploadedLogo = ($this->config->getAppValue('theming', 'logoheaderMime', '') !== ''
			|| $this->config->getAppValue('theming', 'logoMime', '') !== '');
		if ($hasUploadedLogo === true) {
			return $this->inlineLayer(
				css: ':root{--nldesign-logo-url:var(--image-logoheader,var(--image-logo));'
					. '--nldesign-logo-filter:none}'
			);
		}

		// `imagePath()` THROWS when it cannot resolve the file, and this method
		// runs inside the design-system layer, so an unhandled throw here would
		// also cancel the dark-variant and contrast stylesheets emitted after
		// it. Without the URL there is nothing to mask, so the header keeps the
		// pre-existing empty slot rather than gaining a coloured block.
		try {
			$logo = $this->urlGenerator->imagePath(appName: 'core', file: 'logo/logo.svg');
		} catch (Throwable $e) {
			$this->logger->debug(
				'nldesign: core logo.svg could not be resolved, so the header keeps the active set\'s own (absent) logo.',
				[
					'app' => Application::APP_ID,
					'exception' => $e,
				]
			);
			return null;
		}

		$mask = 'url(' . $logo . ') no-repeat center / contain!important';

		return $this->inlineLayer(
			css: '#nextcloud .logo{background-image:none!important;'
				. 'background-color:var(--nldesign-color-header-text,#333333)!important;'
				. 'filter:none!important;'
				. '-webkit-mask:' . $mask . ';'
				. 'mask:' . $mask . '}'
		);
	}//end logoUrlLayer()

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
	 * Build the logo layer entry.
	 *
	 * @param string $css The stylesheet body.
	 *
	 * @return array{layer: string, kind: string, css: string, id: string} The entry.
	 */
	private function inlineLayer(string $css): array {
		return ['layer' => 'logo-url', 'kind' => 'inline', 'css' => $css, 'id' => self::LOGO_STYLE_ID];
	}//end inlineLayer()

	/**
	 * Emit one inline `<style>` block into the page head.
	 *
	 * Indirected for the same reason as `emitStyle()`: it is a side effect on a
	 * Nextcloud static, and a test can capture it only if it is overridable.
	 *
	 * @param string      $css The stylesheet body.
	 * @param string|null $id  Element id, so the client can find and replace this
	 *                         exact block when a set is applied without a reload.
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
	 * Emit the dynamically-generated custom-fonts stylesheet as a `<link>`
	 * header (not a static `css/` file, so `Util::addStyle()` cannot serve it).
	 *
	 * @param string $url The absolute URL to the generated fonts CSS route.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - \OCP\Util::addHeader() is the Nextcloud API for header injection
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	protected function emitFontLink(string $url): void {
		\OCP\Util::addHeader(
			tag: 'link',
			attributes: [
				'rel' => 'stylesheet',
				'href' => $url,
			]
		);
	}//end emitFontLink()
}//end class
