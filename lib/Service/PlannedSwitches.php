<?php

/**
 * Thematiq Planned Switches.
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
 * @spec openspec/specs/scheduled-switch/spec.md#requirement-an-administrator-plans-a-switch
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\Service\Exception\ScheduledSwitchException;
use OCP\IL10N;

/**
 * The list of planned switches as the planner reads and adds to it: a new
 * window checked and in UTC, a new entry refused when it overlaps, and the
 * running switch. Built by ScheduledSwitchService.
 *
 * @spec openspec/specs/scheduled-switch/spec.md#requirement-an-administrator-plans-a-switch
 */
final class PlannedSwitches {

	/**
	 * Constructor.
	 *
	 * @param ScheduledSwitchStore $store The stored plans.
	 * @param IL10N $l10n The translator.
	 */
	public function __construct(
		private readonly ScheduledSwitchStore $store,
		private readonly IL10N $l10n,
	) {
	}//end __construct()

	/**
	 * Parse a planned window to UTC and check it.
	 *
	 * @param SwitchWindow $window The start and optional end, ISO 8601 with an offset.
	 * @param int $now The current time.
	 *
	 * @return SwitchWindow The start and end in UTC.
	 *
	 * @throws ScheduledSwitchException When a time does not parse, or the end is not after the start or not in the future.
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md#requirement-an-administrator-plans-a-switch
	 */
	public function window(SwitchWindow $window, int $now): SwitchWindow {
		$utc = $this->parsed(window: $window);
		$this->assertEndIsAhead(window: $utc, now: $now);

		return $utc;
	}//end window()

	/**
	 * The window in UTC.
	 *
	 * @param SwitchWindow $window The start and optional end, ISO 8601 with an offset.
	 *
	 * @return SwitchWindow The start and end in UTC.
	 *
	 * @throws ScheduledSwitchException When a time does not parse.
	 */
	private function parsed(SwitchWindow $window): SwitchWindow {
		$start = $this->store->toUtc(value: $window->startAt);
		$end = $this->store->toUtc(value: $window->endAt);
		if ($start === null || ($window->endAt !== null && $end === null)) {
			throw new ScheduledSwitchException(message: $this->l10n->t('Enter the start and end as a date and a time.'));
		}

		return new SwitchWindow(startAt: $start, endAt: $end);
	}//end parsed()

	/**
	 * Refuse an end that is not after the start, or that has passed.
	 *
	 * @param SwitchWindow $window The window in UTC.
	 * @param int $now The current time.
	 *
	 * @return void
	 *
	 * @throws ScheduledSwitchException When the end is not after the start or not in the future.
	 */
	private function assertEndIsAhead(SwitchWindow $window, int $now): void {
		if ($window->endAt !== null && strtotime($window->endAt) <= strtotime($window->startAt)) {
			throw new ScheduledSwitchException(message: $this->l10n->t('The end must be after the start.'));
		}

		// A window that is already over would be applied and reverted on the
		// next run: a core theming reset, two audit entries and two versions
		// for a switch nobody sees.
		if ($this->store->hasEnded(end: $window->endAt, now: $now) === true) {
			throw new ScheduledSwitchException(message: $this->l10n->t('The end must be in the future.'));
		}
	}//end assertEndIsAhead()

	/**
	 * Store a new entry unless its window overlaps a stored one.
	 *
	 * @param array<string, mixed> $entry The planned entry.
	 *
	 * @return void
	 *
	 * @throws ScheduledSwitchException When the window overlaps.
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md#requirement-an-administrator-plans-a-switch
	 */
	public function add(array $entry): void {
		$entries = $this->store->all();
		$overlap = $this->store->findOverlap(candidate: $entry, entries: $entries);
		if ($overlap !== null) {
			throw new ScheduledSwitchException(
				message: $this->l10n->t(
					'This window overlaps the planned switch to {set}. Change the times or cancel that switch first.',
					['set' => (string)$overlap['tokenSet']]
				)
			);
		}

		$entries[] = $entry;
		$this->store->save(entries: $entries);
	}//end add()

	/**
	 * The set, end and previous set of the running switch; the last one listed wins.
	 *
	 * @return array{tokenSet: mixed, endAt: mixed, revertTo: mixed} Each null when no switch is running.
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md#requirement-the-page-warns-when-switches-may-run-late
	 */
	public function running(): array {
		$running = ['tokenSet' => null, 'endAt' => null, 'revertTo' => null];
		foreach ($this->store->all() as $entry) {
			if (($entry['status'] ?? null) === 'running') {
				$running = ['tokenSet' => $entry['tokenSet'], 'endAt' => $entry['endAt'], 'revertTo' => $entry['revertTo']];
			}
		}

		return $running;
	}//end running()
}//end class
