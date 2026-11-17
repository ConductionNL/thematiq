<?php

/**
 * Contract of ThemeVersionRestoreService
 *
 * The public surface of ThemeVersionRestoreService that other components call, so they depend
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
 * Contract of ThemeVersionRestoreService.
 *
 * @category Kernel
 * @package  OCA\Thematiq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/thematiq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface ThemeVersionRestoreServiceInterface {

	/**
	 * Show what restoring a version will change, writing nothing.
	 *
	 * @param string $id The version id.
	 *
	 * @return array<string, mixed>|null The dry run and the changes, or null for an unknown id.
	 *
	 * @spec openspec/specs/theme-versions/spec.md
	 */
	public function preview(string $id): ?array;

	/**
	 * Restore a version: dry run, then import, then audit.
	 *
	 * @param string $id The version id.
	 * @param string|null $actor The actor to record instead of the session user, such as `cli`.
	 *
	 * @return array<string, mixed>|null The preview plus `applied`, or null for an unknown id.
	 *
	 * @spec openspec/specs/theme-versions/spec.md
	 */
	public function restore(string $id, ?string $actor = null): ?array;
}//end interface
