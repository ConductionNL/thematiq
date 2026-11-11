<?php

/**
 * Golden test for SettingsController's audit trail: the exact action and
 * context of every entry its setters write, the responses they return, and
 * the group theming refusals that write nothing.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\SettingsController;
use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\AppThemingService;
use OCA\Thematiq\Service\ComplianceReportService;
use OCA\Thematiq\Service\EmailThemingSettings;
use OCA\Thematiq\Service\Exception\GroupThemingValidationException;
use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\SettingsConfigServices;
use OCA\Thematiq\Service\SettingsReportServices;
use OCA\Thematiq\Service\SettingsTokenSetServices;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Service\TokenSetPreviewService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\UpstreamFreshnessService;
use OCA\Theming\ImageManager;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Pins every audit entry SettingsController writes, with its response.
 *
 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
 */
final class SettingsControllerAuditTrailGoldenTest extends TestCase {

	/**
	 * Stored app values, keyed `app|key`.
	 *
	 * @var array<string, string>
	 */
	private array $appConfig = [];

	/**
	 * Every audit call: [action, context].
	 *
	 * @var array<int, array{0: string, 1: array<string, mixed>}>
	 */
	private array $audited = [];

	/**
	 * The request double.
	 *
	 * @var IRequest&MockObject
	 */
	private IRequest $request;

	/**
	 * The theming service double.
	 *
	 * @var ThemingService&MockObject
	 */
	private ThemingService $themingService;

	/**
	 * The image manager double.
	 *
	 * @var ImageManager&MockObject
	 */
	private ImageManager $imageManager;

	/**
	 * The app exclusions double.
	 *
	 * @var AppThemingService&MockObject
	 */
	private AppThemingService $appTheming;

	/**
	 * The group theming double.
	 *
	 * @var GroupThemingService&MockObject
	 */
	private GroupThemingService $groupTheming;

	/**
	 * The controller under test.
	 *
	 * @var SettingsController
	 */
	private SettingsController $controller;

