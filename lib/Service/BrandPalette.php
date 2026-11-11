<?php

/**
 * Thematiq Brand Palette.
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
 * What the brand form derives a set from: its two colours and, optionally,
 * the main button's own colour.
 *
 * @spec openspec/specs/simple-brand-form/spec.md#requirement-the-derived-set-is-complete
 */
final class BrandPalette {

	/**
	 * Constructor.
	 *
	 * @param BrandColours $base The primary and background colours.
	 * @param string|null $action The main button's own colour, or null (or '') to leave it with the primary.
	 */
	public function __construct(
		public readonly BrandColours $base,
		public readonly ?string $action = null,
	) {
	}//end __construct()
}//end class
