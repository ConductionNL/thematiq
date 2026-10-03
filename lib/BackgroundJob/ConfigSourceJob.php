<?php

/**
 * Thematiq background job: apply the configured branding package when it changed.
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
 * @spec openspec/specs/theme-as-code/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\BackgroundJob;

use OCA\Thematiq\Service\ConfigSourceService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Every five minutes: apply `thematiq.config_source` when its content hash
 * changed. Reads a path on disk only; it never fetches anything.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */
class ConfigSourceJob extends TimedJob {

	/**
	 * Five minutes.
	 *
	 * @var int
	 */
	public const INTERVAL_SECONDS = 300;

	/**
	 * Constructor.
	 *
	 * @param ITimeFactory $time The clock.
	 * @param ConfigSourceService $source The configuration source service.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		ITimeFactory $time,
		private readonly ConfigSourceService $source,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(time: $time);
		$this->setInterval(seconds: self::INTERVAL_SECONDS);
	}//end __construct()

	/**
	 * Run the job.
	 *
	 * @param mixed $argument Unused.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) - TimedJob's signature.
	 */
	protected function run($argument): void {
		try {
			$this->source->applyIfChanged();
		} catch (\Throwable $e) {
			$this->logger->error('thematiq ConfigSourceJob failed: ' . $e->getMessage(), ['exception' => $e]);
		}
	}//end run()
}//end class
