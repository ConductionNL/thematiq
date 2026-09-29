<?php

/**
 * Unit tests for ScheduledSwitchService: planning, applying, reverting and
 * cancelling timed token set switches.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/apply-scheduled-theme-switch/specs/scheduled-switch/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\Exception\ScheduledSwitchException;
use OCA\Thematiq\Service\Exception\ScheduledSwitchNotFoundException;
use OCA\Thematiq\Service\ScheduledCoreThemingSync;
use OCA\Thematiq\Service\ScheduledSwitchService;
use OCA\Thematiq\Service\ScheduledSwitchStore;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Service\TokenSetService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The real store, the real active-set service and the real core sync run on
 * an in-memory app config; the token set catalogue, core theming and the
 * audit trail are doubles so each test can see exactly what was written.
 */
class ScheduledSwitchServiceTest extends TestCase {

	/**
	 * In-memory app config, keyed "app/key".
	 *
	 * @var array<string, string>
	 */
	private array $store = [];

	/**
	 * Token sets that exist on the "filesystem".
	 *
	 * @var array<int, string>
	 */
	private array $sets = ['rijkshuisstijl', 'koningsdag-oranje', 'custom-campagne', 'nextcloud'];

	/**
	 * The current time, in seconds since the epoch.
	 *
	 * @var int
	 */
	private int $now = 0;

	/**
	 * Every audit call: [action, context].
	 *
	 * @var array<int, array{0: string, 1: array<string, mixed>}>
	 */
	private array $audit = [];

	/**
	 * The core theming double.
	 *
	 * @var ThemingService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $theming;

	/**
	 * The service under test.
	 *
	 * @var ScheduledSwitchService
	 */
	private ScheduledSwitchService $service;

	/**
	 * Build the service on the doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->store = ['thematiq/token_set' => 'rijkshuisstijl'];
		$this->now = (int)strtotime('2027-04-20T12:00:00Z');

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->store[$app . '/' . $key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->store[$app . '/' . $key] = (string)$value;
			}
		);
		$config->method('deleteAppValue')->willReturnCallback(
			function (string $app, string $key): void {
				unset($this->store[$app . '/' . $key]);
			}
		);

		$tokenSets = $this->createMock(TokenSetService::class);
		$tokenSets->method('isValidTokenSet')->willReturnCallback(
			fn (string $id) => in_array($id, $this->sets, true)
		);
		$tokenSets->method('getAvailableTokenSets')->willReturnCallback(
			fn () => [
				['id' => 'rijkshuisstijl', 'theming' => ['primary_color' => '#154273', 'logo' => 'img/logos/rijkshuisstijl.svg']],
				['id' => 'koningsdag-oranje', 'theming' => ['primary_color' => '#FF6600', 'background_color' => '#FFFFFF']],
			]
		);

		$auditService = $this->createMock(ThemingAuditService::class);
		$auditService->method('log')->willReturnCallback(
			function (string $action, array $context = []): void {
				$this->audit[] = [$action, $context];
			}
		);

		$time = $this->createMock(ITimeFactory::class);
		$time->method('getTime')->willReturnCallback(fn () => $this->now);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(
			function (string $text, $params = []): string {
				foreach ((array)$params as $key => $value) {
					$text = str_replace('{' . $key . '}', (string)$value, $text);
				}

				return $text;
			}
		);

		$this->theming = $this->createMock(ThemingService::class);

		$this->service = new ScheduledSwitchService(
			new ScheduledSwitchStore($config),
			new ActiveTokenSetService($config, $tokenSets, $auditService),
			$tokenSets,
			new ScheduledCoreThemingSync($this->theming, $tokenSets, $config),
			$auditService,
			$config,
			$time,
			$l10n,
			$this->createMock(LoggerInterface::class)
		);
	}//end setUp()

	/**
	 * Move the clock to an ISO 8601 time.
	 *
	 * @param string $iso The time.
	 *
	 * @return void
	 */
	private function at(string $iso): void {
		$this->now = (int)strtotime($iso);
	}//end at()

