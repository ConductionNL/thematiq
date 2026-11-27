<?php

/**
 * The signed-in person's apps menu walkthrough state.
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
 * @spec openspec/specs/apps-menu-walkthrough/spec.md#requirement-finishing-or-closing-records-the-seen-version-req-tqwt-004
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IConfig;

/**
 * Reads and writes the two tour keys as user values of thematiq, and names the version core of the
 * installed app, which the tour compares step versions against.
 *
 * @spec openspec/specs/apps-menu-walkthrough/spec.md#requirement-finishing-or-closing-records-the-seen-version-req-tqwt-004
 */
class WalkthroughPreferences {

	/**
	 * The seen version of the apps menu tour.
	 *
	 * @var string
	 */
	public const SEEN = 'apps_menu_walkthrough_seen_version';

	/**
	 * The progress of the apps menu tour (the engine's `<key>-progress`).
	 *
	 * @var string
	 */
	public const PROGRESS = 'apps_menu_walkthrough_seen_version-progress';

	/**
	 * The keys the preference routes serve; nothing else.
	 *
	 * @var array<int, string>
	 */
	public const KEYS = [self::SEEN, self::PROGRESS];

	/**
	 * The longest value stored, in bytes: a version or a small progress object.
	 *
	 * @var int
	 */
	public const MAX_LENGTH = 1024;

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The user values.
	 * @param IAppManager $appManager The installed version.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly IAppManager $appManager,
	) {
	}//end __construct()

	/**
	 * Whether the routes serve a key.
	 *
	 * @param string $key The key.
	 *
	 * @return bool
	 *
	 * @spec openspec/specs/apps-menu-walkthrough/spec.md#requirement-finishing-or-closing-records-the-seen-version-req-tqwt-004
	 */
	public function serves(string $key): bool {
		return in_array($key, self::KEYS, true);
	}//end serves()

	/**
	 * A person's value of a tour key, or null when none is stored.
	 *
	 * @param string $userId The person.
	 * @param string $key One of {@see self::KEYS}.
	 *
	 * @return string|null
	 *
	 * @spec openspec/specs/apps-menu-walkthrough/spec.md#requirement-finishing-or-closing-records-the-seen-version-req-tqwt-004
	 */
	public function get(string $userId, string $key): ?string {
		if ($this->serves(key: $key) === false) {
			return null;
		}

		$value = $this->config->getUserValue($userId, Application::APP_ID, $key, '');
		if ($value === '') {
			return null;
		}

		return $value;
	}//end get()

	/**
	 * Store a person's value of a tour key; an empty value removes it.
	 *
	 * @param string $userId The person.
	 * @param string $key One of {@see self::KEYS}.
	 * @param string $value The value, at most {@see self::MAX_LENGTH} bytes.
	 *
	 * @return string|null The stored value, null when removed.
	 *
	 * @spec openspec/specs/apps-menu-walkthrough/spec.md#requirement-finishing-or-closing-records-the-seen-version-req-tqwt-004
	 */
	public function set(string $userId, string $key, string $value): ?string {
		if ($this->serves(key: $key) === false) {
			return null;
		}

		$value = substr($value, 0, self::MAX_LENGTH);
		if ($value === '') {
			$this->config->deleteUserValue($userId, Application::APP_ID, $key);
			return null;
		}

		$this->config->setUserValue($userId, Application::APP_ID, $key, $value);
		return $value;
	}//end set()

	/**
	 * The tour part of the menu's initial state for a person: seen version, progress and the
	 * installed version core.
	 *
	 * @param string|null $userId The signed-in person, or null.
	 *
	 * @return array{seen: string, progress: string, appVersion: string}
	 *
	 * @spec openspec/specs/apps-menu-walkthrough/spec.md#requirement-the-tour-starts-on-the-first-open-of-the-apps-menu-req-tqwt-002
	 */
	public function state(?string $userId): array {
		$seen = '';
		$progress = '';
		if ($userId !== null) {
			$seen = ($this->get(userId: $userId, key: self::SEEN) ?? '');
			$progress = ($this->get(userId: $userId, key: self::PROGRESS) ?? '');
		}

		return [
			'seen' => self::versionCore(version: $seen),
			'progress' => $progress,
			'appVersion' => self::versionCore(version: $this->appManager->getAppVersion(Application::APP_ID)),
		];
	}//end state()

	/**
	 * The version core: `major.minor.patch` without a pre-release or build suffix, so a nightly
	 * and its release compare equal. Anything that is not a version reads as empty.
	 *
	 * @param string $version The version, such as `1.2.48-unstable.20261127094823`.
	 *
	 * @return string Such as `1.2.48`, or ''.
	 *
	 * @spec openspec/specs/apps-menu-walkthrough/spec.md#requirement-a-new-menu-feature-gets-its-own-step-after-an-upgrade-req-tqwt-005
	 */
	public static function versionCore(string $version): string {
		if (preg_match('/^\s*v?(\d+)(?:\.(\d+))?(?:\.(\d+))?/', $version, $match) !== 1) {
			return '';
		}

		$parts = [$match[1]];
		foreach ([2, 3] as $index) {
			$part = ($match[$index] ?? '');
			if ($part === '') {
				$part = '0';
			}

			$parts[] = $part;
		}

		return implode('.', $parts);
	}//end versionCore()
}//end class
