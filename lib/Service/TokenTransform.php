<?php

/**
 * A mapping table transform, as a rule or a fallback applies it.
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
 * One of the mapping table's closed set of transforms, with its arguments and
 * the semantic layer it may read (`mix` against another token).
 *
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
 */
final class TokenTransform {

	/**
	 * Constructor.
	 *
	 * @param string $kind The transform name; `copy` and an unknown name leave the value as it is.
	 * @param array<string, mixed> $args The transform's arguments.
	 * @param array<string, string> $semantic The semantic layer so far.
	 */
	public function __construct(
		public readonly string $kind,
		public readonly array $args,
		public readonly array $semantic,
	) {
	}//end __construct()
}//end class
