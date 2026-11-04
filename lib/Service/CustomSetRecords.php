<?php

/**
 * The collaborators the custom set endpoints answer and record with.
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
 * @spec openspec/specs/custom-token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\IConfig;
use OCP\IL10N;

/**
 * The translations of every answer, the audit trail, the app config and Nextcloud's core theming, for undoing a deleted set's sync.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */
final class CustomSetRecords {

	/**
	 * Constructor.
	 *
	 * @param IL10N $l The localization service.
	 * @param ThemingAuditService $auditService The theming audit trail service.
	 * @param IConfig $config The config service.
	 * @param ThemingService $themingService Core theming, for undoing a deleted set's sync.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	public function __construct(
		public readonly IL10N $l,
		public readonly ThemingAuditService $auditService,
		public readonly IConfig $config,
		public readonly ThemingService $themingService,
	) {
	}//end __construct()
}//end class
