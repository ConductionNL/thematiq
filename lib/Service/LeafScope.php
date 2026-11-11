<?php

/**
 * Where a DTCG node sits while the mapper flattens it: its dotted path and the type it inherits.
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
 * @spec openspec/specs/custom-token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Where a DTCG node sits while the mapper flattens it: its dotted path and the type it inherits.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */
final class LeafScope {

	/**
	 * Constructor.
	 *
	 * @param string $prefix The accumulated dotted path.
	 * @param string|null $inheritedType The nearest ancestor's declared `$type`, if any.
	 */
	public function __construct(
		public readonly string $prefix,
		public readonly ?string $inheritedType,
	) {
	}//end __construct()
}//end class
