<?php

/**
 * NL Design Dark Palette Service.
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
 * @spec openspec/specs/dark-mode/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileStore;
use OCP\App\IAppManager;
use Psr\Log\LoggerInterface;

/**
 * Derives, verifies, and materialises dark-mode CSS variants for NL Design
 * token sets.
 *
 * NL Design System ships no dark mode, so every token set is light-only.
 * This service derives a dark palette algorithmically (preserve hue, invert
 * the lightness scale), verifies every WCAG-checked pair still reaches AA
 * 4.5:1 via {@see ContrastService}, honours hand-authored
 * `@media (prefers-color-scheme: dark)` overrides in the token set file
 * (which always win and are never rewritten), and renders the result as a
 * static `css/tokens/dark/{id}.css` file. Generation is build/install-time
 * only — never per request.
 *
 * Token classification note: the existing {@see TokenRegistry} covers the
 * `--color-*` custom-overrides editor (a distinct concern, mapped onto core
 * Nextcloud variable names), not the `--nldesign-*` token-set primitives this
 * service derives. Classification here is therefore a dedicated, local
 * background/text split driven by the `--nldesign-*` naming convention
 * (see {@see self::isTextClass()}), not a reuse of TokenRegistry.
 *
 * @spec openspec/specs/dark-mode/spec.md
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)     - This class is the full dark-palette
 * derivation + WCAG verification/repair + dual-scope CSS rendering + generation/freshness
 * pipeline (RGB<->HSL colour math, the binary-search repair loop, hand-authored override
 * merging, and file I/O). Every method is small, single-purpose, and independently unit
 * tested; splitting further would fragment one cohesive, well-tested algorithm across
 * multiple classes without reducing real complexity (see DesignTokensMapper/
 * ComplianceReportService/UpstreamFreshnessService for the same precedent in this app).
 * @SuppressWarnings(PHPMD.TooManyMethods)           - see the ExcessiveClassLength rationale above.
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) - see the ExcessiveClassLength rationale above.
 */
class DarkPaletteService {

	/**
	 * The generated-file format/algorithm version, embedded in the header
	 * comment so a future algorithm change can force regeneration.
	 *
	 * Version 2 resolves `var()` aliases before deriving (see
	 * {@see self::resolveAlias()}) and classifies text-class tokens by the
	 * `-color` / `-background-color` convention the utrecht and municipal
	 * families use, not only by the word "text".
	 *
	 * Version 3 keeps a translucent light value's alpha on its dark value
	 * (`#rrggbbaa`) and darkens 8-digit hex tokens, which it used to skip.
	 *
	 * Version 4 reads "text" as a whole word of the token name, so `textbox`
	 * surfaces darken instead of landing mid-grey, and repairs the label and
	 * input pairs in {@see self::CONTROL_PAIRS} (thematiq#938).
	 *
	 * Version 5 keeps the page background dark whatever light value it
	 * derives from (see {@see self::PAGE_BACKGROUND_MAX_LUMINANCE}, thematiq#952).
	 *
	 * Version 6 adds the secondary button labels and the link colour to
	 * {@see self::CONTROL_PAIRS}, and measures a transparent fill against the
	 * page it shows (thematiq#969).
	 */
	public const GENERATOR_VERSION = 6;

	/**
	 * The guaranteed-passing near-white snap value (matches NC's own dark
	 * theme main-text colour, `DarkTheme::getCSSVariables()`).
	 */
	private const NEAR_WHITE = '#EBEBEB';

	/**
	 * The guaranteed-passing near-black snap value (matches NC's own dark
	 * theme main-background colour).
	 */
	private const NEAR_BLACK = '#111111';

	/**
	 * The highest relative luminance a dark page background may have, and the
	 * lightness it is pulled down to when it has more.
	 *
	 * Inverting a light value's lightness only lands dark when the light value
	 * was light. A set without its own `--nldesign-color-background` takes the
	 * manifest's theming `background_color` instead (see
	 * {@see self::resolveLightDeclarations()}), which is Nextcloud's header and
	 * login colour and often a mid-tone: vng's #0277BD inverted to #42b1f3, a
	 * bright page in dark mode (thematiq#952). Nextcloud's own dark page has
	 * luminance 0.009; 0.05 leaves every tinted dark page a set already ships
	 * alone. 0.12 lightness is a little above the 0.08 a white page derives
	 * to, so the pulled-down page keeps its hue.
	 */
	private const PAGE_BACKGROUND_MAX_LUMINANCE = 0.05;

	/**
	 * See {@see self::PAGE_BACKGROUND_MAX_LUMINANCE}.
	 */
	private const PAGE_BACKGROUND_DARK_LIGHTNESS = 0.12;

	/**
	 * Design systems that are never eligible for dark-variant generation:
	 * `none` has no `--nldesign-*` tokens to darken (stock Nextcloud handles
	 * its own dark theme), and `high-contrast` is a AAA black-on-white set
	 * whose purpose auto-darkening would defeat (hand-authored dark blocks
	 * remain possible for it).
	 *
	 * @var string[]
	 */
	private const SKIPPED_DESIGN_SYSTEMS = ['none', 'high-contrast'];

	/**
	 * Maximum outer verify/repair rounds (a fix to one pair's shared token
	 * can perturb another pair sharing that token, e.g. `--nldesign-color-primary`
	 * is the background of one fixed pair and the foreground of another).
	 */
	private const MAX_REPAIR_ROUNDS = 4;

	/**
	 * Maximum binary-search steps per failing pair.
	 */
	private const MAX_BINARY_STEPS = 8;

	/**
	 * The initial lightness step for the binary search, halved each round.
	 */
	private const INITIAL_STEP = 0.25;

	/**
	 * Maximum `var()` hops followed when resolving an alias to a literal.
	 *
	 * Bounded rather than cycle-tracked because the bound is also the answer
	 * to a cycle: a chain that has not reached a literal in this many hops is
	 * not one worth darkening.
	 */
	private const MAX_ALIAS_DEPTH = 8;

	/**
	 * A token whose value is nothing but a single `var()` reference, with an
	 * optional fallback: `var(--tilburg-color-black-txt)` or
	 * `var(--utrecht-x, #333)`.
	 *
	 * Deliberately anchored. A value that merely CONTAINS a `var()` among
	 * other terms (`1px solid var(--x)`, a gradient) is not a colour this
	 * service can derive, and half-substituting one would corrupt it.
	 */
	private const ALIAS_PATTERN = '/^var\(\s*(--[A-Za-z0-9_-]+)\s*(?:,\s*(.+?)\s*)?\)$/';

