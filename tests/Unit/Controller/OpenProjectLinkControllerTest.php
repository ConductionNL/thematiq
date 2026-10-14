<?php

/**
 * Tests for OpenProjectLinkController.
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
 * @spec openspec/changes/openproject-in-the-app-menu/specs/openproject-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-openprojects-modules
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\OpenProjectLinkController;
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
 * @spec openspec/changes/openproject-in-the-app-menu/specs/openproject-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-openprojects-modules
 */
final class OpenProjectLinkControllerTest extends TestCase {

	/**
	 * The stored values.
	 *
	 * @var array<string, string>
	 */
	private array $stored = [];

	/**
	 * The controller.
	 *
	 * @return OpenProjectLinkController
	 */
	private function controller(): OpenProjectLinkController {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->stored[$key] ?? $default));
		$config->method('setAppValue')->willReturnCallback(function (string $app, string $key, $value): void {
			$this->stored[$key] = (string)$value;
		});
		$config->method('deleteAppValue')->willReturnCallback(function (string $app, string $key): void {
			unset($this->stored[$key]);
		});
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);

		return new OpenProjectLinkController('thematiq', $this->createMock(IRequest::class), new OpenProjectModules($config), $l);
	}//end controller()

	/**
	 * Scenario: an administrator saves, reads and clears the address.
	 *
	 * @return void
	 */
	public function testTheAddressIsSavedReadAndCleared(): void {
		$this->assertSame(['url' => '', 'modules' => OpenProjectModules::DEFAULT, 'available' => array_keys(OpenProjectModules::MODULES)], $this->controller()->show()->getData());

		$saved = $this->controller()->save(url: 'https://op.example.org/', modules: 'boards,meetings,nope')->getData();
		$this->assertSame('https://op.example.org', $saved['url']);
		$this->assertSame(['boards', 'meetings'], $saved['modules']);

		$this->assertSame(['boards', 'meetings'], $this->controller()->save(url: 'https://op.example.org')->getData()['modules'], 'modules left out stay');

		$refused = $this->controller()->save(url: 'javascript:alert(1)');
		$this->assertSame(400, $refused->getStatus());
		$this->assertSame('https://op.example.org', $this->stored['openproject_url'], 'a refused address changes nothing');

		$this->assertSame('', $this->controller()->save(url: '')->getData()['url']);
		$this->assertArrayNotHasKey('openproject_url', $this->stored);
	}//end testTheAddressIsSavedReadAndCleared()

	/**
	 * Scenario: only an administrator reads or changes it.
	 *
	 * @return void
	 */
	public function testAdminOnly(): void {
		foreach (['show', 'save'] as $method) {
			$attributes = (new ReflectionMethod(OpenProjectLinkController::class, $method))->getAttributes(AuthorizedAdminSetting::class);
			$this->assertCount(1, $attributes, $method);
			$this->assertSame(Admin::class, $attributes[0]->getArguments()['settings']);
		}
	}//end testAdminOnly()
}//end class
