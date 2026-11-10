<?php

/**
 * The two contrast thresholds a set is held to.
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
 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-automated-contrast-audit-over-all-shipped-token-sets
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The text and UI thresholds of one WCAG level.
 *
 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-automated-contrast-audit-over-all-shipped-token-sets
 */
final class WcagThresholds {

	/**
	 * Constructor.
	 *
	 * @param float $text The text threshold.
	 * @param float $ui The UI threshold.
	 */
	public function __construct(
		public readonly float $text,
		public readonly float $ui,
	) {
	}//end __construct()

	/**
	 * The thresholds of a level: AAA (7:1 / 4.5:1) or, for anything else, AA (4.5:1 / 3:1).
	 *
	 * @param string $level The WCAG level.
	 *
	 * @return self The thresholds.
	 *
	 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-automated-contrast-audit-over-all-shipped-token-sets
	 */
	public static function fromLevel(string $level): self {
		if ($level === 'AAA') {
			return new self(text: ShippedTokenSetAuditService::AAA_TEXT, ui: ShippedTokenSetAuditService::AAA_UI);
		}

		return new self(text: ShippedTokenSetAuditService::AA_TEXT, ui: ShippedTokenSetAuditService::AA_UI);
	}//end fromLevel()
}//end class