	/**
	 * Text on a control fill, verified and repaired in the dark variant on top
	 * of {@see ContrastService::check()}'s two brand pairs: normal-size text at
	 * 4.5:1, and the input's border at the 3:1 of a component boundary
	 * (WCAG SC 1.4.11) against the input fill: a dark fill on a dark page
	 * leaves the border as the only edge.
	 *
	 * The hover fill is checked through the component tokens only, which give
	 * it its own label token. `--nldesign-color-button-primary-text` has one
	 * value for both fills, and a derived hover lighter than the rest fill
	 * leaves no single label that clears both (zwolle: white fails the hover,
	 * black fails the rest fill).
	 *
	 * The aliases between these tokens are flattened to literals at
	 * generation time (see {@see self::deriveDarkDeclarations()}), so the
	 * colour tokens and the component tokens that alias them are separate
	 * values and each pair is checked on its own. Without these pairs a white
	 * label clamped to mid-grey #9e9e9e on a light primary fill and shipped at
	 * 1.17:1 in every set (thematiq#938).
	 *
	 * @var array<int, array{fg: string, bg: string, threshold: float}>
	 */
	private const CONTROL_PAIRS = [
		[
			'fg' => '--nldesign-component-textbox-border-color',
			'bg' => '--nldesign-component-textbox-background-color',
			'threshold' => 3.0,
		],
		[
			'fg' => '--nldesign-component-textbox-color',
			'bg' => '--nldesign-component-textbox-background-color',
			'threshold' => 4.5,
		],
		[
			'fg' => '--nldesign-color-button-primary-text',
			'bg' => '--nldesign-color-button-primary-background',
			'threshold' => 4.5,
		],
		[
			'fg' => '--nldesign-component-button-primary-action-color',
			'bg' => '--nldesign-component-button-primary-action-background-color',
			'threshold' => 4.5,
		],
		[
			'fg' => '--nldesign-component-button-primary-action-hover-color',
			'bg' => '--nldesign-component-button-primary-action-hover-background-color',
			'threshold' => 4.5,
		],
		[
			'fg' => '--nldesign-component-badge-color',
			'bg' => '--nldesign-component-badge-background-color',
			'threshold' => 4.5,
		],
		[
			// Nextcloud's own secondary button: overrides.css maps its label
			// to the primary colour and its fill to the primary-light wash.
			'fg' => '--nldesign-color-primary',
			'bg' => '--nldesign-color-primary-light',
			'threshold' => 4.5,
		],
		[
			'fg' => '--nldesign-color-primary',
			'bg' => '--nldesign-color-primary-light-hover',
			'threshold' => 4.5,
		],
		[
			'fg' => '--nldesign-component-button-secondary-action-color',
			'bg' => '--nldesign-component-button-secondary-action-background-color',
			'threshold' => 4.5,
		],
		[
			'fg' => '--nldesign-component-button-secondary-action-color',
			'bg' => '--nldesign-component-button-secondary-action-hover-background-color',
			'threshold' => 4.5,
		],
		[
			'fg' => '--nldesign-component-link-color',
			'bg' => '--nldesign-color-background',
			'threshold' => 4.5,
		],
		[
			'fg' => '--nldesign-color-logo-text',
			'bg' => '--nldesign-color-logo-background',
			'threshold' => 4.5,
		],
		[
			// Summer Breeze paints from its own --summer-* tokens (theme.css
			// maps them onto Nextcloud's variables), so the --nldesign-* pairs
			// above never reach it. These are the pairs its stylesheets paint;
			// tests/Unit/Service/SummerBreezeContrastTest.php measures the
			// committed file against the same list (thematiq#1022).
			'fg' => '--summer-color-primary-text',
			'bg' => '--summer-color-primary',
			'threshold' => 4.5,
		],
		[
			'fg' => '--summer-color-primary-text',
			'bg' => '--summer-color-primary-hover',
			'threshold' => 4.5,
		],
		[
			'fg' => '--summer-color-primary',
			'bg' => '--summer-color-primary-light',
			'threshold' => 4.5,
		],
		[
			'fg' => '--summer-color-primary',
			'bg' => '--summer-color-primary-light-hover',
			'threshold' => 4.5,
		],
		[
			'fg' => '--summer-color-primary',
			'bg' => '--summer-color-surface',
			'threshold' => 4.5,
		],
		[
			'fg' => '--summer-color-primary',
			'bg' => '--summer-color-background-plain',
			'threshold' => 4.5,
		],
		[
			'fg' => '--summer-color-text',
			'bg' => '--summer-color-surface',
			'threshold' => 4.5,
		],
		[
			'fg' => '--summer-color-text',
			'bg' => '--summer-color-background-plain',
			'threshold' => 4.5,
		],
		[
			'fg' => '--summer-color-text',
			'bg' => '--summer-color-background-hover',
			'threshold' => 4.5,
		],
		[
			'fg' => '--summer-color-text-muted',
			'bg' => '--summer-color-surface',
			'threshold' => 4.5,
		],
		[
			'fg' => '--summer-color-text-muted',
			'bg' => '--summer-color-background-plain',
			'threshold' => 4.5,
		],
		[
			'fg' => '--summer-color-text-muted',
			'bg' => '--summer-color-toggle-track',
			'threshold' => 4.5,
		],
		[
			'fg' => '--summer-color-border-input',
			'bg' => '--summer-color-surface',
			'threshold' => 3.0,
		],
		[
			'fg' => '--summer-color-error',
			'bg' => '--summer-color-error-light',
			'threshold' => 4.5,
		],
		[
			'fg' => '--summer-color-error',
			'bg' => '--summer-color-surface',
			'threshold' => 4.5,
		],
		[
			'fg' => '--summer-color-warning',
			'bg' => '--summer-color-warning-light',
			'threshold' => 4.5,
		],
		[
			'fg' => '--summer-color-warning',
			'bg' => '--summer-color-surface',
			'threshold' => 4.5,
		],
		[
			'fg' => '--summer-color-success',
			'bg' => '--summer-color-success-light',
			'threshold' => 4.5,
		],
		[
			'fg' => '--summer-color-success',
			'bg' => '--summer-color-surface',
			'threshold' => 4.5,
		],
		[
			'fg' => '--summer-color-info',
			'bg' => '--summer-color-info-light',
			'threshold' => 4.5,
		],
		[
			'fg' => '--summer-color-info',
			'bg' => '--summer-color-surface',
			'threshold' => 4.5,
		],
	];

	/**
	 * Words that mark a `-color` token as naming a SURFACE rather than the
	 * glyphs drawn on it (see {@see self::isTextClass()}).
	 *
	 * @var string[]
	 */
	private const SURFACE_WORDS = [
		'background',
		'border',
		'outline',
		'fill',
		'shadow',
		'accent',
		'marker',
		'divider',
		'separator',
	];

	/**
	 * The WCAG contrast service (relative-luminance math + colour parsing).
	 *
	 * @var ContrastService
	 */
	private ContrastService $contrast;

	/**
	 * The CSS custom-property parser (also used for dark-block extraction).
	 *
	 * @var CssParserService
	 */
	private CssParserService $parser;

	/**
	 * The app manager for resolving the app's filesystem path.
	 *
	 * @var IAppManager
	 */
	private IAppManager $appManager;

	/**
	 * The logger — every contrast-repair snap and every degrade (unwritable
	 * directory, write failure) is logged as a warning, never an exception.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Where dark variants are written.
	 *
	 * @var RuntimeFileStore
	 */
	private RuntimeFileStore $store;

	/**
	 * Whether a shipped set's dark variant may be (re)written.
	 *
	 * True only when this service is built by hand, which is what
	 * `scripts/generate-dark-variants.php` does at build time: the shipped
	 * dark files are build output, committed with the release and covered by
	 * its signature. Built by the container it is false, so an install never
	 * rewrites a signed file. Rewriting one was the code integrity warning
	 * every regeneration after a generator change used to cause.
	 *
	 * @var bool
	 */
	private bool $mayWriteShipped;

