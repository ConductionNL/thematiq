<?php

/**
 * Thematiq external apps in the apps menu.
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
 * @spec openspec/changes/apps-menu-ordered-list/specs/external-apps-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-the-external-apps-that-have-an-address
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\IConfig;
use OCP\IL10N;

/**
 * The "External apps" section of the workplace apps menu: the apps an administrator gave an
 * address, each a link that opens in a new tab. OpenProject keeps its own keys and is listed as
 * its modules ({@see OpenProjectModules}); the others are one entry each, their addresses in the
 * app config key `external_apps` as `{key: address}`. An app without an address is left out, so
 * the menu never shows a dead link. Nothing here contacts those addresses.
 *
 * @spec openspec/changes/apps-menu-ordered-list/specs/external-apps-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-the-external-apps-that-have-an-address
 */
class ExternalApps {

	/**
	 * The app config key of the addresses.
	 *
	 * @var string
	 */
	public const CONFIG_KEY = 'external_apps';

	/**
	 * OpenProject's key in the admin form.
	 *
	 * @var string
	 */
	public const OPENPROJECT = 'openproject';

	/**
	 * The apps besides OpenProject, in the order of the menu, by what they do: the product name
	 * and the path data of an outline icon on a 24 px grid.
	 *
	 * @var array<string, array{name: string, icon: string}>
	 */
	public const APPS = [
		'mail' => ['name' => 'Open-Xchange', 'icon' => 'M3 5h18v14H3zM3 6l9 7 9-7'],
		'chat' => ['name' => 'Element', 'icon' => 'M4 4h16v12H8l-4 4z'],
		'wiki' => ['name' => 'XWiki', 'icon' => 'M5 3h10l4 4v14H5zM15 3v4h4M8 12h8M8 16h6'],
		'code' => ['name' => 'Forgejo', 'icon' => 'M8 7l-5 5 5 5M16 7l5 5-5 5M14 4l-4 16'],
		'video' => ['name' => 'BigBlueButton', 'icon' => 'M3 6h13v12H3zM16 10l5-3v10l-5-3'],
	];

	/**
	 * Constructor.
	 *
	 * @param IConfig $config Reads and saves the addresses.
	 * @param OpenProjectModules $openProject OpenProject's address and modules.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly OpenProjectModules $openProject,
	) {
	}//end __construct()

	/**
	 * OpenProject's address and modules, which keep their own keys.
	 *
	 * @return OpenProjectModules The OpenProject half.
	 *
	 * @spec openspec/changes/apps-menu-ordered-list/specs/external-apps-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-the-external-apps-that-have-an-address
	 */
	public function openProject(): OpenProjectModules {
		return $this->openProject;
	}//end openProject()

	/**
	 * The section for the menu, or null when no app has an address.
	 *
	 * @param IL10N $l The language of this request.
	 *
	 * @return array{title: string, note: string, apps: array<int, array{key: string, label: string, line: string, url: string, icon: string}>}|null
	 *
	 * @spec openspec/changes/apps-menu-ordered-list/specs/external-apps-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-the-external-apps-that-have-an-address
	 */
	public function section(IL10N $l): ?array {
		$apps = [];
		foreach (($this->openProject->section(l: $l)['modules'] ?? []) as $module) {
			$apps[] = [
				'key' => 'external-openproject-' . $module['key'],
				'label' => $module['label'],
				'line' => 'OpenProject',
				'url' => $module['url'],
				'icon' => $module['icon'],
			];
		}

		$lines = $this->lines(l: $l);
		foreach ($this->addresses() as $key => $url) {
			$apps[] = [
				'key' => 'external-' . $key,
				'label' => self::APPS[$key]['name'],
				'line' => $lines[$key],
				'url' => $url,
				'icon' => self::APPS[$key]['icon'],
			];
		}

		if ($apps === []) {
			return null;
		}

		return ['title' => $l->t('External apps'), 'note' => $l->t('opens in a new tab'), 'apps' => $apps];
	}//end section()

