<?php

/**
 * Unit tests for DtcgValueTyper.
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

use OCA\Thematiq\Service\DtcgValueTyper;
use PHPUnit\Framework\TestCase;

/**
 * Pins the DTCG token each CSS value becomes, or that it stays CSS only.
 */
class DtcgValueTyperTest extends TestCase {

	/**
	 * Each value under each name types as recorded here.
	 *
	 * @return void
	 */
	public function testEachShape(): void {
		$cases = [
			['--x', 'var( --nldesign-a )', ['$alias' => '--nldesign-a']],
			['--x', '12px', ['$type' => 'dimension', '$value' => ['value' => 12, 'unit' => 'px']]],
			['--x', '-0.5rem', ['$type' => 'dimension', '$value' => ['value' => -0.5, 'unit' => 'rem']]],
			['--x', '1.0rem', null],
			['--x', '200ms', ['$type' => 'duration', '$value' => ['value' => 200, 'unit' => 'ms']]],
			['--x', '.5s', null],
			['--x', '0.5s', ['$type' => 'duration', '$value' => ['value' => 0.5, 'unit' => 's']]],
			['--x', '3em', null],
			['--x', 'cubic-bezier(0.4, 0, 0.2, 1)', ['$type' => 'cubicBezier', '$value' => [0.4, 0, 0.2, 1]]],
			['--x', 'cubic-bezier( 0 , -2 , 1 , 3 )', ['$type' => 'cubicBezier', '$value' => [0, -2, 1, 3]]],
			['--x', 'cubic-bezier(1.2, 0, 0.2, 1)', null],
			['--x', 'cubic-bezier(0.4, 0, -0.1, 1)', null],
			['--x', 'cubic-bezier(0.40, 0, 0.2, 1)', null],
			['--font-weight-bold', '700', ['$type' => 'fontWeight', '$value' => 700]],
			['--font-weight-bold', '0', ['$type' => 'number', '$value' => 0]],
			['--font-weight-bold', '1001', ['$type' => 'number', '$value' => 1001]],
			['--line', '700', ['$type' => 'number', '$value' => 700]],
			['--font-family-base', 'Arial, sans-serif', ['$type' => 'fontFamily', '$value' => ['Arial', 'sans-serif']]],
			['--font-family-base', "'Fira Sans', Arial", ['$type' => 'fontFamily', '$value' => ['Fira Sans', 'Arial']]],
			['--font-family-base', 'Fira Sans, Arial', null],
			['--font-family-base', 'Arial,sans-serif', null],
			['--font-family-base', 'var(--a), Arial', null],
			['--font-weight-family', '400', ['$type' => 'fontWeight', '$value' => 400]],
			['--x', '1.5', ['$type' => 'number', '$value' => 1.5]],
			['--x', '1.50', null],
			['--x', 'auto', null],
			['--x', '#ff000080', ['$type' => 'color', '$value' => ['colorSpace' => 'srgb', 'components' => [1.0, 0.0, 0.0], 'hex' => '#ff0000', 'alpha' => 0.502]]],
			['--x', '#336699', ['$type' => 'color', '$value' => ['colorSpace' => 'srgb', 'components' => [0.2, 0.4, 0.6], 'hex' => '#336699']]],
			['--x', 'rgb(0 0 0 / 1)', ['$type' => 'color', '$value' => ['colorSpace' => 'srgb', 'components' => [0.0, 0.0, 0.0], 'hex' => '#000000']]],
		];
		$typer = new DtcgValueTyper();
		foreach ($cases as [$name, $value, $expected]) {
			$this->assertSame(expected: $expected, actual: $typer->typed(name: $name, value: $value), message: $name . ': ' . $value);
		}
	}//end testEachShape()
}//end class
