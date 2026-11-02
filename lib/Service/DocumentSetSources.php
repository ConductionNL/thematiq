<?php

/**
 * The collaborators that say which token set a document wears.
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
 * The set that applies to a user, its name, its resolved tokens and its theming metadata.
 *
 * @spec openspec/specs/document-house-style/spec.md
 */
final class DocumentSetSources {

	/**
	 * Constructor.
	 *
	 * @param UserTokenSetResolver $resolver The set that applies to a user.
	 * @param TokenSetService $tokenSets Set names.
	 * @param TokenSetPreviewService $preview A set's resolved tokens.
	 * @param DesignSystemService $designSystem A set's theming metadata (logo).
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	public function __construct(
		public readonly UserTokenSetResolver $resolver,
		public readonly TokenSetService $tokenSets,
		public readonly TokenSetPreviewService $preview,
		public readonly DesignSystemService $designSystem,
	) {
	}//end __construct()
}//end class
