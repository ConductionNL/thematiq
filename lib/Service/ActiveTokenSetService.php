<?php

/**
 * Thematiq Active Token Set Service.
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
 * @spec openspec/specs/scheduled-switch/spec.md
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-nextcloud-theme-step-keeps-nextclouds-theme-in-line-with-the-set-req-tqfs-008
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use InvalidArgumentException;
use OCA\Thematiq\AppInfo\Application;
use OCP\IConfig;

/**
 * The one write path for the instance-wide token set. The settings dropdown
 * and the scheduled switch job both go through switchTo(), so both run the
 * same isValidTokenSet() check and both leave an audit entry.
 *
 * @spec openspec/specs/scheduled-switch/spec.md
 */
class ActiveTokenSetService {

	/**
	 * The app config key holding the active token set.
	 *
	 * @var string
	 */
	public const CONFIG_KEY = 'token_set';

	/**
	 * The set that is active when none was chosen.
	 *
	 * @var string
	 */
	public const DEFAULT_SET = 'nextcloud';

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The config service.
	 * @param TokenSetService $tokenSets Validates that a set exists.
	 * @param ThemingAuditService $auditService Records each switch.
	 * @param ScheduledCoreThemingSync|null $coreSync Applies a set to Nextcloud's theme while it follows the set.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly TokenSetService $tokenSets,
		private readonly ThemingAuditService $auditService,
		private readonly ?ScheduledCoreThemingSync $coreSync = null,
	) {
	}//end __construct()

	/**
	 * The active token set id.
	 *
	 * @return string The id.
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md
	 */
	public function getActive(): string {
		return $this->config->getAppValue(Application::APP_ID, self::CONFIG_KEY, self::DEFAULT_SET);
	}//end getActive()

	/**
	 * Make a token set the active one and audit the switch.
	 *
	 * @param string $tokenSet The set to activate.
	 * @param string $auditAction The audit action to record.
	 * @param array<string, mixed> $auditContext Extra audit context (for example `actor` and `switchId`).
	 *
	 * @return string The set that was active before.
	 *
	 * @throws \InvalidArgumentException When the set does not exist, or (as a
	 *                                   RestrictedSetNotConfirmedException) is restricted and not confirmed;
	 *                                   nothing is written then.
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md
	 * @spec openspec/specs/token-set-licences/spec.md#requirement-every-path-that-applies-a-set-asks-the-same-gate
	 */
	public function switchTo(string $tokenSet, string $auditAction = 'token_set_changed', array $auditContext = []): string {
		if ($this->tokenSets->isValidTokenSet(tokenSetId: $tokenSet) === false) {
			throw new InvalidArgumentException('Invalid token set');
		}

		$previous = $this->getActive();
		$this->tokenSets->licenceGate()->assertMayApply(tokenSetId: $tokenSet, alreadyApplied: $previous);
		$this->config->setAppValue(Application::APP_ID, self::CONFIG_KEY, $tokenSet);

		$this->auditService->log(
			action: $auditAction,
			context: array_merge(['old' => $previous, 'new' => $tokenSet], $auditContext)
		);
		$this->followInNextcloud(tokenSet: $tokenSet, auditContext: $auditContext);

		return $previous;
	}//end switchTo()

	/**
	 * Whether Nextcloud's own theme follows every change of set (`core_theming_follows_set` is `1`).
	 *
	 * @return bool True while it follows.
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-nextcloud-theme-step-keeps-nextclouds-theme-in-line-with-the-set-req-tqfs-008
	 */
	public function nextcloudFollows(): bool {
		return $this->config->getAppValue(Application::APP_ID, SetupStatusService::FOLLOWS_KEY, '0') === '1';
	}//end nextcloudFollows()

	/**
	 * Store whether Nextcloud's theme follows every change of set; turning it on applies the active
	 * set to Nextcloud's theme straight away (nothing to apply for Nextcloud default), off leaves it.
	 *
	 * @param bool $follows Whether Nextcloud's theme follows.
	 *
	 * @return array<int, string> The Nextcloud theming values applied now.
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-settings-page-switches-nextclouds-theme-in-line-with-the-set-req-tqfs-013
	 */
	public function setNextcloudFollows(bool $follows): array {
		$old = $this->config->getAppValue(Application::APP_ID, SetupStatusService::FOLLOWS_KEY, '');
		$new = ['0', '1'][(int)$follows];
		$this->config->setAppValue(Application::APP_ID, SetupStatusService::FOLLOWS_KEY, $new);
		if ($old !== $new) {
			$this->auditService->log(
				action: 'toggle_changed',
				context: ['key' => SetupStatusService::FOLLOWS_KEY, 'old' => $old, 'new' => $new]
			);
		}

		$active = $this->getActive();
		if ($follows === false || $this->coreSync === null || $active === self::DEFAULT_SET) {
			return [];
		}

		$applied = $this->coreSync->sync(tokenSetId: $active);
		if ($applied !== []) {
			$this->auditService->log(action: 'theming_sync_applied', context: ['new' => $active, 'key' => implode(',', $applied)]);
		}

		return $applied;
	}//end setNextcloudFollows()

	/**
	 * While Nextcloud's theme follows the set, apply the new set to it through the `--sync-core` path.
	 *
	 * @param string $tokenSet The set just made active.
	 * @param array<string, mixed> $auditContext The switch's audit context (its actor).
	 *
	 * @return void
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-nextcloud-theme-step-keeps-nextclouds-theme-in-line-with-the-set-req-tqfs-008
	 */
	private function followInNextcloud(string $tokenSet, array $auditContext): void {
		if ($this->coreSync === null || $this->nextcloudFollows() === false) {
			return;
		}

		$applied = $this->coreSync->sync(tokenSetId: $tokenSet);
		if ($applied !== []) {
			$this->auditService->log(
				action: 'theming_sync_applied',
				context: array_merge(['new' => $tokenSet, 'key' => implode(',', $applied)], $auditContext)
			);
		}
	}//end followInNextcloud()
}//end class
