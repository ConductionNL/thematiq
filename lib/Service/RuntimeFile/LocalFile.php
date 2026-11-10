<?php

/**
 * A file on the local disk, read whole.
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
 * @spec openspec/specs/runtime-file-storage/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service\RuntimeFile;

/**
 * Reads a file of the release or of a data directory.
 *
 * @spec openspec/specs/runtime-file-storage/spec.md
 */
final class LocalFile {

	/**
	 * A file's content, or null when it is not a file or cannot be read.
	 *
	 * @param string $path The absolute path.
	 *
	 * @return string|null The content.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public static function contents(string $path): ?string {
		if (is_file($path) === false) {
			return null;
		}

		$content = file_get_contents($path);
		if ($content === false) {
			return null;
		}

		return $content;
	}//end contents()
}//end class
