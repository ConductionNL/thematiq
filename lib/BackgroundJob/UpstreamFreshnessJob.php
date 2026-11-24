<?php

/**
 * NL Design Upstream Freshness Background Job.
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
 * @spec openspec/specs/upstream-freshness/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\BackgroundJob;

use OCA\Thematiq\Kernel\Contracts\UpstreamFreshnessServiceInterface;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * The app's first background job: a daily, time-insensitive check of whether
 * upstream (nl-design-system/themes) has moved past the revision installed
 * token sets were generated from. Delegates all logic to
 * {@see UpstreamFreshnessService}; this class only wires the schedule and
 * belt-and-braces failure containment so a service-level throw (should one
 * ever escape the service's own catch-all) still cannot break cron.
 *
 * @spec openspec/specs/upstream-freshness/spec.md
 */
class UpstreamFreshnessJob extends TimedJob {

	/**
	 * The one-day interval, in seconds.
	 *
	 * @var int
	 */
	private const INTERVAL_SECONDS = 86400;

	/**
	 * The freshness service the job delegates to.
	 *
	 * @var UpstreamFreshnessServiceInterface
	 */
	private UpstreamFreshnessServiceInterface $service;

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
	 * @param UpstreamFreshnessServiceInterface $service The freshness service.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(ITimeFactory $time, UpstreamFreshnessServiceInterface $service, LoggerInterface $logger) {
		parent::__construct(time: $time);
		$this->setInterval(seconds: self::INTERVAL_SECONDS);
		$this->setTimeSensitivity(sensitivity: self::TIME_INSENSITIVE);
		$this->service = $service;
		$this->logger = $logger;
	}//end __construct()

	/**
	 * Run the job: delegate to the service. Wrapped in its own try/catch —
	 * belt and braces on top of the service's own catch-all — so a job
	 * failure can never escape into cron processing.
	 *
	 * @param mixed $argument Unused; TimedJob does not pass an argument here.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) - required by the abstract Job::run() signature
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	#[\Override]
	protected function run($argument): void {
		try {
			$this->service->runCheck();
		} catch (\Throwable $e) {
			$this->logger->info(
				'nldesign UpstreamFreshnessJob failed: ' . $e->getMessage(),
				['exception' => $e]
			);
		}
	}//end run()
}//end class
