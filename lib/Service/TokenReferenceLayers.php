<?php

/**
 * The layers one token set's reference is read from.
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
 * @spec openspec/specs/token-reference/spec.md#requirement-the-docs-site-has-a-reference-page-per-shipped-set
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * What a set's reference shows each token from: the defaults layer, the set's
 * own declarations, its dark values, and what paints each token.
 *
 * @spec openspec/specs/token-reference/spec.md#requirement-the-docs-site-has-a-reference-page-per-shipped-set
 */
final class TokenReferenceLayers {

	/**
	 * Constructor.
	 *
	 * @param array<string, string> $defaults The defaults layer, name => value.
	 * @param array<string, string> $own The set's own `:root` declarations.
	 * @param array<string, string> $dark The set's dark values.
	 * @param array<string, array<int, string>> $consumers Token => what paints it.
	 */
	public function __construct(
		public readonly array $defaults,
		public readonly array $own,
		public readonly array $dark,
		public readonly array $consumers,
	) {
	}//end __construct()

	/**
	 * Every token the reference lists: the defaults and the set's own, sorted.
	 *
	 * @return array<int, string> The names.
	 *
	 * @spec openspec/specs/token-reference/spec.md#requirement-the-docs-site-has-a-reference-page-per-shipped-set
	 */
	public function names(): array {
		$names = array_unique(array_merge(array_keys($this->defaults), array_keys($this->own)));
		sort($names);

		return $names;
	}//end names()
}//end class
