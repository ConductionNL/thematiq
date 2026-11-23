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

use OCA\Thematiq\AppInfo\Application;
use OCP\INavigationManager;
use OCP\L10N\IFactory;

/**
 * The entry keys js/app-menu.js uses, as the server knows them for the signed-in person: every app
 * in Nextcloud's navigation, by its id and by the app id in its address, and every external app of
 * the menu. A value a person sends is checked against this list before it is stored.
 *
 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-pins/spec.md#requirement-pins-are-stored-for-the-person-on-the-server
 */
class AppMenuKeys {

	/**
	 * Constructor.
	 *
	 * @param INavigationManager $navigation The person's navigation entries.
	 * @param ExternalApps $externalApps The external apps with an address.
	 * @param IFactory $l10nFactory The language of this request.
	 */
	public function __construct(
		private readonly INavigationManager $navigation,
		private readonly ExternalApps $externalApps,
		private readonly IFactory $l10nFactory,
	) {
	}//end __construct()

	/**
	 * The keys of the entries the person can see in the apps menu.
	 *
	 * @return array<int, string> The keys.
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-pins/spec.md#requirement-pins-are-stored-for-the-person-on-the-server
	 */
	public function known(): array {
		$keys = [];
		foreach ($this->navigation->getAll(INavigationManager::TYPE_APPS) as $entry) {
			$keys[] = (string)($entry['id'] ?? '');
			if (preg_match('/\/apps\/([a-z0-9_]+)/', (string)($entry['href'] ?? ''), $match) === 1) {
				$keys[] = $match[1];
			}
		}

		$section = $this->externalApps->section(l: $this->l10nFactory->get(Application::APP_ID));
		foreach (($section['apps'] ?? []) as $app) {
			$keys[] = (string)$app['key'];
		}

		return array_values(array_unique(array_filter($keys, static fn (string $key): bool => $key !== '')));
	}//end known()
}//end class
