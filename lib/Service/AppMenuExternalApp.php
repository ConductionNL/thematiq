<?php

/**
 * Thematiq apps menu: one external app an administrator or a person adds.
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
 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-external-apps-are-checked-on-the-server
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The rule for an external app of the menu: `{id, title, payoff, url, icon, visible, order}`. The
 * address must be https, the icon one of the library's or an uploaded icon's id. The menu draws it
 * as a link that opens in a new tab.
 *
 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-external-apps-are-checked-on-the-server
 */
final class AppMenuExternalApp {

	/**
	 * The icon library: a name and its path, drawn in the text colour.
	 *
	 * @var array<string, string>
	 */
	public const LIBRARY = [
		'link' => 'M3.9,12C3.9,10.29 5.29,8.9 7,8.9H11V7H7A5,5 0 0,0 2,12A5,5 0 0,0 7,17H11V15.1H7C5.29,15.1 3.9,13.71 '
			. '3.9,12M8,13H16V11H8V13M17,7H13V8.9H17C18.71,8.9 20.1,10.29 20.1,12C20.1,13.71 18.71,15.1 17,15.1H13V'
			. '17H17A5,5 0 0,0 22,12A5,5 0 0,0 17,7Z',
		'globe' => 'M17.9,17.39C17.64,16.59 16.89,16 16,16H15V13A1,1 0 0,0 14,12H8V10H10A1,1 0 0,0 11,9V7H13A2,2 0 0,0 1'
			. '5,5V4.59C17.93,5.77 20,8.64 20,12C20,14.08 19.2,15.97 17.9,17.39M11,19.93C7.05,19.44 4,16.08 4,12C4,'
			. '11.38 4.08,10.78 4.21,10.21L9,15V16A2,2 0 0,0 11,18M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 '
			. '0,0 22,12A10,10 0 0,0 12,2Z',
		'building' => 'M15,11V5L12,2L9,5V7H3V21H21V11H15M7,19H5V17H7V19M7,15H5V13H7V15M7,11H5V9H7V11M13,19H11V17H13V19M13,1'
			. '5H11V13H13V15M13,11H11V9H13V11M13,7H11V5H13V7M19,19H17V17H19V19M19,15H17V13H19V15Z',
		'news' => 'M20,11H4V8H20M20,15H13V13H20M20,19H13V17H20M11,19H4V13H11M20.33,4.67L18.67,3L17,4.67L15.33,3L13.67,4'
			. '.67L12,3L10.33,4.67L8.67,3L7,4.67L5.33,3L3.67,4.67L2,3V19A2,2 0 0,0 4,21H20A2,2 0 0,0 22,19V3L20.33,'
			. '4.67Z',
		'book' => 'M18,22A2,2 0 0,0 20,20V4C20,2.89 19.1,2 18,2H12V9L9.5,7.5L7,9V2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18'
			. 'Z',
		'chart' => 'M22,21H2V3H4V19H6V10H10V19H12V6H16V19H18V14H22V21Z',
		'map' => 'M15,19L9,16.89V5L15,7.11M20.5,3C20.44,3 20.39,3 20.34,3L15,5.1L9,3L3.36,4.9C3.15,4.97 3,5.15 3,5.38V'
			. '20.5A0.5,0.5 0 0,0 3.5,21C3.55,21 3.61,21 3.66,20.97L9,18.9L15,21L20.64,19.1C20.85,19 21,18.85 21,18'
			. '.62V3.5A0.5,0.5 0 0,0 20.5,3Z',
		'people' => 'M16,13C15.71,13 15.38,13 15.03,13.05C16.19,13.89 17,15 17,16.5V19H23V16.5C23,14.17 18.33,13 16,13M8,'
			. '13C5.67,13 1,14.17 1,16.5V19H15V16.5C15,14.17 10.33,13 8,13M8,11A3,3 0 0,0 11,8A3,3 0 0,0 8,5A3,3 0 '
			. '0,0 5,8A3,3 0 0,0 8,11M16,11A3,3 0 0,0 19,8A3,3 0 0,0 16,5A3,3 0 0,0 13,8A3,3 0 0,0 16,11Z',
		'support' => 'M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22A10,10 0 0,1 2,12A10,10 0 0,1 12,2M12,4A8,8 0 0,0 4,12A8,8 '
			. '0 0,0 12,20A8,8 0 0,0 20,12A8,8 0 0,0 12,4M12,6A6,6 0 0,1 18,12A6,6 0 0,1 12,18A6,6 0 0,1 6,12A6,6 0'
			. ' 0,1 12,6M12,8A4,4 0 0,0 8,12A4,4 0 0,0 12,16A4,4 0 0,0 16,12A4,4 0 0,0 12,8Z',
		'euro' => 'M15,18.5A6.5,6.5 0 0,1 9.24,15H15V13H8.58C8.53,12.67 8.5,12.34 8.5,12C8.5,11.66 8.53,11.33 8.58,11H1'
			. '5V9H9.24A6.49,6.49 0 0,1 15,5.5C16.61,5.5 18.09,6.09 19.23,7.07L21,5.3C19.41,3.87 17.3,3 15,3C11.08,'
			. '3 7.76,5.51 6.5,9H3V11H6.06C6,11.33 6,11.66 6,12C6,12.34 6,12.67 6.06,13H3V15H6.5C7.76,18.49 11.08,2'
			. '1 15,21C17.31,21 19.41,20.13 21,18.7L19.22,16.93C18.09,17.91 16.62,18.5 15,18.5Z',
		'scales' => 'M12,3C10.73,3 9.6,3.8 9.18,5H3V7H4.95L2,14C1.53,16 3,17 5.5,17C8,17 9.56,16 9,14L6.05,7H9.17C9.5,7.8'
			. '5 10.15,8.5 11,8.83V20H2V22H22V20H13V8.82C13.85,8.5 14.5,7.85 14.82,7H17.95L15,14C14.53,16 16,17 18.'
			. '5,17C21,17 22.56,16 22,14L19.05,7H21V5H14.83C14.4,3.8 13.27,3 12,3M12,5A1,1 0 0,1 13,6A1,1 0 0,1 12,'
			. '7A1,1 0 0,1 11,6A1,1 0 0,1 12,5M5.5,10.25L7,14H4L5.5,10.25M18.5,10.25L20,14H17L18.5,10.25Z',
		'truck' => 'M18,18.5A1.5,1.5 0 0,1 16.5,17A1.5,1.5 0 0,1 18,15.5A1.5,1.5 0 0,1 19.5,17A1.5,1.5 0 0,1 18,18.5M19.'
			. '5,9.5L21.46,12H17V9.5M6,18.5A1.5,1.5 0 0,1 4.5,17A1.5,1.5 0 0,1 6,15.5A1.5,1.5 0 0,1 7.5,17A1.5,1.5 '
			. '0 0,1 6,18.5M20,8H17V4H3C1.89,4 1,4.89 1,6V17H3A3,3 0 0,0 6,20A3,3 0 0,0 9,17H15A3,3 0 0,0 18,20A3,3'
			. ' 0 0,0 21,17H23V12L20,8Z',
		'key' => 'M7,14A2,2 0 0,1 5,12A2,2 0 0,1 7,10A2,2 0 0,1 9,12A2,2 0 0,1 7,14M12.65,10C11.83,7.67 9.61,6 7,6A6,6'
			. ' 0 0,0 1,12A6,6 0 0,0 7,18C9.61,18 11.83,16.33 12.65,14H17V18H21V14H23V10H12.65Z',
		'shield' => 'M12,1L3,5V11C3,16.55 6.84,21.74 12,23C17.16,21.74 21,16.55 21,11V5L12,1M12,7C13.4,7 14.8,8.1 14.8,9.'
			. '5V11C15.4,11 16,11.6 16,12.3V15.8C16,16.4 15.4,17 14.7,17H9.2C8.6,17 8,16.4 8,15.7V12.2C8,11.6 8.6,1'
			. '1 9.2,11V9.5C9.2,8.1 10.6,7 12,7M12,8.2C11.2,8.2 10.5,8.7 10.5,9.5V11H13.5V9.5C13.5,8.7 12.8,8.2 12,'
			. '8.2Z',
		'tree' => 'M11,21V16.74C10.53,16.91 10.03,17 9.5,17C7,17 5,15 5,12.5C5,11.23 5.5,10.09 6.36,9.27C6.13,8.73 6,8.'
			. '13 6,7.5C6,5 8,3 10.5,3C12.06,3 13.44,3.8 14.25,5C14.33,5 14.41,5 14.5,5A5.5,5.5 0 0,1 20,10.5A5.5,5'
			. '.5 0 0,1 14.5,16C14,16 13.5,15.93 13,15.79V21H11Z',
		'star' => 'M12,17.27L18.18,21L16.54,13.97L22,9.24L14.81,8.62L12,2L9.19,8.62L2,9.24L7.45,13.97L5.82,21L12,17.27Z',
	];

