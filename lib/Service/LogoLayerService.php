<?php

/**
 * The inline layer that says which logo the active token set wears.
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
 * @spec openspec/specs/app-token-set-selection/spec.md
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
 * Resolves the active set's logo into the `--nldesign-logo-url` layer.
 *
 * Moved out of {@see CssInjectionService} so that class stays within its
 * size limit; the injector still decides where in the cascade the layer goes.
 * A set's logo may be shipped in the release or uploaded with the set, in
 * which case it lives in app data and is served by the runtime file route.
 *
 * @spec openspec/specs/app-token-set-selection/spec.md
 */
class LogoLayerService {

	/**
	 * What a set's login watermark file is called, after the set id.
	 *
	 * @var string
	 */
	private const WATERMARK_SUFFIX = '-emblem-grey';

	/**
	 * What a bundle that masks the header logo reads when a logo exists.
	 *
	 * The La Suite element overrides, which Cunningham shares, paint
	 * `#header .logo` as a brand-coloured mask of Nextcloud's logo.svg with
	 * `background-image: none !important`, so a set's own logo or an admin's
	 * upload never showed there (#968). That rule reads these three variables,
	 * with the mask, the brand fill and no image as fallbacks. Declared only
	 * when a logo exists, so without one the Nextcloud mark stays. The nldesign
	 * bundle reads `--nldesign-logo-url` directly and ignores them.
	 */
	private const HEADER_LOGO_VARIABLES = '--nldesign-header-logo-image:var(--nldesign-logo-url);'
		. '--nldesign-header-logo-mask:none;'
		. '--nldesign-header-logo-fill:transparent';

