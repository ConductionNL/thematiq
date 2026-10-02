<?php

/**
 * Thematiq Scheduled Switch Order.
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

/**
 * The order in which one background-job run handles the planned switches:
 * by when each entry's next event falls due, an end before a start at the
 * same moment.
 *
 * Stored order is creation order. With two back-to-back windows due in one
 * run and the later one created first, handling them in stored order let the
 * later start snapshot the earlier campaign's look and return to it for good
 * (#825).
 *
 * @spec openspec/specs/scheduled-switch/spec.md#requirement-the-app-applies-a-due-switch-and-switches-back
 */
class ScheduledSwitchOrder {
	/**
	 * The indexes of the entries, in the order a run should handle them.
	 *
	 * @param array<int, array<string, mixed>> $entries The stored entries, as a list.
	 *
	 * @return array<int, int> Indexes into $entries.
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md#requirement-the-app-applies-a-due-switch-and-switches-back
	 */
	public function dueOrder(array $entries): array {
		$order = array_keys($entries);
		usort(
			$order,
			fn (int $left, int $right): int => ($this->dueKey(entry: $entries[$left]) <=> $this->dueKey(entry: $entries[$right]))
		);

		return $order;
	}//end dueOrder()

	/**
	 * When an entry's next event is due: a running entry's end, anything
	 * else's start. Ends sort before starts at the same time.
	 *
	 * @param array<string, mixed> $entry The entry.
	 *
	 * @return array{0: int, 1: int} [time, 0 for an end or 1 for a start].
	 */
	private function dueKey(array $entry): array {
		if (($entry['status'] ?? 'planned') !== 'running') {
			return [(int)strtotime((string)($entry['startAt'] ?? '')), 1];
		}

		$end = ($entry['endAt'] ?? null);
		if ($end === null) {
			return [PHP_INT_MAX, 0];
		}

		return [(int)strtotime((string)$end), 0];
	}//end dueKey()
}//end class
