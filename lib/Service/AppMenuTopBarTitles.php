<?php

/**
 * Thematiq apps menu: whether the top bar shows the names of the pinned apps.
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
 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-show-titles-in-the-top-bar-is-a-theming-choice-of-the-administrator-req-tqmt-005
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\IConfig;

/**
 * The administrator's `menu.topBarTitles`: the names next to the pinned apps in the workplace top
 * bar, and the text labels of Nextcloud's own apps menu on a page without the workplace menu. Until
 * the repair step moved it, the older app value `show_menu_labels` ('1' or '0') stands in for it.
 * The OCS capability, the configuration bundle and the theme versions keep the name
 * `showMenuLabels` and carry this value.
 *
 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-show-titles-in-the-top-bar-is-a-theming-choice-of-the-administrator-req-tqmt-005
 */
class AppMenuTopBarTitles {

	/**
	 * The menu setting.
	 *
	 * @var string
	 */
	public const KEY = 'topBarTitles';

	/**
	 * The workplace bar's stylesheet (`LayoutOptionsService::HEADER_WORKPLACE_STYLESHEET`).
	 *
	 * @var string
	 */
	public const HEADER_WORKPLACE_STYLESHEET = 'header-workplace';

	/**
	 * The older app value it replaces.
	 *
	 * @var string
	 */
	public const LEGACY_KEY = 'show_menu_labels';

	/**
	 * The app value of the menu setting (`AppMenuSettings::PREFIX` and the key).
	 *
	 * @var string
	 */
	public const APP_KEY = 'menu.topBarTitles';

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
	 * The stored value: `menu.topBarTitles`, else the older `show_menu_labels`, else off.
	 *
	 * @return bool Whether the top bar shows the names.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-show-titles-in-the-top-bar-is-a-theming-choice-of-the-administrator-req-tqmt-005
	 */
	public function isOn(): bool {
		$stored = $this->stored();
		if ($stored !== null) {
			return $stored;
		}

		return $this->legacy() === true;
	}//end isOn()

	/**
	 * Store the administrator's value as `menu.topBarTitles`.
	 *
	 * @param bool $on Whether the top bar shows the names.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-show-titles-in-the-top-bar-is-a-theming-choice-of-the-administrator-req-tqmt-005
	 */
	public function set(bool $on): void {
		$this->config->setAppValue(Application::APP_ID, self::APP_KEY, (string)json_encode($on));
	}//end set()

	/**
	 * The value `menu.topBarTitles` holds, or null when it holds none.
	 *
	 * @return bool|null The stored value.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-show-titles-in-the-top-bar-is-a-theming-choice-of-the-administrator-req-tqmt-005
	 */
	public function stored(): ?bool {
		$value = json_decode((string)$this->config->getAppValue(Application::APP_ID, self::APP_KEY, ''), true);
		if (is_bool($value) === false) {
			return null;
		}

		return $value;
	}//end stored()

	/**
	 * The administrator's value as the menu settings read it while `menu.topBarTitles` holds none:
	 * the older option, or nothing.
	 *
	 * @return array<string, bool> `['topBarTitles' => bool]`, or empty.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-show-titles-in-the-top-bar-is-a-theming-choice-of-the-administrator-req-tqmt-005
	 */
	public function legacyDefault(): array {
		$legacy = $this->legacy();
		if ($legacy === null || $this->stored() !== null) {
			return [];
		}

		return [self::KEY => $legacy];
	}//end legacyDefault()

	/**
	 * Remove the older option once the administrator saved `topBarTitles`, so it never stands in again.
	 *
	 * @param array<mixed> $values The values the administrator saved.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-show-titles-in-the-top-bar-is-a-theming-choice-of-the-administrator-req-tqmt-005
	 */
	public function forgetLegacyOnceChosen(array $values): void {
		if (array_key_exists(self::KEY, $values) === true) {
			$this->config->deleteAppValue(Application::APP_ID, self::LEGACY_KEY);
		}
	}//end forgetLegacyOnceChosen()

	/**
	 * The older app value, or null when it holds none.
	 *
	 * @return bool|null True for '1', false for '0'.
	 *
	 * @spec openspec/specs/menu-labels/spec.md
	 */
	public function legacy(): ?bool {
		return match ((string)$this->config->getAppValue(Application::APP_ID, self::LEGACY_KEY, '')) {
			'1' => true,
			'0' => false,
			default => null,
		};
	}//end legacy()

	/**
	 * Whether the page draws the workplace apps menu: the workplace bar's sheet is on it and the
	 * administrator did not choose the classic style. Nextcloud's own menu labels never load then.
	 *
	 * @param array<int, string> $sheets The page's layout stylesheets.
	 *
	 * @return bool True with the workplace apps menu.
	 *
	 * @spec openspec/specs/menu-labels/spec.md
	 */
	public function workplaceMenu(array $sheets): bool {
		if (in_array(self::HEADER_WORKPLACE_STYLESHEET, $sheets, true) === false) {
			return false;
		}

		$style = json_decode((string)$this->config->getAppValue(Application::APP_ID, 'menu.style', ''), true);

		return $style !== 'classic';
	}//end workplaceMenu()
}//end class
