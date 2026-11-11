<?php

/**
 * Thematiq Scheduled Switch Service.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Service
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/scheduled-switch/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\Exception\RestrictedSetNotConfirmedException;
use OCA\Thematiq\Service\Exception\ScheduledSwitchException;
use OCA\Thematiq\Service\Exception\ScheduledSwitchNotFoundException;
use OCP\IL10N;

/**
 * Plans, applies, reverts and cancels timed token set switches.
 *
 * An entry is `planned` until its start has passed. The background job then
 * applies its set the way the apply dialog does — the token set and the
 * Nextcloud logo and colours it carries; with an end it becomes `running`,
 * keeps that set applied, and remembers the
 * set it replaced as `revertTo`, without an end it is done and leaves the
 * list. At the end the job switches back to `revertTo` and the entry leaves
 * the list. A switch that cannot be applied (its set was deleted) becomes
 * `failed`, stays listed with the reason, and is not retried.
 *
 * @spec openspec/specs/scheduled-switch/spec.md
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) - the planner sits between the store, the one
 * token set write path, core theming, the audit trail, the clock and the translator; each is a
 * single call site, none is incidental.
 */
class ScheduledSwitchService {

	/**
	 * The app config key holding the last time the job ran.
	 *
	 * @var string
	 */
	public const LAST_RUN_KEY = 'scheduled_switches_last_run';

	/**
	 * The audit action for an applied or failed switch.
	 *
	 * @var string
	 */
	private const AUDIT_ACTION = 'scheduled_switch_applied';

	/**
	 * Why a due switch was not applied: its set is gone (0), or its set is
	 * restricted and nobody confirmed it (1).
	 *
	 * @var array<int, string>
	 */
	private const START_REFUSALS = [
		'The token set {set} no longer exists, so the switch was not applied.',
		'Nobody confirmed that your organisation may use the house style {set}, so the switch was not applied.',
	];

	/**
	 * Constructor.
	 *
	 * @param ScheduledSwitchStore $store The stored plans.
	 * @param ScheduledSwitchTargets $targets The active token set, the set list and the core theming sync.
	 * @param ScheduledSwitchRuntime $runtime The audit trail, the config, the clock and the logger.
	 * @param IL10N $l10n The translator.
	 */
	public function __construct(
		private readonly ScheduledSwitchStore $store,
		private readonly ScheduledSwitchTargets $targets,
		private readonly ScheduledSwitchRuntime $runtime,
		private readonly IL10N $l10n,
	) {
	}//end __construct()

	/**
	 * The planned, running and failed switches, soonest first.
	 *
	 * @return array<int, array<string, mixed>> The entries.
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md#requirement-an-administrator-plans-a-switch
	 */
	public function list(): array {
		$entries = $this->store->all();
		usort($entries, fn (array $left, array $right) => strcmp((string)($left['startAt'] ?? ''), (string)($right['startAt'] ?? '')));

		return $entries;
	}//end list()

	/**
	 * Plan a switch.
	 *
	 * @param SwitchPlan $plan The set to switch to, and the start and optional end, ISO 8601 with an offset.
	 * @param string $createdBy The administrator's uid.
	 *
	 * @return array<string, mixed> The planned entry.
	 *
	 * @throws ScheduledSwitchException When the set does not exist, a time does not parse, the end is not after the start
	 *                                  or not in the future, or the window overlaps.
	 * @throws RestrictedSetNotConfirmedException When the set is restricted and nobody confirmed it.
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md#requirement-an-administrator-plans-a-switch
	 */
	public function create(SwitchPlan $plan, string $createdBy): array {
		$this->assertMayPlan(tokenSet: $plan->tokenSet);

		$planned = $this->plannedSwitches();
		$window = $planned->window(window: $plan->window, now: $this->runtime->time->getTime());
		$entry = $this->plannedEntry(plan: new SwitchPlan(tokenSet: $plan->tokenSet, window: $window), createdBy: $createdBy);
		$planned->add(entry: $entry);

		return $entry;
	}//end create()

