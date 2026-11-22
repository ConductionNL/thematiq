<?php

/**
 * Thematiq apps menu: what the ordered list needs besides the sections.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Service
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/changes/apps-menu-ordered-list/specs/apps-menu-order/spec.md#requirement-the-order-is-stored-for-the-person-on-the-server
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\IL10N;
use OCP\IUserSession;

/**
 * The part of the `thematiq/app-menu` initial state that is not the token set's: the external
 * apps, the signed-in user's order and the texts of the toggle, the hints and the live region.
 * {@see AppMenuService::inject()} merges it into the menu.
 *
 * @spec openspec/changes/apps-menu-ordered-list/specs/apps-menu-order/spec.md#requirement-the-order-is-stored-for-the-person-on-the-server
 */
class AppMenuListState {

	/**
	 * Constructor.
	 *
	 * @param ExternalApps $externalApps The external apps with an address.
	 * @param AppMenuOrder $order The users' orders.
	 * @param IUserSession $userSession The signed-in user.
	 */
	public function __construct(
		private readonly ExternalApps $externalApps,
		private readonly AppMenuOrder $order,
		private readonly IUserSession $userSession,
	) {
	}//end __construct()

	/**
	 * The state for this request.
	 *
	 * @param IL10N $l The language of this request.
	 *
	 * @return array<string, mixed> `openproject` and `external` (when an app has an address), `order` and `labels`.
	 *
	 * @spec openspec/changes/apps-menu-ordered-list/specs/apps-menu-order/spec.md#requirement-the-order-is-stored-for-the-person-on-the-server
	 * @spec openspec/changes/apps-menu-ordered-list/specs/external-apps-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-the-external-apps-that-have-an-address
	 */
	public function state(IL10N $l): array {
		$state = [];
		$openProject = $this->externalApps->openProject()->section(l: $l);
		if ($openProject !== null) {
			$state['openproject'] = $openProject;
		}

		$external = $this->externalApps->section(l: $l);
		if ($external !== null) {
			$state['external'] = $external;
		}

		$state['order'] = [];
		$user = $this->userSession->getUser();
		if ($user !== null) {
			$state['order'] = $this->order->get(userId: $user->getUID());
		}

		$state['labels'] = [
			'allApps' => $l->t('All apps'),
			'hideApps' => $l->t('Hide apps'),
			'dragHint' => $l->t('Drag an app to change the order.'),
			'moveHint' => $l->t('Alt with an arrow key moves the app.'),
			'moved' => $l->t('{app} moved to place {place} of {total}.'),
			'saveFailed' => $l->t('The order could not be saved.'),
		];

		return $state;
	}//end state()
}//end class
