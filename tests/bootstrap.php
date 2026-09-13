<?php

declare(strict_types=1);

define('PHPUNIT_RUN', 1);

require_once __DIR__ . '/../vendor/autoload.php';

// vendor/ is a symlink into a shared checkout (e.g. a git worktree's vendor/
// points at the main checkout so `composer install` never has to run twice).
// Composer's generated autoload_psr4.php computes its base directory from
// __DIR__, which PHP resolves through that symlink — so without this fix the
// OCA\Thematiq\ prefix would silently load classes from the SHARED checkout
// instead of THIS checkout, masking any change made only here (new files
// would 404 as "class not found"; edited files would test stale code). Force
// the prefix back onto this checkout's own lib/ so tests always exercise the
// code actually present on disk here.
//
// `require_once` above may return `true` rather than the loader (PHPUnit's own
// bootstrap already required this exact file once), so the already-registered
// ClassLoader singleton is located via spl_autoload_functions() instead of
// trusting the require_once return value.
//
// Fixing the PSR-4 prefix alone is not enough: `optimize-autoloader` (see
// composer.json) also bakes every already-known OCA\Thematiq\ class into a
// classMap with an absolute path into the SHARED checkout, and the classMap
// lookup wins over PSR-4 in Composer's ClassLoader::findFile(). Strip those
// entries via reflection so every Thematiq class — new AND edited — falls
// through to the (now-corrected) PSR-4 rule and loads from THIS checkout.
//
// THE PREFIX BELOW IS `OCA\Thematiq\`, NOT `OCA\NLDesign\`. It kept naming the
// pre-rename namespace, which no class has used since `nldesign` -> `thematiq`.
// That did not fail — it did nothing at all: the PSR-4 rule was registered for
// a namespace with no classes, and the classMap loop matched no entries, so
// every real OCA\Thematiq\ classMap entry survived and kept pointing at the
// SHARED checkout. The protection this whole block exists to provide was
// therefore silently absent for exactly the namespace it was meant to cover,
// and the symptom is the one described above: in a worktree whose vendor/ is a
// symlink, a NEW file 404s as "class not found" and an EDITED file tests the
// shared checkout's stale copy while reporting a pass.
foreach (spl_autoload_functions() as $autoloadFunction) {
	if (is_array($autoloadFunction) === false || ($autoloadFunction[0] instanceof \Composer\Autoload\ClassLoader) === false) {
		continue;
	}

	$loader = $autoloadFunction[0];
	$loader->setPsr4('OCA\\Thematiq\\', [__DIR__ . '/../lib']);

	$classMapProperty = (new \ReflectionClass($loader))->getProperty('classMap');
	$classMapProperty->setAccessible(true);
	$classMap = $classMapProperty->getValue($loader);
	foreach (array_keys($classMap) as $mappedClass) {
		if (str_starts_with($mappedClass, 'OCA\\Thematiq\\') === true) {
			unset($classMap[$mappedClass]);
		}
	}

	$classMapProperty->setValue($loader, $classMap);
	break;
}

// Register the OCP/NCU namespaces from the nextcloud/ocp dev dependency so that
// PHPUnit can mock OCP interfaces (e.g. OCP\IConfig, OCP\App\IAppManager) when
// no full Nextcloud server is present (standalone container runs). The
// nextcloud/ocp package ships no autoload entry of its own, so we register it
// manually — mirroring the fleet-standard bootstrap (decidesk, larpingapp, …).
// Registered as a fallback autoloader (appended) so a real Nextcloud
// environment's own OCP classes always win when present.
spl_autoload_register(static function (string $class): void {
	$prefixes = [
		'OCP\\' => __DIR__ . '/../vendor/nextcloud/ocp/OCP/',
		'NCU\\' => __DIR__ . '/../vendor/nextcloud/ocp/NCU/',
	];
	foreach ($prefixes as $prefix => $baseDir) {
		if (str_starts_with($class, $prefix) === false) {
			continue;
		}

		$path = $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
		if (is_file($path) === true) {
			require_once $path;
		}

		return;
	}
});

// Server-internal `OC\` classes (e.g. OC\Mail\EMailTemplate, extended by
// lib/Mail/NLDesignEMailTemplate.php) are not part of the nextcloud/ocp dev
// dependency — there is no public stub package for private-namespace code.
// When the full Nextcloud server IS mounted (real environment, or a
// standalone container with the server's lib/private/ bind-mounted at
// /var/www/html/lib/private per the fleet-standard phpunit invocation), fall
// back to loading the class directly from there. Absent that mount this is a
// silent no-op — mirrors the OCP/NCU registration above, and any test that
// actually needs the class simply fails with a clear "class not found"
// rather than the bootstrap itself failing.
spl_autoload_register(static function (string $class): void {
	if (str_starts_with($class, 'OC\\') === false) {
		return;
	}

	$path = '/var/www/html/lib/private/' . str_replace('\\', '/', substr($class, strlen('OC\\'))) . '.php';
	if (is_file($path) === true) {
		require_once $path;
	}
});