	/**
	 * Plan the King's Day switch used by most tests.
	 *
	 * @param bool $sync Whether to sync core theming.
	 *
	 * @return array<string, mixed> The planned entry.
	 */
	private function planKingsDay(bool $sync = false): array {
		return $this->service->create(
			tokenSet: 'koningsdag-oranje',
			startAt: '2027-04-26T18:00:00+02:00',
			endAt: '2027-04-28T08:00:00+02:00',
			syncCoreTheming: $sync,
			createdBy: 'admin'
		);
	}//end planKingsDay()

	/**
	 * Scenario "An administrator plans a campaign look": the switch is
	 * listed with UTC times and the active set does not change.
	 */
	public function testPlanningStoresTheSwitchAndChangesNothingYet(): void {
		$entry = $this->planKingsDay();

		$this->assertSame('2027-04-26T16:00:00Z', $entry['startAt']);
		$this->assertSame('2027-04-28T06:00:00Z', $entry['endAt']);
		$this->assertSame('planned', $entry['status']);
		$this->assertSame('admin', $entry['createdBy']);
		$this->assertCount(1, $this->service->list());
		$this->assertSame('rijkshuisstijl', $this->store['thematiq/token_set']);
		$this->assertSame([], $this->audit);
	}//end testPlanningStoresTheSwitchAndChangesNothingYet()

	/**
	 * Scenario "Overlapping plans are refused".
	 */
	public function testOverlappingPlanIsRefused(): void {
		$this->service->create(tokenSet: 'koningsdag-oranje', startAt: '2027-05-01T00:00:00Z', endAt: '2027-05-07T00:00:00Z', syncCoreTheming: false, createdBy: 'admin');

		try {
			$this->service->create(tokenSet: 'custom-campagne', startAt: '2027-05-05T00:00:00Z', endAt: '2027-05-10T00:00:00Z', syncCoreTheming: false, createdBy: 'admin');
			$this->fail('An overlapping window was accepted.');
		} catch (ScheduledSwitchException $e) {
			$this->assertStringContainsString('overlaps', $e->getMessage());
		}

		$this->assertCount(1, $this->service->list());
	}//end testOverlappingPlanIsRefused()

	/**
	 * A switch without an end overlaps every later window.
	 */
	public function testOpenEndedSwitchOverlapsEveryLaterWindow(): void {
		$this->service->create(tokenSet: 'koningsdag-oranje', startAt: '2027-05-01T00:00:00Z', endAt: null, syncCoreTheming: false, createdBy: 'admin');

		$this->expectException(ScheduledSwitchException::class);
		$this->service->create(tokenSet: 'custom-campagne', startAt: '2027-06-01T00:00:00Z', endAt: '2027-06-02T00:00:00Z', syncCoreTheming: false, createdBy: 'admin');
	}//end testOpenEndedSwitchOverlapsEveryLaterWindow()

	/**
	 * An unknown token set is refused.
	 */
	public function testUnknownTokenSetIsRefused(): void {
		$this->expectException(ScheduledSwitchException::class);
		$this->expectExceptionMessage('does not exist');
		$this->service->create(tokenSet: 'bestaat-niet', startAt: '2027-05-01T00:00:00Z', endAt: null, syncCoreTheming: false, createdBy: 'admin');
	}//end testUnknownTokenSetIsRefused()

	/**
	 * An end that is not after the start is refused.
	 */
	public function testEndBeforeStartIsRefused(): void {
		$this->expectException(ScheduledSwitchException::class);
		$this->expectExceptionMessage('after the start');
		$this->service->create(tokenSet: 'koningsdag-oranje', startAt: '2027-05-01T00:00:00Z', endAt: '2027-05-01T00:00:00Z', syncCoreTheming: false, createdBy: 'admin');
	}//end testEndBeforeStartIsRefused()

	/**
	 * A time that does not parse is refused.
	 */
	public function testUnparsableTimeIsRefused(): void {
		$this->expectException(ScheduledSwitchException::class);
		$this->service->create(tokenSet: 'koningsdag-oranje', startAt: 'next tuesday-ish', endAt: null, syncCoreTheming: false, createdBy: 'admin');
	}//end testUnparsableTimeIsRefused()

