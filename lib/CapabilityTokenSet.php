<?php

/**
 * NL Design — the active token set as the theming capability reads it.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Capabilities
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/theming-capability/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq;

/**
 * The active token set's id and its manifest entry (empty for a custom or unknown set).
 *
 * @spec openspec/specs/theming-capability/spec.md
 */
final class CapabilityTokenSet {

	/**
	 * Constructor.
	 *
	 * @param string $id The active token set id (appconfig `token_set`).
	 * @param array<string, mixed> $meta The active set's manifest entry.
	 */
	public function __construct(
		public readonly string $id,
		public readonly array $meta,
	) {
	}//end __construct()
}//end class
