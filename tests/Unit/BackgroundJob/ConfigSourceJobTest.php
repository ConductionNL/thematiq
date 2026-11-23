<?php

/**
 * Tests for ConfigSourceJob.
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
 * @spec openspec/specs/theme-as-code/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\BackgroundJob;

use OCA\Thematiq\BackgroundJob\ConfigSourceJob;
use OCA\Thematiq\Service\ConfigSourceService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use RuntimeException;

/**
 * The job runs every five minutes, applies the package when it changed, and
 * logs a failure instead of throwing it.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */
final class ConfigSourceJobTest extends TestCase {

	/**
	 * Run the protected run() method.
	 *
	 * @param ConfigSourceJob $job The job.
	 *
	 * @return void
	 */
	private function runJob(ConfigSourceJob $job): void {
		(new ReflectionMethod($job, 'run'))->invoke($job, null);
	}//end runJob()

	/**
	 * The interval is five minutes.
	 *
	 * @return void
	 */
	public function testRunsEveryFiveMinutes(): void {
		$job = new ConfigSourceJob(
			time: $this->createMock(originalClassName: ITimeFactory::class),
			source: $this->createMock(originalClassName: ConfigSourceService::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);

		$this->assertSame(expected: 300, actual: ConfigSourceJob::INTERVAL_SECONDS);
		$this->assertSame(expected: ConfigSourceJob::INTERVAL_SECONDS, actual: $job->getInterval());
	}//end testRunsEveryFiveMinutes()

	/**
	 * A run applies the package only when it changed, without forcing it.
	 *
	 * @return void
	 */
	public function testRunAppliesThePackageWhenItChanged(): void {
		$source = $this->createMock(originalClassName: ConfigSourceService::class);
		$source->expects($this->once())->method('applyIfChanged')->with()->willReturn(['status' => 'unchanged']);
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->never())->method('error');

		$this->runJob(job: new ConfigSourceJob(time: $this->createMock(originalClassName: ITimeFactory::class), source: $source, logger: $logger));
	}//end testRunAppliesThePackageWhenItChanged()

	/**
	 * A failure is logged with its exception and does not escape the run.
	 *
	 * @return void
	 */
	public function testRunLogsAFailureInsteadOfThrowing(): void {
		$failure = new RuntimeException('unreadable');
		$source = $this->createMock(originalClassName: ConfigSourceService::class);
		$source->method('applyIfChanged')->willThrowException($failure);
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('error')->with(
			'thematiq ConfigSourceJob failed: unreadable',
			['exception' => $failure]
		);

		$this->runJob(job: new ConfigSourceJob(time: $this->createMock(originalClassName: ITimeFactory::class), source: $source, logger: $logger));
	}//end testRunLogsAFailureInsteadOfThrowing()
}//end class
