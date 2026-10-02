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
use ZipArchive;

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
	 * @param array<string, mixed> $bundle The bundle to write as bundle.json.
	 * @param array<string, string> $fonts Font bytes keyed by font id.
	 *
	 * @return array<int, string> The relative paths written.
	 *
	 * @throws RuntimeException When the directory cannot be created or a file cannot be written.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	public function write(string $dir, array $bundle, array $fonts): array {
		$dir = rtrim($dir, '/');
		if (is_dir($dir . '/fonts') === false && mkdir($dir . '/fonts', 0770, true) === false) {
			throw new RuntimeException('Cannot create the package directory ' . $dir . '.');
		}

		$json = json_encode($bundle, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		$written = [];
		$this->writeFile(path: $dir . '/bundle.json', content: ((string)$json) . "\n");
		$written[] = 'bundle.json';

		foreach ($fonts as $id => $bytes) {
			if (preg_match('/^[a-z0-9-]+$/', (string)$id) !== 1) {
				continue;
			}

			$this->writeFile(path: $dir . '/fonts/' . $id . '.woff2', content: $bytes);
			$written[] = 'fonts/' . $id . '.woff2';
		}

		return $written;
	}//end write()

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
			return $this->readZip(path: $path);
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

		$files = [];
		$total = 0;
		foreach ($candidates as $relative) {
			$file = $dir . '/' . $relative;
			if (is_file($file) === false) {
				continue;
			}

			$size = (int)filesize($file);
			$total += $size;
			$this->assertSize(name: $relative, size: $size, total: $total);
			$files[$relative] = (string)file_get_contents($file);
		}

		return $files;
	}//end readDirectory()

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
	private function readZip(string $path): array {
		$zip = new ZipArchive();
		if ($zip->open($path, ZipArchive::RDONLY) !== true) {
			throw new RuntimeException('The branding package ' . $path . ' is neither a directory nor a ZIP file.');
		}

		$prefix = $this->zipPrefix(zip: $zip);
		$files = [];
		$total = 0;
		for ($index = 0; $index < $zip->numFiles; $index++) {
			$stat = $zip->statIndex($index);
			$name = (string)($stat['name'] ?? '');
			if ($name === '' || str_starts_with($name, $prefix) === false) {
				continue;
			}

			$relative = substr($name, strlen($prefix));
			if ($this->isPackageFile(relative: $relative) === false) {
				continue;
			}

			$total += (int)($stat['size'] ?? 0);
			$this->assertSize(name: $relative, size: (int)($stat['size'] ?? 0), total: $total);
			$files[$relative] = (string)$zip->getFromIndex($index);
		}

		$zip->close();

		return $files;
	}//end readZip()

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

	/**
	 * Refuse a file or a package over the size limits.
	 *
	 * @param string $name The relative path, for the message.
	 * @param int $size The file size.
	 * @param int $total The running package size.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When a limit is exceeded.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function assertSize(string $name, int $size, int $total): void {
		if ($size > self::MAX_FILE_BYTES) {
			throw new RuntimeException('The package file ' . $name . ' is larger than 4 MB.');
		}

		if ($total > self::MAX_TOTAL_BYTES) {
			throw new RuntimeException('The branding package is larger than 64 MB.');
		}
	}//end assertSize()

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
		if (isset($files['bundle.json']) === false) {
			throw new RuntimeException('The branding package holds no bundle.json.');
		}

		$bundle = json_decode($files['bundle.json'], true);
		if (is_array($bundle) === false) {
			throw new RuntimeException('The bundle.json of the branding package is not valid JSON.');
		}

		$fonts = [];
		$tokens = [];
		foreach ($files as $relative => $content) {
			if (str_starts_with($relative, 'fonts/') === true) {
				$fonts[basename($relative, '.woff2')] = $content;
			}

			if (str_starts_with($relative, 'tokens/') === true) {
				$tokens[basename($relative, '.json')] = $content;
			}
		}

		$revision = null;
		if (isset($files['REVISION']) === true && trim($files['REVISION']) !== '') {
			$revision = substr(trim($files['REVISION']), 0, 80);
		}

		return [
			'bundle' => $bundle,
			'fonts' => $fonts,
			'tokens' => $tokens,
			'revision' => $revision,
			'hash' => $this->hashFiles(files: $files),
		];
	}//end parse()

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
