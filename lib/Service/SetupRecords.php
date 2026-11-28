<?php

/**
 * Where the first-time setup keeps its values and its trail.
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
 * @spec openspec/specs/first-time-setup/spec.md#requirement-nothing-changes-until-apply-req-tqfs-003
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\Kernel\Contracts\ThemingAuditServiceInterface;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;

/**
 * The app values, the theming audit trail and the clock the setup's actions write with.
 *
 * @spec openspec/specs/first-time-setup/spec.md#requirement-nothing-changes-until-apply-req-tqfs-003
 */
final class SetupRecords {

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The app values.
	 * @param ThemingAuditServiceInterface $audit The theming audit trail.
	 * @param ITimeFactory $time The clock.
	 */
	public function __construct(
		public readonly IConfig $config,
		public readonly ThemingAuditServiceInterface $audit,
		public readonly ITimeFactory $time,
	) {
	}//end __construct()
}//end class
