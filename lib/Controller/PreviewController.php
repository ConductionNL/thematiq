<?php

/**
 * NL Design Theme Preview Controller.
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
 * @spec openspec/specs/theme-preview/spec.md#requirement-preview-lifecycle-endpoints
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use InvalidArgumentException;
use OCA\Thematiq\Service\Exception\RestrictedSetNotConfirmedException;
use OCA\Thematiq\Service\ThemePreviewService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;
use RuntimeException;

/**
 * Admin-only "proefdraaien" lifecycle endpoints: start a per-session token
 * set preview, discard it, or publish it to the instance-wide active set.
 *
 * Every method carries #[AuthorizedAdminSetting(Admin::class)] — no
 * #[PublicPage]/#[NoAdminRequired] — the same delegated-admin auth posture as
 * the rest of the settings API. The acting uid is ALWAYS resolved from
 * `IUserSession`, never from request input, so one admin can never start,
 * discard, or publish a preview on another user's behalf.
 *
 * @spec openspec/specs/theme-preview/spec.md#requirement-preview-lifecycle-endpoints
 */
class PreviewController extends Controller {

	/**
	 * The theme preview service.
	 *
	 * @var ThemePreviewService
	 */
	private ThemePreviewService $previewService;

	/**
	 * The user session — resolves the acting uid.
	 *
	 * @var IUserSession
	 */
	private IUserSession $userSession;

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request object.
	 * @param ThemePreviewService $previewService The theme preview service.
	 * @param IUserSession $userSession The user session.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		ThemePreviewService $previewService,
		IUserSession $userSession,
	) {
		parent::__construct(appName: $appName, request: $request);
		$this->previewService = $previewService;
		$this->userSession = $userSession;
	}//end __construct()

	/**
	 * Start a preview of the given token set in the caller's own session.
	 *
	 * @param string $tokenSet The token set id to preview.
	 *
	 * @return JSONResponse `{status: 'ok', tokenSet, expiresAt}`, or 400 for an invalid id.
	 *
	 * @spec openspec/specs/theme-preview/spec.md#requirement-preview-lifecycle-endpoints
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function start(string $tokenSet): JSONResponse {
		$uid = $this->actingUid();
		if ($uid === null) {
			return new JSONResponse(['error' => 'No active user session'], 400);
		}

		try {
			$state = $this->previewService->startPreview(uid: $uid, tokenSetId: $tokenSet);
		} catch (RestrictedSetNotConfirmedException $e) {
			return new JSONResponse(['error' => RestrictedSetNotConfirmedException::ERROR, 'tokenSet' => $e->getTokenSetId()], 403);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['error' => 'Invalid token set'], 400);
		}

		return new JSONResponse(
			[
				'status' => 'ok',
				'tokenSet' => $state['tokenSet'],
				'expiresAt' => $state['expiresAt'],
			]
		);
	}//end start()

	/**
	 * Discard the caller's active preview, if any.
	 *
	 * @return JSONResponse `{status: 'ok'}`.
	 *
	 * @spec openspec/specs/theme-preview/spec.md#requirement-preview-lifecycle-endpoints
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function discard(): JSONResponse {
		$uid = $this->actingUid();
		if ($uid === null) {
			return new JSONResponse(['error' => 'No active user session'], 400);
		}

		$this->previewService->clearPreview(uid: $uid);

		return new JSONResponse(['status' => 'ok']);
	}//end discard()

	/**
	 * Publish the caller's active preview: promotes it to the instance-wide
	 * active token set and clears the preview.
	 *
	 * @return JSONResponse `{status: 'ok', tokenSet}`, or 400 when no active preview exists.
	 *
	 * @spec openspec/specs/theme-preview/spec.md#requirement-preview-lifecycle-endpoints
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function publish(): JSONResponse {
		$uid = $this->actingUid();
		if ($uid === null) {
			return new JSONResponse(['error' => 'No active user session'], 400);
		}

		try {
			$tokenSet = $this->previewService->publishPreview(uid: $uid);
		} catch (RestrictedSetNotConfirmedException $e) {
			return new JSONResponse(['error' => RestrictedSetNotConfirmedException::ERROR, 'tokenSet' => $e->getTokenSetId()], 403);
		} catch (RuntimeException $e) {
			return new JSONResponse(['error' => 'No active preview to publish'], 400);
		}

		return new JSONResponse(
			[
				'status' => 'ok',
				'tokenSet' => $tokenSet,
			]
		);
	}//end publish()

	/**
	 * Resolve the acting uid from the current user session — never from
	 * request input, so an admin can only ever act on their own preview.
	 *
	 * @return string|null The current user's uid, or null when no user is logged in.
	 */
	private function actingUid(): ?string {
		return $this->userSession->getUser()?->getUID();
	}//end actingUid()
}//end class
