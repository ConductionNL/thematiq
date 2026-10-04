<?php

/**
 * Every shipped dark variant keeps its inputs and primary buttons readable.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/dark-mode/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use PHPUnit\Framework\TestCase;

/**
 * Reads the COMMITTED `css/tokens/dark/*.css` files, the ones the browser
 * gets, not a fresh generator run. Each is resolved the way the cascade does:
 * a token the dark file declares wins; one it leaves out keeps its light
 * value, resolved at `:root` against the light declarations (defaults.css,
 * then the set's own file).
 *
 * thematiq#938: rijkshuisstijl dark shipped inputs at 1.96:1 (#d5ddf0 on
 * #9e9e9e) and primary buttons at 1.16:1 (#9e9e9e on #81afe2).
 */
class ShippedDarkContrastTest extends TestCase {

	/**
	 * Foreground, background and threshold, for the controls theme.css paints
	 * from these tokens: normal-size text at 4.5:1, the input border at the
	 * 3:1 of a component boundary (WCAG SC 1.4.11).
	 *
	 * @var array<string, array{0: string, 1: string, 2?: float}>
	 */
	private const PAIRS = [
		'input text' => [
			'--nldesign-component-textbox-color',
			'--nldesign-component-textbox-background-color',
		],
		'input border on the input' => [
			'--nldesign-component-textbox-border-color',
			'--nldesign-component-textbox-background-color',
			3.0,
		],
		'primary button label' => [
			'--nldesign-component-button-primary-action-color',
			'--nldesign-component-button-primary-action-background-color',
		],
		'primary button label on hover' => [
			'--nldesign-component-button-primary-action-hover-color',
			'--nldesign-component-button-primary-action-hover-background-color',
		],
		'primary button colour tokens' => [
			'--nldesign-color-button-primary-text',
			'--nldesign-color-button-primary-background',
		],
		// Nextcloud's own secondary button: primary-coloured text on the
		// primary-light wash (overrides.css maps --color-primary-element-light
		// and its -text there), at rest and on hover (thematiq#969).
		'secondary button label' => [
			'--nldesign-color-primary',
			'--nldesign-color-primary-light',
		],
		'secondary button label on hover' => [
			'--nldesign-color-primary',
			'--nldesign-color-primary-light-hover',
		],
		'secondary button component label' => [
			'--nldesign-component-button-secondary-action-color',
			'--nldesign-component-button-secondary-action-background-color',
		],
		'secondary button component label on hover' => [
			'--nldesign-component-button-secondary-action-color',
			'--nldesign-component-button-secondary-action-hover-background-color',
		],
		'body text on the page' => [
			'--nldesign-color-text',
			'--nldesign-color-background',
		],
		'link on the page' => [
			'--nldesign-component-link-color',
			'--nldesign-color-background',
		],
	];

	/**
	 * What a high-contrast set's dark variant is held to, on top of {@see self::PAIRS}.
	 *
	 * The set promises WCAG AAA (thematiq#1023), and the high-contrast design
	 * system paints every one of these from the token named here
	 * (css/systems/high-contrast/theme.css and element-overrides.css): text at
	 * the AAA 7:1, a boundary or fill against the page at the 4.5:1 the
	 * contrast audit uses for AAA non-text contrast.
	 *
	 * @var array<string, array{0: string, 1: string, 2: string}> Foreground, background, `text` or `ui`.
	 */
	private const AAA_PAIRS = [
		'body text on the page' => ['--nldesign-color-text', '--nldesign-color-background', 'text'],
		'muted text on the page' => ['--nldesign-color-text-muted', '--nldesign-color-background', 'text'],
		'light text on the page' => ['--nldesign-color-text-light', '--nldesign-color-background', 'text'],
		'body text on the hover surface' => ['--nldesign-color-text', '--nldesign-color-background-hover', 'text'],
		'body text on the dark surface' => ['--nldesign-color-text', '--nldesign-color-background-dark', 'text'],
		'body text on the darker surface' => ['--nldesign-color-text', '--nldesign-color-background-darker', 'text'],
		'body text on the navigation' => ['--nldesign-color-text', '--nldesign-color-nav-background', 'text'],
		'primary button label' => ['--nldesign-color-primary-text', '--nldesign-color-primary', 'text'],
		'primary button label on hover' => ['--nldesign-color-primary-text', '--nldesign-color-primary-hover', 'text'],
		'secondary button label' => ['--nldesign-color-primary', '--nldesign-color-primary-light', 'text'],
		'secondary button label on hover' => ['--nldesign-color-primary', '--nldesign-color-primary-light-hover', 'text'],
		'header text on the header' => ['--nldesign-color-header-text', '--nldesign-color-header-background', 'text'],
		'link on the page' => ['--nldesign-color-link', '--nldesign-color-background', 'text'],
		'link on hover on the page' => ['--nldesign-color-link-hover', '--nldesign-color-background', 'text'],
		'placeholder on the page' => ['--nldesign-color-placeholder-dark', '--nldesign-color-background', 'text'],
		'error text on the page' => ['--nldesign-color-error', '--nldesign-color-background', 'text'],
		'warning text on the page' => ['--nldesign-color-warning', '--nldesign-color-background', 'text'],
		'success text on the page' => ['--nldesign-color-success', '--nldesign-color-background', 'text'],
		'info text on the page' => ['--nldesign-color-info', '--nldesign-color-background', 'text'],
		'error button label' => ['--nldesign-component-button-error-color', '--nldesign-color-error', 'text'],
		'border on the page' => ['--nldesign-color-border', '--nldesign-color-background', 'ui'],
		'strong border on the page' => ['--nldesign-color-border-dark', '--nldesign-color-background', 'ui'],
		'focus ring on the page' => ['--nldesign-color-focus', '--nldesign-color-background', 'ui'],
		'primary fill on the page' => ['--nldesign-color-primary', '--nldesign-color-background', 'ui'],
	];

