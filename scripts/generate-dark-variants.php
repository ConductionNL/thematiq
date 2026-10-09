#!/usr/bin/env php
<?php

/**
 * Generate Dark Variants Script (CI-only, no Nextcloud instance required).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * Regenerates every eligible token set's `css/tokens/dark/{id}.css` using
 * OCA\Thematiq\Service\DarkPaletteService directly (no NC server bootstrap —
 * only a minimal stub IAppManager that resolves the app path to this repo
 * root). Wired into .github/workflows/sync-tokens.yml so upstream token
 * updates regenerate their dark variants in the same PR (see
 * openspec/specs/dark-mode/spec.md, tasks.md#task-2.4).
 *
 * Usage: php scripts/generate-dark-variants.php [--force] [--only <set-id>]
 *
 * --only regenerates one set, which is what `convert-nlds-theme.mjs --write` calls after it
 * writes a light file, so a conversion never rewrites the dark files of other sets.
 * Exit codes: 0 on success (including per-set skips), 1 on any write failure.
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

// The `nextcloud/ocp` dev dependency ships no autoload entry of its own (see
// tests/bootstrap.php for the identical fleet-standard registration), so the
// OCP\* namespace must be wired up manually before any OCP interface (here,
// OCP\App\IAppManager) can be implemented/autoloaded outside phpunit.
spl_autoload_register(
        static function (string $class): void {
            $prefixes = [
                'OCP\\' => __DIR__.'/../vendor/nextcloud/ocp/OCP/',
                'NCU\\' => __DIR__.'/../vendor/nextcloud/ocp/NCU/',
            ];
            foreach ($prefixes as $prefix => $baseDir) {
                if (str_starts_with($class, $prefix) === false) {
                    continue;
                }

                $path = $baseDir.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
                if (is_file($path) === true) {
                    include_once $path;
                }

                return;
            }
        }
        );

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DarkPaletteService;
use OCP\App\IAppManager;
use Psr\Log\AbstractLogger;

/**
 * Minimal IAppManager stub — only getAppPath() is ever called by
 * DarkPaletteService, and it always resolves to this script's repo root.
 */
final class CiAppManagerStub implements IAppManager
{
    // Return type declared explicitly: OCP\App\IAppManager::getAppInfo() is
    // `: ?array` and PHP treats a missing return type on the implementation as
    // incompatible, so without it this script dies with a fatal before it
    // generates anything — which reads as "dark variants are broken" rather
    // than "a stub drifted from the interface it implements".
    public function getAppInfo(string $appId, bool $path=false, $lang=null): ?array
    {
        return [];
    }//end getAppInfo()

    public function getAppInfoByPath(string $path, ?string $lang=null): ?array
    {
        return null;
    }//end getAppInfoByPath()

    // Added in Nextcloud 34 (IAppManager @since 34.0.0). A stub only satisfies
    // an interface as of the version it was written against; these two are the
    // reason this script stopped running, and the failure surfaced as a fatal
    // at class-declaration time rather than as anything about dark variants.
    public function getAppNamespace(string $appId): string
    {
        return '';
    }//end getAppNamespace()

    public function getAppFromNamespace(string $className): ?string
    {
        return null;
    }//end getAppFromNamespace()

    public function getAppVersion(string $appId, bool $useCache=true): string
    {
        return '0';
    }//end getAppVersion()

    public function getAppInstalledVersions(bool $onlyEnabled=false): array
    {
        return [];
    }//end getAppInstalledVersions()

    public function getAppIcon(string $appId, bool $dark=false): ?string
    {
        return null;
    }//end getAppIcon()

    public function isEnabledForUser($appId, $user=null)
    {
        return true;
    }//end isEnabledForUser()

    public function isInstalled($appId)
    {
        return true;
    }//end isInstalled()

    public function isEnabledForAnyone(string $appId): bool
    {
        return true;
    }//end isEnabledForAnyone()

    public function isDefaultEnabled(string $appId): bool
    {
        return false;
    }//end isDefaultEnabled()

    public function loadApp(string $app): void
    {
    }//end loadApp()

    public function isAppLoaded(string $app): bool
    {
        return true;
    }//end isAppLoaded()

    public function enableApp(string $appId, bool $forceEnable=false): void
    {
    }//end enableApp()

