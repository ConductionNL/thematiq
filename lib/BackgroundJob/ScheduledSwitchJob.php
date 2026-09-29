<?php

/**
 * Thematiq Scheduled Switch Background Job.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  BackgroundJob
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/changes/apply-scheduled-theme-switch/specs/scheduled-switch/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\BackgroundJob;

use OCA\Thematiq\Service\ScheduledSwitchService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Applies planned token set switches whose start or end has passed. Runs
 * every five minutes and is time sensitive, so a campaign look starts within
 * minutes of its planned time when cron runs. All logic lives in
 * {@see ScheduledSwitchService::runDue()}; a throw is logged, never passed
 * on to cron.
 *
 * @spec openspec/changes/apply-scheduled-theme-switch/specs/scheduled-switch/spec.md
 */
class ScheduledSwitchJob extends TimedJob {

	/**
	 * The five-minute interval, in seconds.
	 *
	 * @var int
	 */
	private const INTERVAL_SECONDS = 300;

	/**
	 * The planner the job delegates to.
	 *
	 * @var ScheduledSwitchService
	 */
	private ScheduledSwitchService $service;

	/**
	 * The logger for the belt-and-braces catch.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @param ITimeFactory $time The time factory required by the parent Job class.
	 * @param ScheduledSwitchService $service The planner.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(ITimeFactory $time, ScheduledSwitchService $service, LoggerInterface $logger) {
		parent::__construct(time: $time);
		$this->setInterval(seconds: self::INTERVAL_SECONDS);
		$this->setTimeSensitivity(sensitivity: self::TIME_SENSITIVE);
		$this->service = $service;
		$this->logger = $logger;
	}//end __construct()

	/**
	 * Run the job: apply due switches. A throw is logged, never passed on.
	 *
	 * @param mixed $argument Unused; TimedJob does not pass an argument here.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) - required by the abstract Job::run() signature
	 *
	 * @spec openspec/changes/apply-scheduled-theme-switch/specs/scheduled-switch/spec.md
	 */
	protected function run($argument): void {
		try {
			$this->service->runDue();
		} catch (\Throwable $e) {
			$this->logger->error(
				'thematiq ScheduledSwitchJob failed: ' . $e->getMessage(),
				['exception' => $e]
			);
		}
	}//end run()
}//end class
