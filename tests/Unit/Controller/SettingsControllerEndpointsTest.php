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
use OCA\Thematiq\Service\AppThemingService;
use OCA\Thematiq\Service\ComplianceReportService;
use OCA\Thematiq\Service\EmailThemingService;
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
	 */
	protected function setUp(): void {
		parent::setUp();

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->appConfig[$app . '|' . $key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->appConfig[$app . '|' . $key] = (string)$value;
			}
		);

		$auditService = $this->createMock(ThemingAuditService::class);
		$auditService->method('log')->willReturnCallback(
			function (string $action, array $context = []): void {
				$this->audited[] = [$action, $context];
			}
		);

		$this->tokenSetService = $this->createMock(TokenSetService::class);
		$this->previewService = $this->createMock(TokenSetPreviewService::class);
		$this->appThemingService = $this->createMock(AppThemingService::class);

		$this->controller = new SettingsController(
			'thematiq',
			$this->createMock(IRequest::class),
			$config,
			$this->tokenSetService,
			$this->createMock(ThemingService::class),
			$this->previewService,
			$this->appThemingService,
			$this->createMock(ComplianceReportService::class),
			$auditService,
			$this->createMock(EmailThemingService::class),
			$this->createMock(UpstreamFreshnessService::class),
			$this->createMock(GroupThemingService::class)
		);
	}//end setUp()

	/**
	 * An instance that has never chosen a set reports the stock one rather
	 * than an empty string — the panel selects by id, and no id selects
	 * nothing.
	 *
	 * @spec openspec/specs/admin-settings/spec.md
	 */
	public function testTheActiveTokenSetDefaultsToStock(): void {
		$this->assertSame('nextcloud', $this->controller->getTokenSet()->getData()['tokenSet']);

		$this->appConfig['thematiq|token_set'] = 'rijkshuisstijl';
		$this->assertSame('rijkshuisstijl', $this->controller->getTokenSet()->getData()['tokenSet']);
	}//end testTheActiveTokenSetDefaultsToStock()

	/**
	 * A preview is only computed for a set that exists. The resolver reads
	 * files off disk by id, so an unvalidated id would be answered with an
	 * empty map that reads like "this theme changes nothing".
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-token-set-preview
	 */
	public function testAnUnknownPreviewIs404AndResolvesNothing(): void {
		$this->tokenSetService->method('isValidTokenSet')->willReturn(false);
		$this->previewService->expects($this->never())->method('getResolvedColors');

		$response = $this->controller->getTokenSetPreview(tokenSetId: 'no-such-set');

		$this->assertSame(404, $response->getStatus());
		$this->assertSame(['error' => 'Token set not found'], $response->getData());
	}//end testAnUnknownPreviewIs404AndResolvesNothing()

	/**
	 * A known set is answered with the resolved map, echoing the id so the
	 * panel can discard a response that arrives after the admin has moved on
	 * to another set.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-token-set-preview
	 */
	public function testAKnownPreviewEchoesTheIdWithTheResolvedMap(): void {
		$this->tokenSetService->method('isValidTokenSet')->willReturn(true);
		$this->previewService->method('getResolvedColors')->willReturn(['--nldesign-color-primary' => '#154273']);

		$response = $this->controller->getTokenSetPreview(tokenSetId: 'rijkshuisstijl');

		$this->assertSame(200, $response->getStatus());
		$this->assertSame('rijkshuisstijl', $response->getData()['tokenSetId']);
		$this->assertSame(['--nldesign-color-primary' => '#154273'], $response->getData()['resolved']);
	}//end testAKnownPreviewEchoesTheIdWithTheResolvedMap()

	/**
	 * Both login-page toggles persist, echo the new value, and audit the
	 * change with the state they replaced. The `old` value has to be read
	 * BEFORE the write: after it, `old` would always equal `new` and the trail
	 * would record every toggle as a no-op.
	 *
	 * @param string $method   The controller method.
	 * @param string $key      The app config key.
	 * @param string $property The response property echoing the new value.
	 *
	 * @dataProvider toggleProvider
	 *
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	public function testATogglePersistsEchoesAndAuditsThePreviousValue(
		string $method,
		string $key,
		string $property
	): void {
		$this->appConfig['thematiq|' . $key] = '1';

		$response = $this->controller->{$method}(false);

		$this->assertSame(200, $response->getStatus());
		$this->assertSame('ok', $response->getData()['status']);
		$this->assertFalse($response->getData()[$property]);
		$this->assertSame('0', $this->appConfig['thematiq|' . $key]);

		$this->assertCount(1, $this->audited);
		$this->assertSame('toggle_changed', $this->audited[0][0]);
		$this->assertSame($key, $this->audited[0][1]['key']);
		$this->assertTrue($this->audited[0][1]['old']);
		$this->assertFalse($this->audited[0][1]['new']);
	}//end testATogglePersistsEchoesAndAuditsThePreviousValue()

	/**
	 * Turning a toggle ON is stored as the `'1'` the rest of the app reads.
	 *
	 * @param string $method   The controller method.
	 * @param string $key      The app config key.
	 * @param string $property The response property echoing the new value.
	 *
	 * @dataProvider toggleProvider
	 *
	 * @spec openspec/specs/admin-settings/spec.md
	 */
	public function testATogglePersistsTheOnValue(string $method, string $key, string $property): void {
		$response = $this->controller->{$method}(true);

		$this->assertTrue($response->getData()[$property]);
		$this->assertSame('1', $this->appConfig['thematiq|' . $key]);
		$this->assertFalse($this->audited[0][1]['old']);
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
	 */
	public function testTheThemableAppListIsPassedThrough(): void {
		$apps = [['id' => 'files', 'name' => 'Files', 'themed' => true]];
		$this->appThemingService->method('getThemableApps')->willReturn($apps);

		$this->assertSame($apps, $this->controller->getAppTheming()->getData()['apps']);
	}//end testTheThemableAppListIsPassedThrough()

	/**
	 * The exclusion list echoes what was STORED, not what was requested: the
	 * service drops protected app ids, and a panel rendering its own request
	 * back would show an exclusion that is not in force.
	 *
	 * @spec openspec/specs/admin-settings/spec.md
	 */
	public function testTheExclusionListEchoesWhatWasStoredNotWhatWasSent(): void {
		$stored = ['files'];
		$this->appThemingService->method('getDisabledApps')->willReturnOnConsecutiveCalls([], $stored);
		$this->appThemingService->expects($this->once())
			->method('setDisabledApps')
			->with(['files', 'thematiq', 'settings']);

		$response = $this->controller->setAppTheming(disabledApps: ['files', 'thematiq', 'settings']);

		$this->assertSame(200, $response->getStatus());
		$this->assertSame('ok', $response->getData()['status']);
		$this->assertSame($stored, $response->getData()['disabledApps']);
	}//end testTheExclusionListEchoesWhatWasStoredNotWhatWasSent()

	/**
	 * The exclusion change is audited with both sides, which is what makes a
	 * dropped protected id visible after the fact: `new` differs from the
	 * request, and `old` says what it replaced.
	 *
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	public function testTheExclusionChangeIsAuditedWithBothSides(): void {
		$this->appThemingService->method('getDisabledApps')->willReturnOnConsecutiveCalls(['dashboard'], ['files']);

		$this->controller->setAppTheming(disabledApps: ['files']);

		$this->assertCount(1, $this->audited);
		$this->assertSame('app_exclusions_changed', $this->audited[0][0]);
		$this->assertSame(['dashboard'], $this->audited[0][1]['old']);
		$this->assertSame(['files'], $this->audited[0][1]['new']);
	}//end testTheExclusionChangeIsAuditedWithBothSides()

	/**
	 * Clearing the list is an ordinary save, not a special case: the default
	 * argument means "no exclusions" rather than "leave it alone".
	 *
	 * @spec openspec/specs/admin-settings/spec.md
	 */
	public function testClearingTheExclusionListStoresAnEmptyList(): void {
		$this->appThemingService->method('getDisabledApps')->willReturn([]);
		$this->appThemingService->expects($this->once())->method('setDisabledApps')->with([]);

		$response = $this->controller->setAppTheming();

		$this->assertSame([], $response->getData()['disabledApps']);
	}//end testClearingTheExclusionListStoresAnEmptyList()
}//end class
