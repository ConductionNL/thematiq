<?php

/**
 * The two contrast ratios the audit measures for a set.
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
 * the pair could not be measured.
 *
 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-automated-contrast-audit-over-all-shipped-token-sets
 */
final class PairRatios {

	/**
	 * Constructor.
	 *
	 * @param float|null $text The primary/text ratio.
	 * @param float|null $ui The primary/background ratio.
	 */
	public function __construct(
		public readonly ?float $text,
		public readonly ?float $ui,
	) {
	}//end __construct()
}//end class