	/**
	 * Constructor.
	 *
	 * @param ContrastService $contrast The WCAG contrast service.
	 * @param CssParserService $parser The CSS custom-property parser.
	 * @param IAppManager $appManager The app manager.
	 * @param LoggerInterface $logger The logger.
	 * @param RuntimeFileStore|null $store Where dark variants are written. Without one, the
	 *                                     app directory itself (build mode, see $mayWriteShipped).
	 */
	public function __construct(
		ContrastService $contrast,
		CssParserService $parser,
		IAppManager $appManager,
		LoggerInterface $logger,
		?RuntimeFileStore $store = null,
	) {
		$this->contrast = $contrast;
		$this->parser = $parser;
		$this->appManager = $appManager;
		$this->logger = $logger;
		$this->mayWriteShipped = ($store === null);
		if ($store === null) {
			$store = new DirectoryRuntimeFileStore(root: $appManager->getAppPath('thematiq'));
		}

		$this->store = $store;
	}//end __construct()

	/**
	 * Whether a set id is an uploaded set rather than a shipped one.
	 *
	 * @param string $setId The token set id.
	 *
	 * @return bool True for a `custom-` id.
	 */
	private function isUploaded(string $setId): bool {
		return str_starts_with($setId, 'custom-');
	}//end isUploaded()

	/**
	 * A set's light stylesheet: an uploaded one from the store, a shipped one from the release.
	 *
	 * @param string $setId The token set id.
	 *
	 * @return string|null The CSS, or null when the set has no stylesheet.
	 */
	private function readTokenCss(string $setId): ?string {
		$name = 'css/tokens/' . $setId . '.css';
		if ($this->isUploaded(setId: $setId) === true) {
			return $this->store->read(name: $name);
		}

		$path = $this->appManager->getAppPath('thematiq') . '/' . $name;
		if (is_file($path) === false) {
			return null;
		}

		$css = file_get_contents($path);
		if ($css === false) {
			return null;
		}

		return $css;
	}//end readTokenCss()

	/**
	 * Whether a design system id is eligible for dark-variant generation.
	 *
	 * @param string $designSystemId The design system id.
	 *
	 * @return bool True when eligible.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	public function isEligible(string $designSystemId): bool {
		return (in_array($designSystemId, self::SKIPPED_DESIGN_SYSTEMS, true) === false);
	}//end isEligible()

	/**
	 * Discover every token set id on disk (shipped and custom), mirroring
	 * {@see TokenSetService::getAvailableTokenSets()}'s filesystem scan.
	 *
	 * @return string[] The discovered token set ids, alphabetically sorted.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	public function discoverAllSetIds(): array {
		$ids = [];
		$tokensDir = $this->appManager->getAppPath('thematiq') . '/css/tokens';
		$files = [];
		if (is_dir($tokensDir) === true) {
			$files = scandir($tokensDir);
		}

		if ($files === false) {
			$files = [];
		}

		foreach ($files as $file) {
			if (is_file($tokensDir . '/' . $file) === true && str_ends_with($file, '.css') === true) {
				$ids[] = basename($file, '.css');
			}
		}

		foreach ($this->store->listDirectory(directory: 'css/tokens') as $name) {
			$ids[] = basename($name, '.css');
		}

		$ids = array_values(array_unique($ids));
		sort($ids);

		return $ids;
	}//end discoverAllSetIds()

	/**
	 * Resolve the effective light `--nldesign-*` declarations for a set:
	 * `css/systems/nldesign/defaults.css` layered under `css/tokens/{id}.css`,
	 * with a `theming.background_color` fallback for the UI-contrast pair
	 * when the set declares no explicit background token. Mirrors the
	 * resolution {@see ShippedTokenSetAuditService::resolveDeclarations()}
	 * (and the preview service) already use.
	 *
	 * @param string $appPath The app root path.
	 * @param string $setId The token set id.
	 * @param array<string, mixed> $theming The set's theming metadata (for the background fallback).
	 *
	 * @return array<string, string> The merged declarations.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function resolveLightDeclarations(string $appPath, string $setId, array $theming = []): array {
		$declarations = $this->parseFile(filePath: $appPath . '/css/systems/nldesign/defaults.css');

		$tokenCss = $this->readTokenCss(setId: $setId);
		if ($tokenCss !== null) {
			$declarations = array_merge($declarations, ($this->parser->parseDeclarations(content: $tokenCss) ?? []));
		}

		if (isset($declarations['--nldesign-color-background']) === false
			&& isset($theming['background_color']) === true
			&& is_string($theming['background_color']) === true
		) {
			$declarations['--nldesign-color-background'] = $theming['background_color'];
		}

		return $declarations;
	}//end resolveLightDeclarations()

	/**
	 * Derive the dark declarations for every literal color token in the
	 * supplied light declaration map. Non-color tokens (sizes, radii, fonts,
	 * urls, `var()` aliases) are silently excluded — they inherit the light
	 * layer's value via normal cascade.
	 *
	 * @param array<string, string> $lightDeclarations The effective light declarations.
	 *
	 * @return array<string, string> The derived dark declarations (color tokens only).
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	public function deriveDarkDeclarations(array $lightDeclarations): array {
		$dark = [];

		foreach ($lightDeclarations as $token => $value) {
			if (str_ends_with($token, '-rgb') === true) {
				// Regenerated after the main pass, from the derived base token.
				continue;
			}

			// An ALIAS must be resolved here, not left to the cascade.
			//
			// `--utrecht-document-color: var(--tilburg-color-black-txt)` is
			// declared on `:root`, so the substitution happens AT `:root`, with
			// `:root`'s light value. This file's dark block sits on `body` — a
			// descendant — so darkening the target token there cannot reach an
			// alias already resolved one level up: body simply inherits the
			// light literal. Measured on a live portal: 51% of pixels changed
			// in dark mode while every text token stayed light-mode dark,
			// leaving a heading at contrast 1.06 against its own band.
			//
			// So aliases are flattened to literals at generation time. The cost
			// is that a runtime override of the TARGET token no longer
			// propagates through this alias in dark mode; the alternative is a
			// dark mode that only ever half-applies.
			$literal = $this->resolveAlias(value: $value, declarations: $lightDeclarations);
			$darkValue = $this->deriveDarkValue(token: $token, lightValue: $literal, context: $lightDeclarations);
			if ($darkValue === null) {
				// Unparseable (gradient, keyword, size, font stack, url(), an
				// alias chain with no literal at the end) — skip.
				continue;
			}

			$dark[$token] = $darkValue;
		}

		return $this->regenerateRgbCompanions(lightDeclarations: $lightDeclarations, darkDeclarations: $dark);
	}//end deriveDarkDeclarations()

	/**
	 * Derive the dark value of one colour literal, as the generated dark
	 * stylesheets do, so the token editor and the generator never disagree.
	 *
	 * The dark channels come from the opaque colour; a translucent light value
	 * keeps its alpha, so an overlay stays an overlay in dark mode.
	 *
	 * @param string $token The token name (decides text-class or surface-class).
	 * @param string $lightValue The light colour literal.
	 * @param array<string, string> $context The light declarations around it, for the brand-primary exception.
	 *
	 * @return string|null The dark hex value, or null when the value is not a colour literal.
	 *
	 * @spec openspec/specs/token-editor-ui/spec.md#requirement-each-colour-token-has-an-optional-dark-value
	 */
	public function deriveDarkValue(string $token, string $lightValue, array $context = []): ?string {
		$rgba = $this->contrast->parseColorWithAlpha(value: $lightValue);
		if ($rgba === null) {
			return null;
		}

		return $this->deriveColorToken(token: $token, rgb: [$rgba[0], $rgba[1], $rgba[2]], lightDeclarations: $context)
			. $this->alphaSuffix(alpha: $rgba[3]);
	}//end deriveDarkValue()

