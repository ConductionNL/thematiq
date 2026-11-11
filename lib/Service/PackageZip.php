<?php

/**
 * Thematiq Package Zip.
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
 * @spec openspec/specs/theme-as-code/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use RuntimeException;
use ZipArchive;

/**
 * Reads the package files of a branding package ZIP, at its root or under
 * one top-level directory. Built by BrandingPackageReader.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */
final class PackageZip {

	/**
	 * Read the package files of a ZIP, at its root or under one top-level directory.
	 *
	 * @param string $path The ZIP path.
	 *
	 * @return array<string, string> Relative path => content.
	 *
	 * @throws RuntimeException When the file is not a ZIP, or a file is too large.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	public function read(string $path): array {
		$zip = new ZipArchive();
		if ($zip->open($path, ZipArchive::RDONLY) !== true) {
			throw new RuntimeException('The branding package ' . $path . ' is neither a directory nor a ZIP file.');
		}

		$files = [];
		$budget = new PackageSizeBudget();
		foreach ($this->zipPackageEntries(zip: $zip) as $index => $entry) {
			$budget->admit(name: $entry['relative'], size: $entry['size']);
			$files[$entry['relative']] = (string)$zip->getFromIndex($index);
		}

		$zip->close();

		return $files;
	}//end read()

	/**
	 * The package files of a ZIP, at its root or under one top-level directory, by index.
	 *
	 * @param ZipArchive $zip The open archive.
	 *
	 * @return array<int, array{relative: string, size: int}> The path relative to the package root and the size, per index.
	 *
	 * @throws RuntimeException When the archive holds no bundle.json.
	 */
	private function zipPackageEntries(ZipArchive $zip): array {
		$prefix = $this->zipPrefix(zip: $zip);
		$entries = [];
		for ($index = 0; $index < $zip->numFiles; $index++) {
			$stat = $this->zipEntryAt(zip: $zip, index: $index);
			if ($stat['name'] === '' || str_starts_with($stat['name'], $prefix) === false) {
				continue;
			}

			$relative = substr($stat['name'], strlen($prefix));
			if ($this->isPackageFile(relative: $relative) === true) {
				$entries[$index] = ['relative' => $relative, 'size' => $stat['size']];
			}
		}

		return $entries;
	}//end zipPackageEntries()

	/**
	 * The name and size of one ZIP entry.
	 *
	 * @param ZipArchive $zip The open archive.
	 * @param int $index The entry index.
	 *
	 * @return array{name: string, size: int} The name ('' when unknown) and size (0 when unknown).
	 */
	private function zipEntryAt(ZipArchive $zip, int $index): array {
		$stat = $zip->statIndex($index);

		return ['name' => (string)($stat['name'] ?? ''), 'size' => (int)($stat['size'] ?? 0)];
	}//end zipEntryAt()

	/**
	 * The directory prefix of bundle.json inside a ZIP: empty at the root, `top/` one level down.
	 *
	 * @param ZipArchive $zip The open archive.
	 *
	 * @return string The prefix, with a trailing slash when not empty.
	 *
	 * @throws RuntimeException When the archive holds no bundle.json at either place.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function zipPrefix(ZipArchive $zip): string {
		if ($zip->locateName('bundle.json') !== false) {
			return '';
		}

		for ($index = 0; $index < $zip->numFiles; $index++) {
			$name = (string)$zip->getNameIndex($index);
			if (preg_match('#^([^/]+/)bundle\.json$#', $name, $match) === 1) {
				return $match[1];
			}
		}

		throw new RuntimeException('The branding package holds no bundle.json.');
	}//end zipPrefix()

	/**
	 * Whether a relative path is one of the files a package carries.
	 *
	 * @param string $relative The path relative to the package root.
	 *
	 * @return bool True for bundle.json, REVISION, fonts/<id>.woff2 and tokens/<id>.json.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function isPackageFile(string $relative): bool {
		return ($relative === 'bundle.json' || $relative === 'REVISION'
			|| preg_match('#^fonts/[^/]+\.woff2$#', $relative) === 1
			|| preg_match('#^tokens/[^/]+\.json$#', $relative) === 1);
	}//end isPackageFile()
}//end class
