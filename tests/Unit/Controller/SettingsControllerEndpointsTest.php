<?php

/**
 * Unit tests for SettingsController's read/toggle endpoints: the active token
 * set, the preview, the two login-page toggles and the per-app exclusion list.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\SettingsController;
use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\AppThemingService;
use OCA\Thematiq\Service\ComplianceReportService;
use OCA\Thematiq\Service\EmailThemingSettings;
use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Service\TokenSetPreviewService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\UpstreamFreshnessService;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * These are the endpoints the admin panel reads and the two it toggles, and
 * every one of them is audited. The audit entry is the part worth pinning:
 * it records the state BEFORE the change alongside the new one, so an
 * after-the-fact question ("who turned the slogan off, and what was it
 * before?") has an answer. Reading `$previous` after the write instead of
 * before would leave a trail where old always equals new — a log that is
 * present, well-formed and useless.
 *
 * The exclusion list is audited the same way, and there the BEFORE/AFTER pair
 * carries a second meaning: the service drops protected app ids from whatever
 * it is handed, so `after` is what was actually stored rather than what the
 * request asked for. The response echoes the stored list for the same reason —
 * the panel must render what is in force, not what it sent.
 *
 * @spec openspec/specs/admin-settings/spec.md
 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
 */
class SettingsControllerEndpointsTest extends TestCase {

	/**
	 * In-memory appconfig, keyed `<app>|<key>`.
	 *
	 * @var array<string, string>
	 */
	private array $appConfig = [];

	/**
	 * Audit entries recorded during a test, as `[action, context]`.
	 *
	 * @var array<int, array{0: string, 1: array<string, mixed>}>
	 */
	private array $audited = [];

	/**
	 * The token set catalogue mock.
	 *
	 * @var TokenSetService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $tokenSetService;

	/**
	 * The preview service mock.
	 *
	 * @var TokenSetPreviewService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $previewService;

	/**
	 * The per-app theming service mock.
	 *
	 * @var AppThemingService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $appThemingService;

	/**
	 * The controller under test.
	 *
	 * @var SettingsController
	 */
	private SettingsController $controller;

