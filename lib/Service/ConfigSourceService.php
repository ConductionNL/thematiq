<?php

/**
 * Thematiq declarative configuration source.
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
 * @spec openspec/specs/theme-as-code/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\IConfig;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use RuntimeException;

/**
 * Applies the branding package named in config.php whenever it changes.
 *
 * `thematiq.config_source` in config.php names a package directory or ZIP
 * that the deployment (Argo CD, Helm, Ansible) puts on disk. The app never
 * fetches anything itself. {@see applyIfChanged()} hashes the package and
 * applies it only when the hash differs from the last applied one; it runs
 * after every upgrade (repair step), from a background job and from
 * `occ thematiq:config:apply`.
 *
 * Drift is measured against the configuration the last apply produced: a
 * fingerprint of the running configuration is stored right after a
 * successful apply, and any later web change makes the fingerprint differ.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */
class ConfigSourceService {

	/**
	 * The config.php key naming the package path.
	 *
	 * @var string
	 */
	public const SOURCE_KEY = 'thematiq.config_source';

	/**
	 * The config.php key that makes the settings read-only while a source is set.
	 *
	 * @var string
	 */
	public const LOCK_KEY = 'thematiq.config_source_lock';

	/**
	 * The Nextcloud lock that keeps two replicas from applying at once.
	 *
	 * @var string
	 */
	public const LOCK_NAME = 'thematiq-config-source';

	/**
	 * App config keys this service keeps.
	 *
	 * @var string
	 */
	private const KEY_HASH = 'config_source_applied_hash';

	/**
	 * @var string
	 */
	private const KEY_REVISION = 'config_source_applied_revision';

	/**
	 * @var string
	 */
	private const KEY_APPLIED_AT = 'config_source_applied_at';

	/**
	 * @var string
	 */
	private const KEY_STATE = 'config_source_applied_state';

	/**
	 * @var string
	 */
	private const KEY_ERROR = 'config_source_last_error';

	/**
	 * Constructor.
	 *
	 * @param IConfig $config System and app config.
	 * @param ConfigSourceSteps $steps The package reader, the package service, the bundle service and the audit log.
	 * @param ConfigSourceLock $lock The locking provider and the app config.
	 * @param ConfigSourceClock $clock The clock and the logger.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly ConfigSourceSteps $steps,
		private readonly ConfigSourceLock $lock,
		private readonly ConfigSourceClock $clock,
	) {
	}//end __construct()

	/**
	 * The configured package path, or null when no source is set.
	 *
	 * @return string|null The path.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	public function getSourcePath(): ?string {
		$path = trim($this->config->getSystemValueString(self::SOURCE_KEY, ''));
		if ($path === '') {
			return null;
		}

		return $path;
	}//end getSourcePath()

	/**
	 * Whether web changes to the configuration are refused.
	 *
	 * @return bool True when a source is set and the lock is on.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	public function isLocked(): bool {
		return ($this->getSourcePath() !== null && $this->config->getSystemValueBool(self::LOCK_KEY, false) === true);
	}//end isLocked()

	/**
	 * Apply the package when its content changed since the last apply.
	 *
	 * @param bool $force Apply even when the hash is unchanged (`occ thematiq:config:apply --force`).
	 *
	 * @return array<string, mixed> `{status, errors?, revision?, hash?}`; status is one of
	 *                              `unconfigured`, `unchanged`, `applied`, `failed`, `busy`.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag) - the occ --force switch.
	 */
	public function applyIfChanged(bool $force = false): array {
		$path = $this->getSourcePath();
		if ($path === null) {
			return ['status' => 'unconfigured'];
		}

		try {
			$hash = $this->steps->reader->hash(path: $path);
		} catch (RuntimeException $e) {
			return $this->fail(errors: [['section' => 'package', 'message' => $e->getMessage()]], hash: 'unreadable:' . $path);
		}

		if ($this->isUnchanged(force: $force, hash: $hash) === true) {
			return $this->unchangedOnDisk(hash: $hash);
		}

		return $this->applyUnderLock(package: new ConfigSourcePackage(path: $path, hash: $hash), force: $force);
	}//end applyIfChanged()

