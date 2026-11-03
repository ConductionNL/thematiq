<?php

/**
 * The collaborators that write and word the OpenProject set file download.
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
 * The writer of the OpenProject plugin's set file and the translator for its errors.
 *
 * @spec openspec/specs/token-sets/spec.md
 */
final class OpenProjectSetFileOutput {

	/**
	 * Constructor.
	 *
	 * @param OpenProjectSetFileWriter $writer Writes the set file.
	 * @param IL10N $l The error text.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	public function __construct(
		public readonly OpenProjectSetFileWriter $writer,
		public readonly IL10N $l,
	) {
	}//end __construct()
}//end class
