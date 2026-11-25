<?php

/**
 * Tests for House style of my groups in the personal Appearance section.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Tests
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Settings;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\DelegatedGroupThemingService;
use OCA\Thematiq\Settings\PersonalGroupHouseStyle;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * The block shows in Nextcloud's personal Appearance section, after its own theme settings,
 * only to a subadmin of a delegated group.
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
class PersonalGroupHouseStyleTest extends TestCase {

	/**
	 * The settings for a session with or without a user that has delegated groups.
	 *
	 * @param bool $loggedIn Whether a user is logged in.
	 * @param bool $delegated Whether that user is subadmin of a delegated group.
	 *
	 * @return PersonalGroupHouseStyle The settings.
	 */
	private function settings(bool $loggedIn, bool $delegated): PersonalGroupHouseStyle {
		$session = $this->createMock(originalClassName: IUserSession::class);
		$user = null;
		if ($loggedIn === true) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn('anna');
		}
		$session->method('getUser')->willReturn($user);
		$delegation = $this->createMock(originalClassName: DelegatedGroupThemingService::class);
		$delegation->method('hasDelegatedGroups')->with('anna')->willReturn($delegated);

		return new PersonalGroupHouseStyle(delegation: $delegation, userSession: $session);
	}//end settings()

	/**
	 * A subadmin of a delegated group finds it in the Appearance section, after Nextcloud's form.
	 *
	 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
	 *
	 * @return void
	 */
	public function testASubadminOfADelegatedGroupSeesItInAppearance(): void {
		$settings = $this->settings(loggedIn: true, delegated: true);

		$this->assertSame('theming', $settings->getSection());
		$this->assertGreaterThan(40, $settings->getPriority());
	}//end testASubadminOfADelegatedGroupSeesItInAppearance()

	/**
	 * Anyone else, and a request without a user, does not see it.
	 *
	 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
	 *
	 * @return void
	 */
	public function testEveryoneElseDoesNotSeeIt(): void {
		$this->assertNull($this->settings(loggedIn: true, delegated: false)->getSection());
		$this->assertNull($this->settings(loggedIn: false, delegated: false)->getSection());
	}//end testEveryoneElseDoesNotSeeIt()

	/**
	 * It draws the group house style block in its personal shape.
	 *
	 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
	 *
	 * @return void
	 */
	public function testTheFormIsTheBlockInItsPersonalShape(): void {
		$form = $this->settings(loggedIn: true, delegated: true)->getForm();

		$this->assertSame(Application::APP_ID, $form->getApp());
		$this->assertSame('settings/group-house-style', $form->getTemplateName());
		$this->assertSame(['personal' => true], $form->getParams());
	}//end testTheFormIsTheBlockInItsPersonalShape()
}//end class
