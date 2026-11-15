<?php

/**
 * Which token set and brand a request is themed with.
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
 * Resolves the token set for a request: by group, and by the brand an app carries.
 *
 * @spec openspec/specs/css-architecture/spec.md
 */
final class CssInjectionBrands {

	/**
	 * Constructor.
	 *
	 * @param GroupThemingService $groupThemingService The per-group token-set resolver.
	 * @param AppBrandService $appBrands The brand per app.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 */
	public function __construct(
		public readonly GroupThemingService $groupThemingService,
		public readonly AppBrandService $appBrands,
	) {
	}//end __construct()

	/**
	 * The token set and the brand logo layer an app's request is themed with.
	 *
	 * @param string|null $appId The app the request renders, if any.
	 * @param string $styleId The style id the logo layer is emitted under.
	 *
	 * @return array{tokenSet: string, logo: array{layer: string, kind: string, css: string, id: string}|null}
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 */
	public function forApp(?string $appId, string $styleId): array {
		$brand = $this->appBrands->brandFor(appId: $appId);
		$tokenSet = $this->groupThemingService->resolveTokenSetForRequest(appBrandSet: ($brand['tokenSet'] ?? null));

		return ['tokenSet' => $tokenSet, 'logo' => $this->appBrands->logoLayer(brand: $brand, tokenSet: $tokenSet, styleId: $styleId)];
	}//end forApp()
}//end class
