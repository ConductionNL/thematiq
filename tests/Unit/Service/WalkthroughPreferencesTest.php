<?php

/**
 * Tests for WalkthroughPreferences.
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
 * @spec openspec/changes/apps-menu-walkthrough/specs/apps-menu-walkthrough/spec.md#requirement-finishing-or-closing-records-the-seen-version-req-tqwt-004
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\WalkthroughPreferences;
use OCP\App\IAppManager;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

/**
 * Over an in-memory user config.
 *
 * @spec openspec/changes/apps-menu-walkthrough/specs/apps-menu-walkthrough/spec.md#requirement-finishing-or-closing-records-the-seen-version-req-tqwt-004
 */
final class WalkthroughPreferencesTest extends TestCase {

	/**
	 * The stored user values, by user id and key joined with a slash.
	 *
	 * @var array<string, string>
	 */
	private array $stored = [];

	/**
	 * The preferences over the in-memory config, with an installed version.
	 *
	 * @param string $installed The installed thematiq version.
	 *
	 * @return WalkthroughPreferences
	 */
	private function preferences(string $installed = '1.2.49'): WalkthroughPreferences {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			fn (string $uid, string $app, string $key, $default = '') => ($this->stored[$uid . '/' . $key] ?? $default)
		);
		$config->method('setUserValue')->willReturnCallback(function (string $uid, string $app, string $key, $value): void {
			$this->assertSame(expected: 'thematiq', actual: $app);
			$this->stored[$uid . '/' . $key] = (string)$value;
		});
		$config->method('deleteUserValue')->willReturnCallback(function (string $uid, string $app, string $key): void {
			unset($this->stored[$uid . '/' . $key]);
		});
		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('getAppVersion')->willReturn($installed);

		return new WalkthroughPreferences(config: $config, appManager: $apps);
	}//end preferences()

	/**
	 * The two tour keys are stored as user values; an empty value removes one.
	 *
	 * @return void
	 */
	public function testTheTourKeysAreStoredPerPerson(): void {
		$preferences = $this->preferences();
		$this->assertNull(actual: $preferences->get(userId: 'anna', key: WalkthroughPreferences::SEEN));

		$this->assertSame(expected: '1.2.49', actual: $preferences->set(userId: 'anna', key: WalkthroughPreferences::SEEN, value: '1.2.49'));
		$this->assertSame(expected: '1.2.49', actual: $preferences->get(userId: 'anna', key: WalkthroughPreferences::SEEN));
		$this->assertNull(actual: $preferences->get(userId: 'bram', key: WalkthroughPreferences::SEEN));

		$preferences->set(userId: 'anna', key: WalkthroughPreferences::PROGRESS, value: '{"tourId":"apps-menu","stepId":"reorder","index":3}');
		$this->assertNull(actual: $preferences->set(userId: 'anna', key: WalkthroughPreferences::PROGRESS, value: ''));
		$this->assertSame(expected: ['anna/' . WalkthroughPreferences::SEEN => '1.2.49'], actual: $this->stored);
	}//end testTheTourKeysAreStoredPerPerson()

	/**
	 * Any other key is neither read nor written.
	 *
	 * @return void
	 */
	public function testOtherKeysAreRefused(): void {
		$preferences = $this->preferences();
		$this->assertFalse(condition: $preferences->serves(key: 'token_set'));
		$this->assertNull(actual: $preferences->set(userId: 'anna', key: 'token_set', value: 'rijkshuisstijl'));
		$this->assertNull(actual: $preferences->get(userId: 'anna', key: 'token_set'));
		$this->assertSame(expected: [], actual: $this->stored);
	}//end testOtherKeysAreRefused()

	/**
	 * A long value is cut to the most a tour key holds.
	 *
	 * @return void
	 */
	public function testALongValueIsCut(): void {
		$stored = $this->preferences()->set(userId: 'anna', key: WalkthroughPreferences::PROGRESS, value: str_repeat('x', 5000));
		$this->assertSame(expected: WalkthroughPreferences::MAX_LENGTH, actual: strlen((string)$stored));
	}//end testALongValueIsCut()

	/**
	 * Scenario: a nightly build does not hide the next release's steps. The version core drops
	 * the pre-release and build suffix.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/apps-menu-walkthrough/specs/apps-menu-walkthrough/spec.md#requirement-a-new-menu-feature-gets-its-own-step-after-an-upgrade-req-tqwt-005
	 */
	public function testTheVersionCoreDropsTheBuildSuffix(): void {
		$this->assertSame(expected: '1.3.0', actual: WalkthroughPreferences::versionCore(version: '1.3.0-unstable.20261201'));
		$this->assertSame(expected: '1.2.48', actual: WalkthroughPreferences::versionCore(version: '1.2.48-beta.20261124154415'));
		$this->assertSame(expected: '2.0.0', actual: WalkthroughPreferences::versionCore(version: 'v2'));
		$this->assertSame(expected: '1.4.0', actual: WalkthroughPreferences::versionCore(version: '1.4'));
		$this->assertSame(expected: '', actual: WalkthroughPreferences::versionCore(version: ''));
		$this->assertSame(expected: '', actual: WalkthroughPreferences::versionCore(version: 'never'));
	}//end testTheVersionCoreDropsTheBuildSuffix()

	/**
	 * The state gives the seen version and the installed version as cores, and the progress as stored.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/apps-menu-walkthrough/specs/apps-menu-walkthrough/spec.md#requirement-the-tour-starts-on-the-first-open-of-the-apps-menu-req-tqwt-002
	 */
	public function testTheStateCarriesVersionCores(): void {
		$this->stored['anna/' . WalkthroughPreferences::SEEN] = '1.3.0-unstable.20261201';
		$this->stored['anna/' . WalkthroughPreferences::PROGRESS] = '{"tourId":"apps-menu","stepId":"pin","index":4}';
		$preferences = $this->preferences(installed: '1.3.1-unstable.20261210');

		$this->assertSame(
			expected: ['seen' => '1.3.0', 'progress' => '{"tourId":"apps-menu","stepId":"pin","index":4}', 'appVersion' => '1.3.1'],
			actual: $preferences->state(userId: 'anna')
		);
		$this->assertSame(
			expected: ['seen' => '', 'progress' => '', 'appVersion' => '1.3.1'],
			actual: $preferences->state(userId: null)
		);
	}//end testTheStateCarriesVersionCores()
}//end class
