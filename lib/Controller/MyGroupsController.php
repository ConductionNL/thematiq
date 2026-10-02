<?php

/**
 * Thematiq controller: a group subadmin picks the house style of a delegated group.
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
 * @spec openspec/specs/per-group-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\Service\DelegatedGroupThemingService;
use OCA\Thematiq\Service\Exception\DelegationRefusedException;
use OCA\Thematiq\Service\Exception\GroupThemingValidationException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * `GET /api/my-groups/house-style` lists the delegated groups the signed-in
 * user is subadmin of; `POST /api/my-groups/{group}/house-style` sets one.
 * Both are `#[NoAdminRequired]`; the write checks, on every call, that the
 * user is subadmin of the named group and that the set is on its allowed
 * list, answering 403 otherwise.
 *
 * @spec openspec/specs/per-group-theming/spec.md
 */
class MyGroupsController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string                       $appName     The app name.
	 * @param IRequest                     $request     The request.
	 * @param DelegatedGroupThemingService $delegation  The delegation service.
	 * @param IUserSession                 $userSession The session.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly DelegatedGroupThemingService $delegation,
		private readonly IUserSession $userSession,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * The delegated groups of the signed-in user.
	 *
	 * @return JSONResponse `{groups: [...]}`.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	#[NoAdminRequired]
	public function index(): JSONResponse {
		$uid = (string)$this->userSession->getUser()?->getUID();

		return new JSONResponse(['groups' => $this->delegation->listForUser(uid: $uid)]);
	}//end index()

	/**
	 * Set the house style of a delegated group.
	 *
	 * @param string $group The group id.
	 * @param string $tokenSet The chosen set.
	 *
	 * @return JSONResponse The updated entry, 403 when not allowed, 422 when the mapping refuses it.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	#[NoAdminRequired]
	public function update(string $group, string $tokenSet = ''): JSONResponse {
		$uid = (string)$this->userSession->getUser()?->getUID();

		try {
			$this->delegation->requireDelegatedChoice(uid: $uid, group: $group, tokenSet: $tokenSet);
			$entry = $this->delegation->setDelegatedTokenSet(uid: $uid, group: $group, tokenSet: $tokenSet);
		} catch (DelegationRefusedException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (GroupThemingValidationException $e) {
			return new JSONResponse(['error' => $e->getReason()], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		return new JSONResponse($entry);
	}//end update()
}//end class
