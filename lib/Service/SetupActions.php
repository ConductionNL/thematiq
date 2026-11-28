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
 * @spec openspec/specs/first-time-setup/spec.md#requirement-nothing-changes-until-apply-req-tqfs-003
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use RuntimeException;
use Throwable;

/**
 * The two actions of the setup contract: `apply` writes every choice of the wizard at once, `skip`
 * ends the setup and changes nothing else.
 *
 * `apply` checks the whole request before it writes anything, then writes in order the licence
 * confirmation, the token set, Nextcloud's theme, the login page and the apps menu, each through the
 * same path the settings page uses, and stores `setup.completed` only once every part was applied.
 *
 * @spec openspec/specs/first-time-setup/spec.md#requirement-nothing-changes-until-apply-req-tqfs-003
 */
class SetupActions {

	/**
	 * The render contexts thematiq themes, as `themed_contexts` names them.
	 *
	 * @var array<int, string>
	 */
	private const CONTEXTS = ['user', 'login', 'guest', 'public', 'error'];

	/**
	 * Constructor.
	 *
	 * @param SetupRecords $records The app values, the audit trail and the clock.
	 * @param SetupRequest $request Checks a whole request before anything is written.
	 * @param SetupWriters $writers The write paths shared with the settings page.
	 * @param AppMenuSettings $menu The administrator's menu defaults.
	 */
	public function __construct(
		private readonly SetupRecords $records,
		private readonly SetupRequest $request,
		private readonly SetupWriters $writers,
		private readonly AppMenuSettings $menu,
	) {
	}//end __construct()

	/**
	 * Check the request, then write every part, in order.
	 *
	 * @param array<mixed> $request The request body.
	 * @param string $uid The administrator.
	 *
	 * @return array{applied: array<int, string>, failed: array<int, string>, completed: bool} The parts.
	 *
	 * @throws \InvalidArgumentException Naming the first key that is unknown or wrong; nothing is written then.
	 * @throws Exception\RestrictedSetNotConfirmedException When a restricted set is not confirmed; nothing is written then.
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-nothing-changes-until-apply-req-tqfs-003
	 */
	public function apply(array $request, string $uid): array {
		$choices = $this->request->check(request: $request);
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
			$this->records->audit->log(action: 'setup_completed', context: ['new' => $choices['tokenSet']]);
		}

