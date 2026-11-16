<?php

/**
 * Contract of ComplianceReportService
 *
 * The public surface of ComplianceReportService that other components call, so they depend
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
 * Contract of ComplianceReportService.
 *
 * @category Kernel
 * @package  OCA\Thematiq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/thematiq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface ComplianceReportServiceInterface {

	/**
	 * Render the report as pretty-printed, deterministic JSON.
	 *
	 * @return string The JSON document (trailing newline included).
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	public function renderJson(): string;

	/**
	 * Render the report as deterministic, human-readable Markdown.
	 *
	 * @return string The Markdown document (trailing newline included).
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	public function renderMarkdown(): string;
}//end interface
