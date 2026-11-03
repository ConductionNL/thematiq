<?php

/**
 * How far an alias chain has been followed.
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
 * An alias chain being followed: the paths in order and the hops taken.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */
final class AliasWalk {

	/**
	 * The walk so far.
	 *
	 * @param array<int, string> $chain The paths walked, in order.
	 * @param int $hops The hops taken.
	 */
	public function __construct(
		public readonly array $chain,
		public readonly int $hops = 0,
	) {
	}//end __construct()

	/**
	 * Whether the chain already passed a path.
	 *
	 * @param string $path The path.
	 *
	 * @return bool True when the path is on the chain.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	public function passed(string $path): bool {
		return in_array($path, $this->chain, true);
	}//end passed()

	/**
	 * The chain as a diagnostic, optionally with the path it would go to next.
	 *
	 * @param string|null $next The next path.
	 *
	 * @return string The paths joined by arrows.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	public function describe(?string $next = null): string {
		$chain = $this->chain;
		if ($next !== null) {
			$chain[] = $next;
		}

		return implode(' -> ', $chain);
	}//end describe()
}//end class
