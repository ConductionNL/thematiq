<?php

/**
 * Thematiq apps menu: the menu settings, admin defaults with per-user overrides.
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
 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-admin-defaults-with-per-user-overrides
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use InvalidArgumentException;
use OCA\Thematiq\AppInfo\Application;
use OCP\IConfig;

/**
 * How the apps menu looks and what it holds. An administrator sets every key for everyone (app
 * values `menu.<key>`), the Theming keys `titles` and `topBarTitles` included. A person overrides
 * only the personal keys, `order`, `pinned`, `hidden`,
 * their own `externalApps` and the two create switches `showCreateButton` and `showCreateRow`,
 * in user values with the same names; the override wins, and a
 * person's external apps follow the administrator's. Every value is checked before it is stored:
 * one value that is not allowed refuses the whole save.
 *
 * @SuppressWarnings(PHPMD.StaticAccess) - AppMenuOrder::clean() and AppMenuExternalApp hold the shared value rules.
 *
 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-admin-defaults-with-per-user-overrides
 */
class AppMenuSettings {

	/**
	 * The prefix of every app and user config key.
	 *
	 * @var string
	 */
	public const PREFIX = 'menu.';

	/**
	 * The shipped values, used where nobody set one.
	 *
	 * @var array<string, mixed>
	 */
	public const DEFAULTS = [
		'style' => 'workplace',
		'columns' => 4,
		'rows' => 6,
		'rowsAboveFold' => 2,
		'showCreateButton' => true,
		'showCreateRow' => true,
		'showExternalApps' => true,
		'shape' => 'round',
		'tiles' => false,
		'titles' => true,
		'topBarTitles' => false,
		'payoff' => true,
		'iconSize' => 'large',
		'iconColour' => 'brand',
		'order' => [],
		'pinned' => [],
		'hidden' => [],
		'externalApps' => [],
	];

	/**
	 * The keys a person may set for themselves. Every other key is an administrator's.
	 *
	 * @var array<int, string>
	 */
	public const PERSONAL = ['order', 'pinned', 'hidden', 'externalApps', 'showCreateButton', 'showCreateRow'];

	/**
	 * The former single switch of the Create button and the Create row, an administrator's key only.
	 * It moves once into both switches of CREATE_SWITCHES.
	 *
	 * @var string
	 */
	public const FORMER_CREATE_SWITCH = 'showCreateActions';

	/**
	 * The two switches of the quick actions: the Create button in the top bar and the Create row
	 * in the apps menu.
	 *
	 * @var array<int, string>
	 */
	public const CREATE_SWITCHES = ['showCreateButton', 'showCreateRow'];

	/**
	 * The allowed words of the choice settings.
	 *
	 * @var array<string, array<int, string>>
	 */
	public const CHOICES = [
		'style' => ['workplace', 'classic'],
		'shape' => ['round', 'square'],
		'iconSize' => ['small', 'large'],
		'iconColour' => ['brand', 'neutral'],
	];

	/**
	 * The allowed range of the number settings.
	 *
	 * @var array<string, array{0: int, 1: int}>
	 */
	public const RANGES = [
		'columns' => [2, 5],
		'rows' => [1, 12],
		'rowsAboveFold' => [1, 12],
	];

	/**
	 * The switches.
	 *
	 * @var array<int, string>
	 */
	public const SWITCHES = ['showCreateButton', 'showCreateRow', 'showExternalApps', 'tiles', 'titles', 'topBarTitles', 'payoff'];

	/**
	 * The most pinned apps.
	 *
	 * @var int
	 */
	public const MAX_PINNED = 5;

	/**
	 * Constructor.
	 *
	 * @param IConfig $config Reads and writes the values.
	 */
	public function __construct(
		private readonly IConfig $config,
	) {
	}//end __construct()

	/**
	 * The settings a person's menu uses: the shipped values, the administrator's, then the person's
	 * personal keys. `externalApps` holds the administrator's apps, then the person's own, each
	 * marked with `own`.
	 *
	 * @param string|null $userId The person, or null for the defaults alone.
	 *
	 * @return array<string, mixed> Every key of DEFAULTS.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-admin-defaults-with-per-user-overrides
	 */
	public function effective(?string $userId): array {
		$admin = $this->defaults();
		$settings = array_merge(self::DEFAULTS, $admin);
		$external = array_map(static fn (array $app): array => $app + ['own' => false], ($admin['externalApps'] ?? []));
		if ($userId !== null) {
			$own = $this->overrides(userId: $userId);
			foreach ($own['externalApps'] ?? [] as $app) {
				$external[] = $app + ['own' => true];
			}

			unset($own['externalApps']);
			$settings = array_merge($settings, $own);
		}

		$settings['externalApps'] = $external;

		return $settings;
	}//end effective()

