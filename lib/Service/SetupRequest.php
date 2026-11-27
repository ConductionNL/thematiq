<?php

/**
 * Thematiq Setup Request.
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
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-setup-contract-is-for-administrators-only-req-tqfs-005
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use InvalidArgumentException;
use OCA\Thematiq\Service\Exception\RestrictedSetNotConfirmedException;

/**
 * Checks a whole `apply` request of the first-time setup before anything is written: only known keys,
 * a known set, booleans, a login style, the menu values as `POST /app-menu/defaults` checks them, and
 * the licence gate of the settings page.
 *
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-setup-contract-is-for-administrators-only-req-tqfs-005
 */
class SetupRequest {

	/**
	 * The keys `apply` takes.
	 *
	 * @var array<int, string>
	 */
	public const APPLY_KEYS = ['tokenSet', 'confirmRestricted', 'nextcloudThemeFollows', 'loginStyle', 'hideSlogan', 'menu'];

	/**
	 * The login page styles.
	 *
	 * @var array<int, string>
	 */
	public const LOGIN_STYLES = ['house-style', 'nextcloud'];

	/**
	 * The menu keys the wizard sets besides `enabled`.
	 *
	 * @var array<int, string>
	 */
	public const MENU_KEYS = ['columns', 'rowsAboveFold', 'payoff', 'showCreateButton', 'showCreateRow', 'showExternalApps'];

	/**
	 * Constructor.
	 *
	 * @param TokenSetService $tokenSets Which sets exist, and the licence gate.
	 * @param ActiveTokenSetService $active The set applied now.
	 */
	public function __construct(
		private readonly TokenSetService $tokenSets,
		private readonly ActiveTokenSetService $active,
	) {
	}//end __construct()

	/**
	 * Check a whole `apply` request; nothing is written.
	 *
	 * @param array<mixed> $request The request body.
	 *
	 * @return array<string, mixed> The request with defaults filled in.
	 *
	 * @throws InvalidArgumentException Naming the first key that is unknown or wrong.
	 * @throws RestrictedSetNotConfirmedException When a restricted set is neither confirmed nor confirmed now.
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-setup-contract-is-for-administrators-only-req-tqfs-005
	 */
	public function check(array $request): array {
		foreach (array_keys($request) as $key) {
			if (in_array($key, self::APPLY_KEYS, true) === false) {
				throw new InvalidArgumentException((string)$key);
			}
		}

		$checked = [
			'tokenSet' => $this->checkTokenSet(value: ($request['tokenSet'] ?? null)),
			'confirmRestricted' => self::checkBool(key: 'confirmRestricted', value: ($request['confirmRestricted'] ?? false)),
			'nextcloudThemeFollows' => self::checkBool(key: 'nextcloudThemeFollows', value: ($request['nextcloudThemeFollows'] ?? false)),
			'loginStyle' => self::checkLoginStyle(value: ($request['loginStyle'] ?? 'house-style')),
			'hideSlogan' => self::checkBool(key: 'hideSlogan', value: ($request['hideSlogan'] ?? false)),
			'menu' => $this->checkMenu(value: ($request['menu'] ?? ['enabled' => false])),
		];
		$this->checkLicence(tokenSet: $checked['tokenSet'], confirm: $checked['confirmRestricted']);

		return $checked;
	}//end check()

	/**
	 * The licence gate of the settings page: an unconfirmed restricted set needs the confirmation, and
	 * a confirmation is only for a restricted set.
	 *
	 * @param string $tokenSet The set.
	 * @param bool $confirm Whether the request confirms the licence.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException Naming `confirmRestricted` for a set that is not restricted.
	 * @throws RestrictedSetNotConfirmedException When a restricted set is not confirmed.
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-house-style-step-chooses-a-token-set-and-its-licence-confirmation-req-tqfs-007
	 */
	private function checkLicence(string $tokenSet, bool $confirm): void {
		$gate = $this->tokenSets->licenceGate();
		if ($confirm === false) {
			$gate->assertMayApply(tokenSetId: $tokenSet, alreadyApplied: $this->active->getActive());
			return;
		}

		if ($gate->isRestricted(tokenSetId: $tokenSet) === false) {
			throw new InvalidArgumentException('confirmRestricted');
		}
	}//end checkLicence()

