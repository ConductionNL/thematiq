<?php

/**
 * Contract of ThemeGalleryService
 *
 * The public surface of ThemeGalleryService that other components call, so they depend
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
 * Contract of ThemeGalleryService.
 *
 * @category Kernel
 * @package  OCA\Thematiq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/thematiq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface ThemeGalleryServiceInterface {

	/**
	 * What the Gallery block shows. Makes no request while the gallery is off.
	 *
	 * @return array{enabled: bool, host: string, reachable: bool|null, entries: array<int, array<string, mixed>>}
	 *
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-an-administrator-browses-the-gallery
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-updates-are-offered-never-applied
	 */
	public function browse(): array;

	/**
	 * Turn the gallery on or off.
	 *
	 * @param boolean $enabled The new state.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-the-gallery-is-opt-in-and-disclosed
	 */
	public function setEnabled(bool $enabled): void;
}//end interface