	/**
	 * The keys the administrator set, checked.
	 *
	 * @return array<string, mixed> The keys set.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-admin-defaults-with-per-user-overrides
	 * @spec openspec/changes/apps-menu-show-titles/specs/apps-menu-settings/spec.md#requirement-show-titles-in-the-top-bar-is-a-theming-choice-of-the-administrator-req-tqmt-005
	 */
	public function defaults(): array {
		$values = [];
		foreach (array_keys(self::DEFAULTS) as $key) {
			$stored = (string)$this->config->getAppValue(Application::APP_ID, self::PREFIX . $key, '');
			$this->keep(values: $values, key: $key, stored: $stored);
		}

		// Until the repair step moved it, the older menu labels option stands in.
		return $values + (new AppMenuTopBarTitles(config: $this->config))->legacyDefault();
	}//end defaults()

	/**
	 * The personal keys a person set, checked. Before `menu.order` existed the order was kept in
	 * `app_menu_order`; it is read when the new key is absent.
	 *
	 * @param string $userId The person.
	 *
	 * @return array<string, mixed> The keys set.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-admin-defaults-with-per-user-overrides
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-the-create-button-and-the-create-row-switch-on-and-off-independently-req-tqqt-001
	 */
	public function overrides(string $userId): array {
		$values = [];
		foreach (self::PERSONAL as $key) {
			$stored = (string)$this->config->getUserValue($userId, Application::APP_ID, self::PREFIX . $key, '');
			if ($stored === '' && $key === 'order') {
				$stored = (string)$this->config->getUserValue($userId, Application::APP_ID, AppMenuOrder::LEGACY_KEY, '');
			}

			$this->keep(values: $values, key: $key, stored: $stored);
		}

		return $values;
	}//end overrides()

	/**
	 * Store a person's personal keys. A key that is not personal is refused; a key sent as null
	 * goes back to the administrator's value.
	 *
	 * @param string $userId The person.
	 * @param array<mixed> $values The values as sent.
	 * @param array<int, string> $known The keys of the entries the person can see.
	 *
	 * @return array<string, mixed> The values stored.
	 *
	 * @throws InvalidArgumentException Naming the first key or value that is not allowed; nothing is stored then.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-checked-before-they-are-stored
	 */
	public function saveOverrides(string $userId, array $values, array $known): array {
		foreach (array_keys($values) as $key) {
			if (in_array($key, self::PERSONAL, true) === false) {
				throw new InvalidArgumentException((string)$key);
			}
		}

		$clean = self::strict(values: $values, known: $known);
		foreach (array_keys($values) as $key) {
			if (array_key_exists($key, $clean) === true) {
				$this->config->setUserValue($userId, Application::APP_ID, self::PREFIX . $key, (string)json_encode($clean[$key]));
				continue;
			}

			$this->config->deleteUserValue($userId, Application::APP_ID, self::PREFIX . $key);
		}

		return $clean;
	}//end saveOverrides()

	/**
	 * Store the administrator's values for everyone. A key sent as null goes back to the shipped
	 * value. The former `showCreateActions`, as an older setup wizard sends it, is read as both
	 * create switches and never stored.
	 *
	 * @param array<mixed> $values The values as sent.
	 * @param array<int, string> $known The keys of the entries the administrator can see.
	 *
	 * @return array<string, mixed> The values stored.
	 *
	 * @throws InvalidArgumentException Naming the first key or value that is not allowed; nothing is stored then.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-checked-before-they-are-stored
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-the-former-create-switch-moves-into-the-two-new-switches-req-tqqt-002
	 */
	public function saveDefaults(array $values, array $known): array {
		$values = self::withoutFormerCreateSwitch(values: $values);
		$clean = self::strict(values: $values, known: $known);
		foreach (array_keys($values) as $key) {
			if (array_key_exists($key, $clean) === true) {
				$this->config->setAppValue(Application::APP_ID, self::PREFIX . $key, (string)json_encode($clean[$key]));
				continue;
			}

			$this->config->deleteAppValue(Application::APP_ID, self::PREFIX . $key);
		}

		// The administrator chose: the older menu labels option no longer stands in.
		(new AppMenuTopBarTitles(config: $this->config))->forgetLegacyOnceChosen(values: $values);

		return $clean;
	}//end saveDefaults()

	/**
	 * Move the administrator's former single create switch (app value `menu.showCreateActions`)
	 * into `menu.showCreateButton` and `menu.showCreateRow` once. Runs where the external apps
	 * move runs: on the first page that draws the menu and when Menu settings opens.
	 *
	 * @return bool Whether a stored value was moved.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-the-former-create-switch-moves-into-the-two-new-switches-req-tqqt-002
	 */
	public function moveFormerCreateSwitch(): bool {
		return (new AppMenuFormerCreateSwitch(config: $this->config))->move();
	}//end moveFormerCreateSwitch()

