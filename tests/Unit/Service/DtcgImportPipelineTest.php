<?php

/**
 * A DTCG import end to end: a colour moved into sRGB is reported adapted with its original,
 * a forbidden value in the thematiq extension still meets the validator, and a converted
 * colour reaches the contrast check and the dark palette as sRGB hex.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Tests
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/custom-token-sets/spec.md#requirement-w3c-design-tokens-json-import
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Converter, validator, contrast and dark palette over real services.
 *
 * @spec openspec/specs/custom-token-sets/spec.md#requirement-w3c-design-tokens-json-import
 */
final class DtcgImportPipelineTest extends TestCase {

	/**
	 * Convert a document.
	 *
	 * @param array<string, mixed> $document The DTCG document.
	 *
	 * @return array<string, mixed> The converter result.
	 */
	private function convert(array $document): array {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));

		return (new TokenSetConverterService($appManager, new CssParserService(), new ContrastService(), new DesignTokensMapper(), $this->createMock(FontService::class), $this->createMock(LoggerInterface::class)))
			->convert(content: (string)json_encode($document), slug: 'proef', displayName: 'Proef');
	}//end convert()

	/**
	 * Scenario: a display-p3 colour outside sRGB is clipped and the report says so, with the original.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md#requirement-w3c-design-tokens-json-import
	 */
	public function testAdaptedColourIsReported(): void {
		$result = $this->convert(['color' => ['primary' => ['$type' => 'color', '$value' => ['colorSpace' => 'display-p3', 'components' => [1, 0, 0]]]]]);
		$adapted = array_values(array_filter($result['report'], static fn (array $e): bool => $e['reason'] === 'out-of-gamut-clipped'));

		$this->assertCount(1, $adapted);
		$this->assertSame('adapted', $adapted[0]['action']);
		$this->assertSame('#ff0000', $adapted[0]['value']);
		$this->assertStringContainsString('display-p3', $adapted[0]['original']);
	}//end testAdaptedColourIsReported()

	/**
	 * A forbidden value carried in the extension's cssOnly map is still refused by the validator.
	 *
	 * @return void
	 */
	public function testExtensionWithForbiddenValueIsRefused(): void {
		$result = $this->convert(
			[
				'color' => ['primary' => ['$type' => 'color', '$value' => '#154273']],
				'$extensions' => ['nl.conduction.thematiq' => ['setId' => 'x', 'cssOnly' => ['--nldesign-header-background' => 'expression(alert(1))']]],
			]
		);
		$validator = new CustomTokenSetValidator();

		$this->assertNull($validator->validateDeclarations(declarations: (new CssParserService())->parseRootBlock(css: (string)$result['css']), slug: 'proef'));
		$this->assertSame(422, $validator->getLastError()['status'] ?? null);
	}//end testExtensionWithForbiddenValueIsRefused()

	/**
	 * Task 6.3: an oklch primary that fails 4.5:1 against its white text gets a contrast warning.
	 *
	 * @return void
	 */
	public function testConvertedColourIsContrastChecked(): void {
		$mapped = (new DesignTokensMapper())->map(
			document: ['color' => ['$type' => 'color', 'primary' => ['$value' => ['colorSpace' => 'oklch', 'components' => [0.85, 0.08, 100]]], 'primary-text' => ['$value' => '#ffffff']]]
		);
		$warnings = (new ContrastService())->check(declarations: $mapped['declarations']);

		$this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $mapped['declarations']['--nldesign-color-primary']);
		$this->assertNotSame([], $warnings, 'a light converted primary under white text must warn');
	}//end testConvertedColourIsContrastChecked()

	/**
	 * Task 6.4: a set imported from oklch gets a dark variant for that token.
	 *
	 * @return void
	 */
	public function testConvertedColourIsDerived(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$mapped = (new DesignTokensMapper())->map(document: ['color' => ['primary' => ['$type' => 'color', '$value' => ['colorSpace' => 'oklch', 'components' => [0.5, 0.1, 250]]]]]);
		$palette = new DarkPaletteService(new ContrastService(), new CssParserService(), $appManager, $this->createMock(LoggerInterface::class));

		$this->assertArrayHasKey('--nldesign-color-primary', $palette->deriveDarkDeclarations(lightDeclarations: $mapped['declarations']));
		// The control: stored as written, the palette could not read it and would skip it.
		$this->assertArrayNotHasKey('--nldesign-color-primary', $palette->deriveDarkDeclarations(lightDeclarations: ['--nldesign-color-primary' => 'oklch(0.5 0.1 250)']));
	}//end testConvertedColourIsDerived()
}//end class
