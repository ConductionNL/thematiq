<?php

/**
 * Unit tests for the exact output of the theme:set and generate-dark-variants occ commands.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/theme-cli/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Command;

use OCA\Thematiq\Command\GenerateDarkVariants;
use OCA\Thematiq\Command\ThemeSet;
use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\Exception\RestrictedSetNotConfirmedException;
use OCA\Thematiq\Service\ScheduledCoreThemingSync;
use OCA\Thematiq\Service\TokenSetService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Pins each outcome of `thematiq:theme:set` and `thematiq:generate-dark-variants` line by line.
 */
class ThemeCommandOutcomeTest extends TestCase {

	/**
	 * A theme:set tester whose active set is `rijkshuisstijl` and whose valid sets are it and `amsterdam`.
	 *
	 * @param ActiveTokenSetService $active The active token set service.
	 * @param ScheduledCoreThemingSync $coreSync The core theming sync.
	 *
	 * @return CommandTester The tester.
	 */
	private function themeSet(ActiveTokenSetService $active, ScheduledCoreThemingSync $coreSync): CommandTester {
		$tokenSets = $this->createMock(originalClassName: TokenSetService::class);
		$tokenSets->method('isValidTokenSet')->willReturnCallback(
			fn (string $tokenSetId): bool => in_array($tokenSetId, ['rijkshuisstijl', 'amsterdam'], true)
		);
		$active->method('getActive')->willReturn('rijkshuisstijl');

		return new CommandTester(new ThemeSet(active: $active, tokenSets: $tokenSets, coreSync: $coreSync));
	}//end themeSet()

	/**
	 * A switch without --sync-core prints the switch and the unchanged-theming hint.
	 *
	 * @return void
	 */
	public function testASwitchPrintsTheSwitchAndTheThemingHint(): void {
		$active = $this->createMock(originalClassName: ActiveTokenSetService::class);
		$active->expects($this->once())->method('switchTo')->with('amsterdam', 'token_set_changed', ['actor' => 'cli'])->willReturn('rijkshuisstijl');
		$coreSync = $this->createMock(originalClassName: ScheduledCoreThemingSync::class);
		$coreSync->expects($this->never())->method('sync');
		$tester = $this->themeSet(active: $active, coreSync: $coreSync);

		$this->assertSame(expected: Command::SUCCESS, actual: $tester->execute(['token-set' => 'amsterdam']));
		$this->assertSame(
			expected: "Switched the active token set from rijkshuisstijl to amsterdam.\n"
				. "Nextcloud theming is unchanged. Pass --sync-core to apply the set's colours and logo.\n",
			actual: $tester->getDisplay()
		);
	}//end testASwitchPrintsTheSwitchAndTheThemingHint()

	/**
	 * The active set again is a no-op that still syncs core theming when asked.
	 *
	 * @return void
	 */
	public function testTheActiveSetIsANoOpThatStillSyncs(): void {
		$active = $this->createMock(originalClassName: ActiveTokenSetService::class);
		$active->expects($this->never())->method('switchTo');
		$coreSync = $this->createMock(originalClassName: ScheduledCoreThemingSync::class);
		$coreSync->method('sync')->with('rijkshuisstijl')->willReturn([]);
		$coreSync->method('themingParams')->willReturn([]);
		$tester = $this->themeSet(active: $active, coreSync: $coreSync);

		$this->assertSame(expected: Command::SUCCESS, actual: $tester->execute(['token-set' => 'rijkshuisstijl', '--sync-core' => true]));
		$this->assertSame(
			expected: "rijkshuisstijl is already the active token set. Nothing was changed.\n"
				. "Nextcloud theming is unchanged: rijkshuisstijl has no theming values to apply.\n",
			actual: $tester->getDisplay()
		);
	}//end testTheActiveSetIsANoOpThatStillSyncs()

