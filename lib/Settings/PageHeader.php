<?php

/**
 * Thematiq page header of the Theming administration page.
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
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md#requirement-the-admin-page-is-drawn-as-tqtheming-req-aset-101
 */

declare(strict_types=1);

namespace OCA\Thematiq\Settings;

use OCA\Thematiq\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Settings\ISettings;

/**
 * The header TqTheming draws over the whole Theming page: the title, the
 * breadcrumb, the intro and the page actions (Preview in my session and
 * Documentation), and the buildiq square when Buildiq is enabled for the reader.
 *
 * Nextcloud lists the blocks of a section by priority and draws its own
 * theming form at 5, so the header is a block of its own at 1 and comes
 * first. js/admin.js binds the actions by id and points Documentation at the
 * docs of the chosen set's design system.
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md#requirement-the-admin-page-is-drawn-as-tqtheming-req-aset-101
 */
class PageHeader implements ISettings {

	/**
	 * The app ids Buildiq answers to: the current one, then the id it had before
	 * the rename (nextcloud-vue's useBuildiqEditAvailability checks the same two).
	 */
	private const BUILDIQ_APP_IDS = ['buildiq', 'openbuild'];

	/**
	 * Constructor.
	 *
	 * @param IAppManager   $appManager   Tells whether Buildiq is enabled for the reader.
	 * @param IUserSession  $userSession  The reader.
	 * @param IURLGenerator $urlGenerator Builds the square's link.
	 *
	 * @spec openspec/specs/admin-settings/spec.md#requirement-the-admin-page-is-drawn-as-tqtheming-req-aset-101
	 */
	public function __construct(
		private readonly IAppManager $appManager,
		private readonly IUserSession $userSession,
		private readonly IURLGenerator $urlGenerator,
	) {
	}//end __construct()

	/**
	 * The header.
	 *
	 * @return TemplateResponse The template.
	 *
	 * @spec openspec/specs/admin-settings/spec.md#requirement-the-admin-page-is-drawn-as-tqtheming-req-aset-101
	 */
	public function getForm(): TemplateResponse {
		return new TemplateResponse(
			Application::APP_ID,
			'settings/page-header',
			['buildiqHref' => $this->buildiqHref()]
		);
	}//end getForm()

	/**
	 * Where the buildiq square leads, or null when Buildiq is not enabled for
	 * the reader (the square is then left out, as everywhere in the fleet).
	 *
	 * Every board of this page draws the square (ADR-041, the in-app edit entry)
	 * between the secondary actions and the primary one. Apps built on
	 * nextcloud-vue get it from CnAdminSettingsShell, which links to the app's
	 * own page with `?cn_edit=1`; thematiq is a theme app without a page of
	 * its own, so the square opens Buildiq itself.
	 *
	 * @return string|null The link, or null.
	 *
	 * @spec openspec/specs/admin-settings/spec.md#requirement-the-admin-page-is-drawn-as-tqtheming-req-aset-101
	 */
	private function buildiqHref(): ?string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return null;
		}

		foreach (self::BUILDIQ_APP_IDS as $appId) {
			if ($this->appManager->isEnabledForUser($appId, $user) === true) {
				return $this->urlGenerator->linkTo('', 'index.php') . '/apps/' . $appId . '/';
			}
		}

		return null;
	}//end buildiqHref()

	/**
	 * The Theming section, where thematiq's page lives.
	 *
	 * @return string The section id.
	 *
	 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md#requirement-the-admin-page-is-drawn-as-tqtheming-req-aset-101
	 */
	public function getSection(): string {
		return 'theming';
	}//end getSection()

	/**
	 * Before Nextcloud's own theming form (5) and thematiq's page (50).
	 *
	 * @return int The priority.
	 *
	 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md#requirement-the-admin-page-is-drawn-as-tqtheming-req-aset-101
	 */
	public function getPriority(): int {
		return 1;
	}//end getPriority()
}//end class
