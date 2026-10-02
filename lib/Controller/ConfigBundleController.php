<?php

/**
 * NL Design Configuration Bundle Controller.
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
 * @spec openspec/specs/config-portability/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\Service\ConfigBundleService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Throwable;

/**
 * Admin-only controller serving the complete `config-portability` bundle
 * over HTTP for the settings panel's Download/Upload configuration
 * controls. Every method requires the delegated theming admin (no
 * `#[PublicPage]`/`#[NoAdminRequired]` — {@see AuthorizedAdminSetting}
 * with the SecurityMiddleware's admin-session default applying) and is
 * CSRF-protected (no `#[NoCSRFRequired]`).
 *
 * Both methods are thin wrappers around {@see ConfigBundleService} — no
 * second serialization/validation path, so the HTTP endpoints stay
 * byte-for-byte identical to the `thematiq:config:export`/`:import` occ
 * commands for the same bundle.
 *
 * @spec openspec/specs/config-portability/spec.md
 */
class ConfigBundleController extends Controller {

	/**
	 * The maximum accepted upload size in bytes (256 KB, consistent with
	 * the `token-import-export` overrides-upload cap).
	 *
	 * @var int
	 */
	private const MAX_UPLOAD_SIZE = (256 * 1024);

	/**
	 * The configuration bundle service.
	 *
	 * @var ConfigBundleService
	 */
	private ConfigBundleService $service;

	/**
	 * The theming audit trail service.
	 *
	 * @var ThemingAuditService
	 */
	private ThemingAuditService $auditService;

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request object.
	 * @param ConfigBundleService $service The configuration bundle service.
	 * @param ThemingAuditService $auditService The theming audit trail service.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		ConfigBundleService $service,
		ThemingAuditService $auditService,
	) {
		parent::__construct(appName: $appName, request: $request);
		$this->service = $service;
		$this->auditService = $auditService;
	}//end __construct()

	/**
	 * Download the complete configuration bundle as a JSON attachment.
	 *
	 * @return DataDownloadResponse The bundle download.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function export(): DataDownloadResponse {
		$bundle = $this->service->export();
		$json = json_encode($bundle, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		if ($json === false) {
			$json = '{}';
		}

		return new DataDownloadResponse(
			data: $json,
			filename: 'nldesign-config.json',
			contentType: 'application/json'
		);
	}//end export()

	/**
	 * Import a configuration bundle from an uploaded JSON file.
	 *
	 * Validate-everything-first, then write: any hard validation failure
	 * returns HTTP 400 with the full per-section error listing and applies
	 * NOTHING. On success, the per-section applied counts are returned and
	 * (only then) one audit entry is logged.
	 *
	 * @return JSONResponse The import result, or the 400/413/415 error.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function import(): JSONResponse {
		$validationError = $this->validateUploadedFile();
		if ($validationError !== null) {
			return $validationError;
		}

		$bundle = $this->readUploadedBundle();
		if ($bundle instanceof JSONResponse) {
			return $bundle;
		}

		try {
			$result = $this->service->import(bundle: $bundle, dryRun: false);
		} catch (Throwable $e) {
			return new JSONResponse(['error' => $e->getMessage()], 500);
		}

		if ($result['valid'] === false) {
			return new JSONResponse(
				[
					'applied' => false,
					'errors' => $result['errors'],
				],
				400
			);
		}

		$this->auditService->log(
			action: 'config_bundle_imported',
			context: ['sections' => $result['sections']]
		);

		return new JSONResponse(
			[
				'applied' => true,
				'sections' => $result['sections'],
			]
		);
	}//end import()

	/**
	 * Validate the uploaded file envelope for the import endpoint.
	 *
	 * @return JSONResponse|null An error response, or null when the upload is present and within size.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function validateUploadedFile(): ?JSONResponse {
		$file = $this->request->getUploadedFile(key: 'file');
		if (empty($file) === true || isset($file['tmp_name']) === false) {
			return new JSONResponse(['error' => 'No file uploaded'], 400);
		}

		if (($file['size'] ?? 0) > self::MAX_UPLOAD_SIZE) {
			return new JSONResponse(['error' => 'File exceeds the 256 KB size limit'], 413);
		}

		$extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
		if ($extension !== 'json') {
			return new JSONResponse(['error' => 'Only .json files are accepted'], 415);
		}

		return null;
	}//end validateUploadedFile()

	/**
	 * Read and JSON-decode the uploaded bundle.
	 *
	 * @return array<string, mixed>|JSONResponse The decoded bundle, or the 400 error response.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function readUploadedBundle() {
		$file = $this->request->getUploadedFile(key: 'file');
		$content = file_get_contents($file['tmp_name']);
		if ($content === false) {
			return new JSONResponse(['error' => 'Could not read the uploaded file'], 400);
		}

		$decoded = json_decode($content, true);
		if (is_array($decoded) === false) {
			return new JSONResponse(['error' => 'The uploaded file does not contain valid JSON'], 400);
		}

		return $decoded;
	}//end readUploadedBundle()
}//end class
