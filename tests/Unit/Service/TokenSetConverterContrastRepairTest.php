<?php

/**
 * The PHP converter normalises colours and repairs contrast exactly as the JavaScript one does.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * tests/Unit/fixtures/contrast-repair/expected.json holds what js/lib/tokenConverter.js emits
 * for three themes (checked by tests/vitest/tokenConverterContrastRepair.spec.js). The admin
 * upload converts through TokenSetConverterService, the nightly sync through the JavaScript
 * module, and both read one mapping table, so a theme must come out the same either way
 * (thematiq#993).
 *
 * `--nldesign-color-text` is left out of the fixture on purpose: the two runtimes already
 * pick a different neutral-ramp step for it (and for background-dark) on development, before
 * any repair runs. That divergence predates this change and is reported, not fixed, here.
 *
 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ColourLiteralParser;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\ConverterColourRepair;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Parity of colour normalisation and contrast repair across the two converter runtimes.
 */
class TokenSetConverterContrastRepairTest extends TestCase {

	/**
	 * The fixture cases.
	 *
	 * @return array<string, array{0: string}> Case name per case.
	 */
	public static function caseProvider(): array {
		$cases = [];
		foreach (array_keys(self::fixture()) as $name) {
			$cases[$name] = [$name];
		}

		return $cases;
	}//end caseProvider()

	/**
	 * The decoded fixture.
	 *
	 * @return array<string, array{input: string, expected: array<string, string>, primary_color: string}>
	 */
	private static function fixture(): array {
		$path = dirname(__DIR__) . '/fixtures/contrast-repair/expected.json';

		return json_decode((string)file_get_contents($path), true);
	}//end fixture()

	/**
	 * Build the converter against the app's own mapping table.
	 */
	private function converter(): TokenSetConverterService {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));

		return new TokenSetConverterService(
			$appManager,
			new CssParserService(),
			new ContrastService(),
			new DesignTokensMapper(),
			$this->createMock(FontService::class),
			$this->createMock(LoggerInterface::class)
		);
	}//end converter()

	/**
	 * Every token the JavaScript converter emits for the case, PHP emits identically.
	 *
	 * @dataProvider caseProvider
	 *
	 * @param string $name The case.
	 */
	public function testMatchesTheJavaScriptConverter(string $name): void {
		$case = self::fixture()[$name];
		$result = $this->converter()->convert(content: $case['input'], slug: 'demo', displayName: 'Demo', repairContrast: true);

		$css = (string)preg_replace('#/\*.*?\*/#s', '', (string)$result['css']);
		preg_match_all('/(--[\w-]+)\s*:\s*([^;]+);/', $css, $matches, PREG_SET_ORDER);
		$declared = [];
		foreach ($matches as $match) {
			$declared[$match[1]] = trim($match[2]);
		}

		foreach ($case['expected'] as $token => $value) {
			$this->assertSame($value, ($declared[$token] ?? null), $name . ' ' . $token);
		}

		$this->assertSame($case['primary_color'], $result['manifestEntry']['theming']['primary_color'] ?? null);
	}//end testMatchesTheJavaScriptConverter()

	/**
	 * An admin's upload keeps its own colours: the repair only runs when the caller asks.
	 */
	public function testAnUploadKeepsItsColours(): void {
		$result = $this->converter()->convert(content: ".demo-theme {\n\t--utrecht-button-primary-action-background-color: #f0b800;\n}\n", slug: 'demo', displayName: 'Demo');

		$this->assertStringContainsString('--nldesign-color-primary: #f0b800;', (string)$result['css']);
	}//end testAnUploadKeepsItsColours()

	/**
	 * A colour that already passes is never touched, and the report names what was repaired.
	 */
	public function testRepairsOnlyWhatFails(): void {
		$repair = new ConverterColourRepair(contrast: new ContrastService());
		$table = json_decode((string)file_get_contents(\dirname(__DIR__, 3) . '/scripts/mapping/nlds-to-nextcloud.json'), true);
		$manifest = ['theming.primary_color' => '#f0b800'];
		$report = [];

		$semantic = $repair->repairContrast(
			semantic: ['--nldesign-color-primary' => '#f0b800', '--nldesign-color-text' => '#333333'],
			table: $table,
			manifest: $manifest,
			report: $report
		);

		$this->assertSame('#333333', $semantic['--nldesign-color-text']);
		$this->assertNotSame('#f0b800', $semantic['--nldesign-color-primary']);
		$this->assertSame($semantic['--nldesign-color-primary'], $manifest['theming.primary_color']);
		$this->assertSame(['--nldesign-color-primary'], array_column($report, 'target'));
	}//end testRepairsOnlyWhatFails()

	/**
	 * hsl(), hsla() and an 8-digit hex are written the way every reader takes them.
	 */
	public function testNormalisesColourLiterals(): void {
		$repair = new ColourLiteralParser();
		$this->assertSame('#002e52', $repair->normalise(value: 'hsl(206 100% 16%)'));
		$this->assertSame('#1d1d1b', $repair->normalise(value: 'hsl(60, 4%, 11%)'));
		$this->assertSame('rgba(0, 0, 0, 0.5)', $repair->normalise(value: 'hsla(0deg 0% 0% / 50%)'));
		$this->assertSame('rgba(1, 44, 157, 0.6)', $repair->normalise(value: '#012c9d99'));
		$this->assertNull($repair->normalise(value: 'inset 0 -4px hsl(0 0% 0%)'));
		$this->assertSame('#ffffff', $repair->normalise(value: 'white'));
		$this->assertNull($repair->normalise(value: 'transparent'));
	}//end testNormalisesColourLiterals()
}//end class
