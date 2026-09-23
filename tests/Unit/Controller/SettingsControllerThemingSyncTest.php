<?php

/**
 * Unit tests for SettingsController's core-theming sync: the snapshot the
 * dialog reads, the apply, and the reset that shares its endpoint.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/theming-sync/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Theming\ImageManager;
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
 * `POST /settings/theming` carries two operations, because Nextcloud caches
 * the route collection per host for an hour: a brand-new path would 404 on
 * every already-warm instance until that expired. So a reset rides the URL
 * that has existed for releases, selected by `reset=1`.
 *
 * That makes the parameter a dispatch decision, and the cases around it are
 * exactly where a sync can go wrong silently:
 *
 *  - `reset` absent, empty or `"0"` must NOT reset. A theme apply that
 *    silently undid itself would look like a sync that did nothing;
 *  - a reset must forget which images were synced, or the dialog keeps
 *    claiming the previous set's logo is still in place;
 *  - an apply must RECORD which image it put in each slot. Core stores only
 *    THAT a custom image exists, never which file it came from, so without
 *    that record the dialog offered the same unchanged logo on every apply.
 *
 * Validation is asserted on the side effects, not only on the status code: a
 * refused request that had already written half the theme would still answer
 * 400.
 *
 * @spec openspec/specs/theming-sync/spec.md
 * @spec openspec/changes/apply-without-reload/specs/theming-sync/spec.md
 */
class SettingsControllerThemingSyncTest extends TestCase {

	/**
	 * In-memory appconfig, keyed `<app>|<key>`.
	 *
	 * @var array<string, string>
	 */
	private array $appConfig = [];

	/**
	 * The request mock, reconfigured per test.
	 *
	 * @var IRequest&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $request;

	/**
	 * The theming service mock — every core write lands here.
	 *
	 * @var ThemingService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $themingService;

	/**
	 * The audit trail mock.
	 *
	 * @var ThemingAuditService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $auditService;

	/**
	 * Audit entries recorded during a test, as `[action, context]`.
	 *
	 * @var array<int, array{0: string, 1: array<string, mixed>}>
	 */
	private array $audited = [];

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

		if (class_exists(ImageManager::class) === false) {
			$this->markTestSkipped('OCA\Theming is not autoloadable in this environment (no installed Nextcloud root).');
		}

		$config = $this->createMock(IConfig::class);
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

		$imageManager = $this->createMock(ImageManager::class);
		$imageManager->method('getImageUrl')->willReturnCallback(
			static fn (string $key): string => '/index.php/apps/theming/image/' . $key
		);
		$imageManager->method('hasImage')->willReturn(false);

		$this->themingService = $this->createMock(ThemingService::class);
		$this->themingService->method('getImageManager')->willReturn($imageManager);
		$this->themingService->method('getDefaultColors')->willReturn(
			['primary_color' => '#0082c9', 'background_color' => '#ffffff']
		);

		$this->auditService = $this->createMock(ThemingAuditService::class);
		$this->auditService->method('log')->willReturnCallback(
			function (string $action, array $context = []): void {
				$this->audited[] = [$action, $context];
			}
		);

		$this->request = $this->createMock(IRequest::class);