	/**
	 * Refuse a set that does not exist, or that is restricted and nobody confirmed.
	 *
	 * @param string $tokenSet The set to switch to.
	 *
	 * @return void
	 *
	 * @throws ScheduledSwitchException When the set does not exist.
	 * @throws RestrictedSetNotConfirmedException When the set is restricted and nobody confirmed it.
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md#requirement-an-administrator-plans-a-switch
	 */
	private function assertMayPlan(string $tokenSet): void {
		if ($this->targets->tokenSets->isValidTokenSet(tokenSetId: $tokenSet) === false) {
			throw new ScheduledSwitchException(message: $this->l10n->t('The token set {set} does not exist.', ['set' => $tokenSet]));
		}

		// A restricted set nobody confirmed is refused here too; the controller answers 403.
		$this->targets->tokenSets->licenceGate()->assertMayApply(tokenSetId: $tokenSet);
	}//end assertMayPlan()

	/**
	 * The planned switches with their checks and translated refusals.
	 *
	 * @return PlannedSwitches The list.
	 */
	private function plannedSwitches(): PlannedSwitches {
		return new PlannedSwitches(store: $this->store, l10n: $this->l10n);
	}//end plannedSwitches()

	/**
	 * The stored entry of a new plan.
	 *
	 * @param SwitchPlan $plan The set and its window in UTC.
	 * @param string $createdBy The administrator's uid.
	 *
	 * @return array<string, mixed> The planned entry.
	 */
	private function plannedEntry(SwitchPlan $plan, string $createdBy): array {
		return [
			'id' => bin2hex(random_bytes(8)),
			'tokenSet' => $plan->tokenSet,
			'startAt' => $plan->window->startAt,
			'endAt' => $plan->window->endAt,
			'createdBy' => $createdBy,
			'createdAt' => $this->nowIso(),
			'status' => 'planned',
		];
	}//end plannedEntry()

	/**
	 * Cancel a switch. A running one switches back at once.
	 *
	 * @param string $id The entry id.
	 *
	 * @return void
	 *
	 * @throws ScheduledSwitchNotFoundException When no entry has this id.
	 * @throws ScheduledSwitchException When a running switch cannot switch back.
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md#requirement-an-administrator-cancels-a-planned-switch
	 */
	public function cancel(string $id): void {
		$entries = $this->store->all();
		foreach ($entries as $index => $entry) {
			if (($entry['id'] ?? null) !== $id) {
				continue;
			}

			$this->switchBackIfRunning(entry: $entry);
			unset($entries[$index]);
			$this->store->save(entries: $entries);
			return;
		}

		throw new ScheduledSwitchNotFoundException(message: $this->l10n->t('This planned switch does not exist any more.'));
	}//end cancel()

	/**
	 * Switch a running switch back before it is cancelled.
	 *
	 * @param array<string, mixed> $entry The entry being cancelled.
	 *
	 * @return void
	 *
	 * @throws ScheduledSwitchException When the set it replaced no longer exists.
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md#requirement-an-administrator-cancels-a-planned-switch
	 */
	private function switchBackIfRunning(array $entry): void {
		if (($entry['status'] ?? 'planned') === 'running' && $this->switchBack(entry: $entry) === false) {
			throw new ScheduledSwitchException(
				message: $this->l10n->t(
					'The token set {set} no longer exists, so the switch cannot go back to it. Choose a token set by hand.',
					['set' => (string)($entry['revertTo'] ?? '')]
				)
			);
		}
	}//end switchBackIfRunning()

