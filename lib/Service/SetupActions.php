<?php

/**
 * Thematiq Setup Actions.
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
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-nothing-changes-until-apply-req-tqfs-003
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use InvalidArgumentException;
use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Kernel\Contracts\ThemingAuditServiceInterface;
use OCA\Thematiq\Kernel\Contracts\TokenSetLicenceServiceInterface;
use OCA\Thematiq\Service\Exception\RestrictedSetNotConfirmedException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use Throwable;

/**
 * The two actions of the setup contract: `apply` writes every choice of the wizard at once, `skip`
 * ends the setup and changes nothing else.
 *
 * `apply` checks the whole request before it writes anything, then writes in order the licence
 * confirmation, the token set, Nextcloud's theme, the login page and the apps menu, each through the
 * same path the settings page uses, and stores `setup.completed` only once every part was applied.
 *
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-nothing-changes-until-apply-req-tqfs-003
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) One write per part of the wizard, each through its own service.
 */
class SetupActions {

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
	public const MENU_KEYS = ['columns', 'rowsAboveFold', 'payoff', 'showCreateActions', 'showExternalApps'];

	/**
	 * The render contexts thematiq themes, as `themed_contexts` names them.
	 *
	 * @var array<int, string>
	 */
	private const CONTEXTS = ['user', 'login', 'guest', 'public', 'error'];

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The app values.
	 * @param TokenSetService $tokenSets Which sets exist, and the licence gate.
	 * @param ActiveTokenSetService $active The one write path for the active set.
	 * @param TokenSetLicenceServiceInterface $licences Stores a licence confirmation.
	 * @param ScheduledCoreThemingSync $coreSync Applies a set to Nextcloud's theme (`--sync-core`).
	 * @param LayoutOptionsService $layout The top bar style.
	 * @param AppMenuSettings $menu The administrator's menu defaults.
	 * @param ThemingAuditServiceInterface $audit The theming audit trail.
	 * @param ITimeFactory $time The clock.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly TokenSetService $tokenSets,
		private readonly ActiveTokenSetService $active,
		private readonly TokenSetLicenceServiceInterface $licences,
		private readonly ScheduledCoreThemingSync $coreSync,
		private readonly LayoutOptionsService $layout,
		private readonly AppMenuSettings $menu,
		private readonly ThemingAuditServiceInterface $audit,
		private readonly ITimeFactory $time,
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
			'loginStyle' => ($request['loginStyle'] ?? 'house-style'),
			'hideSlogan' => self::checkBool(key: 'hideSlogan', value: ($request['hideSlogan'] ?? false)),
			'menu' => $this->checkMenu(value: ($request['menu'] ?? ['enabled' => false])),
		];
		if (in_array($checked['loginStyle'], self::LOGIN_STYLES, true) === false) {
			throw new InvalidArgumentException('loginStyle');
		}

		$gate = $this->tokenSets->licenceGate();
		if ($checked['confirmRestricted'] === false) {
			$gate->assertMayApply(tokenSetId: $checked['tokenSet'], alreadyApplied: $this->active->getActive());
		}

		if ($checked['confirmRestricted'] === true && $gate->isRestricted(tokenSetId: $checked['tokenSet']) === false) {
			throw new InvalidArgumentException('confirmRestricted');
		}

		return $checked;
	}//end check()

	/**
	 * Check the request, then write every part, in order.
	 *
	 * @param array<mixed> $request The request body.
	 * @param string $uid The administrator.
	 *
	 * @return array{applied: array<int, string>, failed: array<int, string>, completed: bool} The parts.
	 *
	 * @throws InvalidArgumentException Naming the first key that is unknown or wrong; nothing is written then.
	 * @throws RestrictedSetNotConfirmedException When a restricted set is not confirmed; nothing is written then.
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-nothing-changes-until-apply-req-tqfs-003
	 */
	public function apply(array $request, string $uid): array {
		$choices = $this->check(request: $request);
		$parts = [
			'licence' => fn () => $this->confirmLicence(choices: $choices, uid: $uid),
			'house-style' => fn () => $this->switchSet(tokenSet: $choices['tokenSet']),
			'nextcloud-theme' => fn () => $this->nextcloudTheme(tokenSet: $choices['tokenSet'], follows: $choices['nextcloudThemeFollows']),
			'login-page' => fn () => $this->loginPage(style: $choices['loginStyle'], hideSlogan: $choices['hideSlogan']),
			'apps-menu' => fn () => $this->appsMenu(menu: $choices['menu'], tokenSet: $choices['tokenSet']),
		];

		$applied = [];
		$failed = [];
		foreach ($parts as $part => $write) {
			try {
				if ($write() === true) {
					$applied[] = $part;
				}
			} catch (Throwable $e) {
				$failed[] = $part;
			}
		}

		if ($failed === []) {
			$this->store(key: SetupStatusService::COMPLETED_KEY, uid: $uid);
			$this->audit->log(action: 'setup_completed', context: ['new' => $choices['tokenSet']]);
		}

		return ['applied' => $applied, 'failed' => $failed, 'completed' => ($failed === [])];
	}//end apply()

	/**
	 * End the setup without changing anything else.
	 *
	 * @param string $uid The administrator.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-later-closes-the-wizard-for-this-page-skip-ends-it-req-tqfs-004
	 */
	public function skip(string $uid): void {
		$this->store(key: SetupStatusService::SKIPPED_KEY, uid: $uid);
		$this->audit->log(action: 'setup_skipped', context: []);
	}//end skip()

	/**
	 * Store the licence confirmation when the wizard asked for it.
	 *
	 * @param array<string, mixed> $choices The checked request.
	 * @param string $uid The administrator.
	 *
	 * @return bool True when a confirmation was stored.
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-house-style-step-chooses-a-token-set-and-its-licence-confirmation-req-tqfs-007
	 */
	private function confirmLicence(array $choices, string $uid): bool {
		if ($choices['confirmRestricted'] !== true) {
			return false;
		}

		$this->licences->confirm(tokenSetId: (string)$choices['tokenSet'], uid: $uid);

		return true;
	}//end confirmLicence()

	/**
	 * Make the chosen set the active one, through the settings page's path.
	 *
	 * @param string $tokenSet The set.
	 *
	 * @return bool Always true: the part is applied, also when the set was already active.
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-house-style-step-chooses-a-token-set-and-its-licence-confirmation-req-tqfs-007
	 */
	private function switchSet(string $tokenSet): bool {
		$this->active->switchTo(tokenSet: $tokenSet, auditContext: ['via' => 'setup']);

		return true;
	}//end switchSet()

	/**
	 * Store whether Nextcloud's theme follows the set, and apply the set to it when it does.
	 *
	 * @param string $tokenSet The set.
	 * @param bool $follows Whether Nextcloud's theme follows.
	 *
	 * @return bool Always true.
	 *
	 * @throws \RuntimeException When the set's theming values did not validate, so nothing was applied.
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-nextcloud-theme-step-keeps-nextclouds-theme-in-line-with-the-set-req-tqfs-008
	 */
	private function nextcloudTheme(string $tokenSet, bool $follows): bool {
		$old = $this->config->getAppValue(Application::APP_ID, SetupStatusService::FOLLOWS_KEY, '');
		$new = $follows === true ? '1' : '0';
		$this->config->setAppValue(Application::APP_ID, SetupStatusService::FOLLOWS_KEY, $new);
		if ($old !== $new) {
			$this->audit->log(action: 'toggle_changed', context: ['key' => SetupStatusService::FOLLOWS_KEY, 'old' => $old, 'new' => $new]);
		}

		if ($follows === false || $tokenSet === ActiveTokenSetService::DEFAULT_SET) {
			return true;
		}

		$applied = $this->coreSync->sync(tokenSetId: $tokenSet);
		if ($applied === [] && $this->coreSync->themingParams(tokenSetId: $tokenSet) !== []) {
			throw new \RuntimeException('The theming values of ' . $tokenSet . ' did not validate');
		}

		if ($applied !== []) {
			$this->audit->log(action: 'theming_sync_applied', context: ['new' => $tokenSet, 'key' => implode(',', $applied)]);
		}

		return true;
	}//end nextcloudTheme()

	/**
	 * Theme the login page or leave it to Nextcloud, and show or hide the slogan.
	 *
	 * An empty `themed_contexts` themes every context, so following the house style keeps an empty
	 * list as it is; Nextcloud default stores every context but `login`.
	 *
	 * @param string $style `house-style` or `nextcloud`.
	 * @param bool $hideSlogan Whether to hide the Nextcloud slogan.
	 *
	 * @return bool Always true.
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-login-page-step-sets-the-login-page-style-req-tqfs-009
	 */
	private function loginPage(string $style, bool $hideSlogan): bool {
		$oldContexts = $this->config->getAppValue(Application::APP_ID, 'themed_contexts', '');
		$contexts = json_decode($oldContexts === '' ? '[]' : $oldContexts, true);
		$contexts = is_array($contexts) === true ? array_values(array_intersect($contexts, self::CONTEXTS)) : [];
		if ($style === 'nextcloud') {
			$contexts = array_values(array_diff(($contexts === [] ? self::CONTEXTS : $contexts), ['login']));
		}

		if ($style === 'house-style' && $contexts !== [] && in_array('login', $contexts, true) === false) {
			$contexts[] = 'login';
		}

		$newContexts = (string)json_encode($contexts);
		$this->config->setAppValue(Application::APP_ID, 'themed_contexts', $newContexts);
		if ($oldContexts !== $newContexts) {
			$this->audit->log(action: 'toggle_changed', context: ['key' => 'themed_contexts', 'old' => $oldContexts, 'new' => $newContexts]);
		}

		$oldSlogan = $this->config->getAppValue(Application::APP_ID, 'hide_slogan', '0');
		$newSlogan = $hideSlogan === true ? '1' : '0';
		$this->config->setAppValue(Application::APP_ID, 'hide_slogan', $newSlogan);
		if ($oldSlogan !== $newSlogan) {
			$this->audit->log(action: 'toggle_changed', context: ['key' => 'hide_slogan', 'old' => $oldSlogan, 'new' => $newSlogan]);
		}

		return true;
	}//end loginPage()

	/**
	 * Store the menu defaults: workplace with its defaults when on, classic alone when off; on also
	 * brings the workplace top bar when the set does not draw it.
	 *
	 * @param array<string, mixed> $menu The checked menu choice.
	 * @param string $tokenSet The chosen set.
	 *
	 * @return bool Always true.
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-apps-menu-step-is-one-switch-req-tqfs-010
	 */
	private function appsMenu(array $menu, string $tokenSet): bool {
		$values = ['style' => 'classic'];
		if ($menu['enabled'] === true) {
			$values = ['style' => 'workplace'] + array_intersect_key($menu, array_flip(self::MENU_KEYS));
		}

		$this->menu->saveDefaults(values: $values, known: []);
		$this->audit->log(action: 'toggle_changed', context: ['key' => 'menu.defaults', 'new' => (string)json_encode($values)]);

		$headerKey = LayoutOptionValues::HEADER_STYLE_KEY;
		if ($menu['enabled'] === true
			&& $this->layout->resolve(key: $headerKey, tokenSet: $tokenSet) !== LayoutOptionValues::HEADER_STYLE_WORKPLACE
		) {
			$old = $this->layout->setOption(key: $headerKey, value: LayoutOptionValues::HEADER_STYLE_WORKPLACE);
			$this->audit->log(action: 'toggle_changed', context: ['key' => $headerKey, 'old' => $old, 'new' => LayoutOptionValues::HEADER_STYLE_WORKPLACE]);
		}

		return true;
	}//end appsMenu()

	/**
	 * Store a `{by, at}` record.
	 *
	 * @param string $key The app value.
	 * @param string $uid The administrator.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-setup-status-is-derived-from-the-saved-configuration-req-tqfs-002
	 */
	private function store(string $key, string $uid): void {
		$record = ['by' => $uid, 'at' => gmdate('Y-m-d\TH:i:s\Z', $this->time->getTime())];
		$this->config->setAppValue(Application::APP_ID, $key, (string)json_encode($record));
	}//end store()

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
	 */
	private function checkMenu(mixed $value): array {
		if (is_array($value) === false) {
			throw new InvalidArgumentException('menu');
		}

		$checked = ['enabled' => self::checkBool(key: 'menu.enabled', value: ($value['enabled'] ?? true))];
		foreach ($value as $key => $item) {
			if ($key === 'enabled') {
				continue;
			}

			if (in_array($key, self::MENU_KEYS, true) === false) {
				throw new InvalidArgumentException('menu.' . (string)$key);
			}

			try {
				$checked += AppMenuSettings::strict(values: [$key => $item], known: null);
			} catch (InvalidArgumentException $e) {
				throw new InvalidArgumentException('menu.' . $key);
			}
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
}//end class