	/**
	 * Follow a `var()` alias chain to the literal it ends at.
	 *
	 * Returns the value unchanged when it is already a literal, when it is not
	 * a bare alias, or when the chain cannot be resolved — every caller then
	 * asks {@see ContrastService::parseColor()} the same question it would have
	 * asked anyway, so an unresolvable alias degrades to "not a colour" rather
	 * than to a wrong colour.
	 *
	 * A `var(--x, #fff)` fallback is used only when `--x` is absent from the
	 * set, which mirrors what the browser does with it.
	 *
	 * @param string $value The declaration's raw value.
	 * @param array<string, string> $declarations The full light declaration map.
	 * @param int $depth The current hop count (internal).
	 *
	 * @return string The resolved literal, or the input when it does not resolve.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function resolveAlias(string $value, array $declarations, int $depth = 0): string {
		if ($depth >= self::MAX_ALIAS_DEPTH) {
			return $value;
		}

		$matches = [];
		if (preg_match(self::ALIAS_PATTERN, trim($value), $matches) !== 1) {
			return $value;
		}

		$target = $matches[1];
		if (isset($declarations[$target]) === true) {
			return $this->resolveAlias(value: $declarations[$target], declarations: $declarations, depth: ($depth + 1));
		}

		$fallback = ($matches[2] ?? '');
		if ($fallback === '') {
			return $value;
		}

		return $this->resolveAlias(value: $fallback, declarations: $declarations, depth: ($depth + 1));
	}//end resolveAlias()

	/**
	 * Derive one color token's dark value.
	 *
	 * @param string $token The token name.
	 * @param array{0:int,1:int,2:int} $rgb The token's light RGB value.
	 * @param array<string, string> $lightDeclarations The full light declaration map (for the brand-primary exception).
	 *
	 * @return string The derived dark hex value.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function deriveColorToken(string $token, array $rgb, array $lightDeclarations): string {
		if ($this->isBrandPrimaryToken(token: $token) === true
			&& $this->brandPrimaryPasses(lightDeclarations: $lightDeclarations) === true
		) {
			// Brand primary exception: the gemeente brand colour already
			// works on dark, so keep the light value untouched.
			return $this->rgbToHex(rgb: $rgb);
		}

		[$hue, $saturation, $lightness] = $this->rgbToHsl(rgb: $rgb);

		$isText = $this->isTextClass(token: $token);
		$invertedL = (1.0 - $lightness);

		$darkLightness = (0.08 + (0.84 * $invertedL));
		if ($isText === true) {
			$darkLightness = $this->clamp(value: $invertedL, min: 0.62, max: 0.92);
		}

		$darkSaturation = $saturation;
		if ($isText === false) {
			// Desaturate background-class surfaces only, to avoid neon walls.
			$darkSaturation = ($saturation * 0.9);
		}

		$darkRgb = $this->hslToRgb(hue: $hue, saturation: $darkSaturation, lightness: $darkLightness);

		if ($token === '--nldesign-color-background'
			&& $this->luminanceOf(rgb: $darkRgb) > self::PAGE_BACKGROUND_MAX_LUMINANCE
		) {
			$darkRgb = $this->hslToRgb(hue: $hue, saturation: $darkSaturation, lightness: self::PAGE_BACKGROUND_DARK_LIGHTNESS);
		}

		return $this->rgbToHex(rgb: $darkRgb);
	}//end deriveColorToken()

	/**
	 * WCAG relative luminance, read off the contrast ratio against black:
	 * (L + 0.05) / 0.05.
	 *
	 * @param array{0:int,1:int,2:int} $rgb The colour.
	 *
	 * @return float The relative luminance.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function luminanceOf(array $rgb): float {
		return (($this->contrast->ratio(first: $rgb, second: [0, 0, 0]) * 0.05) - 0.05);
	}//end luminanceOf()

	/**
	 * Whether a token is the brand-primary token or its hover variant (the
	 * only tokens eligible for the "keep the light value" exception).
	 *
	 * @param string $token The token name.
	 *
	 * @return bool True for `--nldesign-color-primary` or `--nldesign-color-primary-hover`.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function isBrandPrimaryToken(string $token): bool {
		// The text on primary stays with it: a kept primary under a remapped grey text fell to 2.2:1.
		return in_array($token, ['--nldesign-color-primary', '--nldesign-color-primary-hover', '--nldesign-color-primary-text'], true);
	}//end isBrandPrimaryToken()

	/**
	 * Whether the light `--nldesign-color-primary` / `-primary-text` pair
	 * already passes WCAG AA 4.5:1 — the brand-primary exception condition.
	 *
	 * @param array<string, string> $lightDeclarations The full light declaration map.
	 *
	 * @return bool True when the light pair already passes (or cannot be evaluated;
	 *              an unevaluated pair is treated as "does not obviously fail", so the
	 *              original brand colour is preserved by default).
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function brandPrimaryPasses(array $lightDeclarations): bool {
		$background = ($lightDeclarations['--nldesign-color-primary'] ?? null);
		$foreground = ($lightDeclarations['--nldesign-color-primary-text'] ?? null);
		if ($background === null || $foreground === null) {
			return true;
		}

		$ratio = $this->contrast->measure(foreground: $foreground, background: $background);
		if ($ratio === null) {
			return true;
		}

		return ($ratio >= 4.5);
	}//end brandPrimaryPasses()

	/**
	 * Whether a token name is text-class (its dark value must land LIGHT)
	 * as opposed to background-class (its dark value must land DARK).
	 *
	 * TWO conventions, because the sets use two.
	 *
	 * `--nldesign-*` names the role in the word: anything with "text" as a
	 * whole word (`-text`, `-text-error`, the bare `--nldesign-color-text`) is
	 * text-class. A word that merely starts with it (`textbox`) is not.
	 *
	 * The utrecht and municipal families instead name the SURFACE and leave the
	 * text case unmarked: `--utrecht-document-background-color` is a surface,
	 * `--utrecht-document-color` and `--utrecht-heading-1-color` are the text on
	 * it. Reading only the first convention put every one of those in the
	 * background class, so the tokens that paint body copy were darkened
	 * towards black exactly like the surfaces behind them.
	 *
	 * So a name ending in `-color` is text-class unless a preceding word says
	 * otherwise — `background`, `border`, `outline`, `fill`, `shadow`,
	 * `accent`, `marker` all name something that is not the glyphs.
	 *
	 * See the class docblock for why this does not reuse {@see TokenRegistry}.
	 *
	 * @param string $token The token name.
	 *
	 * @return bool True when the token is text-class.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function isTextClass(string $token): bool {
		// "text" as a whole word of the name: `textbox` and `textarea` name a
		// control, and its `-background-color` is a surface (thematiq#938).
		if (preg_match('/(^|-)text(-|$)/', $token) === 1) {
			return true;
		}

		if (str_ends_with($token, '-color') === false) {
			return false;
		}

		foreach (self::SURFACE_WORDS as $word) {
			if (str_contains($token, $word) === true) {
				return false;
			}
		}

		return true;
	}//end isTextClass()

	/**
	 * Regenerate `--{base}-rgb` companion declarations from their already-derived
	 * base token, for every `-rgb` key present in the light declarations.
	 *
	 * @param array<string, string> $lightDeclarations The light declarations (to find which `-rgb` keys exist).
	 * @param array<string, string> $darkDeclarations The derived dark declarations so far.
	 *
	 * @return array<string, string> The dark declarations with `-rgb` companions added.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function regenerateRgbCompanions(array $lightDeclarations, array $darkDeclarations): array {
		foreach (array_keys($lightDeclarations) as $token) {
			if (str_ends_with($token, '-rgb') === false) {
				continue;
			}

			$baseToken = substr($token, 0, -4);
			if (isset($darkDeclarations[$baseToken]) === false) {
				continue;
			}

			$rgb = $this->contrast->parseColor(value: $darkDeclarations[$baseToken]);
			if ($rgb === null) {
				continue;
			}

			$darkDeclarations[$token] = $rgb[0] . ', ' . $rgb[1] . ', ' . $rgb[2];
		}

		return $darkDeclarations;
	}//end regenerateRgbCompanions()

	/**
	 * Run the WCAG verification/repair loop over a candidate dark
	 * declaration map. Every evaluable {@see ContrastService::check()} pair
	 * that is not protected (hand-authored) is repaired by adjusting the
	 * foreground's lightness away from the background (bounded binary
	 * search); if still failing it snaps to a guaranteed-passing near-white
	 * or near-black value. Protected (hand-authored) tokens are NEVER
	 * adjusted — a failing pair involving one only produces a warning.
	 *
	 * @param array<string, string> $declarations The candidate dark declarations.
	 * @param string[] $protectedTokens Token names that MUST NOT be rewritten (hand-authored overrides).
	 * @param array<int, array{fg: string, bg: string, threshold: float}>|null $pairs The pairs to verify instead of
	 *                                                                                the brand pairs and {@see self::CONTROL_PAIRS};
	 *                                                                                the editor's overrides pass Nextcloud's own
	 *                                                                                names (OverridesCssBuilder::DARK_PAIRS).
	 *
	 * The (possibly repaired) declarations and the final warning list.
	 *
	 * @return array{declarations: array<string, string>, warnings: array<int, array<string, mixed>>}
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	public function verifyAndRepair(array $declarations, array $protectedTokens = [], ?array $pairs = null): array {
		$protected = array_flip($protectedTokens);

		for ($round = 0; $round < self::MAX_REPAIR_ROUNDS; $round++) {
			$warnings = $this->failingPairs(declarations: $declarations, pairs: $pairs);
			$fixableFound = false;

			foreach ($warnings as $warning) {
				if (($warning['unevaluated'] ?? false) === true) {
					continue;
				}

				[$fgName, $bgName] = $this->splitPairLabel(label: $warning['pair']);
				if (isset($protected[$fgName]) === true || isset($protected[$bgName]) === true) {
					// Hand-authored failure: warn only, never rewritten.
					continue;
				}

				// An earlier repair in this round may already have fixed this pair
				// through a shared foreground; repairing again would overshoot it.
				$bgValue = (string)$this->pairBackground(declarations: $declarations, token: $bgName);
				$ratio = $this->contrast->measure(
					foreground: $declarations[$fgName],
					background: $bgValue,
					page: ($declarations['--nldesign-color-background'] ?? null)
				);
				if ($ratio !== null && $ratio >= $warning['threshold']) {
					continue;
				}

				$fixableFound = true;
				$declarations[$fgName] = $this->repairForeground(
					fgValue: $declarations[$fgName],
					bgValue: $bgValue,
					threshold: $warning['threshold']
				);
			}//end foreach

			if ($fixableFound === false) {
				break;
			}
		}//end for

		$finalWarnings = $this->failingPairs(declarations: $declarations, pairs: $pairs);

		return [
			'declarations' => $declarations,
			'warnings' => $finalWarnings,
		];
	}//end verifyAndRepair()

	/**
	 * The failing pairs of a dark declaration map: {@see ContrastService::check()}'s
	 * brand pairs, then every {@see self::CONTROL_PAIRS} entry present in the map,
	 * in the same warning shape and pair-label format.
	 *
	 * @param array<string, string> $declarations The dark declarations.
	 * @param array<int, array{fg: string, bg: string, threshold: float}>|null $pairs The pairs to measure, or null for
	 *                                                                                the brand pairs plus CONTROL_PAIRS.
	 *
	 * @return array<array{pair: string, ratio: float|null, threshold: float, level: string, unevaluated?: bool}> The warnings.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function failingPairs(array $declarations, ?array $pairs = null): array {
		$warnings = [];
		if ($pairs === null) {
			$warnings = $this->contrast->check(declarations: $declarations);
			$pairs = self::CONTROL_PAIRS;
		}

		foreach ($pairs as $pair) {
			$foreground = ($declarations[$pair['fg']] ?? null);
			$background = $this->pairBackground(declarations: $declarations, token: $pair['bg']);
			if ($foreground === null || $background === null) {
				continue;
			}

			$ratio = $this->contrast->measure(
				foreground: $foreground,
				background: $background,
				page: ($declarations['--nldesign-color-background'] ?? null)
			);
			$label = $pair['fg'] . ' vs ' . $pair['bg'];
			if ($ratio === null) {
				$warnings[] = ['pair' => $label, 'ratio' => null, 'threshold' => $pair['threshold'], 'level' => 'AA', 'unevaluated' => true];
				continue;
			}

			if ($ratio < $pair['threshold']) {
				$warnings[] = ['pair' => $label, 'ratio' => round($ratio, 2), 'threshold' => $pair['threshold'], 'level' => 'AA'];
			}
		}

		return $warnings;
	}//end failingPairs()

	/**
	 * The CONTROL_PAIRS fills that are transparent in light and absent from
	 * the dark declarations.
	 *
	 * @param array<string, string> $light The light declarations.
	 * @param array<string, string> $dark The dark declarations so far.
	 *
	 * @return array<int, string> The fill token names.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function transparentFills(array $light, array $dark): array {
		$fills = [];
		foreach (self::CONTROL_PAIRS as $pair) {
			$fill = $pair['bg'];
			if (isset($dark[$fill]) === true || isset($light[$fill]) === false || in_array($fill, $fills, true) === true) {
				continue;
			}

			if (strtolower(trim($this->resolveAlias(value: $light[$fill], declarations: $light))) === 'transparent') {
				$fills[] = $fill;
			}
		}

		return $fills;
	}//end transparentFills()

	/**
	 * The fill a pair's label sits on: the token's value, or the page
	 * background when the fill is `transparent` (a secondary button at rest).
	 *
	 * @param array<string, string> $declarations The dark declarations.
	 * @param string $token The background token.
	 *
	 * @return string|null The colour, or null when the token is absent.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function pairBackground(array $declarations, string $token): ?string {
		$value = ($declarations[$token] ?? null);
		if ($value !== null && strtolower(trim($value)) === 'transparent') {
			return ($declarations['--nldesign-color-background'] ?? $value);
		}

		return $value;
	}//end pairBackground()

	/**
	 * Split a `ContrastService::check()` pair label ("fg vs bg") back into
	 * its two token names.
	 *
	 * @param string $label The pair label, e.g. "--nldesign-color-primary-text vs --nldesign-color-primary".
	 *
	 * @return array{0: string, 1: string} The [foreground, background] token names.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function splitPairLabel(string $label): array {
		$parts = explode(' vs ', $label, 2);

		return [($parts[0] ?? ''), ($parts[1] ?? '')];
	}//end splitPairLabel()

	/**
	 * Repair a failing foreground/background pair: binary-search the
	 * foreground's lightness away from the background (bounded iteration,
	 * step halving from {@see self::INITIAL_STEP}); if no candidate in the
	 * search passes, snap to whichever of near-white/near-black yields the
	 * higher ratio and log a warning naming the pair and the snap.
	 *
	 * @param string $fgValue The current foreground value.
	 * @param string $bgValue The background value (never modified here).
	 * @param float $threshold The AA threshold this pair must reach.
	 *
	 * @return string The repaired (or snapped) foreground hex value.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function repairForeground(string $fgValue, string $bgValue, float $threshold): string {
		$fgRgb = $this->contrast->parseColor(value: $fgValue);
		$bgRgb = $this->contrast->parseColor(value: $bgValue);
		if ($fgRgb === null || $bgRgb === null) {
			// Unevaluated — nothing literal to adjust.
			return $fgValue;
		}

		[$hue, $saturation, $lightness] = $this->rgbToHsl(rgb: $fgRgb);
		[, , $bgLightness] = $this->rgbToHsl(rgb: $bgRgb);

		$direction = -1.0;
		if ($lightness >= $bgLightness) {
			$direction = 1.0;
		}

		$step = self::INITIAL_STEP;
		$candidateL = $lightness;
		$passed = false;

		for ($i = 0; $i < self::MAX_BINARY_STEPS; $i++) {
			$candidateL = $this->clamp(value: ($candidateL + ($direction * $step)), min: 0.0, max: 1.0);
			$candidateRgb = $this->hslToRgb(hue: $hue, saturation: $saturation, lightness: $candidateL);

			if ($this->contrast->ratio(first: $candidateRgb, second: $bgRgb) >= $threshold) {
				$passed = true;
				break;
			}

			$step = ($step / 2);
		}

		if ($passed === true) {
			return $this->rgbToHex(rgb: $this->hslToRgb(hue: $hue, saturation: $saturation, lightness: $candidateL));
		}

		return $this->snapForeground(bgRgb: $bgRgb, threshold: $threshold, originalFg: $fgValue);
	}//end repairForeground()

	/**
	 * Snap a foreground to whichever of near-white/near-black passes (or
	 * comes closest to passing) against the background, logging a warning.
	 *
	 * @param array{0:int,1:int,2:int} $bgRgb The background RGB.
	 * @param float $threshold The AA threshold.
	 * @param string $originalFg The original (failing) foreground value, for the log message.
	 *
	 * @return string The snapped hex value.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function snapForeground(array $bgRgb, float $threshold, string $originalFg): string {
		$whiteRgb = ($this->contrast->parseColor(value: self::NEAR_WHITE) ?? [235, 235, 235]);
		$blackRgb = ($this->contrast->parseColor(value: self::NEAR_BLACK) ?? [17, 17, 17]);

		$whiteRatio = $this->contrast->ratio(first: $whiteRgb, second: $bgRgb);
		$blackRatio = $this->contrast->ratio(first: $blackRgb, second: $bgRgb);

		$chosen = self::NEAR_WHITE;
		if ($blackRatio >= $whiteRatio) {
			$chosen = self::NEAR_BLACK;
		}

		$this->logger->warning(
			'NL Design dark-variant contrast repair could not reach {threshold}:1 within the bounded '
			. 'search; snapped {original} to {chosen}.',
			[
				'threshold' => $threshold,
				'original' => $originalFg,
				'chosen' => $chosen,
			]
		);

		return $chosen;
	}//end snapForeground()

	/**
	 * Load a token set's `design_system` and `theming` metadata, defaulting
	 * to `nldesign`/empty for ids absent from `token-sets.json` (custom
	 * uploads have no manifest entry there and are always `nldesign`).
	 *
	 * @param string $appPath The app root path.
	 * @param string $setId The token set id.
	 *
	 * @return array{design_system: string, theming: array<string, mixed>} The resolved metadata.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function loadSetMeta(string $appPath, string $setId): array {
		$manifestPath = $appPath . '/token-sets.json';
		if (is_readable($manifestPath) === false) {
			return ['design_system' => 'nldesign', 'theming' => []];
		}

		$manifest = json_decode((string)file_get_contents($manifestPath), true);
		if (is_array($manifest) === false) {
			return ['design_system' => 'nldesign', 'theming' => []];
		}

		foreach ($manifest as $entry) {
			if (is_array($entry) === true && ($entry['id'] ?? null) === $setId) {
				$theming = [];
				if (is_array($entry['theming'] ?? null) === true) {
					$theming = $entry['theming'];
				}

				return [
					'design_system' => (string)($entry['design_system'] ?? 'nldesign'),
					'theming' => $theming,
				];
			}
		}

		return ['design_system' => 'nldesign', 'theming' => []];
	}//end loadSetMeta()

	/**
	 * Build the full dark CSS content for one token set: derive, apply
	 * hand-authored overrides (which win and are protected from repair),
	 * run the verification loop, emit the `logo_dark` override when present,
	 * and render the dual-scoped file.
	 *
	 * @param string $setId The token set id.
	 *
	 * @return array{css: string, warnings: array<int, array<string, mixed>>}|null
	 *                                                                             The rendered CSS + warnings, or null when the set is ineligible or
	 *                                                                             has no source file.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	public function generateForSet(string $setId): ?array {
		$appPath = $this->appManager->getAppPath('thematiq');
		$meta = $this->loadSetMeta(appPath: $appPath, setId: $setId);

		if ($this->isEligible(designSystemId: $meta['design_system']) === false) {
			return null;
		}

		$tokenCss = $this->readTokenCss(setId: $setId);
		if ($tokenCss === null) {
			return null;
		}

		$light = $this->resolveLightDeclarations(appPath: $appPath, setId: $setId, theming: $meta['theming']);
		$derived = $this->deriveDarkDeclarations(lightDeclarations: $light);
		$overrides = $this->parser->parseDarkBlock(css: $tokenCss);

		$merged = array_merge($derived, $overrides);

		if (isset($meta['theming']['logo_dark']) === true && is_string($meta['theming']['logo_dark']) === true) {
			$merged['--nldesign-logo-url'] = "url('" . $this->relativeDarkLogoPath(logoDarkPath: $meta['theming']['logo_dark']) . "')";
		}

		// A fill that is transparent in light (a secondary button at rest) is
		// not a colour, so it derives nothing; its label still has to read on
		// the page it shows. It joins the repair as `transparent` and leaves
		// before rendering, so the light value keeps applying (thematiq#969).
		$transparentFills = $this->transparentFills(light: $light, dark: $merged);
		$merged = array_merge($merged, array_fill_keys($transparentFills, 'transparent'));

		$repaired = $this->verifyAndRepair(declarations: $merged, protectedTokens: array_keys($overrides));
		foreach ($transparentFills as $fill) {
			unset($repaired['declarations'][$fill]);
		}

		ksort($repaired['declarations']);

		$sourceHash = 'sha256:' . hash(algo: 'sha256', data: $tokenCss);
		$css = $this->renderDarkCss(setId: $setId, declarations: $repaired['declarations'], sourceHash: $sourceHash);

		return ['css' => $css, 'warnings' => $repaired['warnings']];
	}//end generateForSet()

	/**
	 * Build the dark-scoped logo url()'s path, relative to `css/tokens/dark/`.
	 *
	 * @param string $logoDarkPath The app-relative `logo_dark` path (e.g. `img/logos/x-dark.svg`).
	 *
	 * @return string The path as it must appear inside `url(...)` from `css/tokens/dark/{set}.css`.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function relativeDarkLogoPath(string $logoDarkPath): string {
		// This app is served from a custom_apps URL prefix (the fleet's
		// standard deployment — no /apps/thematiq/ alias exists there), so
		// css/tokens/{set}.css (2 levels under the app root: css/ →
		// tokens/) needs 2 ".." to reach img/, not 3 — verified live against
		// a running custom-app install (css/tokens/rijkshuisstijl.css's
		// old 3-".." value 404'd; 2 resolves correctly).
		// css/tokens/dark/{set}.css is one directory deeper, so it needs one more.
		return '../../../' . $logoDarkPath;
	}//end relativeDarkLogoPath()

	/**
	 * Render the dual-scoped dark CSS file: a `@media (prefers-color-scheme:
	 * dark)` block excluding any explicit theme choice (auto theme + anonymous
	 * pages), and an unconditional `body[data-theme-dark], body[data-themes*=dark]`
	 * block for an explicit dark choice. Exact selector shape verified against
	 * the NC 34 server checkout (design.md §1/§2).
	 *
	 * @param string $setId The token set id.
	 * @param array<string, string> $declarations The final (sorted) dark declarations.
	 * @param string $sourceHash The `sha256:...` hash of the source token file.
	 *
	 * @return string The rendered CSS.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	public function renderDarkCss(string $setId, array $declarations, string $sourceHash): string {
		$lines = [];
		$lines[] = '/* GENERATED by nldesign DarkPaletteService v' . self::GENERATOR_VERSION
			. ' from tokens/' . $setId . '.css (' . $sourceHash . ') — do not edit */';
		$lines[] = '@media (prefers-color-scheme: dark) {';
		$lines[] = '	body:not([data-theme-light]):not([data-theme-dark]):not([data-theme-light-highcontrast]):not([data-theme-dark-highcontrast]) {';