	/**
	 * The icons the AppMenu boards draw for the external apps that moved from the admin page,
	 * filled like the library.
	 *
	 * @var array<string, string>
	 */
	public const BOARD_ICONS = [
		'openproject-work-packages' => 'M19,3H14.82C14.4,1.84 13.3,1 12,1C10.7,1 9.6,1.84 9.18,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 '
			. '0,0 21,19V5A2,2 0 0,0 19,3M12,3A1,1 0 0,1 13,4A1,1 0 0,1 12,5A1,1 0 0,1 11,4A1,1 0 0,1 12,3M7,7H17V5'
			. 'H19V19H5V5H7V7M7.5,13.5L9,12L11,14L15.5,9.5L17,11L11,17L7.5,13.5Z',
		'openproject-gantt' => 'M2,5H10V2H12V22H10V18H6V15H10V13H4V10H10V8H2V5M14,5H17V8H14V5M14,10H19V13H14V10M14,15H22V18H14V15Z',
		'openproject-boards' => 'M16,5V18H21V5M4,18H9V5H4M10,18H15V5H10V18Z',
		'openproject-meetings' => 'M12,5.5A3.5,3.5 0 0,1 15.5,9A3.5,3.5 0 0,1 12,12.5A3.5,3.5 0 0,1 8.5,9A3.5,3.5 0 0,1 12,5.5M5,8C5.56'
			. ',8 6.08,8.15 6.53,8.42C6.38,9.85 6.8,11.27 7.66,12.38C7.16,13.34 6.16,14 5,14A3,3 0 0,1 2,11A3,3 0 0'
			. ',1 5,8M19,8A3,3 0 0,1 22,11A3,3 0 0,1 19,14C17.84,14 16.84,13.34 16.34,12.38C17.2,11.27 17.62,9.85 1'
			. '7.47,8.42C17.92,8.15 18.44,8 19,8M5.5,18.25C5.5,16.18 8.41,14.5 12,14.5C15.59,14.5 18.5,16.18 18.5,1'
			. '8.25V20H5.5V18.25M0,20V18.5C0,17.11 1.89,15.94 4.45,15.6C3.86,16.28 3.5,17.22 3.5,18.25V20H0M24,20H2'
			. '0.5V18.25C20.5,17.22 20.14,16.28 19.55,15.6C22.11,15.94 24,17.11 24,18.5V20Z',
		'app-wiki' => 'M21,5C19.89,4.65 18.67,4.5 17.5,4.5C15.55,4.5 13.45,4.9 12,6C10.55,4.9 8.45,4.5 6.5,4.5C4.55,4.5 2.4'
			. '5,4.9 1,6V20.65C1,20.9 1.25,21.15 1.5,21.15C1.6,21.15 1.65,21.1 1.75,21.1C3.1,20.45 5.05,20 6.5,20C8'
			. '.45,20 10.55,20.4 12,21.5C13.35,20.65 15.8,20 17.5,20C19.15,20 20.85,20.3 22.25,21.05C22.35,21.1 22.'
			. '4,21.1 22.5,21.1C22.75,21.1 23,20.85 23,20.6V6C22.4,5.55 21.75,5.25 21,5M21,18.5C19.9,18.15 18.7,18 '
			. '17.5,18C15.8,18 13.35,18.65 12,19.5V8C13.35,7.15 15.8,6.5 17.5,6.5C18.7,6.5 19.9,6.65 21,7V18.5Z',
	];

