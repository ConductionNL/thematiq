<?php

/**
 * A design token as the mapper reports it: its dotted path and its `$deprecated` value.
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
 * The path a token's diagnostics name, and the raw `$deprecated` value its warning is built from.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */
final class DesignTokenRef {

	/**
	 * Constructor.
	 *
	 * @param string $path The token's dotted path.
	 * @param mixed $deprecated The token's raw `$deprecated` value, null when it has none.
	 */
	public function __construct(
		public readonly string $path,
		public readonly mixed $deprecated,
	) {
	}//end __construct()
}//end class