	/**
	 * A restricted set that is not confirmed fails before core theming is touched.
	 *
	 * @return void
	 */
	public function testAnUnconfirmedRestrictedSetFails(): void {
		$active = $this->createMock(originalClassName: ActiveTokenSetService::class);
		$active->method('switchTo')->willThrowException(new RestrictedSetNotConfirmedException(tokenSetId: 'amsterdam'));
		$coreSync = $this->createMock(originalClassName: ScheduledCoreThemingSync::class);
		$coreSync->expects($this->never())->method('sync');
		$tester = $this->themeSet(active: $active, coreSync: $coreSync);

		$this->assertSame(expected: Command::FAILURE, actual: $tester->execute(['token-set' => 'amsterdam', '--sync-core' => true]));
		$this->assertSame(
			expected: 'amsterdam is the house style of another organisation. An administrator first confirms in the '
				. "Thematiq admin settings that your organisation may use it. Nothing was changed.\n",
			actual: $tester->getDisplay()
		);
	}//end testAnUnconfirmedRestrictedSetFails()

	/**
	 * An unknown set and a dry run each print one line and never switch.
	 *
	 * @return void
	 */
	public function testAnUnknownSetAndADryRunNeverSwitch(): void {
		$active = $this->createMock(originalClassName: ActiveTokenSetService::class);
		$active->expects($this->never())->method('switchTo');
		$tester = $this->themeSet(active: $active, coreSync: $this->createMock(originalClassName: ScheduledCoreThemingSync::class));

		$this->assertSame(expected: Command::FAILURE, actual: $tester->execute(['token-set' => 'nope']));
		$this->assertSame(expected: "Unknown token set: nope. Nothing was changed.\n", actual: $tester->getDisplay());
		$this->assertSame(expected: Command::SUCCESS, actual: $tester->execute(['token-set' => 'amsterdam', '--dry-run' => true]));
		$this->assertSame(
			expected: "Dry run: would switch the active token set from rijkshuisstijl to amsterdam.\n",
			actual: $tester->getDisplay()
		);
	}//end testAnUnknownSetAndADryRunNeverSwitch()

	/**
	 * Every set is reported with its outcome and warnings, and one failed write fails the run.
	 *
	 * @return void
	 */
	public function testDarkVariantsReportEachSetAndFailOnAFailedWrite(): void {
		$palette = $this->createMock(originalClassName: DarkPaletteService::class);
		$palette->expects($this->once())->method('discoverAllSetIds')->willReturn(['a', 'b', 'c']);
		$palette->method('generateAndWrite')->willReturnMap(
			[
				['a', true, ['written' => true, 'reason' => null, 'warnings' => [['pair' => 'fg/bg', 'ratio' => 3.1, 'threshold' => 4.5]]]],
				['b', true, ['written' => false, 'reason' => 'write-failed', 'warnings' => []]],
				['c', true, ['written' => false, 'reason' => 'fresh', 'warnings' => [['pair' => 'x/y', 'threshold' => 3]]]],
			]
		);
		$tester = new CommandTester(new GenerateDarkVariants(darkPalette: $palette));

		$this->assertSame(expected: Command::FAILURE, actual: $tester->execute(['--force' => true]));
		$this->assertSame(
			expected: "a: written\n  contrast warning: fg/bg = 3.1 (needs 4.5:1)\nb: write failed\nc: skipped (fresh)\n"
				. "  contrast warning: x/y = unevaluated (needs 3:1)\n",
			actual: $tester->getDisplay()
		);
	}//end testDarkVariantsReportEachSetAndFailOnAFailedWrite()

	/**
	 * `--set` still discovers, then runs only that set; an empty `--set` runs every set.
	 *
	 * @return void
	 */
	public function testDarkVariantsSetOptionNarrowsTheRun(): void {
		$palette = $this->createMock(originalClassName: DarkPaletteService::class);
		$palette->expects($this->exactly(2))->method('discoverAllSetIds')->willReturn(['a', 'b']);
		$palette->expects($this->exactly(3))->method('generateAndWrite')->willReturn(['written' => true, 'reason' => null, 'warnings' => []]);
		$tester = new CommandTester(new GenerateDarkVariants(darkPalette: $palette));

		$this->assertSame(expected: Command::SUCCESS, actual: $tester->execute(['--set' => 'z']));
		$this->assertSame(expected: "z: written\n", actual: $tester->getDisplay());
		$this->assertSame(expected: Command::SUCCESS, actual: $tester->execute(['--set' => '']));
		$this->assertSame(expected: "a: written\nb: written\n", actual: $tester->getDisplay());
	}//end testDarkVariantsSetOptionNarrowsTheRun()
}//end class
