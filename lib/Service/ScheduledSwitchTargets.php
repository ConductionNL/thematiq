<?php

/**
 * The collaborators a scheduled switch changes the active token set through.
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
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The one token set write path, the set list that validates a plan, and the core theming sync every switch runs.
 *
 * @spec openspec/specs/scheduled-switch/spec.md
 */
final class ScheduledSwitchTargets {

	/**
	 * Constructor.
	 *
	 * @param ActiveTokenSetService $activeTokenSet The one token set write path.
	 * @param TokenSetService $tokenSets Validates a set on planning.
	 * @param ScheduledCoreThemingSync $coreSync Syncs core logo and colours with every switch.
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md
	 */
	public function __construct(
		public readonly ActiveTokenSetService $activeTokenSet,
		public readonly TokenSetService $tokenSets,
		public readonly ScheduledCoreThemingSync $coreSync,
	) {
	}//end __construct()
}//end class
