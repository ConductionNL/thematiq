<?php

/**
 * Who the first-time setup is for.
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
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-wizard-opens-for-an-administrator-on-the-first-nextcloud-page-while-setup-is-due-req-tqfs-001
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\IGroupManager;
use OCP\IUserSession;

/**
 * The signed-in user, and whether they are in the `admin` group. Delegated theming administrators
 * and subadmins are not.
 *
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-wizard-opens-for-an-administrator-on-the-first-nextcloud-page-while-setup-is-due-req-tqfs-001
 */
class SetupAudience {

	/**
	 * Constructor.
	 *
	 * @param IUserSession $userSession The signed-in user.
	 * @param IGroupManager $groupManager The admin group check.
	 */
	public function __construct(
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
	) {
	}//end __construct()

	/**
	 * Whether the signed-in user is in the `admin` group.
	 *
	 * @return bool True for an administrator.
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-wizard-opens-for-an-administrator-on-the-first-nextcloud-page-while-setup-is-due-req-tqfs-001
	 */
	public function isAdministrator(): bool {
		$uid = $this->userSession->getUser()?->getUID();

		return $uid !== null && $this->groupManager->isAdmin($uid) === true;
	}//end isAdministrator()
}//end class
