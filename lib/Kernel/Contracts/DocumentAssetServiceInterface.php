<?php

/**
 * Contract of DocumentAssetService
 *
 * The public surface of DocumentAssetService that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category  Kernel
 * @package   OCA\Thematiq\Kernel\Contracts
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT: <git_id>
 * @link      https://github.com/ConductionNL/thematiq
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Thematiq\Kernel\Contracts;

use RuntimeException;

/**
 * Contract of DocumentAssetService.
 *
 * @category Kernel
 * @package  OCA\Thematiq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/thematiq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface DocumentAssetServiceInterface {

	/**
	 * Remove an asset.
	 *
	 * @param string $kind `logo` or `cover`.
	 *
	 * @return void
	 *
	 * @throws RuntimeException Code 404 for an unknown kind.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	public function delete(string $kind): void;

	/**
	 * The metadata of the stored assets.
	 *
	 * @return array<string, array{mime: string, size: int, uploadedAt: int}> Metadata by kind.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	public function getAssets(): array;

	/**
	 * The extra footer line.
	 *
	 * @return string The line, empty when unset.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	public function getFooterLine(): string;

	/**
	 * Read an asset.
	 *
	 * @param string $kind `logo` or `cover`.
	 *
	 * @return array{bytes: string, mime: string}|null The file, or null when there is none.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	public function read(string $kind): ?array;

	/**
	 * Set the extra footer line. Stored as text; every reader escapes it.
	 *
	 * @param string $line The line.
	 *
	 * @return string The stored line.
	 *
	 * @throws RuntimeException Code 422 when the line is too long or holds a line break.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	public function setFooterLine(string $line): string;

	/**
	 * Validate and store an asset, replacing the previous one of its kind.
	 *
	 * @param string $kind `logo` or `cover`.
	 * @param string $bytes The uploaded file.
	 *
	 * @return array{mime: string, size: int, uploadedAt: int} The stored metadata.
	 *
	 * @throws RuntimeException Code 404 for an unknown kind, 413 when too large, 422 for a refused file.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	public function store(string $kind, string $bytes): array;
}//end interface
