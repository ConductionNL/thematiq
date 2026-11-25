<?php

/**
 * Why an uploaded file is refused: the HTTP status and the message.
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
 * @spec openspec/specs/token-import-export/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * One refusal of an uploaded file.
 *
 * @spec openspec/specs/token-import-export/spec.md
 */
final class UploadRefusal {

	/**
	 * Constructor.
	 *
	 * @param int $status The HTTP status: 400, 413 or 415.
	 * @param string $error The message.
	 */
	public function __construct(
		public readonly int $status,
		public readonly string $error,
	) {
	}//end __construct()
}//end class
