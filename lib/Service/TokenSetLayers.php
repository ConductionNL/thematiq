<?php

/**
 * The set-dependent part of the stylesheet cascade.
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
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\IConfig;

/**
 * The set-dependent part of the cascade, as DATA.
 *
 * Every layer whose presence or content depends on the active token set
 * (or on the design system that set belongs to) is listed by {@see self::forSet()},
 * in cascade order. {@see CssInjectionService::inject()} emits the list
 * verbatim and {@see CssInjectionService::getStylesheetManifest()} hands the
 * SAME list to the admin panel, which is how a set can be applied to the page
 * the admin is looking at without a reload: the client removes the elements
 * this list produced for the old set and inserts the ones it produces for the
 * new one. One owner for the cascade, so the two can never disagree about
 * which files make up a set.
 *
 * The set-INDEPENDENT layers (custom-overrides.css, custom-css.css, the
 * hide-slogan / show-menu-labels toggles, the preview banner) are
 * deliberately not here: they are emitted after this list by `inject()`,
 * do not change when the set changes, and must keep their position AFTER
 * the set layers so an admin's overrides still win.
 *
 * Entries: `kind` is `file` (a stylesheet under `css/`, `file` without
 * extension), `runtime` (a runtime file, `name`) or `inline` (a `<style>`
 * block, `css`, with the element `id` the client replaces).
 *
 * @spec openspec/specs/css-architecture/spec.md
 * @spec openspec/changes/apply-without-reload/specs/css-architecture/spec.md
 */
class TokenSetLayers {

	/**
	 * The token set that means "look like this Nextcloud", and therefore the
	 * one set that cannot be described by a file checked into this repository.
	 *
	 * @var string
	 */
	public const string STOCK_TOKEN_SET = 'nextcloud';

	/**
	 * Functional contrast fixes shared by all design systems.
	 *
	 * `icon-contrast`: app icons that carry their white fill on <path> vanish
	 * on light surfaces in the NC 34 app-management list. `error-contrast`:
	 * our error fill is a saturated brand red where Nextcloud's is pale, so
	 * the components painting --color-error-text on it lose all contrast.
	 *
	 * @var array<int, array{layer: string, kind: string, file: string}>
	 */
	private const array CONTRAST_LAYERS = [
		['layer' => 'contrast', 'kind' => 'file', 'file' => 'icon-contrast'],
		['layer' => 'contrast', 'kind' => 'file', 'file' => 'error-contrast'],
	];

	/**
	 * The theme and component scopes, last of the set layers.
	 *
	 * They capture the `:root` declarations the layers above make, and the
	 * admin's own overrides come after them and are allowed to move the brand
	 * values those captures resolve to. They ride with the set layers rather
	 * than beside them so the manifest carries them, which is what lets the
	 * client add and remove them without a reload.
	 *
	 * @var array<int, array{layer: string, kind: string, file: string}>
	 */
	private const array SCOPE_LAYERS = [
		['layer' => 'theme-scopes', 'kind' => 'file', 'file' => 'theme-scopes'],
		['layer' => 'component-scopes', 'kind' => 'file', 'file' => 'component-scopes'],
	];

	/**
	 * The rendered app's logo layer for this request, when its brand applies.
	 *
	 * @var array{layer: string, kind: string, css: string, id: string}|null
	 */
	private ?array $brandLogo = null;

	/**
	 * Constructor.
	 *
	 * @param DesignSystemService $designSystemService Resolves a design system's stylesheets and a set's dark variant.
	 * @param IConfig $config Reads the dark-variant and Marianne switches.
	 * @param LogoLayerService $logoLayer Resolves the active set's logo layers.
	 * @param TokenSetLayerEntries $entries Builds the single entries.
	 */
	public function __construct(
		private readonly DesignSystemService $designSystemService,
		private readonly IConfig $config,
		private readonly LogoLayerService $logoLayer,
		private readonly TokenSetLayerEntries $entries,
	) {
	}//end __construct()