    public function hasProtectedAppType($types)
    {
        return false;
    }//end hasProtectedAppType()

    public function enableAppForGroups(string $appId, array $groups, bool $forceEnable=false): void
    {
    }//end enableAppForGroups()

    public function disableApp($appId, $automaticDisabled=false): void
    {
    }//end disableApp()

    public function getAppPath(string $appId, bool $ignoreCache=false): string
    {
        return dirname(__DIR__);
    }//end getAppPath()

    public function getAppWebPath(string $appId): string
    {
        return '';
    }//end getAppWebPath()

    public function getEnabledAppsForUser(\OCP\IUser $user)
    {
        return [];
    }//end getEnabledAppsForUser()

    public function getInstalledApps()
    {
        return [];
    }//end getInstalledApps()

    public function getEnabledApps(): array
    {
        return [];
    }//end getEnabledApps()

    public function clearAppsCache(): void
    {
    }//end clearAppsCache()

    public function isShipped($appId)
    {
        return true;
    }//end isShipped()

    public function loadApps(array $types=[]): bool
    {
        return true;
    }//end loadApps()

    public function isType(string $app, array $types): bool
    {
        return false;
    }//end isType()

    public function getAlwaysEnabledApps()
    {
        return [];
    }//end getAlwaysEnabledApps()

    public function getDefaultEnabledApps(): array
    {
        return [];
    }//end getDefaultEnabledApps()

    public function getEnabledAppsForGroup(\OCP\IGroup $group): array
    {
        return [];
    }//end getEnabledAppsForGroup()

    public function getAppRestriction(string $appId): array
    {
        return [];
    }//end getAppRestriction()

    public function getDefaultAppForUser(?\OCP\IUser $user=null, bool $withFallbacks=true): string
    {
        return '';
    }//end getDefaultAppForUser()

    public function getDefaultApps(): array
    {
        return [];
    }//end getDefaultApps()

    public function setDefaultApps(array $defaultApps): void
    {
    }//end setDefaultApps()

    public function isBackendRequired(string $backend): bool
    {
        return false;
    }//end isBackendRequired()

    public function cleanAppId(string $app): string
    {
        return $app;
    }//end cleanAppId()

    public function getAllAppsInAppsFolders(): array
    {
        return [];
    }//end getAllAppsInAppsFolders()

    public function upgradeApp(string $appId): bool
    {
        return true;
    }//end upgradeApp()

    public function isUpgradeRequired(string $appId): bool
    {
        return false;
    }//end isUpgradeRequired()

    public function isAppCompatible(string $serverVersion, array $appInfo, bool $ignoreMax=false): bool
    {
        return true;
    }//end isAppCompatible()
}//end class

/**
 * Minimal stderr logger.
 */
final class CiStderrLogger extends AbstractLogger
{
    public function log($level, $message, array $context=[]): void
    {
        $rendered = (string) $message;
        foreach ($context as $key => $value) {
            $rendered = str_replace('{'.$key.'}', (string) $value, $rendered);
        }

        fwrite(STDERR, "[$level] $rendered\n");
    }//end log()
}//end class

$force = in_array('--force', $argv, true);

$service = new DarkPaletteService(
    new ContrastService(),
    new CssParserService(),
    new CiAppManagerStub(),
    new CiStderrLogger()
);

$onlyIndex = array_search('--only', $argv, true);
$only      = ($onlyIndex === false) ? null : ($argv[($onlyIndex + 1)] ?? '');
if ($only === '' || ($only !== null && preg_match('/^[a-z0-9][a-z0-9-]*$/', $only) !== 1)) {
    fwrite(STDERR, "--only needs a token set id\n");
    exit(1);
}

$results = ($only === null) ? $service->generateAll(force: $force) : [$only => $service->generateAndWrite(setId: $only, force: $force)];

$written = 0;
$failed  = false;
foreach ($results as $setId => $result) {
    if ($result['written'] === true) {
        $written++;
        echo "$setId: written\n";
    } else if ($result['reason'] === 'write-failed') {
        $failed = true;
        echo "$setId: WRITE FAILED\n";
    } else {
        echo "$setId: skipped ({$result['reason']})\n";
    }
}

echo "\nTotal written: $written / ".count($results)."\n";

exit($failed ? 1 : 0);
