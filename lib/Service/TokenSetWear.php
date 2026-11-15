<?php

/**
 * How the page wears a token set: the design system it belongs to and whether its dark variant is injected.
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
 * The design system a set wears and whether its dark variant is part of the cascade.
 *
 * @spec openspec/specs/component-tokens/spec.md
 */
final class TokenSetWear {

	/**
	 * Constructor.
	 *
	 * @param string $designSystemId The design system the set wears.
	 * @param bool $withDark Whether the set's dark variant is injected.
	 */
	public function __construct(
		public readonly string $designSystemId,
		public readonly bool $withDark,
	) {
	}//end __construct()
}//end class
