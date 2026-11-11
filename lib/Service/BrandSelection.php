<?php

/**
 * Thematiq brand selection: an uploaded multi-brand source and the brands chosen from it.
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
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-brand-is-a-custom-token-set-linked-to-its-source
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * An uploaded multi-brand source and the brand keys chosen from it.
 *
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-brand-is-a-custom-token-set-linked-to-its-source
 */
final class BrandSelection {

	/**
	 * Constructor.
	 *
	 * @param BrandDocument $document The uploaded document.
	 * @param array<int, mixed> $keys The chosen brand keys.
	 */
	public function __construct(
		public readonly BrandDocument $document,
		public readonly array $keys,
	) {
	}//end __construct()
}//end class
