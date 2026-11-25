<?php

/**
 * Thematiq group house style in the personal Appearance section.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Settings
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Settings;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\DelegatedGroupThemingService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IUserSession;
use OCP\Settings\ISettings;

/**
 * "House style of my groups" in the personal Appearance and accessibility section, under
 * Nextcloud's own theme settings, as the TqPersoonlijk board draws it. The same block as
 * {@see GroupHouseStyle} and the same endpoints, which check the grant, the subadmin's
 * group and the allowed list on every call. It shows only to someone who is subadmin of
 * at least one delegated group: {@see getSection()} answers null for everyone else.
 */
class PersonalGroupHouseStyle implements ISettings {
	/**
	 * Nextcloud's personal Appearance and accessibility section.
	 *
	 * @var string
	 */
	public const SECTION = 'theming';

	/**
	 * Constructor.
	 *
	 * @param DelegatedGroupThemingService $delegation The delegation service.
	 * @param IUserSession $userSession The session.
	 */
	public function __construct(
		private readonly DelegatedGroupThemingService $delegation,
		private readonly IUserSession $userSession,
	) {
	}//end __construct()

	/**
	 * The block; js/admin-group-house-style.js fills it.
	 *
	 * @return TemplateResponse The template.
	 *
	 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
	 */
	public function getForm(): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'settings/group-house-style', ['personal' => true]);
	}//end getForm()

	/**
	 * The section, or null to hide the block from users without a delegated group.
	 *
	 * @return string|null The section id.
	 *
	 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
	 */
	public function getSection(): ?string {
		$user = $this->userSession->getUser();
		if ($user === null || $this->delegation->hasDelegatedGroups(uid: $user->getUID()) === false) {
			return null;
		}

		return self::SECTION;
	}//end getSection()

	/**
	 * The priority within the section: after Nextcloud's own theme settings (40).
	 *
	 * @return int The priority.
	 *
	 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
	 */
	public function getPriority(): int {
		return 60;
	}//end getPriority()
}//end class
