<?php

/**
 * One map() of a DTCG document: the flattened leaves and the outcome so far. Built by DesignTokensMapper.
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
 * One map() of a DTCG document: the flattened leaves and the outcome so far. Built by DesignTokensMapper.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */
final class LeafMappingRun {

	/**
	 * Constructor.
	 *
	 * @param array<string, array<string, mixed>> $leaves The full leaf table (for alias resolution).
	 * @param DesignTokensMapping $mapping The outcome of the run so far.
	 */
	public function __construct(
		public readonly array $leaves,
		public readonly DesignTokensMapping $mapping,
	) {
	}//end __construct()
}//end class
