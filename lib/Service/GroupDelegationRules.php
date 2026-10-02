<?php

/**
 * Thematiq group delegation rules: the delegation fields of a group mapping entry.
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

use OCA\Thematiq\Service\Exception\GroupThemingValidationException;

/**
 * Reads and validates `delegated` and `allowedTokenSets` on a group mapping
 * entry. A delegated entry needs a non-empty allowed list that holds its
 * current set and names only available sets. An entry without delegation, or
 * one stored before delegation existed, keeps the two-field shape
 * `{group, tokenSet}` and reads as not delegated.
 *
 * @spec openspec/specs/per-group-theming/spec.md
 */
class GroupDelegationRules {

	/**
	 * Carry the delegation fields of a stored entry.
	 *
	 * @param array<string, mixed> $entry The stored entry.
	 * @param array{group: string, tokenSet: string} $clean The entry's group and set.
	 *
	 * @return array<string, mixed> The entry, with `delegated: true` and `allowedTokenSets` when delegated.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function read(array $entry, array $clean): array {
		if (($entry['delegated'] ?? false) !== true || is_array($entry['allowedTokenSets'] ?? null) === false) {
			return $clean;
		}

		$allowed = array_values(array_filter($entry['allowedTokenSets'], 'is_string'));
		if ($allowed === []) {
			return $clean;
		}

		$clean['delegated'] = true;
		$clean['allowedTokenSets'] = $allowed;

		return $clean;
	}//end read()

	/**
	 * Validate the delegation fields of a submitted entry.
	 *
	 * @param mixed $entry The submitted entry.
	 * @param array{group: string, tokenSet: string} $clean The validated group and set.
	 * @param callable(string): bool $isAvailable Whether a token set id is available.
	 *
	 * @return array<string, mixed> The entry to store.
	 *
	 * @throws GroupThemingValidationException When the allowed list is invalid.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function validate(mixed $entry, array $clean, callable $isAvailable): array {
		if (is_array($entry) === false || $this->isOn(value: ($entry['delegated'] ?? null)) === false) {
			return $clean;
		}

		$allowed = ($entry['allowedTokenSets'] ?? null);
		if (is_array($allowed) === false || $allowed === []) {
			throw new GroupThemingValidationException(entry: $entry, reason: 'A delegated group needs at least one allowed token set.');
		}

		$cleanAllowed = [];
		foreach ($allowed as $setId) {
			if (is_string($setId) === false || $isAvailable($setId) === false) {
				throw new GroupThemingValidationException(
					entry: $entry,
					reason: sprintf('Allowed token set %s is not available.', (string)json_encode($setId))
				);
			}

			$cleanAllowed[$setId] = true;
		}

		if (isset($cleanAllowed[$clean['tokenSet']]) === false) {
			throw new GroupThemingValidationException(
				entry: $entry,
				reason: sprintf('The allowed token sets of group "%s" must include its current set.', $clean['group'])
			);
		}

		$clean['delegated'] = true;
		$clean['allowedTokenSets'] = array_keys($cleanAllowed);

		return $clean;
	}//end validate()

	/**
	 * Whether a submitted flag is on.
	 *
	 * @param mixed $value The submitted value.
	 *
	 * @return bool True for true, "true", "1" or 1.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	private function isOn(mixed $value): bool {
		return in_array($value, [true, 'true', '1', 1], true);
	}//end isOn()
}//end class
