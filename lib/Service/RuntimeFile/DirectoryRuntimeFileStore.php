<?php

/**
 * Runtime files kept under a plain directory, for tests and tooling.
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

use RuntimeException;

/**
 * {@see RuntimeFileStore} that keeps each name as a file under one root.
 *
 * The container never wires this one: production uses
 * {@see AppDataRuntimeFileStore}. It exists so unit tests can keep the files
 * they inspect in a temporary directory, under the same names they had before
 * the move to app data. Its root MUST NOT be the app directory.
 *
 * @spec openspec/specs/runtime-file-storage/spec.md
 */
class DirectoryRuntimeFileStore implements RuntimeFileStore {

	/**
	 * Constructor.
	 *
	 * @param string $root The directory every name is stored under.
	 * @param RuntimeFileNames $names The name policy.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function __construct(
		private readonly string $root,
		private readonly RuntimeFileNames $names=new RuntimeFileNames(),
	) {
	}//end __construct()

	/**
	 * Read a file.
	 *
	 * @param string $name An allowed name.
	 *
	 * @return string|null The content, or null when the store holds no such file.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function read(string $name): ?string {
		$this->names->assertAllowed(name: $name);
		$path = $this->path(name: $name);
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
	 * Write a file, replacing any earlier content.
	 *
	 * @param string $name    An allowed name.
	 * @param string $content The content.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When the file cannot be written.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function write(string $name, string $content): void {
		$this->names->assertAllowed(name: $name);
		$path = $this->path(name: $name);
		$directory = dirname($path);
		if (is_dir($directory) === false && mkdir($directory, 0775, true) === false && is_dir($directory) === false) {
			throw new RuntimeException('Could not create ' . $directory);
		}

		if (file_put_contents($path, $content) === false) {
			throw new RuntimeException('Could not write ' . $path);
		}
	}//end write()

	/**
	 * Whether the store holds a file.
	 *
	 * @param string $name An allowed name.
	 *
	 * @return bool True when it does.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function exists(string $name): bool {
		$this->names->assertAllowed(name: $name);

		return is_file($this->path(name: $name));
	}//end exists()

	/**
	 * Delete a file; a file the store does not hold is not an error.
	 *
	 * @param string $name An allowed name.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function delete(string $name): void {
		$this->names->assertAllowed(name: $name);
		$path = $this->path(name: $name);
		if (is_file($path) === true) {
			unlink($path);
		}
	}//end delete()

	/**
	 * The names the store holds in one directory, sorted.
	 *
	 * @param string $directory An app-relative directory such as `css/tokens`.
	 *
	 * @return array<int, string> Full names, such as `css/tokens/example.css`.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function listDirectory(string $directory): array {
		$directory = trim($directory, '/');
		$names = [];
		$paths = glob($this->root . '/' . $directory . '/*');
		if ($paths === false) {
			$paths = [];
		}

		foreach ($paths as $path) {
			$name = $directory . '/' . basename($path);
			if (is_file($path) === true && $this->names->isAllowed(name: $name) === true) {
				$names[] = $name;
			}
		}

		sort($names);

		return $names;
	}//end listDirectory()

	/**
	 * A short value that changes whenever the file's content does.
	 *
	 * @param string $name An allowed name.
	 *
	 * @return string The revision, or an empty string when the file is absent.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function revision(string $name): string {
		$content = $this->read(name: $name);
		if ($content === null) {
			return '';
		}

		return substr(sha1($content), 0, 12);
	}//end revision()

	/**
	 * The file a name is stored in.
	 *
	 * @param string $name An allowed name.
	 *
	 * @return string The absolute path.
	 */
	private function path(string $name): string {
		return rtrim($this->root, '/') . '/' . $name;
	}//end path()
}//end class
