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
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

/**
 * The header TqTheming draws over the whole Theming page: the title, the
 * breadcrumb, the intro and the page actions (Preview in my session and
 * Documentation).
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
	 * The header.
	 *
	 * @return TemplateResponse The template.
	 *
	 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md#requirement-the-admin-page-is-drawn-as-tqtheming-req-aset-101
	 */
	public function getForm(): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'settings/page-header', []);
	}//end getForm()

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
