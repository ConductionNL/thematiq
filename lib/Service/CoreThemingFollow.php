<?php

/**
 * Nextcloud's own theme following the token set.
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
 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-nextcloud-theme-step-keeps-nextclouds-theme-in-line-with-the-set-req-tqfs-008
 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-settings-page-switches-nextclouds-theme-in-line-with-the-set-req-tqfs-013
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\IConfig;

/**
 * While `core_theming_follows_set` is `1`, every change of set also reaches Nextcloud's theme through
 * the `--sync-core` path (the settings page, occ, a planned switch); the switch on the settings page and
 * the wizard's Nextcloud theme step store it.
 *
 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-nextcloud-theme-step-keeps-nextclouds-theme-in-line-with-the-set-req-tqfs-008
 */
class CoreThemingFollow {

	/**
	 * The app value: `1` while Nextcloud's theme follows the set.
	 *
	 * @var string
	 */
	public const KEY = 'core_theming_follows_set';

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The app value.
	 * @param ScheduledCoreThemingSync $coreSync Applies a set to Nextcloud's theme.
	 * @param ThemingAuditService $auditService Records the value and each sync.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly ScheduledCoreThemingSync $coreSync,
		private readonly ThemingAuditService $auditService,
	) {
	}//end __construct()

	/**
	 * Whether Nextcloud's theme follows every change of set.
	 *
	 * @return bool True while it follows.
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-nextcloud-theme-step-keeps-nextclouds-theme-in-line-with-the-set-req-tqfs-008
	 */
	public function follows(): bool {
		return $this->config->getAppValue(Application::APP_ID, self::KEY, '0') === '1';
	}//end follows()

	/**
	 * Store whether Nextcloud's theme follows; on applies the active set now (nothing for Nextcloud default).
	 *
	 * @param bool $follows Whether Nextcloud's theme follows.
	 * @param string $activeSet The set active now.
	 *
	 * @return array<int, string> The Nextcloud theming values applied now.
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-settings-page-switches-nextclouds-theme-in-line-with-the-set-req-tqfs-013
	 */
	public function setFollows(bool $follows, string $activeSet): array {
		$old = $this->config->getAppValue(Application::APP_ID, self::KEY, '');
		$new = ['0', '1'][(int)$follows];
		$this->config->setAppValue(Application::APP_ID, self::KEY, $new);
		if ($old !== $new) {
			$this->auditService->log(action: 'toggle_changed', context: ['key' => self::KEY, 'old' => $old, 'new' => $new]);
		}

		if ($follows === false || $activeSet === ActiveTokenSetService::DEFAULT_SET) {
			return [];
		}

		return $this->apply(tokenSet: $activeSet, auditContext: []);
	}//end setFollows()

	/**
	 * After a change of set: apply it to Nextcloud's theme while it follows.
	 *
	 * @param string $tokenSet The set just made active.
	 * @param array<string, mixed> $auditContext The switch's audit context (its actor).
	 *
	 * @return void
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-nextcloud-theme-step-keeps-nextclouds-theme-in-line-with-the-set-req-tqfs-008
	 */
	public function afterSwitch(string $tokenSet, array $auditContext): void {
		if ($this->follows() === true) {
			$this->apply(tokenSet: $tokenSet, auditContext: $auditContext);
		}
	}//end afterSwitch()

	/**
	 * Apply a set to Nextcloud's theme and audit what changed.
	 *
	 * @param string $tokenSet The set.
	 * @param array<string, mixed> $auditContext Extra audit context.
	 *
	 * @return array<int, string> The values applied.
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-nextcloud-theme-step-keeps-nextclouds-theme-in-line-with-the-set-req-tqfs-008
	 */
	public function apply(string $tokenSet, array $auditContext): array {
		$applied = $this->coreSync->sync(tokenSetId: $tokenSet);
		if ($applied !== []) {
			$this->auditService->log(
				action: 'theming_sync_applied',
				context: array_merge(['new' => $tokenSet, 'key' => implode(',', $applied)], $auditContext)
			);
		}

		return $applied;
	}//end apply()
}//end class
