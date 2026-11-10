<?php

/**
 * A position in a Style Dictionary tree.
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
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Where the Style Dictionary walk is: the brand prefix the leaves are named
 * under, the key path from the root and the recursion depth.
 *
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md#requirement-accepted-conversion-inputs
 */
final class StyleDictionaryPath {

	/**
	 * Constructor.
	 *
	 * @param string $slug The brand prefix.
	 * @param array<int, string> $path The path walked so far.
	 * @param int $depth The recursion depth.
	 */
	public function __construct(
		public readonly string $slug,
		public readonly array $path = [],
		public readonly int $depth = 0,
	) {
	}//end __construct()
}//end class
