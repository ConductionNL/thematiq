<?php

/**
 * The collaborators the settings endpoints read, write and record settings through.
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
 * @spec openspec/specs/token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\IConfig;

/**
 * The app config, the audit trail, the per-app theming and the group theming mapping.
 *
 * @spec openspec/specs/token-sets/spec.md
 */
final class SettingsConfigServices {

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The config service.
	 * @param ThemingAuditService $auditService The theming audit trail service.
	 * @param AppThemingService $appThemingService The per-app theming service.
	 * @param GroupThemingService $groupThemingService The group theming mapping/resolution service.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	public function __construct(
		public readonly IConfig $config,
		public readonly ThemingAuditService $auditService,
		public readonly AppThemingService $appThemingService,
		public readonly GroupThemingService $groupThemingService,
	) {
	}//end __construct()
}//end class
