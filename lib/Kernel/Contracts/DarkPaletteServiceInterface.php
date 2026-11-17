<?php

/**
 * Contract of DarkPaletteService
 *
 * The public surface of DarkPaletteService that other components call, so they depend
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
 * Contract of DarkPaletteService.
 *
 * @category Kernel
 * @package  OCA\Thematiq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/thematiq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface DarkPaletteServiceInterface {

	/**
	 * Discover every token set id on disk (shipped and custom), mirroring
	 * {@see TokenSetService::getAvailableTokenSets()}'s filesystem scan.
	 *
	 * @return string[] The discovered token set ids, alphabetically sorted.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	public function discoverAllSetIds(): array;

	/**
	 * Generate (or skip) every discovered token set's dark variant.
	 *
	 * @param bool $force Regenerate even fresh files.
	 *
	 * The per-set outcome, keyed by set id.
	 *
	 * @return array<string, array{written: bool, skipped: bool, reason: string, warnings: array<int, array<string, mixed>>}>
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag) - see generateAndWrite()'s rationale; this
	 * simply fans the same `--force` convention out over every discovered set.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	public function generateAll(bool $force = false): array;

	/**
	 * Generate and write (or skip) the dark variant for one token set,
	 * handling freshness (source-hash comparison), directory creation, and
	 * write failures — all degrading to a logged warning, never an
	 * exception (theming is presentation, never allowed to fatal the app).
	 *
	 * @param string $setId The token set id.
	 * @param bool $force Regenerate even when the existing file is fresh.
	 *
	 * @return array{written: bool, skipped: bool, reason: string, warnings: array<int, array<string, mixed>>}
	 *                                                                                                         The outcome.
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag) - `$force` mirrors the standard CLI
	 * `--force` convention (also `occ thematiq:generate-dark-variants --force`); a
	 * skip-if-fresh/force-regenerate toggle on one write path, not two responsibilities.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	public function generateAndWrite(string $setId, bool $force = false): array;
}//end interface
