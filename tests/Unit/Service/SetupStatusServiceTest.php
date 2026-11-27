<?php

/**
 * Tests for the first-time setup status.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-setup-status-is-derived-from-the-saved-configuration-req-tqfs-002
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\SetupStatusService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

/**
 * Setup is due only on an instance nobody configured, and never once completed or skipped.
 */
class SetupStatusServiceTest extends TestCase {

	/**
	 * The status over a stored set of app values and a config.php source.
	 *
	 * @param array<string, string> $stored The app values of thematiq.
	 * @param string $source The `thematiq.config_source` system value.
	 *
	 * @return SetupStatusService The service.
	 */
	private function make(array $stored, string $source = ''): SetupStatusService {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppKeys')->willReturn(array_keys($stored));
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($stored[$key] ?? $default)
		);
		$config->method('getSystemValue')->willReturnMap([['thematiq.config_source', '', $source]]);

		return new SetupStatusService(config: $config);
	}//end make()

	/**
	 * A fresh install stores only bookkeeping values: setup is due.
	 *
	 * @return void
	 */
	public function testAFreshInstanceIsDue(): void {
		$service = $this->make(
			stored: ['installed_version' => '1.8.0', 'enabled' => 'yes', 'menu.externalAppsMigrated' => '1', 'menu.externalApps' => '[]']
		);

		$this->assertTrue(condition: $service->isDue());
		$status = $service->status();
		$this->assertSame(expected: '1.8.0', actual: $status['version']);
		$this->assertFalse(condition: $status['completed']);
		$this->assertFalse(condition: $status['skipped']);
		$this->assertFalse(condition: $status['configured']);
		$this->assertSame(
			expected: ['welcome', 'house-style', 'nextcloud-theme', 'login-page', 'apps-menu', 'done'],
			actual: array_keys($status['steps'])
		);
	}//end testAFreshInstanceIsDue()

	/**
	 * An upgrade from a version that was never configured stores nothing setup reads: due.
	 *
	 * @return void
	 */
	public function testAnUpgradeWithNothingStoredIsDue(): void {
		$this->assertTrue(condition: $this->make(stored: ['installed_version' => '1.7.2', 'audit_entries_total' => '3'])->isDue());
	}//end testAnUpgradeWithNothingStoredIsDue()

	/**
	 * A set stored by an earlier version means configured, so set up.
	 *
	 * @return void
	 */
	public function testAnInstanceConfiguredByAnEarlierVersionIsNotDue(): void {
		$service = $this->make(stored: ['token_set' => 'zuiddrecht']);

		$this->assertFalse(condition: $service->isDue());
		$this->assertTrue(condition: $service->status()['configured']);
		$this->assertSame(expected: 'zuiddrecht', actual: $service->status()['current']['tokenSet']);
	}//end testAnInstanceConfiguredByAnEarlierVersionIsNotDue()

	/**
	 * A menu default an administrator chose counts; an added external app counts too.
	 *
	 * @return void
	 */
	public function testAMenuDefaultCountsAsConfigured(): void {
		$this->assertFalse(condition: $this->make(stored: ['menu.columns' => '3'])->isDue());
		$this->assertFalse(condition: $this->make(stored: ['menu.externalApps' => '[{"id":"x"}]'])->isDue());
	}//end testAMenuDefaultCountsAsConfigured()

	/**
	 * Theme as code counts as configured.
	 *
	 * @return void
	 */
	public function testAConfigSourceCountsAsConfigured(): void {
		$this->assertFalse(condition: $this->make(stored: [], source: '/etc/thematiq/theme.json')->isDue());
	}//end testAConfigSourceCountsAsConfigured()

	/**
	 * Completed and skipped are their stored records.
	 *
	 * @return void
	 */
	public function testCompletedAndSkippedAreTheStoredRecords(): void {
		$completed = $this->make(stored: ['setup.completed' => '{"by":"admin","at":"2026-11-27T13:52:00Z"}']);
		$this->assertFalse(condition: $completed->isDue());
		$this->assertSame(expected: ['by' => 'admin', 'at' => '2026-11-27T13:52:00Z'], actual: $completed->status()['completed']);
		$this->assertTrue(condition: $completed->status()['steps']['done']['done']);

		$skipped = $this->make(stored: ['setup.skipped' => '{"by":"noor","at":"2026-11-27T14:00:00Z"}']);
		$this->assertFalse(condition: $skipped->isDue());
		$this->assertSame(expected: 'noor', actual: $skipped->status()['skipped']['by']);
	}//end testCompletedAndSkippedAreTheStoredRecords()

	/**
	 * Each step's done and the stored choices.
	 *
	 * @return void
	 */
	public function testEachStepsDoneAndTheCurrentChoices(): void {
		$service = $this->make(stored: [
			'token_set' => 'amsterdam',
			'core_theming_follows_set' => '1',
			'themed_contexts' => '["user","guest","public","error"]',
			'hide_slogan' => '1',
			'menu.style' => '"classic"',
		]);
		$status = $service->status();

		$this->assertSame(
			expected: [
				'tokenSet' => 'amsterdam',
				'nextcloudThemeFollows' => true,
				'loginStyle' => 'nextcloud',
				'hideSlogan' => true,
				'menu' => ['enabled' => false],
			],
			actual: $status['current']
		);
		$this->assertTrue(condition: $status['steps']['house-style']['done']);
		$this->assertTrue(condition: $status['steps']['nextcloud-theme']['done']);
		$this->assertTrue(condition: $status['steps']['login-page']['done']);
		$this->assertSame(expected: 'classic', actual: $status['steps']['apps-menu']['detail']);
		$this->assertFalse(condition: $status['steps']['done']['done']);
	}//end testEachStepsDoneAndTheCurrentChoices()
}//end class
