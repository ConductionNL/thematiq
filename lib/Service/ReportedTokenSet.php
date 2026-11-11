<?php

/**
 * Thematiq Reported Token Set.
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
 * @spec openspec/specs/compliance-evidence/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The active token set a compliance report is about: its id and its manifest entry.
 *
 * @spec openspec/specs/compliance-evidence/spec.md
 */
final class ReportedTokenSet {

	/**
	 * Constructor.
	 *
	 * @param string $id The active token set id.
	 * @param array<string, mixed> $meta Its manifest entry.
	 */
	public function __construct(
		public readonly string $id,
		public readonly array $meta,
	) {
	}//end __construct()

	/**
	 * The design system the set names, `nldesign` when it names none.
	 *
	 * @return string The design-system id.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	public function designSystem(): string {
		return ($this->meta['design_system'] ?? 'nldesign');
	}//end designSystem()
}//end class
