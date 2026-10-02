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
	 * @param TokenSetService     $tokenSets    The token set catalogue, for names and contrast.
	 * @param IGroupManager       $groupManager Groups.
	 * @param IUserManager        $userManager  Users.
	 * @param ISubAdmin           $subAdmin     Nextcloud's group subadmin role.
	 * @param ThemingAuditService $audit        The audit log.
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
			if (($entry['delegated'] ?? false) === true && $this->isSubAdmin(uid: $uid, group: $entry['group']) === true) {
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
			if (($entry['delegated'] ?? false) !== true || $this->isSubAdmin(uid: $uid, group: $entry['group']) === false) {
				continue;
			}

			if ($catalogue === []) {
				$catalogue = $this->catalogueById();
			}

			$allowed = [];
			foreach ($entry['allowedTokenSets'] as $setId) {
				if (isset($catalogue[$setId]) === true) {
					$allowed[] = $catalogue[$setId];
				}
			}

			$group = $this->groupManager->get($entry['group']);
			$result[] = [
				'group' => $entry['group'],
				'displayName' => ($group?->getDisplayName() ?? $entry['group']),
				'tokenSet' => $entry['tokenSet'],
				'allowedTokenSets' => $allowed,
			];
		}

		return $result;
	}//end listForUser()

	/**
	 * Check that a user may set a group's token set, and return the group's entry.
	 *
	 * @param string $uid The user id.
	 * @param string $group The group id.
	 * @param string $tokenSet The requested set.
	 *
	 * @return array<string, mixed> The group's mapping entry.
	 *
	 * @throws DelegationRefusedException When the user is not subadmin, the group is not delegated, or the set is not allowed.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function requireDelegatedChoice(string $uid, string $group, string $tokenSet): array {
		if ($this->isSubAdmin(uid: $uid, group: $group) === false) {
			throw new DelegationRefusedException(message: 'You are not a subadmin of this group.');
		}

		foreach ($this->groupTheming->getMapping() as $entry) {
			if ($entry['group'] !== $group) {
				continue;
			}

			if (($entry['delegated'] ?? false) !== true) {
				throw new DelegationRefusedException(message: 'The house style of this group is not delegated.');
			}

			if (in_array($tokenSet, $entry['allowedTokenSets'], true) === false
				|| $this->tokenSets->isValidTokenSet(tokenSetId: $tokenSet) === false
			) {
				throw new DelegationRefusedException(message: 'This token set is not allowed for this group.');
			}

			return $entry;
		}

		throw new DelegationRefusedException(message: 'The house style of this group is not delegated.');
	}//end requireDelegatedChoice()

	/**
	 * Set a delegated group's token set, as its subadmin.
	 *
	 * @param string $uid The subadmin's user id.
	 * @param string $group The group id.
	 * @param string $tokenSet The chosen set.
	 *
	 * @return array<string, mixed> The updated entry.
	 *
	 * @throws DelegationRefusedException When the choice is not allowed.
	 * @throws GroupThemingValidationException When the mapping refuses the change.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function setDelegatedTokenSet(string $uid, string $group, string $tokenSet): array {
		$current = $this->requireDelegatedChoice(uid: $uid, group: $group, tokenSet: $tokenSet);
		if ($current['tokenSet'] === $tokenSet) {
			return $current;
		}

		$this->groupTheming->replaceEntry(entry: array_merge($current, ['tokenSet' => $tokenSet]));

		$this->audit->log(
			action: 'token_set_changed',
			context: [
				'actor' => $uid,
				'old' => $current['tokenSet'],
				'new' => $tokenSet,
				'group' => $group,
				'delegated' => true,
			]
		);

		$current['tokenSet'] = $tokenSet;

		return $current;
	}//end setDelegatedTokenSet()

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
