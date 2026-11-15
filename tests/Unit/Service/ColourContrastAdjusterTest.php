<?php

/**
 * Unit tests for ColourContrastAdjuster.
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

use OCA\Thematiq\Service\ColourContrastAdjuster;
use OCA\Thematiq\Service\ColourLiteralParser;
use OCA\Thematiq\Service\ContrastService;
use PHPUnit\Framework\TestCase;

/**
 * Golden of the repaired colour for a grid of foregrounds, backgrounds and thresholds.
 */
class ColourContrastAdjusterTest extends TestCase {

	/**
	 * Every repair, recorded on the code before the split as a hash.
	 *
	 * @return void
	 */
	public function testTheRepairGolden(): void {
		$adjuster = new ColourContrastAdjuster(contrast: new ContrastService(), parser: new ColourLiteralParser());
		$colours = [[0, 0, 0], [255, 255, 255], [21, 66, 115], [225, 112, 0], [128, 128, 128], [200, 30, 40], [250, 250, 200], [10, 200, 120]];
		$out = [];
		foreach ($colours as $foreground) {
			foreach ($colours as $background) {
				foreach ([1.0, 3.0, 4.5, 7.0, 21.0, 22.0] as $min) {
					$out[] = $adjuster->repairLightness(foreground: $foreground, background: $background, min: $min);
				}
			}
		}

		$this->assertSame(expected: '165c3e9d7f72aded', actual: substr(hash('sha256', (string)json_encode($out)), 0, 16));
	}//end testTheRepairGolden()
}//end class
