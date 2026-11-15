<?php

/**
 * The collaborators that keep two replicas from applying at once.
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
 * @spec openspec/specs/theme-as-code/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\IAppConfig;
use OCP\Lock\ILockingProvider;

/**
 * The Nextcloud lock and the app config, re-read under the lock for what another replica applied.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */
final class ConfigSourceLock {

	/**
	 * Constructor.
	 *
	 * @param ILockingProvider $locking The Nextcloud lock.
	 * @param IAppConfig $appConfig Re-read under the lock, for what another replica applied.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	public function __construct(
		public readonly ILockingProvider $locking,
		public readonly IAppConfig $appConfig,
	) {
	}//end __construct()
}//end class
