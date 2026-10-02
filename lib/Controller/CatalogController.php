<?php

/**
 * NL Design Catalogue Controller.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Controller
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/app-token-set-selection/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\Service\TokenDeprecationService;
use OCA\Thematiq\Service\TokenSetService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Non-admin, read-only token-set catalogue endpoint for a leaf app's own
 * picker (e.g. a builder tool inside another Conduction app).
 *
 * `#[NoAdminRequired]` — authenticated non-admin user, NOT `#[PublicPage]`:
 * the only known consumer always runs inside an authenticated Nextcloud
 * session, and exposing admin-uploaded custom sets to anonymous internet
 * traffic would be a new information-disclosure surface with no consumer
 * need (design.md decision 1). Deliberately outside the `/settings/*` URL
 * prefix this app reserves, by convention, for admin-gated routes.
 *
 * @spec openspec/specs/app-token-set-selection/spec.md
 */
class CatalogController extends Controller {

	/**
	 * The token set discovery/projection service.
	 *
	 * @var TokenSetService
	 */
	private TokenSetService $tokenSetService;

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request object.
	 * @param TokenSetService $tokenSetService The token set discovery/projection service.
	 * @param TokenDeprecationService|null $deprecations The token deprecations consuming apps read.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		TokenSetService $tokenSetService,
		private ?TokenDeprecationService $deprecations = null,
	) {
		parent::__construct(appName: $appName, request: $request);
		$this->tokenSetService = $tokenSetService;
	}//end __construct()

	/**
	 * GET /api/token-sets — the closed, non-admin catalogue projection.
	 *
	 * Reuses `TokenSetService::getAvailableTokenSets()` for discovery via
	 * `getPublicCatalogue()` (zero duplication of the scan/manifest-merge
	 * logic) and returns exactly the 5-field shape: `id`, `name`,
	 * `design_system`, `theming` (`primary_color`, `background_color`,
	 * `logo?`), `wcagLevel`. No `description`, `custom`, `warnings`,
	 * `upstreamVersion`, or `upstreamRef` — those are admin-only fields on
	 * the existing `GET /settings/tokensets` response.
	 *
	 * @return JSONResponse `{ tokenSets: [...] }`.
	 *
	 * @spec openspec/specs/app-token-set-selection/spec.md
	 */
	#[NoAdminRequired]
	public function tokenSets(): JSONResponse {
		return new JSONResponse(['tokenSets' => $this->tokenSetService->getPublicCatalogue()]);
	}//end tokenSets()

	/**
	 * GET /api/token-deprecations — every token deprecation, for consuming apps.
	 *
	 * Token names and dates only, the same for every user, so there is no
	 * per-object check to make; a request without a session is refused by
	 * the absence of `#[PublicPage]`.
	 *
	 * @return JSONResponse `{ deprecations: [{token, severity, replacement, removalDate, message, deprecatedAt, due, state}] }`.
	 *
	 * @spec openspec/specs/token-deprecations/spec.md#requirement-consuming-apps-can-read-every-deprecation
	 */
	#[NoAdminRequired]
	public function deprecations(): JSONResponse {
		return new JSONResponse(['deprecations' => ($this->deprecations?->publicList() ?? [])]);
	}//end deprecations()
}//end class
