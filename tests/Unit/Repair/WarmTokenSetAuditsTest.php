<?php

/**
 * Unit tests for WarmTokenSetAudits.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Repair;

use OCA\Thematiq\Repair\WarmTokenSetAudits;
use OCA\Thematiq\Service\TokenSetService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * An install or upgrade lists the catalogue once, so the shipped sets are audited before the first admin page.
 */
#[CoversClass(WarmTokenSetAudits::class)]
class WarmTokenSetAuditsTest extends TestCase {

	/**
	 * The step lists the catalogue once and reports how many sets it audited.
	 *
	 * @return void
	 */
	public function testListsTheCatalogueOnce(): void {
		$tokenSets = $this->createMock(TokenSetService::class);
		$tokenSets->expects($this->once())->method('getAvailableTokenSets')->willReturn([['id' => 'a'], ['id' => 'b']]);
		$output = $this->createMock(IOutput::class);
		$output->expects($this->once())->method('info')->with('WarmTokenSetAudits: audited 2 token sets.');
		$output->expects($this->never())->method('warning');

		$step = new WarmTokenSetAudits($tokenSets, $this->createMock(LoggerInterface::class));
		$this->assertInstanceOf(IRepairStep::class, $step);
		$this->assertNotSame('', $step->getName());
		$step->run($output);
	}//end testListsTheCatalogueOnce()

	/**
	 * A failing listing is logged and reported, and never fails the upgrade.
	 *
	 * @return void
	 */
	public function testAFailureNeverFailsTheUpgrade(): void {
		$tokenSets = $this->createMock(TokenSetService::class);
		$tokenSets->method('getAvailableTokenSets')->willThrowException(new RuntimeException('read-only app data'));
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('warning')->with($this->anything(), ['exception' => 'read-only app data']);
		$output = $this->createMock(IOutput::class);
		$output->expects($this->once())->method('warning');
		$output->expects($this->never())->method('info');

		(new WarmTokenSetAudits($tokenSets, $logger))->run($output);
	}//end testAFailureNeverFailsTheUpgrade()

	/**
	 * The step is registered last in both the post-migration and the install block.
	 *
	 * @return void
	 */
	public function testRegisteredLastAfterInstallAndUpgrade(): void {
		$info = simplexml_load_file(__DIR__ . '/../../../appinfo/info.xml');
		foreach (['post-migration', 'install'] as $block) {
			$steps = array_map('strval', iterator_to_array($info->{'repair-steps'}->{$block}->step, false));
			$this->assertSame(WarmTokenSetAudits::class, end($steps), $block);
		}
	}//end testRegisteredLastAfterInstallAndUpgrade()
}//end class
