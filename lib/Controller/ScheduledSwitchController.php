<?php

/**
 * Thematiq Scheduled Switch Controller.
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
 * @spec openspec/changes/apply-scheduled-theme-switch/specs/scheduled-switch/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\Service\Exception\ScheduledSwitchException;
use OCA\Thematiq\Service\Exception\ScheduledSwitchNotFoundException;
use OCA\Thematiq\Service\ScheduledSwitchService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * List, plan and cancel planned token set switches. Every endpoint is
 * admin-only; Nextcloud's middleware answers 403 to anyone else.
 *
 * A controller of its own rather than more methods on SettingsController,
 * which is already at the coupling limit.
 *
 * @spec openspec/changes/apply-scheduled-theme-switch/specs/scheduled-switch/spec.md
 */
class ScheduledSwitchController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string                 $appName     The app name.
	 * @param IRequest               $request     The request.
	 * @param ScheduledSwitchService $service     The planner.
	 * @param IUserSession           $userSession The signed-in administrator.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly ScheduledSwitchService $service,
		private readonly IUserSession $userSession,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * The planned switches and the status block.
	 *
	 * @return JSONResponse `{switches, status}`.
	 *
	 * @spec openspec/changes/apply-scheduled-theme-switch/specs/scheduled-switch/spec.md#requirement-the-page-warns-when-switches-may-run-late
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function index(): JSONResponse {
		return new JSONResponse(['switches' => $this->service->list(), 'status' => $this->service->getStatus()]);
	}//end index()

	/**
	 * Plan a switch.
	 *
	 * @param string      $tokenSet        The set to switch to.
	 * @param string      $startAt         The start, ISO 8601 with an offset.
	 * @param string|null $endAt           The optional end; empty means none.
	 * @param bool        $syncCoreTheming Also update the Nextcloud logo and colours.
	 *
	 * @return JSONResponse 201 `{switch}`, or 400 `{error}` with the reason.
	 *
	 * @spec openspec/changes/apply-scheduled-theme-switch/specs/scheduled-switch/spec.md#requirement-an-administrator-plans-a-switch
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag) - the administrator's checkbox, passed through as is.
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function create(string $tokenSet, string $startAt, ?string $endAt = null, bool $syncCoreTheming = false): JSONResponse {
		if ($endAt === '') {
			$endAt = null;
		}

		$createdBy = '';
		$user = $this->userSession->getUser();
		if ($user !== null) {
			$createdBy = $user->getUID();
		}

		try {
			$entry = $this->service->create($tokenSet, $startAt, $endAt, $syncCoreTheming, $createdBy);
		} catch (ScheduledSwitchException $e) {
			return new JSONResponse(['error' => $e->getMessage()], 400);
		}

		return new JSONResponse(['switch' => $entry], 201);
	}//end create()

	/**
	 * Cancel a switch; a running one switches back at once.
	 *
	 * @param string $id The switch id.
	 *
	 * @return JSONResponse 200, 404 when it does not exist, 400 when it cannot switch back.
	 *
	 * @spec openspec/changes/apply-scheduled-theme-switch/specs/scheduled-switch/spec.md#requirement-an-administrator-cancels-a-planned-switch
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function cancel(string $id): JSONResponse {
		try {
			$this->service->cancel(id: $id);
		} catch (ScheduledSwitchNotFoundException $e) {
			return new JSONResponse(['error' => $e->getMessage()], 404);
		} catch (ScheduledSwitchException $e) {
			return new JSONResponse(['error' => $e->getMessage()], 400);
		}

		return new JSONResponse(['status' => 'ok']);
	}//end cancel()
}//end class
