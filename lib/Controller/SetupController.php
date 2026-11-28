<?php

/**
 * Thematiq Setup Controller.
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
 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-setup-contract-is-for-administrators-only-req-tqfs-005
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use InvalidArgumentException;
use OCA\Thematiq\Service\Exception\RestrictedSetNotConfirmedException;
use OCA\Thematiq\Service\SetupContract;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * The ADR-042 setup contract: `GET /api/setup/status` and `POST /api/setup/action/{actionId}`.
 *
 * Administrators only: neither method carries `#[NoAdminRequired]`, so Nextcloud's middleware refuses
 * anyone else with 403 before the controller runs, and the POST requires CSRF. Under a configuration
 * source the POST is refused with 423 by `ConfigSourceLockMiddleware`.
 *
 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-setup-contract-is-for-administrators-only-req-tqfs-005
 */
class SetupController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param SetupContract $setup The status document and the actions.
	 * @param IUserSession $userSession The administrator.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly SetupContract $setup,
		private readonly IUserSession $userSession,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * The status document.
	 *
	 * @return JSONResponse `{version, completed, skipped, configured, steps, current}`.
	 *
	 * @auth admin-only the status names the stored house style choices, for administrators only
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-setup-contract-is-for-administrators-only-req-tqfs-005
	 */
	public function status(): JSONResponse {
		return new JSONResponse($this->setup->status->status());
	}//end status()

	/**
	 * Run a setup action: `apply` or `skip`.
	 *
	 * @param string $actionId The action.
	 *
	 * @return JSONResponse The parts and the new status; 400 naming a wrong key, 403 for an unconfirmed
	 *                      restricted set, 404 for an unknown action, 500 when a part failed.
	 *
	 * @auth admin-only applying or skipping the setup changes the whole instance's look, so administrators only
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-setup-contract-is-for-administrators-only-req-tqfs-005
	 */
	public function action(string $actionId): JSONResponse {
		$uid = (string)$this->userSession->getUser()?->getUID();
		if ($actionId === 'skip') {
			$this->setup->actions->skip(uid: $uid);
			$this->setup->status->forget();

			return new JSONResponse(['status' => $this->setup->status->status()]);
		}

		if ($actionId !== 'apply') {
			return new JSONResponse(['error' => 'unknown_action', 'action' => $actionId], 404);
		}

		return $this->apply(uid: $uid);
	}//end action()

	/**
	 * Run `apply` and answer its parts and the new status.
	 *
	 * @param string $uid The administrator.
	 *
	 * @return JSONResponse 200 when every part was applied, 500 when one failed, 400 or 403 when refused.
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-setup-contract-is-for-administrators-only-req-tqfs-005
	 */
	private function apply(string $uid): JSONResponse {
		try {
			$result = $this->setup->actions->apply(request: $this->body(), uid: $uid);
		} catch (RestrictedSetNotConfirmedException $e) {
			return new JSONResponse(['error' => 'restricted_set_not_confirmed'], 403);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['error' => 'invalid_value', 'key' => $e->getMessage()], 400);
		}

		$this->setup->status->forget();
		$code = 200;
		if ($result['completed'] === false) {
			$code = 500;
		}

		return new JSONResponse($result + ['status' => $this->setup->status->status()], $code);
	}//end apply()

	/**
	 * The JSON body of the request, without the route parameter.
	 *
	 * @return array<mixed> The body.
	 *
	 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-setup-contract-is-for-administrators-only-req-tqfs-005
	 */
	private function body(): array {
		$params = $this->request->getParams();
		unset($params['actionId'], $params['_route']);

		return $params;
	}//end body()
}//end class
