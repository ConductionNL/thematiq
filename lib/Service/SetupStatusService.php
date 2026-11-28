<?php

/**
 * Thematiq Setup Status Service.
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
 * @spec openspec/specs/first-time-setup/spec.md#requirement-setup-status-is-derived-from-the-saved-configuration-req-tqfs-002
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\IConfig;

/**
 * Whether the first-time setup is due, and the document `GET /api/setup/status` answers.
 *
 * Completion is derived from the saved configuration: an instance an earlier version configured
 * counts as set up without a migration. The answer is cached for the request.
 *
 * @spec openspec/specs/first-time-setup/spec.md#requirement-setup-status-is-derived-from-the-saved-configuration-req-tqfs-002
 */
class SetupStatusService {

	/**
	 * The app value written when the setup was applied: JSON `{by, at}`.
	 *
	 * @var string
	 */
	public const COMPLETED_KEY = 'setup.completed';

	/**
	 * The app value written when the setup was skipped: JSON `{by, at}`.
	 *
	 * @var string
	 */
	public const SKIPPED_KEY = 'setup.skipped';

	/**
	 * Whether every later change of set also applies to Nextcloud's own theme.
	 *
	 * @var string
	 */
	public const FOLLOWS_KEY = ActiveTokenSetService::FOLLOWS_KEY;

	/**
	 * The app values whose presence means an administrator configured thematiq.
	 *
	 * @var array<int, string>
	 */
	public const CONFIGURED_KEYS = [
		'token_set',
		'header_style',
		'themed_contexts',
		'hide_slogan',
		'login_watermark',
		'brand_stripe_placement',
		self::FOLLOWS_KEY,
	];

	/**
	 * The `config.php` key that manages the house style as code.
	 *
	 * @var string
	 */
	public const CONFIG_SOURCE_SYSTEM_KEY = 'thematiq.config_source';

	/**
	 * The steps of the wizard, in order.
	 *
	 * @var array<int, string>
	 */
	public const STEPS = ['welcome', 'house-style', 'nextcloud-theme', 'login-page', 'apps-menu', 'done'];

	/**
	 * The status of this request, once read.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $cached = null;

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The app and system values, the installed version among them.
	 */
	public function __construct(
		private readonly IConfig $config,
	) {
	}//end __construct()

	/**
	 * Whether the wizard should open: not completed, not skipped and not configured.
	 *
	 * @return bool True while setup is due.
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-setup-status-is-derived-from-the-saved-configuration-req-tqfs-002
	 */
	public function isDue(): bool {
		$status = $this->status();

		return $status['completed'] === false && $status['skipped'] === false && $status['configured'] === false;
	}//end isDue()

	/**
	 * Forget the cached answer, after an action wrote new values.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-setup-status-is-derived-from-the-saved-configuration-req-tqfs-002
	 */
	public function forget(): void {
		$this->cached = null;
	}//end forget()

	/**
	 * The status document: `{version, completed, skipped, configured, steps, current}`.
	 *
	 * `completed` and `skipped` are the stored `{by, at}` records or false.
	 *
	 * @return array<string, mixed> The document.
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-setup-contract-is-for-administrators-only-req-tqfs-005
	 */
	public function status(): array {
		if ($this->cached !== null) {
			return $this->cached;
		}

		$current = $this->current();
		$completed = $this->record(key: self::COMPLETED_KEY);
		$this->cached = [
			'version' => $this->value(key: 'installed_version', default: ''),
			'completed' => $completed ?? false,
			'skipped' => ($this->record(key: self::SKIPPED_KEY) ?? false),
			'configured' => $this->isConfigured(),
			'steps' => $this->steps(current: $current, completed: ($completed !== null)),
			'current' => $current,
		];

		return $this->cached;
	}//end status()

	/**
	 * Whether any configuration value is stored, or the house style is managed as code.
	 *
	 * @return bool True when an administrator (or deployment) configured thematiq.
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-setup-status-is-derived-from-the-saved-configuration-req-tqfs-002
	 */
	public function isConfigured(): bool {
		if (trim((string)$this->config->getSystemValue(self::CONFIG_SOURCE_SYSTEM_KEY, '')) !== '') {
			return true;
		}

		$stored = $this->config->getAppKeys(Application::APP_ID);
		foreach ($stored as $key) {
			if (in_array($key, self::CONFIGURED_KEYS, true) === true || $this->isMenuDefault(key: $key) === true) {
				return true;
			}
		}

		return false;
	}//end isConfigured()

