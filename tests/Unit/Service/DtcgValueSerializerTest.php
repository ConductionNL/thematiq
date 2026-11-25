<?php

/**
 * Tests for DtcgValueSerializer.
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
 * @spec openspec/specs/custom-token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ColorSpaceConverter;
use OCA\Thematiq\Service\DtcgColourSerializer;
use OCA\Thematiq\Service\DtcgValueSerializer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every DTCG value type to its CSS text, and every shape it refuses.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */
final class DtcgValueSerializerTest extends TestCase {

	/**
	 * The serializer over the real colour serializer.
	 *
	 * @return DtcgValueSerializer
	 */
	private function serializer(): DtcgValueSerializer {
		return new DtcgValueSerializer(colours: new DtcgColourSerializer(colorSpaces: new ColorSpaceConverter()));
	}//end serializer()

	/**
	 * Values that serialize, with their CSS text.
	 *
	 * @return array<string, array{string, mixed, string}>
	 */
	public static function servedProvider(): array {
		return [
			'dimension string' => ['dimension', ' 4px ', '4px'],
			'dimension object' => ['dimension', ['value' => 1.5, 'unit' => 'rem'], '1.5rem'],
			'duration object' => ['duration', ['value' => 200, 'unit' => 'ms'], '200ms'],
			'cubicBezier string' => ['cubicBezier', ' ease-in ', 'ease-in'],
			'cubicBezier array' => ['cubicBezier', [0.4, 0, '0.2', 1], 'cubic-bezier(0.4, 0, 0.2, 1)'],
			'fontFamily string' => ['fontFamily', ' Fira Sans ', 'Fira Sans'],
			'fontFamily stack' => ['fontFamily', ['Fira Sans', ' ', 7, 'Arial', 'sans-serif'], "'Fira Sans', Arial, sans-serif"],
			'fontWeight integer' => ['fontWeight', 600, '600'],
			'fontWeight float' => ['fontWeight', 450.7, '450'],
			'fontWeight numeric string' => ['fontWeight', ' 700 ', '700'],
			'fontWeight keyword' => ['fontWeight', 'Semi-Bold', '600'],
			'fontWeight extra-black' => ['fontWeight', 'extra-black', '950'],
			'number passthrough' => ['number', 1.25, '1.25'],
			'shadow string passthrough' => ['shadow', '0 1px 2px #000', '0 1px 2px #000'],
			'boolean passthrough' => ['other', true, '1'],
		];
	}//end servedProvider()

	/**
	 * A supported value serializes to its CSS text.
	 *
	 * @param string $type The DTCG type.
	 * @param mixed $value The value.
	 * @param string $expected The CSS text.
	 *
	 * @return void
	 */
	#[DataProvider('servedProvider')]
	public function testServesTheCssText(string $type, mixed $value, string $expected): void {
		$this->assertSame(expected: ['ok' => true, 'value' => $expected], actual: $this->serializer()->serialize(type: $type, value: $value));
	}//end testServesTheCssText()

	/**
	 * Shapes each type refuses.
	 *
	 * @return array<string, array{string, mixed}>
	 */
	public static function refusedProvider(): array {
		return [
			'dimension number' => ['dimension', 4],
			'dimension without unit' => ['dimension', ['value' => 4]],
			'dimension with a numeric unit' => ['dimension', ['value' => 4, 'unit' => 1]],
			'dimension with an array value' => ['dimension', ['value' => [4], 'unit' => 'px']],
			'cubicBezier of three' => ['cubicBezier', [0, 0, 1]],
			'cubicBezier with text' => ['cubicBezier', [0, 'a', 1, 1]],
			'cubicBezier number' => ['cubicBezier', 1],
			'fontFamily of no names' => ['fontFamily', [' ', 3]],
			'fontFamily number' => ['fontFamily', 12],
			'fontWeight unknown keyword' => ['fontWeight', 'chunky'],
			'fontWeight array' => ['fontWeight', [400]],
			'passthrough array' => ['shadow', ['x' => 1]],
			'passthrough null' => ['number', null],
		];
	}//end refusedProvider()

	/**
	 * An unsupported shape is refused with its reason.
	 *
	 * @param string $type The DTCG type.
	 * @param mixed $value The value.
	 *
	 * @return void
	 */
	#[DataProvider('refusedProvider')]
	public function testRefusesAnUnsupportedShape(string $type, mixed $value): void {
		$this->assertSame(
			expected: ['ok' => false, 'reason' => 'unsupported-value-shape'],
			actual: $this->serializer()->serialize(type: $type, value: $value)
		);
	}//end testRefusesAnUnsupportedShape()

	/**
	 * A colour goes through the colour serializer.
	 *
	 * @return void
	 */
	public function testAColourGoesThroughTheColourSerializer(): void {
		$colours = new DtcgColourSerializer(colorSpaces: new ColorSpaceConverter());
		$serializer = new DtcgValueSerializer(colours: $colours);

		$this->assertSame(
			expected: $colours->serializeColor(value: '#00689e'),
			actual: $serializer->serialize(type: 'color', value: '#00689e')
		);
		$this->assertTrue(condition: $serializer->serialize(type: 'color', value: '#00689e')['ok']);
	}//end testAColourGoesThroughTheColourSerializer()
}//end class
