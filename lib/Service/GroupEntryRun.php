<?php

/**
 * NL Design Group Entry Run.
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

use Closure;

/**
 * One batch of submitted mapping entries being validated: the groups seen so
 * far, the entry being checked, and how to read a group's stored set. Built
 * by GroupThemingService.
 *
 * @spec openspec/specs/per-group-theming/spec.md
 */
final class GroupEntryRun {

	/**
	 * The groups already validated in this batch.
	 *
	 * @var array<string, bool>
	 */
	public array $seenGroups = [];

	/**
	 * The raw entry being validated, as the refusal names it.
	 *
	 * @var mixed
	 */
	public mixed $entry = null;

	/**
	 * Constructor.
	 *
	 * @param Closure $storedSetOf Gives the set a group's stored entry names, or null.
	 */
	public function __construct(
		public readonly Closure $storedSetOf,
	) {
	}//end __construct()
}//end class
