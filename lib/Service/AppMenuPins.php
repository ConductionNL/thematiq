<?php

/**
 * Thematiq apps menu: the apps a person pinned to the top bar.
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
use OCP\IConfig;

/**
 * The apps a person pinned next to the grid button, first to last, kept in their own user settings
 * like {@see AppMenuOrder}. js/app-menu.js reads them from the menu's initial state and saves them
 * through AppMenuPinsController.
 *
 * @SuppressWarnings(PHPMD.StaticAccess) - AppMenuOrder::clean() holds the shared key rule.
 *
 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-pins/spec.md#requirement-pins-are-stored-for-the-person-on-the-server
 */
class AppMenuPins {

	/**
	 * The user config key.
	 *
	 * @var string
	 */
	public const KEY = 'app_menu_pins';

	/**
	 * The most pins.
	 *
	 * @var int
	 */
	public const MAX = 5;

	/**
	 * Constructor.
	 *
	 * @param IConfig $config Reads and writes the user value.
	 */
	public function __construct(
		private readonly IConfig $config,
	) {
	}//end __construct()

	/**
	 * The stored pins of a user.
	 *
	 * @param string $userId The user.
	 *
	 * @return array<int, string> The keys.
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-pins/spec.md#requirement-pins-are-stored-for-the-person-on-the-server
	 */
	public function get(string $userId): array {
		$stored = json_decode($this->config->getUserValue($userId, Application::APP_ID, self::KEY, ''), true);
		if (is_array($stored) === false) {
			return [];
		}

		return self::clean(keys: $stored, known: null);
	}//end get()

	/**
	 * Store a user's pins: only keys of entries the user can see, each once, at most MAX. An empty
	 * list removes the value.
	 *
	 * @param string $userId The user.
	 * @param array<mixed> $keys The keys as sent.
	 * @param array<int, string> $known The keys of the entries the user can see.
	 *
	 * @return array<int, string> The keys stored.
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-pins/spec.md#requirement-pins-are-stored-for-the-person-on-the-server
	 */
	public function save(string $userId, array $keys, array $known): array {
		$clean = self::clean(keys: $keys, known: $known);
		if ($clean === []) {
			$this->config->deleteUserValue($userId, Application::APP_ID, self::KEY);
			return [];
		}

		$this->config->setUserValue($userId, Application::APP_ID, self::KEY, (string)json_encode($clean));

		return $clean;
	}//end save()

	/**
	 * The keys that may be stored: the shape of an entry key, each once, on the known list when one
	 * is given, at most MAX.
	 *
	 * @param array<mixed> $keys The keys.
	 * @param array<int, string>|null $known The keys of the entries the user can see, or null.
	 *
	 * @return array<int, string> The clean keys.
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-pins/spec.md#requirement-pins-are-stored-for-the-person-on-the-server
	 */
	public static function clean(array $keys, ?array $known): array {
		$clean = array_values(
			array_filter(
				AppMenuOrder::clean(keys: $keys),
				static fn (string $key): bool => $known === null || in_array($key, $known, true) === true
			)
		);

		return array_slice($clean, 0, self::MAX);
	}//end clean()
}//end class
