<?php

/**
 * Thematiq Switch Import Run.
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
 * @spec openspec/specs/config-portability/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use Closure;

/**
 * One validation of a bundle's planned switches: the set check, the entry
 * being read, and the accepted entries and errors so far. Built by
 * ScheduledSwitchStore.
 *
 * @spec openspec/specs/config-portability/spec.md
 */
final class SwitchImportRun {

	/**
	 * The position of the entry being read, for the error messages.
	 *
	 * @var integer
	 */
	public int $index = 0;

	/**
	 * The accepted, normalised entries.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $entries = [];

	/**
	 * The errors, in the order found.
	 *
	 * @var array<int, string>
	 */
	public array $errors = [];

	/**
	 * Constructor.
	 *
	 * @param Closure $setExists Answers whether a token set id exists (installed or bundled).
	 */
	public function __construct(
		public readonly Closure $setExists,
	) {
	}//end __construct()
}//end class