		foreach ($declarations as $token => $value) {
			$lines[] = '		' . $token . ': ' . $value . ';';
		}

		$lines[] = '	}';
		$lines[] = '}';
		$lines[] = 'body[data-theme-dark],';
		$lines[] = 'body[data-themes*=dark] {';

		foreach ($declarations as $token => $value) {
			$lines[] = '	' . $token . ': ' . $value . ';';
		}

		$lines[] = '}';

		return implode("\n", $lines) . "\n";
	}//end renderDarkCss()

	/**
	 * Generate and write (or skip) the dark variant for one token set,
	 * handling freshness (source-hash comparison), directory creation, and
	 * write failures — all degrading to a logged warning, never an
	 * exception (theming is presentation, never allowed to fatal the app).
	 *
	 * @param string $setId The token set id.
	 * @param bool $force Regenerate even when the existing file is fresh.
	 *
	 * @return array{written: bool, skipped: bool, reason: string, warnings: array<int, array<string, mixed>>}
	 *                                                                                                         The outcome.
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag) - `$force` mirrors the standard CLI
	 * `--force` convention (also `occ thematiq:generate-dark-variants --force`); a
	 * skip-if-fresh/force-regenerate toggle on one write path, not two responsibilities.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	public function generateAndWrite(string $setId, bool $force = false): array {
		$appPath = $this->appManager->getAppPath('thematiq');
		if ($this->isUploaded(setId: $setId) === false && $this->mayWriteShipped === false) {
			// A shipped set's dark variant is build output, signed with the
			// release. Rewriting it here is exactly the code integrity warning
			// this guard exists to prevent; a stale one fails the build instead.
			return ['written' => false, 'skipped' => true, 'reason' => 'shipped', 'warnings' => []];
		}

		$darkName = 'css/tokens/dark/' . $setId . '.css';
		$meta = $this->loadSetMeta(appPath: $appPath, setId: $setId);
		if ($this->isEligible(designSystemId: $meta['design_system']) === false) {
			return ['written' => false, 'skipped' => true, 'reason' => 'ineligible', 'warnings' => []];
		}

		$tokenCss = $this->readTokenCss(setId: $setId);
		if ($tokenCss === null) {
			return ['written' => false, 'skipped' => true, 'reason' => 'source-missing', 'warnings' => []];
		}

		$sourceHash = 'sha256:' . hash(algo: 'sha256', data: $tokenCss);
		if ($force === false && $this->isFresh(darkCss: $this->store->read(name: $darkName), sourceHash: $sourceHash) === true) {
			return ['written' => false, 'skipped' => true, 'reason' => 'fresh', 'warnings' => []];
		}

		$generated = $this->generateForSet(setId: $setId);
		if ($generated === null) {
			return ['written' => false, 'skipped' => true, 'reason' => 'ineligible', 'warnings' => []];
		}

		try {
			$this->store->write(name: $darkName, content: $generated['css']);
		} catch (\Throwable $e) {
			$this->logger->warning(
				'NL Design dark variant for "{set}" could not be stored; the set stays light-only.',
				['set' => $setId, 'exception' => $e]
			);
			return ['written' => false, 'skipped' => true, 'reason' => 'not-writable', 'warnings' => $generated['warnings']];
		}

		return ['written' => true, 'skipped' => false, 'reason' => '', 'warnings' => $generated['warnings']];
	}//end generateAndWrite()

	/**
	 * Generate (or skip) every discovered token set's dark variant.
	 *
	 * @param bool $force Regenerate even fresh files.
	 *
	 * The per-set outcome, keyed by set id.
	 *
	 * @return array<string, array{written: bool, skipped: bool, reason: string, warnings: array<int, array<string, mixed>>}>
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag) - see generateAndWrite()'s rationale; this
	 * simply fans the same `--force` convention out over every discovered set.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	public function generateAll(bool $force = false): array {
		$results = [];
		foreach ($this->discoverAllSetIds() as $setId) {
			$results[$setId] = $this->generateAndWrite(setId: $setId, force: $force);
		}

		return $results;
	}//end generateAll()

	/**
	 * Delete a custom set's dark variant file, if any (best-effort, never throws).
	 *
	 * @param string $setId The custom set id.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	public function deleteDarkVariant(string $setId): void {
		if ($this->isUploaded(setId: $setId) === false && $this->mayWriteShipped === false) {
			return;
		}

		$this->store->delete(name: 'css/tokens/dark/' . $setId . '.css');
	}//end deleteDarkVariant()

	/**
	 * Whether an existing dark file was generated from the current source BY
	 * THE CURRENT GENERATOR.
	 *
	 * Both halves are required, and the second was missing: the version was
	 * stamped into every header and read by nothing, so {@see
	 * self::GENERATOR_VERSION} documented a way to force regeneration that did
	 * not exist. A generator fix then landed on an installation whose sources
	 * had not changed, every set reported "skipped (fresh)", and the artefacts
	 * still on disk were the ones the fix was written to replace — a silent
	 * no-op that reads exactly like a successful run.
	 *
	 * @param string|null $darkCss The existing dark stylesheet, or null when there is none.
	 * @param string $sourceHash The current `sha256:...` source hash.
	 *
	 * @return bool True when the file is fresh.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function isFresh(?string $darkCss, string $sourceHash): bool {
		if ($darkCss === null) {
			return false;
		}

		$header = substr($darkCss, 0, 512);
		if (str_contains($header, 'DarkPaletteService v' . self::GENERATOR_VERSION . ' ') === false) {
			return false;
		}

		return str_contains($header, $sourceHash);
	}//end isFresh()

	/**
	 * Parse a CSS file's `:root`-scoped declarations into a map (empty when absent/unreadable).
	 *
	 * @param string $filePath The absolute file path.
	 *
	 * @return array<string, string> The parsed declarations.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function parseFile(string $filePath): array {
		if (is_file($filePath) === false) {
			return [];
		}

		$content = file_get_contents($filePath);
		if ($content === false) {
			return [];
		}

		return ($this->parser->parseDeclarations(content: $content) ?? []);
	}//end parseFile()

	/**
	 * Convert an RGB triple to [hue, saturation, lightness] (hue in degrees
	 * 0-360, saturation/lightness in [0, 1]).
	 *
	 * @param array{0:int,1:int,2:int} $rgb The RGB triple (0-255 each).
	 *
	 * @return array{0: float, 1: float, 2: float} The [hue, saturation, lightness] triple.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function rgbToHsl(array $rgb): array {
		$r = ($rgb[0] / 255);
		$g = ($rgb[1] / 255);
		$b = ($rgb[2] / 255);

		$max = max($r, $g, $b);
		$min = min($r, $g, $b);
		$l = (($max + $min) / 2);

		if ($max === $min) {
			return [0.0, 0.0, $l];
		}

		$delta = ($max - $min);

		$s = ($delta / ($max + $min));
		if ($l > 0.5) {
			$s = ($delta / (2 - $max - $min));
		}

		$h = $this->hueSector(r: $r, g: $g, b: $b, max: $max, delta: $delta);

		// Use fmod(), not `%` — the hue is a float, and PHP's `%` silently
		// truncates both operands to int, which would corrupt sub-degree hue
		// precision (and break the "hue preserved" contract).
		$h = fmod($h * 60, 360);
		if ($h < 0) {
			$h += 360;
		}

		return [$h, $s, $l];
	}//end rgbToHsl()

	/**
	 * Compute the pre-normalisation hue sector value (before the `* 60` scale
	 * and `fmod(…, 360)` wrap) for the standard RGB->HSL conversion. Early
	 * returns instead of an if/else-if/else chain, one sector per channel
	 * being the maximum.
	 *
	 * @param float $r The red channel, normalised to [0, 1].
	 * @param float $g The green channel, normalised to [0, 1].
	 * @param float $b The blue channel, normalised to [0, 1].
	 * @param float $max The maximum of r/g/b.
	 * @param float $delta The max-min spread.
	 *
	 * @return float The pre-normalisation hue sector value.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function hueSector(float $r, float $g, float $b, float $max, float $delta): float {
		if ($max === $g) {
			return ((($b - $r) / $delta) + 2);
		}

		if ($max === $b) {
			return ((($r - $g) / $delta) + 4);
		}

		// $max === $r.
		$gLessThanB = 0;
		if ($g < $b) {
			$gLessThanB = 6;
		}

		return ((($g - $b) / $delta) + $gLessThanB);
	}//end hueSector()

	/**
	 * Convert [hue, saturation, lightness] to an RGB triple.
	 *
	 * @param float $hue Hue in degrees [0, 360).
	 * @param float $saturation Saturation in [0, 1].
	 * @param float $lightness Lightness in [0, 1].
	 *
	 * @return array{0:int,1:int,2:int} The RGB triple (0-255 each).
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function hslToRgb(float $hue, float $saturation, float $lightness): array {
		$saturation = $this->clamp(value: $saturation, min: 0.0, max: 1.0);
		$lightness = $this->clamp(value: $lightness, min: 0.0, max: 1.0);

		if ($saturation === 0.0) {
			$v = (int)round($lightness * 255);

			return [$v, $v, $v];
		}

		$q = (($lightness + $saturation) - ($lightness * $saturation));
		if ($lightness < 0.5) {
			$q = ($lightness * (1 + $saturation));
		}

		$p = ((2 * $lightness) - $q);

		// Use fmod(), not `%` — see the rgbToHsl() note above.
		$hNorm = (fmod($hue, 360) / 360);
		if ($hNorm < 0) {
			$hNorm += 1;
		}

		$r = $this->hueToRgbChannel(p: $p, q: $q, t: ($hNorm + (1 / 3)));
		$g = $this->hueToRgbChannel(p: $p, q: $q, t: $hNorm);
		$b = $this->hueToRgbChannel(p: $p, q: $q, t: ($hNorm - (1 / 3)));

		return [
			(int)round($r * 255),
			(int)round($g * 255),
			(int)round($b * 255),
		];
	}//end hslToRgb()

	/**
	 * One channel of the standard HSL->RGB conversion.
	 *
	 * @param float $p The P intermediate.
	 * @param float $q The Q intermediate.
	 * @param float $t The hue-shifted normalised position.
	 *
	 * @return float The channel value in [0, 1].
	 */
	private function hueToRgbChannel(float $p, float $q, float $t): float {
		if ($t < 0) {
			$t += 1;
		}

		if ($t > 1) {
			$t -= 1;
		}

		if ($t < (1 / 6)) {
			return ($p + (($q - $p) * 6 * $t));
		}

		if ($t < 0.5) {
			return $q;
		}

		if ($t < (2 / 3)) {
			return ($p + (($q - $p) * ((2 / 3) - $t) * 6));
		}

		return $p;
	}//end hueToRgbChannel()

	/**
	 * Convert an RGB triple to a lowercase `#rrggbb` hex string.
	 *
	 * @param array{0:int,1:int,2:int} $rgb The RGB triple.
	 *
	 * @return string The hex colour string.
	 */
	private function rgbToHex(array $rgb): string {
		return sprintf(
			'#%02x%02x%02x',
			$this->clampInt(value: $rgb[0]),
			$this->clampInt(value: $rgb[1]),
			$this->clampInt(value: $rgb[2])
		);
	}//end rgbToHex()

	/**
	 * The fourth hex pair for an alpha below 1, or '' for an opaque colour.
	 *
	 * @param float $alpha The alpha, 0-1.
	 *
	 * @return string Two lowercase hex digits, or an empty string.
	 *
	 * @spec openspec/specs/dark-mode/spec.md#requirement-derived-dark-values-keep-the-light-values-alpha
	 */
	private function alphaSuffix(float $alpha): string {
		if ($alpha >= 1.0) {
			return '';
		}

		return sprintf('%02x', $this->clampInt(value: (int)round($alpha * 255)));
	}//end alphaSuffix()

	/**
	 * Clamp a float into [min, max].
	 *
	 * @param float $value The value.
	 * @param float $min The minimum.
	 * @param float $max The maximum.
	 *
	 * @return float The clamped value.
	 */
	private function clamp(float $value, float $min, float $max): float {
		return max($min, min($max, $value));
	}//end clamp()

	/**
	 * Clamp an int channel into [0, 255].
	 *
	 * @param int $value The channel value.
	 *
	 * @return int The clamped value.
	 */
	private function clampInt(int $value): int {
		return max(0, min(255, $value));
	}//end clampInt()
}//end class
