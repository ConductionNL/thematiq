<?php

/**
 * NL Design App Theming Service.
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
 * @spec openspec/changes/archive/2026-06-14-per-app-theming-toggle/tasks.md#task-1.1
 * @spec openspec/changes/archive/2026-06-14-per-app-theming-toggle/tasks.md#task-1.2
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IConfig;

/**
 * Resolves whether nldesign theming should be injected for a given app.
 *
 * The exclusion list is stored in the nldesign appconfig key `disabled_apps`
 * as a JSON array of app id strings (default empty — today's global theming).
 * The ids `nldesign`, `settings`, and `theming` are protected: they can never
 * be excluded (the settings panel lives there and theming itself is the point).
 *
 * @spec openspec/changes/archive/2026-06-14-per-app-theming-toggle/tasks.md#task-1.1
 * @spec openspec/changes/archive/2026-06-14-per-app-theming-toggle/tasks.md#task-1.2
 */
class AppThemingService {

	/**
	 * The appconfig key holding the JSON exclusion list.
	 *
	 * @var string
	 */
	private const CONFIG_KEY = 'disabled_apps';

	/**
	 * App ids that must never be excluded from theming.
	 *
	 * @var string[]
	 */
	private const PROTECTED_IDS = ['thematiq', 'settings', 'theming'];

	/**
	 * The application configuration service.
	 *
	 * @var IConfig
	 */
	private IConfig $config;

	/**
	 * The app manager, used to validate app ids and resolve display names.
	 *
	 * @var IAppManager
	 */
	private IAppManager $appManager;

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The config service.
	 * @param IAppManager $appManager The app manager.
	 */
	public function __construct(IConfig $config, IAppManager $appManager) {
		$this->config = $config;
		$this->appManager = $appManager;
	}//end __construct()

	/**
	 * Get the list of app ids excluded from nldesign theming.
	 *
	 * Returns an empty array when the key is absent or malformed, which
	 * reproduces today's behavior exactly (theming injected globally).
	 *
	 * @return string[] The excluded app ids.
	 *
	 * @spec openspec/changes/archive/2026-06-14-per-app-theming-toggle/tasks.md#task-1.1
	 */
	public function getDisabledApps(): array {
		$raw = $this->config->getAppValue(Application::APP_ID, self::CONFIG_KEY, '[]');

		$decoded = json_decode($raw, true);
		if (is_array($decoded) === false) {
			return [];
		}

		// Keep only non-empty strings.
		$result = [];
		foreach ($decoded as $appId) {
			if (is_string($appId) === true && $appId !== '') {
				$result[] = $appId;
			}
		}

		return array_values(array_unique($result));
	}//end getDisabledApps()

	/**
	 * Replace the exclusion list.
	 *
	 * Each id is validated against the installed apps; unknown ids are dropped
	 * (so stale entries from uninstalled apps self-heal on the next save) and
	 * the protected ids are never accepted.
	 *
	 * @param string[] $appIds The desired exclusion list.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-06-14-per-app-theming-toggle/tasks.md#task-1.1
	 */
	public function setDisabledApps(array $appIds): void {
		$clean = [];
		foreach ($appIds as $appId) {
			// Coerce defensively — callers may pass a loosely-typed payload
			// (e.g. decoded JSON) even though the signature promises string[].
			$appId = (string)$appId;
			if ($appId === '') {
				continue;
			}

			if (in_array($appId, self::PROTECTED_IDS, true) === true) {
				continue;
			}

			if ($this->appManager->isInstalled($appId) === false) {
				continue;
			}

			$clean[$appId] = true;
		}

		$this->config->setAppValue(
			Application::APP_ID,
			self::CONFIG_KEY,
			json_encode(array_keys($clean))
		);
	}//end setDisabledApps()

