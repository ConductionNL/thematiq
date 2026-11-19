<?php

/**
 * Contract of TokenReferenceService
 *
 * The public surface of TokenReferenceService that other components call, so they depend
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
 * Contract of TokenReferenceService.
 *
 * @category Kernel
 * @package  OCA\Thematiq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/thematiq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface TokenReferenceServiceInterface {

	/**
	 * Render the reference of one set.
	 *
	 * @param string $appPath The app root.
	 * @param array<string, mixed> $set The set: `id`, `name`, `theming`.
	 * @param string $format `md`, `md-plain` or `html`. `md-plain` is Markdown without swatch images:
	 *                       the docs site renders them, but a downloaded file is read as text, where
	 *                       each one is a long data URL.
	 *
	 * @return string The reference.
	 *
	 * @spec openspec/specs/token-reference/spec.md#requirement-the-docs-site-has-a-reference-page-per-shipped-set
	 * @spec openspec/specs/token-reference/spec.md#requirement-the-reference-does-not-rely-on-colour-alone
	 */
	public function render(string $appPath, array $set, string $format): string;
}//end interface
