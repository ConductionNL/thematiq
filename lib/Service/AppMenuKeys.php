<?php

/**
 * The keys of the entries a person may pin or hide in the apps menu.
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
 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-pins/spec.md#requirement-pins-are-stored-for-the-person-on-the-server
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\INavigationManager;

/**
 * The entry keys js/app-menu.js uses, as the server knows them for the signed-in person: every app
 * in Nextcloud's navigation, by its id and by the app id in its address, and every external app of
 * the menu. A value a person sends is checked against this list before it is stored.
 *
 * @SuppressWarnings(PHPMD.StaticAccess) - AppMenuExternalApp::key() names an external app's entry.
 *
 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-pins/spec.md#requirement-pins-are-stored-for-the-person-on-the-server
 */
class AppMenuKeys {

	/**
	 * Constructor.
	 *
	 * @param INavigationManager $navigation The person's navigation entries.
	 * @param AppMenuSettings|null $settings The external apps an administrator or the person added.
	 */
	public function __construct(
		private readonly INavigationManager $navigation,
		private readonly ?AppMenuSettings $settings = null,
	) {
	}//end __construct()

	/**
	 * The keys of the entries the person can see in the apps menu.
	 *
	 * @param string|null $userId The person, for their own external apps.
	 *
	 * @return array<int, string> The keys.
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-pins/spec.md#requirement-pins-are-stored-for-the-person-on-the-server
	 */
	public function known(?string $userId = null): array {
		$keys = [];
		foreach ($this->navigation->getAll(INavigationManager::TYPE_APPS) as $entry) {
			$keys[] = (string)($entry['id'] ?? '');
			if (preg_match('/\/apps\/([a-z0-9_]+)/', (string)($entry['href'] ?? ''), $match) === 1) {
				$keys[] = $match[1];
			}
		}

		foreach (($this->settings?->effective(userId: $userId)['externalApps'] ?? []) as $app) {
			$keys[] = AppMenuExternalApp::key(app: $app);
		}

		return array_values(array_unique(array_filter($keys, static fn (string $key): bool => $key !== '')));
	}//end known()
}//end class
