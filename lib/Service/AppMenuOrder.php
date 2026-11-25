<?php

/**
 * Thematiq apps menu order, per user.
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
 * @spec openspec/specs/apps-menu-order/spec.md#requirement-the-order-is-stored-for-the-person-on-the-server
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\IConfig;

/**
 * The order a person gave the entries of the workplace apps menu, kept in their own user settings
 * so it follows them to every browser. js/app-menu.js reads it from the menu's initial state and
 * saves it through AppMenuOrderController.
 *
 * @spec openspec/specs/apps-menu-order/spec.md#requirement-the-order-is-stored-for-the-person-on-the-server
 */
class AppMenuOrder {

	/**
	 * The user config key.
	 *
	 * @var string
	 */
	public const KEY = 'menu.order';

	/**
	 * The user config key the order had before the menu settings, still read when KEY is absent.
	 *
	 * @var string
	 */
	public const LEGACY_KEY = 'app_menu_order';

	/**
	 * The most keys kept.
	 *
	 * @var int
	 */
	public const MAX = 200;

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
	 * The stored order of a user, cleaned as on save.
	 *
	 * @param string $userId The user.
	 *
	 * @return array<int, string> The keys.
	 *
	 * @spec openspec/specs/apps-menu-order/spec.md#requirement-the-order-is-stored-for-the-person-on-the-server
	 */
	public function get(string $userId): array {
		$raw = (string)$this->config->getUserValue($userId, Application::APP_ID, self::KEY, '');
		if ($raw === '') {
			$raw = (string)$this->config->getUserValue($userId, Application::APP_ID, self::LEGACY_KEY, '');
		}

		$stored = json_decode($raw, true);
		if (is_array($stored) === false) {
			return [];
		}

		return self::clean(keys: $stored);
	}//end get()

	/**
	 * Store a user's order. An empty list removes the value.
	 *
	 * @param string $userId The user.
	 * @param array<mixed> $keys The keys as sent.
	 *
	 * @return array<int, string> The keys stored.
	 *
	 * @spec openspec/specs/apps-menu-order/spec.md#requirement-the-order-is-stored-for-the-person-on-the-server
	 */
	public function save(string $userId, array $keys): array {
		$clean = self::clean(keys: $keys);
		$this->config->deleteUserValue($userId, Application::APP_ID, self::LEGACY_KEY);
		if ($clean === []) {
			$this->config->deleteUserValue($userId, Application::APP_ID, self::KEY);
			return [];
		}

		$this->config->setUserValue($userId, Application::APP_ID, self::KEY, (string)json_encode($clean));

		return $clean;
	}//end save()

	/**
	 * The keys that may be stored: an entry key of lower case letters, digits, underscores and
	 * hyphens, each once, at most MAX.
	 *
	 * @param array<mixed> $keys The keys.
	 *
	 * @return array<int, string> The clean keys.
	 *
	 * @spec openspec/specs/apps-menu-order/spec.md#requirement-the-order-is-stored-for-the-person-on-the-server
	 */
	public static function clean(array $keys): array {
		$clean = [];
		foreach ($keys as $key) {
			if (is_string($key) === true && preg_match('/^[a-z0-9_-]{1,80}$/', $key) === 1) {
				$clean[$key] = true;
			}
		}

		return array_slice(array_keys($clean), 0, self::MAX);
	}//end clean()
}//end class
