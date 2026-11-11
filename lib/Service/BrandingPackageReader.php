<?php

/**
 * Thematiq branding package reader and writer.
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

/**
 * Reads a branding package from disk, and writes one.
 *
 * A package is a directory, or a ZIP of one:
 *
 *     bundle.json        the configuration bundle, unchanged format
 *     fonts/<id>.woff2   one file per customFonts entry
 *     tokens/<id>.json   optional DTCG sources, converted on apply
 *     REVISION           optional, the Git commit the directory came from
 *
 * A ZIP may hold the tree at its root or under one top-level directory (the
 * shape a Git host's "download ZIP" produces). Nothing is ever extracted to
 * disk: every file is read into memory, so a crafted entry name cannot write
 * outside anything.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */
class BrandingPackageReader {

	/**
	 * The largest single file a package may carry (a font is at most 2 MB).
	 *
	 * @var int
	 */
	public const MAX_FILE_BYTES = (4 * 1024 * 1024);

	/**
	 * The largest package, all files together.
	 *
	 * @var int
	 */
	public const MAX_TOTAL_BYTES = (64 * 1024 * 1024);

	/**
	 * The extension of each kind of package part, cut off to give its id.
	 *
	 * @var array<string, string>
	 */
	private const PART_EXTENSIONS = ['fonts' => '.woff2', 'tokens' => '.json'];

	/**
	 * Read a package directory or ZIP.
	 *
	 * @param string $path The directory or ZIP path.
	 *
	 * @return array{
	 *     bundle: array<string, mixed>,
	 *     fonts: array<string, string>,
	 *     tokens: array<string, string>,
	 *     revision: string|null,
	 *     hash: string
	 * } The decoded bundle, font bytes and token sources keyed by id, the revision and the content hash.
	 *
	 * @throws RuntimeException When the path is missing, unreadable, too large, or holds no valid bundle.json.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	public function read(string $path): array {
		return $this->parse(files: $this->readFiles(path: $path));
	}//end read()

	/**
	 * The content hash of a package: sorted relative paths with the hash of each file.
	 *
	 * @param string $path The directory or ZIP path.
	 *
	 * @return string The `sha256:` prefixed hash.
	 *
	 * @throws RuntimeException When the package cannot be read.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	public function hash(string $path): string {
		return $this->hashFiles(files: $this->readFiles(path: $path));
	}//end hash()

	/**
	 * Write a package directory.
	 *
	 * @param string $dir The target directory; created when missing.
	 * @param PackageContent $content The bundle to write as bundle.json, and the font bytes keyed by font id.
	 *
	 * @return array<int, string> The relative paths written.
	 *
	 * @throws RuntimeException When the directory cannot be created or a file cannot be written.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	public function write(string $dir, PackageContent $content): array {
		$dir = rtrim($dir, '/');
		if (is_dir($dir . '/fonts') === false && mkdir($dir . '/fonts', 0770, true) === false) {
			throw new RuntimeException('Cannot create the package directory ' . $dir . '.');
		}

		$json = json_encode($content->bundle, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		$this->writeFile(path: $dir . '/bundle.json', content: ((string)$json) . "\n");

		return array_merge(['bundle.json'], $this->writeFonts(dir: $dir, fonts: $content->fonts));
	}//end write()

	/**
	 * Write each font with a safe id into the package's fonts directory.
	 *
	 * @param string $dir The package directory.
	 * @param array<string, string> $fonts Font bytes keyed by font id.
	 *
	 * @return array<int, string> The relative paths written.
	 *
	 * @throws RuntimeException When a file cannot be written.
	 */
	private function writeFonts(string $dir, array $fonts): array {
		$written = [];
		foreach ($fonts as $id => $bytes) {
			if (preg_match('/^[a-z0-9-]+$/', (string)$id) !== 1) {
				continue;
			}

			$this->writeFile(path: $dir . '/fonts/' . $id . '.woff2', content: $bytes);
			$written[] = 'fonts/' . $id . '.woff2';
		}

		return $written;
	}//end writeFonts()

