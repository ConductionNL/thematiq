<?php

/**
 * Thematiq Delegated Choice.
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
 * @spec openspec/specs/per-group-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * A subadmin's choice of a token set for a delegated group.
 *
 * @spec openspec/specs/per-group-theming/spec.md
 */
final class DelegatedChoice {

	/**
	 * Constructor.
	 *
	 * @param string $group The group id.
	 * @param string $tokenSet The chosen set.
	 */
	public function __construct(
		public readonly string $group,
		public readonly string $tokenSet,
	) {
	}//end __construct()
}//end class
