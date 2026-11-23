<?php

/**
 * Tests for the page header of the Theming page.
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
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md#requirement-the-admin-page-is-drawn-as-tqtheming-req-aset-101
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Settings;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Settings\PageHeader;
use PHPUnit\Framework\TestCase;

/**
 * The header is its own block in the Theming section, before Nextcloud's form.
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md#requirement-the-admin-page-is-drawn-as-tqtheming-req-aset-101
 */
class PageHeaderTest extends TestCase {

	/**
	 * It sits in the Theming section before Nextcloud's own form (priority 5).
	 *
	 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md#requirement-the-admin-page-is-drawn-as-tqtheming-req-aset-101
	 *
	 * @return void
	 */
	public function testComesFirstInTheThemingSection(): void {
		$header = new PageHeader();

		$this->assertSame(expected: 'theming', actual: $header->getSection());
		$this->assertLessThan(expected: 5, actual: $header->getPriority());
	}//end testComesFirstInTheThemingSection()

	/**
	 * It renders its own template.
	 *
	 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md#requirement-the-admin-page-is-drawn-as-tqtheming-req-aset-101
	 *
	 * @return void
	 */
	public function testRendersTheHeaderTemplate(): void {
		$form = (new PageHeader())->getForm();

		$this->assertSame(expected: Application::APP_ID, actual: $form->getApp());
		$this->assertSame(expected: 'settings/page-header', actual: $form->getTemplateName());
	}//end testRendersTheHeaderTemplate()

	/**
	 * The template keeps the ids js/admin.js binds and carries the board's words.
	 *
	 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md#requirement-the-admin-page-is-drawn-as-tqtheming-req-aset-101
	 *
	 * @return void
	 */
	public function testTemplateKeepsTheActionIds(): void {
		$template = (string)file_get_contents(__DIR__ . '/../../../templates/settings/page-header.php');

		$this->assertStringContainsString(needle: 'id="nldesign-preview-btn"', haystack: $template);
		$this->assertStringContainsString(needle: 'id="nldesign-doc-link"', haystack: $template);
		$this->assertStringContainsString(needle: 'Admin::DEFAULT_DOCUMENTATION_URL', haystack: $template);
		$this->assertStringContainsString(needle: "'Themes and appearance'", haystack: $template);
		$this->assertStringContainsString(needle: 'aria-current="page"', haystack: $template);
	}//end testTemplateKeepsTheActionIds()
}//end class
