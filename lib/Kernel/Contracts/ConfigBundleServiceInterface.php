<?php

/**
 * Contract of ConfigBundleService
 *
 * The public surface of ConfigBundleService that other components call, so they depend
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
 * Contract of ConfigBundleService.
 *
 * @category Kernel
 * @package  OCA\Thematiq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/thematiq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface ConfigBundleServiceInterface {

	/**
	 * Export the complete nldesign configuration as a bundle array.
	 *
	 * @return array<string, mixed> The v1 bundle.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function export(): array;

	/**
	 * Import a bundle: phase 1 validates every section, phase 2 (unless
	 * `$dryRun`) applies them. Any phase-1 failure aborts with zero writes.
	 *
	 * @param array<string, mixed> $bundle The decoded bundle.
	 * @param bool $dryRun When true, validate only — never write.
	 *
	 * @return array<string, mixed> `{valid, dryRun, applied, sections?, errors?}`.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag) - `$dryRun` IS the spec-required distinction
	 * between "validate only" and "validate then apply" (`occ thematiq:config:import --dry-run`);
	 * splitting into two public methods would duplicate phase 1 rather than remove a real flag.
	 */
	public function import(array $bundle, bool $dryRun = false): array;
}//end interface
