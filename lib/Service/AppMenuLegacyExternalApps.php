<?php

/**
 * Thematiq apps menu: move the admin page's external apps into the menu settings, once.
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
 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-the-external-apps-of-the-admin-page-move-into-the-menu-settings
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\IConfig;
use OCP\IL10N;

/**
 * Before the menu settings, an administrator gave the external apps an address each on the admin
 * page (app values `external_apps`, `openproject_url`, `openproject_modules`). Those apps become
 * entries of the administrator's `menu.externalApps` with their title, payoff, address and icon,
 * and keep their menu keys (`external-openproject-boards`, `external-wiki`), so a person's order,
 * pins and hidden apps still name them. The old values are removed, so the menu settings are the
 * only source. A marker makes it run once: a later reset of the defaults does not bring them back.
 *
 * @SuppressWarnings(PHPMD.StaticAccess) - AppMenuExternalApp holds the external app rule.
 *
 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-the-external-apps-of-the-admin-page-move-into-the-menu-settings
 */
class AppMenuLegacyExternalApps {

	/**
	 * The app config key that records the move.
	 *
	 * @var string
	 */
	public const MARKER = 'menu.externalAppsMigrated';

	/**
	 * Constructor.
	 *
	 * @param IConfig $config Reads the old values and the marker.
	 * @param ExternalApps $externalApps Reads the admin page's apps.
	 * @param AppMenuSettings $settings Stores the moved apps.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly ExternalApps $externalApps,
		private readonly AppMenuSettings $settings,
	) {
	}//end __construct()

	/**
	 * Move the apps when that has not happened yet.
	 *
	 * @param IL10N $l The language the moved titles are written in.
	 *
	 * @return int The number of apps moved; 0 when there was nothing to move or it ran before.
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-the-external-apps-of-the-admin-page-move-into-the-menu-settings
	 */
	public function migrateOnce(IL10N $l): int {
		if ($this->config->getAppValue(Application::APP_ID, self::MARKER, '') === '1') {
			return 0;
		}

		$moved = $this->moved(l: $l);
		$current = ($this->settings->defaults()['externalApps'] ?? []);
		$taken = array_column($current, 'id');
		$added = 0;
		foreach ($moved as $app) {
			if (in_array($app['id'], $taken, true) === true) {
				continue;
			}

			$app['order'] = count($current);
			$current[] = $app;
			$added++;
		}

		if ($added > 0) {
			$this->settings->saveDefaults(values: ['externalApps' => $current], known: []);
		}

		foreach ([ExternalApps::CONFIG_KEY, OpenProjectModules::URL_KEY, OpenProjectModules::MODULES_KEY] as $key) {
			$this->config->deleteAppValue(Application::APP_ID, $key);
		}

		$this->config->setAppValue(Application::APP_ID, self::MARKER, '1');

		return $added;
	}//end migrateOnce()

	/**
	 * The admin page's apps as external apps of the menu settings; one whose address the menu
	 * settings refuse is left out.
	 *
	 * @param IL10N $l The language of the titles.
	 *
	 * @return array<int, array<string, mixed>> The apps.
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-the-external-apps-of-the-admin-page-move-into-the-menu-settings
	 */
	private function moved(IL10N $l): array {
		$apps = [];
		$icons = AppMenuExternalApp::icons();
		foreach (($this->externalApps->section(l: $l)['apps'] ?? []) as $index => $old) {
			$id = substr((string)$old['key'], strlen('external-'));
			$name = 'app-' . $id;
			if (str_starts_with($id, 'openproject-') === true) {
				$name = str_replace('_', '-', $id);
			}

			if (isset($icons[$name]) === false) {
				$name = 'link';
			}

			$app = AppMenuExternalApp::clean(
				app: [
					'id' => $id,
					'title' => (string)$old['label'],
					'payoff' => (string)$old['line'],
					'url' => (string)$old['url'],
					'icon' => ['library' => $name],
					'visible' => true,
					'order' => $index,
				],
				index: $index
			);
			if ($app !== null) {
				$apps[] = $app;
			}
		}

		return $apps;
	}//end moved()
}//end class
