<?php

/**
 * Tests for the nldesign:theme:* commands.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Tests
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/theme-cli/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Command;

use OCA\Thematiq\Command\ThemeGet;
use OCA\Thematiq\Command\ThemeList;
use OCA\Thematiq\Command\ThemeSet;
use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\ScheduledCoreThemingSync;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\TokenSetService;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The commands run over the real ActiveTokenSetService, so the write, the validation and the audit
 * call are the ones the Design token set dropdown uses.
 *
 * @spec openspec/specs/theme-cli/spec.md
 */
class ThemeCommandsTest extends TestCase {

	/**
	 * The stored app config, by key.
	 *
	 * @var array<string, string>
	 */
	private array $store = [];

	/**
	 * The config double.
	 *
	 * @var IConfig&MockObject
	 */
	private IConfig $config;

	/**
	 * The set list double.
	 *
	 * @var TokenSetService&MockObject
	 */
	private TokenSetService $tokenSets;

	/**
	 * The audit double.
	 *
	 * @var ThemingAuditService&MockObject
	 */
	private ThemingAuditService $audit;

	/**
	 * The core theming sync double.
	 *
	 * @var ScheduledCoreThemingSync&MockObject
	 */
	private ScheduledCoreThemingSync $coreSync;

	/**
	 * Wire the doubles: `rijkshuisstijl` active, `amsterdam` and `custom-gemeente-x` available.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->store = ['token_set' => 'rijkshuisstijl'];
		$this->config = $this->createMock(IConfig::class);
		$this->config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, string $default = '') => ($this->store[$key] ?? $default)
		);
		$this->config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, string $value): void {
				$this->store[$key] = $value;
			}
		);
		$this->tokenSets = $this->createMock(TokenSetService::class);
		$this->tokenSets->method('isValidTokenSet')->willReturnCallback(
			fn (string $tokenSetId) => in_array($tokenSetId, ['rijkshuisstijl', 'amsterdam', 'custom-gemeente-x'], true)
		);
		$this->tokenSets->method('getAvailableTokenSets')->willReturn(
			[
				['id' => 'amsterdam', 'name' => 'Amsterdam'],
				['id' => 'custom-gemeente-x', 'name' => 'Gemeente X'],
				['id' => 'rijkshuisstijl', 'name' => 'Rijkshuisstijl'],
			]
		);
		$this->audit = $this->createMock(ThemingAuditService::class);
		$this->coreSync = $this->createMock(ScheduledCoreThemingSync::class);
	}//end setUp()

	/**
	 * A tester for one command.
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
	 * The set command over the real active set service.
	 *
	 * @return CommandTester The tester.
	 */
	private function setCommand(): CommandTester {
		$active = new ActiveTokenSetService($this->config, $this->tokenSets, $this->audit);

		return $this->tester(new ThemeSet($active, $this->tokenSets, $this->coreSync));
	}//end setCommand()

	/**
	 * Scenario: an operator looks up the id of a set.
	 *
	 * @return void
	 */
	public function testListMarksCustomSets(): void {
		$tester = $this->tester(new ThemeList($this->tokenSets));

		$this->assertSame(Command::SUCCESS, $tester->execute([]));
		$this->assertStringContainsString('custom-gemeente-x  custom  Gemeente X', $tester->getDisplay());
		$this->assertStringContainsString('amsterdam  shipped  Amsterdam', $tester->getDisplay());
	}//end testListMarksCustomSets()

	/**
	 * The get command prints the active set and the mappings in priority order.
	 *
	 * @return void
	 */
	public function testGetPrintsActiveSetAndMappingsInOrder(): void {
		$groups = $this->createMock(GroupThemingService::class);
		$groups->method('getMapping')->willReturn(
			[['group' => 'noord', 'tokenSet' => 'amsterdam'], ['group' => 'admin', 'tokenSet' => 'custom-gemeente-x']]
		);
		$active = new ActiveTokenSetService($this->config, $this->tokenSets, $this->audit);
		$tester = $this->tester(new ThemeGet($active, $groups));

		$this->assertSame(Command::SUCCESS, $tester->execute([]));
		$display = $tester->getDisplay();
		$this->assertStringContainsString('Active token set: rijkshuisstijl', $display);
		$this->assertStringContainsString("1. noord  amsterdam\n2. admin  custom-gemeente-x", $display);
	}//end testGetPrintsActiveSetAndMappingsInOrder()

	/**
	 * Scenario: an operator switches the house style from a script. Audited as `cli`, core untouched.
	 *
	 * @return void
	 */
	public function testSetSwitchesAndAuditsAsCli(): void {
		$this->audit->expects($this->once())->method('log')->with(
			'token_set_changed',
			['old' => 'rijkshuisstijl', 'new' => 'amsterdam', 'actor' => 'cli']
		);
		$this->coreSync->expects($this->never())->method('sync');

		$tester = $this->setCommand();

		$this->assertSame(Command::SUCCESS, $tester->execute(['token-set' => 'amsterdam']));
		$this->assertSame('amsterdam', $this->store['token_set']);
		$this->assertStringContainsString('Nextcloud theming is unchanged', $tester->getDisplay());
	}//end testSetSwitchesAndAuditsAsCli()

	/**
	 * Scenario: a typo does not change the theme.
	 *
	 * @return void
	 */
	public function testUnknownIdFailsAndChangesNothing(): void {
		$this->audit->expects($this->never())->method('log');

		$tester = $this->setCommand();

		$this->assertSame(Command::FAILURE, $tester->execute(['token-set' => 'amsterdm']));
		$this->assertStringContainsString('amsterdm', $tester->getDisplay());
		$this->assertSame('rijkshuisstijl', $this->store['token_set']);
	}//end testUnknownIdFailsAndChangesNothing()

	/**
	 * Setting the set that is already active exits 0 and writes no audit entry.
	 *
	 * @return void
	 */
	public function testSameSetIsANoOp(): void {
		$this->audit->expects($this->never())->method('log');

		$tester = $this->setCommand();

		$this->assertSame(Command::SUCCESS, $tester->execute(['token-set' => 'rijkshuisstijl']));
		$this->assertStringContainsString('already the active token set', $tester->getDisplay());
	}//end testSameSetIsANoOp()

	/**
	 * A dry run validates and prints, and writes nothing.
	 *
	 * @return void
	 */
	public function testDryRunWritesNothing(): void {
		$this->audit->expects($this->never())->method('log');
		$this->coreSync->expects($this->never())->method('sync');

		$tester = $this->setCommand();

		$this->assertSame(Command::SUCCESS, $tester->execute(['token-set' => 'amsterdam', '--dry-run' => true, '--sync-core' => true]));
		$this->assertStringContainsString('would switch the active token set from rijkshuisstijl to amsterdam', $tester->getDisplay());
		$this->assertSame('rijkshuisstijl', $this->store['token_set']);
	}//end testDryRunWritesNothing()

	/**
	 * Scenario: an operator also updates the Nextcloud logo and colours.
	 *
	 * @return void
	 */
	public function testSyncCoreAppliesAndListsTheValues(): void {
		$this->coreSync->expects($this->once())->method('sync')->with('amsterdam')->willReturn(['primary_color', 'background_color', 'logo']);

		$tester = $this->setCommand();

		$this->assertSame(Command::SUCCESS, $tester->execute(['token-set' => 'amsterdam', '--sync-core' => true]));
		$this->assertStringContainsString('Applied to Nextcloud theming: primary_color, background_color, logo.', $tester->getDisplay());
	}//end testSyncCoreAppliesAndListsTheValues()
}//end class
