<?php

/**
 * The collaborators the brand per app endpoints read and change brands through.
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
 * @spec openspec/specs/token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The brand per app, the per-app theming guard and the token set list a brand may name.
 *
 * @spec openspec/specs/token-sets/spec.md
 */
final class AppBrandSources {

	/**
	 * Constructor.
	 *
	 * @param AppBrandService $brands The brands.
	 * @param AppThemingService $appTheming The apps that can be themed.
	 * @param TokenSetService $tokenSets The selectable sets.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	public function __construct(
		public readonly AppBrandService $brands,
		public readonly AppThemingService $appTheming,
		public readonly TokenSetService $tokenSets,
	) {
	}//end __construct()
}//end class