	/**
	 * Apply every due start and end. Called by the background job.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md#requirement-the-app-applies-a-due-switch-and-switches-back
	 */
	public function runDue(): void {
		$now = $this->runtime->time->getTime();
		$entries = array_values($this->store->all());

		// Ends before starts, in due order, not stored order (#825).
		$order = (new ScheduledSwitchOrder())->dueOrder(entries: $entries);

		$advanced = [];
		foreach ($order as $index) {
			$advanced[$index] = $this->advance(entry: $entries[$index], now: $now);
		}

		ksort($advanced);
		$kept = array_values(array_filter($advanced, fn (?array $entry): bool => $entry !== null));

		$this->store->save(entries: $kept);
		$this->runtime->config->setAppValue(Application::APP_ID, self::LAST_RUN_KEY, $this->nowIso());
	}//end runDue()

	/**
	 * What the settings page shows above the list.
	 *
	 * @return array<string, mixed> `{activeTokenSet, runningTokenSet, activeUntil, revertTo, lastRun, cronMode, cronWarning}`.
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md#requirement-the-page-warns-when-switches-may-run-late
	 */
	public function getStatus(): array {
		$running = $this->plannedSwitches()->running();
		$lastRun = $this->lastRun();
		$cronMode = $this->runtime->config->getAppValue('core', 'backgroundjobs_mode', 'ajax');

		return [
			'activeTokenSet' => $this->targets->activeTokenSet->getActive(),
			'runningTokenSet' => $running['tokenSet'],
			'activeUntil' => $running['endAt'],
			'revertTo' => $running['revertTo'],
			'lastRun' => $lastRun,
			'cronMode' => $cronMode,
			'cronWarning' => ($cronMode === 'ajax'),
		];
	}//end getStatus()

	/**
	 * When the background job last ran.
	 *
	 * @return string|null The time, or null when it never ran.
	 */
	private function lastRun(): ?string {
		$lastRun = $this->runtime->config->getAppValue(Application::APP_ID, self::LAST_RUN_KEY, '');
		if ($lastRun === '') {
			return null;
		}

		return $lastRun;
	}//end lastRun()

	/**
	 * Move one entry forward in time.
	 *
	 * @param array<string, mixed> $entry The entry.
	 * @param int $now The current time.
	 *
	 * @return array<string, mixed>|null The entry to keep, or null when it is done.
	 */
	private function advance(array $entry, int $now): ?array {
		$entry = $this->startWhenDue(entry: $entry, now: $now);
		$status = ($entry['status'] ?? 'planned');
		if ($status === 'done') {
			return null;
		}

		if ($status !== 'running') {
			return $entry;
		}

		return $this->keepOrEnd(entry: $entry, now: $now);
	}//end advance()

	/**
	 * Apply a planned entry whose start has passed.
	 *
	 * @param array<string, mixed> $entry The entry.
	 * @param int $now The current time.
	 *
	 * @return array<string, mixed> The entry, started when it was due.
	 */
	private function startWhenDue(array $entry, int $now): array {
		if (($entry['status'] ?? 'planned') === 'planned' && strtotime((string)$entry['startAt']) <= $now) {
			return $this->start(entry: $entry);
		}

		return $entry;
	}//end startWhenDue()

	/**
	 * Keep a running switch applied until its end, then switch back.
	 *
	 * @param array<string, mixed> $entry The running entry.
	 * @param int $now The current time.
	 *
	 * @return array<string, mixed>|null The entry to keep, or null when it switched back.
	 */
	private function keepOrEnd(array $entry, int $now): ?array {
		if ($entry['endAt'] === null || strtotime((string)$entry['endAt']) > $now) {
			return $this->keepApplied(entry: $entry);
		}

		if ($this->switchBack(entry: $entry) === true) {
			return null;
		}

		$entry['status'] = 'failed';
		$entry['failureReason'] = $this->l10n->t(
			'The token set {set} no longer exists, so the switch could not go back to it.',
			['set' => (string)$entry['revertTo']]
		);

		return $entry;
	}//end keepOrEnd()