	/**
	 * Scenario "The look starts and ends on time", plus theming-audit "A
	 * start and an end leave two entries".
	 */
	public function testTheLookStartsAndEndsOnTime(): void {
		$entry = $this->planKingsDay();

		$this->at('2027-04-26T16:03:00Z');
		$this->service->runDue();

		$this->assertSame('koningsdag-oranje', $this->store['thematiq/token_set']);
		$running = $this->service->list()[0];
		$this->assertSame('running', $running['status']);
		$this->assertSame('rijkshuisstijl', $running['revertTo']);

		$this->at('2027-04-28T06:02:00Z');
		$this->service->runDue();

		$this->assertSame('rijkshuisstijl', $this->store['thematiq/token_set']);
		$this->assertSame([], $this->service->list());

		$this->assertCount(2, $this->audit);
		$this->assertSame('scheduled_switch_applied', $this->audit[0][0]);
		$this->assertSame(
			['old' => 'rijkshuisstijl', 'new' => 'koningsdag-oranje', 'actor' => 'system', 'switchId' => $entry['id']],
			$this->audit[0][1]
		);
		$this->assertSame('scheduled_switch_applied', $this->audit[1][0]);
		$this->assertSame('koningsdag-oranje', $this->audit[1][1]['old']);
		$this->assertSame('rijkshuisstijl', $this->audit[1][1]['new']);
		$this->assertSame('system', $this->audit[1][1]['actor']);
	}//end testTheLookStartsAndEndsOnTime()

	/**
	 * Before its start a planned switch is left alone.
	 */
	public function testNothingHappensBeforeTheStart(): void {
		$this->planKingsDay();

		$this->at('2027-04-26T15:59:00Z');
		$this->service->runDue();

		$this->assertSame('rijkshuisstijl', $this->store['thematiq/token_set']);
		$this->assertSame('planned', $this->service->list()[0]['status']);
		$this->assertSame([], $this->audit);
	}//end testNothingHappensBeforeTheStart()

	/**
	 * A switch without an end is done once applied and leaves the list.
	 */
	public function testOpenEndedSwitchLeavesTheListOnceApplied(): void {
		$this->service->create(tokenSet: 'koningsdag-oranje', startAt: '2027-04-21T00:00:00Z', endAt: null, syncCoreTheming: false, createdBy: 'admin');

		$this->at('2027-04-21T00:04:00Z');
		$this->service->runDue();

		$this->assertSame('koningsdag-oranje', $this->store['thematiq/token_set']);
		$this->assertSame([], $this->service->list());
	}//end testOpenEndedSwitchLeavesTheListOnceApplied()

	/**
	 * Scenario "A switch to a deleted set fails visibly".
	 */
	public function testASwitchToADeletedSetFailsVisibly(): void {
		$entry = $this->service->create(tokenSet: 'custom-campagne', startAt: '2027-04-21T00:00:00Z', endAt: '2027-04-22T00:00:00Z', syncCoreTheming: false, createdBy: 'admin');
		$this->sets = ['rijkshuisstijl', 'koningsdag-oranje', 'nextcloud'];

		$this->at('2027-04-21T00:03:00Z');
		$this->service->runDue();

		$this->assertSame('rijkshuisstijl', $this->store['thematiq/token_set']);
		$listed = $this->service->list()[0];
		$this->assertSame('failed', $listed['status']);
		$this->assertStringContainsString('custom-campagne', $listed['failureReason']);

		$this->assertCount(1, $this->audit);
		$this->assertSame('scheduled_switch_applied', $this->audit[0][0]);
		$this->assertSame('rijkshuisstijl', $this->audit[0][1]['old']);
		$this->assertNull($this->audit[0][1]['new']);
		$this->assertSame('system', $this->audit[0][1]['actor']);
		$this->assertSame($entry['id'], $this->audit[0][1]['switchId']);
		$this->assertSame($listed['failureReason'], $this->audit[0][1]['reason']);

		// A failed switch is not retried on the next run.
		$this->service->runDue();
		$this->assertCount(1, $this->audit);
	}//end testASwitchToADeletedSetFailsVisibly()

	/**
	 * Scenario "A switch without core sync keeps the core logo".
	 */
	public function testASwitchWithoutCoreSyncLeavesCoreThemingAlone(): void {
		$this->planKingsDay(sync: false);

		$this->theming->expects($this->never())->method('applyColors');
		$this->theming->expects($this->never())->method('applyImages');
		$this->theming->expects($this->never())->method('resetToDefaults');

		$this->at('2027-04-26T16:03:00Z');
		$this->service->runDue();
		$this->at('2027-04-28T06:03:00Z');
		$this->service->runDue();
	}//end testASwitchWithoutCoreSyncLeavesCoreThemingAlone()