	/**
	 * Read every file of a package into memory, keyed by relative path.
	 *
	 * @param string $path The directory or ZIP path.
	 *
	 * @return array<string, string> Relative path => content.
	 *
	 * @throws RuntimeException When the path is missing or unreadable.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function readFiles(string $path): array {
		if (is_dir($path) === true) {
			return $this->readDirectory(dir: rtrim($path, '/'));
		}

		if (is_file($path) === true && is_readable($path) === true) {
			return (new PackageZip())->read(path: $path);
		}

		throw new RuntimeException('The branding package ' . $path . ' does not exist or cannot be read.');
	}//end readFiles()

	/**
	 * Read the package files of a directory (bundle.json, REVISION, fonts/, tokens/).
	 *
	 * @param string $dir The package directory.
	 *
	 * @return array<string, string> Relative path => content.
	 *
	 * @throws RuntimeException When a file is too large.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function readDirectory(string $dir): array {
		$files = [];
		$budget = new PackageSizeBudget();
		foreach ($this->directoryCandidates(dir: $dir) as $relative) {
			$file = $dir . '/' . $relative;
			if (is_file($file) === false) {
				continue;
			}

			$budget->admit(name: $relative, size: (int)filesize($file));
			$files[$relative] = (string)file_get_contents($file);
		}

		return $files;
	}//end readDirectory()

	/**
	 * The files a package directory may carry: bundle.json, REVISION, and the
	 * fonts and token sources it holds.
	 *
	 * @param string $dir The package directory.
	 *
	 * @return array<int, string> Paths relative to the directory.
	 */
	private function directoryCandidates(string $dir): array {
		$candidates = ['bundle.json', 'REVISION'];
		foreach (['fonts' => '*.woff2', 'tokens' => '*.json'] as $sub => $pattern) {
			$found = glob($dir . '/' . $sub . '/' . $pattern);
			if ($found === false) {
				continue;
			}

			foreach ($found as $file) {
				$candidates[] = $sub . '/' . basename($file);
			}
		}

		return $candidates;
	}//end directoryCandidates()

	/**
	 * Turn the raw files into the package parts.
	 *
	 * @param array<string, string> $files Relative path => content.
	 *
	 * @return array{
	 *     bundle: array<string, mixed>,
	 *     fonts: array<string, string>,
	 *     tokens: array<string, string>,
	 *     revision: string|null,
	 *     hash: string
	 * } The package parts.
	 *
	 * @throws RuntimeException When bundle.json is missing or is not a JSON object.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function parse(array $files): array {
		return [
			'bundle' => $this->decodedBundle(files: $files),
			'fonts' => $this->partsUnder(files: $files, sub: 'fonts'),
			'tokens' => $this->partsUnder(files: $files, sub: 'tokens'),
			'revision' => $this->revisionOf(files: $files),
			'hash' => $this->hashFiles(files: $files),
		];
	}//end parse()

	/**
	 * The package's bundle.json as an array.
	 *
	 * @param array<string, string> $files Relative path => content.
	 *
	 * @return array<string, mixed> The bundle.
	 *
	 * @throws RuntimeException When bundle.json is missing or is not a JSON object.
	 */
	private function decodedBundle(array $files): array {
		if (isset($files['bundle.json']) === false) {
			throw new RuntimeException('The branding package holds no bundle.json.');
		}

		$bundle = json_decode($files['bundle.json'], true);
		if (is_array($bundle) === false) {
			throw new RuntimeException('The bundle.json of the branding package is not valid JSON.');
		}

		return $bundle;
	}//end decodedBundle()

	/**
	 * The files under `fonts/` or `tokens/`, keyed by their id.
	 *
	 * @param array<string, string> $files Relative path => content.
	 * @param string $sub `fonts` or `tokens`.
	 *
	 * @return array<string, string> Id => content.
	 */
	private function partsUnder(array $files, string $sub): array {
		$parts = [];
		foreach ($files as $relative => $content) {
			if (str_starts_with($relative, $sub . '/') === true) {
				$parts[basename($relative, self::PART_EXTENSIONS[$sub])] = $content;
			}
		}

		return $parts;
	}//end partsUnder()

	/**
	 * The package's REVISION, trimmed and cut to 80 characters, null when absent or empty.
	 *
	 * @param array<string, string> $files Relative path => content.
	 *
	 * @return string|null The revision.
	 */
	private function revisionOf(array $files): string|null {
		if (isset($files['REVISION']) === false || trim($files['REVISION']) === '') {
			return null;
		}

		return substr(trim($files['REVISION']), 0, 80);
	}//end revisionOf()

	/**
	 * Hash the package files independent of their order on disk.
	 *
	 * @param array<string, string> $files Relative path => content.
	 *
	 * @return string The `sha256:` prefixed hash.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function hashFiles(array $files): string {
		ksort($files);
		$context = hash_init('sha256');
		foreach ($files as $relative => $content) {
			hash_update($context, $relative . "\0" . hash('sha256', $content) . "\n");
		}

		return 'sha256:' . hash_final($context);
	}//end hashFiles()

	/**
	 * Write one file.
	 *
	 * @param string $path The absolute path.
	 * @param string $content The content.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When the file cannot be written.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function writeFile(string $path, string $content): void {
		if (file_put_contents($path, $content) === false) {
			throw new RuntimeException('Cannot write ' . $path . '.');
		}
	}//end writeFile()
}//end class
