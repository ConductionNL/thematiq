<?php

/**
 * Unit tests for ScheduledSwitchJob.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/scheduled-switch/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\BackgroundJob;

use OCA\Thematiq\BackgroundJob\ScheduledSwitchJob;
use OCA\Thematiq\Service\ScheduledSwitchService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The job runs every five minutes, time sensitive, delegates to the service
 * and never lets a throw escape into cron. The apply and revert behaviour
 * itself is covered in ScheduledSwitchServiceTest.
 */
class ScheduledSwitchJobTest extends TestCase {

	/**
	 * Invoke the protected run() method.
	 *
	 * @param ScheduledSwitchJob $job The job.
	 *
	 * @return void
	 */
	private function invokeRun(ScheduledSwitchJob $job): void {
		$method = new \ReflectionMethod($job, 'run');
		$method->setAccessible(true);
		$method->invoke($job, null);
	}//end invokeRun()

	/**
	 * Five minutes, time sensitive.
	 */
	public function testRunsEveryFiveMinutesAndIsTimeSensitive(): void {
		$job = new ScheduledSwitchJob(
			$this->createMock(ITimeFactory::class),
			$this->createMock(ScheduledSwitchService::class),
			$this->createMock(LoggerInterface::class)
		);

		$this->assertSame(300, $job->getInterval());
		$property = new \ReflectionProperty(TimedJob::class, 'timeSensitivity');
		$property->setAccessible(true);
		$this->assertSame(TimedJob::TIME_SENSITIVE, $property->getValue($job));
	}//end testRunsEveryFiveMinutesAndIsTimeSensitive()

	/**
	 * run() delegates to runDue().
	 */
	public function testRunDelegatesToTheService(): void {
		$service = $this->createMock(ScheduledSwitchService::class);
		$service->expects($this->once())->method('runDue');

		$this->invokeRun(new ScheduledSwitchJob($this->createMock(ITimeFactory::class), $service, $this->createMock(LoggerInterface::class)));
	}//end testRunDelegatesToTheService()

	/**
	 * A throw is logged, not rethrown.
	 */
	public function testAThrowIsContained(): void {
		$service = $this->createMock(ScheduledSwitchService::class);
		$service->method('runDue')->willThrowException(new \RuntimeException('boom'));
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('error');

		$this->invokeRun(new ScheduledSwitchJob($this->createMock(ITimeFactory::class), $service, $logger));
	}//end testAThrowIsContained()

	/**
	 * The job is registered next to the freshness job.
	 */
	public function testTheJobIsRegisteredInInfoXml(): void {
		$xml = (string)file_get_contents(__DIR__ . '/../../../appinfo/info.xml');
		$this->assertStringContainsString('<job>OCA\Thematiq\BackgroundJob\ScheduledSwitchJob</job>', $xml);
	}//end testTheJobIsRegisteredInInfoXml()
}//end class
