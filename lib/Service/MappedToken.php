<?php

/**
 * A design token being mapped: its path and deprecation, and the outcome of the run it belongs to.
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
 * A design token being mapped: its path and deprecation, and the outcome of the run it belongs to.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */
final class MappedToken {

	/**
	 * Constructor.
	 *
	 * @param DesignTokenRef $ref The token's path and `$deprecated` value.
	 * @param DesignTokensMapping $mapping The outcome of the run so far.
	 */
	public function __construct(
		public readonly DesignTokenRef $ref,
		public readonly DesignTokensMapping $mapping,
	) {
	}//end __construct()
}//end class
