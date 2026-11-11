<?php

/**
 * Thematiq Brand Colours.
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
 * @spec openspec/specs/simple-brand-form/spec.md#requirement-the-derived-set-is-complete
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The two colours the brand form always asks for, as entered.
 *
 * @spec openspec/specs/simple-brand-form/spec.md#requirement-the-derived-set-is-complete
 */
final class BrandColours {

	/**
	 * Constructor.
	 *
	 * @param string $primary The primary colour, `#rgb` or `#rrggbb`.
	 * @param string $background The background colour, `#rgb` or `#rrggbb`.
	 */
	public function __construct(
		public readonly string $primary,
		public readonly string $background,
	) {
	}//end __construct()
}//end class
