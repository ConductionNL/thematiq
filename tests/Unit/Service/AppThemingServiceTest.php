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
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(originalClassName: IConfig::class);
		// Mock the REAL OCP interface. It previously mocked a test-only
		// sub-interface that re-declared getEnabledApps() because "the OCP 31
		// stub no longer types" it — but the stub was right and the production
		// call was wrong: OC\App\AppManager has no getEnabledApps() before
		// Nextcloud 34. The mock was manufacturing the method under test, so
		// this suite passed on every version where the panel 500'd. Mocking
		// only what OCP actually declares is what makes that impossible.
		$this->appManager = $this->createMock(originalClassName: IAppManager::class);
		$this->service = new AppThemingService(config: $this->config, appManager: $this->appManager);
	}//end setUp()

	/**
	 * Absent config returns an empty list (today's global theming).
	 *
	 * @return void
	 */
	public function testGetDisabledAppsDefaultsToEmpty(): void {
		$this->config->method('getAppValue')->willReturn('[]');
		$this->assertSame(expected: [], actual: $this->service->getDisabledApps());
	}//end testGetDisabledAppsDefaultsToEmpty()

	/**
	 * Malformed JSON degrades to an empty list, never an error.
	 *
	 * @return void
	 */
	public function testGetDisabledAppsMalformedJsonIsEmpty(): void {
		$this->config->method('getAppValue')->willReturn('not-json{');
		$this->assertSame(expected: [], actual: $this->service->getDisabledApps());
	}//end testGetDisabledAppsMalformedJsonIsEmpty()

	/**
	 * A stored list is read back, de-duplicated and string-filtered.
	 *
	 * @return void
	 */
	public function testGetDisabledAppsReadsStoredList(): void {
		$this->config->method('getAppValue')->willReturn('["calendar","files","calendar"]');
		$this->assertSame(expected: ['calendar', 'files'], actual: $this->service->getDisabledApps());
	}//end testGetDisabledAppsReadsStoredList()

	/**
	 * Unknown app ids are dropped on save (self-heal); known ids retained.
	 *
	 * @return void
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
		$this->assertSame(expected: ['files'], actual: json_decode((string)$captured, true));
	}//end testSetDisabledAppsDropsUnknownIds()

	/**
	 * Protected ids can never enter the exclusion list.
	 *
	 * @return void
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
		$this->assertSame(expected: ['calendar'], actual: json_decode((string)$captured, true));
	}//end testSetDisabledAppsDropsProtectedIds()

	/**
	 * Null/unresolved app id is always themed.
	 *
	 * @return void
	 */
	public function testIsThemingDisabledForNullIsFalse(): void {
		$this->assertFalse(condition: $this->service->isThemingDisabledFor(null));
		$this->assertFalse(condition: $this->service->isThemingDisabledFor(''));
	}//end testIsThemingDisabledForNullIsFalse()

	/**
	 * An app in the exclusion list is reported as disabled.
	 *
	 * @return void
	 */
	public function testIsThemingDisabledForExcludedApp(): void {
		$this->config->method('getAppValue')->willReturn('["calendar"]');
		$this->assertTrue(condition: $this->service->isThemingDisabledFor('calendar'));
		$this->assertFalse(condition: $this->service->isThemingDisabledFor('files'));
	}//end testIsThemingDisabledForExcludedApp()

	/**
	 * The resolver extracts the app id from app-page paths and only those.
	 *
	 * @param string|null $pathInfo The request path info.
	 * @param string|null $expected The expected resolved app id.
	 *
	 * @dataProvider pathProvider
	 *
	 * @return void
	 */
	public function testResolveAppIdFromPath(?string $pathInfo, ?string $expected): void {
		$this->assertSame(expected: $expected, actual: $this->service->resolveAppIdFromPath($pathInfo));
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
	 *
	 * @return void
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
		$this->assertNotContains(needle: 'nldesign', haystack: $ids);
		$this->assertNotContains(needle: 'settings', haystack: $ids);
		$this->assertNotContains(needle: 'theming', haystack: $ids);

		// Sorted by display name: Calendar before Files.
		$this->assertSame(expected: ['calendar', 'files'], actual: $ids);

		// Themed flag reflects the exclusion list.
		$byId = array_column($apps, 'themed', 'id');
		$this->assertFalse(condition: $byId['calendar']);
		$this->assertTrue(condition: $byId['files']);
	}//end testGetThemableAppsListsEnabledAppsWithState()
	/**
	 * Golden: the stored list keeps installed, unprotected, non-empty ids once in submitted order, and the
	 * panel lists each enabled app by its display name, sorted case-insensitively.
	 *
	 * @return void
	 */
	public function testTheStoredListAndThePanelGolden(): void {
		$stored = [];
		$this->config->method('setAppValue')->willReturnCallback(
			static function (string $app, string $key, string $value) use (&$stored): void {
				$stored[$key] = $value;
			}
		);
		$asked = [];
		$this->appManager->method('isInstalled')->willReturnCallback(
			static function (string $appId) use (&$asked): bool {
				$asked[] = $appId;

				return $appId !== 'gone';
			}
		);
		$this->service->setDisabledApps(appIds: ['files', '', 'theming', 'gone', 7, 'files', 'deck', null]);
		$this->assertSame(expected: ['files', 'gone', '7', 'files', 'deck'], actual: $asked);
		$this->assertSame(expected: '["files",7,"deck"]', actual: array_values($stored)[0]);

		$this->config->method('getAppValue')->willReturn('["deck"]');
		$this->appManager->method('getInstalledApps')->willReturn(['zeta', 'settings', 'deck', 'alpha', 'nameless', 'empty', 'Beta', 'listed']);
		$this->appManager->method('getAppInfo')->willReturnCallback(
			static fn (string $appId): mixed => match ($appId) {
				'zeta' => ['name' => 'zeta app'],
				'deck' => ['name' => ['en' => 'Deck', 'nl' => 'Deck NL']],
				'alpha' => ['name' => 'Alpha'],
				'nameless' => ['id' => 'nameless'],
				'empty' => ['name' => ''],
				'listed' => ['name' => [7]],
				default => null,
			}
		);
		$this->assertSame(
			expected: [
				['id' => 'alpha', 'name' => 'Alpha', 'themed' => true],
				['id' => 'Beta', 'name' => 'Beta', 'themed' => true],
				['id' => 'deck', 'name' => 'Deck', 'themed' => false],
				['id' => 'empty', 'name' => 'empty', 'themed' => true],
				['id' => 'listed', 'name' => 'listed', 'themed' => true],
				['id' => 'nameless', 'name' => 'nameless', 'themed' => true],
				['id' => 'zeta', 'name' => 'zeta app', 'themed' => true],
			],
			actual: $this->service->getThemableApps()
		);
	}//end testTheStoredListAndThePanelGolden()
}//end class
