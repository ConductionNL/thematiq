<?php

/**
 * Unit tests for CatalogController: auth posture and the closed 5-field
 * response shape.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/app-token-set-selection/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\CatalogController;
use OCA\Thematiq\Service\TokenSetService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Two concerns share this suite (mirroring MetricsControllerTest's pattern):
 *
 * 1. Auth posture — `tokenSets()` MUST carry `#[NoAdminRequired]` (any
 *    authenticated user, admin or not) and MUST NOT carry `#[PublicPage]`
 *    (design.md decision 1: authenticated non-admin, not anonymous).
 * 2. The closed 5-field response contract — no `description`, `custom`,
 *    `warnings`, `upstreamVersion`, or `upstreamRef` leakage from the admin
 *    shape.
 */
class CatalogControllerTest extends TestCase {

	/**
	 * `tokenSets()` MUST carry `#[NoAdminRequired]` — any authenticated
	 * (admin or non-admin) user may read the catalogue.
	 */
	public function testTokenSetsHasNoAdminRequiredAttribute(): void {
		$method = new ReflectionMethod(CatalogController::class, 'tokenSets');

		$this->assertCount(
			expectedCount: 1,
			haystack: $method->getAttributes(NoAdminRequired::class),
			message: '#[NoAdminRequired] must be present so any authenticated user (not just admins) can read the catalogue.'
		);
	}//end testTokenSetsHasNoAdminRequiredAttribute()

	/**
	 * `tokenSets()` MUST NOT carry `#[PublicPage]` — design.md decision 1
	 * deliberately keeps the endpoint inside the instance's authenticated
	 * user base, not open to anonymous internet traffic.
	 */
	public function testTokenSetsHasNoPublicPageAttribute(): void {
		$method = new ReflectionMethod(CatalogController::class, 'tokenSets');

		$this->assertCount(
			expectedCount: 0,
			haystack: $method->getAttributes(PublicPage::class),
			message: '#[PublicPage] must be absent — the catalogue may expose admin-uploaded custom sets and must stay behind authentication.'
		);
	}//end testTokenSetsHasNoPublicPageAttribute()

	/**
	 * The response wraps `TokenSetService::getPublicCatalogue()` verbatim
	 * under a `tokenSets` key — the controller adds no fields, drops none.
	 */
	public function testTokenSetsReturnsServiceProjectionUnderTokenSetsKey(): void {
		$projection = [
			[
				'id' => 'rijkshuisstijl',
				'name' => 'Rijkshuisstijl',
				'design_system' => 'nldesign',
				'theming' => [
					'primary_color' => '#154273',
					'background_color' => '#F5F6F7',
					'logo' => 'img/logos/rijkshuisstijl.svg',
				],
				'wcagLevel' => 'AA',
			],
		];

		$tokenSetService = $this->createMock(originalClassName: TokenSetService::class);
		$tokenSetService->method('getPublicCatalogue')->willReturn($projection);

		$controller = new CatalogController(appName: 'nldesign', request: $this->createMock(originalClassName: IRequest::class), tokenSetService: $tokenSetService);
		$response = $controller->tokenSets();

		$this->assertInstanceOf(expected: JSONResponse::class, actual: $response);
		$this->assertSame(expected: ['tokenSets' => $projection], actual: $response->getData());
	}//end testTokenSetsReturnsServiceProjectionUnderTokenSetsKey()

	/**
	 * Every entry MUST contain exactly the 5 allowlisted keys — no
	 * `description`, `custom`, `warnings`, `upstreamVersion`, or
	 * `upstreamRef` leakage from the admin `TokenSetEntry` shape.
	 */
	public function testEntryShapeIsExactlyTheFiveAllowlistedKeys(): void {
		$projection = [
			[
				'id' => 'nextcloud',
				'name' => 'Nextcloud (default)',
				'design_system' => 'none',
				'theming' => [
					'primary_color' => '#0082c9',
					'background_color' => '#FFFFFF',
				],
				'wcagLevel' => null,
			],
		];

		$tokenSetService = $this->createMock(originalClassName: TokenSetService::class);
		$tokenSetService->method('getPublicCatalogue')->willReturn($projection);

		$controller = new CatalogController(appName: 'nldesign', request: $this->createMock(originalClassName: IRequest::class), tokenSetService: $tokenSetService);
		$entry = $controller->tokenSets()->getData()['tokenSets'][0];

		$this->assertSame(expected: ['id', 'name', 'design_system', 'theming', 'wcagLevel'], actual: array_keys($entry));
		foreach (['description', 'custom', 'warnings', 'upstreamVersion', 'upstreamRef'] as $forbidden) {
			$this->assertArrayNotHasKey(key: $forbidden, array: $entry, message: $forbidden . ' must never leak into the non-admin catalogue entry.');
		}
	}//end testEntryShapeIsExactlyTheFiveAllowlistedKeys()
}//end class
