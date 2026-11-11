<?php

/**
 * Unit tests for the exact output of the ConfigRestore occ command.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/theme-versions/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Command;

use OCA\Thematiq\Command\ConfigRestore;
use OCA\Thematiq\Service\ThemeVersionRestoreService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Pins each outcome of `thematiq:config:restore` line by line.
 */
class ConfigRestoreOutcomeTest extends TestCase {

	/**
	 * A result with every change kind and the given validity.
	 *
	 * @param array<string, mixed> $extra Fields merged over the defaults.
	 *
	 * @return array<string, mixed> The result.
	 */
	private function restoreResult(array $extra): array {
		return $extra + [
			'valid' => true,
			'errors' => [],
			'changes' => [['field' => 'tokenSet', 'from' => 'a/b', 'to' => null]],
			'customTokenSets' => ['add' => ['custom-a'], 'remove' => ['custom-b']],
			'missingFonts' => [['role' => 'body', 'name' => 'Fira']],
		];
	}//end restoreResult()

	/**
	 * Run the command against a restorer.
	 *
	 * @param ThemeVersionRestoreService $restorer The restorer.
	 * @param array<string, mixed> $input The input.
	 *
	 * @return array{0: int, 1: string} The exit code and the display.
	 */
	private function runCommand(ThemeVersionRestoreService $restorer, array $input): array {
		$tester = new CommandTester(new ConfigRestore(restorer: $restorer));
		$code = $tester->execute($input);

		return [$code, $tester->getDisplay()];
	}//end runCommand()

	/**
	 * The changes block lists fields, added and removed sets and missing fonts in order.
	 *
	 * @return void
	 */
	public function testAppliedRestorePrintsEveryChangeKind(): void {
		$restorer = $this->createMock(originalClassName: ThemeVersionRestoreService::class);
		$restorer->method('restore')->willReturn($this->restoreResult(extra: ['applied' => true]));

		$this->assertSame(
			expected: [
				Command::SUCCESS,
				"  tokenSet: \"a/b\" -> null\n  custom token set added: custom-a\n  custom token set removed: custom-b\n"
				. "  body font no longer uploaded, stays on the default: Fira\nVersion v1 restored.\n",
			],
			actual: $this->runCommand(restorer: $restorer, input: ['id' => 'v1'])
		);
	}//end testAppliedRestorePrintsEveryChangeKind()

	/**
	 * A valid result that was not applied is a failure, errors listed with their fallbacks.
	 *
	 * @return void
	 */
	public function testAValidButUnappliedRestoreFails(): void {
		$restorer = $this->createMock(originalClassName: ThemeVersionRestoreService::class);
		$restorer->method('restore')->willReturn(
			$this->restoreResult(
				extra: [
					'changes' => [],
					'customTokenSets' => ['add' => [], 'remove' => []],
					'missingFonts' => [],
					'errors' => [['code' => 7]],
				]
			)
		);

		$this->assertSame(
			expected: [
				Command::FAILURE,
				"The version does not validate today; nothing was applied:\n  - [unknown] {\"code\":7}\n",
			],
			actual: $this->runCommand(restorer: $restorer, input: ['id' => 'v1'])
		);
	}//end testAValidButUnappliedRestoreFails()

	/**
	 * A valid dry run needs no `applied` flag.
	 *
	 * @return void
	 */
	public function testAValidDryRunSucceedsWithoutApplied(): void {
		$restorer = $this->createMock(originalClassName: ThemeVersionRestoreService::class);
		$restorer->expects($this->never())->method('restore');
		$restorer->method('preview')->willReturn(
			$this->restoreResult(extra: ['changes' => [], 'customTokenSets' => ['add' => [], 'remove' => []], 'missingFonts' => []])
		);

		$this->assertSame(
			expected: [Command::SUCCESS, "Dry run: nothing was written.\n"],
			actual: $this->runCommand(restorer: $restorer, input: ['id' => 'v1', '--dry-run' => true])
		);
	}//end testAValidDryRunSucceedsWithoutApplied()

	/**
	 * An invalid dry run fails and lists the errors.
	 *
	 * @return void
	 */
	public function testAnInvalidDryRunFails(): void {
		$restorer = $this->createMock(originalClassName: ThemeVersionRestoreService::class);
		$restorer->method('preview')->willReturn(
			$this->restoreResult(
				extra: [
					'valid' => false,
					'changes' => [],
					'customTokenSets' => ['add' => [], 'remove' => []],
					'missingFonts' => [],
					'errors' => [['section' => 'fonts', 'message' => 'bad']],
				]
			)
		);

		$this->assertSame(
			expected: [Command::FAILURE, "The version does not validate today; nothing was applied:\n  - [fonts] bad\n"],
			actual: $this->runCommand(restorer: $restorer, input: ['id' => 'v1', '--dry-run' => true])
		);
	}//end testAnInvalidDryRunFails()

	/**
	 * An unknown version names the id.
	 *
	 * @return void
	 */
	public function testAnUnknownVersionFails(): void {
		$restorer = $this->createMock(originalClassName: ThemeVersionRestoreService::class);
		$restorer->method('preview')->willReturn(null);

		$this->assertSame(
			expected: [Command::FAILURE, "Unknown version: v9\n"],
			actual: $this->runCommand(restorer: $restorer, input: ['id' => 'v9', '--dry-run' => true])
		);
	}//end testAnUnknownVersionFails()
}//end class
