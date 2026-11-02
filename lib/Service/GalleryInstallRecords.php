<?php

/**
 * The collaborators that store and record an installed gallery theme.
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
 * @spec openspec/specs/theme-gallery/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;

/**
 * Custom set storage, the audit trail, the app config for the active set, and the clock.
 *
 * @spec openspec/specs/theme-gallery/spec.md
 */
final class GalleryInstallRecords {

	/**
	 * Constructor.
	 *
	 * @param CustomTokenSetService $customSets Custom set storage.
	 * @param ThemingAuditService $audit The audit trail.
	 * @param IConfig $config App config, for the active token set.
	 * @param ITimeFactory $time The clock.
	 *
	 * @spec openspec/specs/theme-gallery/spec.md
	 */
	public function __construct(
		public readonly CustomTokenSetService $customSets,
		public readonly ThemingAuditService $audit,
		public readonly IConfig $config,
		public readonly ITimeFactory $time,
	) {
	}//end __construct()
}//end class
