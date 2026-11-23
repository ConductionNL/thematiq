<?php

/**
 * Contract of ShippedTokenSetAuditService
 *
 * The public surface of ShippedTokenSetAuditService that other components call, so they depend
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
 * Contract of ShippedTokenSetAuditService.
 *
 * @category Kernel
 * @package  OCA\Thematiq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/thematiq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface ShippedTokenSetAuditServiceInterface {

	/**
	 * Compute the audit verdict for a single token set at the given level.
	 *
	 * @param string $appPath The app root path.
	 * @param string $id The token set id.
	 * @param array<string, mixed> $theming The set's theming block.
	 * @param string $level The WCAG threshold profile: 'AA' (default) or 'AAA' (7:1 / 4.5:1).
	 *
	 * The per-set audit result.
	 *
	 * @return array{
	 *     id: string,
	 *     textRatio: float|null,
	 *     uiRatio: float|null,
	 *     textThreshold: float,
	 *     uiThreshold: float,
	 *     verdict: string,
	 *     pairs?: array<int, array<string, mixed>>,
	 *     denhaag?: array<string, array<string, mixed>>
	 * }
	 *
	 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-automated-contrast-audit-over-all-shipped-token-sets
	 * @spec openspec/specs/token-set-contrast-audit/spec.md
	 */
	public function auditSet(string $appPath, string $id, array $theming, string $level = 'AA'): array;
}//end interface
