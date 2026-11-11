<?php

/**
 * How the CSS colour parser scales one component of a colour function.
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
 * What 100% of one component stands for, and what its plain number is divided by.
 *
 * @spec openspec/specs/token-set-dtcg-export/spec.md
 */
final class ColourComponentScale {

	/**
	 * Constructor.
	 *
	 * @param float $percent The value 100% stands for in this position.
	 * @param float $plainDivisor What a plain number is divided by: 255 for an rgb() channel, else 1.
	 */
	public function __construct(
		public readonly float $percent,
		public readonly float $plainDivisor,
	) {
	}//end __construct()
}//end class
