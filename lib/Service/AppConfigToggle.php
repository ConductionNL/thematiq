<?php

/**
 * An administrator's on/off switch kept as `yes` or `no` in an app config value.
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
 * @spec openspec/specs/theme-gallery/spec.md#requirement-the-gallery-is-opt-in-and-disclosed
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\IConfig;

/**
 * An opt-in switch: off until an administrator turns it on.
 *
 * @spec openspec/specs/theme-gallery/spec.md#requirement-the-gallery-is-opt-in-and-disclosed
 */
final class AppConfigToggle {

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The app config.
	 * @param string $key The app config key the switch is stored under.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly string $key,
	) {
	}//end __construct()

	/**
	 * Whether the switch is on.
	 *
	 * @return boolean True when an administrator turned it on.
	 *
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-the-gallery-is-opt-in-and-disclosed
	 */
	public function isOn(): bool {
		return $this->config->getAppValue(Application::APP_ID, $this->key, 'no') === 'yes';
	}//end isOn()

	/**
	 * Turn the switch on or off.
	 *
	 * @param boolean $enabled The new state.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-the-gallery-is-opt-in-and-disclosed
	 */
	public function set(bool $enabled): void {
		$value = 'no';
		if ($enabled === true) {
			$value = 'yes';
		}

		$this->config->setAppValue(Application::APP_ID, $this->key, $value);
	}//end set()
}//end class
