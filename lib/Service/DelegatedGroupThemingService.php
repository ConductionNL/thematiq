<?php

/**
 * Thematiq delegated group house style: a group subadmin picks the group's set.
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

use OCA\Thematiq\Service\Exception\DelegationRefusedException;
use OCA\Thematiq\Service\Exception\GroupThemingValidationException;
use OCP\Group\ISubAdmin;
use OCP\IGroupManager;
use OCP\IUserManager;

/**
 * An administrator marks a group mapping as delegated and lists the sets the
 * group may use. A subadmin of that group (Nextcloud's owner role per group)
 * then picks the group's set from that list, and nothing else: not the
 * priority, not the allowed list, not another group.
 *
 * Every choice is checked on every call: the user must be subadmin of the
 * named group, the group must be delegated, and the set must be on its
 * allowed list. The change goes through {@see GroupThemingService::setMapping()},
 * so it is validated again, bumps the generation the resolution cache keys
 * on, and is audited with the subadmin as actor.
 *
 * @spec openspec/specs/per-group-theming/spec.md
 */
class DelegatedGroupThemingService {

	/**
	 * Constructor.
	 *
	 * @param GroupThemingService $groupTheming The group mapping.
	 * @param TokenSetService $tokenSets The token set catalogue, for names and contrast.
	 * @param IGroupManager $groupManager Groups.
	 * @param IUserManager $userManager Users.
	 * @param ISubAdmin $subAdmin Nextcloud's group subadmin role.
	 * @param ThemingAuditService $audit The audit log.
	 */
	public function __construct(
		private readonly GroupThemingService $groupTheming,
		private readonly TokenSetService $tokenSets,
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
		private readonly ISubAdmin $subAdmin,
		private readonly ThemingAuditService $audit,
	) {
	}//end __construct()

	/**
	 * Whether a user is subadmin of at least one delegated group.
	 *
	 * @param string $uid The user id.
	 *
	 * @return bool True when the personal section has something to offer.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function hasDelegatedGroups(string $uid): bool {
		foreach ($this->groupTheming->getMapping() as $entry) {
			if ($this->isDelegatedTo(entry: $entry, uid: $uid) === true) {
				return true;
			}
		}

		return false;
	}//end hasDelegatedGroups()

	/**
	 * The delegated groups a user is subadmin of, with the allowed sets.
	 *
	 * @param string $uid The user id.
	 *
	 * @return array<int, array<string, mixed>> `{group, displayName, tokenSet, allowedTokenSets: [{id, name, primaryColor, wcagLevel}]}`.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function listForUser(string $uid): array {
		$catalogue = [];
		$result = [];
		foreach ($this->groupTheming->getMapping() as $entry) {
			if ($this->isDelegatedTo(entry: $entry, uid: $uid) === false) {
				continue;
			}

			if ($catalogue === []) {
				$catalogue = $this->catalogueById();
			}

			$result[] = $this->listedGroup(entry: $entry, catalogue: $catalogue);
		}

		return $result;
	}//end listForUser()

	/**
	 * Whether a mapping entry is delegated and the user is subadmin of its group.
	 *
	 * @param array<string, mixed> $entry The mapping entry.
	 * @param string $uid The user id.
	 *
	 * @return bool True when the user may choose its set.
	 */
	private function isDelegatedTo(array $entry, string $uid): bool {
		return ($entry['delegated'] ?? false) === true && $this->isSubAdmin(uid: $uid, group: $entry['group']) === true;
	}//end isDelegatedTo()

	/**
	 * One delegated group as the subadmin's page lists it, with the allowed sets the catalogue holds.
	 *
	 * @param array<string, mixed> $entry The mapping entry.
	 * @param array<string, array<string, mixed>> $catalogue The public catalogue by set id.
	 *
	 * @return array<string, mixed> `{group, displayName, tokenSet, allowedTokenSets}`.
	 */
	private function listedGroup(array $entry, array $catalogue): array {
		$allowed = [];
		foreach ($entry['allowedTokenSets'] as $setId) {
			if (isset($catalogue[$setId]) === true) {
				$allowed[] = $catalogue[$setId];
			}
		}

		$group = $this->groupManager->get($entry['group']);

		return [
			'group' => $entry['group'],
			'displayName' => ($group?->getDisplayName() ?? $entry['group']),
			'tokenSet' => $entry['tokenSet'],
			'allowedTokenSets' => $allowed,
		];
	}//end listedGroup()

