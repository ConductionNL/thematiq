<?php

/**
 * Cache for a shipped token set's audit warnings.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category Service
 * @package  OCA\Thematiq\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use FilesystemIterator;
use OCA\Thematiq\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\ICache;
use OCP\ICacheFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Keeps a shipped set's audit warnings until one of their inputs changes.
 *
 * The contrast, vocabulary and font audits read every stylesheet under
 * `css/` for every set. `Capabilities` reached them on every page through
 * {@see TokenSetService::getAvailableTokenSets()}: measured 2026-10-06 at
 * ~1.5 s per call for 61 sets, on every request of the instance.
 *
 * The audits are pure functions of the release files, the set's merged
 * manifest entry and its theming block, so the key is built from exactly
 * those, with the release files folded into one fingerprint.
 *
 * @spec openspec/specs/token-sets/spec.md
 */
class AuditWarningsCache {

	/**
	 * How long the release fingerprint is trusted before the `css/` tree is
	 * walked again. Matches the instance's opcache revalidation window, so a
	 * stylesheet edited on a development checkout reaches the audit as soon
	 * as the PHP that reads it does.
	 */
	private const FINGERPRINT_TTL_SECONDS = 60;

	/**
	 * How long warnings are kept. The key changes with every input, so this
	 * only bounds how long an orphaned entry lingers.
	 */
	private const WARNINGS_TTL_SECONDS = 86400;

	/**
	 * Distributed cache for the warnings.
	 *
	 * @var ICache
	 */
	private ICache $warnings;

	/**
	 * Per-server cache for the release fingerprint. Local, not distributed:
	 * it describes this server's copy of the app's files.
	 *
	 * @var ICache
	 */
	private ICache $fingerprints;

	/**
	 * The release fingerprint, memoised for the rest of this request.
	 *
	 * @var string|null
	 */
	private ?string $fingerprint = null;

	/**
	 * Constructor.
	 *
	 * @param ICacheFactory $cacheFactory Creates the two caches.
	 * @param IAppManager $appManager Reports the app version.
	 */
	public function __construct(
		ICacheFactory $cacheFactory,
		private readonly IAppManager $appManager,
	) {
		$this->warnings = $cacheFactory->createDistributed(prefix: 'thematiq_set_warnings');
		$this->fingerprints = $cacheFactory->createLocal(prefix: 'thematiq_release_fingerprint');
	}//end __construct()

	/**
	 * The warnings for one shipped set, computed only when not cached.
	 *
	 * @param string $appPath The app directory path.
	 * @param array<int, mixed> $inputs Every per-set input the audits read (id, design system, theming, meta).
	 * @param callable(): array<int, mixed> $compute Runs the audits.
	 *
	 * @return array<int, mixed> The warnings; empty when the set passes every audit.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	public function remember(string $appPath, array $inputs, callable $compute): array {
		$key = hash('sha256', (string)json_encode([$this->releaseFingerprint(appPath: $appPath), $inputs]));
		$cached = $this->warnings->get($key);
		if (is_array($cached) === true) {
			return $cached;
		}

		$warnings = $compute();
		$this->warnings->set($key, $warnings, self::WARNINGS_TTL_SECONDS);

		return $warnings;
	}//end remember()

	/**
	 * A fingerprint of the release files the audits read: the app version
	 * plus the path, size and mtime of both manifests and of every stylesheet
	 * under `css/`.
	 *
	 * The version alone would do in production, where files change only on
	 * an upgrade. A development checkout is edited in place, so the files
	 * count too. Walking ~160 files costs ~20 ms, hence the per-server cache.
	 *
	 * @param string $appPath The app directory path.
	 *
	 * @return string The fingerprint.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	private function releaseFingerprint(string $appPath): string {
		if ($this->fingerprint !== null) {
			return $this->fingerprint;
		}

		$cached = $this->fingerprints->get($appPath);
		if (is_string($cached) === true && $cached !== '') {
			$this->fingerprint = $cached;
			return $cached;
		}

		$parts = [$this->appManager->getAppVersion(appId: Application::APP_ID)];
		foreach (['token-sets.json', 'design-systems.json'] as $manifest) {
			$parts[] = $this->describe(file: new SplFileInfo($appPath . '/' . $manifest));
		}

		if (is_dir($appPath . '/css') === true) {
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($appPath . '/css', FilesystemIterator::SKIP_DOTS)
			);
			foreach ($files as $file) {
				if ($file instanceof SplFileInfo && $file->getExtension() === 'css') {
					$parts[] = $this->describe(file: $file);
				}
			}
		}

		sort($parts);
		$fingerprint = hash('sha256', implode("\n", $parts));
		$this->fingerprints->set($appPath, $fingerprint, self::FINGERPRINT_TTL_SECONDS);
		$this->fingerprint = $fingerprint;

		return $fingerprint;
	}//end releaseFingerprint()

	/**
	 * One file's line in the fingerprint: path, mtime and size, or the path
	 * alone when the file is absent.
	 *
	 * @param SplFileInfo $file The file.
	 *
	 * @return string The line.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	private function describe(SplFileInfo $file): string {
		if ($file->isFile() === false) {
			return $file->getPathname();
		}

		return $file->getPathname() . ':' . $file->getMTime() . ':' . $file->getSize();
	}//end describe()
}//end class
