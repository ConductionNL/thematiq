<?php

/**
 * Seeded golden test for ColourLiteralParser and CssColorParser.
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
 * @spec openspec/specs/token-set-converter/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ColourLiteralParser;
use OCA\Thematiq\Service\CssColorParser;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Parses seeded colour literals with both parsers: hex in every length, named colours, and
 * rgb(), hsl(), oklch(), oklab(), lab(), lch(), hwb() and color() with comma and space syntax,
 * slash and fourth-argument alpha, percentages, units, `none`, signs, exponents and garbage
 * arguments. Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\ColourLiteralParser
 * @covers \OCA\Thematiq\Service\CssColorParser
 * @uses   \OCA\Thematiq\Service\ColorSpaceConverter
 */
class ColourParsersGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/colour-parsers.golden.json';

	private const FIXED = [
		'#fff', '#FFFF', '#154273', '#15427380', '#12345', 'red', 'Transparent', ' NAVY ', 'rebeccapurple', '', 'none',
		'color(srgb 0.5 0.25 1)', 'color(srgb 50% 0 1 / 0.5)', 'rgb(128 64 0)', 'color(display-p3 1 0 0)', 'rgb2(1 2 3)',
	];

	private const FUNCTIONS = ['rgb', 'rgba', 'hsl', 'hsla', 'oklch', 'oklab', 'lab', 'lch', 'hwb', 'color', 'RGB', 'foo'];

	private const SPACES = ['srgb', 'display-p3', 'rec2020', 'xyz', 'srgb-linear', 'nope'];

	private const ARGS = [
		'0', '255', '128', '300', '-5', '50%', '120%', '0.5', '.25', '1e2', '1.5e-1', '210deg', '0.6turn', '3.1rad', 'none', 'abc', '40%',
		'0.2', '0.37', '100%', '-0.1',
	];

	/**
	 * Every seeded literal parses as recorded.
	 *
	 * @return void
	 */
	public function testParsesMatchTheGoldenRecording(): void {
		$literal = new ColourLiteralParser();
		$css = new CssColorParser();
		$values = self::FIXED;
		for ($seed = 1; $seed <= 700; $seed++) {
			$values[] = $this->value(random: new Randomizer(engine: new Mt19937(seed: $seed)));
		}

		$actual = [];
		foreach ($values as $index => $value) {
			$actual[$index . ':' . $value] = (string)json_encode(
				['literal' => $literal->parse(value: $value), 'css' => $css->parse(value: $value)],
				JSON_PRESERVE_ZERO_FRACTION
			);
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame(expected: $json, actual: $actual[$name], message: (string)$name);
		}
	}//end testParsesMatchTheGoldenRecording()

	/**
	 * A seeded colour function literal.
	 *
	 * @param Randomizer $random The seeded source.
	 *
	 * @return string
	 */
	private function value(Randomizer $random): string {
		$name = self::FUNCTIONS[$random->getInt(0, (count(self::FUNCTIONS) - 1))];
		$count = $random->getInt(2, 4);
		$args = [];
		for ($i = 0; $i < $count; $i++) {
			$args[] = self::ARGS[$random->getInt(0, (count(self::ARGS) - 1))];
		}

		if (strtolower($name) === 'color' && $random->getInt(0, 4) > 0) {
			array_unshift($args, self::SPACES[$random->getInt(0, (count(self::SPACES) - 1))]);
		}

		$commas = ($random->getInt(0, 1) === 1);
		$text = implode(($commas === true ? ', ' : ' '), $args);
		$alpha = $random->getInt(0, 3);
		if ($alpha === 0) {
			$text .= ' / ' . ['0.5', '50%', '1', '2', '-1', 'x'][$random->getInt(0, 5)];
		}

		return $name . '(' . $text . ')';
	}//end value()
}//end class
