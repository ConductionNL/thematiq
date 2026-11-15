<?php

/**
 * Thematiq group house style settings.
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
 * @spec openspec/changes/group-house-style-admin/specs/per-group-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Settings;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\DelegatedGroupThemingService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\Settings\IDelegatedSettings;

/**
 * "House style of my groups", in the Theming section of the administration
 * settings: a group subadmin picks the house style of a delegated group.
 *
 * A delegated setting ({@see IDelegatedSettings}): an administrator can grant
 * it to a group under Administration privileges, and its members then see
 * this block, and only this block, of the Theming section. The block shows
 * only to someone who is subadmin of at least one delegated group:
 * {@see getSection()} answers null for everyone else. The endpoints behind
 * it check the grant and, on every call, the subadmin's group and the
 * allowed list.
 *
 * It replaces the block that lived in the personal Appearance settings
 * (Ruben, 11 October 2026): a person's own appearance is Nextcloud's.
 *
 * @spec openspec/changes/group-house-style-admin/specs/per-group-theming/spec.md
 */
class GroupHouseStyle implements IDelegatedSettings {

	/**
	 * The administration section the block lives in (Theming, beside the main page).
	 *
	 * @var string
	 */
	public const SECTION = 'theming';

	/**
	 * Constructor.
	 *
	 * @param DelegatedGroupThemingService $delegation The delegation service.
	 * @param IUserSession $userSession The session.
	 * @param IL10N $l10n The translator, for the name on Administration privileges.
	 */
	public function __construct(
		private readonly DelegatedGroupThemingService $delegation,
		private readonly IUserSession $userSession,
		private readonly IL10N $l10n,
	) {
	}//end __construct()

	/**
	 * The block; js/admin-group-house-style.js fills it.
	 *
	 * @return TemplateResponse The template.
	 *
	 * @spec openspec/changes/group-house-style-admin/specs/per-group-theming/spec.md#requirement-a-group-subadmin-chooses-the-house-style-of-a-delegated-group
	 */
	public function getForm(): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'settings/group-house-style', []);
	}//end getForm()

	/**
	 * The section, or null to hide the block from users without a delegated group.
	 *
	 * @return string|null The section id.
	 *
	 * @spec openspec/changes/group-house-style-admin/specs/per-group-theming/spec.md#requirement-a-group-subadmin-chooses-the-house-style-of-a-delegated-group
	 */
	public function getSection(): ?string {
		$user = $this->userSession->getUser();
		if ($user === null || $this->delegation->hasDelegatedGroups(uid: $user->getUID()) === false) {
			return null;
		}

		return self::SECTION;
	}//end getSection()

	/**
	 * The priority within the section: after thematiq's main page (50).
	 *
	 * @return int The priority.
	 *
	 * @spec openspec/changes/group-house-style-admin/specs/per-group-theming/spec.md#requirement-a-group-subadmin-chooses-the-house-style-of-a-delegated-group
	 */
	public function getPriority(): int {
		return 60;
	}//end getPriority()

	/**
	 * The name an administrator sees under Administration privileges.
	 *
	 * @return string|null The name.
	 *
	 * @spec openspec/changes/group-house-style-admin/specs/per-group-theming/spec.md#requirement-a-group-subadmin-chooses-the-house-style-of-a-delegated-group
	 */
	public function getName(): ?string {
		return $this->l10n->t('House style of my groups');
	}//end getName()

	/**
	 * No app config is written directly by a delegated user: the choice goes
	 * through MyGroupsController, which checks it.
	 *
	 * @return array<string, array<int, string>> Nothing.
	 *
	 * @spec openspec/changes/group-house-style-admin/specs/per-group-theming/spec.md#requirement-a-group-subadmin-chooses-the-house-style-of-a-delegated-group
	 */
	public function getAuthorizedAppConfig(): array {
		return [];
	}//end getAuthorizedAppConfig()
}//end class
