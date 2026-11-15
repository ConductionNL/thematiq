<?php

/**
 * The collaborators a scheduled switch runs with.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Service
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/scheduled-switch/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * The audit trail, the app config for the last run and cron mode, the clock and the logger.
 *
 * @spec openspec/specs/scheduled-switch/spec.md
 */
final class ScheduledSwitchRuntime {

	/**
	 * Constructor.
	 *
	 * @param ThemingAuditService $auditService Records a failed switch.
	 * @param IConfig $config Last run and cron mode.
	 * @param ITimeFactory $time The clock.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md
	 */
	public function __construct(
		public readonly ThemingAuditService $auditService,
		public readonly IConfig $config,
		public readonly ITimeFactory $time,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
