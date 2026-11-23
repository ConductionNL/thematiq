<?php

/**
 * Tests for ExternalApps.
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
 * @spec openspec/specs/external-apps-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-the-external-apps-from-menu-settings
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ExternalApps;
use OCA\Thematiq\Service\OpenProjectModules;
use OCP\IConfig;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

/**
 * Over an in-memory app config.
 *
 * @spec openspec/specs/external-apps-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-the-external-apps-from-menu-settings
 */
final class ExternalAppsTest extends TestCase {

	/**
	 * The stored app values.
	 *
	 * @var array<string, string>
	 */
	private array $stored = [];

	/**
	 * The service over the in-memory config.
	 *
	 * @return ExternalApps
	 */
	private function apps(): ExternalApps {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->stored[$key] ?? $default));
		$config->method('setAppValue')->willReturnCallback(function (string $app, string $key, $value): void {
			$this->stored[$key] = (string)$value;
		});
		$config->method('deleteAppValue')->willReturnCallback(function (string $app, string $key): void {
			unset($this->stored[$key]);
		});

		return new ExternalApps(config: $config, openProject: new OpenProjectModules(config: $config));
	}//end apps()

	/**
	 * A language that answers with the source text.
	 *
	 * @return IL10N
	 */
	private function l(): IL10N {
		$l = $this->createMock(originalClassName: IL10N::class);
		$l->method('t')->willReturnArgument(0);

		return $l;
	}//end l()

	/**
	 * Scenario: an administrator gives two addresses. Only those apps are listed.
	 *
	 * @return void
	 */
	public function testOnlyTheAppsWithAnAddressAreListed(): void {
		$this->assertNull(actual: $this->apps()->section(l: $this->l()), message: 'no address, no section');

		$this->assertSame(
			expected: [],
			actual: $this->apps()->save(
				urls: ['openproject' => 'https://op.example.org/', 'code' => ' https://code.example.org ', 'nope' => 'https://x.example.org']
			)
		);
		$section = $this->apps()->section(l: $this->l());

		$this->assertSame(expected: 'External apps', actual: $section['title']);
		$this->assertSame(
			expected: [
				'external-openproject-work_packages',
				'external-openproject-gantt',
				'external-openproject-boards',
				'external-openproject-meetings',
				'external-code',
			],
			actual: array_column($section['apps'], 'key')
		);
		$this->assertSame(
			expected: [
				'key' => 'external-code',
				'label' => 'Forgejo',
				'line' => 'Code',
				'url' => 'https://code.example.org',
				'icon' => ExternalApps::APPS['code']['icon'],
			],
			actual: $section['apps'][4]
		);
		$this->assertSame(expected: 'https://op.example.org/work_packages', actual: $section['apps'][0]['url']);
		$this->assertSame(expected: 'OpenProject', actual: $section['apps'][0]['line']);
		$this->assertSame(expected: 'https://op.example.org', actual: $this->stored['openproject_url'], message: 'OpenProject keeps its key');
		$this->assertSame(expected: '{"code":"https:\/\/code.example.org"}', actual: $this->stored[ExternalApps::CONFIG_KEY]);
	}//end testOnlyTheAppsWithAnAddressAreListed()

	/**
	 * Scenario: an address that is not one refuses the whole save.
	 *
	 * @return void
	 */
	public function testOneBadAddressRefusesTheWholeSave(): void {
		$this->apps()->save(urls: ['chat' => 'https://chat.example.org']);
		$before = $this->stored;

		$this->assertSame(
			expected: ['chat', 'video'],
			actual: $this->apps()->save(urls: ['chat' => 'javascript:alert(1)', 'mail' => 'https://mail.example.org', 'video' => 'ftp://meet.example.org'])
		);
		$this->assertSame(expected: $before, actual: $this->stored, message: 'nothing changed');
	}//end testOneBadAddressRefusesTheWholeSave()

	/**
	 * An empty address removes that app; keys left out stay.
	 *
	 * @return void
	 */
	public function testAnEmptyAddressRemovesThatAppOnly(): void {
		$this->apps()->save(urls: ['chat' => 'https://chat.example.org', 'wiki' => 'https://wiki.example.org', 'openproject' => 'https://op.example.org']);
		$this->apps()->save(urls: ['chat' => '']);
		$this->assertSame(expected: '{"wiki":"https:\/\/wiki.example.org"}', actual: $this->stored[ExternalApps::CONFIG_KEY]);
		$this->assertSame(expected: 'https://op.example.org', actual: $this->stored['openproject_url'], message: 'left out, kept');

		$this->apps()->save(urls: ['wiki' => '', 'openproject' => '']);
		$this->assertArrayNotHasKey(key: ExternalApps::CONFIG_KEY, array: $this->stored);
		$this->assertArrayNotHasKey(key: 'openproject_url', array: $this->stored);
		$this->assertNull(actual: $this->apps()->section(l: $this->l()));
	}//end testAnEmptyAddressRemovesThatAppOnly()

	/**
	 * Scenario: OpenProject keeps its address. A value set before this change is listed, and a
	 * stored address that is not http or https is never linked.
	 *
	 * @return void
	 */
	public function testOpenProjectKeepsItsKeys(): void {
		$this->stored['openproject_url'] = 'https://op.example.org';
		$this->stored['openproject_modules'] = 'boards';
		$this->stored[ExternalApps::CONFIG_KEY] = '{"mail":"javascript:alert(1)","video":"https://meet.example.org"}';

		$section = $this->apps()->section(l: $this->l());
		$this->assertSame(expected: ['external-openproject-boards', 'external-video'], actual: array_column($section['apps'], 'key'));
	}//end testOpenProjectKeepsItsKeys()

	/**
	 * The admin page gets every app, OpenProject first, with its stored address.
	 *
	 * @return void
	 */
	public function testTheAdminPageGetsEveryApp(): void {
		$this->apps()->save(urls: ['video' => 'https://meet.example.org']);
		$rows = $this->apps()->stored(l: $this->l());

		$this->assertSame(expected: ['openproject', 'mail', 'chat', 'wiki', 'code', 'video'], actual: array_column($rows, 'key'));
		$this->assertSame(
			expected: ['key' => 'video', 'name' => 'BigBlueButton', 'line' => 'Video meetings', 'url' => 'https://meet.example.org'],
			actual: $rows[5]
		);
		$this->assertSame(expected: '', actual: $rows[0]['url']);
	}//end testTheAdminPageGetsEveryApp()
}//end class
