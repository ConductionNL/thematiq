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
	 * @param AppMenuPins|null $pins The users' pinned apps.
	 */
	public function __construct(
		private readonly ExternalApps $externalApps,
		private readonly AppMenuOrder $order,
		private readonly IUserSession $userSession,
		private readonly ?AppMenuPins $pins = null,
	) {
	}//end __construct()

	/**
	 * The state for this request.
	 *
	 * @param IL10N $l The language of this request.
	 *
	 * @return array<string, mixed> `external` (when an app has an address), `order`, `pins` and `labels`.
	 *
	 * @spec openspec/changes/apps-menu-ordered-list/specs/apps-menu-order/spec.md#requirement-the-order-is-stored-for-the-person-on-the-server
	 * @spec openspec/changes/apps-menu-ordered-list/specs/external-apps-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-the-external-apps-that-have-an-address
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-create/spec.md#requirement-the-create-row-offers-what-nextcloud-offers
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-pins/spec.md#requirement-pins-are-stored-for-the-person-on-the-server
	 */
	public function state(IL10N $l): array {
		$state = [];
		$external = $this->externalApps->section(l: $l);
		if ($external !== null) {
			$state['external'] = $external;
		}

		$state['order'] = [];
		$state['pins'] = [];
		$user = $this->userSession->getUser();
		if ($user !== null) {
			$state['order'] = $this->order->get(userId: $user->getUID());
			$state['pins'] = ($this->pins?->get(userId: $user->getUID()) ?? []);
		}

		$state['labels'] = [
			'title' => $l->t('Apps'),
			'allApps' => $l->t('All apps'),
			'hideApps' => $l->t('Hide apps'),
			'dragHint' => $l->t('Drag an app to reorder it or pin it to the top bar.'),
			'moveHint' => $l->t('Alt with an arrow key moves, Alt+P pins or unpins.'),
			'moved' => $l->t('{app} moved to place {place} of {total}.'),
			'saveFailed' => $l->t('The order could not be saved.'),
			'create' => $l->t('Create'),
			'newDocument' => $l->t('New document'),
			'newSpreadsheet' => $l->t('New spreadsheet'),
			'newPresentation' => $l->t('New presentation'),
			'newWhiteboard' => $l->t('New whiteboard'),
			'more' => $l->t('More'),
			'createFailed' => $l->t('The file could not be created.'),
			'pinned' => $l->t('Pinned apps'),
			'dropToPin' => $l->t('Drop to pin'),
			'releaseToRemove' => $l->t('Release to remove'),
			'pinnedApp' => $l->t('{app} pinned to the top bar.'),
			'unpinnedApp' => $l->t('{app} removed from the top bar.'),
			'pinFull' => $l->t('You can pin up to {max} apps.'),
			'pinSaveFailed' => $l->t('The pinned apps could not be saved.'),
		];

		return $state;
	}//end state()
}//end class
