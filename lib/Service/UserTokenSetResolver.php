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

		$user = $this->userManager->get($uid);
		if ($user === null) {
			return $this->defaultSet();
		}

		$groups = $this->groupManager->getUserGroupIds($user);
		foreach ($this->groupTheming->getMapping() as $entry) {
			if (in_array($entry['group'], $groups, true) === true
				&& $this->tokenSets->isValidTokenSet(tokenSetId: $entry['tokenSet']) === true
			) {
				return $entry['tokenSet'];
			}
		}

		return $this->defaultSet();
	}//end forUser()

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
