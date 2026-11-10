<?php

/**
 * The two contrast ratios the audit measures for a set, and the thresholds they are held to.
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
 * The primary/text and primary/background ratios of one set, each null when
 * the pair could not be measured, and the text and UI thresholds of the WCAG
 * level the set is held to.
 *
 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-automated-contrast-audit-over-all-shipped-token-sets
 */
final class ContrastMeasurement {

	/**
	 * Constructor.
	 *
	 * @param float|null $textRatio The primary/text ratio.
	 * @param float|null $uiRatio The primary/background ratio.
	 * @param float $textThreshold The applicable text threshold.
	 * @param float $uiThreshold The applicable UI threshold.
	 */
	public function __construct(
		public readonly ?float $textRatio,
		public readonly ?float $uiRatio,
		public readonly float $textThreshold,
		public readonly float $uiThreshold,
	) {
	}//end __construct()

	/**
	 * The verdict: `unevaluated` when a pair could not be measured, `pass` when both meet their threshold, else `fail`.
	 *
	 * @return string One of `pass`, `fail`, `unevaluated`.
	 *
	 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-automated-contrast-audit-over-all-shipped-token-sets
	 */
	public function verdict(): string {
		if ($this->textRatio === null || $this->uiRatio === null) {
			return 'unevaluated';
		}

		if ($this->textRatio >= $this->textThreshold && $this->uiRatio >= $this->uiThreshold) {
			return 'pass';
		}

		return 'fail';
	}//end verdict()
}//end class