	/**
	 * Build the controller over doubles that record config writes and audit calls.
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
		$config->method('deleteAppValue')->willReturnCallback(
			function (string $app, string $key): void {
				unset($this->appConfig[$app . '|' . $key]);
			}
		);

		$this->imageManager = $this->createMock(originalClassName: ImageManager::class);
		$this->imageManager->method('getImageUrl')->willReturnCallback(
			static fn (string $key): string => '/image/' . $key
		);
		$this->imageManager->method('hasImage')->willReturnCallback(
			static fn (string $key): bool => in_array($key, ['logo', 'favicon'], true)
		);

		$this->themingService = $this->createMock(originalClassName: ThemingService::class);
		$this->themingService->method('getImageManager')->willReturn($this->imageManager);
		$this->themingService->method('getDefaultColors')->willReturn(
			['primary_color' => '#0082c9', 'background_color' => '#ffffff']
		);

		$audit = $this->createMock(originalClassName: ThemingAuditService::class);
		$audit->method('log')->willReturnCallback(
			function (string $action, array $context = []): void {
				$this->audited[] = [$action, $context];
			}
		);

		$this->request = $this->createMock(originalClassName: IRequest::class);
		$this->appTheming = $this->createMock(originalClassName: AppThemingService::class);
		$this->groupTheming = $this->createMock(originalClassName: GroupThemingService::class);

		$this->controller = new SettingsController(
			appName: 'thematiq',
			request: $this->request,
			settings: new SettingsConfigServices(
				config: $config,
				auditService: $audit,
				appThemingService: $this->appTheming,
				groupThemingService: $this->groupTheming
			),
			tokenSets: new SettingsTokenSetServices(
				tokenSetService: $this->createMock(originalClassName: TokenSetService::class),
				previewService: $this->createMock(originalClassName: TokenSetPreviewService::class),
				activeTokenSet: $this->createMock(originalClassName: ActiveTokenSetService::class),
				themingService: $this->themingService
			),
			reports: new SettingsReportServices(
				complianceService: $this->createMock(originalClassName: ComplianceReportService::class),
				emailTheming: $this->createMock(originalClassName: EmailThemingSettings::class),
				freshnessService: $this->createMock(originalClassName: UpstreamFreshnessService::class)
			)
		);
	}//end setUp()

	/**
	 * Each single toggle writes its value and one `toggle_changed` entry with the old and new state.
	 *
	 * @return void
	 */
	public function testEachToggleAuditsItsOldAndNewState(): void {
		$this->appConfig['thematiq|hide_slogan'] = '1';

		$responses = [
			$this->controller->setSloganSetting(hideSlogan: false)->getData(),
			$this->controller->setPrimaryDrivesComponentsSetting(primaryDrivesComponents: true)->getData(),
			$this->controller->setMenuLabelsSetting(showMenuLabels: true)->getData(),
			$this->controller->setDarkVariants(enabled: false)->getData(),
			$this->controller->setMarianneEnabled(enabled: true)->getData(),
		];

		$this->assertSame(
			expected: [
				['status' => 'ok', 'hideSlogan' => false],
				['status' => 'ok', 'primaryDrivesComponents' => true],
				['status' => 'ok', 'showMenuLabels' => true],
				['status' => 'ok', 'enabled' => false],
				['status' => 'ok', 'enabled' => true],
			],
			actual: $responses
		);
		$this->assertSame(
			expected: [
				['toggle_changed', ['key' => 'hide_slogan', 'old' => true, 'new' => false]],
				['toggle_changed', ['key' => 'primary_drives_components', 'old' => false, 'new' => true]],
				['toggle_changed', ['key' => 'show_menu_labels', 'old' => false, 'new' => true]],
				['toggle_changed', ['key' => 'dark_variants', 'old' => true, 'new' => false]],
				['toggle_changed', ['key' => 'marianne_enabled', 'old' => false, 'new' => true]],
			],
			actual: $this->audited
		);
		$this->assertSame(expected: '0', actual: $this->appConfig['thematiq|hide_slogan']);
		$this->assertSame(expected: '1', actual: $this->appConfig['thematiq|primary_drives_components']);
		$this->assertSame(expected: '1', actual: $this->appConfig['thematiq|show_menu_labels']);
		$this->assertSame(expected: '0', actual: $this->appConfig['thematiq|dark_variants']);
		$this->assertSame(expected: '1', actual: $this->appConfig['thematiq|marianne_enabled']);
	}//end testEachToggleAuditsItsOldAndNewState()

	/**
	 * The save confirmations write and audit only the flag that changed.
	 *
	 * @return void
	 */
	public function testSaveConfirmationsAuditOnlyWhatChanged(): void {
		$response = $this->controller->setSaveConfirmSettings(confirmSaveStock: true, confirmSaveTheme: false);

		$this->assertSame(
			expected: ['status' => 'ok', 'confirmSaveStock' => true, 'confirmSaveTheme' => false],
			actual: $response->getData()
		);
		$this->assertSame(
			expected: [['toggle_changed', ['key' => 'confirm_save_theme', 'old' => true, 'new' => false]]],
			actual: $this->audited
		);
		$this->assertSame(expected: ['thematiq|confirm_save_theme' => '0'], actual: $this->appConfig);

		$this->controller->setSaveConfirmSettings(confirmSaveStock: false, confirmSaveTheme: true);

		$this->assertSame(
			expected: [
				['toggle_changed', ['key' => 'confirm_save_stock', 'old' => true, 'new' => false]],
				['toggle_changed', ['key' => 'confirm_save_theme', 'old' => false, 'new' => true]],
			],
			actual: array_slice($this->audited, 1)
		);
	}//end testSaveConfirmationsAuditOnlyWhatChanged()