	/**
	 * Check that a user may set a group's token set, and return the group's entry.
	 *
	 * @param string $uid The user id.
	 * @param DelegatedChoice $choice The group and the requested set.
	 *
	 * @return array<string, mixed> The group's mapping entry.
	 *
	 * @throws DelegationRefusedException When the user is not subadmin, the group is not delegated, or the set is not allowed.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function requireDelegatedChoice(string $uid, DelegatedChoice $choice): array {
		if ($this->isSubAdmin(uid: $uid, group: $choice->group) === false) {
			throw new DelegationRefusedException(message: 'You are not a subadmin of this group.');
		}

		$entry = $this->mappedEntry(group: $choice->group);
		if ($entry === null || ($entry['delegated'] ?? false) !== true) {
			throw new DelegationRefusedException(message: 'The house style of this group is not delegated.');
		}

		$this->assertAllowed(entry: $entry, tokenSet: $choice->tokenSet);

		return $entry;
	}//end requireDelegatedChoice()

	/**
	 * Refuse a set the delegated entry does not allow, or that no longer exists.
	 *
	 * @param array<string, mixed> $entry The group's delegated entry.
	 * @param string $tokenSet The requested set.
	 *
	 * @return void
	 *
	 * @throws DelegationRefusedException When the set is not allowed.
	 */
	private function assertAllowed(array $entry, string $tokenSet): void {
		if (in_array($tokenSet, $entry['allowedTokenSets'], true) === false
			|| $this->tokenSets->isValidTokenSet(tokenSetId: $tokenSet) === false
		) {
			throw new DelegationRefusedException(message: 'This token set is not allowed for this group.');
		}
	}//end assertAllowed()

	/**
	 * The first mapping entry of a group.
	 *
	 * @param string $group The group id.
	 *
	 * @return array<string, mixed>|null The entry, or null when the group has none.
	 */
	private function mappedEntry(string $group): array|null {
		foreach ($this->groupTheming->getMapping() as $entry) {
			if ($entry['group'] === $group) {
				return $entry;
			}
		}

		return null;
	}//end mappedEntry()

	/**
	 * Set a delegated group's token set, as its subadmin.
	 *
	 * @param string $uid The subadmin's user id.
	 * @param DelegatedChoice $choice The group and the chosen set.
	 *
	 * @return array<string, mixed> The updated entry.
	 *
	 * @throws DelegationRefusedException When the choice is not allowed.
	 * @throws GroupThemingValidationException When the mapping refuses the change.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function setDelegatedTokenSet(string $uid, DelegatedChoice $choice): array {
		$current = $this->requireDelegatedChoice(uid: $uid, choice: $choice);
		if ($current['tokenSet'] === $choice->tokenSet) {
			return $current;
		}

		$this->groupTheming->replaceEntry(entry: array_merge($current, ['tokenSet' => $choice->tokenSet]));
		$this->auditChoice(uid: $uid, change: ['old' => $current['tokenSet'], 'new' => $choice->tokenSet, 'group' => $choice->group]);

		$current['tokenSet'] = $choice->tokenSet;

		return $current;
	}//end setDelegatedTokenSet()

	/**
	 * Audit a subadmin's change of a delegated group's set.
	 *
	 * @param string $uid The subadmin's user id.
	 * @param array{old: string, new: string, group: string} $change The set before and after, and the group.
	 *
	 * @return void
	 */
	private function auditChoice(string $uid, array $change): void {
		$this->audit->log(
			action: 'token_set_changed',
			context: ['actor' => $uid] + $change + ['delegated' => true]
		);
	}//end auditChoice()

	/**
	 * Whether a user is subadmin of a group.
	 *
	 * @param string $uid The user id.
	 * @param string $group The group id.
	 *
	 * @return bool True when subadmin.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	private function isSubAdmin(string $uid, string $group): bool {
		$user = $this->userManager->get($uid);
		$groupObject = $this->groupManager->get($group);
		if ($user === null || $groupObject === null) {
			return false;
		}

		return $this->subAdmin->isSubAdminOfGroup($user, $groupObject);
	}//end isSubAdmin()

	/**
	 * The public catalogue keyed by set id, with what the section shows.
	 *
	 * @return array<string, array{id: string, name: string, primaryColor: string|null, wcagLevel: string|null}> The sets.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	private function catalogueById(): array {
		$result = [];
		foreach ($this->tokenSets->getPublicCatalogue() as $set) {
			$result[$set['id']] = [
				'id' => $set['id'],
				'name' => $set['name'],
				'primaryColor' => ($set['theming']['primary_color'] ?? null),
				'wcagLevel' => $set['wcagLevel'],
			];
		}

		return $result;
	}//end catalogueById()
}//end class
