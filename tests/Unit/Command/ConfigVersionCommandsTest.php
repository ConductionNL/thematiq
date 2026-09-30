<?php

/**
 * Unit tests for occ nldesign:config:versions and nldesign:config:restore.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/theme-versions/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Command;

use OCA\Thematiq\Command\ConfigRestore;
use OCA\Thematiq\Command\ConfigVersions;
use OCA\Thematiq\Service\ThemeVersionRestoreService;
use OCA\Thematiq\Service\ThemeVersionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Operators list and restore versions without the web interface.
 */
class ConfigVersionCommandsTest extends TestCase {

	/**
	 * Build a tester for one command.
	 *
	 * @param Command $command The command.
	 *
	 * @return CommandTester The tester.
	 */
	private function tester(Command $command): CommandTester {
		$application = new Application();
		$application->add($command);

		return new CommandTester($application->find((string)$command->getName()));
	}//end tester()

	/**
	 * The versions command prints id, time, actor and action per version.
	 */
	public function testVersionsListsEachVersion(): void {
		$versions = $this->createMock(ThemeVersionService::class);
		$versions->method('list')->willReturn(
			[['id' => '20260929164000-0001', 'ts' => '2026-09-29T16:40:00Z', 'actor' => 'admin', 'action' => 'token_set_changed']]
		);

		$tester = $this->tester(new ConfigVersions($versions));

		$this->assertSame(Command::SUCCESS, $tester->execute([]));
		$this->assertStringContainsString('20260929164000-0001  2026-09-29T16:40:00Z  admin  token_set_changed', $tester->getDisplay());
	}//end testVersionsListsEachVersion()

	/**
	 * --dry-run prints the changes and writes nothing; without it the restore applies and exits 0.
	 */
	public function testRestoreDryRunThenApply(): void {
		$restorer = $this->createMock(ThemeVersionRestoreService::class);
		$preview = [
			'valid' => true,
			'errors' => [],
			'changes' => [['field' => 'tokenSet', 'from' => 'custom-bad', 'to' => 'rijkshuisstijl']],
			'customTokenSets' => ['add' => [], 'remove' => ['custom-bad']],
			'missingFonts' => [],
		];
		$restorer->expects($this->once())->method('preview')->with('20260929164000-0001')->willReturn($preview);
		$restorer->expects($this->once())->method('restore')->with('20260929164000-0001')->willReturn($preview + ['applied' => true]);

		$tester = $this->tester(new ConfigRestore($restorer));

		$this->assertSame(Command::SUCCESS, $tester->execute(['id' => '20260929164000-0001', '--dry-run' => true]));
		$this->assertStringContainsString('tokenSet: "custom-bad" -> "rijkshuisstijl"', $tester->getDisplay());
		$this->assertStringContainsString('Dry run', $tester->getDisplay());

		$this->assertSame(Command::SUCCESS, $tester->execute(['id' => '20260929164000-0001']));
		$this->assertStringContainsString('restored', $tester->getDisplay());
	}//end testRestoreDryRunThenApply()

	/**
	 * An unknown id and a refused version exit 1.
	 */
	public function testRestoreFailsForUnknownAndRefused(): void {
		$restorer = $this->createMock(ThemeVersionRestoreService::class);
		$restorer->method('restore')->willReturnOnConsecutiveCalls(
			null,
			['valid' => false, 'applied' => false, 'errors' => [['section' => 'customTokenSets', 'message' => 'custom-x fails the whitelist']], 'changes' => [], 'customTokenSets' => ['add' => [], 'remove' => []], 'missingFonts' => []]
		);

		$tester = $this->tester(new ConfigRestore($restorer));

		$this->assertSame(Command::FAILURE, $tester->execute(['id' => 'nope']));
		$this->assertSame(Command::FAILURE, $tester->execute(['id' => '20260929164000-0001']));
		$this->assertStringContainsString('custom-x fails the whitelist', $tester->getDisplay());
	}//end testRestoreFailsForUnknownAndRefused()
}//end class