	/**
	 * Keep a running switch's token set active until its end.
	 *
	 * The start used to be the only moment the switch was applied: a token set
	 * picked by hand during the window then stayed, and every later run left it
	 * there, so the campaign look was gone until the end put the old set back.
	 * Each run now applies the switch's set again when another one is active. It
	 * does not touch `revertTo` or the snapshot: the end still returns to what
	 * the switch replaced.
	 *
	 * @param array<string, mixed> $entry The running entry.
	 *
	 * @return array<string, mixed> The entry.
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md#requirement-the-app-applies-a-due-switch-and-switches-back
	 */
	private function keepApplied(array $entry): array {
		$tokenSet = (string)$entry['tokenSet'];
		if ($this->targets->activeTokenSet->getActive() === $tokenSet) {
			return $entry;
		}

		try {
			$this->applySet(entry: $entry, context: ['actor' => 'system', 'switchId' => $entry['id'], 'reapplied' => true]);
		} catch (\InvalidArgumentException $e) {
			$this->runtime->logger->warning(
				'thematiq: running switch {id} could not keep {set} active: the set does not exist',
				['id' => $entry['id'], 'set' => $tokenSet]
			);
		}

		return $entry;
	}//end keepApplied()

	/**
	 * Apply a due start.
	 *
	 * @param array<string, mixed> $entry The planned entry.
	 *
	 * @return array<string, mixed> The entry, now `running`, `done` or `failed`.
	 */
	private function start(array $entry): array {
		$entry = $this->snapshotBeforeStart(entry: $entry);

		try {
			$entry['revertTo'] = $this->applySet(entry: $entry, context: ['actor' => 'system', 'switchId' => $entry['id']]);
		} catch (\InvalidArgumentException $e) {
			return $this->failedStart(entry: $entry, e: $e);
		}

		$entry['status'] = 'done';
		if ($entry['endAt'] !== null) {
			$entry['status'] = 'running';
		}

		return $entry;
	}//end start()

	/**
	 * Take a snapshot of core theming before a switch with an end starts; its
	 * end puts it back: the logo and colours it replaces may be an
	 * administrator's own, which no token set carries.
	 *
	 * @param array<string, mixed> $entry The planned entry.
	 *
	 * @return array<string, mixed> The entry, marked `coreSnapshot` when a snapshot was taken.
	 */
	private function snapshotBeforeStart(array $entry): array {
		if ($entry['endAt'] !== null
			&& $this->changesCoreTheming(entry: $entry) === true
			&& $this->targets->tokenSets->isValidTokenSet(tokenSetId: (string)$entry['tokenSet']) === true
		) {
			$this->targets->coreSync->snapshot(switchId: (string)$entry['id']);
			$entry['coreSnapshot'] = true;
		}

		return $entry;
	}//end snapshotBeforeStart()

	/**
	 * Mark a start that could not be applied as failed, with a warning and an audit entry naming why.
	 *
	 * @param array<string, mixed> $entry The planned entry.
	 * @param \InvalidArgumentException $e Why the set could not be applied.
	 *
	 * @return array<string, mixed> The entry, now `failed`.
	 */
	private function failedStart(array $entry, \InvalidArgumentException $e): array {
		$tokenSet = (string)$entry['tokenSet'];
		$reason = $this->l10n->t(self::START_REFUSALS[(int)($e instanceof RestrictedSetNotConfirmedException)], ['set' => $tokenSet]);

		$this->runtime->logger->warning(
			'thematiq: planned switch {id} to {set} failed: {why}',
			['id' => $entry['id'], 'set' => $tokenSet, 'why' => $e->getMessage()]
		);
		$this->auditFailedStart(entry: $entry, reason: $reason);
		$entry['status'] = 'failed';
		$entry['failureReason'] = $reason;
		return $entry;
	}//end failedStart()

	/**
	 * The audit entry of a start that could not be applied.
	 *
	 * @param array<string, mixed> $entry The planned entry.
	 * @param string $reason Why it was not applied.
	 *
	 * @return void
	 */
	private function auditFailedStart(array $entry, string $reason): void {
		$this->runtime->auditService->log(
			action: self::AUDIT_ACTION,
			context: [
				'old' => $this->targets->activeTokenSet->getActive(),
				'new' => null,
				'actor' => 'system',
				'switchId' => $entry['id'],
				'reason' => $reason,
			]
		);
	}//end auditFailedStart()

