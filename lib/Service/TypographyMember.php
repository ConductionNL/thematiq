<?php

/**
 * One sub-property of a composite typography token.
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
 * One sub-property of a composite `typography` token.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */
final class TypographyMember {

	/**
	 * The sub-property.
	 *
	 * @param string $path Its dotted path.
	 * @param string $key Its key in the composite.
	 * @param mixed $value Its value.
	 */
	public function __construct(
		public readonly string $path,
		public readonly string $key,
		public readonly mixed $value,
	) {
	}//end __construct()
}//end class
