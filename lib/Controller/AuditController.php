<?php

/**
 * NL Design Audit Controller.
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
 * @spec openspec/specs/theming-audit/spec.md#requirement-admin-audit-endpoints
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\Service\ThemeVersionRestoreService;
use OCA\Thematiq\Service\ThemeVersionService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Admin-only controller exposing the theming audit trail: the most recent
 * entries for the settings panel table, and a full-log download.
 *
 * Every method carries #[AuthorizedAdminSetting(Admin::class)] — no
 * #[PublicPage]/#[NoAdminRequired] — so both endpoints inherit the same
 * delegated-admin auth posture as the rest of the settings API.
 *
 * @spec openspec/specs/theming-audit/spec.md#requirement-admin-audit-endpoints
 */
class AuditController extends Controller {

	/**
	 * The hard cap on the `limit` query parameter.
	 *
	 * @var int
	 */
	private const MAX_LIMIT = 200;

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
	 * @param ThemingAuditService $auditService The theming audit trail service.
	 * @param ThemeVersionService $versionService The kept configuration versions.
	 * @param ThemeVersionRestoreService $restoreService Previews and restores a version.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		ThemingAuditService $auditService,
		private readonly ThemeVersionService $versionService,
		private readonly ThemeVersionRestoreService $restoreService,
	) {
		parent::__construct(appName: $appName, request: $request);
		$this->auditService = $auditService;
	}//end __construct()

	/**
	 * List the kept configuration versions, newest first.
	 *
	 * @return JSONResponse `{ versions: [{id, ts, actor, action}] }`.
	 *
	 * @spec openspec/specs/theme-versions/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function versions(): JSONResponse {
		return new JSONResponse(['versions' => $this->versionService->list()]);
	}//end versions()

	/**
	 * Show what restoring a version will change, writing nothing.
	 *
	 * @param string $id The version id.
	 *
	 * @return JSONResponse The preview, or 404 for an unknown id.
	 *
	 * @spec openspec/specs/theme-versions/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function previewVersion(string $id): JSONResponse {
		$preview = $this->restoreService->preview(id: $id);
		if ($preview === null) {
			return new JSONResponse(['error' => 'Unknown version.'], Http::STATUS_NOT_FOUND);
		}

		return new JSONResponse($preview);
	}//end previewVersion()

	/**
	 * Restore a version through the bundle import.
	 *
	 * @param string $id The version id.
	 *
	 * @return JSONResponse The result: 200 applied, 422 refused whole, 404 unknown id.
	 *
	 * @spec openspec/specs/theme-versions/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function restoreVersion(string $id): JSONResponse {
		$result = $this->restoreService->restore(id: $id);
		if ($result === null) {
			return new JSONResponse(['error' => 'Unknown version.'], Http::STATUS_NOT_FOUND);
		}

		if ($result['applied'] !== true) {
			return new JSONResponse($result, Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		return new JSONResponse($result);
	}//end restoreVersion()

	/**
	 * List the most recent audit entries, newest first.
	 *
	 * @param int $limit Requested entry count (default 20, hard-capped at 200).
	 *
	 * @return JSONResponse `{ entries: [...] }`.
	 *
	 * @spec openspec/specs/theming-audit/spec.md#requirement-admin-audit-endpoints
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function list(int $limit = 20): JSONResponse {
		return new JSONResponse(['entries' => $this->auditService->getRecent(limit: $this->capLimit(limit: $limit))]);
	}//end list()

	/**
	 * Stream the full retained log (rotated generation + current file) as a
	 * JSONL download.
	 *
	 * @return DataDownloadResponse The `nldesign-audit.jsonl` attachment.
	 *
	 * @spec openspec/specs/theming-audit/spec.md#requirement-admin-audit-endpoints
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function export(): DataDownloadResponse {
		return new DataDownloadResponse(
			data: $this->auditService->exportAll(),
			filename: 'nldesign-audit.jsonl',
			contentType: 'application/x-ndjson'
		);
	}//end export()

	/**
	 * Clamp the requested limit to `[0, MAX_LIMIT]`.
	 *
	 * @param int $limit The requested limit.
	 *
	 * @return int The clamped limit.
	 */
	private function capLimit(int $limit): int {
		if ($limit < 0) {
			return 0;
		}

		if ($limit > self::MAX_LIMIT) {
			return self::MAX_LIMIT;
		}

		return $limit;
	}//end capLimit()
}//end class
