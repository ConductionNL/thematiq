<?php

/**
 * Thematiq audit array diff.
 *
 * Which identities changed between the old and new array of an audit entry:
 * list values, assoc keys, or the groups of a group theming mapping. Split out
 * of {@see ThemingAuditService}, which writes the entries.
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
 * @spec openspec/specs/per-group-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The `changed` list of an audit entry.
 *
 * @spec openspec/specs/per-group-theming/spec.md
 */
class AuditArrayDiff {

	/**
	 * Diff two arrays' identities: list values, or assoc keys plus any key
	 * whose value differs between old and new.
	 *
	 * @param array<int|string, mixed> $old The prior array.
	 * @param array<int|string, mixed> $new The new array.
	 *
	 * @return array<int, int|string> The changed identities.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function changed(array $old, array $new): array {
		$isList = (array_is_list($old) === true && array_is_list($new) === true);

		// An ordered list of `{group, tokenSet}` entries (group theming, #943):
		// strval() on those raised "Array to string conversion" and audited
		// ["Array"]. Diff it as a map from group to entry and position.
		if ($isList === true && $this->isGroupList(list: array_merge($old, $new)) === true) {
			return $this->changed(old: $this->byGroup(list: $old), new: $this->byGroup(list: $new));
		}

		if ($isList === true) {
			$oldValues = array_map($this->identityOf(...), $old);
			$newValues = array_map($this->identityOf(...), $new);

			return array_values(
				array_unique(
					array_merge(
						array_diff($oldValues, $newValues),
						array_diff($newValues, $oldValues)
					)
				)
			);
		}

		$oldKeys = array_keys($old);
		$newKeys = array_keys($new);

		$changed = array_merge(
			array_diff($oldKeys, $newKeys),
			array_diff($newKeys, $oldKeys)
		);

		foreach (array_intersect($oldKeys, $newKeys) as $key) {
			if ($old[$key] !== $new[$key]) {
				$changed[] = $key;
			}
		}

		return array_values(array_unique($changed));
	}//end changed()

	/**
	 * Whether every item of a list is an entry with a string `group`.
	 *
	 * @param array<int|string, mixed> $list The items.
	 *
	 * @return bool True for a non-empty list of group entries.
	 */
	private function isGroupList(array $list): bool {
		if ($list === []) {
			return false;
		}

		foreach ($list as $item) {
			if (is_array($item) === false || is_string($item['group'] ?? null) === false) {
				return false;
			}
		}

		return true;
	}//end isGroupList()

	/**
	 * A list of group entries keyed by group, each with its priority position,
	 * so a re-pointed, delegated or moved group differs and an untouched one does not.
	 *
	 * @param array<int|string, mixed> $list The group entries, in priority order.
	 *
	 * @return array<string, array<string, mixed>> Group => entry plus position.
	 */
	private function byGroup(array $list): array {
		$keyed = [];
		foreach (array_values($list) as $position => $entry) {
			if (is_array($entry) === true) {
				$keyed[(string)$entry['group']] = array_merge($entry, ['position' => $position]);
			}
		}

		return $keyed;
	}//end byGroup()

	/**
	 * The identity of one list item: a scalar as itself, anything else as its JSON.
	 *
	 * @param mixed $item The item.
	 *
	 * @return string The identity.
	 */
	private function identityOf(mixed $item): string {
		if (is_scalar($item) === true || $item === null) {
			return (string)$item;
		}

		return (string)json_encode($item);
	}//end identityOf()
}//end class
