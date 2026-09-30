<?php

/**
 * Thematiq Gallery Controller.
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
 * @spec openspec/specs/theme-gallery/spec.md
 */

declare(strict_types=1);
namespace OCA\Thematiq\Controller;

use OCA\Thematiq\Service\Exception\GalleryException;
use OCA\Thematiq\Service\GalleryInstallService;
use OCA\Thematiq\Service\ThemeGalleryService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Browse the theme gallery, turn it on or off, and install an entry. Every endpoint is
 * admin-only; Nextcloud's middleware answers 403 to anyone else.
 *
 * @spec openspec/specs/theme-gallery/spec.md
 */
class GalleryController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string                $appName   The app name.
	 * @param IRequest              $request   The request.
	 * @param ThemeGalleryService   $gallery   The index.
	 * @param GalleryInstallService $installer The installer.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private ThemeGalleryService $gallery,
		private GalleryInstallService $installer,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * What the Gallery block shows. Makes no outbound request while the gallery is off.
	 *
	 * @return JSONResponse `{enabled, host, reachable, entries}`.
	 *
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-an-administrator-browses-the-gallery
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function index(): JSONResponse {
		return new JSONResponse($this->gallery->browse());
	}//end index()

	/**
	 * Turn the gallery on or off, then answer as index() does.
	 *
	 * @return JSONResponse `{enabled, host, reachable, entries}`.
	 *
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-the-gallery-is-opt-in-and-disclosed
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function setEnabled(): JSONResponse {
		$enabled = $this->request->getParam('enabled');
		$this->gallery->setEnabled(enabled: ($enabled === true || $enabled === 'true' || $enabled === '1' || $enabled === 1));

		return new JSONResponse($this->gallery->browse());
	}//end setEnabled()

	/**
	 * Install or update one gallery entry.
	 *
	 * @param string $id The gallery entry id.
	 *
	 * @return JSONResponse `{id, updated, imported, warnings}`, or `{error}` with 404, 422 or 502.
	 *
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-installing-is-checked-and-goes-through-the-upload-path
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function install(string $id): JSONResponse {
		try {
			return new JSONResponse($this->installer->install(galleryId: $id));
		} catch (GalleryException $e) {
			return new JSONResponse(['error' => $e->getMessage()], $e->getCode());
		}
	}//end install()
}//end class
