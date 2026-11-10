<?php

/**
 * The copy fields a page reads, in the language of the request.
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
 * @spec openspec/changes/not-found-page-board/specs/workplace-layout/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The keys of a page's copy, as `token-sets.json` writes them, and the
 * language each value is picked in.
 *
 * @spec openspec/changes/not-found-page-board/specs/workplace-layout/spec.md
 */
final class CopyFields {

	/**
	 * Constructor.
	 *
	 * @param array<int, string> $keys The copy keys, in snake case.
	 * @param string $language The request's language code.
	 */
	public function __construct(
		public readonly array $keys,
		public readonly string $language,
	) {
	}//end __construct()
}//end class
