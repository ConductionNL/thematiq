<?php

/**
 * NL Design Group Mapping Rules.
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
use OCP\IGroupManager;

/**
 * The rules a group theming entry obeys: how a stored entry is read, and
 * what a submitted entry must be (a group and a set, no duplicate group, a
 * group that exists, a set that is available and, when restricted,
 * confirmed). Built by GroupThemingService.
 *
 * @spec openspec/specs/per-group-theming/spec.md
 */
final class GroupMappingRules {

	/**
	 * Constructor.
	 *
	 * @param IGroupManager $groups The group manager, for existence checks.
	 * @param TokenSetService $tokenSets The token set service, for availability and the licence gate.
	 */
	public function __construct(
		private readonly IGroupManager $groups,
		private readonly TokenSetService $tokenSets,
	) {
	}//end __construct()

	/**
	 * One stored entry as the mapping carries it, null when it has no string group and token set.
	 *
	 * @param mixed $entry The stored entry.
	 *
	 * @return array<string, mixed>|null The entry.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function read(mixed $entry): array|null {
		if (is_array($entry) === false) {
			return null;
		}

		$clean = $this->storedIdentity(entry: $entry);
		if ($clean === null) {
			return null;
		}

		return (new GroupDelegationRules())->read(entry: $entry, clean: $clean);
	}//end read()

	/**
	 * Validate one raw mapping entry against the group/token-set/duplicate
	 * rules, recording its group in the batch so a duplicate anywhere in the
	 * payload is caught.
	 *
	 * @param mixed $entry The raw entry (may be malformed).
	 * @param GroupEntryRun $run The batch: the groups already validated in it, and the stored set of a group.
	 *
	 * @return array{group: string, tokenSet: string, delegated?: true, allowedTokenSets?: list<string>} The cleaned entry.
	 *
	 * @throws GroupThemingValidationException When the entry fails any validation rule.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function validate(mixed $entry, GroupEntryRun $run): array {
		$run->entry = $entry;
		$clean = $this->identityOf(entry: $entry);
		if (isset($run->seenGroups[$clean['group']]) === true) {
			throw new GroupThemingValidationException(
				entry: $entry,
				reason: sprintf('Only one mapping entry is allowed per group ("%s" appears more than once).', $clean['group'])
			);
		}

		$this->assertMayMap(run: $run, clean: $clean);
		$run->seenGroups[$clean['group']] = true;

		return (new GroupDelegationRules())->validate(
			entry: $entry,
			clean: $clean,
			isAvailable: fn (string $setId): bool => $this->tokenSets->isValidTokenSet(tokenSetId: $setId)
		);
	}//end validate()

	/**
	 * The refusal for an entry whose group has no stored entry to replace.
	 *
	 * @param array<string, mixed> $entry The entry.
	 *
	 * @return GroupThemingValidationException The refusal.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function unmapped(array $entry): GroupThemingValidationException {
		return new GroupThemingValidationException(entry: $entry, reason: 'The group has no mapping entry.');
	}//end unmapped()

	/**
	 * The group and token set of a stored entry, null unless both are strings.
	 *
	 * @param array<string, mixed> $entry The stored entry.
	 *
	 * @return array{group: string, tokenSet: string}|null The pair.
	 */
	private function storedIdentity(array $entry): array|null {
		$group = ($entry['group'] ?? null);
		$tokenSet = ($entry['tokenSet'] ?? null);
		if (is_string($group) === false || is_string($tokenSet) === false) {
			return null;
		}

		return ['group' => $group, 'tokenSet' => $tokenSet];
	}//end storedIdentity()

	/**
	 * The group and token set a raw entry names, both non-empty strings.
	 *
	 * @param mixed $entry The raw entry (may be malformed).
	 *
	 * @return array{group: string, tokenSet: string} The pair.
	 *
	 * @throws GroupThemingValidationException When either is missing or not a non-empty string.
	 */
	private function identityOf(mixed $entry): array {
		$group = $this->extractField(entry: $entry, field: 'group');
		$tokenSet = $this->extractField(entry: $entry, field: 'tokenSet');

		if (is_string($group) === false || $group === '') {
			throw new GroupThemingValidationException(entry: $entry, reason: 'Missing or invalid group id.');
		}

		if (is_string($tokenSet) === false || $tokenSet === '') {
			throw new GroupThemingValidationException(entry: $entry, reason: 'Missing or invalid token set id.');
		}

		return ['group' => $group, 'tokenSet' => $tokenSet];
	}//end identityOf()

	/**
	 * Refuse an entry whose group does not exist or whose set is not available,
	 * then one whose set is restricted and unconfirmed.
	 *
	 * @param GroupEntryRun $run The batch, holding the raw entry.
	 * @param array{group: string, tokenSet: string} $clean The group and token set it names.
	 *
	 * @return void
	 *
	 * @throws GroupThemingValidationException When one of the rules refuses it.
	 */
	private function assertMayMap(GroupEntryRun $run, array $clean): void {
		if ($this->groups->groupExists($clean['group']) === false) {
			throw new GroupThemingValidationException(entry: $run->entry, reason: sprintf('Group "%s" does not exist.', $clean['group']));
		}

		if ($this->tokenSets->isValidTokenSet(tokenSetId: $clean['tokenSet']) === false) {
			throw new GroupThemingValidationException(entry: $run->entry, reason: sprintf('Token set "%s" is not available.', $clean['tokenSet']));
		}

		$this->assertConfirmedIfRestricted(run: $run, clean: $clean);
	}//end assertMayMap()

	/**
	 * Refuse a set restricted to its owner that nobody confirmed. A group that
	 * keeps its stored set is not a new use of it.
	 *
	 * @param GroupEntryRun $run The batch, holding the raw entry and the stored set lookup.
	 * @param array{group: string, tokenSet: string} $clean The group and token set it names.
	 *
	 * @return void
	 *
	 * @throws GroupThemingValidationException Naming the restricted set.
	 */
	private function assertConfirmedIfRestricted(GroupEntryRun $run, array $clean): void {
		$tokenSet = $clean['tokenSet'];
		$stored = ($run->storedSetOf)($clean['group']);
		if ($this->tokenSets->licenceGate()->refuses(tokenSetId: $tokenSet, alreadyApplied: $stored) === true) {
			throw new GroupThemingValidationException(
				entry: $run->entry,
				reason: sprintf('Token set "%s" is restricted to its owner and has not been confirmed.', $tokenSet),
				restrictedSet: $tokenSet
			);
		}
	}//end assertConfirmedIfRestricted()

	/**
	 * Extract a string field from a raw, possibly malformed mapping entry.
	 *
	 * @param mixed $entry The raw entry.
	 * @param string $field The field name (`group` or `tokenSet`).
	 *
	 * @return mixed The field value, or null when the entry is not an array or lacks the field.
	 */
	private function extractField(mixed $entry, string $field): mixed {
		if (is_array($entry) === false) {
			return null;
		}

		return ($entry[$field] ?? null);
	}//end extractField()
}//end class
