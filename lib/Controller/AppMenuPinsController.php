<?php

/**
 * The signed-in person's pinned apps in the top bar.
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

use OCA\Thematiq\Service\AppMenuKeys;
use OCA\Thematiq\Service\AppMenuPins;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Save the pinned apps of the signed-in user. The route takes no user id: a person writes only
 * their own value, and only entries they can see.
 *
 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-pins/spec.md#requirement-pins-are-stored-for-the-person-on-the-server
 */
class AppMenuPinsController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param AppMenuPins $pins Reads and saves the pins.
	 * @param AppMenuKeys $keys The entries the user can see.
	 * @param IUserSession $userSession The signed-in user.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly AppMenuPins $pins,
		private readonly AppMenuKeys $keys,
		private readonly IUserSession $userSession,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * Store the pins. Keys that may not be stored are dropped; an empty list removes them.
	 *
	 * @param array<mixed> $pins The entry keys, first to last.
	 *
	 * @return JSONResponse `{pins}` as stored, or 401 without a signed-in user.
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-pins/spec.md#requirement-pins-are-stored-for-the-person-on-the-server
	 */
	#[NoAdminRequired]
	public function save(array $pins = []): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['pins' => []], Http::STATUS_UNAUTHORIZED);
		}

		return new JSONResponse(
			['pins' => $this->pins->save(userId: $user->getUID(), keys: $pins, known: $this->keys->known(userId: $user->getUID()))]
		);
	}//end save()
}//end class
