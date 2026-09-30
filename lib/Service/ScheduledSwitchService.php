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
use OCA\Thematiq\Service\Exception\ScheduledSwitchException;
use OCA\Thematiq\Service\Exception\ScheduledSwitchNotFoundException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * Plans, applies, reverts and cancels timed token set switches.
 *
 * An entry is `planned` until its start has passed. The background job then
 * switches to its set; with an end it becomes `running` and remembers the
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
	 * Constructor.
	 *
	 * @param ScheduledSwitchStore     $store          The stored plans.
	 * @param ActiveTokenSetService    $activeTokenSet The one token set write path.
	 * @param TokenSetService          $tokenSets      Validates a set on planning.
	 * @param ScheduledCoreThemingSync $coreSync       Syncs core logo and colours when asked.
	 * @param ThemingAuditService      $auditService   Records a failed switch.
	 * @param IConfig                  $config         Last run and cron mode.
	 * @param ITimeFactory             $time           The clock.
	 * @param IL10N                    $l10n           The translator.
	 * @param LoggerInterface          $logger         The logger.
	 *
	 * @SuppressWarnings(PHPMD.ExcessiveParameterList) - one collaborator per concern listed in the class docblock.
	 */
	public function __construct(
		private readonly ScheduledSwitchStore $store,
		private readonly ActiveTokenSetService $activeTokenSet,
		private readonly TokenSetService $tokenSets,
		private readonly ScheduledCoreThemingSync $coreSync,
		private readonly ThemingAuditService $auditService,
		private readonly IConfig $config,
		private readonly ITimeFactory $time,
		private readonly IL10N $l10n,
		private readonly LoggerInterface $logger,
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
	 * @param string      $tokenSet        The set to switch to.
	 * @param string      $startAt         The start, ISO 8601 with an offset.
	 * @param string|null $endAt           The optional end, ISO 8601 with an offset.
	 * @param bool        $syncCoreTheming Whether to also update the Nextcloud logo and colours.
	 * @param string      $createdBy       The administrator's uid.
	 *
	 * @return array<string, mixed> The planned entry.
	 *
	 * @throws ScheduledSwitchException When the set does not exist, a time does not parse, the end is not after the start, or the window overlaps.
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md#requirement-an-administrator-plans-a-switch
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag) - the core sync option is the administrator's own checkbox, stored as is.
	 */
	public function create(string $tokenSet, string $startAt, ?string $endAt, bool $syncCoreTheming, string $createdBy): array {
		if ($this->tokenSets->isValidTokenSet(tokenSetId: $tokenSet) === false) {
			throw new ScheduledSwitchException($this->l10n->t('The token set {set} does not exist.', ['set' => $tokenSet]));
		}

		$start = ScheduledSwitchStore::toUtc(value: $startAt);
		$end = null;
		if ($endAt !== null) {
			$end = ScheduledSwitchStore::toUtc(value: $endAt);
		}

		if ($start === null || ($endAt !== null && $end === null)) {
			throw new ScheduledSwitchException($this->l10n->t('Enter the start and end as a date and a time.'));
		}

		if ($end !== null && strtotime($end) <= strtotime($start)) {
			throw new ScheduledSwitchException($this->l10n->t('The end must be after the start.'));
		}

		$entry = [
			'id' => bin2hex(random_bytes(8)),
			'tokenSet' => $tokenSet,
			'startAt' => $start,
			'endAt' => $end,
			'syncCoreTheming' => $syncCoreTheming,
			'createdBy' => $createdBy,
			'createdAt' => $this->nowIso(),
			'status' => 'planned',
		];

		$entries = $this->store->all();
		$overlap = ScheduledSwitchStore::findOverlap(candidate: $entry, entries: $entries);
		if ($overlap !== null) {
			throw new ScheduledSwitchException(
				$this->l10n->t('This window overlaps the planned switch to {set}. Change the times or cancel that switch first.', ['set' => (string)$overlap['tokenSet']])
			);
		}

		$entries[] = $entry;
		$this->store->save(entries: $entries);

		return $entry;
	}//end create()

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

			if (($entry['status'] ?? 'planned') === 'running' && $this->switchBack(entry: $entry) === false) {
				throw new ScheduledSwitchException(
					$this->l10n->t('The token set {set} no longer exists, so the switch cannot go back to it. Choose a token set by hand.', ['set' => (string)($entry['revertTo'] ?? '')])
				);
			}

			unset($entries[$index]);
			$this->store->save(entries: $entries);
			return;
		}

		throw new ScheduledSwitchNotFoundException($this->l10n->t('This planned switch does not exist any more.'));
	}//end cancel()

	/**
	 * Apply every due start and end. Called by the background job.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md#requirement-the-app-applies-a-due-switch-and-switches-back
	 */
	public function runDue(): void {
		$now = $this->time->getTime();
		$kept = [];
		foreach ($this->store->all() as $entry) {
			$entry = $this->advance(entry: $entry, now: $now);
			if ($entry !== null) {
				$kept[] = $entry;
			}
		}

		$this->store->save(entries: $kept);
		$this->config->setAppValue(Application::APP_ID, self::LAST_RUN_KEY, $this->nowIso());
	}//end runDue()

	/**
	 * What the settings page shows above the list.
	 *
	 * @return array<string, mixed> `{activeTokenSet, activeUntil, revertTo, lastRun, cronMode, cronWarning}`.
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md#requirement-the-page-warns-when-switches-may-run-late
	 */
	public function getStatus(): array {
		$activeUntil = null;
		$revertTo = null;
		foreach ($this->store->all() as $entry) {
			if (($entry['status'] ?? null) === 'running') {
				$activeUntil = $entry['endAt'];
				$revertTo = $entry['revertTo'];
			}
		}

		$lastRun = $this->config->getAppValue(Application::APP_ID, self::LAST_RUN_KEY, '');
		if ($lastRun === '') {
			$lastRun = null;
		}

		$cronMode = $this->config->getAppValue('core', 'backgroundjobs_mode', 'ajax');

		return [
			'activeTokenSet' => $this->activeTokenSet->getActive(),
			'activeUntil' => $activeUntil,
			'revertTo' => $revertTo,
			'lastRun' => $lastRun,
			'cronMode' => $cronMode,
			'cronWarning' => ($cronMode === 'ajax'),
		];
	}//end getStatus()

	/**
	 * Move one entry forward in time.
	 *
	 * @param array<string, mixed> $entry The entry.
	 * @param int                  $now   The current time.
	 *
	 * @return array<string, mixed>|null The entry to keep, or null when it is done.
	 */
	private function advance(array $entry, int $now): ?array {
		$status = ($entry['status'] ?? 'planned');
		if ($status === 'planned' && strtotime((string)$entry['startAt']) <= $now) {
			$entry = $this->start(entry: $entry);
			$status = $entry['status'];
		}

		if ($status === 'done') {
			return null;
		}

		if ($status === 'running' && $entry['endAt'] !== null && strtotime((string)$entry['endAt']) <= $now) {
			if ($this->switchBack(entry: $entry) === true) {
				return null;
			}

			$entry['status'] = 'failed';
			$entry['failureReason'] = $this->l10n->t('The token set {set} no longer exists, so the switch could not go back to it.', ['set' => (string)$entry['revertTo']]);
		}

		return $entry;
	}//end advance()

	/**
	 * Apply a due start.
	 *
	 * @param array<string, mixed> $entry The planned entry.
	 *
	 * @return array<string, mixed> The entry, now `running`, `done` or `failed`.
	 */
	private function start(array $entry): array {
		$context = ['actor' => 'system', 'switchId' => $entry['id']];
		if (($entry['syncCoreTheming'] ?? false) === true && $this->tokenSets->isValidTokenSet(tokenSetId: $entry['tokenSet']) === true) {
			$context['coreThemingSynced'] = $this->coreSync->sync(tokenSetId: $entry['tokenSet']);
		}

		try {
			$entry['revertTo'] = $this->activeTokenSet->switchTo(tokenSet: $entry['tokenSet'], auditAction: self::AUDIT_ACTION, auditContext: $context);
		} catch (\InvalidArgumentException $e) {
			$reason = $this->l10n->t('The token set {set} no longer exists, so the switch was not applied.', ['set' => $entry['tokenSet']]);
			$this->logger->warning('thematiq: planned switch {id} to {set} failed: the set does not exist', ['id' => $entry['id'], 'set' => $entry['tokenSet']]);
			$this->auditService->log(
				action: self::AUDIT_ACTION,
				context: ['old' => $this->activeTokenSet->getActive(), 'new' => null, 'actor' => 'system', 'switchId' => $entry['id'], 'reason' => $reason]
			);
			$entry['status'] = 'failed';
			$entry['failureReason'] = $reason;
			return $entry;
		}

		$entry['status'] = 'done';
		if ($entry['endAt'] !== null) {
			$entry['status'] = 'running';
		}

		return $entry;
	}//end start()

	/**
	 * Switch back to the set a running switch replaced.
	 *
	 * @param array<string, mixed> $entry The running entry.
	 *
	 * @return bool False when that set no longer exists.
	 */
	private function switchBack(array $entry): bool {
		$revertTo = (string)($entry['revertTo'] ?? '');
		$context = ['actor' => 'system', 'switchId' => $entry['id']];
		if (($entry['syncCoreTheming'] ?? false) === true && $this->tokenSets->isValidTokenSet(tokenSetId: $revertTo) === true) {
			$context['coreThemingSynced'] = $this->coreSync->sync(tokenSetId: $revertTo);
		}

		try {
			$this->activeTokenSet->switchTo(tokenSet: $revertTo, auditAction: self::AUDIT_ACTION, auditContext: $context);
		} catch (\InvalidArgumentException $e) {
			$this->logger->warning('thematiq: planned switch {id} could not go back to {set}', ['id' => $entry['id'], 'set' => $revertTo]);
			return false;
		}

		return true;
	}//end switchBack()

	/**
	 * The current time as UTC ISO 8601.
	 *
	 * @return string The time.
	 */
	private function nowIso(): string {
		return gmdate('Y-m-d\TH:i:s\Z', $this->time->getTime());
	}//end nowIso()
}//end class
