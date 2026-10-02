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
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use Psr\Log\LoggerInterface;
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
	 * @param IConfig                $config         System and app config.
	 * @param BrandingPackageReader  $reader         Hashes the package.
	 * @param BrandingPackageService $packageService Applies the package.
	 * @param ConfigBundleService    $bundleService  Exports the running configuration, for drift.
	 * @param ThemingAuditService    $audit          The audit log.
	 * @param ILockingProvider       $locking        The Nextcloud lock.
	 * @param ITimeFactory           $time           The clock.
	 * @param LoggerInterface        $logger         The logger.
	 *
	 * @SuppressWarnings(PHPMD.ExcessiveParameterList) - each collaborator is one step of an apply.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly BrandingPackageReader $reader,
		private readonly BrandingPackageService $packageService,
		private readonly ConfigBundleService $bundleService,
		private readonly ThemingAuditService $audit,
		private readonly ILockingProvider $locking,
		private readonly ITimeFactory $time,
		private readonly LoggerInterface $logger,
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
			$hash = $this->reader->hash(path: $path);
		} catch (RuntimeException $e) {
			return $this->fail(errors: [['section' => 'package', 'message' => $e->getMessage()]], hash: 'unreadable:' . $path);
		}

		if ($force === false && $hash === $this->getApp(key: self::KEY_HASH)) {
			return ['status' => 'unchanged', 'hash' => $hash];
		}

		try {
			$this->locking->acquireLock(self::LOCK_NAME, ILockingProvider::LOCK_EXCLUSIVE);
		} catch (LockedException $e) {
			return ['status' => 'busy'];
		}

		try {
			return $this->apply(path: $path, hash: $hash);
		} finally {
			$this->locking->releaseLock(self::LOCK_NAME, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}//end applyIfChanged()

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

		$state = $this->getApp(key: self::KEY_STATE);
		$lastError = json_decode($this->getApp(key: self::KEY_ERROR), true);
		$appliedAt = (int)$this->getApp(key: self::KEY_APPLIED_AT);

		return [
			'managed' => true,
			'path' => $path,
			'locked' => $this->isLocked(),
			'revision' => ($this->getApp(key: self::KEY_REVISION) ?: null),
			'appliedAt' => ($appliedAt > 0 ? gmdate('c', $appliedAt) : null),
			'lastError' => (is_array($lastError) === true ? $lastError : null),
			'drift' => ($state !== '' && $state !== $this->stateFingerprint()),
		];
	}//end getStatus()

	/**
	 * Apply the package under the lock and record the outcome.
	 *
	 * @param string $path The package path.
	 * @param string $hash The package hash.
	 *
	 * @return array<string, mixed> The outcome.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function apply(string $path, string $hash): array {
		$result = $this->packageService->import(path: $path);
		if ($result['valid'] === false) {
			return $this->fail(errors: $result['errors'], hash: $hash);
		}

		$revision = (string)($result['revision'] ?? '');
		$this->setApp(key: self::KEY_HASH, value: $hash);
		$this->setApp(key: self::KEY_REVISION, value: $revision);
		$this->setApp(key: self::KEY_APPLIED_AT, value: (string)$this->time->getTime());
		$this->setApp(key: self::KEY_STATE, value: $this->stateFingerprint());
		$this->config->deleteAppValue(Application::APP_ID, self::KEY_ERROR);

		$this->audit->log(
			action: 'config_imported',
			context: [
				'actor' => 'system',
				'source' => 'deployment',
				'revision' => ($revision !== '' ? $revision : null),
				'hash' => $hash,
			]
		);
		$this->logger->info('thematiq: applied the branding package from ' . $path . ' (' . ($revision ?: $hash) . ').');

		return ['status' => 'applied', 'revision' => ($revision ?: null), 'hash' => $hash];
	}//end apply()

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
			$this->logger->error('thematiq: the branding package was not applied, nothing changed: ' . implode(' ', $messages));
			$this->setApp(
				key: self::KEY_ERROR,
				value: (string)json_encode(['hash' => $hash, 'at' => gmdate('c', $this->time->getTime()), 'errors' => $errors], JSON_UNESCAPED_SLASHES)
			);
		}

		return ['status' => 'failed', 'errors' => $errors, 'hash' => $hash];
	}//end fail()

	/**
	 * A fingerprint of the running configuration, without the fields that change on every export.
	 *
	 * @return string The `sha256:` prefixed fingerprint.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function stateFingerprint(): string {
		$state = $this->bundleService->export();
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
