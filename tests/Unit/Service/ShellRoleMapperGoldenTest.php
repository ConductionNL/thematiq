<?php

/**
 * Golden test for ShellRoleMapper::map().
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
 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-every-shell-role-always-has-a-value
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\ShellRoleMapper;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use ReflectionClassConstant;

/**
 * Maps seeded token sets onto the shell roles: every source token the mapper reads given a
 * colour, a length, a translucent, empty or unusable value or nothing, with and without the
 * administrator's own button colours. Compares each role map with the recording.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\ShellRoleMapper
 * @uses   \OCA\Thematiq\Service\ContrastService
 */
class ShellRoleMapperGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/shell-role-mapper.golden.json';

	private const VALUES = ['#154273', '#ffffff', '#000', 'rgb(200, 16, 46)', 'rgba(0, 0, 0, 0.4)', '', '  ', 'var(--x)', '4px', '0.5rem', '12', 'Inter, sans-serif', 'transparent', 'not-a-value'];

	/**
	 * Every seeded set maps to the recorded roles.
	 *
	 * @return void
	 */
	public function testMapMatchesTheGoldenRecording(): void {
		$mapper = new ShellRoleMapper(colours: new ContrastService());
		$names = $this->sourceNames();
		$actual = [];
		for ($seed = 1; $seed <= 300; $seed++) {
			$random = new Randomizer(new Mt19937($seed));
			$tokens = [];
			foreach ($names as $name) {
				if ($random->getInt(0, 2) === 0) {
					$tokens[$name] = self::VALUES[$random->getInt(0, (count(self::VALUES) - 1))];
				}
			}

			$overrides = [];
			foreach (['--nldesign-component-button-primary-action-background-color', '--nldesign-component-button-primary-action-hover-background-color'] as $name) {
				if ($random->getInt(0, 2) === 0) {
					$overrides[$name] = self::VALUES[$random->getInt(0, 4)];
				}
			}

			foreach ($names as $name) {
				if ($random->getInt(0, 9) === 0) {
					$overrides[$name] = self::VALUES[$random->getInt(0, 4)];
				}
			}

			$actual['seed:' . $seed] = (string)json_encode(
				['tokens' => $tokens, 'overrides' => $overrides, 'roles' => $mapper->map(tokens: $tokens, overrides: $overrides)],
				JSON_UNESCAPED_SLASHES
			);
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame(expected: $json, actual: $actual[$name], message: $name);
		}
	}//end testMapMatchesTheGoldenRecording()

	/**
	 * Every token name the mapper reads.
	 *
	 * @return array<int, string>
	 */
	private function sourceNames(): array {
		$names = ['--nldesign-color-action', '--nldesign-component-button-primary-action-background-color', '--nldesign-component-button-primary-action-hover-background-color'];
		foreach ((new ReflectionClassConstant(ShellRoleMapper::class, 'SOURCES'))->getValue() as $sources) {
			foreach ($sources as $name) {
				$names[] = $name;
			}
		}

		foreach ((new ReflectionClassConstant(ShellRoleMapper::class, 'PRIMARY_HOVER'))->getValue() as $name) {
			$names[] = $name;
		}

		$names = array_values(array_unique($names));
		sort($names);
		return $names;
	}//end sourceNames()
}//end class
