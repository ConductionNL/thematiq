<?php

/**
 * ColorSpaceConverter pins one value per DTCG colour space, each channel within 1 of the
 * reference in tests/Unit/fixtures/dtcg/colour-spaces.tokens.json.
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

use OCA\Thematiq\Service\ColorSpaceConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Colour space maths.
 *
 * @spec openspec/specs/custom-token-sets/spec.md#requirement-w3c-design-tokens-json-import
 */
final class ColorSpaceConverterTest extends TestCase {

	/**
	 * One case per colour space, from the fixture.
	 *
	 * @return array<string, array{0: string, 1: array<int, float>, 2: string}>
	 */
	public static function fixture(): array {
		$document = json_decode((string)file_get_contents(\dirname(__DIR__) . '/fixtures/dtcg/colour-spaces.tokens.json'), true);
		$cases    = [];
		foreach ($document['color'] as $space => $token) {
			if (is_array($token) === true && isset($token['$value']) === true) {
				$cases[$space] = [$token['$value']['colorSpace'], $token['$value']['components'], $token['$description']];
			}
		}

		return $cases;
	}//end fixture()

	/**
	 * Every colour space the module lists has a case.
	 *
	 * @return void
	 */
	public function testEverySpaceHasACase(): void {
		$this->assertSame(ColorSpaceConverter::SPACES, array_keys(self::fixture()));
	}//end testEverySpaceHasACase()

	/**
	 * Each conversion lands within 1 per channel of the reference.
	 *
	 * @param string            $space      The colour space.
	 * @param array<int, float> $components The components.
	 * @param string            $expected   The reference hex.
	 *
	 * @return void
	 */
	#[DataProvider('fixture')]
	public function testConvertsToTheReference(string $space, array $components, string $expected): void {
		$converter = new ColorSpaceConverter();
		$actual    = $converter->toHex(rgb: (array)$converter->toSrgb(space: $space, components: $components));

		foreach ([1, 3, 5] as $offset) {
			$this->assertEqualsWithDelta(hexdec(substr($expected, $offset, 2)), hexdec(substr($actual, $offset, 2)), 1, $space . ': ' . $actual . ' vs ' . $expected);
		}
	}//end testConvertsToTheReference()

	/**
	 * Display P3 pure red is outside sRGB and clips to #ff0000; an unknown space is null.
	 *
	 * @return void
	 */
	public function testGamutAndUnknownSpace(): void {
		$converter = new ColorSpaceConverter();
		$red       = (array)$converter->toSrgb(space: 'display-p3', components: [1, 0, 0]);

		$this->assertFalse($converter->isInGamut(rgb: $red));
		$this->assertSame('#ff0000', $converter->toHex(rgb: $red));
		$this->assertTrue($converter->isInGamut(rgb: (array)$converter->toSrgb(space: 'oklch', components: [0.5, 0.1, 250])));
		$this->assertNull($converter->toSrgb(space: 'cmyk', components: [0, 0, 0]));
		$this->assertSame('#15427380', $converter->toHex(rgb: [0.0824, 0.2588, 0.451], alpha: 0.5));
	}//end testGamutAndUnknownSpace()
}//end class