/**
 * Tell whether a Nextcloud root is an INSTALLED instance, not just a source tree.
 *
 * `lib/base.php` from a source tree that was never installed (the workspace
 * checkout above apps-extra/ has a 0-byte config/config.php) still declares
 * `OC` and builds `\OC::$server` before it throws "Not installed". That server
 * cannot be undone (`OC::$server` is a typed static), so from then on every
 * `\OC::$server->get()` in the code under test hits a container that knows
 * none of this app's registrations and autowires from scratch; constructor
 * cycles then recurse until memory runs out (19 GB and 6 GB of swap in one
 * openregister run on 2026-09-08). So the decision has to be made BEFORE
 * base.php is loaded, and the only cheap signal is the `installed` flag in
 * config/config.php.
 *
 * @param string $ncRoot Candidate Nextcloud root.
 *
 * @return bool True when config/config.php declares `installed => true`.
 */
function thematiq_nc_root_is_installed(string $ncRoot): bool
{
	$configFile = $ncRoot . '/config/config.php';
	if (is_file($configFile) === false || filesize($configFile) === 0) {
		return false;
	}

	// The config file is a plain `$CONFIG = [...]` script; including it in a
	// closure keeps `$CONFIG` out of the global scope.
	$config = (static function () use ($configFile): array {
		$CONFIG = [];
		try {
			include $configFile;
		} catch (\Throwable) {
			return [];
		}

		if (is_array($CONFIG) === false) {
			return [];
		}

		return $CONFIG;
	})();

	return ($config['installed'] ?? false) === true;
}//end thematiq_nc_root_is_installed()

// Only an INSTALLED root is booted; a bare source tree runs in pure-unit mode
// with the composer autoload and the stubs above. NC's tests/autoload.php
// requires lib/base.php itself, so it sits behind the same guard.
if (!defined('OC_CONSOLE')) {
	$thematiqNcRoot = realpath(__DIR__ . '/../../..');
	if ($thematiqNcRoot !== false && file_exists($thematiqNcRoot . '/lib/base.php') === true) {
		if (thematiq_nc_root_is_installed($thematiqNcRoot) === true) {
			try {
				require_once $thematiqNcRoot . '/lib/base.php';

				if (file_exists($thematiqNcRoot . '/tests/autoload.php') === true) {
					require_once $thematiqNcRoot . '/tests/autoload.php';
				}
			} catch (\Throwable $e) {
				// The tree IS installed, so the dangerous case this guard exists for
				// (loading a bare source tree) did not happen. base.php still failed
				// part-way.
				//
				// This does NOT abort. `OC::$server` is a typed static, so a half-built
				// container cannot be unset, and aborting was tried: it turned all six
				// PHPUnit legs red on a suite that passes (humaniq, 2026-09-08). The
				// runaway this guard exists for needs an autowiring lookup to reach the
				// poisoned container, this app has none in lib, and phpunit.xml's 2G cap
				// bounds one anyway.
				//
				// So: say plainly that the container is unreliable, and let the pure unit
				// tests run. A container-bound test failing loudly is the intended outcome.
				fwrite(
					STDERR,
					sprintf(
						"[thematiq/tests/bootstrap] Nextcloud at %s could not finish booting (%s).\n"
						. "  \\OC::\$server now holds a HALF-BUILT container and cannot be unset. Pure unit tests\n"
						. "  continue; anything resolving a service from that container is UNVERIFIED by this run.\n",
						$thematiqNcRoot,
						$e->getMessage()
					)
				);
			}
		} else {
			fwrite(
				STDERR,
				sprintf(
					"[thematiq/tests/bootstrap] Nextcloud root at %s is not an installed instance (config/config.php lacks installed => true); "
					. "skipping lib/base.php and running with composer autoload only (pure-unit mode).\n",
					$thematiqNcRoot
				)
			);
		}
	}

	unset($thematiqNcRoot);

	if (class_exists('\OC_App')) {
		\OC_App::loadApps();
		// The APP ID, which is `thematiq` since 2026-08-22. Loading `nldesign`
		// asked the server for an app that no longer exists — a second silent
		// no-op alongside the PSR-4 prefix above, leaving this app's own
		// autoloader, container registrations and l10n unloaded for the suite.
		\OC_App::loadApp('thematiq');
		OC_Hook::clear();
	}
}
