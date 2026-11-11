<?php

/**
 * The order a Tokens Studio document merges its sets in.
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
 * @spec openspec/specs/multi-brand-token-sources/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * `$metadata.tokenSetOrder`, as a comparison of two set names: names it does not list follow, by name.
 *
 * @spec openspec/specs/multi-brand-token-sources/spec.md
 */
final class SetOrder {

	/**
	 * Constructor.
	 *
	 * @param array<int, string> $order The set names in order.
	 */
	public function __construct(
		public readonly array $order,
	) {
	}//end __construct()

	/**
	 * Compare two set names by their place in the set order; names it does not list follow, by name.
	 *
	 * @param string $first The first name.
	 * @param string $second The second name.
	 *
	 * @return int Below, at or above zero, as for usort().
	 *
	 * @spec openspec/specs/multi-brand-token-sources/spec.md
	 */
	public function compare(string $first, string $second): int {
		$firstAt = array_search($first, $this->order, true);
		$secondAt = array_search($second, $this->order, true);
		if ($firstAt === false && $secondAt === false) {
			return strcmp($first, $second);
		}

		if ($firstAt === false || $secondAt === false) {
			return (int)($firstAt === false) - (int)($secondAt === false);
		}

		return $firstAt <=> $secondAt;
	}//end compare()
}//end class
