<?php

/**
 * Thematiq apps menu: the former single create switch, moved into the two create switches.
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
 * @spec openspec/changes/quick-actions-toggles/specs/apps-menu-settings/spec.md#requirement-the-former-create-switch-moves-into-the-two-new-switches-req-tqqt-002
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\IConfig;

/**
 * `showCreateActions` was one administrator's switch for both the Create button and the Create
 * row. It is read as both create switches (AppMenuSettings::CREATE_SWITCHES): once from the
 * stored app value, which is then removed, and on every save that still sends it.
 *
 * @spec openspec/changes/quick-actions-toggles/specs/apps-menu-settings/spec.md#requirement-the-former-create-switch-moves-into-the-two-new-switches-req-tqqt-002
 */
final class AppMenuFormerCreateSwitch {

	/**
	 * Constructor.
	 *
	 * @param IConfig $config Reads and writes the app values.
	 */
	public function __construct(
		private readonly IConfig $config,
	) {
	}//end __construct()

	/**
	 * Copy the stored former switch into each create switch that is not stored yet, then remove
	 * it. A stored value that is not true or false is removed without a copy. Once the old key is
	 * gone this does nothing.
	 *
	 * @return bool Whether a stored value was there.
	 *
	 * @spec openspec/changes/quick-actions-toggles/specs/apps-menu-settings/spec.md#requirement-the-former-create-switch-moves-into-the-two-new-switches-req-tqqt-002
	 */
	public function move(): bool {
		$former = AppMenuSettings::PREFIX . AppMenuSettings::FORMER_CREATE_SWITCH;
		$stored = (string)$this->config->getAppValue(Application::APP_ID, $former, '');
		if ($stored === '') {
			return false;
		}

		$value = json_decode($stored, true);
		if (is_bool($value) === true) {
			foreach (AppMenuSettings::CREATE_SWITCHES as $key) {
				if ((string)$this->config->getAppValue(Application::APP_ID, AppMenuSettings::PREFIX . $key, '') === '') {
					$this->config->setAppValue(Application::APP_ID, AppMenuSettings::PREFIX . $key, (string)json_encode($value));
				}
			}
		}

		$this->config->deleteAppValue(Application::APP_ID, $former);

		return true;
	}//end move()

	/**
	 * Values as sent with the former key read as each create switch that is not sent itself.
	 *
	 * @param array<mixed> $values The values as sent.
	 *
	 * @return array<mixed> The values without the former key.
	 *
	 * @spec openspec/changes/quick-actions-toggles/specs/apps-menu-settings/spec.md#requirement-the-former-create-switch-moves-into-the-two-new-switches-req-tqqt-002
	 */
	public static function strip(array $values): array {
		if (array_key_exists(AppMenuSettings::FORMER_CREATE_SWITCH, $values) === false) {
			return $values;
		}

		$former = $values[AppMenuSettings::FORMER_CREATE_SWITCH];
		unset($values[AppMenuSettings::FORMER_CREATE_SWITCH]);
		foreach (AppMenuSettings::CREATE_SWITCHES as $key) {
			if (array_key_exists($key, $values) === false) {
				$values[$key] = $former;
			}
		}

		return $values;
	}//end strip()
}//end class
