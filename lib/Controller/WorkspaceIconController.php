<?php

/**
 * Upload, remove and serve the workspace's mobile logo and touch icon.
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
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\Service\Exception\AppBrandException;
use OCA\Thematiq\Service\WorkspaceIconService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IL10N;
use OCP\IRequest;

/**
 * The two workspace images.
 *
 * @spec openspec/changes/mobile-logo-and-touch-icon/specs/mobile-logo-and-touch-icon/spec.md#requirement-an-administrator-sets-a-mobile-logo-and-a-touch-icon
 */
class WorkspaceIconController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param WorkspaceIconService $icons The stored images.
	 * @param IL10N $l10n The messages.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly WorkspaceIconService $icons,
		private readonly IL10N $l10n,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * Which images are set.
	 *
	 * @return JSONResponse Kind to address, or null.
	 *
	 * @spec openspec/changes/mobile-logo-and-touch-icon/specs/mobile-logo-and-touch-icon/spec.md#requirement-an-administrator-sets-a-mobile-logo-and-a-touch-icon
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function index(): JSONResponse {
		return new JSONResponse($this->icons->status());
	}//end index()

	/**
	 * Store an uploaded image.
	 *
	 * @param string $kind `mobile-logo` or `touch-icon`.
	 *
	 * @return JSONResponse What was stored, or 400, 404, 413 or 422 with the reason.
	 *
	 * @spec openspec/changes/mobile-logo-and-touch-icon/specs/mobile-logo-and-touch-icon/spec.md#requirement-an-administrator-sets-a-mobile-logo-and-a-touch-icon
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function upload(string $kind): JSONResponse {
		$file = $this->request->getUploadedFile('file');
		if (empty($file) === true || isset($file['tmp_name']) === false || ($file['error'] ?? 0) !== 0) {
			return new JSONResponse(['error' => $this->l10n->t('No file uploaded.')], Http::STATUS_BAD_REQUEST);
		}

		try {
			return new JSONResponse($this->icons->store(kind: $kind, bytes: (string)file_get_contents($file['tmp_name'])));
		} catch (AppBrandException $e) {
			return $this->refused(exception: $e, kind: $kind);
		}
	}//end upload()

	/**
	 * Remove an image, so Nextcloud's own takes its place again.
	 *
	 * @param string $kind `mobile-logo` or `touch-icon`.
	 *
	 * @return JSONResponse `{removed: kind}`, or 404.
	 *
	 * @spec openspec/changes/mobile-logo-and-touch-icon/specs/mobile-logo-and-touch-icon/spec.md#requirement-an-administrator-sets-a-mobile-logo-and-a-touch-icon
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function remove(string $kind): JSONResponse {
		try {
			$this->icons->remove(kind: $kind);
		} catch (AppBrandException $e) {
			return $this->refused(exception: $e, kind: $kind);
		}

		return new JSONResponse(['removed' => $kind]);
	}//end remove()

	/**
	 * Serve an image to anyone: a phone fetches the touch icon without a session, and the
	 * login page draws the header too. Instance-wide brand images, the same for every visitor.
	 *
	 * @param string $kind `mobile-logo` or `touch-icon`.
	 *
	 * @return Response The image, or 404.
	 *
	 * @no-admin-idor-exempt an instance-wide brand image, the same for every visitor; no per-user object
	 *
	 * @spec openspec/changes/mobile-logo-and-touch-icon/specs/mobile-logo-and-touch-icon/spec.md#requirement-the-mobile-logo-and-the-touch-icon-reach-every-page
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	public function show(string $kind): Response {
		$image = $this->icons->read(kind: $kind);
		if ($image === null) {
			return new Response(Http::STATUS_NOT_FOUND);
		}

		$response = new DataDisplayResponse($image['bytes'], Http::STATUS_OK, ['Content-Type' => $image['mime']]);
		$response->addHeader('X-Content-Type-Options', 'nosniff');
		$response->addHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src data:");
		$response->addHeader('Cache-Control', 'public, max-age=86400');

		return $response;
	}//end show()

	/**
	 * The response for a refused image.
	 *
	 * @param AppBrandException $exception The refusal.
	 * @param string $kind The kind asked for.
	 *
	 * @return JSONResponse The error with its status.
	 */
	private function refused(AppBrandException $exception, string $kind): JSONResponse {
		$messages = [
			AppBrandException::BAD_IMAGE => $this->l10n->t('Upload a PNG, JPEG or WebP image, or an SVG without script.'),
			AppBrandException::TOO_LARGE => $this->l10n->t('The image is larger than 1 MB.'),
			'unknown_kind' => $this->l10n->t('This kind of image is not kept.'),
		];
		if ($kind === 'touch-icon' && $exception->reason === AppBrandException::BAD_IMAGE) {
			$messages[AppBrandException::BAD_IMAGE] = $this->l10n->t('Upload a square PNG of at least 120 by 120 pixels; 180 by 180 is best.');
		}

		return new JSONResponse(
			['error' => ($messages[$exception->reason] ?? $exception->reason), 'reason' => $exception->reason],
			$exception->getCode()
		);
	}//end refused()
}//end class
