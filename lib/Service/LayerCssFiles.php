<?php

/**
 * Thematiq Layer CSS Files.
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
 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The CSS layer files the vocabulary audit reads: every `.css` file under a
 * directory, except the token sets themselves and the runtime admin files.
 * Built by TokenSetVocabularyAuditService.
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
 */
final class LayerCssFiles {

	/**
	 * Runtime-generated CSS files under `css/` that are admin data, not app
	 * source. Excluded from the vocabulary scan so a value an admin typed into
	 * the theme editor can never widen the accepted vocabulary.
	 *
	 * @var array<int, string>
	 */
	private const RUNTIME_CSS_FILES = ['custom-overrides.css', 'custom-css.css'];

	/**
	 * Recursively collect every `.css` file under a directory, skipping the
	 * `tokens/` directory (the audited input) and the runtime-generated
	 * admin-data files.
	 *
	 * @param string $directory The directory to walk.
	 *
	 * @return array<int, string> Absolute file paths, sorted for determinism.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
	 */
	public function under(string $directory): array {
		$files = [];
		foreach ($this->directoryEntries(directory: $directory) as $entry) {
			$files = array_merge($files, $this->cssFilesAt(directory: $directory, entry: $entry));
		}

		sort($files);

		return $files;
	}//end under()

	/**
	 * The entries of a directory without `.` and `..`, [] when it is no directory or cannot be read.
	 *
	 * @param string $directory The directory.
	 *
	 * @return array<int, string> The entry names, in the order scandir() gives them.
	 */
	private function directoryEntries(string $directory): array {
		if (is_dir($directory) === false) {
			return [];
		}

		$entries = scandir($directory);
		if (is_array($entries) === false) {
			return [];
		}

		return array_values(array_diff($entries, ['.', '..']));
	}//end directoryEntries()

	/**
	 * The CSS files one directory entry contributes: a subdirectory's (never
	 * `tokens/`), or the entry itself when it is a CSS file that is not a
	 * runtime admin file.
	 *
	 * @param string $directory The directory.
	 * @param string $entry The entry name.
	 *
	 * @return array<int, string> Absolute file paths.
	 */
	private function cssFilesAt(string $directory, string $entry): array {
		$path = $directory . '/' . $entry;
		if (is_dir($path) === true) {
			return $this->subdirectoryCssFiles(entry: $entry, path: $path);
		}

		if (str_ends_with($entry, '.css') === false || in_array($entry, self::RUNTIME_CSS_FILES, true) === true) {
			return [];
		}

		return [$path];
	}//end cssFilesAt()

	/**
	 * The CSS files under a subdirectory, none under `tokens/` (the audited input).
	 *
	 * @param string $entry The subdirectory's name.
	 * @param string $path Its path.
	 *
	 * @return array<int, string> Absolute file paths.
	 */
	private function subdirectoryCssFiles(string $entry, string $path): array {
		if ($entry === 'tokens') {
			return [];
		}

		return $this->under(directory: $path);
	}//end subdirectoryCssFiles()
}//end class
