<?php

/**
 * A design token value with the DTCG type it is serialized as.
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
 * A design token value with the DTCG type it is serialized as.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */
final class TypedValue {

	/**
	 * Constructor.
	 *
	 * @param string $type The resolved `$type`.
	 * @param mixed $value The resolved `$value`.
	 */
	public function __construct(
		public readonly string $type,
		public readonly mixed $value,
	) {
	}//end __construct()
}//end class
