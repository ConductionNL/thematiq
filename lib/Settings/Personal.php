<?php

/**
 * Thematiq personal settings: the house style of the user's delegated groups.
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
 * @spec openspec/specs/per-group-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Settings;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\DelegatedGroupThemingService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IUserSession;
use OCP\Settings\ISettings;

/**
 * "House style of my groups", in the personal Appearance and accessibility
 * section. Shown only to a subadmin of at least one delegated group:
 * {@see getSection()} answers null for everyone else, which is how Nextcloud
 * hides a setting per user (a personal section itself cannot be hidden).
 *
 * @spec openspec/specs/per-group-theming/spec.md
 */
class Personal implements ISettings {

	/**
	 * The personal section the block lives in (Nextcloud's Appearance and accessibility).
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
	 * The block; js/personal-group-house-style.js fills it.
	 *
	 * @return TemplateResponse The template.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function getForm(): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'settings/personal', []);
	}//end getForm()

	/**
	 * The section, or null to hide the block from users without a delegated group.
	 *
	 * @return string|null The section id.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function getSection(): ?string {
		$user = $this->userSession->getUser();
		if ($user === null || $this->delegation->hasDelegatedGroups(uid: $user->getUID()) === false) {
			return null;
		}

		return self::SECTION;
	}//end getSection()

	/**
	 * The priority within the section.
	 *
	 * @return int The priority.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function getPriority(): int {
		return 90;
	}//end getPriority()
}//end class
