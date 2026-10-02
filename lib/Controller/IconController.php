<?php

/**
 * Serves the active icon pack's icon by name.
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
 * @spec openspec/specs/icon-packs/spec.md#requirement-icon-path-resolver-by-name
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\GroupThemingService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IRequest;
use OCP\IURLGenerator;

/**
 * GET /icons/{name}: the icon of that name in the pack the request's theme uses.
 *
 * DesignSystemService::resolveIconPath() picked the icon from the active pack,
 * and nothing called it, so choosing a design system changed no icon (#663).
 * This route is its caller: another app, or the theme's own CSS, asks for an
 * icon by name and gets the active pack's file. The token set follows the
 * request (preview, then group mapping, then the instance default), as the
 * theme itself does.
 *
 * Public, like the font routes: an `<img>` or CSS `url()` load carries no
 * CSRF token and must also work on the login page. It reveals only which
 * bundled icon file the active theme serves.
 *
 * @spec openspec/specs/icon-packs/spec.md#requirement-icon-path-resolver-by-name
 */
class IconController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest            $request             The request.
	 * @param DesignSystemService $designSystemService Resolves an icon name in the active pack.
	 * @param GroupThemingService $groupTheming        Resolves the request's token set.
	 * @param IURLGenerator       $urlGenerator        Builds the icon's static file URL.
	 *
	 * @return void
	 */
	public function __construct(
		IRequest $request,
		private readonly DesignSystemService $designSystemService,
		private readonly GroupThemingService $groupTheming,
		private readonly IURLGenerator $urlGenerator,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Redirect to the active pack's icon of this name, or answer 404.
	 *
	 * @param string $name The icon name, without `.svg`.
	 *
	 * @return RedirectResponse|NotFoundResponse The icon's static URL, or 404 when no active pack has it.
	 *
	 * @spec openspec/specs/icon-packs/spec.md#requirement-icon-path-resolver-by-name
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[AnonRateLimit(limit: 600, period: 60)]
	public function show(string $name): RedirectResponse|NotFoundResponse {
		$path = $this->designSystemService->resolveIconPath(
			name: $name,
			tokenSetId: $this->groupTheming->resolveTokenSetForRequest()
		);
		if ($path === null) {
			return new NotFoundResponse();
		}

		return new RedirectResponse($this->urlGenerator->imagePath(appName: Application::APP_ID, file: $path));
	}//end show()
}//end class
