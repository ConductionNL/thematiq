<?php

/**
 * Seeded golden test for ContrastService::check(), measure() and parseColorWithAlpha().
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
 * @spec openspec/specs/token-set-contrast-audit/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use ReflectionClassConstant;

/**
 * Checks seeded token sets: every checked pair and the action pair declared, missing, equal to
 * the primary pair, opaque, translucent (alpha as a number and as a percentage) or unparseable,
 * on seeded page backgrounds. Also measures and parses every colour of the pool directly.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\ContrastService
 * @uses   \OCA\Thematiq\Service\ColourRgba
 */
class ContrastServiceCheckGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/contrast-service-check.golden.json';

	private const COLOURS = [
		'#154273', '#ffffff', '#000', '#0008', '#767676', '#fafafa', '#e17000', '#ffff00', '#15427380', 'rgb(21, 66, 115)',
		'rgba(0, 0, 0, 0.5)', 'rgba(255,255,255,50%)', 'rgba(0, 0, 0, )', 'rgb(300, 0, 0)', 'rgba(0, 0, 0, 2)', ' #ABCDEF ',
		'transparent', 'not-a-colour', 'hsl(210, 60%, 40%)', 'rgba(10, 20, 30, 0%)',
	];

	/**
	 * Every case checks as recorded.
	 *
	 * @return void
	 */
	public function testChecksMatchTheGoldenRecording(): void {
		$service = new ContrastService();
		$tokens = [
			'--nldesign-color-primary',
			'--nldesign-color-primary-text',
			'--nldesign-color-action',
			'--nldesign-color-action-text',
			'--nldesign-color-background',
		];
		foreach ((new ReflectionClassConstant(ContrastService::class, 'PAIRS'))->getValue() as $pair) {
			$tokens[] = $pair['fg'];
			$tokens[] = $pair['bg'];
		}

		$tokens = array_values(array_unique($tokens));
		$actual = [];
		for ($seed = 1; $seed <= 300; $seed++) {
			$random = new Randomizer(engine: new Mt19937(seed: $seed));
			$declarations = [];
			foreach ($tokens as $token) {
				if ($random->getInt(0, 2) > 0) {
					$declarations[$token] = self::COLOURS[$random->getInt(0, (count(self::COLOURS) - 1))];
				}
			}

			$copy = $random->getInt(0, 4);
			if ($copy === 0 && isset($declarations['--nldesign-color-primary']) === true) {
				$declarations['--nldesign-color-action'] = $declarations['--nldesign-color-primary'];
			}

			if ($copy <= 1 && isset($declarations['--nldesign-color-primary-text']) === true) {
				$declarations['--nldesign-color-action-text'] = $declarations['--nldesign-color-primary-text'];
			}

			$actual['check:' . $seed] = (string)json_encode($service->check(declarations: $declarations), JSON_PRESERVE_ZERO_FRACTION);
		}

		foreach (self::COLOURS as $index => $colour) {
			$actual['parse:' . $index] = (string)json_encode($service->parseColorWithAlpha(value: $colour), JSON_PRESERVE_ZERO_FRACTION);
			$measured = [];
			foreach ([null, '#ffffff', '#1a1a1a', 'rgba(0, 0, 0, 0.5)', 'nope'] as $page) {
				$measured[] = $service->measure(foreground: '#767676', background: $colour, page: $page);
				$measured[] = $service->measure(foreground: $colour, background: '#fafafa80', page: $page);
			}

			$actual['measure:' . $index] = (string)json_encode($measured, JSON_PRESERVE_ZERO_FRACTION);
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame(expected: $json, actual: $actual[$name], message: $name);
		}
	}//end testChecksMatchTheGoldenRecording()
}//end class