	/**
	 * Every app with its stored address, as the admin page shows them.
	 *
	 * @param IL10N $l The language of this request.
	 *
	 * @return array<int, array{key: string, name: string, line: string, url: string}>
	 *
	 * @spec openspec/changes/apps-menu-ordered-list/specs/external-apps-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-the-external-apps-that-have-an-address
	 */
	public function stored(IL10N $l): array {
		$rows = [
			[
				'key' => self::OPENPROJECT,
				'name' => 'OpenProject',
				'line' => $l->t('Projects and work packages'),
				'url' => $this->openProject->stored()['url'],
			],
		];
		$addresses = $this->addresses();
		$lines = $this->lines(l: $l);
		foreach (self::APPS as $key => $app) {
			$rows[] = ['key' => $key, 'name' => $app['name'], 'line' => $lines[$key], 'url' => ($addresses[$key] ?? '')];
		}

		return $rows;
	}//end stored()

	/**
	 * Save the addresses given, by key. An empty address removes that app. One address that is not
	 * http or https refuses the whole save.
	 *
	 * @param array<mixed> $urls The addresses by key; keys left out stay as they are.
	 *
	 * @return array<int, string> The keys refused; empty when everything was saved.
	 *
	 * @spec openspec/changes/apps-menu-ordered-list/specs/external-apps-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-the-external-apps-that-have-an-address
	 */
	public function save(array $urls): array {
		$refused = [];
		$clean = [];
		foreach ($urls as $key => $url) {
			$key = (string)$key;
			if ($key !== self::OPENPROJECT && isset(self::APPS[$key]) === false) {
				continue;
			}

			if (is_string($url) === false) {
				$url = '';
			}

			$url = trim($url);
			$normalized = $this->openProject->normalize(value: $url);
			if ($url !== '' && $normalized === null) {
				$refused[] = $key;
				continue;
			}

			$clean[$key] = ($normalized ?? '');
		}

		if ($refused !== []) {
			return $refused;
		}

		if (array_key_exists(self::OPENPROJECT, $clean) === true) {
			$this->openProject->save(url: $clean[self::OPENPROJECT], modules: null);
			unset($clean[self::OPENPROJECT]);
		}

		$addresses = array_filter(array_merge($this->addresses(), $clean), static fn (string $url): bool => $url !== '');
		if ($addresses === []) {
			$this->config->deleteAppValue(Application::APP_ID, self::CONFIG_KEY);
			return [];
		}

		$this->config->setAppValue(Application::APP_ID, self::CONFIG_KEY, (string)json_encode($addresses));

		return [];
	}//end save()

	/**
	 * The stored addresses of the known apps that are http or https, in the order of APPS.
	 *
	 * @return array<string, string> The addresses by key.
	 *
	 * @spec openspec/changes/apps-menu-ordered-list/specs/external-apps-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-the-external-apps-that-have-an-address
	 */
	private function addresses(): array {
		$stored = json_decode($this->config->getAppValue(Application::APP_ID, self::CONFIG_KEY, ''), true);
		$addresses = [];
		if (is_array($stored) === false) {
			return $addresses;
		}

		foreach (array_keys(self::APPS) as $key) {
			$url = null;
			if (is_string($stored[$key] ?? null) === true) {
				$url = $this->openProject->normalize(value: $stored[$key]);
			}

			if ($url !== null) {
				$addresses[$key] = $url;
			}
		}

		return $addresses;
	}//end addresses()

	/**
	 * The second line of each app: what it is for.
	 *
	 * @param IL10N $l The language of this request.
	 *
	 * @return array<string, string> The lines by key.
	 *
	 * @spec openspec/changes/apps-menu-ordered-list/specs/external-apps-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-the-external-apps-that-have-an-address
	 */
	private function lines(IL10N $l): array {
		return [
			'mail' => $l->t('Mail'),
			'chat' => $l->t('Chat'),
			'wiki' => $l->t('Knowledge base'),
			'code' => $l->t('Code'),
			'video' => $l->t('Video meetings'),
		];
	}//end lines()
}//end class
