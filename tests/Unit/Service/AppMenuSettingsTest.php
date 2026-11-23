<?php

/**
 * Tests for AppMenuSettings and AppMenuExternalApp.
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
 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\Thematiq\Service\AppMenuExternalApp;
use OCA\Thematiq\Service\AppMenuSettings;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

/**
 * Over an in-memory app and user config.
 *
 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md
 */
final class AppMenuSettingsTest extends TestCase {

	/**
	 * The app values, by key.
	 *
	 * @var array<string, string>
	 */
	private array $app = [];

	/**
	 * The user values, by user and key.
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $user = [];

	/**
	 * The service over the two stores.
	 *
	 * @return AppMenuSettings
	 */
	private function settings(): AppMenuSettings {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->app[$key] ?? $default));
		$config->method('setAppValue')->willReturnCallback(function (string $app, string $key, $value): void {
			$this->app[$key] = (string)$value;
		});
		$config->method('deleteAppValue')->willReturnCallback(function (string $app, string $key): void {
			unset($this->app[$key]);
		});
		$config->method('getUserValue')->willReturnCallback(
			fn (string $user, string $app, string $key, $default = '') => ($this->user[$user][$key] ?? $default)
		);
		$config->method('setUserValue')->willReturnCallback(function (string $user, string $app, string $key, $value): void {
			$this->user[$user][$key] = (string)$value;
		});
		$config->method('deleteUserValue')->willReturnCallback(function (string $user, string $app, string $key): void {
			unset($this->user[$user][$key]);
		});

		return new AppMenuSettings(config: $config);
	}//end settings()

	/**
	 * An external app as sent.
	 *
	 * @param string $id The id.
	 * @param string $url The address.
	 *
	 * @return array<string, mixed>
	 */
	private static function app(string $id, string $url = 'https://intranet.example.org'): array {
		return ['id' => $id, 'title' => 'Intranet', 'payoff' => 'News', 'url' => $url, 'icon' => ['library' => 'news'], 'visible' => true, 'order' => 0];
	}//end app()

	/**
	 * Scenario: the person's personal keys win; everything else is the administrator's; the
	 * person's external apps follow the administrator's.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-admin-defaults-with-per-user-overrides
	 */
	public function testThePersonsOwnKeysWinAndTheirAppsFollowTheAdministrators(): void {
		$settings = $this->settings();
		$settings->saveDefaults(
			values: ['columns' => 3, 'shape' => 'square', 'order' => ['deck', 'files'], 'pinned' => ['files'], 'externalApps' => [self::app(id: 'adm')]],
			known: ['deck', 'files']
		);
		$settings->saveOverrides(userId: 'anna', values: ['pinned' => ['deck'], 'externalApps' => [self::app(id: 'own')]], known: ['deck', 'files']);

		$anna = $settings->effective(userId: 'anna');
		$this->assertSame(expected: 3, actual: $anna['columns']);
		$this->assertSame(expected: 'square', actual: $anna['shape']);
		$this->assertSame(expected: ['deck', 'files'], actual: $anna['order']);
		$this->assertSame(expected: ['deck'], actual: $anna['pinned']);
		$apps = array_map(static fn (array $app): array => [$app['id'], $app['own']], $anna['externalApps']);
		$this->assertSame(expected: [['adm', false], ['own', true]], actual: $apps);
		$this->assertSame(expected: '3', actual: $this->app['menu.columns']);

		$bob = $settings->effective(userId: 'bob');
		$this->assertSame(expected: ['files'], actual: $bob['pinned']);
		$this->assertCount(expectedCount: 1, haystack: $bob['externalApps']);
		$this->app = [];
		$this->assertSame(expected: 4, actual: $this->settings()->effective(userId: null)['columns']);
	}//end testThePersonsOwnKeysWinAndTheirAppsFollowTheAdministrators()

	/**
	 * Scenario: a person may not set a theming or configuration key, nor a value outside its rule.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-checked-before-they-are-stored
	 */
	public function testAPersonMaySetOnlyTheirOwnKeys(): void {
		$settings = $this->settings();
		foreach (['columns', 'style', 'shape', 'tiles'] as $key) {
			try {
				$settings->saveOverrides(userId: 'anna', values: ['pinned' => ['deck'], $key => AppMenuSettings::DEFAULTS[$key]], known: ['deck']);
				$this->fail(message: 'accepted ' . $key);
			} catch (InvalidArgumentException $e) {
				$this->assertSame(expected: $key, actual: $e->getMessage());
			}
		}

		$this->assertSame(expected: [], actual: $this->user);
	}//end testAPersonMaySetOnlyTheirOwnKeys()

	/**
	 * Scenario: a value that is not allowed refuses the whole save.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-checked-before-they-are-stored
	 */
	public function testAValueThatIsNotAllowedRefusesTheSave(): void {
		$settings = $this->settings();
		$refused = [
			['columns' => 6],
			['rows' => 0],
			['rowsAboveFold' => 'two'],
			['style' => 'fancy'],
			['iconSize' => 'medium'],
			['iconColour' => 'red'],
			['tiles' => 'yes'],
			['hidden' => 'deck'],
			['externalApps' => [self::app(id: 'a', url: 'http://intranet.example.org')]],
			['colour' => 'red'],
		];
		foreach ($refused as $values) {
			try {
				$settings->saveDefaults(values: ['shape' => 'square'] + $values, known: ['deck']);
				$this->fail(message: 'accepted ' . json_encode($values));
			} catch (InvalidArgumentException $e) {
				$this->assertSame(expected: (string)array_key_first($values), actual: $e->getMessage());
			}
		}

		$this->assertSame(expected: [], actual: $this->app);
	}//end testAValueThatIsNotAllowedRefusesTheSave()

	/**
	 * Unknown entries are dropped from the lists, at most five pins stay, and the order of the
	 * time before the settings is still read.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-checked-before-they-are-stored
	 */
	public function testListsKeepKnownEntriesAndTheOldOrderIsRead(): void {
		$settings = $this->settings();
		$stored = $settings->saveOverrides(
			userId: 'anna',
			values: ['pinned' => ['a', 'b', 'nope', 'c', 'd', 'e', 'f'], 'hidden' => ['b', 'admin_audit']],
			known: ['a', 'b', 'c', 'd', 'e', 'f']
		);
		$this->assertSame(expected: ['a', 'b', 'c', 'd', 'e'], actual: $stored['pinned']);
		$this->assertSame(expected: ['b'], actual: $stored['hidden']);

		$this->user['bob']['app_menu_order'] = '["deck","files"]';
		$this->assertSame(expected: ['deck', 'files'], actual: $settings->effective(userId: 'bob')['order']);
	}//end testListsKeepKnownEntriesAndTheOldOrderIsRead()

	/**
	 * Scenario: reset clears the person's keys, or for an administrator the defaults.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-reset-to-default
	 */
	public function testResetClearsTheScope(): void {
		$settings = $this->settings();
		$settings->saveDefaults(values: ['columns' => 3, 'pinned' => ['deck']], known: ['deck']);
		$settings->saveOverrides(userId: 'anna', values: ['pinned' => []], known: ['deck']);
		$this->user['anna']['app_menu_order'] = '["deck"]';

		$settings->resetOverrides(userId: 'anna');
		$this->assertSame(expected: [], actual: $this->user['anna']);
		$this->assertSame(expected: ['deck'], actual: $settings->effective(userId: 'anna')['pinned']);

		$settings->resetDefaults();
		$this->assertSame(expected: [], actual: $this->app);
		$this->assertSame(expected: 4, actual: $settings->effective(userId: 'anna')['columns']);
	}//end testResetClearsTheScope()

	/**
	 * Scenario: an external app needs a title, an https address with a host and a known icon.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-external-apps-are-checked-on-the-server
	 */
	public function testAnExternalAppIsChecked(): void {
		$this->assertNotNull(actual: AppMenuExternalApp::clean(app: self::app(id: 'ok'), index: 0));
		foreach (['http://x.org', 'javascript:alert(1)', 'https://', 'https://user:pw@x.org', 'https://x.org/a b', '//x.org'] as $url) {
			$this->assertNull(actual: AppMenuExternalApp::clean(app: self::app(id: 'a', url: $url), index: 0), message: $url);
		}

		$this->assertNull(actual: AppMenuExternalApp::clean(app: ['title' => ''] + self::app(id: 'a'), index: 0));
		$this->assertNull(actual: AppMenuExternalApp::clean(app: ['title' => '<b>x</b>'] + self::app(id: 'a'), index: 0));
		$this->assertNull(actual: AppMenuExternalApp::clean(app: ['icon' => ['library' => 'rocket']] + self::app(id: 'a'), index: 0));
		$this->assertNull(actual: AppMenuExternalApp::clean(app: ['icon' => ['upload' => '../x']] + self::app(id: 'a'), index: 0));
		$this->assertNotNull(actual: AppMenuExternalApp::clean(app: ['icon' => ['upload' => str_repeat('a', 32)]] + self::app(id: 'a'), index: 0));

		$made = AppMenuExternalApp::clean(app: ['id' => ''] + self::app(id: 'x'), index: 3);
		$this->assertMatchesRegularExpression(pattern: '/^[a-f0-9]{12}$/', string: (string)$made['id']);
		$this->assertNull(actual: AppMenuExternalApp::cleanList(apps: [self::app(id: 'same'), self::app(id: 'same')]));
		$this->assertSame(expected: 'external-ok', actual: AppMenuExternalApp::key(app: ['id' => 'ok']));
	}//end testAnExternalAppIsChecked()
}//end class
