<?php

/**
 * One internal token's rule before it is scoped: the component selectors and the declaration.
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
 * @spec openspec/specs/component-tokens/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The selectors an internal token reaches and the declaration that carries it there.
 *
 * @spec openspec/specs/component-tokens/spec.md
 */
final class InternalRule {

	/**
	 * Constructor.
	 *
	 * @param array<int, string> $selectors The component's selectors.
	 * @param string $declaration The declaration line.
	 */
	public function __construct(
		public readonly array $selectors,
		public readonly string $declaration,
	) {
	}//end __construct()
}//end class
