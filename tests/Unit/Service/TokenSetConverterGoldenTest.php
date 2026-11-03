<?php

/**
 * Golden test for TokenSetConverterService::convert().
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://github.com/ConductionNL/thematiq
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\DesignTokensWriter;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Converts every input shape (built theme CSS, DTCG, Style Dictionary, a `:root` set, thematiq's
 * own export, Figma and OpenProject files, a logo, nothing usable) under seeded combinations of
 * the optional arguments, and compares each whole result with the recording.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\TokenSetConverterService
 * @uses   \OCA\Thematiq\Service\ColorSpaceConverter
 * @uses   \OCA\Thematiq\Service\ColourContrastAdjuster
 * @uses   \OCA\Thematiq\Service\ColourLiteralParser
 * @uses   \OCA\Thematiq\Service\ContrastService
 * @uses   \OCA\Thematiq\Service\ConversionRun
 * @uses   \OCA\Thematiq\Service\ConverterColourRepair
 * @uses   \OCA\Thematiq\Service\ConverterComponentRepair
 * @uses   \OCA\Thematiq\Service\ConverterPairRepair
 * @uses   \OCA\Thematiq\Service\CssColorParser
 * @uses   \OCA\Thematiq\Service\CssParserService
 * @uses   \OCA\Thematiq\Service\CssDeclarationScanner
 * @uses   \OCA\Thematiq\Service\DesignTokenRef
 * @uses   \OCA\Thematiq\Service\DesignTokensAliasResolver
 * @uses   \OCA\Thematiq\Service\DesignTokensMapper
 * @uses   \OCA\Thematiq\Service\DesignTokensMapping
 * @uses   \OCA\Thematiq\Service\DesignTokensWriter
 * @uses   \OCA\Thematiq\Service\DtcgValueTyper
 * @uses   \OCA\Thematiq\Service\FigmaTokenReader
 * @uses   \OCA\Thematiq\Service\FileDarkValues
 * @uses   \OCA\Thematiq\Service\OpenProjectSetFile
 * @uses   \OCA\Thematiq\Service\ThematiqExportLayers
 * @uses   \OCA\Thematiq\Service\ToolTokenFileNormaliser
 * @uses   \OCA\Thematiq\Service\TokensStudioSets
 */
class TokenSetConverterGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/token-set-converter.golden.json';

	private const FILES = [
		'tests/Unit/fixtures/converter/oklch.input.css',
		'tests/Unit/fixtures/converter/raw-dump.input.css',
		'tests/Unit/fixtures/converter/style-dictionary.input.json',
		'tests/Unit/fixtures/converter/theme-css.input.css',
		'tests/Unit/fixtures/dtcg/03-object-color-and-dimension.tokens.json',
		'tests/Unit/fixtures/dtcg/04-composite-typography.tokens.json',
		'tests/Unit/fixtures/dtcg/09-deprecated.tokens.json',
		'tests/Unit/fixtures/dtcg/11-version-carrying.tokens.json',
		'tests/Unit/fixtures/dtcg/12-zero-yield.tokens.json',
		'tests/Unit/fixtures/dtcg/14-utrecht-real-excerpt.tokens.json',
		'tests/Unit/fixtures/dtcg/15-amsterdam-real-excerpt.tokens.json',
		'tests/Unit/fixtures/dtcg/colour-spaces.tokens.json',
		'tests/Unit/fixtures/multi-brand/three-themes.tokens.json',
		'tests/fixtures/figma/plugin/cunningham.tokens-studio.json',
		'tests/fixtures/figma/tokens-studio-dtcg.json',
		'tests/fixtures/figma/tokens-studio-legacy.json',
		'tests/fixtures/openproject/zuiddrecht.json',
	];

	private const EXPORTED = [
		'--nldesign-color-primary' => '#154273',
		'--nldesign-color-primary-text' => '#ffffff',
		'--nldesign-color-background' => 'rgb(250, 250, 250)',
		'--nldesign-font-family' => '"Fira Sans", Arial, sans-serif',
		'--nldesign-border-radius' => '4px',
		'--golden-color-blue-500' => '#0b71a1',
		'--nldesign-color-action' => 'var(--golden-color-blue-500)',
		'--thematiq-shell-menu' => '#003366',
		'--nldesign-header-background' => 'url("https://example.org/x.png")',
	];

	private const SOURCE_NAMES = [null, 'theme.css', "evil */ name\n.css", 'tokens-1.2.json'];

	/**
	 * Every recorded case converts to the recorded result.
	 *
	 * @return void
	 */
	public function testConvertMatchesTheGoldenRecording(): void {
		$actual = [];
		foreach ($this->inputs() as $name => $content) {
			for ($variant = 0; $variant < 3; $variant++) {
				$random = new Randomizer(new Mt19937(crc32($name) + $variant));
				$actual[$name . '#' . $variant] = $this->convertSeeded(content: $content, random: $random);
			}
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(array_keys($expected), array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame($json, $actual[$name], 'case ' . $name);
		}
	}//end testConvertMatchesTheGoldenRecording()

	/**
	 * One conversion with seeded optional arguments, as JSON; a refusal as its message and code.
	 *
	 * @param string $content The input.
	 * @param Randomizer $random The seeded source.
	 *
	 * @return string
	 */
	private function convertSeeded(string $content, Randomizer $random): string {
		$arguments = [
			'content' => $content,
			'slug' => ['golden', 'demo'][$random->getInt(0, 1)],
			'displayName' => ['Golden', 'Demo set'][$random->getInt(0, 1)],
			'sourceName' => self::SOURCE_NAMES[$random->getInt(0, 3)],
			'assetName' => [null, 'custom-golden'][$random->getInt(0, 1)],
			'referenceOnlyPaths' => [[], ['color.primary'], ['brand.color.blue']][$random->getInt(0, 2)],
			'repairContrast' => ($random->getInt(0, 1) === 1),
		];
		$recorded = $arguments;
		unset($recorded['content']);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$converter = new TokenSetConverterService(
			$appManager,
			new CssParserService(),
			new ContrastService(),
			new DesignTokensMapper(),
			$this->createMock(FontService::class),
			$this->createMock(LoggerInterface::class)
		);

		try {
			$result = $converter->convert(...$arguments);
		} catch (RuntimeException $exception) {
			$result = ['refused' => $exception->getMessage(), 'code' => $exception->getCode()];
		}

		if (isset($result['logoAsset']['contents']) === true) {
			$result['logoAsset']['contents'] = base64_encode($result['logoAsset']['contents']);
		}

		return (string)json_encode(['arguments' => $recorded, 'result' => $result], (JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
	}//end convertSeeded()

	/**
	 * The inputs, by name.
	 *
	 * @return array<string, string>
	 */
	private function inputs(): array {
		$root = \dirname(__DIR__, 3);
		$inputs = [];
		foreach (self::FILES as $file) {
			$inputs['file:' . $file] = (string)file_get_contents($root . '/' . $file);
		}

		$export = (new DesignTokensWriter())->write(declarations: self::EXPORTED, setId: 'golden', setName: 'Golden', appVersion: 'golden');
		$inputs['export'] = (string)json_encode($export);
		$inputs['export-without-shell-menu'] = (string)json_encode(
			(new DesignTokensWriter())->write(declarations: ['--nldesign-color-primary' => 'var(--x-blue)', '--x-blue' => '#0b71a1'], setId: 'g', setName: 'G', appVersion: 'golden')
		);
		$inputs['export-without-tokens'] = (string)json_encode((new DesignTokensWriter())->write(declarations: [], setId: 'e', setName: 'E', appVersion: 'golden'));

		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>';
		$inputs['root-set-with-logo'] = ":root {\n  --nldesign-color-primary: #154273;\n  --conduction-logo-header-background-image: url(\"data:image/svg+xml;base64,"
			. base64_encode($svg) . "\");\n  --nldesign-color-page-background: #fafafa;\n  --utrecht-button-primary-action-background-color: var(--nldesign-color-primary);\n}\n";
		$inputs['root-set-logo-after-none'] = ":root {\n  --nldesign-logo-url: url(none);\n  --conduction-logo-header-background-image: url(\" data:image/svg+xml;base64,"
			. base64_encode($svg) . " \");\n  --nldesign-color-primary: #154273;\n}\n";
		$inputs['root-set-logo-after-empty-url'] = ":root {\n  --nldesign-logo-url: url('');\n  --conduction-logo-navbar-background-image: url('data:image/svg+xml;base64,"
			. base64_encode($svg) . "');\n  --nldesign-color-primary: #154273;\n}\n";
		$inputs['var-chains'] = ":root {\n  --nldesign-color-primary: var(--golden-a, #154273);\n  --golden-a: var(--golden-b);\n  --golden-b: var(--golden-a);\n"
			. "  --nldesign-color-text: var(--golden-missing, var(--golden-also-missing, #222222));\n  --nldesign-color-error: var(--golden-missing, );\n"
			. "  --nldesign-color-success: var(--golden-ok);\n  --golden-ok: #00aa00;\n  --nldesign-color-warning: var(--golden-step-0);\n"
			. "  --golden-step-0: var(--golden-step-1);\n  --golden-step-1: var(--golden-step-2);\n  --golden-step-2: var(--golden-step-3);\n  --golden-step-3: var(--golden-step-4);\n  --golden-step-4: var(--golden-step-5);\n  --golden-step-5: var(--golden-step-6);\n  --golden-step-6: var(--golden-step-7);\n  --golden-step-7: var(--golden-step-8);\n  --golden-step-8: var(--golden-step-9);\n  --golden-step-9: var(--golden-step-10);\n  --golden-step-10: var(--golden-step-11);\n  --golden-step-11: var(--golden-step-12);\n  --golden-step-12: #cc8800;\n  --nldesign-color-info: var(--golden-step-6);\n}\n";
		$inputs['theme-bundled-font'] = ".demo-theme {\n  --demo-color-primary: #154273;\n  --utrecht-document-font-family: Marianne, sans-serif;\n}\n";
		$inputs['root-set-logo-on-disk'] = ":root {\n  --nldesign-color-primary: #0b71a1;\n  --nldesign-logo-url: url('../../img/logos/rijkshuisstijl.svg');\n}\n";
		$inputs['theme-block'] = ".demo-theme {\n  --demo-color-primary: hsl(210, 70%, 30%);\n  --demo-color-text: #ffffffcc;\n"
			. "  --utrecht-button-primary-action-background-color: var(--demo-color-primary);\n  --utrecht-document-font-family: 'Fira Sans', sans-serif;\n}\n";
		$inputs['style-dictionary-same-name-twice'] = (string)json_encode(
			[
				'color' => ['brand' => ['value' => ' #111111 '], 'text' => ['value' => '#ffffff']],
				'color-brand' => ['value' => '#222222'],
				'utrecht' => ['button' => ['primary-action' => ['background-color' => ['value' => '{color.brand}']]]],
			]
		);
		$inputs['nothing'] = 'no custom properties here';
		$inputs['empty-json'] = '{}';

		return $inputs;
	}//end inputs()
}//end class
