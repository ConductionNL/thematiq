<?php

/**
 * The app brand logo reaches the header on every design system.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/per-app-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\AppBrandLogoStore;
use OCA\Thematiq\Service\ImageSniffer;
use OCP\Files\IAppData;
use PHPUnit\Framework\TestCase;

/**
 * Issue #942: with an app brand on a La Suite or Cunningham set the header
 * kept the Nextcloud logo. logoCss() only set `--nldesign-logo-url`, which
 * only the nldesign bundle reads; the La Suite element overrides (shared by
 * both systems) paint the header logo as a mask of Nextcloud's logo with
 * `background-image: none !important`, and Nextcloud's own header rule reads
 * `--image-logoheader`. This reads the real bundle files and checks that the
 * brand layer outranks every header-logo rule in them and undoes what each sets.
 */
class AppBrandLogoReachTest extends TestCase {

	/**
	 * The bundle stylesheets that paint the header logo, per design system.
	 *
	 * @var array<string, string>
	 */
	private const BUNDLES = [
		'lasuite and cunningham' => 'css/systems/lasuite/element-overrides.css',
		'nldesign' => 'css/systems/nldesign/theme.css',
	];

	/**
	 * The header-logo properties a bundle may set that hide or tint a brand image.
	 *
	 * @var array<int, string>
	 */
	private const PROPERTIES = ['background-image', 'background-color', 'mask', '-webkit-mask', 'mask-image', '-webkit-mask-image', 'filter'];

	/**
	 * The brand layer for a large and a small logo.
	 *
	 * @return string The CSS.
	 */
	private function layerCss(): string {
		$store = new AppBrandLogoStore($this->createMock(IAppData::class), new ImageSniffer());

		return $store->logoCss(large: '/brand/collectives/large', small: '/brand/collectives/small');
	}//end layerCss()

	/**
	 * Every innermost rule of a stylesheet, comments removed.
	 *
	 * @param string $css The stylesheet.
	 *
	 * @return array<int, array{selectors: array<int, string>, declarations: array<string, string>}> The rules.
	 */
	private function rules(string $css): array {
		$css = (string)preg_replace('#/\*.*?\*/#s', '', $css);
		preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);
		$rules = [];
		foreach ($matches as $match) {
			$declarations = [];
			foreach (explode(';', $match[2]) as $declaration) {
				$parts = explode(':', $declaration, 2);
				if (count($parts) === 2) {
					$declarations[strtolower(trim($parts[0]))] = trim($parts[1]);
				}
			}

			$rules[] = [
				'selectors' => array_map('trim', explode(',', trim($match[1]))),
				'declarations' => $declarations,
			];
		}

		return $rules;
	}//end rules()

	/**
	 * Specificity of a simple selector as one comparable number (ids, classes, elements).
	 *
	 * @param string $selector The selector.
	 *
	 * @return int The specificity.
	 */
	private function specificity(string $selector): int {
		$ids = preg_match_all('/#[A-Za-z0-9_-]+/', $selector);
		$classes = preg_match_all('/\.[A-Za-z0-9_-]+|\[[^\]]*\]|(?<!:):(?!:)[A-Za-z-]+/', $selector);
		$elements = preg_match_all('/(?:^|[\s>+~])[a-z][a-z0-9]*/i', $selector);

		return ($ids * 10000) + ($classes * 100) + $elements;
	}//end specificity()

	/**
	 * The brand rule for the header logo, with its specificity.
	 *
	 * @return array{specificity: int, declarations: array<string, string>} The rule.
	 */
	private function brandRule(): array {
		foreach ($this->rules(css: $this->layerCss()) as $rule) {
			foreach ($rule['selectors'] as $selector) {
				if (str_contains($selector, '.logo') === true) {
					return ['specificity' => $this->specificity(selector: $selector), 'declarations' => $rule['declarations']];
				}
			}
		}

		$this->fail('the brand layer has no rule for the header logo');
	}//end brandRule()

	/**
	 * The brand rule outranks each bundle's header-logo rule and undoes each property it sets.
	 */
	public function testTheBrandLogoOutranksEveryBundleHeaderLogoRule(): void {
		$brand = $this->brandRule();
		$this->assertSame('var(--nldesign-logo-url) !important', $brand['declarations']['background-image'] ?? null);

		$checked = 0;
		foreach (self::BUNDLES as $system => $file) {
			$css = (string)file_get_contents(\dirname(__DIR__, 3) . '/' . $file);
			foreach ($this->rules(css: $css) as $rule) {
				$headerLogo = array_filter(
					$rule['selectors'],
					fn (string $selector): bool => preg_match('/#(header|nextcloud)\b.*\.logo(-icon)?\b/', $selector) === 1
				);
				$set = array_intersect(array_keys($rule['declarations']), self::PROPERTIES);
				if ($headerLogo === [] || $set === []) {
					continue;
				}

				foreach ($headerLogo as $selector) {
					$checked++;
					$this->assertGreaterThan($this->specificity(selector: $selector), $brand['specificity'], $system . ': ' . $selector . ' outranks the brand logo');
				}

				foreach ($set as $property) {
					$this->assertArrayHasKey($property, $brand['declarations'], $system . ' sets ' . $property . ' on the header logo and the brand layer leaves it');
				}
			}
		}

		$this->assertGreaterThan(0, $checked, 'no header-logo rule found in the bundles; the test would pass on nothing');
	}//end testTheBrandLogoOutranksEveryBundleHeaderLogoRule()

	/**
	 * The small logo still replaces the large one below the narrow-header breakpoint,
	 * through the variable the brand rule reads.
	 */
	public function testTheSmallLogoStillSwapsThroughTheVariable(): void {
		$css = $this->layerCss();

		$this->assertStringContainsString(':root{--nldesign-logo-url:url(/brand/collectives/large)', $css);
		$this->assertStringContainsString('@media (max-width:1024px){:root{--nldesign-logo-url:url(/brand/collectives/small)}}', $css);
	}//end testTheSmallLogoStillSwapsThroughTheVariable()
}//end class