	/**
	 * Check the login page style.
	 *
	 * @param mixed $value The value sent.
	 *
	 * @return string `house-style` or `nextcloud`.
	 *
	 * @throws InvalidArgumentException Naming `loginStyle`.
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-login-page-step-sets-the-login-page-style-req-tqfs-009
	 */
	private static function checkLoginStyle(mixed $value): string {
		if (in_array($value, self::LOGIN_STYLES, true) === false) {
			throw new InvalidArgumentException('loginStyle');
		}

		return (string)$value;
	}//end checkLoginStyle()

	/**
	 * Check the token set: a known set id.
	 *
	 * @param mixed $value The value sent.
	 *
	 * @return string The set id.
	 *
	 * @throws InvalidArgumentException Naming `tokenSet`.
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-setup-contract-is-for-administrators-only-req-tqfs-005
	 */
	private function checkTokenSet(mixed $value): string {
		if (is_string($value) === false || $this->tokenSets->isValidTokenSet(tokenSetId: $value) === false) {
			throw new InvalidArgumentException('tokenSet');
		}

		return $value;
	}//end checkTokenSet()

	/**
	 * Check the menu choice: `enabled` and, when on, the workplace defaults, as `POST /app-menu/defaults` checks them.
	 *
	 * @param mixed $value The value sent.
	 *
	 * @return array<string, mixed> The checked choice.
	 *
	 * @throws InvalidArgumentException Naming `menu` or `menu.<key>`.
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-apps-menu-step-is-one-switch-req-tqfs-010
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-the-former-create-switch-moves-into-the-two-new-switches-req-tqqt-002
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) AppMenuSettings holds the one rule for the former create switch.
	 */
	private function checkMenu(mixed $value): array {
		if (is_array($value) === false) {
			throw new InvalidArgumentException('menu');
		}

		$checked = ['enabled' => self::checkBool(key: 'menu.enabled', value: ($value['enabled'] ?? true))];
		unset($value['enabled']);
		// A wizard built before the two create switches still sends the former one.
		foreach (AppMenuSettings::withoutFormerCreateSwitch(values: $value) as $key => $item) {
			$checked += $this->menuValue(key: (string)$key, value: $item);
		}

		return $checked;
	}//end checkMenu()

	/**
	 * Check a boolean value.
	 *
	 * @param string $key The key, for the refusal.
	 * @param mixed $value The value sent.
	 *
	 * @return bool The value.
	 *
	 * @throws InvalidArgumentException Naming the key.
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-setup-contract-is-for-administrators-only-req-tqfs-005
	 */
	private static function checkBool(string $key, mixed $value): bool {
		if (is_bool($value) === false) {
			throw new InvalidArgumentException($key);
		}

		return $value;
	}//end checkBool()

	/**
	 * One menu value, checked as `POST /app-menu/defaults` checks it.
	 *
	 * @param string $key The menu key.
	 * @param mixed $value The value sent.
	 *
	 * @return array<string, mixed> The checked value under its key.
	 *
	 * @throws InvalidArgumentException Naming `menu.<key>` when the key or the value is not allowed.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) AppMenuSettings::strict() is the one rule set of the menu defaults.
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-apps-menu-step-is-one-switch-req-tqfs-010
	 */
	private function menuValue(string $key, mixed $value): array {
		if (in_array($key, self::MENU_KEYS, true) === false) {
			throw new InvalidArgumentException('menu.' . $key);
		}

		try {
			return AppMenuSettings::strict(values: [$key => $value], known: null);
		} catch (InvalidArgumentException $e) {
			throw new InvalidArgumentException('menu.' . $key);
		}
	}//end menuValue()
}//end class
