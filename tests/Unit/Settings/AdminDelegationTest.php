<?php

/**
 * Unit tests for Settings\Admin's IDelegatedSettings surface: where the panel
 * appears, and which app config keys a delegated admin may write through it.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Settings;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\EmailThemingService;
use OCA\Thematiq\Service\ThemePreviewService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Settings\IDelegatedSettings;
use PHPUnit\Framework\TestCase;

/**
 * `getAuthorizedAppConfig()` is an ALLOWLIST, and it is the only thing standing
 * between a delegated admin — someone granted this settings section without
 * being a full admin — and the rest of this app's configuration. Two mistakes
 * are possible in it and neither shows up in the UI:
 *
 *  - a key the panel writes but the list omits: the delegated admin's save is
 *    refused, while a full admin's identical save succeeds;
 *  - a key the list carries that the panel does not write: a delegated admin
 *    can reach configuration this section was never meant to expose.
 *
 * The second is the one this class pins hardest. The upstream-freshness keys
 * are the live example: two of them are admin-initiated (the opt-in toggle and
 * the dismissal) and belong here, while the background job's own ETag,
 * head-SHA, checked-at and notices keys are written by the job and must never
 * be writable through a delegated-admin surface. That distinction is invisible
 * from the panel, and reversing it would change nothing an admin could see.
 *
 * The placement methods are asserted too, but plainly: they decide that the
 * panel lands in Nextcloud's own Theming section rather than a section of its
 * own, which is a product decision that should not change silently.
 *
 * @spec openspec/specs/admin-settings/spec.md
 * @spec openspec/specs/per-group-theming/spec.md
 * @spec openspec/specs/upstream-freshness/spec.md
 */
class AdminDelegationTest extends TestCase {

	/**
	 * Build an Admin instance; none of these tests reads collaborators.
	 *
	 * @return Admin The settings class under test.
	 */
	private function admin(): Admin {
		return new Admin(
			$this->createMock(IConfig::class),
			$this->createMock(IL10N::class),
			$this->createMock(TokenSetService::class),
			$this->createMock(EmailThemingService::class),
			$this->createMock(ThemePreviewService::class),
			$this->createMock(IUserSession::class),
			$this->createMock(DesignSystemService::class),
			$this->createMock(IInitialState::class),
			$this->createMock(IRequest::class)
		);
	}//end admin()

	/**
	 * The panel is a delegated setting at all — the interface is what makes
	 * the "grant this section to a group" flow offer it.
	 *
	 * @spec openspec/specs/admin-settings/spec.md
	 */
	public function testThePanelIsADelegatedSetting(): void {
		$this->assertInstanceOf(IDelegatedSettings::class, $this->admin());
	}//end testThePanelIsADelegatedSetting()

	/**
	 * The panel lives inside Nextcloud's own Theming section rather than
	 * claiming one of its own, and sorts after core's entries.
	 *
	 * @spec openspec/specs/admin-settings/spec.md
	 */
	public function testThePanelSitsInTheThemingSection(): void {
		$admin = $this->admin();

		$this->assertSame('theming', $admin->getSection());
		$this->assertSame(50, $admin->getPriority());
		// Null, not '': a name would render a second heading under the
		// section's own, which is what an empty string does NOT do.
		$this->assertNull($admin->getName());
	}//end testThePanelSitsInTheThemingSection()

	/**
	 * The allowlist is scoped to this app and nothing else — a second app id
	 * here would hand a delegated admin another app's configuration.
	 *
	 * @spec openspec/specs/admin-settings/spec.md
	 */
	public function testTheAllowlistCoversThisAppOnly(): void {
		$authorized = $this->admin()->getAuthorizedAppConfig();

		$this->assertSame([Application::APP_ID], array_keys($authorized));
		$this->assertNotEmpty($authorized[Application::APP_ID]);
	}//end testTheAllowlistCoversThisAppOnly()

	/**
	 * Every key the admin panel actually writes is in the allowlist; a missing
	 * one refuses a delegated admin's save while a full admin's identical save
	 * succeeds.
	 *
	 * @param string $key The app config key the panel writes.
	 *
	 * @dataProvider writableKeyProvider
	 *
	 * @spec openspec/specs/admin-settings/spec.md
	 */
	public function testEveryKeyThePanelWritesIsAuthorized(string $key): void {
		$this->assertContains(
			'/' . $key . '/',
			$this->admin()->getAuthorizedAppConfig()[Application::APP_ID]
		);
	}//end testEveryKeyThePanelWritesIsAuthorized()

	/**
	 * The app config keys the admin panel writes.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function writableKeyProvider(): array {
		$keys = [
			'token_set',
			'hide_slogan',
			'show_menu_labels',
			'dark_variants',
			'marianne_enabled',
			'disabled_apps',
			'group_token_sets',
			'upstream_freshness_enabled',
			'upstream_freshness_dismissed',
			'email_footer_org_name',
			'email_footer_accessibility_url',
			'email_footer_privacy_url',
		];

		return array_combine($keys, array_map(static fn (string $key): array => [$key], $keys));
	}//end writableKeyProvider()

	/**
	 * The upstream-freshness keys the background job owns are NOT delegable.
	 * They are written by the job, read by the panel, and a delegated admin
	 * who could write them could make the freshness notice claim anything.
	 *
	 * @param string $key The job-internal app config key.
	 *
	 * @dataProvider jobOwnedKeyProvider
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	public function testJobOwnedKeysAreNotDelegable(string $key): void {
		$this->assertNotContains(
			'/' . $key . '/',
			$this->admin()->getAuthorizedAppConfig()[Application::APP_ID]
		);
	}//end testJobOwnedKeysAreNotDelegable()

	/**
	 * App config keys written by the upstream-freshness background job.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function jobOwnedKeyProvider(): array {
		$keys = [
			'upstream_freshness_etag',
			'upstream_freshness_head_sha',
			'upstream_freshness_checked_at',
			'upstream_freshness_notices',
		];

		return array_combine($keys, array_map(static fn (string $key): array => [$key], $keys));
	}//end jobOwnedKeyProvider()
}//end class