	/**
	 * Wire the controller over mocked services and an in-memory appconfig.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->appConfig[$app . '|' . $key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->appConfig[$app . '|' . $key] = (string)$value;
			}
		);

		$auditService = $this->createMock(originalClassName: ThemingAuditService::class);
		$auditService->method('log')->willReturnCallback(
			function (string $action, array $context = []): void {
				$this->audited[] = [$action, $context];
			}
		);

		$this->tokenSetService = $this->createMock(originalClassName: TokenSetService::class);
		$this->previewService = $this->createMock(originalClassName: TokenSetPreviewService::class);
		$this->appThemingService = $this->createMock(originalClassName: AppThemingService::class);

		$this->controller = new SettingsController(
			appName: 'thematiq',
			request: $this->createMock(originalClassName: IRequest::class),
			config: $config,
			tokenSetService: $this->tokenSetService,
			themingService: $this->createMock(originalClassName: ThemingService::class),
			previewService: $this->previewService,
			appThemingService: $this->appThemingService,
			complianceService: $this->createMock(originalClassName: ComplianceReportService::class),
			auditService: $auditService,
			emailTheming: $this->createMock(originalClassName: EmailThemingSettings::class),
			freshnessService: $this->createMock(originalClassName: UpstreamFreshnessService::class),
			groupThemingService: $this->createMock(originalClassName: GroupThemingService::class),
			activeTokenSet: $this->createMock(originalClassName: ActiveTokenSetService::class)
		);
	}//end setUp()

	/**
	 * A preview is only computed for a set that exists. The resolver reads
	 * files off disk by id, so an unvalidated id would be answered with an
	 * empty map that reads like "this theme changes nothing".
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-token-set-preview
	 *
	 * @return void
	 */
	public function testAnUnknownPreviewIs404AndResolvesNothing(): void {
		$this->tokenSetService->method('isValidTokenSet')->willReturn(false);
		$this->previewService->expects($this->never())->method('getResolvedColors');

		$response = $this->controller->getTokenSetPreview(tokenSetId: 'no-such-set');

		$this->assertSame(expected: 404, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'Token set not found'], actual: $response->getData());
	}//end testAnUnknownPreviewIs404AndResolvesNothing()

	/**
	 * A known set is answered with the resolved map, echoing the id so the
	 * panel can discard a response that arrives after the admin has moved on
	 * to another set.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-token-set-preview
	 *
	 * @return void
	 */
	public function testAKnownPreviewEchoesTheIdWithTheResolvedMap(): void {
		$this->tokenSetService->method('isValidTokenSet')->willReturn(true);
		$this->previewService->method('getResolvedColors')->willReturn(['--nldesign-color-primary' => '#154273']);

		$response = $this->controller->getTokenSetPreview(tokenSetId: 'rijkshuisstijl');

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: 'rijkshuisstijl', actual: $response->getData()['tokenSetId']);
		$this->assertSame(expected: ['--nldesign-color-primary' => '#154273'], actual: $response->getData()['resolved']);
	}//end testAKnownPreviewEchoesTheIdWithTheResolvedMap()

	/**
	 * Both login-page toggles persist, echo the new value, and audit the
	 * change with the state they replaced. The `old` value has to be read
	 * BEFORE the write: after it, `old` would always equal `new` and the trail
	 * would record every toggle as a no-op.
	 *
	 * @param string $method The controller method.
	 * @param string $key The app config key.
	 * @param string $property The response property echoing the new value.
	 *
	 * @dataProvider toggleProvider
	 *
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 *
	 * @return void
	 */
	public function testATogglePersistsEchoesAndAuditsThePreviousValue(
		string $method,
		string $key,
		string $property,
	): void {
		$this->appConfig['thematiq|' . $key] = '1';

		$response = $this->controller->{$method}(false);

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: 'ok', actual: $response->getData()['status']);
		$this->assertFalse(condition: $response->getData()[$property]);
		$this->assertSame(expected: '0', actual: $this->appConfig['thematiq|' . $key]);

		$this->assertCount(expectedCount: 1, haystack: $this->audited);
		$this->assertSame(expected: 'toggle_changed', actual: $this->audited[0][0]);
		$this->assertSame(expected: $key, actual: $this->audited[0][1]['key']);
		$this->assertTrue(condition: $this->audited[0][1]['old']);
		$this->assertFalse(condition: $this->audited[0][1]['new']);
	}//end testATogglePersistsEchoesAndAuditsThePreviousValue()

	/**
	 * Turning a toggle ON is stored as the `'1'` the rest of the app reads.
	 *
	 * @param string $method The controller method.
	 * @param string $key The app config key.
	 * @param string $property The response property echoing the new value.
	 *
	 * @dataProvider toggleProvider
	 *
	 * @spec openspec/specs/admin-settings/spec.md
	 *
	 * @return void
	 */
	public function testATogglePersistsTheOnValue(string $method, string $key, string $property): void {
		$response = $this->controller->{$method}(true);

		$this->assertTrue(condition: $response->getData()[$property]);
		$this->assertSame(expected: '1', actual: $this->appConfig['thematiq|' . $key]);
		$this->assertFalse(condition: $this->audited[0][1]['old']);
	}//end testATogglePersistsTheOnValue()

	/**
	 * The boolean toggles that persist, echo and audit the same way.
	 *
	 * `primary_drives_components` is one of them and not a special case: it
	 * stores '1'/'0' under the app id, echoes the new value, and audits the
	 * previous one. What it switches on — css/primary-lock.css, and the locked
	 * rows in the editor — is decided elsewhere by reading that same key.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function toggleProvider(): array {
		return [
			'hide slogan' => ['setSloganSetting', 'hide_slogan', 'hideSlogan'],
			'show menu labels' => ['setMenuLabelsSetting', 'show_menu_labels', 'showMenuLabels'],
			'primary drives components' => [
				'setPrimaryDrivesComponentsSetting',
				'primary_drives_components',
				'primaryDrivesComponents',
			],
		];
	}//end toggleProvider()

	/**
	 * The themable-app list is handed through as the service built it.
	 *
	 * @spec openspec/specs/admin-settings/spec.md
	 *
	 * @return void
	 */
	public function testTheThemableAppListIsPassedThrough(): void {
		$apps = [['id' => 'files', 'name' => 'Files', 'themed' => true]];
		$this->appThemingService->method('getThemableApps')->willReturn($apps);

		$this->assertSame(expected: $apps, actual: $this->controller->getAppTheming()->getData()['apps']);
	}//end testTheThemableAppListIsPassedThrough()

	/**
	 * The exclusion list echoes what was STORED, not what was requested: the
	 * service drops protected app ids, and a panel rendering its own request
	 * back would show an exclusion that is not in force.
	 *
	 * @spec openspec/specs/admin-settings/spec.md
	 *
	 * @return void
	 */
	public function testTheExclusionListEchoesWhatWasStoredNotWhatWasSent(): void {
		$stored = ['files'];
		$this->appThemingService->method('getDisabledApps')->willReturnOnConsecutiveCalls([], $stored);
		$this->appThemingService->expects($this->once())
			->method('setDisabledApps')
			->with(['files', 'thematiq', 'settings']);

		$response = $this->controller->setAppTheming(disabledApps: ['files', 'thematiq', 'settings']);

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: 'ok', actual: $response->getData()['status']);
		$this->assertSame(expected: $stored, actual: $response->getData()['disabledApps']);
	}//end testTheExclusionListEchoesWhatWasStoredNotWhatWasSent()

	/**
	 * The exclusion change is audited with both sides, which is what makes a
	 * dropped protected id visible after the fact: `new` differs from the
	 * request, and `old` says what it replaced.
	 *
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 *
	 * @return void
	 */
	public function testTheExclusionChangeIsAuditedWithBothSides(): void {
		$this->appThemingService->method('getDisabledApps')->willReturnOnConsecutiveCalls(['dashboard'], ['files']);

		$this->controller->setAppTheming(disabledApps: ['files']);

		$this->assertCount(expectedCount: 1, haystack: $this->audited);
		$this->assertSame(expected: 'app_exclusions_changed', actual: $this->audited[0][0]);
		$this->assertSame(expected: ['dashboard'], actual: $this->audited[0][1]['old']);
		$this->assertSame(expected: ['files'], actual: $this->audited[0][1]['new']);
	}//end testTheExclusionChangeIsAuditedWithBothSides()

	/**
	 * Clearing the list is an ordinary save, not a special case: the default
	 * argument means "no exclusions" rather than "leave it alone".
	 *
	 * @spec openspec/specs/admin-settings/spec.md
	 *
	 * @return void
	 */
	public function testClearingTheExclusionListStoresAnEmptyList(): void {
		$this->appThemingService->method('getDisabledApps')->willReturn([]);
		$this->appThemingService->expects($this->once())->method('setDisabledApps')->with([]);

		$response = $this->controller->setAppTheming();

		$this->assertSame(expected: [], actual: $response->getData()['disabledApps']);
	}//end testClearingTheExclusionListStoresAnEmptyList()

	/**
	 * The two save confirmations are separate switches: turning one off
	 * stores and audits that one only. Both start ON, so the flag left alone
	 * is neither rewritten nor logged — a trail with an entry per save for a
	 * setting nobody touched would bury the one that changed.
	 *
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 *
	 * @return void
	 */
	public function testASaveConfirmationStoresAndAuditsOnlyTheFlagThatChanged(): void {
		$response = $this->controller->setSaveConfirmSettings(confirmSaveStock: false, confirmSaveTheme: true);

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(
			expected: ['status' => 'ok', 'confirmSaveStock' => false, 'confirmSaveTheme' => true],
			actual: $response->getData()
		);
		$this->assertSame(expected: '0', actual: $this->appConfig['thematiq|confirm_save_stock']);
		$this->assertArrayNotHasKey(key: 'thematiq|confirm_save_theme', array: $this->appConfig);

		$this->assertCount(expectedCount: 1, haystack: $this->audited);
		$this->assertSame(expected: 'toggle_changed', actual: $this->audited[0][0]);
		$this->assertSame(expected: ['key' => 'confirm_save_stock', 'old' => true, 'new' => false], actual: $this->audited[0][1]);
	}//end testASaveConfirmationStoresAndAuditsOnlyTheFlagThatChanged()

	/**
	 * The controls under the token editor turn a "do not ask again" back on,
	 * so the choice is never one-way: both flags go back to `'1'`, each with
	 * its own audit entry.
	 *
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 *
	 * @return void
	 */
	public function testBothSaveConfirmationsCanBeTurnedBackOn(): void {
		$this->appConfig['thematiq|confirm_save_stock'] = '0';
		$this->appConfig['thematiq|confirm_save_theme'] = '0';

		$this->controller->setSaveConfirmSettings(confirmSaveStock: true, confirmSaveTheme: true);

		$this->assertSame(expected: '1', actual: $this->appConfig['thematiq|confirm_save_stock']);
		$this->assertSame(expected: '1', actual: $this->appConfig['thematiq|confirm_save_theme']);
		$this->assertCount(expectedCount: 2, haystack: $this->audited);
		$this->assertSame(expected: ['key' => 'confirm_save_stock', 'old' => false, 'new' => true], actual: $this->audited[0][1]);
		$this->assertSame(expected: ['key' => 'confirm_save_theme', 'old' => false, 'new' => true], actual: $this->audited[1][1]);
	}//end testBothSaveConfirmationsCanBeTurnedBackOn()
}//end class
