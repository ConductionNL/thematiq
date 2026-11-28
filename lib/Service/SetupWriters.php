<?php

/**
 * The services the first-time setup writes through.
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

use OCA\Thematiq\Kernel\Contracts\TokenSetLicenceServiceInterface;

/**
 * The write paths the setup's `apply` shares with the settings page: the active set, the licence
 * confirmation, Nextcloud's theme and the top bar.
 *
 * @spec openspec/specs/first-time-setup/spec.md#requirement-nothing-changes-until-apply-req-tqfs-003
 */
final class SetupWriters {

	/**
	 * Constructor.
	 *
	 * @param ActiveTokenSetService $active The one write path for the active set.
	 * @param TokenSetLicenceServiceInterface $licences Stores a licence confirmation.
	 * @param ScheduledCoreThemingSync $coreSync Applies a set to Nextcloud's theme (`--sync-core`).
	 * @param LayoutOptionsService $layout The top bar style.
	 */
	public function __construct(
		public readonly ActiveTokenSetService $active,
		public readonly TokenSetLicenceServiceInterface $licences,
		public readonly ScheduledCoreThemingSync $coreSync,
		public readonly LayoutOptionsService $layout,
	) {
	}//end __construct()
}//end class
