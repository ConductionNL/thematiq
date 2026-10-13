<?php

/**
 * Tests for FigmaTokenReader and FigmaTokenWriter.
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
 * @spec openspec/changes/figma-token-files/specs/figma-token-files/spec.md#requirement-a-file-from-figma-becomes-a-custom-token-set
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\DesignTokensWriter;
use OCA\Thematiq\Service\FigmaTokenReader;
use OCA\Thematiq\Service\FigmaTokenWriter;
use PHPUnit\Framework\TestCase;

/**
 * The rewrite of an upload, and the three download formats, on small documents.
 *
 * @spec openspec/changes/figma-token-files/specs/figma-token-files/spec.md#requirement-a-file-from-figma-becomes-a-custom-token-set
 */
final class FigmaTokenReaderTest extends TestCase {

	/**
	 * Rewrite a document.
	 *
	 * @param array<string, mixed> $document The upload.
	 *
	 * @return array{document: array<string, mixed>, skipped: array<int, array{path: string, reason: string}>}
	 */
	private function read(array $document): array {
		$skipped = [];
		$out = (new FigmaTokenReader())->normalise(content: (string)json_encode($document), skipped: $skipped);

		return ['document' => (array)json_decode($out, true), 'skipped' => $skipped];
	}//end read()

	/**
	 * Any other upload is handed on byte for byte.
	 *
	 * @return void
	 */
	public function testOtherContentIsNotTouched(): void {
		$skipped = [];
		$reader = new FigmaTokenReader();
		$css = ":root {\n\t--nldesign-color-primary: #123456;\n}\n";
		$dtcg = "{\n  \"color\": {\"primary\": {\"\$type\": \"color\", \"\$value\": \"#123456\"}}\n}";
		$own = (string)json_encode((new DesignTokensWriter())->write(declarations: ['--nldesign-color-primary' => '#123456'], setId: 'x', setName: 'X', appVersion: '1'));

		$this->assertSame($css, $reader->normalise(content: $css, skipped: $skipped));
		$this->assertSame($dtcg, $reader->normalise(content: $dtcg, skipped: $skipped));
		$this->assertSame($own, $reader->normalise(content: $own, skipped: $skipped), 'a download with its extensions needs no rewrite');
		$this->assertSame('{not json', $reader->normalise(content: '{not json', skipped: $skipped));
		$this->assertSame('', $reader->normalise(content: '', skipped: $skipped));
		$this->assertSame([], $skipped);
	}//end testOtherContentIsNotTouched()

	/**
	 * A path under `nldesign` is the CSS name: a colour keeps its token, any other value is the CSS value.
	 *
	 * @return void
	 */
	public function testThePathIsTheCssName(): void {
		$read = $this->read(
			document: [
				'nldesign' => [
					'color' => ['primary' => ['$type' => 'color', '$value' => '#123456']],
					'border' => [
						'radius' => ['$type' => 'dimension', '$value' => '6px'],
						'radius-large' => ['$type' => 'dimension', '$value' => '{nldesign.border.radius}'],
						'width' => ['$type' => 'dimension', '$value' => ['value' => 1, 'unit' => 'px']],
					],
					'line' => ['height' => ['$type' => 'number', '$value' => 1.5]],
				],
				'merk' => ['color' => ['blauw-500' => ['$type' => 'color', '$value' => '#0b5fff']]],
			]
		);

		$extension = $read['document']['$extensions']['nl.conduction.thematiq'];
		$this->assertSame('--nldesign-color-primary', $read['document']['nldesign']['color']['primary']['$extensions']['nl.conduction.thematiq']['cssVariable']);
		$this->assertSame('--merk-color-blauw-500', $read['document']['merk']['color']['blauw-500']['$extensions']['nl.conduction.thematiq']['cssVariable']);
		$this->assertSame('--nldesign-border-width', $read['document']['nldesign']['border']['width']['$extensions']['nl.conduction.thematiq']['cssVariable']);
		$this->assertSame(
			['--nldesign-border-radius' => '6px', '--nldesign-border-radius-large' => 'var(--nldesign-border-radius)', '--nldesign-line-height' => '1.5'],
			$extension['cssOnly']
		);
		$this->assertIsString($extension['setId']);
		$this->assertSame([], $read['skipped']);
	}//end testThePathIsTheCssName()

	/**
	 * A value that is not a CSS value is left out and named.
	 *
	 * @return void
	 */
	public function testAValueThatCouldLeaveItsDeclarationIsLeftOut(): void {
		$read = $this->read(
			document: [
				'nldesign' => [
					'color' => ['primary' => ['$type' => 'color', '$value' => '#123456']],
					'spacing' => [
						'escape' => ['$type' => 'other', '$value' => '0; } body { display: none'],
						'markup' => ['$type' => 'other', '$value' => '<script>'],
						'comment' => ['$type' => 'other', '$value' => '1px /* x'],
						'sum' => ['$type' => 'dimension', '$value' => '{nldesign.spacing.base} * 2'],
						'elsewhere' => ['$type' => 'dimension', '$value' => '{core.spacing.base}'],
						'switch' => ['$type' => 'boolean', '$value' => true],
						'empty' => ['$type' => 'other', '$value' => ' '],
						'Odd_Name' => ['$type' => 'other', '$value' => '1px'],
					],
				],
			]
		);

		$reasons = array_column($read['skipped'], 'reason', 'path');
		foreach (['escape', 'markup', 'comment', 'sum', 'elsewhere', 'switch', 'empty'] as $key) {
			$this->assertSame('value-not-a-css-value', ($reasons['nldesign.spacing.' . $key] ?? null), $key);
			$this->assertArrayNotHasKey($key, $read['document']['nldesign']['spacing']);
		}

		$this->assertSame([], $read['document']['$extensions']['nl.conduction.thematiq']['cssOnly']);
		$this->assertArrayHasKey('Odd_Name', $read['document']['nldesign']['spacing'], 'a path that is not a CSS name of ours is left to the mapper');
	}//end testAValueThatCouldLeaveItsDeclarationIsLeftOut()

