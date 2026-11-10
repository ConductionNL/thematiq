<?php

/**
 * Unit tests for ScheduledSwitchService: planning, applying, reverting and
 * cancelling timed token set switches.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/scheduled-switch/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\BrandingCaptureService;
use OCA\Thematiq\Service\Exception\ScheduledSwitchException;
use OCA\Thematiq\Service\Exception\ScheduledSwitchNotFoundException;
use OCA\Thematiq\Service\ScheduledCoreThemingSync;
use OCA\Thematiq\Service\ScheduledSwitchRuntime;
use OCA\Thematiq\Service\ScheduledSwitchService;
use OCA\Thematiq\Service\ScheduledSwitchStore;
use OCA\Thematiq\Service\ScheduledSwitchTargets;
use OCA\Thematiq\Service\SwitchPlan;
use OCA\Thematiq\Service\SwitchWindow;
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
	 * The core theming snapshots the capture double keeps, by key.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $snapshots = [];

	/**
	 * What core theming holds when a snapshot is taken: an administrator's own
	 * primary colour, with Nextcloud's default background.
	 *
	 * @var array<string, mixed>
	 */
	private array $coreTheming = ['captured' => true, 'primary_color' => '#0082C9', 'background_mode' => 'default'];

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
	 * Every warning the service logged: [message, context].
	 *
	 * @var array<int, array{0: string, 1: array<string, mixed>}>
	 */
	private array $warnings = [];

	/**
	 * Build the service on the doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->store = ['thematiq/token_set' => 'rijkshuisstijl'];
		$this->now = (int)strtotime('2027-04-20T12:00:00Z');

		$config = $this->createMock(originalClassName: IConfig::class);
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

		$tokenSets = $this->createMock(originalClassName: TokenSetService::class);
		$tokenSets->method('isValidTokenSet')->willReturnCallback(
			fn (string $id) => in_array($id, $this->sets, true)
		);
		$tokenSets->method('getAvailableTokenSets')->willReturnCallback(
			fn () => [
				['id' => 'rijkshuisstijl', 'theming' => ['primary_color' => '#154273', 'logo' => 'img/logos/rijkshuisstijl.svg']],
				['id' => 'koningsdag-oranje', 'theming' => ['primary_color' => '#FF6600', 'background_color' => '#FFFFFF']],
			]
		);

		$auditService = $this->createMock(originalClassName: ThemingAuditService::class);
		$auditService->method('log')->willReturnCallback(
			function (string $action, array $context = []): void {
				$this->audit[] = [$action, $context];
			}
		);

		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('getTime')->willReturnCallback(fn () => $this->now);

		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnCallback(
			function (string $text, $params = []): string {
				foreach ((array)$params as $key => $value) {
					$text = str_replace('{' . $key . '}', (string)$value, $text);
				}

				return $text;
			}
		);

		$this->theming = $this->createMock(originalClassName: ThemingService::class);

		$branding = $this->createMock(originalClassName: BrandingCaptureService::class);
		$branding->method('capture')->willReturnCallback(
			function (string $setId): array {
				$this->snapshots[$setId] = $this->coreTheming;
				return $this->coreTheming;
			}
		);
		$branding->method('all')->willReturnCallback(fn () => $this->snapshots);
		$branding->method('forget')->willReturnCallback(
			function (string $setId): void {
				unset($this->snapshots[$setId]);
			}
		);

		$this->service = new ScheduledSwitchService(
			store: new ScheduledSwitchStore(config: $config),
			targets: new ScheduledSwitchTargets(
				activeTokenSet: new ActiveTokenSetService(config: $config, tokenSets: $tokenSets, auditService: $auditService),
				tokenSets: $tokenSets,
				coreSync: new ScheduledCoreThemingSync(themingService: $this->theming, tokenSets: $tokenSets, config: $config, branding: $branding)
			),
			runtime: new ScheduledSwitchRuntime(
				auditService: $auditService,
				config: $config,
				time: $time,
				logger: $this->warningLogger()
			),
			l10n: $l10n
		);
	}//end setUp()

	/**
	 * Move the clock to an ISO 8601 time.
	 *
	 * @param string $iso The time.
	 *
	 * @return void
	 */
	/**
	 * A logger that records its warnings.
	 *
	 * @return LoggerInterface The logger.
	 */
	private function warningLogger(): LoggerInterface {
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->method('warning')->willReturnCallback(
			function (string $message, array $context = []): void {
				$this->warnings[] = [$message, $context];
			}
		);

		return $logger;
	}//end warningLogger()

	private function at(string $iso): void {
		$this->now = (int)strtotime($iso);
	}//end at()

	/**
	 * Plan the King's Day switch used by most tests.
	 *
	 * @return array<string, mixed> The planned entry.
	 */
	private function planKingsDay(): array {
		return $this->service->create(plan: new SwitchPlan(tokenSet: 'koningsdag-oranje', window: new SwitchWindow(startAt: '2027-04-26T18:00:00+02:00', endAt: '2027-04-28T08:00:00+02:00')), createdBy: 'admin');
	}//end planKingsDay()

	/**
	 * Scenario "An administrator plans a campaign look": the switch is
	 * listed with UTC times and the active set does not change.
	 *
	 * @return void
	 */
	public function testPlanningStoresTheSwitchAndChangesNothingYet(): void {
		$entry = $this->planKingsDay();

		$this->assertSame(expected: '2027-04-26T16:00:00Z', actual: $entry['startAt']);
		$this->assertSame(expected: '2027-04-28T06:00:00Z', actual: $entry['endAt']);
		$this->assertSame(expected: 'planned', actual: $entry['status']);
		$this->assertSame(expected: 'admin', actual: $entry['createdBy']);
		$this->assertCount(expectedCount: 1, haystack: $this->service->list());
		$this->assertSame(expected: 'rijkshuisstijl', actual: $this->store['thematiq/token_set']);
		$this->assertSame(expected: [], actual: $this->audit);
	}//end testPlanningStoresTheSwitchAndChangesNothingYet()

	/**
	 * Scenario "Overlapping plans are refused".
	 *
	 * @return void
	 */
	public function testOverlappingPlanIsRefused(): void {
		$this->service->create(plan: new SwitchPlan(tokenSet: 'koningsdag-oranje', window: new SwitchWindow(startAt: '2027-05-01T00:00:00Z', endAt: '2027-05-07T00:00:00Z')), createdBy: 'admin');

		try {
			$this->service->create(plan: new SwitchPlan(tokenSet: 'custom-campagne', window: new SwitchWindow(startAt: '2027-05-05T00:00:00Z', endAt: '2027-05-10T00:00:00Z')), createdBy: 'admin');
			$this->fail(message: 'An overlapping window was accepted.');
		} catch (ScheduledSwitchException $e) {
			$this->assertStringContainsString(needle: 'overlaps', haystack: $e->getMessage());
		}

		$this->assertCount(expectedCount: 1, haystack: $this->service->list());
	}//end testOverlappingPlanIsRefused()

	/**
	 * A switch without an end overlaps every later window.
	 *
	 * @return void
	 */
	public function testOpenEndedSwitchOverlapsEveryLaterWindow(): void {
		$this->service->create(plan: new SwitchPlan(tokenSet: 'koningsdag-oranje', window: new SwitchWindow(startAt: '2027-05-01T00:00:00Z', endAt: null)), createdBy: 'admin');

		$this->expectException(exception: ScheduledSwitchException::class);
		$this->service->create(plan: new SwitchPlan(tokenSet: 'custom-campagne', window: new SwitchWindow(startAt: '2027-06-01T00:00:00Z', endAt: '2027-06-02T00:00:00Z')), createdBy: 'admin');
	}//end testOpenEndedSwitchOverlapsEveryLaterWindow()

	/**
	 * An unknown token set is refused.
	 *
	 * @return void
	 */
	public function testUnknownTokenSetIsRefused(): void {
		$this->expectException(exception: ScheduledSwitchException::class);
		$this->expectExceptionMessage(message: 'does not exist');
		$this->service->create(plan: new SwitchPlan(tokenSet: 'bestaat-niet', window: new SwitchWindow(startAt: '2027-05-01T00:00:00Z', endAt: null)), createdBy: 'admin');
	}//end testUnknownTokenSetIsRefused()

	/**
	 * An end that is not after the start is refused.
	 *
	 * @return void
	 */
	public function testEndBeforeStartIsRefused(): void {
		$this->expectException(exception: ScheduledSwitchException::class);
		$this->expectExceptionMessage(message: 'after the start');
		$this->service->create(plan: new SwitchPlan(tokenSet: 'koningsdag-oranje', window: new SwitchWindow(startAt: '2027-05-01T00:00:00Z', endAt: '2027-05-01T00:00:00Z')), createdBy: 'admin');
	}//end testEndBeforeStartIsRefused()

	/**
	 * A window that is already over is refused, so it is not applied and
	 * reverted on the next run; one that is still running is accepted.
	 *
	 * @return void
	 */
	public function testAWindowThatIsAlreadyOverIsRefused(): void {
		try {
			$this->service->create(plan: new SwitchPlan(tokenSet: 'koningsdag-oranje', window: new SwitchWindow(startAt: '2027-04-19T08:00:00Z', endAt: '2027-04-20T12:00:00Z')), createdBy: 'admin');
			$this->fail(message: 'A window that ended was accepted.');
		} catch (ScheduledSwitchException $e) {
			$this->assertStringContainsString(needle: 'in the future', haystack: $e->getMessage());
		}

		$this->assertSame(expected: [], actual: $this->service->list());

		$entry = $this->service->create(plan: new SwitchPlan(tokenSet: 'koningsdag-oranje', window: new SwitchWindow(startAt: '2027-04-19T08:00:00Z', endAt: '2027-04-21T08:00:00Z')), createdBy: 'admin');
		$this->assertSame(expected: 'planned', actual: $entry['status']);
	}//end testAWindowThatIsAlreadyOverIsRefused()

	/**
	 * A time that does not parse is refused.
	 *
	 * @return void
	 */
	public function testUnparsableTimeIsRefused(): void {
		$this->expectException(exception: ScheduledSwitchException::class);
		$this->service->create(plan: new SwitchPlan(tokenSet: 'koningsdag-oranje', window: new SwitchWindow(startAt: 'next tuesday-ish', endAt: null)), createdBy: 'admin');
	}//end testUnparsableTimeIsRefused()

	/**
	 * Scenario "The look starts and ends on time", plus theming-audit "A
	 * start and an end leave two entries".
	 *
	 * @return void
	 */
	public function testTheLookStartsAndEndsOnTime(): void {
		$entry = $this->planKingsDay();

		$this->at(iso: '2027-04-26T16:03:00Z');
		$this->service->runDue();

		$this->assertSame(expected: 'koningsdag-oranje', actual: $this->store['thematiq/token_set']);
		$running = $this->service->list()[0];
		$this->assertSame(expected: 'running', actual: $running['status']);
		$this->assertSame(expected: 'rijkshuisstijl', actual: $running['revertTo']);

		$this->at(iso: '2027-04-28T06:02:00Z');
		$this->service->runDue();

		$this->assertSame(expected: 'rijkshuisstijl', actual: $this->store['thematiq/token_set']);
		$this->assertSame(expected: [], actual: $this->service->list());

		$this->assertCount(expectedCount: 2, haystack: $this->audit);
		$this->assertSame(expected: 'scheduled_switch_applied', actual: $this->audit[0][0]);
		$this->assertSame(
			expected: ['old' => 'rijkshuisstijl', 'new' => 'koningsdag-oranje', 'actor' => 'system', 'switchId' => $entry['id'], 'coreThemingSynced' => []],
			actual: $this->audit[0][1]
		);
		$this->assertSame(expected: 'scheduled_switch_applied', actual: $this->audit[1][0]);
		$this->assertSame(expected: 'koningsdag-oranje', actual: $this->audit[1][1]['old']);
		$this->assertSame(expected: 'rijkshuisstijl', actual: $this->audit[1][1]['new']);
		$this->assertSame(expected: 'system', actual: $this->audit[1][1]['actor']);
	}//end testTheLookStartsAndEndsOnTime()

	/**
	 * Two back-to-back campaigns planned out of order (#825): the later one is
	 * stored first. When the first one's end and the second one's start are
	 * due in the same run, the end must go first, so the second campaign
	 * snapshots and returns to the administrator's own look, not the first
	 * campaign's.
	 *
	 * @return void
	 */
	public function testBackToBackSwitchesPlannedOutOfOrderReturnToTheOriginalLook(): void {
		$later = $this->service->create(plan: new SwitchPlan(tokenSet: 'custom-campagne', window: new SwitchWindow(startAt: '2027-05-01T17:00:00Z', endAt: '2027-05-01T19:00:00Z')), createdBy: 'admin');
		$this->service->create(plan: new SwitchPlan(tokenSet: 'koningsdag-oranje', window: new SwitchWindow(startAt: '2027-05-01T15:00:00Z', endAt: '2027-05-01T17:00:00Z')), createdBy: 'admin');

		$this->at(iso: '2027-05-01T15:01:00Z');
		$this->service->runDue();
		$this->assertSame(expected: 'koningsdag-oranje', actual: $this->store['thematiq/token_set']);

		// One run, both due: the first campaign's end and the second one's start.
		$this->at(iso: '2027-05-01T17:05:00Z');
		$this->service->runDue();

		$this->assertSame(expected: 'custom-campagne', actual: $this->store['thematiq/token_set']);
		$running = $this->service->list();
		$this->assertCount(expectedCount: 1, haystack: $running);
		$this->assertSame(expected: $later['id'], actual: $running[0]['id']);
		$this->assertSame(expected: 'rijkshuisstijl', actual: $running[0]['revertTo']);

		$this->at(iso: '2027-05-01T19:05:00Z');
		$this->service->runDue();

		$this->assertSame(expected: 'rijkshuisstijl', actual: $this->store['thematiq/token_set']);
		$this->assertSame(expected: [], actual: $this->service->list());
	}//end testBackToBackSwitchesPlannedOutOfOrderReturnToTheOriginalLook()

	/**
	 * Scenario "A set picked by hand during a running switch does not last":
	 * the next run puts the switch's set back, and the end still returns to the
	 * set the switch replaced.
	 *
	 * @return void
	 */
	public function testARunningSwitchKeepsItsSetActive(): void {
		$entry = $this->planKingsDay();
		$this->at(iso: '2027-04-26T16:03:00Z');
		$this->service->runDue();

		// An administrator picks another set by hand during the window.
		$this->store['thematiq/token_set'] = 'nextcloud';
		$this->at(iso: '2027-04-27T10:00:00Z');
		$this->service->runDue();

		$this->assertSame(expected: 'koningsdag-oranje', actual: $this->store['thematiq/token_set']);
		$this->assertSame(expected: 'rijkshuisstijl', actual: $this->service->list()[0]['revertTo']);
		$this->assertSame(
			expected: ['old' => 'nextcloud', 'new' => 'koningsdag-oranje', 'actor' => 'system', 'switchId' => $entry['id'], 'reapplied' => true, 'coreThemingSynced' => []],
			actual: $this->audit[1][1]
		);

		// Already active: nothing to do and nothing logged.
		$this->service->runDue();
		$this->assertCount(expectedCount: 2, haystack: $this->audit);

		$this->at(iso: '2027-04-28T06:02:00Z');
		$this->service->runDue();
		$this->assertSame(expected: 'rijkshuisstijl', actual: $this->store['thematiq/token_set']);
	}//end testARunningSwitchKeepsItsSetActive()

	/**
	 * Putting the set back brings its logo and colours back too.
	 *
	 * @return void
	 */
	public function testKeepingASwitchActiveSyncsCoreThemingAgain(): void {
		$this->planKingsDay();
		$this->at(iso: '2027-04-26T16:03:00Z');
		$this->service->runDue();
		$this->store['thematiq/token_set'] = 'nextcloud';

		$this->theming->expects($this->once())
			->method('applyColors')
			->with(['primary_color' => '#FF6600', 'background_color' => '#FFFFFF'])
			->willReturn(['primary_color']);

		$this->at(iso: '2027-04-27T10:00:00Z');
		$this->service->runDue();

		$this->assertSame(expected: 'koningsdag-oranje', actual: $this->store['thematiq/token_set']);
	}//end testKeepingASwitchActiveSyncsCoreThemingAgain()

	/**
	 * A switch stored while the core sync was an option, with it switched off,
	 * keeps the administrator's choice: the token set alone, at the start, on
	 * every run and at the end.
	 *
	 * @return void
	 */
	public function testASwitchStoredWithoutCoreSyncLeavesCoreThemingAlone(): void {
		$this->store['thematiq/scheduled_switches'] = json_encode(
			[
				[
					'id' => 'old1',
					'tokenSet' => 'koningsdag-oranje',
					'startAt' => '2027-04-26T16:00:00Z',
					'endAt' => '2027-04-28T06:00:00Z',
					'syncCoreTheming' => false,
					'status' => 'planned',
					'createdBy' => 'admin',
					'createdAt' => '2027-04-20T12:00:00Z',
				],
			]
		);

		$this->theming->expects($this->never())->method('applyColors');
		$this->theming->expects($this->never())->method('applyImages');
		$this->theming->expects($this->never())->method('resetToDefaults');

		$this->at(iso: '2027-04-26T16:03:00Z');
		$this->service->runDue();
		$this->assertSame(expected: 'koningsdag-oranje', actual: $this->store['thematiq/token_set']);
		$this->assertSame(expected: [], actual: $this->snapshots);

		$this->store['thematiq/token_set'] = 'nextcloud';
		$this->at(iso: '2027-04-27T10:00:00Z');
		$this->service->runDue();
		$this->assertSame(expected: 'koningsdag-oranje', actual: $this->store['thematiq/token_set']);

		$this->at(iso: '2027-04-28T06:02:00Z');
		$this->service->runDue();
		$this->assertSame(expected: 'rijkshuisstijl', actual: $this->store['thematiq/token_set']);
	}//end testASwitchStoredWithoutCoreSyncLeavesCoreThemingAlone()

	/**
	 * A running switch whose set was deleted mid-window cannot put it back:
	 * the other set stays, the switch keeps running and says so in the log.
	 *
	 * @return void
	 */
	public function testARunningSwitchWhoseSetIsGoneLeavesTheActiveSetAlone(): void {
		$this->planKingsDay();
		$this->at(iso: '2027-04-26T16:03:00Z');
		$this->service->runDue();

		$this->store['thematiq/token_set'] = 'nextcloud';
		$this->sets = ['rijkshuisstijl', 'nextcloud'];
		$this->at(iso: '2027-04-27T10:00:00Z');
		$this->service->runDue();

		$this->assertSame(expected: 'nextcloud', actual: $this->store['thematiq/token_set']);
		$this->assertSame(expected: 'running', actual: $this->service->list()[0]['status']);
	}//end testARunningSwitchWhoseSetIsGoneLeavesTheActiveSetAlone()

	/**
	 * A switch whose previous set was deleted cannot go back to it at its end,
	 * and shows as failed with the reason.
	 *
	 * @return void
	 */
	public function testAnEndWhosePreviousSetIsGoneFailsVisibly(): void {
		$this->planKingsDay();
		$this->at(iso: '2027-04-26T16:03:00Z');
		$this->service->runDue();

		$this->sets = ['koningsdag-oranje', 'nextcloud'];
		$this->at(iso: '2027-04-28T06:02:00Z');
		$this->service->runDue();

		$listed = $this->service->list()[0];
		$this->assertSame(expected: 'failed', actual: $listed['status']);
		$this->assertStringContainsString(needle: 'rijkshuisstijl', haystack: $listed['failureReason']);
		$this->assertSame(expected: 'koningsdag-oranje', actual: $this->store['thematiq/token_set']);
	}//end testAnEndWhosePreviousSetIsGoneFailsVisibly()

	/**
	 * Cancelling a running switch whose previous set was deleted says so, and
	 * keeps the switch.
	 *
	 * @return void
	 */
	public function testCancellingWhenThePreviousSetIsGoneIsRefused(): void {
		$entry = $this->planKingsDay();
		$this->at(iso: '2027-04-26T16:03:00Z');
		$this->service->runDue();
		$this->sets = ['koningsdag-oranje', 'nextcloud'];

		try {
			$this->service->cancel(id: $entry['id']);
			$this->fail(message: 'Cancelling a switch that cannot go back was accepted.');
		} catch (ScheduledSwitchException $e) {
			$this->assertStringContainsString(needle: 'rijkshuisstijl', haystack: $e->getMessage());
		}

		$this->assertCount(expectedCount: 1, haystack: $this->service->list());
	}//end testCancellingWhenThePreviousSetIsGoneIsRefused()

	/**
	 * A set's theming block that does not validate is not written into core
	 * theming; the token set still switches.
	 *
	 * @return void
	 */
	public function testAThemingBlockThatDoesNotValidateIsNotWritten(): void {
		$this->planKingsDay();
		$this->theming->method('validateColors')->willReturn('Invalid colour');
		$this->theming->expects($this->never())->method('applyColors');

		$this->at(iso: '2027-04-26T16:03:00Z');
		$this->service->runDue();

		$this->assertSame(expected: 'koningsdag-oranje', actual: $this->store['thematiq/token_set']);
		$this->assertSame(expected: [], actual: $this->audit[0][1]['coreThemingSynced']);
	}//end testAThemingBlockThatDoesNotValidateIsNotWritten()

	/**
	 * A set without a theming block brings no logo or colours.
	 *
	 * @return void
	 */
	public function testASetWithoutThemingWritesNoCoreTheming(): void {
		$this->service->create(plan: new SwitchPlan(tokenSet: 'custom-campagne', window: new SwitchWindow(startAt: '2027-04-21T00:00:00Z', endAt: '2027-04-22T00:00:00Z')), createdBy: 'admin');
		$this->theming->expects($this->never())->method('applyColors');

		$this->at(iso: '2027-04-21T00:03:00Z');
		$this->service->runDue();

		$this->assertSame(expected: 'custom-campagne', actual: $this->store['thematiq/token_set']);
		$this->assertSame(expected: [], actual: $this->audit[0][1]['coreThemingSynced']);
	}//end testASetWithoutThemingWritesNoCoreTheming()

	/**
	 * Before its start a planned switch is left alone.
	 *
	 * @return void
	 */
	public function testNothingHappensBeforeTheStart(): void {
		$this->planKingsDay();

		$this->at(iso: '2027-04-26T15:59:00Z');
		$this->service->runDue();

		$this->assertSame(expected: 'rijkshuisstijl', actual: $this->store['thematiq/token_set']);
		$this->assertSame(expected: 'planned', actual: $this->service->list()[0]['status']);
		$this->assertSame(expected: [], actual: $this->audit);
	}//end testNothingHappensBeforeTheStart()

	/**
	 * A switch without an end is done once applied and leaves the list.
	 *
	 * @return void
	 */
	public function testOpenEndedSwitchLeavesTheListOnceApplied(): void {
		$this->service->create(plan: new SwitchPlan(tokenSet: 'koningsdag-oranje', window: new SwitchWindow(startAt: '2027-04-21T00:00:00Z', endAt: null)), createdBy: 'admin');

		$this->at(iso: '2027-04-21T00:04:00Z');
		$this->service->runDue();

		$this->assertSame(expected: 'koningsdag-oranje', actual: $this->store['thematiq/token_set']);
		$this->assertSame(expected: [], actual: $this->service->list());
	}//end testOpenEndedSwitchLeavesTheListOnceApplied()

	/**
	 * Scenario "A switch to a deleted set fails visibly".
	 *
	 * @return void
	 */
	public function testASwitchToADeletedSetFailsVisibly(): void {
		$entry = $this->service->create(plan: new SwitchPlan(tokenSet: 'custom-campagne', window: new SwitchWindow(startAt: '2027-04-21T00:00:00Z', endAt: '2027-04-22T00:00:00Z')), createdBy: 'admin');
		$this->sets = ['rijkshuisstijl', 'koningsdag-oranje', 'nextcloud'];

		$this->at(iso: '2027-04-21T00:03:00Z');
		$this->service->runDue();

		$this->assertSame(expected: 'rijkshuisstijl', actual: $this->store['thematiq/token_set']);
		$listed = $this->service->list()[0];
		$this->assertSame(expected: 'failed', actual: $listed['status']);
		$this->assertStringContainsString(needle: 'custom-campagne', haystack: $listed['failureReason']);

		$this->assertCount(expectedCount: 1, haystack: $this->audit);
		$this->assertSame(expected: 'scheduled_switch_applied', actual: $this->audit[0][0]);
		$this->assertSame(expected: 'rijkshuisstijl', actual: $this->audit[0][1]['old']);
		$this->assertNull(actual: $this->audit[0][1]['new']);
		$this->assertSame(expected: 'system', actual: $this->audit[0][1]['actor']);
		$this->assertSame(expected: $entry['id'], actual: $this->audit[0][1]['switchId']);
		$this->assertSame(expected: $listed['failureReason'], actual: $this->audit[0][1]['reason']);

		// A failed switch is not retried on the next run.
		$this->service->runDue();
		$this->assertCount(expectedCount: 1, haystack: $this->audit);
	}//end testASwitchToADeletedSetFailsVisibly()

	/**
	 * Scenario "A planned switch brings the set's logo and colours, and gives
	 * back what it replaced": the set's theming block is applied at the start,
	 * and the end puts back the snapshot the start took (reset first, then the
	 * administrator's own values), not the previous set's block.
	 *
	 * @return void
	 */
	public function testASwitchRestoresTheBrandingItReplaced(): void {
		$entry = $this->planKingsDay();

		$applied = [];
		$this->theming->method('validateColors')->willReturn(null);
		$this->theming->method('validateImagePaths')->willReturn(null);
		$this->theming->method('applyColors')->willReturnCallback(
			function (array $params) use (&$applied): array {
				$applied[] = $params;
				return array_keys(array_intersect_key($params, ['primary_color' => 1, 'background_color' => 1]));
			}
		);
		$this->theming->expects($this->once())->method('resetToDefaults')->willReturn(['primary_color', 'logo']);

		$this->at(iso: '2027-04-26T16:03:00Z');
		$this->service->runDue();

		$this->assertSame(expected: ['primary_color' => '#FF6600', 'background_color' => '#FFFFFF'], actual: $applied[0]);
		$this->assertSame(expected: ['primary_color', 'background_color'], actual: $this->audit[0][1]['coreThemingSynced']);
		$this->assertTrue(condition: $this->service->list()[0]['coreSnapshot']);
		$this->assertArrayHasKey(key: ScheduledCoreThemingSync::SNAPSHOT_PREFIX . $entry['id'], array: $this->snapshots);

		$this->at(iso: '2027-04-28T06:03:00Z');
		$this->service->runDue();

		$this->assertSame(expected: ['primary_color' => '#0082C9', 'background_mode' => 'default'], actual: $applied[1]);
		$this->assertSame(expected: ['primary_color', 'logo'], actual: $this->audit[1][1]['coreThemingRestored']);
		$this->assertSame(expected: [], actual: $this->snapshots);
		$this->assertSame(expected: 'rijkshuisstijl', actual: $this->store['thematiq/token_set']);
	}//end testASwitchRestoresTheBrandingItReplaced()

	/**
	 * A switch without an end never goes back, so it takes no snapshot.
	 *
	 * @return void
	 */
	public function testAnOpenEndedSwitchTakesNoSnapshot(): void {
		$this->service->create(plan: new SwitchPlan(tokenSet: 'koningsdag-oranje', window: new SwitchWindow(startAt: '2027-04-21T00:00:00Z', endAt: null)), createdBy: 'admin');

		$this->at(iso: '2027-04-21T00:04:00Z');
		$this->service->runDue();

		$this->assertSame(expected: [], actual: $this->snapshots);
	}//end testAnOpenEndedSwitchTakesNoSnapshot()

	/**
	 * A switch that ticked the core sync before snapshots existed has none to
	 * restore, and goes back the way it was planned: synced to the previous set.
	 *
	 * @return void
	 */
	public function testAnOlderSyncedSwitchSyncsThePreviousSetAtItsEnd(): void {
		$this->store['thematiq/token_set'] = 'koningsdag-oranje';
		$this->store['thematiq/scheduled_switches'] = json_encode(
			[
				[
					'id' => 'old2',
					'tokenSet' => 'koningsdag-oranje',
					'startAt' => '2027-04-26T16:00:00Z',
					'endAt' => '2027-04-28T06:00:00Z',
					'syncCoreTheming' => true,
					'status' => 'running',
					'revertTo' => 'rijkshuisstijl',
					'createdBy' => 'admin',
					'createdAt' => '2027-04-20T12:00:00Z',
				],
			]
		);

		$this->theming->expects($this->never())->method('resetToDefaults');
		$this->theming->expects($this->once())
			->method('applyColors')
			->with(['primary_color' => '#154273', 'logo' => 'img/logos/rijkshuisstijl.svg'])
			->willReturn(['primary_color']);

		$this->at(iso: '2027-04-28T06:02:00Z');
		$this->service->runDue();

		$this->assertSame(expected: 'rijkshuisstijl', actual: $this->store['thematiq/token_set']);
		$this->assertSame(expected: ['primary_color'], actual: $this->audit[0][1]['coreThemingSynced']);
	}//end testAnOlderSyncedSwitchSyncsThePreviousSetAtItsEnd()

	/**
	 * A snapshot that is gone restores nothing rather than resetting core theming.
	 *
	 * @return void
	 */
	public function testAMissingSnapshotRestoresNothing(): void {
		$this->store['thematiq/token_set'] = 'koningsdag-oranje';
		$this->store['thematiq/scheduled_switches'] = json_encode(
			[
				[
					'id' => 'lost',
					'tokenSet' => 'koningsdag-oranje',
					'startAt' => '2027-04-26T16:00:00Z',
					'endAt' => '2027-04-28T06:00:00Z',
					'status' => 'running',
					'revertTo' => 'rijkshuisstijl',
					'coreSnapshot' => true,
					'createdBy' => 'admin',
					'createdAt' => '2027-04-20T12:00:00Z',
				],
			]
		);

		$this->theming->expects($this->never())->method('resetToDefaults');

		$this->at(iso: '2027-04-28T06:02:00Z');
		$this->service->runDue();

		$this->assertSame(expected: [], actual: $this->audit[0][1]['coreThemingRestored']);
		$this->assertSame(expected: 'rijkshuisstijl', actual: $this->store['thematiq/token_set']);
	}//end testAMissingSnapshotRestoresNothing()

	/**
	 * Cancelling a planned switch removes it without touching the active set.
	 *
	 * @return void
	 */
	public function testCancellingAPlannedSwitchRemovesIt(): void {
		$entry = $this->planKingsDay();

		$this->service->cancel(id: $entry['id']);

		$this->assertSame(expected: [], actual: $this->service->list());
		$this->assertSame(expected: 'rijkshuisstijl', actual: $this->store['thematiq/token_set']);
		$this->assertSame(expected: [], actual: $this->audit);
	}//end testCancellingAPlannedSwitchRemovesIt()

	/**
	 * Scenario "Cancelling a running campaign".
	 *
	 * @return void
	 */
	public function testCancellingARunningCampaignSwitchesBackAtOnce(): void {
		$entry = $this->planKingsDay();
		$this->at(iso: '2027-04-26T16:03:00Z');
		$this->service->runDue();

		$this->at(iso: '2027-04-27T10:00:00Z');
		$this->service->cancel(id: $entry['id']);

		$this->assertSame(expected: 'rijkshuisstijl', actual: $this->store['thematiq/token_set']);
		$this->assertSame(expected: [], actual: $this->service->list());
		$this->assertSame(expected: 'rijkshuisstijl', actual: $this->audit[1][1]['new']);
		// The branding the switch replaced is back, and its snapshot gone.
		$this->assertArrayHasKey(key: 'coreThemingRestored', array: $this->audit[1][1]);
		$this->assertSame(expected: [], actual: $this->snapshots);
	}//end testCancellingARunningCampaignSwitchesBackAtOnce()

	/**
	 * Cancelling an id that is not planned says so.
	 *
	 * @return void
	 */
	public function testCancellingAnUnknownSwitchIsNotFound(): void {
		$this->expectException(exception: ScheduledSwitchNotFoundException::class);
		$this->service->cancel(id: 'nope');
	}//end testCancellingAnUnknownSwitchIsNotFound()

	/**
	 * Scenario "An administrator on AJAX cron is warned", and the status
	 * names the last run and what is active until when.
	 *
	 * @return void
	 */
	public function testStatusNamesTheLastRunTheCronModeAndTheActiveWindow(): void {
		$this->store['core/backgroundjobs_mode'] = 'ajax';
		$this->assertTrue(condition: $this->service->getStatus()['cronWarning']);
		$this->assertNull(actual: $this->service->getStatus()['lastRun']);

		$this->planKingsDay();
		$this->at(iso: '2027-04-26T16:03:00Z');
		$this->service->runDue();

		$status = $this->service->getStatus();
		$this->assertSame(expected: '2027-04-26T16:03:00Z', actual: $status['lastRun']);
		$this->assertSame(expected: 'koningsdag-oranje', actual: $status['activeTokenSet']);
		$this->assertSame(expected: '2027-04-28T06:00:00Z', actual: $status['activeUntil']);
		$this->assertSame(expected: 'rijkshuisstijl', actual: $status['revertTo']);
		$this->assertSame(expected: 'koningsdag-oranje', actual: $status['runningTokenSet']);

		$this->store['core/backgroundjobs_mode'] = 'cron';
		$this->assertFalse(condition: $this->service->getStatus()['cronWarning']);
	}//end testStatusNamesTheLastRunTheCronModeAndTheActiveWindow()

	/**
	 * A start, a kept set and an end that cannot be applied each log one warning naming the switch and the set.
	 *
	 * @return void
	 */
	public function testEachSwitchThatCannotBeAppliedLogsAWarning(): void {
		$failing = $this->service->create(plan: new SwitchPlan(tokenSet: 'custom-campagne', window: new SwitchWindow(startAt: '2027-04-21T00:00:00Z', endAt: '2027-04-22T00:00:00Z')), createdBy: 'admin');
		$this->sets = ['rijkshuisstijl', 'koningsdag-oranje', 'nextcloud'];
		$this->at(iso: '2027-04-21T00:03:00Z');
		$this->service->runDue();

		$this->assertSame(
			expected: [
				[
					'thematiq: planned switch {id} to {set} failed: {why}',
					['id' => $failing['id'], 'set' => 'custom-campagne', 'why' => 'Invalid token set'],
				],
			],
			actual: $this->warnings
		);

		$entry = $this->planKingsDay();
		$this->at(iso: '2027-04-26T16:03:00Z');
		$this->service->runDue();
		$this->store['thematiq/token_set'] = 'nextcloud';
		$this->sets = ['rijkshuisstijl', 'nextcloud'];
		$this->at(iso: '2027-04-27T10:00:00Z');
		$this->service->runDue();

		$this->assertSame(
			expected: [
				'thematiq: running switch {id} could not keep {set} active: the set does not exist',
				['id' => $entry['id'], 'set' => 'koningsdag-oranje'],
			],
			actual: $this->warnings[1]
		);

		$this->sets = ['koningsdag-oranje', 'nextcloud'];
		$this->at(iso: '2027-04-28T06:02:00Z');
		$this->service->runDue();

		$this->assertSame(
			expected: ['thematiq: planned switch {id} could not go back to {set}', ['id' => $entry['id'], 'set' => 'rijkshuisstijl']],
			actual: $this->warnings[2]
		);
		$this->assertCount(expectedCount: 3, haystack: $this->warnings);
	}//end testEachSwitchThatCannotBeAppliedLogsAWarning()

	/**
	 * A planned entry carries exactly its fields, in UTC, with a 16-character hex id.
	 *
	 * @return void
	 */
	public function testAPlannedEntryCarriesItsFieldsInUtc(): void {
		$entry = $this->planKingsDay();

		$this->assertMatchesRegularExpression(pattern: '/^[0-9a-f]{16}$/', string: $entry['id']);
		unset($entry['id']);
		$this->assertSame(
			expected: [
				'tokenSet' => 'koningsdag-oranje',
				'startAt' => '2027-04-26T16:00:00Z',
				'endAt' => '2027-04-28T06:00:00Z',
				'createdBy' => 'admin',
				'createdAt' => '2027-04-20T12:00:00Z',
				'status' => 'planned',
			],
			actual: $entry
		);
	}//end testAPlannedEntryCarriesItsFieldsInUtc()
}//end class
