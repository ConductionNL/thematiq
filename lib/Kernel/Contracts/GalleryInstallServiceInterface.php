<?php

/**
 * Contract of GalleryInstallService
 *
 * The public surface of GalleryInstallService that other components call, so they depend
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

/**
 * Contract of GalleryInstallService.
 *
 * @category Kernel
 * @package  OCA\Thematiq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/thematiq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface GalleryInstallServiceInterface {

	/**
	 * Install or update one gallery entry.
	 *
	 * @param string $galleryId The entry id.
	 *
	 * @return array{id: string, updated: bool, imported: int, warnings: array<int, array<string, mixed>>}
	 *
	 * @throws \OCA\Thematiq\Service\Exception\GalleryException 404 unknown entry, 502 download failed, 422 mismatch or refused by the upload path.
	 *
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-installing-is-checked-and-goes-through-the-upload-path
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-updates-are-offered-never-applied
	 */
	public function install(string $galleryId): array;
}//end interface
