<?php

/**
 * How a contrast ratio and a threshold are written in the Markdown reports.
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
 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-reproducible-contrast-report
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The cells the contrast reports share: a ratio with two decimals, a threshold
 * with one, an em dash for a pair that could not be measured.
 *
 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-reproducible-contrast-report
 */
final class ContrastReportCells {

	/**
	 * A ratio as a report cell.
	 *
	 * @param float|null $ratio The ratio, or null when unevaluated.
	 *
	 * @return string The cell text.
	 *
	 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-reproducible-contrast-report
	 */
	public static function ratio(?float $ratio): string {
		if ($ratio === null) {
			return '—';
		}

		return number_format($ratio, 2, '.', '') . ':1';
	}//end ratio()

	/**
	 * A threshold as a report cell.
	 *
	 * @param float $threshold The threshold.
	 *
	 * @return string The cell text.
	 *
	 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-reproducible-contrast-report
	 */
	public static function threshold(float $threshold): string {
		return number_format($threshold, 1, '.', '') . ':1';
	}//end threshold()
}//end class
