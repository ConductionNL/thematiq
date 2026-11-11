<?php

/**
 * Thematiq: which token set applies to a given user.
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
 * @spec openspec/specs/document-house-style/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;

/**
 * The token set for a user by id, following the per-group-theming order.
 *
 * For the signed-in user it asks {@see GroupThemingService::resolveTokenSetForRequest()},
 * so an admin preview still wins. For another user (an app rendering a letter
 * in the background) it walks the group mapping the same way: the first entry
 * whose group the user is in and whose set still exists, else the instance
 * default. No user means the instance default.
 *
 * @spec openspec/specs/document-house-style/spec.md
 */
class UserTokenSetResolver {

	/**
	 * Constructor.
	 *
	 * @param GroupThemingService $groupTheming The group mapping and the session resolution.
	 * @param TokenSetService $tokenSets Which sets exist.
	 * @param IGroupManager $groupManager A user's groups.
	 * @param IUserManager $userManager Users by id.
	 * @param IUserSession $userSession The signed-in user.
	 * @param IConfig $config The instance default set.
	 */
	public function __construct(
		private readonly GroupThemingService $groupTheming,
		private readonly TokenSetService $tokenSets,
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
		private readonly IUserSession $userSession,
		private readonly IConfig $config,
	) {
	}//end __construct()

	/**
	 * The token set id for a user.
	 *
	 * @param string|null $uid The user id, or null for the instance default.
	 *
	 * @return string The token set id.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	public function forUser(?string $uid): string {
		if ($uid === null || $uid === '') {
			return $this->defaultSet();
		}

		if ($this->userSession->getUser()?->getUID() === $uid) {
			return $this->groupTheming->resolveTokenSetForRequest();
		}

		return $this->forOtherUser(uid: $uid);
	}//end forUser()

	/**
	 * The set of a user who is not the signed-in one: the first mapped group they are in whose set exists.
	 *
	 * @param string $uid The user id.
	 *
	 * @return string The token set id, the instance default for an unknown user or no match.
	 */
	private function forOtherUser(string $uid): string {
		$user = $this->userManager->get($uid);
		if ($user === null) {
			return $this->defaultSet();
		}

		return ($this->mappedSet(groups: $this->groupManager->getUserGroupIds($user)) ?? $this->defaultSet());
	}//end forOtherUser()

	/**
	 * The first mapping entry whose group is one of these and whose set still exists.
	 *
	 * @param array<int, string> $groups The user's group ids.
	 *
	 * @return string|null The token set id, or null when none matches.
	 */
	private function mappedSet(array $groups): string|null {
		foreach ($this->groupTheming->getMapping() as $entry) {
			if (in_array($entry['group'], $groups, true) === true
				&& $this->tokenSets->isValidTokenSet(tokenSetId: $entry['tokenSet']) === true
			) {
				return $entry['tokenSet'];
			}
		}

		return null;
	}//end mappedSet()

	/**
	 * The instance default set.
	 *
	 * @return string The token set id.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	private function defaultSet(): string {
		return (string)$this->config->getAppValue(Application::APP_ID, 'token_set', 'nextcloud');
	}//end defaultSet()
}//end class
