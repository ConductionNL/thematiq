<?php

/**
 * Thematiq brand source document: an uploaded multi-brand source and its file name.
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
 * An uploaded multi-brand source: its content and the name of the file it came in.
 *
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-brand-is-a-custom-token-set-linked-to-its-source
 */
final class BrandDocument {

	/**
	 * Constructor.
	 *
	 * @param string $content The uploaded document.
	 * @param string|null $fileName The uploaded file's name.
	 */
	public function __construct(
		public readonly string $content,
		public readonly ?string $fileName = null,
	) {
	}//end __construct()
}//end class
