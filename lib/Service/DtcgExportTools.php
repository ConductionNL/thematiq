<?php

/**
 * The collaborators that turn a token set into a DTCG document.
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
 * @spec openspec/specs/token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\IL10N;

/**
 * The CSS parser, the DTCG writer, the deprecation records a document carries, and the translator for its errors.
 *
 * @spec openspec/specs/token-sets/spec.md
 */
final class DtcgExportTools {

	/**
	 * Constructor.
	 *
	 * @param CssParserService $cssParser Reads the set's declarations.
	 * @param DesignTokensWriter $writer Writes the document.
	 * @param DeprecationRecords $deprecations Written as `$deprecated` on deprecated tokens.
	 * @param IL10N $l The error text.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	public function __construct(
		public readonly CssParserService $cssParser,
		public readonly DesignTokensWriter $writer,
		public readonly DeprecationRecords $deprecations,
		public readonly IL10N $l,
	) {
	}//end __construct()
}//end class