	/**
	 * Use the rendered app's logo layer in place of the set's own, from now on.
	 *
	 * @param array{layer: string, kind: string, css: string, id: string}|null $brandLogo The brand's logo layer, or null when no brand applies.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public function useBrandLogo(?array $brandLogo): void {
		$this->brandLogo = $brandLogo;
	}//end useBrandLogo()

	/**
	 * The set layers of a token set on a design system, in cascade order.
	 *
	 * @param string $designSystemId The resolved design system id.
	 * @param string $tokenSet The token set id.
	 *
	 * @return array<int, array{layer: string, kind: string, file?: string, name?: string, css?: string, id?: string}>
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	public function forSet(string $designSystemId, string $tokenSet): array {
		$system = $this->systemLayers(designSystemId: $designSystemId);

		// 3. Token values load only when a design system reads --nldesign-* vars.
		if ($designSystemId === 'none') {
			return array_merge($system, $this->noDesignSystemLayers(tokenSet: $tokenSet));
		}

		$layers = array_merge($system, $this->tokenLayers(tokenSet: $tokenSet));
		// 3b. The generated dark variant, directly after the light layer so its
		// media-query/attribute-scoped rules override it.
		$withDark = $this->hasDarkVariantLayer(tokenSet: $tokenSet);
		if ($withDark === true) {
			$layers = array_merge($layers, $this->darkLayers(tokenSet: $tokenSet));
		}

		$scopes = $this->entries->internalScopes(tokenSet: $tokenSet, cascade: ['designSystemId' => $designSystemId, 'withDark' => $withDark]);

		return array_merge($layers, self::CONTRAST_LAYERS, self::SCOPE_LAYERS, $scopes);
	}//end forSet()

	/**
	 * The design system's stylesheets in declared order, then Marianne.
	 *
	 * For `none` (stock Nextcloud) the stylesheet list is empty.
	 *
	 * @param string $designSystemId The resolved design system id.
	 *
	 * @return array<int, array{layer: string, kind: string, file: string}>
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 * @spec openspec/specs/marianne-font/spec.md
	 */
	private function systemLayers(string $designSystemId): array {
		$layers = [];
		foreach ($this->designSystemService->getDesignSystem(id: $designSystemId)['stylesheets'] as $stylesheet) {
			$layers[] = ['layer' => 'design-system', 'kind' => 'file', 'file' => $stylesheet];
		}

		// 2b. Marianne, directly after the design-system stylesheets (which
		// already include the base systems/lasuite/fonts layer) so its real
		// @font-face declarations exist.
		if ($this->marianneEnabled(designSystemId: $designSystemId) === true) {
			$layers[] = ['layer' => 'marianne', 'kind' => 'file', 'file' => 'systems/lasuite/marianne'];
		}

		return $layers;
	}//end systemLayers()

	/**
	 * Whether the gated, self-hosted Marianne (French State typeface) loads:
	 * when BOTH the design system is `lasuite` AND an admin has acknowledged
	 * eligibility via the `marianne_enabled` appconfig flag (default `'0'`).
	 * Otherwise no `@font-face` `url()` source for Marianne exists and the
	 * fonts.css family stack falls through to Inter.
	 *
	 * @param string $designSystemId The resolved design system id.
	 *
	 * @return bool True when the Marianne layer is part of the cascade.
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 */
	private function marianneEnabled(string $designSystemId): bool {
		if ($designSystemId !== 'lasuite') {
			return false;
		}

		return $this->config->getAppValue(Application::APP_ID, 'marianne_enabled', '0') === '1';
	}//end marianneEnabled()

	/**
	 * The token layer, the logo, and the set's own element overrides.
	 *
	 * The `nextcloud` set normally takes the `none` branch; it reaches this
	 * only when its metadata could not be read and the design system
	 * defaulted, and then the resolved block still beats the shipped snapshot.
	 *
	 * The logo is an ABSOLUTE url overriding the relative one the token file
	 * declares (see LogoLayerService::layer()). The element overrides come
	 * directly after the tokens so they win over the design system's shared
	 * element-overrides.css; they are kept OUT of the token file because a
	 * shipped token file is exactly one flat `:root { }` block.
	 *
	 * @param string $tokenSet The token set id.
	 *
	 * @return array<int, array{layer: string, kind: string, file?: string, name?: string, css?: string, id?: string}>
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 */
	private function tokenLayers(string $tokenSet): array {
		$tokenLayer = $this->entries->file(layer: 'tokens', file: 'tokens/' . $tokenSet);
		if ($tokenSet === self::STOCK_TOKEN_SET) {
			$tokenLayer = ($this->entries->stockTokens() ?? $tokenLayer);
		}

		$layers = [$tokenLayer];
		$logo = ($this->brandLogo ?? $this->logoLayer->layer(tokenSet: $tokenSet));
		if ($logo !== null) {
			$layers[] = $logo;
		}

		if (is_file($this->appPath() . '/css/token-overrides/' . $tokenSet . '.css') === true) {
			$layers[] = ['layer' => 'token-overrides', 'kind' => 'file', 'file' => 'token-overrides/' . $tokenSet];
		}

		return $layers;
	}//end tokenLayers()