	/**
	 * The most external apps in one list.
	 *
	 * @var int
	 */
	public const MAX = 30;

	/**
	 * The menu entry key of an external app.
	 *
	 * @param array<string, mixed> $app A clean app.
	 *
	 * @return string The key.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-external-apps-are-checked-on-the-server
	 */
	public static function key(array $app): string {
		return 'external-' . (string)$app['id'];
	}//end key()

	/**
	 * A list of external apps, each checked; null when one of them is not allowed.
	 *
	 * @param array<mixed> $apps The apps as sent.
	 *
	 * @return array<int, array<string, mixed>>|null The clean apps in their order.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-external-apps-are-checked-on-the-server
	 */
	public static function cleanList(array $apps): ?array {
		if (count($apps) > self::MAX || array_is_list($apps) === false) {
			return null;
		}

		$clean = [];
		$ids = [];
		foreach ($apps as $index => $app) {
			if (is_array($app) === false) {
				return null;
			}

			$one = self::clean(app: $app, index: $index);
			if ($one === null || isset($ids[$one['id']]) === true) {
				return null;
			}

			$ids[$one['id']] = true;
			$clean[] = $one;
		}

		usort($clean, static fn (array $a, array $b): int => ($a['order'] <=> $b['order']));

		return $clean;
	}//end cleanList()

