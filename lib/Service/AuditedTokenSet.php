<?php

/**
 * One shipped token set as the contrast audit reads it.
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
 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-automated-contrast-audit-over-all-shipped-token-sets
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * A shipped token set: the app root it ships in, its id and its
 * `token-sets.json` entry.
 *
 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-automated-contrast-audit-over-all-shipped-token-sets
 */
final class AuditedTokenSet {

	/**
	 * Constructor.
	 *
	 * @param string $appPath The app root path.
	 * @param string $id The token set id.
	 * @param array<string, mixed> $meta The set's manifest entry.
	 */
	public function __construct(
		public readonly string $appPath,
		public readonly string $id,
		public readonly array $meta,
	) {
	}//end __construct()

	/**
	 * The set's theming block.
	 *
	 * @return array<string, mixed> The block; empty when the entry has none.
	 *
	 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-automated-contrast-audit-over-all-shipped-token-sets
	 */
	public function theming(): array {
		return ($this->meta['theming'] ?? []);
	}//end theming()
}//end class
