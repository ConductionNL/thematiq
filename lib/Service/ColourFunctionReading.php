<?php

/**
 * How the CSS colour parser reads the components of one colour function.
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
 * @spec openspec/specs/token-set-dtcg-export/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The colour space a function writes and what its plain numbers are divided by.
 *
 * @spec openspec/specs/token-set-dtcg-export/spec.md
 */
final class ColourFunctionReading {

	/**
	 * Constructor.
	 *
	 * @param string $space The colour space.
	 * @param float $plainDivisor What a plain number is divided by: 255 for an rgb() channel, else 1.
	 */
	public function __construct(
		public readonly string $space,
		public readonly float $plainDivisor,
	) {
	}//end __construct()
}//end class