	/**
	 * One external app checked: an id is made when none is given.
	 *
	 * @param array<mixed> $app The app as sent.
	 * @param int $index Its place in the list, the order when none is given.
	 *
	 * @return array<string, mixed>|null The clean app, or null when it is not allowed.
	 *
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity) - one condition per field of the app.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-external-apps-are-checked-on-the-server
	 */
	public static function clean(array $app, int $index): ?array {
		$id = ($app['id'] ?? '');
		if ($id === '') {
			$id = bin2hex(random_bytes(6));
		}

		$title = self::text(value: ($app['title'] ?? null), max: 60);
		$payoff = self::text(value: ($app['payoff'] ?? ''), max: 80);
		$url = self::address(value: ($app['url'] ?? null));
		$icon = self::icon(value: ($app['icon'] ?? null));
		$visible = ($app['visible'] ?? true);
		$order = filter_var(($app['order'] ?? $index), FILTER_VALIDATE_INT);
		if (is_string($id) === false || preg_match('/^[a-z0-9_-]{1,40}$/', $id) !== 1
			|| $title === null || $title === '' || $payoff === null || $url === null || $icon === null
			|| is_bool($visible) === false || is_int($order) === false
		) {
			return null;
		}

		return ['id' => $id, 'title' => $title, 'payoff' => $payoff, 'url' => $url, 'icon' => $icon, 'visible' => $visible, 'order' => $order];
	}//end clean()

	/**
	 * A single line of text without markup, trimmed, at most `max` characters.
	 *
	 * @param mixed $value The text.
	 * @param int $max The most characters.
	 *
	 * @return string|null The text, or null when it is not allowed.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-external-apps-are-checked-on-the-server
	 */
	private static function text(mixed $value, int $max): ?string {
		if (is_string($value) === false) {
			return null;
		}

		$value = trim($value);
		if (mb_strlen($value) > $max || preg_match('/[<>\x00-\x1F\x7F]/u', $value) === 1) {
			return null;
		}

		return $value;
	}//end text()

	/**
	 * An https address with a host, or null. Plain http is allowed only on this machine's loopback
	 * address (localhost, 127.x, ::1), where a development or demo service runs without TLS.
	 *
	 * @param mixed $value The address.
	 *
	 * @return string|null The address.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-external-apps-are-checked-on-the-server
	 */
	private static function address(mixed $value): ?string {
		if (is_string($value) === false || strlen($value) > 2000) {
			return null;
		}

		$value = trim($value);
		$parts = parse_url($value);
		if ($parts === false || ($parts['host'] ?? '') === '' || isset($parts['user']) === true || preg_match('/\s/', $value) === 1) {
			return null;
		}

		$scheme = strtolower($parts['scheme'] ?? '');
		if ($scheme !== 'https' && ($scheme !== 'http' || self::isLoopback(host: $parts['host']) === false)) {
			return null;
		}

		return $value;
	}//end address()

	/**
	 * Whether a host is this machine's loopback address.
	 *
	 * @param string $host The host.
	 *
	 * @return bool True for localhost, 127.x and ::1.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-external-apps-are-checked-on-the-server
	 */
	private static function isLoopback(string $host): bool {
		$host = strtolower(trim($host, '[]'));

		return $host === 'localhost' || $host === '::1' || preg_match('/^127(\.\d{1,3}){3}$/', $host) === 1;
	}//end isLoopback()

	/**
	 * Every icon an external app may name: the library the dialog offers, and the icons the
	 * external apps had on the admin page before they moved into the menu settings.
	 *
	 * @return array<string, string> The path data, by name.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-the-external-apps-of-the-admin-page-move-into-the-menu-settings
	 */
	public static function icons(): array {
		$icons = self::LIBRARY;
		foreach (OpenProjectModules::MODULES as $key => $module) {
			$icons['openproject-' . str_replace('_', '-', $key)] = $module['icon'];
		}

		foreach (ExternalApps::APPS as $key => $app) {
			$icons['app-' . $key] = $app['icon'];
		}

		return array_merge($icons, self::BOARD_ICONS);
	}//end icons()

	/**
	 * The names of the icons drawn as a line instead of filled: the admin page's own icons that
	 * no board redrew.
	 *
	 * @return array<int, string> The names.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-the-external-apps-of-the-admin-page-move-into-the-menu-settings
	 */
	public static function strokeIcons(): array {
		return array_values(array_diff(array_keys(self::icons()), array_keys(self::LIBRARY), array_keys(self::BOARD_ICONS)));
	}//end strokeIcons()

	/**
	 * `{library: <name>}` from the library, or `{upload: <id>}` of an uploaded icon, or null.
	 *
	 * @param mixed $value The icon.
	 *
	 * @return array<string, string>|null The icon.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-external-apps-are-checked-on-the-server
	 */
	private static function icon(mixed $value): ?array {
		if (is_array($value) === false || count($value) !== 1) {
			return null;
		}

		if (is_string($value['library'] ?? null) === true && isset(self::icons()[$value['library']]) === true) {
			return ['library' => $value['library']];
		}

		if (is_string($value['upload'] ?? null) === true && preg_match(AppMenuIcons::ID_PATTERN, $value['upload']) === 1) {
			return ['upload' => $value['upload']];
		}

		return null;
	}//end icon()
}//end class
