<?php

/**
 * Summer Breeze keeps its own text, controls and status colours readable in
 * light mode and in both dark scopes.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/dark-mode/spec.md#requirement-summer-breeze-is-legible-in-light-and-in-both-dark-scopes
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use PHPUnit\Framework\TestCase;

/**
 * Summer Breeze runs its own design system: `theme.css` maps `--summer-*`
 * tokens onto Nextcloud's variables and never reads a `--nldesign-*` name.
 * {@see ShippedDarkContrastTest} measures the `--nldesign-*` pairs, so for
 * this set it measured Rijkshuisstijl defaults the page never paints
 * (thematiq#1022). This test measures the pairs Summer Breeze actually paints,
 * from the COMMITTED files: `css/tokens/summer-breeze.css` for light, and each
 * of the two dark scopes of `css/tokens/dark/summer-breeze.css` on its own
 * (the `prefers-color-scheme` block for "auto" users and the explicit
 * `data-theme-dark` block), because a token remapped in one scope only leaves
 * the other scope on the light value.
 */
class SummerBreezeContrastTest extends TestCase {

	/**
	 * Foreground, background and threshold for what theme.css and
	 * element-overrides.css paint: normal-size text at 4.5:1, an input's
	 * border at the 3:1 of a component boundary (WCAG SC 1.4.11).
	 *
	 * The surface is Nextcloud's main background (panels, inputs, the header);
	 * the plain background is the page behind them.
	 *
	 * @var array<string, array{0: string, 1: string, 2?: float}>
	 */
	public const PAIRS = [
		'body text on a panel' => ['--summer-color-text', '--summer-color-surface'],
		'body text on the page' => ['--summer-color-text', '--summer-color-background-plain'],
		'body text on a hovered row' => ['--summer-color-text', '--summer-color-background-hover'],
		'muted text on a panel' => ['--summer-color-text-muted', '--summer-color-surface'],
		'muted text on the page' => ['--summer-color-text-muted', '--summer-color-background-plain'],
		'view toggle label on its track' => ['--summer-color-text-muted', '--summer-color-toggle-track'],
		'primary button label' => ['--summer-color-primary-text', '--summer-color-primary'],
		'primary button label on hover' => ['--summer-color-primary-text', '--summer-color-primary-hover'],
		'secondary button label' => ['--summer-color-primary', '--summer-color-primary-light'],
		'secondary button label on hover' => ['--summer-color-primary', '--summer-color-primary-light-hover'],
		'link on a panel' => ['--summer-color-primary', '--summer-color-surface'],
		'link on the page' => ['--summer-color-primary', '--summer-color-background-plain'],
		'input border on the input' => ['--summer-color-border-input', '--summer-color-surface', 3.0],
		'error text on its badge' => ['--summer-color-error', '--summer-color-error-light'],
		'error text on a panel' => ['--summer-color-error', '--summer-color-surface'],
		'warning text on its badge' => ['--summer-color-warning', '--summer-color-warning-light'],
		'warning text on a panel' => ['--summer-color-warning', '--summer-color-surface'],
		'success text on its badge' => ['--summer-color-success', '--summer-color-success-light'],
		'success text on a panel' => ['--summer-color-success', '--summer-color-surface'],
		'info text on its badge' => ['--summer-color-info', '--summer-color-info-light'],
		'info text on a panel' => ['--summer-color-info', '--summer-color-surface'],
	];

	/**
	 * The modes measured: light, and each dark scope on its own.
	 *
	 * @return array<string, array{0: string}> Mode per case.
	 */
	public static function modeProvider(): array {
		return [
			'light' => ['light'],
			'dark, auto theme (prefers-color-scheme)' => ['media'],
			'dark, explicit dark theme' => ['explicit'],
		];
	}//end modeProvider()

	/**
	 * Every pair reaches its threshold in the given mode.
	 *
	 * @dataProvider modeProvider
	 *
	 * @param string $mode The mode: light, media or explicit.
	 *
	 * @spec openspec/specs/dark-mode/spec.md#requirement-summer-breeze-is-legible-in-light-and-in-both-dark-scopes
	 */
	public function testEveryPaintedPairReachesAa(string $mode): void {
		$contrast = new ContrastService();
		$values = $this->values(mode: $mode);

		$failures = [];
		foreach (self::PAIRS as $name => $pair) {
			[$fgToken, $bgToken] = $pair;
			$threshold = ($pair[2] ?? 4.5);
			$this->assertArrayHasKey($fgToken, $values, $mode . ': ' . $fgToken . ' is not declared');
			$this->assertArrayHasKey($bgToken, $values, $mode . ': ' . $bgToken . ' is not declared');

			$ratio = $contrast->measure(foreground: $values[$fgToken], background: $values[$bgToken], page: $values['--summer-color-background-plain']);
			$this->assertNotNull($ratio, $mode . ' ' . $name . ': cannot measure ' . $values[$fgToken] . ' on ' . $values[$bgToken]);
			if ($ratio < $threshold) {
				$failures[] = sprintf('%s: %s on %s = %.2f:1 (needs %.1f:1)', $name, $values[$fgToken], $values[$bgToken], $ratio, $threshold);
			}
		}

		$this->assertSame([], $failures, 'summer-breeze ' . $mode);
	}//end testEveryPaintedPairReachesAa()

