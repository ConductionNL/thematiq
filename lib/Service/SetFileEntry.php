<?php

/**
 * One value of the OpenProject plugin's set file, under the CSS name it becomes.
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
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * A value of a set file: the CSS name it is kept under (null for a key the
 * file should not have), the value as written, and where it sits.
 *
 * @spec openspec/specs/openproject-set-file/spec.md#requirement-the-plugins-set-file-becomes-a-custom-token-set
 */
final class SetFileEntry {

	/**
	 * Constructor.
	 *
	 * @param string|null $name The CSS name, or null for a key the file should not have.
	 * @param mixed $value The value.
	 * @param string $path Where the value sits in the file.
	 */
	public function __construct(
		public readonly ?string $name,
		public readonly mixed $value,
		public readonly string $path,
	) {
	}//end __construct()
}//end class