	/**
	 * Values as sent with the former `showCreateActions` read as both create switches.
	 *
	 * @param array<mixed> $values The values as sent.
	 *
	 * @return array<mixed> The values without the former key.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-the-former-create-switch-moves-into-the-two-new-switches-req-tqqt-002
	 */
	public static function withoutFormerCreateSwitch(array $values): array {
		return AppMenuFormerCreateSwitch::strip(values: $values);
	}//end withoutFormerCreateSwitch()

	/**
	 * Remove a person's personal keys, so the administrator's values apply again.
	 *
	 * @param string $userId The person.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-reset-to-default
	 */
	public function resetOverrides(string $userId): void {
		foreach (self::PERSONAL as $key) {
			$this->config->deleteUserValue($userId, Application::APP_ID, self::PREFIX . $key);
		}

		$this->config->deleteUserValue($userId, Application::APP_ID, AppMenuOrder::LEGACY_KEY);
	}//end resetOverrides()

	/**
	 * Remove the administrator's values, so the shipped values apply again.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-reset-to-default
	 */
	public function resetDefaults(): void {
		foreach (array_keys(self::DEFAULTS) as $key) {
			$this->config->deleteAppValue(Application::APP_ID, self::PREFIX . $key);
		}

		$this->config->deleteAppValue(Application::APP_ID, self::PREFIX . self::FORMER_CREATE_SWITCH);
		$this->config->deleteAppValue(Application::APP_ID, AppMenuTopBarTitles::LEGACY_KEY);
	}//end resetDefaults()

	/**
	 * Check values as sent: an unknown key, or a value outside its rule, refuses them all. Null
	 * leaves a key out.
	 *
	 * @param array<mixed> $values The values as sent.
	 * @param array<int, string>|null $known The entry keys `order`, `pinned` and `hidden` may name, or null for any well-formed key.
	 *
	 * @return array<string, mixed> The clean values.
	 *
	 * @throws InvalidArgumentException Naming the first key that is not allowed.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-checked-before-they-are-stored
	 */
	public static function strict(array $values, ?array $known): array {
		$clean = [];
		foreach ($values as $key => $value) {
			if (is_string($key) === false || array_key_exists($key, self::DEFAULTS) === false) {
				throw new InvalidArgumentException((string)$key);
			}

			if ($value === null) {
				continue;
			}

			$checked = self::check(key: $key, value: $value, known: $known);
			if ($checked === null) {
				throw new InvalidArgumentException($key);
			}

			$clean[$key] = $checked;
		}

		return $clean;
	}//end strict()

	/**
	 * Add a stored JSON value to a list when it still passes its rule.
	 *
	 * @param array<string, mixed> $values The values so far.
	 * @param string $key The setting.
	 * @param string $stored The JSON, or empty.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-admin-defaults-with-per-user-overrides
	 */
	private function keep(array &$values, string $key, string $stored): void {
		if ($stored === '') {
			return;
		}

		$checked = self::check(key: $key, value: json_decode($stored, true), known: null);
		if ($checked !== null) {
			$values[$key] = $checked;
		}
	}//end keep()

	/**
	 * One value checked against its key's rule.
	 *
	 * @param string $key The setting.
	 * @param mixed $value The value.
	 * @param array<int, string>|null $known The entry keys a list may name, or null for any well-formed key.
	 *
	 * @return mixed The clean value, or null when it is not allowed.
	 *
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity) - one branch per kind of setting.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-checked-before-they-are-stored
	 */
	private static function check(string $key, mixed $value, ?array $known): mixed {
		if (isset(self::CHOICES[$key]) === true) {
			if (is_string($value) === true && in_array($value, self::CHOICES[$key], true) === true) {
				return $value;
			}

			return null;
		}

		if (isset(self::RANGES[$key]) === true) {
			return self::number(value: $value, range: self::RANGES[$key]);
		}

		if (in_array($key, self::SWITCHES, true) === true) {
			return match ($value) {
				true, 'true', '1', 1 => true,
				false, 'false', '0', 0 => false,
				default => null,
			};
		}

		if (is_array($value) === false) {
			return null;
		}

		if ($key === 'externalApps') {
			return AppMenuExternalApp::cleanList(apps: $value);
		}

		$keys = array_values(
			array_filter(
				AppMenuOrder::clean(keys: $value),
				static fn (string $entry): bool => $known === null || in_array($entry, $known, true) === true
			)
		);
		if ($key === 'pinned') {
			return array_slice($keys, 0, self::MAX_PINNED);
		}

		return $keys;
	}//end check()

	/**
	 * A whole number inside a range, or null.
	 *
	 * @param mixed $value The value.
	 * @param array{0: int, 1: int} $range The lowest and highest allowed.
	 *
	 * @return int|null The number.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-checked-before-they-are-stored
	 */
	private static function number(mixed $value, array $range): ?int {
		$number = filter_var($value, FILTER_VALIDATE_INT);
		if (is_int($number) === true && $number >= $range[0] && $number <= $range[1]) {
			return $number;
		}

		return null;
	}//end number()
}//end class
