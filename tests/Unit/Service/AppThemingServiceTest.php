<?php

/**
 * Unit tests for AppThemingService.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/archive/2026-06-14-per-app-theming-toggle/tasks.md#task-5.1
 * @spec openspec/changes/archive/2026-06-14-per-app-theming-toggle/tasks.md#task-5.2
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\AppThemingService;
use OCP\App\IAppManager;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AppThemingService.
 */
class AppThemingServiceTest extends TestCase {

	/**
	 * The config mock.
	 *
	 * @var IConfig
	 */
	private $config;

	/**
	 * The app manager mock.
	 *
	 * @var IAppManager
	 */
	private $appManager;

	/**
	 * The service under test.
	 *
	 * @var AppThemingService
	 */
	private AppThemingService $service;

	/**
	 * Set up mocks before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		// Mock the REAL OCP interface. It previously mocked a test-only
		// sub-interface that re-declared getEnabledApps() because "the OCP 31
		// stub no longer types" it — but the stub was right and the production
		// call was wrong: OC\App\AppManager has no getEnabledApps() before
		// Nextcloud 34. The mock was manufacturing the method under test, so
		// this suite passed on every version where the panel 500'd. Mocking
		// only what OCP actually declares is what makes that impossible.
		$this->appManager = $this->createMock(IAppManager::class);
		$this->service = new AppThemingService($this->config, $this->appManager);
	}//end setUp()

	/**
	 * Absent config returns an empty list (today's global theming).
	 */
	public function testGetDisabledAppsDefaultsToEmpty(): void {
		$this->config->method('getAppValue')->willReturn('[]');
		$this->assertSame([], $this->service->getDisabledApps());
	}//end testGetDisabledAppsDefaultsToEmpty()

	/**
	 * Malformed JSON degrades to an empty list, never an error.
	 */
	public function testGetDisabledAppsMalformedJsonIsEmpty(): void {
		$this->config->method('getAppValue')->willReturn('not-json{');
		$this->assertSame([], $this->service->getDisabledApps());
	}//end testGetDisabledAppsMalformedJsonIsEmpty()

	/**
	 * A stored list is read back, de-duplicated and string-filtered.
	 */
	public function testGetDisabledAppsReadsStoredList(): void {
		$this->config->method('getAppValue')->willReturn('["calendar","files","calendar"]');
		$this->assertSame(['calendar', 'files'], $this->service->getDisabledApps());
	}//end testGetDisabledAppsReadsStoredList()

	/**
	 * Unknown app ids are dropped on save (self-heal); known ids retained.
	 */
	public function testSetDisabledAppsDropsUnknownIds(): void {
		$this->appManager->method('isInstalled')->willReturnMap(
			[
				['files', true],
				['uninstalled-app', false],
			]
		);

		$captured = null;
		$this->config->expects($this->once())
			->method('setAppValue')
			->willReturnCallback(
				function ($app, $key, $value) use (&$captured) {
					$captured = $value;
				}
			);

		$this->service->setDisabledApps(['files', 'uninstalled-app']);
		$this->assertSame(['files'], json_decode((string)$captured, true));
	}//end testSetDisabledAppsDropsUnknownIds()

	/**
	 * Protected ids can never enter the exclusion list.
	 */
	public function testSetDisabledAppsDropsProtectedIds(): void {
		$this->appManager->method('isInstalled')->willReturn(true);

		$captured = null;
		$this->config->method('setAppValue')->willReturnCallback(
			function ($app, $key, $value) use (&$captured) {
				$captured = $value;
			}
		);

		$this->service->setDisabledApps(['calendar', 'thematiq', 'settings', 'theming']);
		$this->assertSame(['calendar'], json_decode((string)$captured, true));
	}//end testSetDisabledAppsDropsProtectedIds()

	/**
	 * Null/unresolved app id is always themed.
	 */
	public function testIsThemingDisabledForNullIsFalse(): void {
		$this->assertFalse($this->service->isThemingDisabledFor(null));
		$this->assertFalse($this->service->isThemingDisabledFor(''));
	}//end testIsThemingDisabledForNullIsFalse()

	/**
	 * An app in the exclusion list is reported as disabled.
	 */
	public function testIsThemingDisabledForExcludedApp(): void {
		$this->config->method('getAppValue')->willReturn('["calendar"]');
		$this->assertTrue($this->service->isThemingDisabledFor('calendar'));
		$this->assertFalse($this->service->isThemingDisabledFor('files'));
	}//end testIsThemingDisabledForExcludedApp()

	/**
	 * The resolver extracts the app id from app-page paths and only those.
	 *
	 * @dataProvider pathProvider
	 *
	 * @param string|null $pathInfo The request path info.
	 * @param string|null $expected The expected resolved app id.
	 */
	public function testResolveAppIdFromPath(?string $pathInfo, ?string $expected): void {
		$this->assertSame($expected, $this->service->resolveAppIdFromPath($pathInfo));
	}//end testResolveAppIdFromPath()

	/**
	 * Path resolution cases.
	 *
	 * @return array<string, array{0: string|null, 1: string|null}>
	 */
	public static function pathProvider(): array {
		return [
			'plain app path' => ['/apps/files/', 'files'],
			'app path no slash' => ['/apps/calendar', 'calendar'],
			'index.php prefix' => ['/index.php/apps/calendar/', 'calendar'],
			'underscore app id' => ['/apps/files_external/list', 'files_external'],
			'settings page' => ['/settings/admin/theming', null],
			'login page' => ['/login', null],
			'share link' => ['/s/abcd1234', null],
			'empty path' => ['', null],
			'null path' => [null, null],
		];
	}//end pathProvider()

	/**
	 * getThemableApps omits protected ids, sorts by name, sets themed flag.
	 */
	public function testGetThemableAppsListsEnabledAppsWithState(): void {
		$this->config->method('getAppValue')->willReturn('["calendar"]');
		$this->appManager->method('getInstalledApps')
			->willReturn(['files', 'calendar', 'thematiq', 'settings', 'theming']);
		$this->appManager->method('getAppInfo')->willReturnCallback(
			fn ($id) => ['name' => ucfirst($id)]
		);

		$apps = $this->service->getThemableApps();
		$ids = array_column($apps, 'id');

		// Protected ids excluded.
		$this->assertNotContains('nldesign', $ids);
		$this->assertNotContains('settings', $ids);
		$this->assertNotContains('theming', $ids);

		// Sorted by display name: Calendar before Files.
		$this->assertSame(['calendar', 'files'], $ids);

		// Themed flag reflects the exclusion list.
		$byId = array_column($apps, 'themed', 'id');
		$this->assertFalse($byId['calendar']);
		$this->assertTrue($byId['files']);
	}//end testGetThemableAppsListsEnabledAppsWithState()
}//end class