	/**
	 * The generated dark variant, then the dark logo as an absolute url after
	 * the dark file that names it relatively (see LogoLayerService::darkLayers()).
	 *
	 * @param string $tokenSet The token set id.
	 *
	 * @return array<int, array{layer: string, kind: string, file?: string, name?: string, css?: string, id?: string}>
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function darkLayers(string $tokenSet): array {
		return array_merge(
			[$this->entries->file(layer: 'dark-variant', file: 'tokens/dark/' . $tokenSet)],
			$this->logoLayer->darkLayers(tokenSet: $tokenSet, brandLogo: ($this->brandLogo !== null))
		);
	}//end darkLayers()

	/**
	 * The set layers of a token set on the `none` design system.
	 *
	 * STOCK STILL GETS THE COMPONENT LAYER, and that is the difference between
	 * a themable instance and an inert one.
	 *
	 * `none` means "no design system", not "the app does nothing". Every
	 * instance STARTS on the stock `nextcloud` set, so without this the token
	 * editor and the playground could not move a single colour until an admin
	 * had already picked some other theme.
	 *
	 * Emitting it costs nothing visually. This layer only REDIRECTS Nextcloud's
	 * own variables inside a component's subtree, and every component token is
	 * undeclared until somebody sets one, so an untouched stock instance
	 * resolves each redirect straight back to the captured global and renders
	 * byte-identically to stock. What it buys is that the overrides file
	 * (emitted after this list whatever the design system) finally has
	 * something reading the tokens it writes.
	 *
	 * A CUSTOM set on `none` is a theme saved off stock Nextcloud, and its file
	 * is the only place its values live. The stock set itself has no file to
	 * load: it IS the running Nextcloud.
	 *
	 * THE STOCK SET'S TOKENS ARE THE INSTANCE'S OWN, resolved from the running
	 * theme (see TokenSetLayerEntries::stockTokens()). Nothing on a `none` page
	 * paints from them; what reads them is the admin panel. When the instance
	 * cannot be read nothing is emitted: the shipped snapshot is never the
	 * stock set's layer (thematiq#620).
	 *
	 * @param string $tokenSet The token set id.
	 *
	 * @return array<int, array{layer: string, kind: string, file?: string, name?: string, css?: string, id?: string}> The entries, in cascade order.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 * @spec openspec/changes/component-playground/specs/nextcloud-variable-mapping/spec.md
	 */
	private function noDesignSystemLayers(string $tokenSet): array {
		$layers = [];
		if ($tokenSet !== self::STOCK_TOKEN_SET) {
			$layers[] = $this->entries->file(layer: 'tokens', file: 'tokens/' . $tokenSet);
		}

		if ($tokenSet === self::STOCK_TOKEN_SET) {
			$layers = array_filter([$this->entries->stockTokens()]);
		}

		return array_merge(
			$layers,
			self::SCOPE_LAYERS,
			// This branch injects no dark variant, so none is read for the scopes either.
			$this->entries->internalScopes(tokenSet: $tokenSet, cascade: ['designSystemId' => 'none', 'withDark' => false])
		);
	}//end noDesignSystemLayers()

	/**
	 * Whether the generated dark-mode stylesheet is part of this set's cascade:
	 * when ALL of the `dark_variants` app config is enabled, and a generated
	 * `css/tokens/dark/{set}.css` file exists for the set. A missing file or a
	 * disabled toggle simply adds nothing, never an error.
	 *
	 * @param string $tokenSet The active token set id.
	 *
	 * @return bool True when the dark-variant layer is part of this set's cascade.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function hasDarkVariantLayer(string $tokenSet): bool {
		if ($this->config->getAppValue(Application::APP_ID, 'dark_variants', '1') !== '1') {
			return false;
		}

		return $this->designSystemService->hasGeneratedDarkVariant(tokenSetId: $tokenSet);
	}//end hasDarkVariantLayer()

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
}//end class
