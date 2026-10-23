<?php

/**
 * The theme editor never writes page text under 4.5:1.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/token-editor-ui/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\OverridesCssBuilder;
use OCA\Thematiq\Service\TokenValueValidator;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Measured on dossiq (22 Oct): the editor's muted text #75758a sat on white at
 * 4.49:1, under WCAG 1.4.3's 4.5:1, on every caption and hint that reads
 * --color-text-maxcontrast. The file the editor writes steps a page text
 * colour darker (or lighter, on a dark page) until it reaches 4.5:1.
 */
class OverridesCssBuilderReadableTextTest extends TestCase {

	private OverridesCssBuilder $builder;
	private ContrastService $contrast;

	protected function setUp(): void {
		parent::setUp();
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn(sys_get_temp_dir());
		$this->contrast = new ContrastService();
		$darkPalette = new DarkPaletteService($this->contrast, new CssParserService(), $appManager, $this->createMock(LoggerInterface::class));
		$this->builder = new OverridesCssBuilder(darkPalette: $darkPalette, values: new TokenValueValidator());
	}//end setUp()

	/**
	 * The `:root` value of a token in the built file.
	 *
	 * @param string $css The file content.
	 * @param string $token The token.
	 *
	 * @return string|null The value without `!important`.
	 */
	private function rootValue(string $css, string $token): ?string {
		$root = substr($css, strpos($css, ':root {'));
		$root = substr($root, 0, strpos($root, '}'));
		if (preg_match('/' . preg_quote($token, '/') . '\s*:\s*([^;!]+)/', $root, $match) !== 1) {
			return null;
		}

		return trim($match[1]);
	}//end rootValue()

	public function testMutedTextUnderThresholdIsSteppedToFourPointFive(): void {
		$css = $this->builder->build(tokens: ['--color-text-maxcontrast' => '#75758a']);
		$value = $this->rootValue($css, '--color-text-maxcontrast');

		$this->assertNotNull($value);
		$this->assertNotSame('#75758a', strtolower($value));
		$this->assertGreaterThanOrEqual(4.5, $this->contrast->measure(foreground: $value, background: '#ffffff'));
	}//end testMutedTextUnderThresholdIsSteppedToFourPointFive()

	public function testReadableMutedTextIsLeftAlone(): void {
		$css = $this->builder->build(tokens: ['--color-text-maxcontrast' => '#5E6168']);

		$this->assertSame('#5E6168', $this->rootValue($css, '--color-text-maxcontrast'));
	}//end testReadableMutedTextIsLeftAlone()

	public function testMeasuresAgainstTheEditorsOwnBackground(): void {
		$css = $this->builder->build(tokens: ['--color-main-background' => '#f0f0f3', '--color-text-lighter' => '#75758a']);
		$value = $this->rootValue($css, '--color-text-lighter');

		$this->assertGreaterThanOrEqual(4.5, $this->contrast->measure(foreground: $value, background: '#f0f0f3'));
		// A settable variable is written as its --nldesign-nc-* token.
		$this->assertSame('#f0f0f3', $this->rootValue($css, '--nldesign-nc-color-main-background'));
	}//end testMeasuresAgainstTheEditorsOwnBackground()

	public function testAddsNoBackgroundOfItsOwn(): void {
		$css = $this->builder->build(tokens: ['--color-text-maxcontrast' => '#75758a']);

		$this->assertNull($this->rootValue($css, '--nldesign-nc-color-main-background'));
	}//end testAddsNoBackgroundOfItsOwn()
}//end class
