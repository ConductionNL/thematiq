<?php

/**
 * The token set a document is styled with, and its resolved tokens.
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
 * @spec openspec/specs/document-house-style/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * A resolved token set: its id and the values its tokens resolve to.
 *
 * @spec openspec/specs/document-house-style/spec.md
 */
final class DocumentSet {

	/**
	 * Constructor.
	 *
	 * @param string $id The token set id.
	 * @param array<string, string> $tokens Token => resolved value.
	 */
	public function __construct(
		public readonly string $id,
		public readonly array $tokens,
	) {
	}//end __construct()
}//end class
