<?php

/**
 * Thematiq Token Set Licence Controller.
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
 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use InvalidArgumentException;
use OCA\Thematiq\Service\Exception\RestrictedSetInUseException;
use OCA\Thematiq\Service\TokenSetLicenceService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * The licence of a shipped token set, and the confirmation an administrator
 * gives before a restricted set can be applied.
 *
 * A plain Controller, not an OCSController: Nextcloud's OCSMiddleware answers
 * a 403 from an OCS controller with HTTP 200, and a refusal has to reach the
 * browser as an error status.
 *
 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md
 */
class TokenSetLicenceController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param TokenSetLicenceService $licences Reads licences and keeps the confirmations.
	 * @param IUserSession $userSession Names the acting administrator.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly TokenSetLicenceService $licences,
		private readonly IUserSession $userSession,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * Every restricted set with its licence and confirmation, and the ones
	 * that are applied without a confirmation.
	 *
	 * @return JSONResponse `{sets: [...], awaiting: [...]}`.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-the-picker-shows-the-warning-and-the-licence-text
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function index(): JSONResponse {
		return new JSONResponse(['sets' => $this->licences->restrictedSets(), 'awaiting' => $this->licences->awaitingConfirmation()]);
	}//end index()

	/**
	 * A set's licence: the declaration, the full text, the confirmation and where the set is applied.
	 *
	 * @param string $id The token set id.
	 *
	 * @return JSONResponse The licence, or 404 when the set declares none.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-the-picker-shows-the-warning-and-the-licence-text
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function show(string $id): JSONResponse {
		$licence = $this->licences->describe(tokenSetId: $id);
		if ($licence === null) {
			return new JSONResponse(['error' => 'no_licence'], 404);
		}

		return new JSONResponse($licence);
	}//end show()

	/**
	 * Confirm that this organisation is the owner of a restricted set, or has its permission.
	 *
	 * The body must carry `confirmed: true`: the statement is the
	 * administrator's, so a request without it confirms nothing.
	 *
	 * @param string $id The token set id.
	 * @param mixed $confirmed The administrator's statement; only the JSON value `true` counts.
	 *
	 * @return JSONResponse The stored confirmation; 400 without the statement, 404 for a set that is not restricted.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-an-administrator-confirms-a-restricted-set
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function confirm(string $id, mixed $confirmed = null): JSONResponse {
		$uid = (string)$this->userSession->getUser()?->getUID();
		if ($confirmed !== true || $uid === '') {
			return new JSONResponse(['error' => 'confirmation_required'], 400);
		}

		try {
			$confirmation = $this->licences->confirm(tokenSetId: $id, uid: $uid);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['error' => 'not_restricted'], 404);
		}

		return new JSONResponse(['status' => 'ok', 'tokenSet' => $id, 'confirmation' => $confirmation]);
	}//end confirm()

	/**
	 * Withdraw the confirmation of a restricted set.
	 *
	 * @param string $id The token set id.
	 *
	 * @return JSONResponse `{status: 'ok'}`, or 409 naming where the set is still applied.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-a-confirmation-can-be-withdrawn-once-the-set-is-no-longer-applied
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function withdraw(string $id): JSONResponse {
		$uid = (string)$this->userSession->getUser()?->getUID();

		try {
			$this->licences->withdraw(tokenSetId: $id, uid: $uid);
		} catch (RestrictedSetInUseException $e) {
			return new JSONResponse(['error' => 'restricted_set_in_use', 'tokenSet' => $id, 'appliedIn' => $e->getAppliedIn()], 409);
		}

		return new JSONResponse(['status' => 'ok', 'tokenSet' => $id]);
	}//end withdraw()
}//end class
