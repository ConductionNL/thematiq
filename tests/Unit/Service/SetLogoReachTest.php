<?php

/**
 * A set or admin logo reaches the La Suite and Cunningham header.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/app-token-set-selection/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\LogoLayerService;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileLocator;
use OCP\IConfig;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Issue #968: on a La Suite or Cunningham set the header kept the
 * brand-coloured Nextcloud mark even when the set shipped a logo or the admin
 * uploaded one. LogoLayerService resolved `--nldesign-logo-url`, but the La
 * Suite element overrides paint `#header .logo` with
 * `background-image: none !important` and a mask of core's logo.svg.
 *
 * This takes the layer LogoLayerService really emits, reads the real bundle
 * stylesheet, and resolves each header-logo property against the layer's
 * variables, the way the cascade does.
 */
class SetLogoReachTest extends TestCase {

	/**
	 * The bundle stylesheet lasuite and cunningham share.
	 */
	private const BUNDLE = 'css/systems/lasuite/element-overrides.css';

	/**
	 * The layer for a set with its own logo, an uploaded logo, or neither.
	 *
	 * @param bool $shipped Whether the set ships `img/logos/<set>.svg`.
	 * @param bool $uploaded Whether the admin uploaded a logo.
	 *
	 * @return string The layer CSS.
	 */
	private function layerCss(bool $shipped, bool $uploaded): string {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => ($uploaded === true && $key === 'logoMime') ? 'image/png' : $default
		);
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('imagePath')->willReturn('/core/img/logo/logo.svg');
		$files = $this->createMock(RuntimeFileLocator::class);
		$files->method('exists')->willReturnCallback(static fn (string $name): bool => $shipped === true && $name === 'img/logos/cunningham.svg');
		$files->method('url')->willReturn('/apps/thematiq/img/logos/cunningham.svg');

		$layer = (new LogoLayerService($config, $urls, $this->createMock(LoggerInterface::class), $files))->layer(tokenSet: 'cunningham');
		$this->assertNotNull($layer);

