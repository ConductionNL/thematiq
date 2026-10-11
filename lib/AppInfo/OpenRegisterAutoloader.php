<?php

/**
 * The ADR-040 OpenRegister autoload prelude, as one named, testable unit.
 *
 * @category  AppInfo
 * @package   OCA\Thematiq\AppInfo
 * @author    Conduction B.V. <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Thematiq\AppInfo;

use OCP\App\IAppManager;
use OCP\Server;
use Throwable;

/**
 * Makes `OCA\OpenRegister\*` resolvable during this app's `register()`.
 *
 * WHY THIS EXISTS
 * ---------------
 * `Coordinator::registerApps()` walks the SORTED app list, registering each
 * app's autoloading and then calling `register()` for one app at a time.
 * Under the app's OLD id, `nldesign` sorted before `openregister`, so
 * `Application::register()` ran while the `OCA\OpenRegister\` PSR-4 prefix did
 * not yet exist — on a completely healthy instance with OpenRegister installed
 * and enabled.
 *
 * Any `class_exists()` probe on an OpenRegister class in that window answers
 * FALSE and the branch it guards silently takes the wrong path, permanently
 * and with no error anywhere. See ADR-040.
 *
 * Under the new id, `thematiq` sorts AFTER `openregister`, so that window is
 * normally closed — but app sort order is not a contract to rely on and this
 * call is idempotent and cheap, so the prelude stays.
 *
 * PUBLIC API ONLY (NEXTCLOUD 35)
 * ------------------------------
 * This used to call `\OC_App::registerAutoloading()`. That is private API and
 * Nextcloud 35 REMOVED it (moved to the equally private
 * `OC\App\AppManager::registerAutoloading()`). The resulting `\Error` landed in
 * the catch below, `ensure()` returned false and federated config sharing was
 * switched off in silence. For an app shipping its own `vendor/autoload.php` —
 * which OpenRegister does — Nextcloud's registration reduces to a PSR-4 prefix
 * over `lib/`, so that is exactly what is registered here, with
 * `spl_autoload_register()` and the public `IAppManager`. Same approach as
 * keepiq#712.
 *
 * OpenRegister's `vendor/autoload.php` is deliberately NOT required: that would
 * pull its whole dependency tree into this process, where versions differing
 * from ours would win first-come. Deliberately NOT `IAppManager::loadApp()`
 * either: that boots OpenRegister before its own `register()` has run.
 *
 * WHY IT IS A CLASS AND NOT FOUR INLINE LINES
 * -------------------------------------------
 * It is independently unit-testable, which inline lines inside `register()`
 * are not — `Application` cannot be constructed in a unit test without a
 * server container. And `ensure()` is deliberately an INSTANCE method so the
 * call site in `register()` is an ordinary method call: the PHPMD StaticAccess
 * suppression below then covers exactly one class, and the rule keeps its
 * teeth in the bootstrap and everywhere else.
 *
 * @SuppressWarnings(PHPMD.StaticAccess) - `\OCP\Server::get()` is the public
 * service locator, and this runs at the composition root where no container
 * is available to inject from.
 *
 * @spec openspec/specs/federated-config-sharing/spec.md
 */
final class OpenRegisterAutoloader {

	/**
	 * The app whose autoloading this prelude pulls in.
	 *
	 * @var string
	 */
	public const OPENREGISTER_APP_ID = 'openregister';

	/**
	 * The PSR-4 namespace prefix OpenRegister's `lib/` serves.
	 *
	 * @var string
	 */
	private const OPENREGISTER_NAMESPACE = 'OCA\\OpenRegister\\';

	/**
	 * The registered loader, or null when none is on the SPL chain.
	 *
	 * Static on purpose: the SPL chain is process-wide, so one loader per
	 * process however many instances call `ensure()`.
	 *
	 * @var (\Closure(string): void)|null
	 */
	private static ?\Closure $loader = null;

	/**
	 * Register OpenRegister's PSR-4 prefix with the running process.
	 *
	 * Idempotent: a second call while registered is a no-op. Every failure is
	 * swallowed on purpose: OpenRegister is a SOFT dependency, and "absent or
	 * disabled" is a supported state in which the caller's `class_exists()`
	 * guard then answers FALSE truthfully.
	 *
	 * @param IAppManager|null $appManager Injected for tests; resolved from the
	 *                                     server container when omitted.
	 *
	 * @return bool True when the prefix is registered, false when OpenRegister
	 *              is absent, disabled, or otherwise unreachable.
	 *
	 * @spec openspec/specs/federated-config-sharing/spec.md
	 */
	public function ensure(?IAppManager $appManager = null): bool {
		try {
			$manager = ($appManager ?? Server::get(IAppManager::class));

			// Checked before the short-circuit: under a long-lived worker the
			// static loader outlives the request, and an OpenRegister disabled
			// since must not stay wired.
			if ($manager->isEnabledForAnyone(self::OPENREGISTER_APP_ID) === false) {
				self::unregister();
				return false;
			}

			if (self::$loader !== null) {
				return true;
			}

			$path = rtrim($manager->getAppPath(self::OPENREGISTER_APP_ID), '/');
			if (is_dir($path . '/lib') === false) {
				return false;
			}

			self::$loader = static function (string $class) use ($path): void {
				$file = self::classFile(appPath: $path, class: $class);
				if ($file !== null && is_file($file) === true) {
					include_once $file;
				}
			};
			spl_autoload_register(self::$loader);

			return true;
		} catch (Throwable) {
			// OpenRegister absent, disabled, or the server container is not
			// available (unit tests). The caller degrades as designed. Never
			// rethrow: an exception escaping here would abort the caller's
			// entire register().
			return false;
		}//end try
	}//end ensure()

	/**
	 * Take the loader off the SPL chain (tests, and OpenRegister disabled).
	 *
	 * @return void
	 */
	public static function unregister(): void {
		if (self::$loader !== null) {
			spl_autoload_unregister(self::$loader);
			self::$loader = null;
		}
	}//end unregister()

	/**
	 * Map an `OCA\OpenRegister\…` class name to its file under `lib/`.
	 *
	 * Returns null for any class that is not OpenRegister's, so this loader
	 * never answers for (and shadows) names another loader owns.
	 *
	 * @param string $appPath Absolute path to the openregister app, no trailing slash.
	 * @param string $class   The fully qualified class name being resolved.
	 *
	 * @return string|null The candidate file, or null when not OpenRegister's.
	 */
	public static function classFile(string $appPath, string $class): ?string {
		if (str_starts_with($class, self::OPENREGISTER_NAMESPACE) === false) {
			return null;
		}

		$relative = substr($class, strlen(self::OPENREGISTER_NAMESPACE));
		if ($relative === '') {
			return null;
		}

		return $appPath . '/lib/' . str_replace('\\', '/', $relative) . '.php';
	}//end classFile()
}//end class
