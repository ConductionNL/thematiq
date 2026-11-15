<?php

/**
 * Unit tests for each outcome line of the ConfigApply occ command.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Command;

use OCA\Thematiq\Command\ConfigApply;
use OCA\Thematiq\Service\ConfigSourceService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Pins the exit code and the printed line for every status the source service returns.
 */
class ConfigApplyOutcomeTest extends TestCase {

	/**
	 * Each status with its exit code and display.
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: int, 2: string}> The cases.
	 */
	public static function outcomes(): array {
		return [
			'applied with revision' => [['status' => 'applied', 'revision' => 'r1', 'hash' => 'h1'], Command::SUCCESS, "Branding package applied (r1).\n"],
			'applied with hash' => [['status' => 'applied', 'hash' => 'h1'], Command::SUCCESS, "Branding package applied (h1).\n"],
			'unchanged' => [['status' => 'unchanged'], Command::SUCCESS, "The branding package did not change. Nothing to do.\n"],
			'unconfigured' => [
				['status' => 'unconfigured'],
				Command::FAILURE,
				"No branding package is configured. Set thematiq.config_source in config.php.\n",
			],
			'busy' => [['status' => 'busy'], Command::FAILURE, "Another apply is running. Try again in a moment.\n"],
			'invalid' => [
				['status' => 'invalid', 'errors' => [['section' => 'tokens', 'message' => 'bad'], []]],
				Command::FAILURE,
				"The branding package was not applied, nothing changed:\n  - [tokens] bad\n  - [unknown] Unknown error.\n",
			],
			'invalid without errors' => [['status' => 'invalid'], Command::FAILURE, "The branding package was not applied, nothing changed:\n"],
		];
	}//end outcomes()

	/**
	 * The status decides the exit code and the line printed.
	 *
	 * @param array<string, mixed> $result The service result.
	 * @param int $code The expected exit code.
	 * @param string $display The expected display.
	 *
	 * @return void
	 */
	#[DataProvider('outcomes')]
	public function testEachStatusPrintsItsLine(array $result, int $code, string $display): void {
		$source = $this->createMock(originalClassName: ConfigSourceService::class);
		$source->expects($this->once())->method('applyIfChanged')->with(true)->willReturn($result);

		$tester = new CommandTester(new ConfigApply(source: $source));

		$this->assertSame(expected: $code, actual: $tester->execute(['--force' => true]));
		$this->assertSame(expected: $display, actual: $tester->getDisplay());
	}//end testEachStatusPrintsItsLine()
}//end class