	/**
	 * The page and the panels are dark in both dark scopes and light in light
	 * mode (relative luminance at most 0.05, at least 0.8).
	 *
	 * @dataProvider modeProvider
	 *
	 * @param string $mode The mode: light, media or explicit.
	 *
	 * @spec openspec/specs/dark-mode/spec.md#requirement-summer-breeze-is-legible-in-light-and-in-both-dark-scopes
	 */
	public function testSurfacesFollowTheMode(string $mode): void {
		$contrast = new ContrastService();
		$values = $this->values(mode: $mode);

		foreach (['--summer-color-surface', '--summer-color-background-plain'] as $token) {
			$rgb = $contrast->parseColor(value: $values[$token]);
			$this->assertNotNull($rgb, $mode . ': cannot parse ' . $token);
			$luminance = (($contrast->ratio(first: $rgb, second: [0, 0, 0]) * 0.05) - 0.05);
			if ($mode === 'light') {
				$this->assertGreaterThanOrEqual(0.8, $luminance, sprintf('%s %s = %s', $mode, $token, $values[$token]));
				continue;
			}

			$this->assertLessThanOrEqual(0.05, $luminance, sprintf('%s %s = %s', $mode, $token, $values[$token]));
		}
	}//end testSurfacesFollowTheMode()

	/**
	 * Every colour token the light set declares has a dark value in BOTH dark
	 * scopes, and the two scopes agree: a token left out keeps its light value
	 * on a dark page.
	 *
	 * @spec openspec/specs/dark-mode/spec.md#requirement-summer-breeze-is-legible-in-light-and-in-both-dark-scopes
	 */
	public function testBothDarkScopesRemapEveryColourToken(): void {
		$contrast = new ContrastService();
		$light = $this->lightDeclarations();
		[$media, $explicit] = $this->darkScopes();

		$colours = array_keys(array_filter(
			$light,
			static fn (string $value, string $name): bool => str_starts_with($name, '--summer-color-')
				&& $contrast->parseColorWithAlpha(value: $value) !== null,
			ARRAY_FILTER_USE_BOTH
		));
		$this->assertNotEmpty($colours, 'The light set declares no --summer-color-* literal at all.');

		$summer = static fn (array $scope): array => array_filter(
			$scope,
			static fn (string $name): bool => str_starts_with($name, '--summer-'),
			ARRAY_FILTER_USE_KEY
		);

		$this->assertSame([], array_values(array_diff($colours, array_keys($media))), 'Colour tokens the prefers-color-scheme scope leaves on their light value.');
		$this->assertSame([], array_values(array_diff($colours, array_keys($explicit))), 'Colour tokens the explicit dark scope leaves on their light value.');
		$this->assertSame($summer($media), $summer($explicit), 'The two dark scopes must declare the same --summer-* values.');
	}//end testBothDarkScopesRemapEveryColourToken()

	/**
	 * The committed dark file was generated from the committed light file: its
	 * header names the light file's sha256.
	 *
	 * @spec openspec/specs/dark-mode/spec.md#requirement-summer-breeze-is-legible-in-light-and-in-both-dark-scopes
	 */
	public function testTheDarkFileWasGeneratedFromTheCurrentLightFile(): void {
		$root = dirname(__DIR__, 3);
		$dark = (string)file_get_contents($root . '/css/tokens/dark/summer-breeze.css');
		$hash = hash('sha256', (string)file_get_contents($root . '/css/tokens/summer-breeze.css'));

		$this->assertStringContainsString('GENERATED by', $dark);
		$this->assertStringContainsString('sha256:' . $hash, $dark, 'css/tokens/dark/summer-breeze.css is stale: run php scripts/generate-dark-variants.php');
	}//end testTheDarkFileWasGeneratedFromTheCurrentLightFile()