	/**
	 * With core sync ticked, the set's theming block is applied at the start
	 * and the previous set's block at the end.
	 */
	public function testASwitchWithCoreSyncAppliesTheSetsThemingBothWays(): void {
		$this->planKingsDay(sync: true);

		$applied = [];
		$this->theming->method('validateColors')->willReturn(null);
		$this->theming->method('validateImagePaths')->willReturn(null);
		$this->theming->method('applyColors')->willReturnCallback(
			function (array $params) use (&$applied): array {
				$applied[] = $params;
				return array_keys(array_intersect_key($params, ['primary_color' => 1, 'background_color' => 1]));
			}
		);
		$this->theming->method('applyImages')->willReturnCallback(
			fn (array $params): array => array_keys(array_intersect_key($params, ['logo' => 1]))
		);

		$this->at('2027-04-26T16:03:00Z');
		$this->service->runDue();

		$this->assertSame(['primary_color' => '#FF6600', 'background_color' => '#FFFFFF'], $applied[0]);
		$this->assertSame(['primary_color', 'background_color'], $this->audit[0][1]['coreThemingSynced']);

		$this->at('2027-04-28T06:03:00Z');
		$this->service->runDue();

		$this->assertSame(['primary_color' => '#154273', 'logo' => 'img/logos/rijkshuisstijl.svg'], $applied[1]);
		$this->assertSame('img/logos/rijkshuisstijl.svg', $this->store['thematiq/synced_logo']);
	}//end testASwitchWithCoreSyncAppliesTheSetsThemingBothWays()

	/**
	 * Cancelling a planned switch removes it without touching the active set.
	 */
	public function testCancellingAPlannedSwitchRemovesIt(): void {
		$entry = $this->planKingsDay();

		$this->service->cancel(id: $entry['id']);

		$this->assertSame([], $this->service->list());
		$this->assertSame('rijkshuisstijl', $this->store['thematiq/token_set']);
		$this->assertSame([], $this->audit);
	}//end testCancellingAPlannedSwitchRemovesIt()

	/**
	 * Scenario "Cancelling a running campaign".
	 */
	public function testCancellingARunningCampaignSwitchesBackAtOnce(): void {
		$entry = $this->planKingsDay();
		$this->at('2027-04-26T16:03:00Z');
		$this->service->runDue();

		$this->at('2027-04-27T10:00:00Z');
		$this->service->cancel(id: $entry['id']);

		$this->assertSame('rijkshuisstijl', $this->store['thematiq/token_set']);
		$this->assertSame([], $this->service->list());
		$this->assertSame('rijkshuisstijl', $this->audit[1][1]['new']);
	}//end testCancellingARunningCampaignSwitchesBackAtOnce()

	/**
	 * Cancelling an id that is not planned says so.
	 */
	public function testCancellingAnUnknownSwitchIsNotFound(): void {
		$this->expectException(ScheduledSwitchNotFoundException::class);
		$this->service->cancel(id: 'nope');
	}//end testCancellingAnUnknownSwitchIsNotFound()

	/**
	 * Scenario "An administrator on AJAX cron is warned", and the status
	 * names the last run and what is active until when.
	 */
	public function testStatusNamesTheLastRunTheCronModeAndTheActiveWindow(): void {
		$this->store['core/backgroundjobs_mode'] = 'ajax';
		$this->assertTrue($this->service->getStatus()['cronWarning']);
		$this->assertNull($this->service->getStatus()['lastRun']);

		$this->planKingsDay();
		$this->at('2027-04-26T16:03:00Z');
		$this->service->runDue();

		$status = $this->service->getStatus();
		$this->assertSame('2027-04-26T16:03:00Z', $status['lastRun']);
		$this->assertSame('koningsdag-oranje', $status['activeTokenSet']);
		$this->assertSame('2027-04-28T06:00:00Z', $status['activeUntil']);
		$this->assertSame('rijkshuisstijl', $status['revertTo']);

		$this->store['core/backgroundjobs_mode'] = 'cron';
		$this->assertFalse($this->service->getStatus()['cronWarning']);
	}//end testStatusNamesTheLastRunTheCronModeAndTheActiveWindow()
}//end class
