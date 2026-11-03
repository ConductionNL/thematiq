<?php

/**
 * The collaborators that put the theme's stylesheets and marker on a page.
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
 * @spec openspec/specs/css-architecture/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The CSS injection, the per-app theming guard and the environment marker the theme listener runs on every page.
 *
 * @spec openspec/specs/css-architecture/spec.md
 */
final class ThemeInjectionStyles {

	/**
	 * Constructor.
	 *
	 * @param CssInjectionService $cssInjectionService The CSS injection service.
	 * @param AppThemingService $appThemingService The per-app theming guard resolver.
	 * @param EnvironmentMarkerService $environmentMarker The environment marker.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 */
	public function __construct(
		public readonly CssInjectionService $cssInjectionService,
		public readonly AppThemingService $appThemingService,
		public readonly EnvironmentMarkerService $environmentMarker,
	) {
	}//end __construct()
}//end class
