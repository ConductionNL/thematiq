<?php

/**
 * Unit tests for UpstreamFreshnessJob.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/archive/2026-07-23-upstream-token-freshness/tasks.md#task-5.2
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\BackgroundJob;

use OCA\Thematiq\BackgroundJob\UpstreamFreshnessJob;
use OCA\Thematiq\Service\UpstreamFreshnessService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers openspec/specs/upstream-freshness/spec.md "Daily Freshness
 * Background Job": the job declares a 24-hour interval and time-insensitive
 * execution, run() delegates to the service, and a service-level throw does
 * not escape run() (belt and braces on top of the service's own catch-all).
 */
class UpstreamFreshnessJobTest extends TestCase {

	/**
	 * Invoke the protected run() method via reflection.
	 *
	 * @param UpstreamFreshnessJob $job The job instance.
	 * @param mixed $argument The argument to pass to run().
	 *
	 * @return void
	 */
	private function invokeRun(UpstreamFreshnessJob $job, $argument = null): void {
		$method = new \ReflectionMethod($job, 'run');
		$method->setAccessible(true);
		$method->invoke($job, $argument);
	}//end invokeRun()

	/**
	 * The job declares a 24-hour interval and TIME_INSENSITIVE sensitivity.
	 * `getInterval()` is the public OCP getter; time-sensitivity has no
	 * public getter on this Nextcloud version (only `isTimeSensitive()`,
	 * which collapses TIME_INSENSITIVE and TIME_INSENSITIVE_ONLY to the
	 * same `false`), so the protected `timeSensitivity` property is read via
	 * reflection to assert the exact declared level.
	 */
	public function testDeclaresDailyIntervalAndTimeInsensitive(): void {
		$time = $this->createMock(ITimeFactory::class);
		$service = $this->createMock(UpstreamFreshnessService::class);
		$logger = $this->createMock(LoggerInterface::class);

		$job = new UpstreamFreshnessJob($time, $service, $logger);

		$this->assertSame(24 * 60 * 60, $job->getInterval());
		$this->assertFalse($job->isTimeSensitive());

		$property = new \ReflectionProperty(TimedJob::class, 'timeSensitivity');
		$property->setAccessible(true);
		$this->assertSame(TimedJob::TIME_INSENSITIVE, $property->getValue($job));
	}//end testDeclaresDailyIntervalAndTimeInsensitive()

	/**
	 * run() delegates to UpstreamFreshnessService::runCheck().
	 */
	public function testRunDelegatesToService(): void {
		$time = $this->createMock(ITimeFactory::class);
		$service = $this->createMock(UpstreamFreshnessService::class);
		$service->expects($this->once())->method('runCheck');
		$logger = $this->createMock(LoggerInterface::class);

		$job = new UpstreamFreshnessJob($time, $service, $logger);

		$this->invokeRun($job);
	}//end testRunDelegatesToService()

	/**
	 * A service-level throw does not escape run() — belt and braces on top
	 * of the service's own catch-all (defence in depth, never relied upon
	 * alone: UpstreamFreshnessService::runCheck() already never throws).
	 */
	public function testServiceThrowDoesNotEscapeRun(): void {
		$time = $this->createMock(ITimeFactory::class);
		$service = $this->createMock(UpstreamFreshnessService::class);
		$service->method('runCheck')->willThrowException(new \RuntimeException('unexpected'));
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->atLeastOnce())->method('info');

		$job = new UpstreamFreshnessJob($time, $service, $logger);

		// Must not throw.
		$this->invokeRun($job);
		$this->addToAssertionCount(1);
	}//end testServiceThrowDoesNotEscapeRun()
}//end class