	/**
	 * Every shipped set the high-contrast design system wears, or that declares AAA.
	 *
	 * @return array<string, array{0: string}> Set id per case.
	 */
	public static function highContrastSetProvider(): array {
		$manifest = json_decode((string)file_get_contents(dirname(__DIR__, 3) . '/token-sets.json'), true);
		$cases = [];
		foreach ((is_array($manifest) === true ? $manifest : []) as $set) {
			if (self::isHighContrast(set: $set) === true) {
				$cases[$set['id']] = [$set['id']];
			}
		}

		return $cases;
	}//end highContrastSetProvider()

	/**
	 * Whether a manifest entry is held to AAA, the rule the contrast audit uses.
	 *
	 * @param array<string, mixed> $set The manifest entry.
	 *
	 * @return bool True for a high-contrast or AAA-declaring set.
	 */
	private static function isHighContrast(array $set): bool {
		return (($set['design_system'] ?? '') === 'high-contrast') || (($set['contrast_level'] ?? '') === 'AAA');
	}//end isHighContrast()

	/**
	 * The provider finds the shipped high-contrast set, so the AAA cases cannot pass empty.
	 */
	public function testThereIsAHighContrastSet(): void {
		$this->assertArrayHasKey('hoog-contrast', self::highContrastSetProvider());
	}//end testThereIsAHighContrastSet()

	/**
	 * A high-contrast set ships a dark variant (thematiq#1023): without one its
	 * surfaces stay white in Nextcloud's dark and dark-highcontrast themes.
	 *
	 * @dataProvider highContrastSetProvider
	 *
	 * @param string $setId The token set id.
	 */
	public function testHighContrastSetShipsADarkVariant(string $setId): void {
		$this->assertFileExists(dirname(__DIR__, 3) . '/css/tokens/dark/' . $setId . '.css', $setId . ' has no dark variant');
	}//end testHighContrastSetShipsADarkVariant()

	/**
	 * A high-contrast set's dark variant reaches AAA: 7:1 for text, 4.5:1 for
	 * borders, fills and the focus ring.
	 *
	 * @dataProvider highContrastSetProvider
	 *
	 * @param string $setId The token set id.
	 */
	public function testHighContrastDarkVariantReachesAaa(string $setId): void {
		$this->assertFileExists(dirname(__DIR__, 3) . '/css/tokens/dark/' . $setId . '.css');

		$contrast = new ContrastService();
		$value = $this->darkValues(setId: $setId);
		$page = $value('--nldesign-color-background');
		$this->assertNotNull($page, $setId . ' dark declares no page background');

		$failures = [];
		foreach (self::AAA_PAIRS as $name => [$fgToken, $bgToken, $kind]) {
			$threshold = ShippedTokenSetAuditService::AAA_UI;
			if ($kind === 'text') {
				$threshold = ShippedTokenSetAuditService::AAA_TEXT;
			}

			$foreground = $value($fgToken);
			$background = $value($bgToken);
			if ($foreground === null || $background === null) {
				$failures[] = sprintf('%s: %s or %s is not declared', $name, $fgToken, $bgToken);
				continue;
			}

			$ratio = $contrast->measure(foreground: $foreground, background: $background, page: $page);
			if ($ratio === null || $ratio < $threshold) {
				$failures[] = sprintf('%s: %s on %s = %s, needs %.1f:1', $name, $foreground, $background, ($ratio === null ? 'unmeasurable' : sprintf('%.2f:1', $ratio)), $threshold);
			}
		}

		$this->assertSame([], $failures, $setId . ' dark');
	}//end testHighContrastDarkVariantReachesAaa()

