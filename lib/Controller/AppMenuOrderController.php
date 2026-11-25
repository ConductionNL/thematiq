<?php

/**
 * The signed-in person's order of the apps menu.
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

use OCA\Thematiq\Service\AppMenuOrder;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Save the order of the workplace apps menu for the signed-in user. The route takes no user id:
 * a person writes only their own value.
 *
 * @spec openspec/specs/apps-menu-order/spec.md#requirement-the-order-is-stored-for-the-person-on-the-server
 */
class AppMenuOrderController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param AppMenuOrder $order Reads and saves the order.
	 * @param IUserSession $userSession The signed-in user.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly AppMenuOrder $order,
		private readonly IUserSession $userSession,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * Store the order. Keys that may not be stored are dropped; an empty list removes it.
	 *
	 * @param array<mixed> $order The entry keys, first to last.
	 *
	 * @return JSONResponse `{order}` as stored, or 401 without a signed-in user.
	 *
	 * @spec openspec/specs/apps-menu-order/spec.md#requirement-the-order-is-stored-for-the-person-on-the-server
	 */
	#[NoAdminRequired]
	public function save(array $order = []): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['order' => []], Http::STATUS_UNAUTHORIZED);
		}

		return new JSONResponse(['order' => $this->order->save(userId: $user->getUID(), keys: $order)]);
	}//end save()
}//end class
