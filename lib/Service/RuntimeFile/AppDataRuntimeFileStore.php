<?php

/**
 * Runtime files kept in Nextcloud's app data for thematiq.
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
 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service\RuntimeFile;

use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFolder;
use RuntimeException;
use Throwable;

/**
 * {@see RuntimeFileStore} over `IAppData`, the storage uploaded fonts and the
 * theming audit log already use.
 *
 * App data folders are flat, so each directory of a name becomes one folder:
 * `css/tokens/dark/example.css` is file `example.css` in folder
 * `css-tokens-dark`. The mapping is fixed by {@see RuntimeFileNames}, so two
 * names can never share a folder and file by accident.
 *
 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
 */
class AppDataRuntimeFileStore implements RuntimeFileStore {

	/**
	 * Constructor.
	 *
	 * @param IAppData $appData Nextcloud's app data for thematiq.
	 * @param RuntimeFileNames $names The name policy.
	 *
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	public function __construct(
		private readonly IAppData $appData,
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
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	public function read(string $name): ?string {
		$this->names->assertAllowed(name: $name);
		[$directory, $file] = $this->names->split(name: $name);
		try {
			return $this->appData->getFolder($this->folderName(directory: $directory))->getFile($file)->getContent();
		} catch (NotFoundException|NotPermittedException $e) {
			return null;
		}
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
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	public function write(string $name, string $content): void {
		$this->names->assertAllowed(name: $name);
		[$directory, $file] = $this->names->split(name: $name);
		try {
			$folder = $this->folder(directory: $directory);
			if ($folder->fileExists($file) === true) {
				$folder->getFile($file)->putContent($content);
				return;
			}

			$folder->newFile($file, $content);
		} catch (Throwable $e) {
			throw new RuntimeException('Could not store runtime file ' . $name . ': ' . $e->getMessage(), 0, $e);
		}
	}//end write()

	/**
	 * Whether the store holds a file.
	 *
	 * @param string $name An allowed name.
	 *
	 * @return bool True when it does.
	 *
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	public function exists(string $name): bool {
		$this->names->assertAllowed(name: $name);
		[$directory, $file] = $this->names->split(name: $name);
		try {
			return $this->appData->getFolder($this->folderName(directory: $directory))->fileExists($file);
		} catch (NotFoundException $e) {
			return false;
		}
	}//end exists()

	/**
	 * Delete a file; a file the store does not hold is not an error.
	 *
	 * @param string $name An allowed name.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	public function delete(string $name): void {
		$this->names->assertAllowed(name: $name);
		[$directory, $file] = $this->names->split(name: $name);
		try {
			$this->appData->getFolder($this->folderName(directory: $directory))->getFile($file)->delete();
		} catch (NotFoundException $e) {
			return;
		}
	}//end delete()

	/**
	 * The names the store holds in one directory, sorted.
	 *
	 * @param string $directory An app-relative directory such as `css/tokens`.
	 *
	 * @return array<int, string> Full names, such as `css/tokens/example.css`.
	 *
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	public function listDirectory(string $directory): array {
		$directory = trim($directory, '/');
		try {
			$files = $this->appData->getFolder($this->folderName(directory: $directory))->getDirectoryListing();
		} catch (NotFoundException $e) {
			return [];
		}

		$names = [];
		foreach ($files as $file) {
			$name = $directory . '/' . $file->getName();
			if ($this->names->isAllowed(name: $name) === true) {
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
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	public function revision(string $name): string {
		$this->names->assertAllowed(name: $name);
		[$directory, $file] = $this->names->split(name: $name);
		try {
			$simple = $this->appData->getFolder($this->folderName(directory: $directory))->getFile($file);
		} catch (NotFoundException $e) {
			return '';
		}

		return substr(sha1($simple->getETag() . ':' . $simple->getMTime() . ':' . $simple->getSize()), 0, 12);
	}//end revision()

	/**
	 * The app data folder name for a directory.
	 *
	 * @param string $directory An app-relative directory such as `css/tokens/dark`.
	 *
	 * @return string The flat folder name, such as `css-tokens-dark`.
	 */
	private function folderName(string $directory): string {
		return str_replace('/', '-', trim($directory, '/'));
	}//end folderName()

	/**
	 * The app data folder for a directory, created when absent.
	 *
	 * @param string $directory An app-relative directory.
	 *
	 * @return ISimpleFolder The folder.
	 */
	private function folder(string $directory): ISimpleFolder {
		$name = $this->folderName(directory: $directory);
		try {
			return $this->appData->getFolder($name);
		} catch (NotFoundException $e) {
			return $this->appData->newFolder($name);
		}
	}//end folder()
}//end class
