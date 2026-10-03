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
	];

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
		$root = dirname(__DIR__, 3);
		$parser = new CssParserService();
		$contrast = new ContrastService();

		$light = ($parser->parseDeclarations((string)file_get_contents($root . '/css/systems/nldesign/defaults.css')) ?? []);
		if (is_file($root . '/css/tokens/' . $setId . '.css') === true) {
			$light = array_merge($light, ($parser->parseDeclarations((string)file_get_contents($root . '/css/tokens/' . $setId . '.css')) ?? []));
		}

		$dark = ($parser->parseDeclarations((string)file_get_contents($root . '/css/tokens/dark/' . $setId . '.css')) ?? []);

		$value = function (string $token) use ($light, $dark): ?string {
			if (isset($dark[$token]) === true) {
				return $this->resolve(value: $dark[$token], declarations: $dark + $light);
			}

			if (isset($light[$token]) === true) {
				return $this->resolve(value: $light[$token], declarations: $light);
			}

			return null;
		};

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

			$ratio = $contrast->measure(foreground: $foreground, background: $background, page: $page);
			$this->assertNotNull($ratio, $setId . ' ' . $name . ': cannot measure ' . $foreground . ' on ' . $background);
			if ($ratio < $threshold) {
				$failures[] = sprintf('%s: %s on %s = %.2f:1', $name, $foreground, $background, $ratio);
			}
		}

		$this->assertSame([], $failures, $setId . ' dark');
	}//end testInputsAndPrimaryButtonsReachAa()

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
