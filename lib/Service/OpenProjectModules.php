<?php

/**
 * OpenProject's modules as a section of the apps menu.
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
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\IConfig;
use OCP\IL10N;

/**
 * The OpenProject plugin lists Nextcloud's apps in a column of its app switcher when an
 * administrator gives Nextcloud's address. This is the other half: with OpenProject's address in
 * `openproject_url`, Nextcloud's apps menu gets a section with OpenProject's modules.
 *
 * THE LIST IS FIXED, like the plugin's: it does not ask OpenProject which modules exist or which
 * ones the user may open. OpenProject decides that when the link is followed.
 *
 * @spec openspec/changes/openproject-in-the-app-menu/specs/openproject-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-openprojects-modules
 */
class OpenProjectModules {

	/**
	 * The app config key of OpenProject's address.
	 *
	 * @var string
	 */
	public const URL_KEY = 'openproject_url';

	/**
	 * The app config key of the modules to list, by key, comma separated.
	 *
	 * @var string
	 */
	public const MODULES_KEY = 'openproject_modules';

	/**
	 * The modules the section lists when nothing is set: the plugin's default row.
	 *
	 * @var array<int, string>
	 */
	public const DEFAULT = ['work_packages', 'gantt', 'boards', 'meetings'];

	/**
	 * Each module by the key of its item in OpenProject's top menu: its global path and the path
	 * data of its outline icon on a 24 px grid, as the plugin draws it.
	 *
	 * @var array<string, array{path: string, icon: string}>
	 */
	public const MODULES = [
		'projects' => ['path' => '/projects', 'icon' => 'M3 7h18M3 12h18M3 17h18'],
		'my_page' => ['path' => '/my/page', 'icon' => 'M3 10 12 3l9 7v10a1 1 0 0 1-1 1h-5v-7h-6v7H4a1 1 0 0 1-1-1z'],
		'work_packages' => ['path' => '/work_packages', 'icon' => 'M3 6h18M3 12h18M3 18h18'],
		'gantt' => ['path' => '/gantt', 'icon' => 'M3 5h8M7 12h10M11 19h10M3 3v18'],
		'boards' => ['path' => '/boards', 'icon' => 'M4 5h16v14H4zM9 5v14M15 5v14'],
		'team_planners' => ['path' => '/team_planners', 'icon' => 'M4 5h16v14H4zM4 10h16M9 5v14'],
		'calendar_view' => [
			'path' => '/calendars',
			'icon' => 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z',
		],
		'meetings' => ['path' => '/meetings', 'icon' => 'M23 7l-7 5 7 5zM1 5h15v14H1z'],
		'news' => ['path' => '/news', 'icon' => 'M4 4h16v16H4zM8 8h8M8 12h8M8 16h5'],
	];

	/**
	 * The most modules in the section.
	 *
	 * @var int
	 */
	public const MAX = 9;

	/**
	 * Constructor.
	 *
	 * @param IConfig $config Reads the address and the modules.
	 */
	public function __construct(
		private readonly IConfig $config,
	) {
	}//end __construct()

	/**
	 * The section, or null when no usable address is set.
	 *
	 * @param IL10N $l The language of this request.
	 *
	 * @return array{title: string, url: string, modules: array<int, array{key: string, label: string, url: string, icon: string}>}|null
	 *
	 * @spec openspec/changes/openproject-in-the-app-menu/specs/openproject-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-openprojects-modules
	 */
	public function section(IL10N $l): ?array {
		$base = $this->normalize(value: $this->config->getAppValue(Application::APP_ID, self::URL_KEY, ''));
		if ($base === null) {
			return null;
		}

		$labels = [
			'projects' => $l->t('Projects'),
			'my_page' => $l->t('My page'),
			'work_packages' => $l->t('Work packages'),
			'gantt' => $l->t('Gantt charts'),
			'boards' => $l->t('Boards'),
			'team_planners' => $l->t('Team planners'),
			'calendar_view' => $l->t('Calendars'),
			'meetings' => $l->t('Meetings'),
			'news' => $l->t('News'),
		];

		$modules = [];
		foreach ($this->wanted(value: $this->config->getAppValue(Application::APP_ID, self::MODULES_KEY, '')) as $key) {
			$modules[] = [
				'key' => $key,
				'label' => $labels[$key],
				'url' => $base . self::MODULES[$key]['path'],
				'icon' => self::MODULES[$key]['icon'],
			];
		}

		return ['title' => 'OpenProject', 'url' => $base, 'modules' => $modules];
	}//end section()

	/**
	 * The stored address and modules, as the admin page shows them.
	 *
	 * @return array{url: string, modules: array<int, string>, available: array<int, string>}
	 *
	 * @spec openspec/changes/openproject-in-the-app-menu/specs/openproject-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-openprojects-modules
	 */
	public function stored(): array {
		return [
			'url' => ($this->normalize(value: $this->config->getAppValue(Application::APP_ID, self::URL_KEY, '')) ?? ''),
			'modules' => $this->wanted(value: $this->config->getAppValue(Application::APP_ID, self::MODULES_KEY, '')),
			'available' => array_keys(self::MODULES),
		];
	}//end stored()

	/**
	 * Save the address, and the modules when given. An empty address removes the section.
	 *
	 * @param string $url The address, or empty.
	 * @param string|null $modules The module keys, or null to leave them.
	 *
	 * @return bool False, and nothing saved, for an address that is not http or https.
	 *
	 * @spec openspec/changes/openproject-in-the-app-menu/specs/openproject-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-openprojects-modules
	 */
	public function save(string $url, ?string $modules): bool {
		$normalized = $this->normalize(value: $url);
		if (trim($url) !== '' && $normalized === null) {
			return false;
		}

		$this->config->deleteAppValue(Application::APP_ID, self::URL_KEY);
		if ($normalized !== null) {
			$this->config->setAppValue(Application::APP_ID, self::URL_KEY, $normalized);
		}

		if ($modules !== null) {
			$this->config->setAppValue(Application::APP_ID, self::MODULES_KEY, implode(',', $this->wanted(value: $modules)));
		}

		return true;
	}//end save()

	/**
	 * The address without a trailing slash, or null when it is not an http or https address. It ends
	 * up in a link, so nothing else gets through.
	 *
	 * @param string $value The stored value.
	 *
	 * @return string|null The address.
	 *
	 * @spec openspec/changes/openproject-in-the-app-menu/specs/openproject-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-openprojects-modules
	 */
	public function normalize(string $value): ?string {
		$url = (string)preg_replace('#/+$#', '', trim($value));
		if (preg_match('#^https?://[^\s"\'<>\\\\]+$#i', $url) !== 1) {
			return null;
		}

		return $url;
	}//end normalize()

	/**
	 * The modules to list: the stored keys this class knows, in their order, else the default.
	 *
	 * @param string $value The stored list, comma or space separated.
	 *
	 * @return array<int, string> The keys.
	 *
	 * @spec openspec/changes/openproject-in-the-app-menu/specs/openproject-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-openprojects-modules
	 */
	public function wanted(string $value): array {
		$keys = (array)preg_split('/[\s,|]+/', strtolower(trim($value)), -1, PREG_SPLIT_NO_EMPTY);
		$keys = array_values(array_unique(array_filter($keys, static fn ($key): bool => is_string($key) === true && isset(self::MODULES[$key]))));
		if ($keys === []) {
			return self::DEFAULT;
		}

		return array_slice($keys, 0, self::MAX);
	}//end wanted()
}//end class