	/**
	 * The shipped contrast audit (the admin dropdown, the capabilities
	 * document and the generated docs) reports the colours Summer Breeze
	 * paints, not the Rijkshuisstijl defaults underneath it (thematiq#1022:
	 * it reported 10.20:1, which is #ffffff on the defaults' #154273).
	 *
	 * @spec openspec/specs/dark-mode/spec.md#requirement-summer-breeze-is-legible-in-light-and-in-both-dark-scopes
	 */
	public function testTheShippedAuditMeasuresSummerBreezeOnItsOwnColours(): void {
		$contrast = new ContrastService();
		$light = $this->values(mode: 'light');
		$manifest = json_decode((string)file_get_contents(dirname(__DIR__, 3) . '/token-sets.json'), true);
		$theming = [];
		foreach ((array)$manifest as $set) {
			if (is_array($set) === true && ($set['id'] ?? null) === 'summer-breeze') {
				$theming = (array)($set['theming'] ?? []);
			}
		}

		$result = (new ShippedTokenSetAuditService($contrast, new CssParserService()))->auditSet(dirname(__DIR__, 3), 'summer-breeze', $theming);

		$this->assertSame(
			round((float)$contrast->measure(foreground: $light['--summer-color-primary-text'], background: $light['--summer-color-primary']), 2),
			$result['textRatio'],
			'primary text on primary must be measured on --summer-color-primary-text and --summer-color-primary'
		);
		$this->assertSame(
			round((float)$contrast->measure(foreground: $light['--summer-color-primary'], background: $light['--summer-color-background-plain']), 2),
			$result['uiRatio'],
			'primary on background must be measured on --summer-color-primary and --summer-color-background-plain'
		);
		$this->assertSame('pass', $result['verdict']);
	}//end testTheShippedAuditMeasuresSummerBreezeOnItsOwnColours()

	/**
	 * The effective literal value of every token in a mode.
	 *
	 * Light: the set's own declarations. Dark: the scope's declarations win,
	 * a token it leaves out keeps its light value.
	 *
	 * @param string $mode The mode: light, media or explicit.
	 *
	 * @return array<string, string> Token name to resolved value.
	 */
	private function values(string $mode): array {
		$light = $this->lightDeclarations();
		$declarations = $light;
		if ($mode !== 'light') {
			[$media, $explicit] = $this->darkScopes();
			$scope = $explicit;
			if ($mode === 'media') {
				$scope = $media;
			}

			$declarations = array_merge($light, $scope);
		}

		$resolved = [];
		foreach ($declarations as $name => $value) {
			$resolved[$name] = $this->resolve(value: $value, declarations: $declarations);
		}

		return $resolved;
	}//end values()

	/**
	 * The light set's declarations.
	 *
	 * @return array<string, string> Token name to raw value.
	 */
	private function lightDeclarations(): array {
		$css = (string)file_get_contents(dirname(__DIR__, 3) . '/css/tokens/summer-breeze.css');

		return ((new CssParserService())->parseDeclarations((string)preg_replace('#/\*.*?\*/#s', '', $css)) ?? []);
	}//end lightDeclarations()

	/**
	 * The two dark scopes of the committed dark file, parsed separately.
	 *
	 * @return array{0: array<string, string>, 1: array<string, string>} The prefers-color-scheme scope, then the explicit one.
	 */
	private function darkScopes(): array {
		$css = (string)preg_replace('#/\*.*?\*/#s', '', (string)file_get_contents(dirname(__DIR__, 3) . '/css/tokens/dark/summer-breeze.css'));

		$start = strpos($css, '@media');
		$this->assertNotFalse($start, 'The dark file has no prefers-color-scheme block.');
		$open = strpos($css, '{', $start);
		$this->assertNotFalse($open);

		$depth = 0;
		$end = $open;
		for ($i = $open; $i < strlen($css); $i++) {
			if ($css[$i] === '{') {
				$depth++;
			} elseif ($css[$i] === '}') {
				$depth--;
				if ($depth === 0) {
					$end = $i;
					break;
				}
			}
		}

		$parser = new CssParserService();
		$media = ($parser->parseDeclarations(substr($css, $open + 1, $end - $open - 1)) ?? []);
		$explicit = ($parser->parseDeclarations(substr($css, 0, $start) . substr($css, $end + 1)) ?? []);
		$this->assertNotSame([], $media, 'The prefers-color-scheme scope declares nothing.');
		$this->assertNotSame([], $explicit, 'The explicit dark scope declares nothing.');

		return [$media, $explicit];
	}//end darkScopes()

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
