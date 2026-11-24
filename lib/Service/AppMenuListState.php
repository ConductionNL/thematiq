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
 * @spec openspec/specs/apps-menu-order/spec.md#requirement-the-order-is-stored-for-the-person-on-the-server
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserSession;

/**
 * The part of the `thematiq/app-menu` initial state that is not the token set's: the external
 * apps, the signed-in user's order and the texts of the toggle, the hints and the live region.
 * {@see AppMenuService::inject()} merges it into the menu.
 *
 * @SuppressWarnings(PHPMD.StaticAccess) - AppMenuExternalApp names the entry and icon of an external app.
 *
 * @spec openspec/specs/apps-menu-order/spec.md#requirement-the-order-is-stored-for-the-person-on-the-server
 */
class AppMenuListState {

	/**
	 * Constructor.
	 *
	 * @param AppMenuOrder $order The users' orders.
	 * @param IUserSession $userSession The signed-in user.
	 * @param AppMenuPins|null $pins The users' pinned apps.
	 * @param AppMenuSettings|null $settings The menu settings: the defaults and the person's own.
	 * @param IURLGenerator|null $urls Builds the address of an uploaded icon.
	 * @param IGroupManager|null $groups Whether the person is an administrator.
	 * @param AppMenuLegacyExternalApps|null $legacy Moves the admin page's external apps into the settings, once.
	 */
	public function __construct(
		private readonly AppMenuOrder $order,
		private readonly IUserSession $userSession,
		private readonly ?AppMenuPins $pins = null,
		private readonly ?AppMenuSettings $settings = null,
		private readonly ?IURLGenerator $urls = null,
		private readonly ?IGroupManager $groups = null,
		private readonly ?AppMenuLegacyExternalApps $legacy = null,
	) {
	}//end __construct()

	/**
	 * The state for this request.
	 *
	 * @param IL10N $l The language of this request.
	 *
	 * @return array<string, mixed> `external` (when an app has an address), `order`, `pins`, `settings` and `labels`.
	 *
	 * @spec openspec/specs/apps-menu-order/spec.md#requirement-the-order-is-stored-for-the-person-on-the-server
	 * @spec openspec/specs/external-apps-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-the-external-apps-from-menu-settings
	 * @spec openspec/specs/apps-menu-create/spec.md#requirement-the-create-row-offers-what-nextcloud-offers
	 * @spec openspec/specs/apps-menu-pins/spec.md#requirement-pins-are-stored-for-the-person-on-the-server
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-admin-defaults-with-per-user-overrides
	 */
	public function state(IL10N $l): array {
		$state = [];
		$this->legacy?->migrateOnce(l: $l);

		$state['order'] = [];
		$state['pins'] = [];
		$user = $this->userSession->getUser();
		if ($user !== null) {
			$state['order'] = $this->order->get(userId: $user->getUID());
			$state['pins'] = ($this->pins?->get(userId: $user->getUID()) ?? []);
		}

		if ($this->settings !== null) {
			$settings = $this->settings->effective(userId: $user?->getUID());
			$state['order'] = $settings['order'];
			$state['pins'] = $settings['pinned'];
			$state['external'] = $this->withConfigured(apps: $settings['externalApps'], l: $l);
			if ($state['external'] === null) {
				unset($state['external']);
			}

			unset($settings['order'], $settings['pinned'], $settings['externalApps']);
			$state['settings'] = $settings;
		}

		$state['admin'] = ($user !== null && $this->groups?->isAdmin($user->getUID()) === true);

		$state['labels'] = [
			'title' => $l->t('Apps'),
			'allApps' => $l->t('More'),
			'hideApps' => $l->t('Fewer'),
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
			'menuSettings' => $l->t('Menu settings'),
		];

		return $state;
	}//end state()

	/**
	 * The external apps section with the visible apps an administrator or the person added.
	 *
	 * @param array<int, array<string, mixed>> $apps The configured external apps.
	 * @param IL10N $l The language of this request.
	 *
	 * @return array<string, mixed>|null The section, or null when it has no apps.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-external-apps-are-checked-on-the-server
	 */
	private function withConfigured(array $apps, IL10N $l): ?array {
		$icons = AppMenuExternalApp::icons();
		$stroke = AppMenuExternalApp::strokeIcons();
		$section = ['title' => $l->t('External apps'), 'note' => $l->t('opens in a new tab'), 'apps' => []];
		foreach ($apps as $app) {
			if ($app['visible'] !== true) {
				continue;
			}

			$entry = ['key' => AppMenuExternalApp::key(app: $app), 'label' => $app['title'], 'line' => $app['payoff'], 'url' => $app['url'], 'icon' => ''];
			if (isset($app['icon']['library']) === true) {
				$entry['icon'] = $icons[$app['icon']['library']];
				$entry['stroke'] = in_array($app['icon']['library'], $stroke, true);
			} elseif ($this->urls !== null) {
				$entry['iconUrl'] = $this->urls->linkToRoute('thematiq.appMenuSettings.icon', ['id' => $app['icon']['upload']]);
			}

			$section['apps'][] = $entry;
		}

		if ($section['apps'] === []) {
			return null;
		}

		return $section;
	}//end withConfigured()
}//end class
