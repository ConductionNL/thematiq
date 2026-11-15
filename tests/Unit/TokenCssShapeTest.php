<?php

/**
 * Shipped token CSS structural-invariant regression test.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/app-token-set-selection/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Turns the empirically-verified-but-undocumented invariant the scoped-
 * application contract (design.md decision 3) depends on into a
 * mechanically-enforced regression guard: every shipped base/light
 * `css/tokens/*.css` file (excluding the generated `dark/` subdirectory,
 * which uses an unrelated `@media (prefers-color-scheme: dark) { ... }`
 * shape) MUST be exactly one flat `:root { }` block, with no at-rules
 * (`@media`, `@supports`, `@import`, `@font-face`) and no selector other
 * than `:root`. A shared client-side applier (the companion `nextcloud-vue`
 * change) relies on this shape to do a pure `:root` →
 * `[data-nldesign-theme-scope="<scopeId>"]` selector-prefix rewrite; a
 * future hand-authored shipped set is the one path not already validated
 * mechanically at write time the way `CustomTokenSetValidator` validates
 * custom uploads.
 *
 * No Nextcloud runtime required — pure filesystem + regex over the repo's
 * own `css/tokens/` directory.
 */
class TokenCssShapeTest extends TestCase {

	/**
	 * Repository root, derived from this test file's location.
	 *
	 * @return string
	 */
	private function repoRoot(): string {
		return \dirname(__DIR__, 2);
	}//end repoRoot()

	/**
	 * Every shipped base/light token CSS file path (excludes `dark/`).
	 *
	 * @return array<int, string> Absolute file paths.
	 */
	private function shippedTokenCssFiles(): array {
		$tokensDir = $this->repoRoot() . '/css/tokens';
		$files = glob($tokensDir . '/*.css');

		$this->assertIsArray(actual: $files, message: 'css/tokens/ must be readable.');
		$this->assertNotEmpty(actual: $files, message: 'css/tokens/ must contain at least one shipped set.');

		return $files;
	}//end shippedTokenCssFiles()

	/**
	 * Every shipped `css/tokens/*.css` file (excluding `dark/`) MUST contain
	 * exactly one `:root { }` block and no at-rule.
	 *
	 * @spec openspec/specs/app-token-set-selection/spec.md
	 *
	 * @return void
	 */
	public function testEveryShippedTokenCssFileIsFlatRootOnly(): void {
		foreach ($this->shippedTokenCssFiles() as $file) {
			$css = (string)file_get_contents($file);
			$basename = basename($file);

			// Strip comments so a commented-out at-rule/selector never trips
			// the guard (same approach as CustomTokenSetValidator::hasDisallowedSelector()).
			$stripped = (string)preg_replace('#/\*.*?\*/#s', '', $css);

			$this->assertSame(
				expected: 0,
				actual: preg_match('/@[a-z-]+/i', $stripped),
				message: $basename . ' MUST NOT contain an at-rule (@media, @supports, @import, @font-face, ...).'
			);

			$selectors = [];
			if (preg_match_all('/([^{}]+)\{/', $stripped, $matches) > 0) {
				foreach ($matches[1] as $selector) {
					$selector = trim($selector);
					if ($selector !== '') {
						$selectors[] = $selector;
					}
				}
			}

			$this->assertCount(
				expectedCount: 1,
				haystack: $selectors,
				message: $basename . ' MUST contain exactly one selector block (found: ' . implode(', ', $selectors) . ').'
			);
			$this->assertSame(
				expected: ':root',
				actual: strtolower($selectors[0]),
				message: $basename . ' MUST use ":root" as its only selector (found "' . $selectors[0] . '").'
			);
		}//end foreach
	}//end testEveryShippedTokenCssFileIsFlatRootOnly()

	/**
	 * The `dark/` subdirectory is a distinct, unrelated shape (a
	 * `@media (prefers-color-scheme: dark) { body:not(...) { ... } }` block)
	 * and is explicitly excluded from this contract — it MUST NOT be
	 * expected to satisfy the flat `:root`-only shape.
	 *
	 * @spec openspec/specs/app-token-set-selection/spec.md
	 *
	 * @return void
	 */
	public function testDarkVariantsAreExcludedFromTheContract(): void {
		$darkDir = $this->repoRoot() . '/css/tokens/dark';
		$files = glob($darkDir . '/*.css');

		$this->assertIsArray(actual: $files);
		$this->assertNotEmpty(actual: $files, message: 'css/tokens/dark/ must contain at least one generated variant to exercise the exclusion.');

		$atRuleFound = false;
		foreach ($files as $file) {
			if (str_contains((string)file_get_contents($file), '@media') === true) {
				$atRuleFound = true;
				break;
			}
		}

		$this->assertTrue(
			condition: $atRuleFound,
			message: 'At least one dark variant is expected to use @media — confirming the exclusion is meaningful, not vacuous.'
		);
	}//end testDarkVariantsAreExcludedFromTheContract()
	/**
	 * The design system maps the house style's family and weights to Nextcloud's
	 * variables, and leaves Nextcloud's body size alone (15px).
	 *
	 * @spec openspec/specs/token-editor-ui/spec.md#requirement-font-family-size-and-weight-come-from-the-house-style
	 *
	 * @return void
	 */
	public function testTheHouseStyleSetsFamilyAndWeightsButNotTheBodySize(): void {
		$css = (string)file_get_contents($this->repoRoot() . '/css/systems/nldesign/overrides.css');
		// Comments say what is left unmapped; only declarations count.
		$declarations = (string)preg_replace('#/\*.*?\*/#s', '', $css);

		$expected = [
			'--font-face' => '--nldesign-font-family',
			'--font-weight-default' => '--nldesign-font-weight-default',
			'--font-weight-element' => '--nldesign-font-weight-element',
			'--font-weight-heading' => '--nldesign-font-weight-heading',
		];
		foreach ($expected as $nextcloud => $houseStyle) {
			$this->assertMatchesRegularExpression(
				pattern: '/(^|[;{\s])' . preg_quote($nextcloud, '/') . '\s*:\s*var\(\s*' . preg_quote($houseStyle, '/') . '\s*[,)]/m',
				string: $declarations,
				message: $nextcloud . ' MUST take its value from ' . $houseStyle . '.'
			);
		}

		$this->assertDoesNotMatchRegularExpression(
			pattern: '/(^|[;{\s])--default-font-size\s*:/m',
			string: $declarations,
			message: '--default-font-size MUST stay unmapped so body text keeps Nextcloud\'s 15px.'
		);
	}//end testTheHouseStyleSetsFamilyAndWeightsButNotTheBodySize()
}//end class
