<?php

/**
 * Tests for AppMenuPins and AppMenuKeys.
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
 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-pins/spec.md#requirement-pins-are-stored-for-the-person-on-the-server
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\AppMenuKeys;
use OCA\Thematiq\Service\AppMenuPins;
use OCA\Thematiq\Service\ExternalApps;
use OCA\Thematiq\Service\OpenProjectModules;
use OCP\IConfig;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;

/**
 * Over an in-memory user config.
 *
 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-pins/spec.md#requirement-pins-are-stored-for-the-person-on-the-server
 */
final class AppMenuPinsTest extends TestCase {

	/**
	 * The stored values, by user.
	 *
	 * @var array<string, string>
	 */
	private array $stored = [];

	/**
	 * A config that keeps user values in $stored.
	 *
	 * @return IConfig
	 */
	private function config(): IConfig {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('setUserValue')->willReturnCallback(function (string $user, string $app, string $key, $value): void {
			$this->stored[$user] = (string)$value;
		});
		$config->method('deleteUserValue')->willReturnCallback(function (string $user): void {
			unset($this->stored[$user]);
		});
		$config->method('getUserValue')->willReturnCallback(
			fn (string $user, string $app, string $key, $default = '') => ($this->stored[$user] ?? $default)
		);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, $default = '') => ([ExternalApps::CONFIG_KEY => '{"code":"https://code.example.org"}'][$key] ?? $default)
		);

		return $config;
	}//end config()

	/**
	 * Scenario: what may not be pinned is dropped. Unknown, malformed and repeated keys go, and at
	 * most five stay.
	 *
	 * @return void
	 */
	public function testOnlyKnownKeysAreStoredAndAtMostFive(): void {
		$pins = new AppMenuPins(config: $this->config());
		$known = ['files', 'deck', 'mail', 'talk', 'notes', 'calendar', 'external-code'];
		$stored = $pins->save(
			userId: 'anna',
			keys: ['files', 'Files', 'files', 'admin_audit', '<b>', 'external-code', 'deck', 'mail', 'talk', 'notes', 'calendar'],
			known: $known
		);

		$this->assertSame(expected: ['files', 'external-code', 'deck', 'mail', 'talk'], actual: $stored);
		$this->assertSame(expected: $stored, actual: $pins->get(userId: 'anna'));
	}//end testOnlyKnownKeysAreStoredAndAtMostFive()

	/**
	 * An empty list removes the value, and a broken value reads as none.
	 *
	 * @return void
	 */
	public function testAnEmptyListRemovesTheValue(): void {
		$pins = new AppMenuPins(config: $this->config());
		$pins->save(userId: 'anna', keys: ['files'], known: ['files']);
		$this->assertSame(expected: [], actual: $pins->save(userId: 'anna', keys: [], known: ['files']));
		$this->assertSame(expected: [], actual: $this->stored);

		$this->stored['bob'] = '{not json';
		$this->assertSame(expected: [], actual: $pins->get(userId: 'bob'));
	}//end testAnEmptyListRemovesTheValue()

	/**
	 * The known keys are the navigation's ids, the app ids in their addresses, and the external
	 * apps' keys.
	 *
	 * @return void
	 */
	public function testTheKnownKeysComeFromTheNavigationAndTheExternalApps(): void {
		$config = $this->config();
		$navigation = $this->createMock(originalClassName: INavigationManager::class);
		$navigation->method('getAll')->with(INavigationManager::TYPE_APPS)->willReturn(
			[
				['id' => 'files', 'href' => '/index.php/apps/files/'],
				['id' => 'talk', 'href' => '/index.php/apps/spreed/'],
				['id' => '', 'href' => 'https://example.org/'],
			]
		);
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$factory = $this->createMock(originalClassName: IFactory::class);
		$factory->method('get')->willReturn($l10n);
		$keys = new AppMenuKeys(
			navigation: $navigation,
			externalApps: new ExternalApps(config: $config, openProject: new OpenProjectModules(config: $config)),
			l10nFactory: $factory
		);

		$this->assertSame(expected: ['files', 'talk', 'spreed', 'external-code'], actual: $keys->known());
	}//end testTheKnownKeysComeFromTheNavigationAndTheExternalApps()
}//end class