	/**
	 * The theming snapshot carries every field, in order, with the synced images.
	 *
	 * @return void
	 */
	public function testTheThemingSnapshotIsComplete(): void {
		$this->appConfig['theming|primary_color'] = '#154273';
		$this->appConfig['theming|backgroundMime'] = 'backgroundColor';
		$this->appConfig['thematiq|synced_logo'] = '/img/logo.svg';
		$this->appConfig['thematiq|synced_favicon'] = '/img/favicon.svg';

		$this->assertSame(
			expected: [
				'primary_color' => '#154273',
				'background_color' => '',
				'logo_url' => '/image/logo',
				'background_url' => '/image/background',
				'has_custom_logo' => true,
				'has_custom_logoheader' => false,
				'has_custom_favicon' => true,
				'has_custom_background' => false,
				'background_mime' => 'backgroundColor',
				'default_primary_color' => '#0082c9',
				'default_background_color' => '#ffffff',
				'synced_logo' => '/img/logo.svg',
				'synced_background' => '',
				'synced_logoheader' => '',
				'synced_favicon' => '/img/favicon.svg',
			],
			actual: $this->controller->getThemingValues()->getData()
		);
	}//end testTheThemingSnapshotIsComplete()

	/**
	 * An apply writes the synced images, counts the sync and audits both snapshots.
	 *
	 * @return void
	 */
	public function testAnApplyAuditsTheSnapshotsAroundIt(): void {
		$this->request->method('getParams')->willReturn(['primary_color' => '#154273', 'logo' => '/img/a.svg', 'favicon' => '/img/b.svg']);
		$this->themingService->method('validateColors')->willReturn(null);
		$this->themingService->method('validateImagePaths')->willReturn(null);
		$this->themingService->method('applyColors')->willReturnCallback(
			function (): array {
				$this->appConfig['theming|primary_color'] = '#154273';

				return ['primary_color'];
			}
		);
		$this->themingService->method('applyImages')->willReturn(['logo', 'favicon']);
		$this->appConfig['thematiq|theming_syncs_total'] = '2';

		$response = $this->controller->updateThemingValues();

		$this->assertSame(expected: ['status' => 'ok', 'updated' => ['primary_color', 'logo', 'favicon']], actual: $response->getData());
		$this->assertSame(expected: '/img/a.svg', actual: $this->appConfig['thematiq|synced_logo']);
		$this->assertSame(expected: '/img/b.svg', actual: $this->appConfig['thematiq|synced_favicon']);
		$this->assertSame(expected: '3', actual: $this->appConfig['thematiq|theming_syncs_total']);
		$this->assertCount(expectedCount: 1, haystack: $this->audited);
		$this->assertSame(expected: 'theming_sync_applied', actual: $this->audited[0][0]);
		$this->assertSame(expected: ['old', 'new'], actual: array_keys($this->audited[0][1]));
		$this->assertSame(expected: '', actual: $this->audited[0][1]['old']['primary_color']);
		$this->assertSame(expected: '', actual: $this->audited[0][1]['old']['synced_logo']);
		$this->assertSame(expected: '#154273', actual: $this->audited[0][1]['new']['primary_color']);
		$this->assertSame(expected: '/img/a.svg', actual: $this->audited[0][1]['new']['synced_logo']);
	}//end testAnApplyAuditsTheSnapshotsAroundIt()

	/**
	 * A refused colour and a refused image path answer 400 and apply, count and audit nothing.
	 *
	 * @return void
	 */
	public function testARefusedApplyWritesNothing(): void {
		$this->request->method('getParams')->willReturn(['primary_color' => 'nope']);
		$this->themingService->method('validateColors')->willReturnOnConsecutiveCalls('Bad colour', null);
		$this->themingService->method('validateImagePaths')->willReturn('Bad path');
		$this->themingService->expects($this->never())->method('applyColors');

		$first = $this->controller->updateThemingValues();
		$second = $this->controller->updateThemingValues();

		$this->assertSame(expected: [400, ['error' => 'Bad colour']], actual: [$first->getStatus(), $first->getData()]);
		$this->assertSame(expected: [400, ['error' => 'Bad path']], actual: [$second->getStatus(), $second->getData()]);
		$this->assertSame(expected: [], actual: $this->audited);
		$this->assertSame(expected: [], actual: $this->appConfig);
	}//end testARefusedApplyWritesNothing()

