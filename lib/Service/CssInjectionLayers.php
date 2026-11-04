<?php

/**
 * The layers the stylesheet cascade adds after the token set.
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
 * The custom CSS, the custom fonts, the theme-preview banner, and the set and brand of the request.
 *
 * @spec openspec/specs/css-architecture/spec.md
 */
final class CssInjectionLayers {

	/**
	 * Constructor.
	 *
	 * @param CustomCssService $customCssService The freeform custom CSS service.
	 * @param FontService $fontService The custom font resolver.
	 * @param ThemePreviewBannerService $previewBannerService The theme-preview banner injector.
	 * @param CssInjectionBrands $brands Which token set and brand a request is themed with.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 */
	public function __construct(
		public readonly CustomCssService $customCssService,
		public readonly FontService $fontService,
		public readonly ThemePreviewBannerService $previewBannerService,
		public readonly CssInjectionBrands $brands,
	) {
	}//end __construct()
}//end class