	/**
	 * Switch back to the set a running switch replaced.
	 *
	 * Core theming goes back to the snapshot the start took, not to whatever
	 * the previous set carries: an administrator's own logo and colours, or a
	 * set without branding, would otherwise be lost or leave the campaign's
	 * look in place. A switch started before snapshots existed keeps what it
	 * was planned with: synced to the previous set when it asked for that.
	 *
	 * @param array<string, mixed> $entry The running entry.
	 *
	 * @return bool False when that set no longer exists.
	 */
	private function switchBack(array $entry): bool {
		$revertTo = (string)($entry['revertTo'] ?? '');
		$context = ['actor' => 'system', 'switchId' => $entry['id']];
		if (($entry['coreSnapshot'] ?? false) === true) {
			$context['coreThemingRestored'] = $this->targets->coreSync->restore(switchId: (string)$entry['id']);
		} elseif (($entry['syncCoreTheming'] ?? false) === true) {
			$context['coreThemingSynced'] = $this->targets->coreSync->sync(tokenSetId: $revertTo);
		}

		try {
			$this->targets->activeTokenSet->switchTo(tokenSet: $revertTo, auditAction: self::AUDIT_ACTION, auditContext: $context);
		} catch (\InvalidArgumentException $e) {
			$this->runtime->logger->warning('thematiq: planned switch {id} could not go back to {set}', ['id' => $entry['id'], 'set' => $revertTo]);
			return false;
		}

		return true;
	}//end switchBack()

	/**
	 * Apply a switch's token set the way the apply dialog does: Nextcloud's
	 * logo and colours from the set first, then the token set itself.
	 *
	 * A switch used to change only the token set unless the administrator had
	 * ticked a box, and a set's look lives largely in the Nextcloud colours and
	 * logo it carries — so a switch changed the token set and nothing anyone
	 * could see. For the stock set the sync resets Nextcloud's theming to its
	 * defaults, as the dialog does.
	 *
	 * @param array<string, mixed> $entry The switch; its `tokenSet` is the set to apply.
	 * @param array<string, mixed> $context The audit context.
	 *
	 * @return string The set that was active before.
	 *
	 * @throws \InvalidArgumentException When the set does not exist.
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md#requirement-a-planned-switch-applies-a-set-like-the-apply-dialog
	 */
	private function applySet(array $entry, array $context): string {
		$tokenSet = (string)$entry['tokenSet'];
		if ($this->changesCoreTheming(entry: $entry) === true) {
			// A set that no longer exists has no theming block, so this writes
			// nothing, and switchTo() below refuses it.
			$context['coreThemingSynced'] = $this->targets->coreSync->sync(tokenSetId: $tokenSet);
		}

		return $this->targets->activeTokenSet->switchTo(tokenSet: $tokenSet, auditAction: self::AUDIT_ACTION, auditContext: $context);
	}//end applySet()

	/**
	 * Whether a switch changes core theming.
	 *
	 * Every switch does, except one planned while that was an option and
	 * stored with it switched off: its administrator chose the token set alone,
	 * and that choice stands.
	 *
	 * @param array<string, mixed> $entry The switch.
	 *
	 * @return bool True unless the switch was stored with `syncCoreTheming` false.
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md#requirement-a-planned-switch-applies-a-set-like-the-apply-dialog
	 */
	private function changesCoreTheming(array $entry): bool {
		return ($entry['syncCoreTheming'] ?? true) !== false;
	}//end changesCoreTheming()

	/**
	 * The current time as UTC ISO 8601.
	 *
	 * @return string The time.
	 */
	private function nowIso(): string {
		return gmdate('Y-m-d\TH:i:s\Z', $this->runtime->time->getTime());
	}//end nowIso()
}//end class
