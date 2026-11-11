<?php

/**
 * Which of a Tokens Studio theme's sets to merge: the theme and the set statuses that count.
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
 * @spec openspec/specs/multi-brand-token-sources/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * A theme and the statuses (`enabled`, `source`) whose sets are merged for it.
 *
 * @spec openspec/specs/multi-brand-token-sources/spec.md
 */
final class ThemeSets {

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $theme The theme.
	 * @param array<int, string> $statuses The statuses to include.
	 */
	public function __construct(
		public readonly array $theme,
		public readonly array $statuses,
	) {
	}//end __construct()
}//end class
