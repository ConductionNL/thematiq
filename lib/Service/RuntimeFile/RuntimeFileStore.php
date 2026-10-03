<?php

/**
 * Where thematiq keeps the files it writes at runtime.
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
 * Storage for runtime files, addressed by {@see RuntimeFileNames} names.
 *
 * The production implementation is {@see AppDataRuntimeFileStore}, which keeps
 * them in Nextcloud's app data for thematiq. Nothing that implements this may
 * write inside the app directory: that directory is signed, and every file
 * added or changed there is a code integrity warning in the admin overview.
 *
 * @spec openspec/specs/runtime-file-storage/spec.md
 */
interface RuntimeFileStore {

	/**
	 * Read a file.
	 *
	 * @param string $name An allowed name.
	 *
	 * @return string|null The content, or null when the store holds no such file.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function read(string $name): ?string;

	/**
	 * Write a file, replacing any earlier content.
	 *
	 * @param string $name An allowed name.
	 * @param string $content The content.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When the file cannot be written.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function write(string $name, string $content): void;

	/**
	 * Whether the store holds a file.
	 *
	 * @param string $name An allowed name.
	 *
	 * @return bool True when it does.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function exists(string $name): bool;

	/**
	 * Delete a file. Deleting a file the store does not hold is not an error.
	 *
	 * @param string $name An allowed name.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function delete(string $name): void;

	/**
	 * The names the store holds in one directory, sorted.
	 *
	 * @param string $directory An app-relative directory such as `css/tokens`.
	 *
	 * @return array<int, string> Full names, such as `css/tokens/example.css`.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function listDirectory(string $directory): array;

	/**
	 * A short value that changes whenever the file's content does, for cache busting.
	 *
	 * @param string $name An allowed name.
	 *
	 * @return string The revision, or an empty string when the file is absent.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function revision(string $name): string;
}//end interface
