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
 * @spec openspec/changes/apply-scheduled-theme-switch/tasks.md#task-1.1
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\IConfig;

/**
 * The one write path for the instance-wide token set. The settings dropdown
 * and the scheduled switch job both go through switchTo(), so both run the
 * same isValidTokenSet() check and both leave an audit entry.
 *
 * @spec openspec/changes/apply-scheduled-theme-switch/tasks.md#task-1.1
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
	 * @param IConfig             $config       The config service.
	 * @param TokenSetService     $tokenSets    Validates that a set exists.
	 * @param ThemingAuditService $auditService Records each switch.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly TokenSetService $tokenSets,
		private readonly ThemingAuditService $auditService,
	) {
	}//end __construct()

	/**
	 * The active token set id.
	 *
	 * @return string The id.
	 *
	 * @spec openspec/changes/apply-scheduled-theme-switch/tasks.md#task-1.1
	 */
	public function getActive(): string {
		return $this->config->getAppValue(Application::APP_ID, self::CONFIG_KEY, self::DEFAULT_SET);
	}//end getActive()

	/**
	 * Make a token set the active one and audit the switch.
	 *
	 * @param string               $tokenSet     The set to activate.
	 * @param string               $auditAction  The audit action to record.
	 * @param array<string, mixed> $auditContext Extra audit context (for example `actor` and `switchId`).
	 *
	 * @return string The set that was active before.
	 *
	 * @throws \InvalidArgumentException When the set does not exist; nothing is written then.
	 *
	 * @spec openspec/changes/apply-scheduled-theme-switch/tasks.md#task-1.1
	 */
	public function switchTo(string $tokenSet, string $auditAction = 'token_set_changed', array $auditContext = []): string {
		if ($this->tokenSets->isValidTokenSet(tokenSetId: $tokenSet) === false) {
			throw new \InvalidArgumentException('Invalid token set');
		}

		$previous = $this->getActive();
		$this->config->setAppValue(Application::APP_ID, self::CONFIG_KEY, $tokenSet);

		$this->auditService->log(
			action: $auditAction,
			context: array_merge(['old' => $previous, 'new' => $tokenSet], $auditContext)
		);

		return $previous;
	}//end switchTo()
}//end class
