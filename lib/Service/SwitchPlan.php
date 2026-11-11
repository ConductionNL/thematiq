<?php

/**
 * Thematiq Switch Plan.
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
 * @spec openspec/specs/scheduled-switch/spec.md#requirement-an-administrator-plans-a-switch
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * A planned switch: the token set to switch to and the window it is active in.
 *
 * @spec openspec/specs/scheduled-switch/spec.md#requirement-an-administrator-plans-a-switch
 */
final class SwitchPlan {

	/**
	 * Constructor.
	 *
	 * @param string $tokenSet The set to switch to.
	 * @param SwitchWindow $window The start and the optional end.
	 */
	public function __construct(
		public readonly string $tokenSet,
		public readonly SwitchWindow $window,
	) {
	}//end __construct()
}//end class
