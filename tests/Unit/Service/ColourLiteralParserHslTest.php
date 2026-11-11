<?php

/**
 * Unit tests for ColourLiteralParser's HSL conversions.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/token-sync-workflow/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ColourLiteralParser;
use PHPUnit\Framework\TestCase;

/**
 * Pins hslToRgb() and rgbToHsl() to the exact floats they give.
 */
class ColourLiteralParserHslTest extends TestCase {

	/**
	 * Every sector, wrapped and negative hues, and the round trip of a grid of colours.
	 *
	 * @return void
	 */
	public function testTheConversionsExactly(): void {
		$parser = new ColourLiteralParser();
		$out = [];
		foreach ([0.0, 30.0, 90.0, 150.0, 210.0, 270.0, 330.0, 360.0, 725.5, -45.0, 59.999] as $hue) {
			foreach ([[1.0, 0.5], [0.25, 0.8], [0.0, 0.3], [0.6, 1.0]] as [$saturation, $lightness]) {
				$out[] = $parser->hslToRgb(hue: $hue, saturation: $saturation, lightness: $lightness);
			}
		}

		foreach ([0, 17, 128, 255] as $red) {
			foreach ([0, 64, 200, 255] as $green) {
				foreach ([0, 99, 255] as $blue) {
					$out[] = $parser->rgbToHsl(rgb: [$red, $green, $blue]);
				}
			}
		}

		// Recorded on the code before the split: a hash of the exact floats, so a changed last digit fails.
		$this->assertSame(expected: 'f583b616', actual: substr(hash('sha256', serialize($out)), 0, 8), message: json_encode(array_slice($out, 0, 3)));
	}//end testTheConversionsExactly()
}//end class