		return $layer['css'];
	}//end layerCss()

	/**
	 * The custom properties a layer declares on :root.
	 *
	 * @param string $css The layer CSS.
	 *
	 * @return array<string, string> Name => value.
	 */
	private function rootVariables(string $css): array {
		$vars = [];
		if (preg_match('/:root\{([^}]*)\}/', $css, $match) === 1) {
			foreach (explode(';', $match[1]) as $declaration) {
				$parts = explode(':', $declaration, 2);
				if (count($parts) === 2 && str_starts_with(trim($parts[0]), '--') === true) {
					$vars[trim($parts[0])] = trim($parts[1]);
				}
			}
		}

		return $vars;
	}//end rootVariables()

	/**
	 * The declarations of the bundle's `#header .logo` rule, comments removed.
	 *
	 * @return array<string, string> Property => value, `!important` dropped.
	 */
	private function headerLogoRule(): array {
		$css = (string)preg_replace('#/\*.*?\*/#s', '', (string)file_get_contents(\dirname(__DIR__, 3) . '/' . self::BUNDLE));
		preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$selectors = array_map('trim', explode(',', trim($match[1])));
			if (in_array('#header .logo', $selectors, true) === false) {
				continue;
			}

			$declarations = [];
			foreach (explode(';', $match[2]) as $declaration) {
				$parts = explode(':', $declaration, 2);
				if (count($parts) === 2) {
					$declarations[strtolower(trim($parts[0]))] = trim((string)preg_replace('/\s*!important\s*$/', '', trim($parts[1])));
				}
			}

			return $declarations;
		}

		$this->fail(self::BUNDLE . ' has no #header .logo rule');
	}//end headerLogoRule()

	/**
	 * Resolve the `var()` references in a value against a variable map; an
	 * unknown variable takes its fallback, or stays as written without one.
	 *
	 * @param string $value The value.
	 * @param array<string, string> $vars The variables.
	 *
	 * @return string The resolved value, whitespace collapsed.
	 */
	private function resolve(string $value, array $vars): string {
		for ($i = 0; $i < 10; $i++) {
			if (preg_match('/var\(\s*--/', $value, $found, PREG_OFFSET_CAPTURE) !== 1) {
				break;
			}

			$start = $found[0][1];

			$depth = 0;
			$comma = null;
			$end = null;
			for ($k = $start + 4; $k < strlen($value); $k++) {
				if ($value[$k] === '(') {
					$depth++;
				} elseif ($value[$k] === ')') {
					if ($depth === 0) {
						$end = $k;
						break;
					}

					$depth--;
				} elseif ($value[$k] === ',' && $depth === 0 && $comma === null) {
					$comma = $k;
				}
			}

			if ($end === null) {
				break;
			}

			$name = trim(substr($value, $start + 4, ($comma ?? $end) - $start - 4));
			if (isset($vars[$name]) === true) {
				$replacement = $vars[$name];
			} elseif ($comma !== null) {
				$replacement = substr($value, $comma + 1, $end - $comma - 1);
			} else {
				// Not this test's variable (Nextcloud's own); leave it, marked.
				$replacement = '<' . $name . '>';
			}

			$value = substr($value, 0, $start) . $replacement . substr($value, $end + 1);
		}//end for

		return trim((string)preg_replace('/\s+/', ' ', $value));
	}//end resolve()

	/**
	 * A shipped set logo replaces the Nextcloud mark on the La Suite header.
	 */
	public function testAShippedSetLogoReachesTheHeader(): void {
		$vars = $this->rootVariables(css: $this->layerCss(shipped: true, uploaded: false));
		$rule = $this->headerLogoRule();

		$this->assertSame('url(/apps/thematiq/img/logos/cunningham.svg)', $this->resolve(value: ($rule['background-image'] ?? 'none'), vars: $vars));
		$this->assertSame('none', $this->resolve(value: ($rule['mask'] ?? ''), vars: $vars));
		$this->assertSame('none', $this->resolve(value: ($rule['-webkit-mask'] ?? ''), vars: $vars));
		$this->assertSame('transparent', $this->resolve(value: ($rule['background-color'] ?? ''), vars: $vars));
	}//end testAShippedSetLogoReachesTheHeader()

	/**
	 * An admin-uploaded logo replaces it too, through the theming app's variables.
	 */
	public function testAnUploadedLogoReachesTheHeader(): void {
		$vars = $this->rootVariables(css: $this->layerCss(shipped: false, uploaded: true));
		$rule = $this->headerLogoRule();

		// Nextcloud's own --image-logoheader / --image-logo, which this test
		// does not declare: what matters is that the image is the theming one.
		$this->assertStringStartsWith('<--image-logo', $this->resolve(value: ($rule['background-image'] ?? 'none'), vars: $vars));
		$this->assertSame('none', $this->resolve(value: ($rule['mask'] ?? ''), vars: $vars));
		$this->assertSame('transparent', $this->resolve(value: ($rule['background-color'] ?? ''), vars: $vars));
	}//end testAnUploadedLogoReachesTheHeader()

	/**
	 * Without any logo the header keeps the brand-coloured Nextcloud mark.
	 */
	public function testWithoutALogoTheBrandColouredMarkStays(): void {
		$vars = $this->rootVariables(css: $this->layerCss(shipped: false, uploaded: false));
		$rule = $this->headerLogoRule();

		$this->assertSame([], $vars, 'the no-logo layer declares no logo variables');
		$this->assertSame('none', $this->resolve(value: ($rule['background-image'] ?? ''), vars: $vars));
		$this->assertStringContainsString('core/img/logo/logo.svg', $this->resolve(value: ($rule['mask'] ?? ''), vars: $vars));
		$this->assertStringContainsString('brand-550', $this->resolve(value: ($rule['background-color'] ?? ''), vars: $vars));
	}//end testWithoutALogoTheBrandColouredMarkStays()

	/**
	 * The declarations of the no-logo layer's own `#nextcloud .logo` rule.
	 *
	 * @param string $css The layer CSS.
	 *
	 * @return array<string, string> Property => value, `!important` dropped.
	 */
	private function layerLogoRule(string $css): array {
		$this->assertSame(1, preg_match('/#nextcloud \.logo\{([^}]*)\}/', $css, $match), 'the no-logo layer paints #nextcloud .logo');
		$declarations = [];
		foreach (explode(';', $match[1]) as $declaration) {
			$parts = explode(':', $declaration, 2);
			if (count($parts) === 2) {
				$declarations[strtolower(trim($parts[0]))] = trim((string)preg_replace('/\s*!important\s*$/', '', trim($parts[1])));
			}
		}

		return $declarations;
	}//end layerLogoRule()

	/**
	 * Without a logo the mark keeps the La Suite brand fill, not the header text
	 * colour (thematiq#975).
	 *
	 * The no-logo layer paints `#nextcloud .logo` with !important, the same
	 * specificity as the bundle's `#header .logo` and later in the page, so its
	 * fill is the one that renders. It filled with `--nldesign-color-header-text`,
	 * near-black on La Suite. The custom properties the bundle rule declares on
	 * the element itself are what the layer's var() resolves against.
	 */
	public function testWithoutALogoTheLayerKeepsTheLaSuiteBrandFill(): void {
		$layer = $this->layerLogoRule(css: $this->layerCss(shipped: false, uploaded: false));
		$element = array_filter($this->headerLogoRule(), static fn (string $name): bool => str_starts_with($name, '--'), ARRAY_FILTER_USE_KEY);

		$fill = $this->resolve(value: ($layer['background-color'] ?? ''), vars: $element);
		$this->assertStringContainsString('brand-550', $fill, 'the no-logo mark on La Suite fills with ' . $fill);
	}//end testWithoutALogoTheLayerKeepsTheLaSuiteBrandFill()

	/**
	 * On the nldesign bundle, which declares no such property, the mark still
	 * takes the set's header text colour.
	 */
	public function testWithoutALogoNldesignKeepsTheHeaderTextFill(): void {
		$layer = $this->layerLogoRule(css: $this->layerCss(shipped: false, uploaded: false));

		$this->assertSame('#123456', $this->resolve(value: ($layer['background-color'] ?? ''), vars: ['--nldesign-color-header-text' => '#123456']));
	}//end testWithoutALogoNldesignKeepsTheHeaderTextFill()
}//end class