	/**
	 * A reference cycle is found, whatever its length, and a chain that ends is not one.
	 *
	 * @return void
	 */
	public function testAReferenceCycleIsLeftOut(): void {
		$read = $this->read(
			document: [
				'nldesign' => [
					'color' => [
						'a' => ['$type' => 'color', '$value' => '{nldesign.color.b}'],
						'b' => ['$type' => 'color', '$value' => '{nldesign.color.c}'],
						'c' => ['$type' => 'color', '$value' => '{nldesign.color.a}'],
						'self' => ['$type' => 'color', '$value' => '{nldesign.color.self}'],
						'd' => ['$type' => 'color', '$value' => '{nldesign.color.e}'],
						'e' => ['$type' => 'color', '$value' => '#123456'],
					],
				],
			]
		);

		$this->assertSame(
			['nldesign.color.a', 'nldesign.color.b', 'nldesign.color.c', 'nldesign.color.self'],
			array_column($read['skipped'], 'path')
		);
		$this->assertSame(['d', 'e'], array_keys($read['document']['nldesign']['color']));
	}//end testAReferenceCycleIsLeftOut()

	/**
	 * A Tokens Studio file without a theme has all its sets merged, the later one winning.
	 *
	 * @return void
	 */
	public function testSetsAreMergedInTheirOrder(): void {
		$read = $this->read(
			document: [
				'tweede' => ['color' => ['primary' => ['value' => '#222222', 'type' => 'color', 'description' => 'Merk']]],
				'eerste' => ['color' => ['primary' => ['value' => '#111111', 'type' => 'color'], 'text' => ['value' => '#000000', 'type' => 'color']]],
				'derde' => ['font' => ['base' => ['value' => 'Fira Sans', 'type' => 'fontFamilies']]],
				'$metadata' => ['tokenSetOrder' => ['eerste', 'tweede', 'ontbreekt', 7]],
			]
		);

		$this->assertSame(['$value' => '#222222', '$type' => 'color', '$description' => 'Merk'], $read['document']['color']['primary']);
		$this->assertSame(['$value' => '#000000', '$type' => 'color'], $read['document']['color']['text']);
		$this->assertSame('fontFamily', $read['document']['font']['base']['$type']);
		$this->assertArrayNotHasKey('$metadata', $read['document']);
		$this->assertSame([], $read['skipped']);
	}//end testSetsAreMergedInTheirOrder()

	/**
	 * A colour that is not opaque is written as rgba(), and a name that is also a group has no place in a Tokens Studio file.
	 *
	 * @return void
	 */
	public function testTheWriterKeepsAlphaAndLeavesOutWhatHasNoPlace(): void {
		$declarations = [
			'--nldesign-color-overlay' => 'rgba(0, 0, 0, 0.5)',
			'--nldesign-color-primary' => '#123456',
			'--nldesign-color-primary-hover' => 'var(--nldesign-color-primary)',
			'--nldesign-shadow-small' => '0 0 2px #000000',
			'--nldesign-color' => '#ffffff',
			'--nldesign-animation-quick' => '200ms',
		];
		$document = (new DesignTokensWriter())->write(declarations: $declarations, setId: 'x', setName: 'X', appVersion: '1');
		$writer = new FigmaTokenWriter();

		$figma = (array)json_decode((string)json_encode($writer->write(document: $document, declarations: $declarations, format: 'figma', setId: 'x', setName: 'X')), true);
		$studio = (array)json_decode((string)json_encode($writer->write(document: $document, declarations: $declarations, format: 'tokens-studio', setId: 'x', setName: 'X')), true);

		$this->assertSame($document, $writer->write(document: $document, declarations: $declarations, format: 'dtcg', setId: 'x', setName: 'X'));
		$this->assertSame('rgba(0, 0, 0, 0.5)', $figma['nldesign']['color']['overlay']['$value']);
		$this->assertSame('#123456', $figma['nldesign']['color']['primary']['$value']);
		$this->assertSame('{nldesign.color.primary}', $figma['nldesign']['color']['primary-hover']['$value']);
		$this->assertSame('#ffffff', $figma['$extensions']['nl.conduction.thematiq']['cssOnly']['--nldesign-color']);

		$tokens = $studio['x']['nldesign'];
		$this->assertSame(['$value' => 'rgba(0, 0, 0, 0.5)', '$type' => 'color'], $tokens['color']['overlay']);
		$this->assertSame(['$value' => '{nldesign.color.primary}', '$type' => 'color'], $tokens['color']['primary-hover']);
		$this->assertSame(['$value' => '0 0 2px #000000', '$type' => 'other'], $tokens['shadow']['small']);
		$this->assertSame(['$value' => '200ms', '$type' => 'other'], $tokens['animation']['quick']);
		$this->assertArrayNotHasKey('$value', $tokens['color'], 'the name that is also a group is left out');
		$this->assertSame('x.tokens-studio.json', $writer->fileName(setId: 'x', format: 'tokens-studio'));
		$this->assertSame('x.tokens.json', $writer->fileName(setId: 'x', format: 'figma'));
	}//end testTheWriterKeepsAlphaAndLeavesOutWhatHasNoPlace()
}//end class
