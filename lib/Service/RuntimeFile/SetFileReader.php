<?php

/**
 * Read a token set's files from the release or from the runtime store.
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
 * The one rule every reader of a set's stylesheet follows.
 *
 * An uploaded set's id starts with `custom-` and its files live in the
 * runtime store. Every other set ships with the release. Readers that are
 * also used without a store (the docs build, hand-built unit tests) pass
 * null and get the release only, which is what they had before.
 *
 * Kept in one place so the readers do not each carry a copy of the branch.
 *
 * @spec openspec/specs/runtime-file-storage/spec.md
 */
class SetFileReader {

	/**
	 * Names that belong to an uploaded set.
	 */
	private const UPLOADED = '#^css/tokens/(?:dark/)?custom-[a-z0-9-]+\.css$#';

	/**
	 * Read a set file.
	 *
	 * @param string                $appPath The app directory.
	 * @param string                $name    The app-relative name, such as `css/tokens/utrecht.css`.
	 * @param RuntimeFileStore|null $store   The runtime store, or null for the release only.
	 *
	 * @return string|null The content, or null when the file does not exist.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function read(string $appPath, string $name, ?RuntimeFileStore $store): ?string {
		if ($store !== null && preg_match(self::UPLOADED, $name) === 1) {
			return $store->read(name: $name);
		}

		$path = rtrim($appPath, '/') . '/' . $name;
		if (is_file($path) === false) {
			return null;
		}

		$content = file_get_contents($path);
		if ($content === false) {
			return null;
		}

		return $content;
	}//end read()

	/**
	 * Whether a set file exists.
	 *
	 * @param string                $appPath The app directory.
	 * @param string                $name    The app-relative name.
	 * @param RuntimeFileStore|null $store   The runtime store, or null for the release only.
	 *
	 * @return bool True when it does.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function exists(string $appPath, string $name, ?RuntimeFileStore $store): bool {
		if ($store !== null && preg_match(self::UPLOADED, $name) === 1) {
			return $store->exists(name: $name);
		}

		return is_file(rtrim($appPath, '/') . '/' . $name);
	}//end exists()

	/**
	 * The file names in one directory, shipped and uploaded, without duplicates.
	 *
	 * @param string                $appPath   The app directory.
	 * @param string                $directory An app-relative directory such as `css/tokens`.
	 * @param RuntimeFileStore|null $store     The runtime store, or null for the release only.
	 *
	 * @return array<int, string> Base names such as `utrecht.css`, sorted.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function fileNames(string $appPath, string $directory, ?RuntimeFileStore $store): array {
		$directory = trim($directory, '/');
		$names = [];
		$paths = glob(rtrim($appPath, '/') . '/' . $directory . '/*');
		if ($paths === false) {
			$paths = [];
		}

		foreach ($paths as $path) {
			if (is_file($path) === true) {
				$names[] = basename($path);
			}
		}

		if ($store !== null) {
			foreach ($store->listDirectory(directory: $directory) as $name) {
				$names[] = basename($name);
			}
		}

		$names = array_values(array_unique($names));
		sort($names);

		return $names;
	}//end fileNames()
}//end class
