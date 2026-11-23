<?php

/**
 * Tests for ExternalAppsController.
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
 * @spec openspec/changes/apps-menu-ordered-list/specs/external-apps-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-the-external-apps-that-have-an-address
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\ExternalAppsController;
use OCA\Thematiq\Service\ExternalApps;
use OCA\Thematiq\Service\OpenProjectModules;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Over an in-memory app config.
 *
 * @spec openspec/changes/apps-menu-ordered-list/specs/external-apps-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-the-external-apps-that-have-an-address
 */
final class ExternalAppsControllerTest extends TestCase {

	/**
	 * The stored app values.
	 *
	 * @var array<string, string>
	 */
	private array $stored = [];

	/**
	 * The controller.
	 *
	 * @return ExternalAppsController
	 */
	private function controller(): ExternalAppsController {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->stored[$key] ?? $default));
		$config->method('setAppValue')->willReturnCallback(function (string $app, string $key, $value): void {
			$this->stored[$key] = (string)$value;
		});
		$config->method('deleteAppValue')->willReturnCallback(function (string $app, string $key): void {
			unset($this->stored[$key]);
		});
		$l = $this->createMock(originalClassName: IL10N::class);
		$l->method('t')->willReturnArgument(0);

		return new ExternalAppsController(
			appName: 'thematiq',
			request: $this->createMock(originalClassName: IRequest::class),
			externalApps: new ExternalApps(config: $config, openProject: new OpenProjectModules(config: $config)),
			l: $l
		);
	}//end controller()

	/**
	 * Saved addresses come back; a bad one answers 400 and stores nothing.
	 *
	 * @return void
	 */
	public function testAddressesAreSavedAndABadOneIsRefused(): void {
		$apps = $this->controller()->save(urls: ['chat' => 'https://chat.example.org'])->getData()['apps'];
		$this->assertSame(expected: 'https://chat.example.org', actual: array_column($apps, 'url', 'key')['chat']);

		$refused = $this->controller()->save(urls: ['chat' => 'javascript:alert(1)']);
		$this->assertSame(expected: 400, actual: $refused->getStatus());
		$this->assertSame(expected: ['chat'], actual: $refused->getData()['refused']);
		$this->assertSame(
			expected: 'https://chat.example.org',
			actual: array_column($this->controller()->show()->getData()['apps'], 'url', 'key')['chat'],
			message: 'a refused save changes nothing'
		);
	}//end testAddressesAreSavedAndABadOneIsRefused()

	/**
	 * OpenProject's own endpoint saves, reads and clears its address and modules.
	 *
	 * @return void
	 */
	public function testOpenProjectsAddressIsSavedReadAndCleared(): void {
		$this->assertSame(
			expected: ['url' => '', 'modules' => OpenProjectModules::DEFAULT, 'available' => array_keys(OpenProjectModules::MODULES)],
			actual: $this->controller()->openProject()->getData()
		);

		$saved = $this->controller()->saveOpenProject(url: 'https://op.example.org/', modules: 'boards,meetings,nope')->getData();
		$this->assertSame(expected: 'https://op.example.org', actual: $saved['url']);
		$this->assertSame(expected: ['boards', 'meetings'], actual: $saved['modules']);
		$this->assertSame(
			expected: ['boards', 'meetings'],
			actual: $this->controller()->saveOpenProject(url: 'https://op.example.org')->getData()['modules'],
			message: 'modules left out stay'
		);

		$this->assertSame(expected: 400, actual: $this->controller()->saveOpenProject(url: 'javascript:alert(1)')->getStatus());
		$this->assertSame(expected: 'https://op.example.org', actual: $this->stored['openproject_url'], message: 'a refused address changes nothing');

		$this->assertSame(expected: '', actual: $this->controller()->saveOpenProject(url: '')->getData()['url']);
		$this->assertArrayNotHasKey(key: 'openproject_url', array: $this->stored);
	}//end testOpenProjectsAddressIsSavedReadAndCleared()

	/**
	 * Scenario: only an administrator sets the addresses.
	 *
	 * @return void
	 */
	public function testEveryMethodIsAdminOnly(): void {
		foreach (['show', 'save', 'openProject', 'saveOpenProject'] as $method) {
			$attributes = (new ReflectionMethod(ExternalAppsController::class, $method))->getAttributes(AuthorizedAdminSetting::class);
			$this->assertCount(expectedCount: 1, haystack: $attributes, message: $method);
			$this->assertSame(expected: Admin::class, actual: $attributes[0]->getArguments()['settings']);
		}
	}//end testEveryMethodIsAdminOnly()
}//end class
