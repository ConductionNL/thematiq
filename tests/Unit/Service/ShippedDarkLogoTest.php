<?php

/**
 * The dark logo a shipped token set declares must reach the page.
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
use OCA\Thematiq\Service\DarkPaletteService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Walks every shipped `theming.logo_dark` through the real data path:
 * token-sets.json, DarkPaletteService::generateForSet() and the committed
 * dark stylesheet. Until 29 Sep 2026 no shipped set declared one, so the
 * dark-logo code path existed and never ran.
 */
class ShippedDarkLogoTest extends TestCase {

	/**
	 * The dark background every generated dark variant paints behind the header.
	 */
	private const DARK_BACKGROUND = '#141414';

	/**
	 * The repo root.
	 *
	 * @return string The absolute path.
	 */
	private function root(): string {
		return dirname(__DIR__, 3);
	}//end root()

	/**
	 * Every shipped set that declares a dark logo, keyed by set id.
	 *
	 * @return array<string, string> Set id to the app-relative dark logo path.
	 */
	private function shippedDarkLogos(): array {
		$manifest = json_decode((string)file_get_contents($this->root() . '/token-sets.json'), true);
		$logos = [];
		foreach ($manifest as $entry) {
			if (isset($entry['theming']['logo_dark']) === true) {
				$logos[$entry['id']] = $entry['theming']['logo_dark'];
			}
		}

		return $logos;
	}//end shippedDarkLogos()

	/**
	 * The Epe set draws its letters in near black on a transparent
	 * background, so it must ship a dark logo.
	 */
	public function testEpeShipsADarkLogo(): void {
		$this->assertSame(expected: 'img/logos/epe-dark.svg', actual: ($this->shippedDarkLogos()['epe'] ?? null));
	}//end testEpeShipsADarkLogo()

	/**
	 * The shipped sets whose light logo draws dark ink on a transparent
	 * background, so the mark disappears on a dark header. Each ships a
	 * mechanically derived dark variant (dark ink recoloured to white).
	 *
	 * @return array<string, array{0: string}> Set id per case.
	 */
	public static function setsWithDarkInkLogos(): array {
		return [
			'nijmegen' => ['nijmegen'],
			'noordwijk' => ['noordwijk'],
			'provincie-zuid-holland' => ['provincie-zuid-holland'],
			'vng' => ['vng'],
			'xxllnc' => ['xxllnc'],
		];
	}//end setsWithDarkInkLogos()

	/**
	 * A set whose light logo is dark ink on transparent ships a dark logo
	 * named after its light logo.
	 *
	 * @param string $setId The token set id.
	 *
	 * @dataProvider setsWithDarkInkLogos
	 */
	public function testDarkInkLogoSetShipsADarkLogo(string $setId): void {
		$this->assertSame(expected: "img/logos/{$setId}-dark.svg", actual: ($this->shippedDarkLogos()[$setId] ?? null));
	}//end testDarkInkLogoSetShipsADarkLogo()

	/**
	 * Each declared dark logo exists under img/logos/ and reaches both the
	 * generator output and the committed dark stylesheet.
	 */
	public function testEveryShippedDarkLogoReachesTheDarkStylesheet(): void {
		$logos = $this->shippedDarkLogos();
		$this->assertNotEmpty(actual: $logos, message: 'At least one shipped token set must declare theming.logo_dark.');

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->root());
		$service = new DarkPaletteService(
			contrast: new ContrastService(),
			parser: new CssParserService(),
			appManager: $appManager,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);

		foreach ($logos as $setId => $path) {
			$this->assertStringStartsWith(prefix: 'img/logos/', string: $path, message: "$setId: logo_dark must live under img/logos/.");
			$this->assertStringNotContainsString(needle: '..', haystack: $path, message: "$setId: logo_dark must not traverse.");
			$this->assertFileExists(filename: $this->root() . '/' . $path, message: "$setId: logo_dark file is missing.");

			$expected = "--nldesign-logo-url: url('../../../" . $path . "')";
			$generated = $service->generateForSet(setId: $setId);
			$this->assertNotNull(actual: $generated, message: "$setId: the set must be eligible for a dark variant.");
			$this->assertStringContainsString(needle: $expected, haystack: $generated['css'], message: "$setId: the generator must emit the dark logo.");

			$committed = (string)file_get_contents($this->root() . '/css/tokens/dark/' . $setId . '.css');
			$this->assertStringContainsString(needle: $expected, haystack: $committed, message: "$setId: regenerate css/tokens/dark/$setId.css with --force.");
		}
	}//end testEveryShippedDarkLogoReachesTheDarkStylesheet()

	/**
	 * Every fill in a shipped dark logo reaches 3:1 against the dark
	 * background (WCAG 1.4.11 non-text contrast).
	 */
	public function testEveryShippedDarkLogoIsReadableOnDark(): void {
		$contrast = new ContrastService();
		$logos = $this->shippedDarkLogos();
		$this->assertNotEmpty(actual: $logos, message: 'At least one shipped token set must declare theming.logo_dark.');

		foreach ($logos as $setId => $path) {
			$svg = (string)file_get_contents($this->root() . '/' . $path);
			preg_match_all('/fill\s*[=:]\s*"?\s*(#[0-9a-fA-F]{3,8})/', $svg, $matches);
			$this->assertNotEmpty(actual: $matches[1], message: "$setId: the dark logo must declare its fill colours.");
			foreach (array_unique($matches[1]) as $fill) {
				$ratio = $contrast->measure(foreground: $fill, background: self::DARK_BACKGROUND);
				$this->assertNotNull(actual: $ratio);
				$this->assertGreaterThanOrEqual(expected: 3.0, actual: $ratio, message: "$setId: fill $fill is below 3:1 on " . self::DARK_BACKGROUND . '.');
			}
		}
	}//end testEveryShippedDarkLogoIsReadableOnDark()
}//end class
