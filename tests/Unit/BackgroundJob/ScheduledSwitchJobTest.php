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
	 *
	 * @return void
	 */
	public function testRunsEveryFiveMinutesAndIsTimeSensitive(): void {
		$job = new ScheduledSwitchJob(
			time: $this->createMock(originalClassName: ITimeFactory::class),
			service: $this->createMock(originalClassName: ScheduledSwitchService::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);

		$this->assertSame(expected: 300, actual: $job->getInterval());
		$property = new \ReflectionProperty(TimedJob::class, 'timeSensitivity');
		$property->setAccessible(true);
		$this->assertSame(expected: TimedJob::TIME_SENSITIVE, actual: $property->getValue($job));
	}//end testRunsEveryFiveMinutesAndIsTimeSensitive()

	/**
	 * run() delegates to runDue().
	 *
	 * @return void
	 */
	public function testRunDelegatesToTheService(): void {
		$service = $this->createMock(originalClassName: ScheduledSwitchService::class);
		$service->expects($this->once())->method('runDue');

		$this->invokeRun(job: new ScheduledSwitchJob(
			time: $this->createMock(originalClassName: ITimeFactory::class),
			service: $service,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		));
	}//end testRunDelegatesToTheService()

	/**
	 * A throw is logged, not rethrown.
	 *
	 * @return void
	 */
	public function testAThrowIsContained(): void {
		$service = $this->createMock(originalClassName: ScheduledSwitchService::class);
		$service->method('runDue')->willThrowException(new \RuntimeException('boom'));
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('error');

		$this->invokeRun(job: new ScheduledSwitchJob(
			time: $this->createMock(originalClassName: ITimeFactory::class),
			service: $service,
			logger: $logger
		));
	}//end testAThrowIsContained()

	/**
	 * The job is registered next to the freshness job.
	 *
	 * @return void
	 */
	public function testTheJobIsRegisteredInInfoXml(): void {
		$xml = (string)file_get_contents(__DIR__ . '/../../../appinfo/info.xml');
		$this->assertStringContainsString(needle: '<job>OCA\Thematiq\BackgroundJob\ScheduledSwitchJob</job>', haystack: $xml);
	}//end testTheJobIsRegisteredInInfoXml()
}//end class
