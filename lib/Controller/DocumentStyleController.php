<?php

/**
 * Thematiq controller: the document house style profile and its assets.
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
 * @spec openspec/specs/document-house-style/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\Service\DocumentAssetService;
use OCA\Thematiq\Service\DocumentStyleService;
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
use OCP\IUserSession;
use RuntimeException;

/**
 * Signed-in users read the profile (`GET /api/document-style`) and its
 * assets; administrators manage the Documents block under `/settings/document-style`.
 *
 * @spec openspec/specs/document-house-style/spec.md
 */
class DocumentStyleController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string               $appName     The app name.
	 * @param IRequest             $request     The request.
	 * @param DocumentStyleService $style       The profile.
	 * @param DocumentAssetService $assets      The document assets.
	 * @param IUserSession         $userSession The session.
	 * @param IL10N                $l10n        Translations.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly DocumentStyleService $style,
		private readonly DocumentAssetService $assets,
		private readonly IUserSession $userSession,
		private readonly IL10N $l10n,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * The profile for the signed-in user. Without a session Nextcloud refuses the request.
	 *
	 * @return JSONResponse The profile.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	#[NoAdminRequired]
	public function show(): JSONResponse {
		return new JSONResponse($this->style->forUser(uid: $this->userSession->getUser()?->getUID()));
	}//end show()

	/**
	 * A document asset, for signed-in users.
	 *
	 * @param string $kind `logo` or `cover`.
	 *
	 * @return Response The image, or 404.
	 *
	 * @no-admin-idor-exempt one of two instance-wide branding images, the same for every signed-in user; the kind is not an object reference
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function asset(string $kind): Response {
		$file = $this->assets->read(kind: $kind);
		if ($file === null) {
			return new Response(Http::STATUS_NOT_FOUND);
		}

		$response = new DataDisplayResponse($file['bytes'], Http::STATUS_OK, ['Content-Type' => $file['mime']]);
		$response->addHeader('X-Content-Type-Options', 'nosniff');
		$response->addHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src data:");
		$response->addHeader('Cache-Control', 'private, max-age=3600');

		return $response;
	}//end asset()

	/**
	 * The Documents block: the footer line, the asset metadata and a preview of the profile.
	 *
	 * @return JSONResponse The settings.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function settings(): JSONResponse {
		return new JSONResponse(
			[
				'footerLine' => $this->assets->getFooterLine(),
				'assets' => $this->assets->getAssets(),
				'profile' => $this->style->forUser(uid: null),
			]
		);
	}//end settings()

	/**
	 * Save the extra footer line.
	 *
	 * @param string $footerLine The line.
	 *
	 * @return JSONResponse The settings, or 422.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function saveFooter(string $footerLine = ''): JSONResponse {
		try {
			$this->assets->setFooterLine(line: $footerLine);
		} catch (RuntimeException $e) {
			return new JSONResponse(['error' => $this->l10n->t('The footer line is one line of at most 200 characters.')], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		return $this->settings();
	}//end saveFooter()

	/**
	 * Upload the document logo or the cover image.
	 *
	 * @param string $kind `logo` or `cover`.
	 *
	 * @return JSONResponse The settings, or 400, 404, 413 or 422 with the reason.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function upload(string $kind): JSONResponse {
		$file = $this->request->getUploadedFile('file');
		if (empty($file) === true || isset($file['tmp_name']) === false || ($file['error'] ?? 0) !== 0) {
			return new JSONResponse(['error' => $this->l10n->t('No file uploaded.')], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->assets->store(kind: $kind, bytes: (string)file_get_contents($file['tmp_name']));
		} catch (RuntimeException $e) {
			return new JSONResponse(['error' => $this->uploadError(code: $e->getCode())], $e->getCode());
		}

		return $this->settings();
	}//end upload()

	/**
	 * Remove the document logo or the cover image.
	 *
	 * @param string $kind `logo` or `cover`.
	 *
	 * @return JSONResponse The settings, or 404.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function remove(string $kind): JSONResponse {
		try {
			$this->assets->delete(kind: $kind);
		} catch (RuntimeException $e) {
			return new JSONResponse(['error' => $this->uploadError(code: 404)], Http::STATUS_NOT_FOUND);
		}

		return $this->settings();
	}//end remove()

	/**
	 * The translated reason for a refused upload.
	 *
	 * @param int $code The service's error code.
	 *
	 * @return string The reason.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	private function uploadError(int $code): string {
		if ($code === Http::STATUS_REQUEST_ENTITY_TOO_LARGE) {
			return $this->l10n->t('The file is larger than 2 MB.');
		}

		if ($code === Http::STATUS_NOT_FOUND) {
			return $this->l10n->t('Unknown document image.');
		}

		return $this->l10n->t('Upload a PNG, JPEG or WebP image, or an SVG without script.');
	}//end uploadError()
}//end class