	/**
	 * Whether a stored key is a menu default an administrator chose.
	 *
	 * `menu.externalAppsMigrated` and an empty `menu.externalApps` are written by the legacy
	 * external-apps migration on the first menu read, not by an administrator, so they do not count.
	 *
	 * @param string $key The app value key.
	 *
	 * @return bool True for a chosen `menu.<key>` default.
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-setup-status-is-derived-from-the-saved-configuration-req-tqfs-002
	 */
	private function isMenuDefault(string $key): bool {
		if (str_starts_with($key, AppMenuSettings::PREFIX) === false) {
			return false;
		}

		$name = substr($key, strlen(AppMenuSettings::PREFIX));
		if (array_key_exists($name, AppMenuSettings::DEFAULTS) === false) {
			return false;
		}

		if ($name === 'externalApps') {
			return $this->value(key: $key, default: '[]') !== '[]';
		}

		return true;
	}//end isMenuDefault()

	/**
	 * The stored choices, which the wizard fills in on Set up again.
	 *
	 * @return array<string, mixed> `{tokenSet, nextcloudThemeFollows, loginStyle, hideSlogan, menu}`.
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-setup-contract-is-for-administrators-only-req-tqfs-005
	 */
	private function current(): array {
		$contexts = json_decode($this->value(key: 'themed_contexts', default: '[]'), true);
		$loginStyle = 'house-style';
		if (is_array($contexts) === true && $contexts !== [] && in_array('login', $contexts, true) === false) {
			$loginStyle = 'nextcloud';
		}

		$style = json_decode($this->value(key: AppMenuSettings::PREFIX . 'style', default: '""'), true);

		return [
			'tokenSet' => $this->value(key: ActiveTokenSetService::CONFIG_KEY, default: ActiveTokenSetService::DEFAULT_SET),
			'nextcloudThemeFollows' => ($this->value(key: self::FOLLOWS_KEY, default: '0') === '1'),
			'loginStyle' => $loginStyle,
			'hideSlogan' => ($this->value(key: 'hide_slogan', default: '0') === '1'),
			'menu' => ['enabled' => ($style !== 'classic')],
		];
	}//end current()

	/**
	 * Each step's `{done, detail}`.
	 *
	 * @param array<string, mixed> $current The stored choices.
	 * @param bool $completed Whether the setup was applied.
	 *
	 * @return array<string, array{done: bool, detail: string}> The steps by id.
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-setup-contract-is-for-administrators-only-req-tqfs-005
	 */
	private function steps(array $current, bool $completed): array {
		$stored = $this->config->getAppKeys(Application::APP_ID);
		$details = [
			'welcome' => '',
			'house-style' => (string)$current['tokenSet'],
			'nextcloud-theme' => ['unchanged', 'follows'][(int)$current['nextcloudThemeFollows']],
			'login-page' => (string)$current['loginStyle'],
			'apps-menu' => ['classic', 'workplace'][(int)$current['menu']['enabled']],
			'done' => '',
		];
		$done = [
			'welcome' => $completed,
			'house-style' => in_array(ActiveTokenSetService::CONFIG_KEY, $stored, true),
			'nextcloud-theme' => in_array(self::FOLLOWS_KEY, $stored, true),
			'login-page' => (in_array('themed_contexts', $stored, true) || in_array('hide_slogan', $stored, true)),
			'apps-menu' => in_array(AppMenuSettings::PREFIX . 'style', $stored, true),
			'done' => $completed,
		];

		$steps = [];
		foreach (self::STEPS as $step) {
			$steps[$step] = ['done' => $done[$step], 'detail' => $details[$step]];
		}

		return $steps;
	}//end steps()

	/**
	 * A stored `{by, at}` record, or null when none is stored.
	 *
	 * @param string $key The app value.
	 *
	 * @return array<string, mixed>|null The record.
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-setup-status-is-derived-from-the-saved-configuration-req-tqfs-002
	 */
	private function record(string $key): ?array {
		$raw = $this->value(key: $key, default: '');
		if ($raw === '') {
			return null;
		}

		$decoded = json_decode($raw, true);
		if (is_array($decoded) === false) {
			return ['by' => '', 'at' => ''];
		}

		return $decoded;
	}//end record()

	/**
	 * One app value of thematiq.
	 *
	 * @param string $key The key.
	 * @param string $default The value when none is stored.
	 *
	 * @return string The value.
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-setup-status-is-derived-from-the-saved-configuration-req-tqfs-002
	 */
	private function value(string $key, string $default): string {
		return $this->config->getAppValue(Application::APP_ID, $key, $default);
	}//end value()
}//end class