	/**
	 * A high-contrast dark variant applies in both dark scopes with the same
	 * values: the system preference without an explicit theme, and an
	 * explicit dark choice (`body[data-themes*=dark]` also matches Nextcloud's
	 * dark-highcontrast theme). A token in one scope only would leave that
	 * path half light.
	 *
	 * @dataProvider highContrastSetProvider
	 *
	 * @param string $setId The token set id.
	 */
	public function testHighContrastDarkVariantCoversBothScopesAlike(string $setId): void {
		$css = (string)file_get_contents(dirname(__DIR__, 3) . '/css/tokens/dark/' . $setId . '.css');
		$css = (string)preg_replace('#/\*.*?\*/#s', '', $css);

		$media = [];
		$this->assertSame(
			1,
			preg_match('/@media \(prefers-color-scheme: dark\) \{\s*body:not\(\[data-theme-light\]\):not\(\[data-theme-dark\]\):not\(\[data-theme-light-highcontrast\]\):not\(\[data-theme-dark-highcontrast\]\) \{([^}]*)\}\s*\}/', $css, $media),
			$setId . ': no system-preference dark scope'
		);
		$explicit = [];
		$this->assertSame(
			1,
			preg_match('/body\[data-theme-dark\],\s*body\[data-themes\*=dark\] \{([^}]*)\}/', $css, $explicit),
			$setId . ': no explicit dark scope'
		);

		$parser = new CssParserService();
		$fromMedia = ($parser->parseDeclarations(':root {' . $media[1] . '}') ?? []);
		$fromExplicit = ($parser->parseDeclarations(':root {' . $explicit[1] . '}') ?? []);
		$this->assertNotSame([], $fromMedia);
		$this->assertSame($fromMedia, $fromExplicit, $setId . ': the two dark scopes differ');
	}//end testHighContrastDarkVariantCoversBothScopesAlike()

	/**
	 * Every committed dark variant.
	 *
	 * @return array<string, array{0: string}> Set id per case.
	 */
	public static function darkSetProvider(): array {
		$cases = [];
		foreach (glob(dirname(__DIR__, 3) . '/css/tokens/dark/*.css') ?: [] as $file) {
			$id = basename($file, '.css');
			$cases[$id] = [$id];
		}

		return $cases;
	}//end darkSetProvider()

	/**
	 * The committed dark files exist at all, so the provider cannot pass empty.
	 */
	public function testThereAreShippedDarkVariants(): void {
		$this->assertGreaterThan(10, count(self::darkSetProvider()));
	}//end testThereAreShippedDarkVariants()

	/**
	 * Inputs and primary buttons reach 4.5:1 in the shipped dark variant.
	 *
	 * @dataProvider darkSetProvider
	 *
	 * @param string $setId The token set id.
	 */
	public function testInputsAndPrimaryButtonsReachAa(string $setId): void {
		$contrast = new ContrastService();
		$value = $this->darkValues(setId: $setId);

		$page = ($value('--nldesign-color-background') ?? '#171717');
		$failures = [];
		foreach (self::PAIRS as $name => $pair) {
			[$fgToken, $bgToken] = $pair;
			$threshold = ($pair[2] ?? 4.5);
			$foreground = $value($fgToken);
			$background = $value($bgToken);
			if ($foreground === null || $background === null) {
				continue;
			}

			// A transparent fill shows the page through it.
			if (strtolower($background) === 'transparent') {
				$background = $page;
			}

			$ratio = $contrast->measure(foreground: $foreground, background: $background, page: $page);
			$this->assertNotNull($ratio, $setId . ' ' . $name . ': cannot measure ' . $foreground . ' on ' . $background);
			if ($ratio < $threshold) {
				$failures[] = sprintf('%s: %s on %s = %.2f:1', $name, $foreground, $background, $ratio);
			}
		}

		$this->assertSame([], $failures, $setId . ' dark');
	}//end testInputsAndPrimaryButtonsReachAa()

	/**
	 * The error chip and error button label read on the error fill in the
	 * shipped dark variant (thematiq#1027). css/error-contrast.css paints both
	 * labels from `--nldesign-component-button-error-color`, white when the
	 * set leaves it out, on `--color-error`: `--nldesign-color-error`, or
	 * `--summer-color-error` on the summer-breeze system. The lasuite system
	 * paints its own ramp's red, which no token file declares;
	 * tests/vitest/errorLabelContrast.spec.js resolves every system's real
	 * cascade, lasuite included.
	 *
	 * @dataProvider darkSetProvider
	 *
	 * @param string $setId The token set id.
	 */
	public function testTheErrorLabelReadsOnTheErrorFill(string $setId): void {
		$contrast = new ContrastService();
		$value = $this->darkValues(setId: $setId);
		$label = ($value('--nldesign-component-button-error-color') ?? '#ffffff');

		$manifest = json_decode((string)file_get_contents(dirname(__DIR__, 3) . '/token-sets.json'), true);
		$designSystem = (array_column((is_array($manifest) === true ? $manifest : []), 'design_system', 'id')[$setId] ?? 'nldesign');
		$fillToken = ([
			'nldesign' => '--nldesign-color-error',
			'high-contrast' => '--nldesign-color-error',
			'summer-breeze' => '--summer-color-error',
		][$designSystem] ?? null);
		if ($fillToken === null) {
			$this->addToAssertionCount(1);
			return;
		}

		$fill = $value($fillToken);
		$this->assertNotNull($fill, $setId . ' dark declares no ' . $fillToken);
		$ratio = $contrast->measure(foreground: $label, background: $fill);
		$this->assertGreaterThanOrEqual(4.5, (float)$ratio, sprintf('%s dark error label %s on %s', $setId, $label, $fill));
	}//end testTheErrorLabelReadsOnTheErrorFill()