	/**
	 * Apply the package while holding the replica lock, unless another replica applied it meanwhile.
	 *
	 * @param ConfigSourcePackage $package The package and its hash.
	 * @param bool $force Apply even when the hash is unchanged.
	 *
	 * @return array<string, mixed> The outcome; `busy` when another replica holds the lock.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function applyUnderLock(ConfigSourcePackage $package, bool $force): array {
		try {
			$this->lock->locking->acquireLock(self::LOCK_NAME, ILockingProvider::LOCK_EXCLUSIVE);
		} catch (LockedException $e) {
			return ['status' => 'busy'];
		}

		try {
			return $this->applyIfStillChanged(package: $package, force: $force);
		} finally {
			$this->lock->locking->releaseLock(self::LOCK_NAME, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}//end applyUnderLock()

	/**
	 * Under the lock: apply the package unless another replica applied it meanwhile.
	 *
	 * Compared again under the lock: a replica that read the hash while
	 * another one was applying this package would apply it a second
	 * time, with a second `config_imported` entry and version. The
	 * cached app config of this request predates that apply.
	 *
	 * @param ConfigSourcePackage $package The package and its hash.
	 * @param bool $force Apply even when the hash is unchanged.
	 *
	 * @return array<string, mixed> The outcome.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function applyIfStillChanged(ConfigSourcePackage $package, bool $force): array {
		$this->lock->appConfig->clearCache();
		if ($this->isUnchanged(force: $force, hash: $package->hash) === true) {
			return ['status' => 'unchanged', 'hash' => $package->hash];
		}

		return $this->apply(package: $package);
	}//end applyIfStillChanged()

	/**
	 * The answer for a package that is the one last applied, forgetting the error of a later one.
	 *
	 * @param string $hash The package hash.
	 *
	 * @return array<string, mixed> The `unchanged` outcome.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function unchangedOnDisk(string $hash): array {
		$this->clearStaleError(hash: $hash);
		return ['status' => 'unchanged', 'hash' => $hash];
	}//end unchangedOnDisk()

	/**
	 * Whether the package on disk is the one last applied, and no forced apply was asked.
	 *
	 * @param bool $force Apply even when the hash is unchanged.
	 * @param string $hash The package hash.
	 *
	 * @return bool True when there is nothing to apply.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function isUnchanged(bool $force, string $hash): bool {
		return ($force === false && $hash === $this->getApp(key: self::KEY_HASH));
	}//end isUnchanged()

	/**
	 * What the settings page shows about the source.
	 *
	 * @return array<string, mixed> `{managed, path, locked, revision, appliedAt, lastError, drift}`.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	public function getStatus(): array {
		$path = $this->getSourcePath();
		if ($path === null) {
			return ['managed' => false];
		}

		return $this->managedStatus(path: $path);
	}//end getStatus()

	/**
	 * The status while a source is set.
	 *
	 * @param string $path The package path.
	 *
	 * @return array<string, mixed> `{managed, path, locked, revision, appliedAt, lastError, drift}`.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function managedStatus(string $path): array {
		$state = $this->getApp(key: self::KEY_STATE);
		$lastError = $this->lastError();
		$appliedAt = $this->appliedAt();

		return [
			'managed' => true,
			'path' => $path,
			'locked' => $this->isLocked(),
			'revision' => $this->nullIfEmpty(value: $this->getApp(key: self::KEY_REVISION)),
			'appliedAt' => $appliedAt,
			'lastError' => $lastError,
			'drift' => ($state !== '' && $state !== $this->stateFingerprint()),
		];
	}//end managedStatus()

	/**
	 * The recorded error of the last failed apply.
	 *
	 * @return array<string, mixed>|null The error, or null when none is recorded.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function lastError(): array|null {
		$lastError = json_decode($this->getApp(key: self::KEY_ERROR), true);
		if (is_array($lastError) === false) {
			return null;
		}

		return $lastError;
	}//end lastError()

	/**
	 * When the package was last applied.
	 *
	 * @return string|null The ISO 8601 time, or null when it never was.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function appliedAt(): string|null {
		$appliedTime = (int)$this->getApp(key: self::KEY_APPLIED_AT);
		if ($appliedTime > 0) {
			return gmdate('c', $appliedTime);
		}

		return null;
	}//end appliedAt()

	/**
	 * Apply the package under the lock and record the outcome.
	 *
	 * @param ConfigSourcePackage $package The package and its hash.
	 *
	 * @return array<string, mixed> The outcome.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function apply(ConfigSourcePackage $package): array {
		$result = $this->steps->packageService->import(path: $package->path);
		if ($result['valid'] === false) {
			return $this->fail(errors: $result['errors'], hash: $package->hash);
		}

		$revision = (string)($result['revision'] ?? '');
		$this->recordApplied(hash: $package->hash, revision: $revision);
		$this->reportApplied(package: $package, revision: $this->nullIfEmpty(value: $revision));

		return ['status' => 'applied', 'revision' => $this->nullIfEmpty(value: $revision), 'hash' => $package->hash];
	}//end apply()

	/**
	 * Store what was applied: hash, revision, time and the running configuration's fingerprint; forget the last error.
	 *
	 * @param string $hash The package hash.
	 * @param string $revision The package revision, empty when it has none.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function recordApplied(string $hash, string $revision): void {
		$this->setApp(key: self::KEY_HASH, value: $hash);
		$this->setApp(key: self::KEY_REVISION, value: $revision);
		$this->setApp(key: self::KEY_APPLIED_AT, value: (string)$this->clock->time->getTime());
		$this->setApp(key: self::KEY_STATE, value: $this->stateFingerprint());
		$this->config->deleteAppValue(Application::APP_ID, self::KEY_ERROR);
	}//end recordApplied()

	/**
	 * Audit the apply as the system and log it.
	 *
	 * @param ConfigSourcePackage $package The package and its hash.
	 * @param string|null $revision The package revision, or null when it has none.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function reportApplied(ConfigSourcePackage $package, string|null $revision): void {
		$this->steps->audit->log(
			action: 'config_imported',
			context: [
				'actor' => 'system',
				'source' => 'deployment',
				'revision' => $revision,
				'hash' => $package->hash,
			]
		);
		$this->clock->logger->info('thematiq: applied the branding package from ' . $package->path . ' (' . ($revision ?? $package->hash) . ').');
	}//end reportApplied()

	/**
	 * An empty string as null.
	 *
	 * @param string $value The value.
	 *
	 * @return string|null The value, or null when empty.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function nullIfEmpty(string $value): ?string {
		if ($value === '') {
			return null;
		}

		return $value;
	}//end nullIfEmpty()

	/**
	 * Record a failed apply: the error listing for the settings page, and one log line per package hash.
	 *
	 * @param array<int, array<string, mixed>> $errors The errors.
	 * @param string $hash The package hash (or a marker for an unreadable path).
	 *
	 * @return array<string, mixed> The outcome.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function fail(array $errors, string $hash): array {
		$previous = json_decode($this->getApp(key: self::KEY_ERROR), true);
		if (is_array($previous) === false || ($previous['hash'] ?? null) !== $hash) {
			$messages = array_map(static fn (array $error): string => (string)($error['message'] ?? ''), $errors);
			$this->clock->logger->error('thematiq: the branding package was not applied, nothing changed: ' . implode(' ', $messages));
			$this->setApp(
				key: self::KEY_ERROR,
				value: (string)json_encode(['hash' => $hash, 'at' => gmdate('c', $this->clock->time->getTime()), 'errors' => $errors], JSON_UNESCAPED_SLASHES)
			);
		}

		return ['status' => 'failed', 'errors' => $errors, 'hash' => $hash];
	}//end fail()

	/**
	 * Forget the error of a package that is no longer the one on disk.
	 *
	 * The error is otherwise cleared only by a successful apply, so after a
	 * broken package was pushed and then reverted to the one already applied,
	 * the page kept showing the broken package's errors.
	 *
	 * @param string $hash The hash of the package on disk, which is the applied one.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function clearStaleError(string $hash): void {
		$error = json_decode($this->getApp(key: self::KEY_ERROR), true);
		if (is_array($error) === true && ($error['hash'] ?? null) !== $hash) {
			$this->config->deleteAppValue(Application::APP_ID, self::KEY_ERROR);
		}
	}//end clearStaleError()

	/**
	 * A fingerprint of the running configuration, without the fields that change on every export.
	 *
	 * @return string The `sha256:` prefixed fingerprint.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function stateFingerprint(): string {
		$state = $this->steps->bundleService->export();
		unset($state['exportedAt'], $state['app']);

		$fonts = [];
		foreach (($state['customFonts']['manifest'] ?? []) as $id => $meta) {
			$fonts[$id] = [($meta['name'] ?? ''), ($meta['role'] ?? ''), ($meta['size'] ?? 0)];
		}

		ksort($fonts);
		$state['customFonts'] = $fonts;

		return 'sha256:' . hash('sha256', (string)json_encode($state));
	}//end stateFingerprint()

	/**
	 * Read an app config value of this service.
	 *
	 * @param string $key The key.
	 *
	 * @return string The value, empty when unset.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function getApp(string $key): string {
		return (string)$this->config->getAppValue(Application::APP_ID, $key, '');
	}//end getApp()

	/**
	 * Write an app config value of this service.
	 *
	 * @param string $key The key.
	 * @param string $value The value.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function setApp(string $key, string $value): void {
		$this->config->setAppValue(Application::APP_ID, $key, $value);
	}//end setApp()
}//end class
