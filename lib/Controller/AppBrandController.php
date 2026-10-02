<?php

/**
 * Thematiq controller: brand per app.
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
 * @spec openspec/specs/per-app-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\Service\AppBrandService;
use OCA\Thematiq\Service\AppThemingService;
use OCA\Thematiq\Service\Exception\AppBrandException;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IL10N;
use OCP\IRequest;

/**
 * The Brand per app block (`/settings/app-brands`, admin) and the logos
 * (`/api/app-brands/{appId}/logo/{size}`, signed-in users, since branded
 * pages are signed-in pages).
 *
 * @spec openspec/specs/per-app-theming/spec.md
 */
class AppBrandController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string            $appName    The app name.
	 * @param IRequest          $request    The request.
	 * @param AppBrandService   $brands     The brands.
	 * @param AppThemingService $appTheming The apps that can be themed.
	 * @param TokenSetService   $tokenSets  The selectable sets.
	 * @param IL10N             $l10n       Translations.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly AppBrandService $brands,
		private readonly AppThemingService $appTheming,
		private readonly TokenSetService $tokenSets,
		private readonly IL10N $l10n,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * The brands, the apps that can carry one, and the sets to choose from.
	 *
	 * @return JSONResponse `{brands, apps, tokenSets}`.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function index(): JSONResponse {
		$apps = array_values(array_filter($this->appTheming->getThemableApps(), static fn (array $app): bool => $app['themed'] === true));

		return new JSONResponse(
			[
				'brands' => (object)$this->brands->getBrands(),
				'apps' => $apps,
				'tokenSets' => $this->tokenSets->getPublicCatalogue(),
			]
		);
	}//end index()

	/**
	 * Map an app to a token set.
	 *
	 * @param string $appId The app id.
	 * @param string $tokenSet The token set id.
	 *
	 * @return JSONResponse The brand, or 422 with the reason.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function save(string $appId, string $tokenSet = ''): JSONResponse {
		try {
			return new JSONResponse($this->brands->setBrand(appId: $appId, tokenSet: $tokenSet));
		} catch (AppBrandException $e) {
			return $this->refused(exception: $e);
		}
	}//end save()

	/**
	 * Remove an app's brand.
	 *
	 * @param string $appId The app id.
	 *
	 * @return JSONResponse `{removed: true}`.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function remove(string $appId): JSONResponse {
		$this->brands->removeBrand(appId: $appId);

		return new JSONResponse(['removed' => true]);
	}//end remove()

	/**
	 * Upload the large or small logo of a branded app.
	 *
	 * @param string $appId The app id.
	 * @param string $size `large` or `small`.
	 *
	 * @return JSONResponse The brand, or 400, 404, 413 or 422 with the reason.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function uploadLogo(string $appId, string $size): JSONResponse {
		$file = $this->request->getUploadedFile('file');
		if (empty($file) === true || isset($file['tmp_name']) === false || ($file['error'] ?? 0) !== 0) {
			return new JSONResponse(['error' => $this->l10n->t('No file uploaded.')], Http::STATUS_BAD_REQUEST);
		}

		try {
			return new JSONResponse(
				$this->brands->storeLogo(appId: $appId, size: $size, bytes: (string)file_get_contents($file['tmp_name']))
			);
		} catch (AppBrandException $e) {
			return $this->refused(exception: $e);
		}
	}//end uploadLogo()

	/**
	 * A brand logo, for signed-in users.
	 *
	 * @param string $appId The app id.
	 * @param string $size `large` or `small`.
	 *
	 * @return Response The image, or 404.
	 *
	 * @no-admin-idor-exempt an instance-wide brand image of an app, the same for every signed-in user; no per-user object
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function logo(string $appId, string $size): Response {
		$logo = $this->brands->readLogo(appId: $appId, size: $size);
		if ($logo === null) {
			return new Response(Http::STATUS_NOT_FOUND);
		}

		$response = new DataDisplayResponse($logo['bytes'], Http::STATUS_OK, ['Content-Type' => $logo['mime']]);
		$response->addHeader('X-Content-Type-Options', 'nosniff');
		$response->addHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src data:");
		$response->addHeader('Cache-Control', 'private, max-age=86400');

		return $response;
	}//end logo()

	/**
	 * The response for a refused brand or logo.
	 *
	 * @param AppBrandException $exception The refusal.
	 *
	 * @return JSONResponse The error with its status.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	private function refused(AppBrandException $exception): JSONResponse {
		$messages = [
			AppBrandException::PROTECTED => $this->l10n->t('The settings pages always follow the house style. This app cannot get its own brand.'),
			AppBrandException::NOT_INSTALLED => $this->l10n->t('This app is not installed.'),
			AppBrandException::EXCLUDED => $this->l10n->t('This app is excluded from theming. Include it under Theming per app first.'),
			AppBrandException::UNKNOWN_SET => $this->l10n->t('This token set is not available.'),
			AppBrandException::BAD_IMAGE => $this->l10n->t('Upload a PNG, JPEG or WebP image, or an SVG without script.'),
			AppBrandException::TOO_LARGE => $this->l10n->t('The logo is larger than 1 MB.'),
		];

		return new JSONResponse(
			['error' => ($messages[$exception->reason] ?? $exception->reason), 'reason' => $exception->reason],
			$exception->getCode()
		);
	}//end refused()
}//end class
