<?php

/**
 * The bytes of an uploaded font and the size the upload reported.
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
 * @spec openspec/specs/custom-fonts/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * An uploaded font file as read from the request.
 *
 * @spec openspec/specs/custom-fonts/spec.md
 */
final class FontUpload {

	/**
	 * Constructor.
	 *
	 * @param string $bytes The file's bytes.
	 * @param int $reportedSize The size the upload reported, else the bytes' length.
	 */
	public function __construct(
		public readonly string $bytes,
		public readonly int $reportedSize,
	) {
	}//end __construct()
}//end class
