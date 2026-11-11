<?php

/**
 * Unit tests for how CssColorParser scales a component.
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
 * @spec openspec/specs/token-set-dtcg-export/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\CssColorParser;
use PHPUnit\Framework\TestCase;

/**
 * Pins the scale of each component: a percentage by its space and position, an rgb() channel from 0..255.
 */
class CssColorParserScaleTest extends TestCase {

	/**
	 * Each literal reads as these components and alpha.
	 *
	 * @return void
	 */
	public function testComponentsAreScaledByTheirSlot(): void {
		$cases = [
			'rgb(255 51 0)' => ['srgb', [1.0, 0.2, 0.0], null],
			'rgb(100% 50% 0%)' => ['srgb', [1.0, 0.5, 0.0], null],
			'color(srgb 255 0.5 50%)' => ['srgb', [255.0, 0.5, 0.5], null],
			'oklch(50% 50% 50%)' => ['oklch', [0.5, 0.2, 0.5], null],
			'oklab(100% 100% -100%)' => ['oklab', [1.0, 0.4, -0.4], null],
			'lab(50% 20% 40% / 25%)' => ['lab', [50.0, 25.0, 50.0], 0.25],
			'lch(10% 10% 120deg)' => ['lch', [10.0, 15.0, 120.0], null],
			'hwb(90 10% 20%)' => ['hwb', [90.0, 10.0, 20.0], null],
			'color(display-p3 50% 1 none)' => ['display-p3', [0.5, 1.0, 0.0], null],
			'color(srgb-linear 1e1 .5 -2)' => ['srgb-linear', [10.0, 0.5, -2.0], null],
		];
		$parser = new CssColorParser();
		foreach ($cases as $value => [$space, $components, $alpha]) {
			$this->assertSame(
				expected: ['space' => $space, 'components' => $components, 'alpha' => $alpha],
				actual: $parser->parse(value: $value),
				message: $value
			);
		}
	}//end testComponentsAreScaledByTheirSlot()

	/**
	 * A word that is not a number makes the colour unreadable.
	 *
	 * @return void
	 */
	public function testANonNumberIsNoColour(): void {
		$parser = new CssColorParser();
		$this->assertNull($parser->parse(value: 'rgb(1 abc 3)'));
		$this->assertNull($parser->parse(value: 'oklch(0.5 0.1 0.6turn)'));
	}//end testANonNumberIsNoColour()
}//end class