	/**
	 * Whether theming injection must be skipped for the given app id.
	 *
	 * A null app id (login, settings, share links, occ/cron) is always themed.
	 *
	 * @param string|null $appId The resolved app id, or null.
	 *
	 * @return bool True when theming must be skipped.
	 *
	 * @spec openspec/changes/archive/2026-06-14-per-app-theming-toggle/tasks.md#task-1.1
	 */
	public function isThemingDisabledFor(?string $appId): bool {
		if ($appId === null || $appId === '') {
			return false;
		}

		return in_array($appId, $this->getDisabledApps(), true);
	}//end isThemingDisabledFor()

	/**
	 * Resolve the app id being rendered from a request path.
	 *
	 * Matches `/apps/{appid}` with or without an `index.php` prefix. Any path
	 * that is not an app page (login, /settings, /s/{token}, dav, ocs, …) and
	 * any unparseable input resolves to null — which keeps the page themed.
	 *
	 * @param string|null $pathInfo The request path info.
	 *
	 * @return string|null The app id, or null when the path is not an app page.
	 *
	 * @spec openspec/changes/archive/2026-06-14-per-app-theming-toggle/tasks.md#task-1.2
	 */
	public function resolveAppIdFromPath(?string $pathInfo): ?string {
		if ($pathInfo === null || $pathInfo === '') {
			return null;
		}

		if (preg_match('#^/?(?:index\.php/)?apps/([a-zA-Z0-9_]+)#', $pathInfo, $matches) === 1) {
			return $matches[1];
		}

		return null;
	}//end resolveAppIdFromPath()

	/**
	 * List enabled apps with their current themed state, for the admin UI/API.
	 *
	 * Protected ids are omitted. Each entry is { id, name, themed }, sorted by
	 * display name. "themed" is true when the app is NOT in the exclusion list.
	 *
	 * @return array<array{id: string, name: string, themed: bool}> The app list.
	 *
	 * @spec openspec/changes/archive/2026-06-14-per-app-theming-toggle/tasks.md#task-3.1
	 */
	public function getThemableApps(): array {
		$disabled = $this->getDisabledApps();
		// `getInstalledApps()` — NOT `getEnabledApps()`. Despite the name it
		// returns the ENABLED app ids, and it is the only one of the two that
		// exists across the whole range appinfo/info.xml claims support for
		// (Nextcloud 28-34). `IAppManager::getEnabledApps()` was added later:
		// it is absent from OC\App\AppManager on 31 and present on 34, so the
		// call worked on the NC 34 dev container and threw "Call to undefined
		// method OC\App\AppManager::getEnabledApps()" on every supported older
		// server — a hard 500 for GET /settings/app-theming, i.e. the per-app
		// theming admin panel was dead on those versions. First seen in CI on
		// stable31, run 30889958278 job 91929658882; the browser symptom was
		// the panel's fetch receiving an HTML error page, reported as
		// "Error loading app theming: SyntaxError: Unexpected token '<'".
		$enabled = $this->appManager->getInstalledApps();

		$apps = [];
		foreach ($enabled as $appId) {
			if (in_array($appId, self::PROTECTED_IDS, true) === true) {
				continue;
			}

			$apps[] = [
				'id' => $appId,
				'name' => $this->resolveDisplayName(appId: $appId),
				'themed' => (in_array($appId, $disabled, true) === false),
			];
		}

		usort($apps, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

		return $apps;
	}//end getThemableApps()

	/**
	 * Resolve a human display name for an app id, falling back to the id.
	 *
	 * @param string $appId The app id.
	 *
	 * @return string The display name.
	 *
	 * @spec openspec/changes/archive/2026-06-14-per-app-theming-toggle/tasks.md#task-3.1
	 */
	private function resolveDisplayName(string $appId): string {
		$info = $this->appManager->getAppInfo($appId);
		if (is_array($info) === true && isset($info['name']) === true) {
			$name = $info['name'];
			if (is_array($name) === true) {
				// Localized info.xml names may parse to an array keyed by language.
				$name = reset($name);
			}

			if (is_string($name) === true && $name !== '') {
				return $name;
			}
		}

		return $appId;
	}//end resolveDisplayName()
}//end class