	/**
	 * Constructor.
	 *
	 * @param IConfig $config Reads the theming app's uploaded-logo flags.
	 * @param IURLGenerator $urlGenerator Resolves core's logo.
	 * @param LoggerInterface $logger Logs an unresolvable core logo.
	 * @param RuntimeFileLocator $files Finds a shipped or uploaded logo.
	 *
	 * @spec openspec/specs/app-token-set-selection/spec.md
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly IURLGenerator $urlGenerator,
		private readonly LoggerInterface $logger,
		private readonly RuntimeFileLocator $files,
	) {
	}//end __construct()

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
	 *                                                                          nothing can be said about the logo (core's logo.svg unresolvable).
	 *
	 * @spec openspec/specs/app-token-set-selection/spec.md
	 */
	public function layer(string $tokenSet): ?array {
		// A converter-extracted logo may be any raster or vector type Nextcloud's
		// ImageManager accepts; a shipped set's is an .svg. First match wins.
		$relative = $this->shippedLogo(name: $tokenSet);

		// UNQUOTED on purpose, here and below. `Util::addHeader()` HTML-escapes
		// its text, so a quoted `url("…")` reaches the page as
		// `url(&quot;…&quot;)` and the declaration is invalid — measured in the
		// browser. An app path carries no spaces, parentheses or quotes, so
		// unquoted is both valid and safe.
		if ($relative !== null) {
			return $this->inlineLayer(
				css: ':root{--nldesign-logo-url:url(' . $this->files->url(name: $relative) . ');' . self::HEADER_LOGO_VARIABLES
					. $this->watermarkDeclaration(tokenSet: $tokenSet) . '}'
			);
		}

		// The same appconfig keys `ThemingDefaults` reads to decide whether a
		// custom logo exists at all.
		$hasUploadedLogo = ($this->config->getAppValue('theming', 'logoheaderMime', '') !== ''
			|| $this->config->getAppValue('theming', 'logoMime', '') !== '');
		if ($hasUploadedLogo === true) {
			return $this->inlineLayer(
				css: ':root{--nldesign-logo-url:var(--image-logoheader,var(--image-logo));'
					. '--nldesign-logo-filter:none;' . self::HEADER_LOGO_VARIABLES . '}'
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

		// A bundle that paints the mark in its own colour declares
		// --nldesign-header-mark-fill on the logo element: the La Suite element
		// overrides fill it with the brand, and this rule, which renders over
		// theirs, read only the header text colour, near-black there (#975).
		// The nldesign bundle declares none and keeps the header text colour.

		return $this->inlineLayer(
			css: '#nextcloud .logo{background-image:none!important;'
				. 'background-color:var(--nldesign-header-mark-fill,var(--nldesign-color-header-text,#333333))!important;'
				. 'filter:none!important;'
				. '-webkit-mask:' . $mask . ';'
				. 'mask:' . $mask . '}'
		);
	}//end layer()

	/**
	 * Re-declare the active set's DARK logo as an absolute url, in both dark
	 * scopes, for a set that ships `img/logos/<set>-dark.<ext>` next to its
	 * light logo.
	 *
	 * The generated dark file already names the dark logo, but as a RELATIVE
	 * url on body, and for the reason {@see self::layer()} explains that url
	 * resolves against the stylesheet that uses it: right from
	 * css/systems/<bundle>/, out of the app from css/token-overrides/. And
	 * `--nldesign-header-logo-image` is substituted where it is declared, on
	 * :root, so the La Suite header kept the LIGHT logo whatever body said
	 * (thematiq#1021). This layer restates both on body as absolute urls. It is
	 * emitted after the dark file, which uses the same selectors, so it wins.
	 *
	 * @param string $tokenSet The selected token set id.
	 *
	 * @return array{layer: string, kind: string, css: string, id: string}|null The inline layer, or null when the
	 *                                                                          set ships no light and dark logo pair.
	 *
	 * @spec openspec/specs/frankendesk-token-set/spec.md
	 */
	public function darkLayer(string $tokenSet): ?array {
		if ($this->shippedLogo(name: $tokenSet) === null) {
			return null;
		}

		$dark = $this->shippedLogo(name: $tokenSet . '-dark');
		if ($dark === null) {
			return null;
		}

		$href = $this->files->url(name: $dark);
		if ($href === null || $href === '') {
			return null;
		}

		$url = 'url(' . $href . ')';
		$declarations = '{--nldesign-logo-url:' . $url . ';--nldesign-header-logo-image:' . $url . '}';

		return [
			'layer' => 'dark-logo-url',
			'kind' => 'inline',
			'css' => '@media (prefers-color-scheme: dark){'
				. 'body:not([data-theme-light]):not([data-theme-dark]):not([data-theme-light-highcontrast]):not([data-theme-dark-highcontrast])'
				. $declarations . '}'
				. 'body[data-theme-dark],body[data-themes*=dark]' . $declarations,
			'id' => CssInjectionService::DARK_LOGO_STYLE_ID,
		];
	}//end darkLayer()

	/**
	 * The login watermark of a set, as an absolute url, or nothing.
	 *
	 * A set that ships a grey emblem beside its logo, `img/logos/<set>-emblem-grey.svg`,
	 * names it as its login watermark in `--nldesign-login-watermark-image`. The
	 * token file spells that url relative to itself; the rule that draws it
	 * (css/workplace-layout.css) sits at another depth, which is the same
	 * problem the logo has, so the same layer carries the absolute url. A set
	 * without that file gets no declaration, and the watermark rule draws nothing.
	 *
	 * @param string $tokenSet The selected token set id.
	 *
	 * @return string One declaration starting with `;`, or the empty string.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-the-light-layout-may-draw-a-login-watermark
	 */
	private function watermarkDeclaration(string $tokenSet): string {
		$watermark = $this->shippedLogo(name: $tokenSet . self::WATERMARK_SUFFIX);
		if ($watermark === null) {
			return '';
		}

		return ';--nldesign-login-watermark-image:url(' . $this->files->url(name: $watermark) . ')';
	}//end watermarkDeclaration()

	/**
	 * The dark logo layer as a list the injector can append: none when an
	 * admin's brand logo replaces the set's logo in both modes, or when the
	 * set ships no dark logo (see {@see self::darkLayer()}).
	 *
	 * @param string $tokenSet The selected token set id.
	 * @param bool $brandLogo Whether an admin's brand logo is active.
	 *
	 * @return array<int, array{layer: string, kind: string, css: string, id: string}> Zero or one layer.
	 *
	 * @spec openspec/specs/frankendesk-token-set/spec.md
	 */
	public function darkLayers(string $tokenSet, bool $brandLogo): array {
		if ($brandLogo === true) {
			return [];
		}

		$layer = $this->darkLayer(tokenSet: $tokenSet);
		if ($layer === null) {
			return [];
		}

		return [$layer];
	}//end darkLayers()

	/**
	 * The first shipped or uploaded `img/logos/<name>.<ext>`, by the order
	 * {@see self::layer()} uses.
	 *
	 * @param string $name The file name without extension.
	 *
	 * @return string|null The app-relative path, or null when there is none.
	 */
	private function shippedLogo(string $name): ?string {
		foreach (['svg', 'png', 'jpg', 'gif', 'webp'] as $extension) {
			$candidate = 'img/logos/' . $name . '.' . $extension;
			if ($this->files->exists(name: $candidate) === true) {
				return $candidate;
			}
		}

		return null;
	}//end shippedLogo()

	/**
	 * Build the logo layer entry.
	 *
	 * @param string $css The stylesheet body.
	 *
	 * @return array{layer: string, kind: string, css: string, id: string} The entry.
	 */
	private function inlineLayer(string $css): array {
		return ['layer' => 'logo-url', 'kind' => 'inline', 'css' => $css, 'id' => CssInjectionService::LOGO_STYLE_ID];
	}//end inlineLayer()
}//end class