		$this->controller = new SettingsController(
			'thematiq',
			$this->request,
			$config,
			$this->createMock(TokenSetService::class),
			$this->themingService,
			$this->createMock(TokenSetPreviewService::class),
			$this->createMock(AppThemingService::class),
			$this->createMock(ComplianceReportService::class),
			$this->auditService,
			$this->createMock(EmailThemingService::class),
			$this->createMock(UpstreamFreshnessService::class),
			$this->createMock(GroupThemingService::class)
		);
	}//end setUp()

	/**
	 * Point the request at one set of POST parameters.
	 *
	 * @param array<string, mixed> $params The request parameters.
	 *
	 * @return void
	 */
	private function withParams(array $params): void {
		$this->request->method('getParams')->willReturn($params);
	}//end withParams()

	/**
	 * The actions recorded on the audit trail.
	 *
	 * @return array<int, string> The recorded actions.
	 */
	private function auditedActions(): array {
		return array_column($this->audited, 0);
	}//end auditedActions()

	/**
	 * The snapshot carries what core currently holds, what a reset would land
	 * on, and which file the sync last put in each image slot — the last of
	 * those being the only way the dialog can tell "already in place" from
	 * "this set has a logo".
	 *
	 * @spec openspec/specs/theming-sync/spec.md
	 */
	public function testThemingValuesCarryCurrentDefaultAndSyncedState(): void {
		$this->appConfig['theming|primary_color'] = '#154273';
		$this->appConfig['thematiq|synced_logo'] = 'img/logos/rijkshuisstijl.svg';

		$data = $this->controller->getThemingValues()->getData();

		$this->assertSame('#154273', $data['primary_color']);
		$this->assertSame('#0082c9', $data['default_primary_color']);
		$this->assertSame('#ffffff', $data['default_background_color']);
		$this->assertSame('img/logos/rijkshuisstijl.svg', $data['synced_logo']);
		$this->assertSame('', $data['synced_background']);
		$this->assertFalse($data['has_custom_logo']);
		$this->assertStringContainsString('logo', $data['logo_url']);
	}//end testThemingValuesCarryCurrentDefaultAndSyncedState()

	/**
	 * A malformed colour is refused with the validator's own message, and
	 * nothing is written — not the colours, not the images, not the counter.
	 *
	 * @spec openspec/specs/theming-sync/spec.md
	 */
	public function testAnInvalidColourIsRefusedBeforeAnythingIsApplied(): void {
		$this->withParams(['primary_color' => 'not-a-colour']);
		$this->themingService->method('validateColors')->willReturn('Invalid hex color for primary_color: not-a-colour');
		$this->themingService->expects($this->never())->method('applyColors');
		$this->themingService->expects($this->never())->method('applyImages');

		$response = $this->controller->updateThemingValues();

		$this->assertSame(400, $response->getStatus());
		$this->assertSame('Invalid hex color for primary_color: not-a-colour', $response->getData()['error']);
		$this->assertArrayNotHasKey('thematiq|theming_syncs_total', $this->appConfig);
		$this->assertSame([], $this->auditedActions());
	}//end testAnInvalidColourIsRefusedBeforeAnythingIsApplied()

	/**
	 * An image path that fails validation is refused the same way. The colour
	 * check has already passed at this point, so this is the case where a
	 * half-applied theme would be easiest to ship by accident.
	 *
	 * @spec openspec/specs/theming-sync/spec.md
	 */
	public function testAnInvalidImagePathIsRefusedBeforeAnythingIsApplied(): void {
		$this->withParams(['logo' => '../../etc/passwd']);
		$this->themingService->method('validateColors')->willReturn(null);
		$this->themingService->method('validateImagePaths')->willReturn('Invalid image path for logo: path traversal not allowed');
		$this->themingService->expects($this->never())->method('applyColors');
		$this->themingService->expects($this->never())->method('applyImages');

		$response = $this->controller->updateThemingValues();

		$this->assertSame(400, $response->getStatus());
		$this->assertStringContainsString('path traversal', $response->getData()['error']);
		$this->assertSame([], $this->auditedActions());
	}//end testAnInvalidImagePathIsRefusedBeforeAnythingIsApplied()

	/**
	 * A successful apply reports what it changed, RECORDS which file went
	 * into each image slot, counts the sync, and audits it with the state it
	 * replaced.
	 *
	 * @spec openspec/specs/theming-sync/spec.md
	 */
	public function testASuccessfulApplyRecordsTheSyncedImageAndCountsIt(): void {
		$this->withParams(['primary_color' => '#154273', 'logo' => 'img/logos/rijkshuisstijl.svg']);
		$this->themingService->method('validateColors')->willReturn(null);
		$this->themingService->method('validateImagePaths')->willReturn(null);
		$this->themingService->method('applyColors')->willReturn(['primary_color']);
		$this->themingService->method('applyImages')->willReturn(['logo']);

		$response = $this->controller->updateThemingValues();

		$this->assertSame(200, $response->getStatus());
		$this->assertSame('ok', $response->getData()['status']);
		$this->assertSame(['primary_color', 'logo'], $response->getData()['updated']);

		$this->assertSame('img/logos/rijkshuisstijl.svg', $this->appConfig['thematiq|synced_logo']);
		$this->assertSame('1', $this->appConfig['thematiq|theming_syncs_total']);
		$this->assertSame(['theming_sync_applied'], $this->auditedActions());
		$this->assertArrayHasKey('old', $this->audited[0][1]);
	}//end testASuccessfulApplyRecordsTheSyncedImageAndCountsIt()

	/**
	 * The audit entry's `old` and `new` are both SNAPSHOTS, so the trail can
	 * say what a sync changed.
	 *
	 * `new` used to be the list of field names that were written, while `old`
	 * was a keyed snapshot. ThemingAuditService diffs the two to fill the
	 * entry's `changed` list, and diffing a map's keys against a list's
	 * indices named every snapshot field on every sync plus the integers 0, 1
	 * and 2 — so the field a reader relies on to see what happened carried
	 * nothing but noise. The response still carries the list; the audit entry
	 * carries before and after.
	 *
	 * @spec openspec/specs/theming-audit/spec.md#requirement-append-only-audit-entries
	 */
	public function testTheSyncAuditEntryComparesTwoSnapshots(): void {
		$this->withParams(['primary_color' => '#154273']);
		$this->themingService->method('validateColors')->willReturn(null);
		$this->themingService->method('validateImagePaths')->willReturn(null);
		$this->themingService->method('applyColors')->willReturn(['primary_color']);
		$this->themingService->method('applyImages')->willReturn([]);

		$this->controller->updateThemingValues();

		$context = $this->audited[0][1];

		$this->assertArrayHasKey('old', $context);
		$this->assertArrayHasKey('new', $context);

		// Both sides carry the snapshot's own keys, and neither is a list.
		$this->assertArrayHasKey('primary_color', $context['old']);
		$this->assertArrayHasKey('primary_color', $context['new']);
		$this->assertSame(array_keys($context['old']), array_keys($context['new']));
		$this->assertNotSame(
			array_keys($context['new']),
			range(0, (count($context['new']) - 1)),
			'`new` must be a snapshot, not a list of written field names.'
		);
	}//end testTheSyncAuditEntryComparesTwoSnapshots()

	/**
	 * The sync counter accumulates rather than being re-set to one; it is the
	 * value `MetricsController` exposes as `nldesign_theming_syncs_total`.
	 *
	 * @spec openspec/specs/theming-sync/spec.md
	 */
	public function testTheSyncCounterAccumulates(): void {
		$this->appConfig['thematiq|theming_syncs_total'] = '7';
		$this->withParams(['primary_color' => '#154273']);
		$this->themingService->method('validateColors')->willReturn(null);
		$this->themingService->method('validateImagePaths')->willReturn(null);
		$this->themingService->method('applyColors')->willReturn(['primary_color']);
		$this->themingService->method('applyImages')->willReturn([]);

		$this->controller->updateThemingValues();

		$this->assertSame('8', $this->appConfig['thematiq|theming_syncs_total']);
	}//end testTheSyncCounterAccumulates()

	/**
	 * `reset=1` undoes everything the sync can write and forgets which images
	 * were synced — stock Nextcloud is the ABSENCE of a synced theme, not a
	 * set of values to write.
	 *
	 * @spec openspec/changes/apply-without-reload/specs/theming-sync/spec.md
	 */
	public function testResetUndoesTheSyncAndForgetsTheSyncedImages(): void {
		$this->appConfig['thematiq|synced_logo'] = 'img/logos/rijkshuisstijl.svg';
		$this->appConfig['thematiq|synced_background'] = 'img/backgrounds/rijk.jpg';

		$this->withParams(['reset' => '1']);
		$this->themingService->expects($this->once())
			->method('resetToDefaults')
			->willReturn(ThemingService::RESETTABLE);
		$this->themingService->expects($this->never())->method('applyColors');
		$this->themingService->expects($this->never())->method('applyImages');

		$response = $this->controller->updateThemingValues();

		$this->assertSame(200, $response->getStatus());
		$this->assertSame('ok', $response->getData()['status']);
		$this->assertSame(ThemingService::RESETTABLE, $response->getData()['reset']);

		$this->assertArrayNotHasKey('thematiq|synced_logo', $this->appConfig);
		$this->assertArrayNotHasKey('thematiq|synced_background', $this->appConfig);
		$this->assertSame(['theming_sync_reset'], $this->auditedActions());
	}//end testResetUndoesTheSyncAndForgetsTheSyncedImages()

	/**
	 * A reset is not counted as a sync: the counter measures themes pushed
	 * into core, and a reset pushes none.
	 *
	 * @spec openspec/changes/apply-without-reload/specs/theming-sync/spec.md
	 */
	public function testResetDoesNotCountAsASync(): void {
		$this->withParams(['reset' => '1']);
		$this->themingService->method('resetToDefaults')->willReturn(ThemingService::RESETTABLE);

		$this->controller->updateThemingValues();

		$this->assertArrayNotHasKey('thematiq|theming_syncs_total', $this->appConfig);
	}//end testResetDoesNotCountAsASync()

	/**
	 * An absent, empty or `"0"` reset parameter is an ordinary apply. A theme
	 * apply that silently undid itself would read as a sync that did nothing,
	 * which is the hardest kind of failure to attribute.
	 *
	 * @param array<string, mixed> $params The request parameters.
	 *
	 * @dataProvider notAResetProvider
	 *
	 * @spec openspec/changes/apply-without-reload/specs/theming-sync/spec.md
	 */
	public function testOnlyATruthyResetParameterResets(array $params): void {
		$this->withParams($params);
		$this->themingService->method('validateColors')->willReturn(null);
		$this->themingService->method('validateImagePaths')->willReturn(null);
		$this->themingService->method('applyColors')->willReturn(['primary_color']);
		$this->themingService->method('applyImages')->willReturn([]);
		$this->themingService->expects($this->never())->method('resetToDefaults');

		$response = $this->controller->updateThemingValues();

		$this->assertSame(200, $response->getStatus());
		$this->assertArrayHasKey('updated', $response->getData());
		$this->assertArrayNotHasKey('reset', $response->getData());
		$this->assertSame(['theming_sync_applied'], $this->auditedActions());
	}//end testOnlyATruthyResetParameterResets()

	/**
	 * Parameter sets that must NOT be read as a reset.
	 *
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function notAResetProvider(): array {
		return [
			'no reset key' => [['primary_color' => '#154273']],
			'empty string' => [['primary_color' => '#154273', 'reset' => '']],
			'string zero' => [['primary_color' => '#154273', 'reset' => '0']],
		];
	}//end notAResetProvider()
}//end class
