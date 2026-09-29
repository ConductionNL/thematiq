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
		$this->assertSame('img/logos/epe-dark.svg', ($this->shippedDarkLogos()['epe'] ?? null));
	}//end testEpeShipsADarkLogo()

	/**
	 * Each declared dark logo exists under img/logos/ and reaches both the
	 * generator output and the committed dark stylesheet.
	 */
	public function testEveryShippedDarkLogoReachesTheDarkStylesheet(): void {
		$logos = $this->shippedDarkLogos();
		$this->assertNotEmpty($logos, 'At least one shipped token set must declare theming.logo_dark.');

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->root());
		$service = new DarkPaletteService(
			new ContrastService(),
			new CssParserService(),
			$appManager,
			$this->createMock(LoggerInterface::class)
		);

		foreach ($logos as $setId => $path) {
			$this->assertStringStartsWith('img/logos/', $path, "$setId: logo_dark must live under img/logos/.");
			$this->assertStringNotContainsString('..', $path, "$setId: logo_dark must not traverse.");
			$this->assertFileExists($this->root() . '/' . $path, "$setId: logo_dark file is missing.");

			$expected = "--nldesign-logo-url: url('../../../" . $path . "')";
			$generated = $service->generateForSet(setId: $setId);
			$this->assertNotNull($generated, "$setId: the set must be eligible for a dark variant.");
			$this->assertStringContainsString($expected, $generated['css'], "$setId: the generator must emit the dark logo.");

			$committed = (string)file_get_contents($this->root() . '/css/tokens/dark/' . $setId . '.css');
			$this->assertStringContainsString($expected, $committed, "$setId: regenerate css/tokens/dark/$setId.css with --force.");
		}
	}//end testEveryShippedDarkLogoReachesTheDarkStylesheet()

	/**
	 * Every fill in a shipped dark logo reaches 3:1 against the dark
	 * background (WCAG 1.4.11 non-text contrast).
	 */
	public function testEveryShippedDarkLogoIsReadableOnDark(): void {
		$contrast = new ContrastService();
		$logos = $this->shippedDarkLogos();
		$this->assertNotEmpty($logos, 'At least one shipped token set must declare theming.logo_dark.');

		foreach ($logos as $setId => $path) {
			$svg = (string)file_get_contents($this->root() . '/' . $path);
			preg_match_all('/fill\s*[=:]\s*"?\s*(#[0-9a-fA-F]{3,8})/', $svg, $matches);
			$this->assertNotEmpty($matches[1], "$setId: the dark logo must declare its fill colours.");
			foreach (array_unique($matches[1]) as $fill) {
				$ratio = $contrast->measure(foreground: $fill, background: self::DARK_BACKGROUND);
				$this->assertNotNull($ratio);
				$this->assertGreaterThanOrEqual(3.0, $ratio, "$setId: fill $fill is below 3:1 on " . self::DARK_BACKGROUND . '.');
			}
		}
	}//end testEveryShippedDarkLogoIsReadableOnDark()
}//end class
