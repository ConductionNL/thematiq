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
use OCP\App\IAppManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * The header is its own block in the Theming section, before Nextcloud's form.
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md#requirement-the-admin-page-is-drawn-as-tqtheming-req-aset-101
 */
class PageHeaderTest extends TestCase {

	/**
	 * A header whose reader has exactly the given apps enabled.
	 *
	 * @param string[] $enabled The enabled app ids.
	 * @param bool     $signedIn Whether a user is signed in.
	 *
	 * @return PageHeader The header.
	 */
	private function header(array $enabled = [], bool $signedIn = true): PageHeader {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForUser')->willReturnCallback(
			static fn (string $appId): bool => in_array($appId, $enabled, true)
		);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($signedIn === true ? $this->createMock(IUser::class) : null);
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkTo')->willReturn('/index.php');

		return new PageHeader($appManager, $session, $urls);
	}//end header()

	/**
	 * It sits in the Theming section before Nextcloud's own form (priority 5).
	 *
	 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md#requirement-the-admin-page-is-drawn-as-tqtheming-req-aset-101
	 *
	 * @return void
	 */
	public function testComesFirstInTheThemingSection(): void {
		$header = $this->header();

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
		$form = $this->header()->getForm();

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

	/**
	 * With Buildiq enabled the square links to Buildiq; the template draws it.
	 *
	 * @spec openspec/specs/admin-settings/spec.md#requirement-the-admin-page-is-drawn-as-tqtheming-req-aset-101
	 *
	 * @return void
	 */
	public function testBuildiqSquareLinksToBuildiq(): void {
		$params = $this->header(enabled: ['buildiq'])->getForm()->getParams();
		$this->assertSame(expected: '/index.php/apps/buildiq/', actual: $params['buildiqHref']);

		$template = (string)file_get_contents(__DIR__ . '/../../../templates/settings/page-header.php');
		$this->assertStringContainsString(needle: 'id="thematiq-buildiq-edit"', haystack: $template);
		$this->assertStringContainsString(needle: "'Edit in Buildiq'", haystack: $template);
	}//end testBuildiqSquareLinksToBuildiq()

	/**
	 * An install that still serves Buildiq's old app id gets the square too.
	 *
	 * @spec openspec/specs/admin-settings/spec.md#requirement-the-admin-page-is-drawn-as-tqtheming-req-aset-101
	 *
	 * @return void
	 */
	public function testBuildiqSquareFollowsTheOldAppId(): void {
		$params = $this->header(enabled: ['openbuild'])->getForm()->getParams();
		$this->assertSame(expected: '/index.php/apps/openbuild/', actual: $params['buildiqHref']);
	}//end testBuildiqSquareFollowsTheOldAppId()

	/**
	 * Without Buildiq, or without a reader, there is no square.
	 *
	 * @spec openspec/specs/admin-settings/spec.md#requirement-the-admin-page-is-drawn-as-tqtheming-req-aset-101
	 *
	 * @return void
	 */
	public function testNoBuildiqSquareWithoutBuildiq(): void {
		$this->assertNull($this->header()->getForm()->getParams()['buildiqHref']);
		$this->assertNull($this->header(enabled: ['buildiq'], signedIn: false)->getForm()->getParams()['buildiqHref']);
	}//end testNoBuildiqSquareWithoutBuildiq()
}//end class