	/**
	 * The page background of a dark variant is dark (thematiq#952).
	 *
	 * vng shipped `#42b1f3`: its manifest's theming `background_color`
	 * (#0277BD, Nextcloud's header and login colour) stood in for the page
	 * background, and inverting a mid-tone lands on a mid-tone. Nextcloud's
	 * own dark page is #171717 (relative luminance 0.009); 0.05 leaves room
	 * for a tinted dark surface and refuses anything that reads as light.
	 *
	 * @dataProvider darkSetProvider
	 *
	 * @param string $setId The token set id.
	 */
	public function testThePageBackgroundIsDark(string $setId): void {
		$contrast = new ContrastService();
		$page = $this->darkValues(setId: $setId)('--nldesign-color-background');
		if ($page === null) {
			// The set leaves the page background to Nextcloud's own dark theme.
			$this->addToAssertionCount(1);
			return;
		}

		$rgb = $contrast->parseColor(value: $page);
		$this->assertNotNull($rgb, $setId . ': cannot parse ' . $page);
		// Relative luminance from the ratio against black: (L + 0.05) / 0.05.
		$luminance = (($contrast->ratio(first: $rgb, second: [0, 0, 0]) * 0.05) - 0.05);
		$this->assertLessThanOrEqual(0.05, $luminance, sprintf('%s dark page background %s has luminance %.3f', $setId, $page, $luminance));
	}//end testThePageBackgroundIsDark()

	/**
	 * A resolver for a set's effective dark values: a token the dark file
	 * declares wins; one it leaves out keeps its light value, resolved at
	 * `:root` against the light declarations.
	 *
	 * @param string $setId The token set id.
	 *
	 * @return \Closure(string): (string|null) Token name to resolved value, or null when undeclared.
	 */
	private function darkValues(string $setId): \Closure {
		$root = dirname(__DIR__, 3);
		$parser = new CssParserService();

		// The nldesign defaults are not part of a high-contrast set's cascade
		// (design-systems.json loads no defaults.css for that system), so the
		// component tokens they declare must not stand in for its values.
		$light = [];
		$set = (self::highContrastSetProvider()[$setId] ?? null);
		if ($set === null) {
			$light = ($parser->parseDeclarations((string)file_get_contents($root . '/css/systems/nldesign/defaults.css')) ?? []);
		}

		if (is_file($root . '/css/tokens/' . $setId . '.css') === true) {
			$light = array_merge($light, ($parser->parseDeclarations((string)file_get_contents($root . '/css/tokens/' . $setId . '.css')) ?? []));
		}

		$dark = ($parser->parseDeclarations((string)file_get_contents($root . '/css/tokens/dark/' . $setId . '.css')) ?? []);

		return function (string $token) use ($light, $dark): ?string {
			if (isset($dark[$token]) === true) {
				return $this->resolve(value: $dark[$token], declarations: $dark + $light);
			}

			if (isset($light[$token]) === true) {
				return $this->resolve(value: $light[$token], declarations: $light);
			}

			return null;
		};

	}//end darkValues()

	/**
	 * Follow `var()` references to a literal.
	 *
	 * @param string $value The declared value.
	 * @param array<string, string> $declarations Where the references resolve.
	 * @param int $depth Hops taken so far.
	 *
	 * @return string The literal, or the value unchanged when it does not resolve.
	 */
	private function resolve(string $value, array $declarations, int $depth = 0): string {
		$matches = [];
		if ($depth > 8 || preg_match('/^var\(\s*(--[A-Za-z0-9_-]+)\s*(?:,\s*(.+?)\s*)?\)$/s', trim($value), $matches) !== 1) {
			return trim($value);
		}

		if (isset($declarations[$matches[1]]) === true) {
			return $this->resolve(value: $declarations[$matches[1]], declarations: $declarations, depth: ($depth + 1));
		}

		return $this->resolve(value: ($matches[2] ?? $value), declarations: $declarations, depth: ($depth + 1));
	}//end resolve()
}//end class