		return ['applied' => $applied, 'failed' => $failed, 'completed' => ($failed === [])];
	}//end apply()

	/**
	 * Check a whole `apply` request; nothing is written.
	 *
	 * @param array<mixed> $request The request body.
	 *
	 * @return array<string, mixed> The checked request.
	 *
	 * @throws \InvalidArgumentException Naming the first key that is unknown or wrong.
	 * @throws Exception\RestrictedSetNotConfirmedException When a restricted set is not confirmed.
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-setup-contract-is-for-administrators-only-req-tqfs-005
	 */
	public function check(array $request): array {
		return $this->request->check(request: $request);
	}//end check()

	/**
	 * End the setup without changing anything else.
	 *
	 * @param string $uid The administrator.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-later-closes-the-wizard-for-this-page-skip-ends-it-req-tqfs-004
	 */
	public function skip(string $uid): void {
		$this->store(key: SetupStatusService::SKIPPED_KEY, uid: $uid);
		$this->records->audit->log(action: 'setup_skipped', context: []);
	}//end skip()

	/**
	 * Store the licence confirmation when the wizard asked for it.
	 *
	 * @param array<string, mixed> $choices The checked request.
	 * @param string $uid The administrator.
	 *
	 * @return bool True when a confirmation was stored.
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-house-style-step-chooses-a-token-set-and-its-licence-confirmation-req-tqfs-007
	 */
	private function confirmLicence(array $choices, string $uid): bool {
		if ($choices['confirmRestricted'] !== true) {
			return false;
		}

		$this->writers->licences->confirm(tokenSetId: (string)$choices['tokenSet'], uid: $uid);

		return true;
	}//end confirmLicence()

	/**
	 * Make the chosen set the active one, through the settings page's path.
	 *
	 * @param string $tokenSet The set.
	 *
	 * @return bool Always true: the part is applied, also when the set was already active.
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-house-style-step-chooses-a-token-set-and-its-licence-confirmation-req-tqfs-007
	 */
	private function switchSet(string $tokenSet): bool {
		$this->writers->active->switchTo(tokenSet: $tokenSet, auditContext: ['via' => 'setup']);

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
	 * @throws RuntimeException When the set's theming values did not validate, so nothing was applied.
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-nextcloud-theme-step-keeps-nextclouds-theme-in-line-with-the-set-req-tqfs-008
	 */
	private function nextcloudTheme(string $tokenSet, bool $follows): bool {
		$this->storeValue(key: SetupStatusService::FOLLOWS_KEY, value: self::flag(value: $follows));

		if ($follows === false || $tokenSet === ActiveTokenSetService::DEFAULT_SET) {
			return true;
		}

		$this->syncCore(tokenSet: $tokenSet);

		return true;
	}//end nextcloudTheme()

	/**
	 * Apply a set to Nextcloud's theme through the `--sync-core` path and audit what changed.
	 *
	 * @param string $tokenSet The set.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When the set's theming values did not validate, so nothing was applied.
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-nextcloud-theme-step-keeps-nextclouds-theme-in-line-with-the-set-req-tqfs-008
	 */
	private function syncCore(string $tokenSet): void {
		$applied = $this->writers->coreSync->sync(tokenSetId: $tokenSet);
		if ($applied !== []) {
			$this->records->audit->log(action: 'theming_sync_applied', context: ['new' => $tokenSet, 'key' => implode(',', $applied)]);
			return;
		}

		if ($this->writers->coreSync->themingParams(tokenSetId: $tokenSet) !== []) {
			throw new RuntimeException('The theming values of ' . $tokenSet . ' did not validate');
		}
	}//end syncCore()

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
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-login-page-step-sets-the-login-page-style-req-tqfs-009
	 */
	private function loginPage(string $style, bool $hideSlogan): bool {
		$this->storeValue(key: 'themed_contexts', value: (string)json_encode($this->loginContexts(style: $style)));
		$this->storeValue(key: 'hide_slogan', value: self::flag(value: $hideSlogan));

		return true;
	}//end loginPage()

	/**
	 * The themed contexts with `login` added or removed.
	 *
	 * @param string $style `house-style` or `nextcloud`.
	 *
	 * @return array<int, string> The contexts to store.
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-login-page-step-sets-the-login-page-style-req-tqfs-009
	 */
	private function loginContexts(string $style): array {
		$contexts = json_decode($this->records->config->getAppValue(Application::APP_ID, 'themed_contexts', '[]'), true);
		if (is_array($contexts) === false) {
			$contexts = [];
		}

		$contexts = array_values(array_intersect($contexts, self::CONTEXTS));
		if ($style === 'nextcloud') {
			return self::withoutLogin(contexts: $contexts);
		}

		if ($contexts !== [] && in_array('login', $contexts, true) === false) {
			$contexts[] = 'login';
		}

		return $contexts;
	}//end loginContexts()

	/**
	 * Every themed context but `login`; an empty list stands for all of them.
	 *
	 * @param array<int, string> $contexts The stored contexts.
	 *
	 * @return array<int, string> The contexts without `login`.
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-login-page-step-sets-the-login-page-style-req-tqfs-009
	 */
	private static function withoutLogin(array $contexts): array {
		if ($contexts === []) {
			$contexts = self::CONTEXTS;
		}

		return array_values(array_diff($contexts, ['login']));
	}//end withoutLogin()

	/**
	 * Store an app value and audit it when it changed.
	 *
	 * @param string $key The app value.
	 * @param string $value The new value.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-setup-contract-is-for-administrators-only-req-tqfs-005
	 */
	private function storeValue(string $key, string $value): void {
		$old = $this->records->config->getAppValue(Application::APP_ID, $key, '');
		$this->records->config->setAppValue(Application::APP_ID, $key, $value);
		if ($old !== $value) {
			$this->records->audit->log(action: 'toggle_changed', context: ['key' => $key, 'old' => $old, 'new' => $value]);
		}
	}//end storeValue()

	/**
	 * A boolean as the app value `1` or `0`.
	 *
	 * @param bool $value The boolean.
	 *
	 * @return string `1` or `0`.
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-setup-contract-is-for-administrators-only-req-tqfs-005
	 */
	private static function flag(bool $value): string {
		if ($value === true) {
			return '1';
		}

		return '0';
	}//end flag()

	/**
	 * Store the menu defaults: workplace with its defaults when on, classic alone when off; on also
	 * brings the workplace top bar (light layout and workplace header) when the set does not draw it.
	 *
	 * @param array<string, mixed> $menu The checked menu choice.
	 * @param string $tokenSet The chosen set.
	 *
	 * @return bool Always true.
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-apps-menu-step-is-one-switch-req-tqfs-010
	 */
	private function appsMenu(array $menu, string $tokenSet): bool {
		$values = ['style' => 'classic'];
		if ($menu['enabled'] === true) {
			$values = ['style' => 'workplace'] + array_intersect_key($menu, array_flip(SetupRequest::MENU_KEYS));
		}

		$this->menu->saveDefaults(values: $values, known: []);
		$this->records->audit->log(action: 'toggle_changed', context: ['key' => 'menu.defaults', 'new' => (string)json_encode($values)]);

		// The workplace top bar is the light layout with the workplace header: on brings both
		// when the set does not draw them, otherwise the menu would not show at all.
		if ($menu['enabled'] === true) {
			foreach (LayoutOptionValues::WORKPLACE_TOP_BAR as $key => $value) {
				$this->bringOption(key: $key, value: $value, tokenSet: $tokenSet);
			}
		}

		return true;
	}//end appsMenu()

	/**
	 * Store a layout option when the set does not resolve to it already, audited.
	 *
	 * @param string $key The layout option's app config key.
	 * @param string $value The value the workplace top bar needs.
	 * @param string $tokenSet The chosen set.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-apps-menu-step-is-one-switch-req-tqfs-010
	 */
	private function bringOption(string $key, string $value, string $tokenSet): void {
		if ($this->writers->layout->resolve(key: $key, tokenSet: $tokenSet) === $value) {
			return;
		}

		$old = $this->writers->layout->setOption(key: $key, value: $value);
		$this->records->audit->log(action: 'toggle_changed', context: ['key' => $key, 'old' => $old, 'new' => $value]);
	}//end bringOption()

	/**
	 * Store a `{by, at}` record.
	 *
	 * @param string $key The app value.
	 * @param string $uid The administrator.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-setup-status-is-derived-from-the-saved-configuration-req-tqfs-002
	 */
	private function store(string $key, string $uid): void {
		$record = ['by' => $uid, 'at' => gmdate('Y-m-d\TH:i:s\Z', $this->records->time->getTime())];
		$this->records->config->setAppValue(Application::APP_ID, $key, (string)json_encode($record));
	}//end store()
}//end class