	/**
	 * A reset forgets every synced image and audits the snapshot before it and the reset fields.
	 *
	 * @return void
	 */
	public function testAResetAuditsWhatItReset(): void {
		$this->request->method('getParams')->willReturn(['reset' => '1']);
		$this->themingService->method('resetToDefaults')->willReturn(['primary_color', 'logo']);
		$this->appConfig['thematiq|synced_logo'] = '/img/logo.svg';
		$this->appConfig['thematiq|synced_background'] = '/img/bg.jpg';

		$response = $this->controller->updateThemingValues();

		$this->assertSame(expected: ['status' => 'ok', 'reset' => ['primary_color', 'logo']], actual: $response->getData());
		$this->assertSame(expected: [], actual: $this->appConfig);
		$this->assertSame(expected: 'theming_sync_reset', actual: $this->audited[0][0]);
		$this->assertSame(expected: '/img/logo.svg', actual: $this->audited[0][1]['old']['synced_logo']);
		$this->assertSame(expected: ['primary_color', 'logo'], actual: $this->audited[0][1]['new']);
	}//end testAResetAuditsWhatItReset()

	/**
	 * The app exclusions are audited as read before and after the write.
	 *
	 * @return void
	 */
	public function testAppExclusionsAuditTheListsAroundTheWrite(): void {
		$this->appTheming->method('getDisabledApps')->willReturnOnConsecutiveCalls(['files'], ['files', 'mail']);
		$this->appTheming->expects($this->once())->method('setDisabledApps')->with(['mail', 'files']);

		$response = $this->controller->setAppTheming(disabledApps: ['mail', 'files']);

		$this->assertSame(expected: ['status' => 'ok', 'disabledApps' => ['files', 'mail']], actual: $response->getData());
		$this->assertSame(
			expected: [['app_exclusions_changed', ['old' => ['files'], 'new' => ['files', 'mail']]]],
			actual: $this->audited
		);
	}//end testAppExclusionsAuditTheListsAroundTheWrite()

	/**
	 * An accepted group mapping is audited with the stored mapping before and after.
	 *
	 * @return void
	 */
	public function testAnAcceptedGroupMappingIsAudited(): void {
		$old = [['group' => 'staff', 'tokenSet' => 'rijkshuisstijl']];
		$new = [['group' => 'staff', 'tokenSet' => 'utrecht']];
		$this->groupTheming->method('getMapping')->willReturn($old);
		$this->groupTheming->method('setMapping')->with($new)->willReturn($new);

		$response = $this->controller->setGroupTheming(mapping: $new);

		$this->assertSame(expected: [200, ['status' => 'ok', 'mapping' => $new]], actual: [$response->getStatus(), $response->getData()]);
		$this->assertSame(expected: [['group_theming_changed', ['old' => $old, 'new' => $new]]], actual: $this->audited);
	}//end testAnAcceptedGroupMappingIsAudited()

	/**
	 * A mapping refused for an unconfirmed restricted set answers 403, an invalid one 422; neither is audited.
	 *
	 * @return void
	 */
	public function testARefusedGroupMappingIsNotAudited(): void {
		$entry = ['group' => 'staff', 'tokenSet' => 'gated'];
		$this->groupTheming->method('getMapping')->willReturn([]);
		$this->groupTheming->method('setMapping')->willReturnOnConsecutiveCalls(
			$this->throwException(new GroupThemingValidationException(entry: $entry, reason: 'Restricted', restrictedSet: 'gated')),
			$this->throwException(new GroupThemingValidationException(entry: $entry, reason: 'Unknown token set'))
		);

		$restricted = $this->controller->setGroupTheming(mapping: [$entry]);
		$invalid = $this->controller->setGroupTheming(mapping: [$entry]);

		$this->assertSame(
			expected: [403, ['error' => 'restricted_set_not_confirmed', 'tokenSet' => 'gated', 'entry' => $entry]],
			actual: [$restricted->getStatus(), $restricted->getData()]
		);
		$this->assertSame(
			expected: [422, ['error' => 'invalid_mapping', 'entry' => $entry, 'reason' => 'Unknown token set']],
			actual: [$invalid->getStatus(), $invalid->getData()]
		);
		$this->assertSame(expected: [], actual: $this->audited);
	}//end testARefusedGroupMappingIsNotAudited()
}//end class
