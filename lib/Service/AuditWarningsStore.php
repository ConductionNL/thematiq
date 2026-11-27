<?php

/**
 * The shipped sets' audit warnings, kept in app data so they survive a cache flush.
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
 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use FilesystemIterator;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Second level behind the local cache of a shipped set's audit warnings.
 *
 * The local cache empties on every PHP-FPM restart (each deploy and upgrade)
 * and its entries expire after an hour. The first catalogue read after that
 * re-ran the contrast, vocabulary and typeface audits of every shipped set:
 * about three seconds, paid by whoever opened the Theming page or the setup
 * wizard first. The warnings depend only on the app's own files, so they are
 * kept here in one app data file, read on a local cache miss.
 *
 * The file holds a fingerprint of every audit input that the per-set key does
 * not cover (the stylesheets under css/, design-systems.json and
 * token-sets.json, by path, modification time and size). Any change to those
 * files, an upgrade or an edit on a development checkout, makes the stored
 * warnings unused, so the audits run again.
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
 */
class AuditWarningsStore {

	/**
	 * The app data folder the file lives in.
	 */
	public const FOLDER = 'audit-cache';

	/**
	 * The file name.
	 */
	public const FILE = 'set-warnings.json';

	/**
	 * The stored warnings by set key, once read; null before the first read.
	 *
	 * @var array<string, array<int, mixed>>|null
	 */
	private ?array $stored = null;

	/**
	 * The warnings asked for or added in this request, by set key: what the next write keeps.
	 *
	 * @var array<string, array<int, mixed>>
	 */
	private array $touched = [];

	/**
	 * Whether a warning was added that the file does not hold yet.
	 *
	 * @var boolean
	 */
	private bool $dirty = false;

	/**
	 * The fingerprint of the audit inputs, by app path.
	 *
	 * @var array<string, string>
	 */
	private array $fingerprints = [];

	/**
	 * Constructor.
	 *
	 * @param IAppData $appData The app's data folder.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
	 */
	public function __construct(
		private readonly IAppData $appData,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The stored warnings of one set, or null when there are none for this key and these inputs.
	 *
	 * @param string $appPath The app root the audits read.
	 * @param string $key The set's cache key (set id, app version, CSS hash, entry).
	 *
	 * @return array<int, mixed>|null The warnings, or null.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
	 */
	public function get(string $appPath, string $key): ?array {
		$stored = $this->stored(appPath: $appPath);
		if (array_key_exists($key, $stored) === false || is_array($stored[$key]) === false) {
			return null;
		}

		$this->touched[$key] = $stored[$key];

		return $stored[$key];
	}//end get()

	/**
	 * Remember a set's freshly audited warnings; the next write() stores them.
	 *
	 * @param string $key The set's cache key.
	 * @param array<int, mixed> $warnings The warnings.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
	 */
	public function put(string $key, array $warnings): void {
		$this->touched[$key] = $warnings;
		$this->dirty = true;
	}//end put()

	/**
	 * Remember warnings the local cache answered, so the next write() keeps
	 * the whole catalogue; they do not cause a write themselves.
	 *
	 * @param string $key The set's cache key.
	 * @param array<int, mixed> $warnings The warnings.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
	 */
	public function keep(string $key, array $warnings): void {
		$this->touched[$key] = $warnings;
	}//end keep()

	/**
	 * Store the warnings of this request's catalogue read, when it audited any set.
	 *
	 * Only the sets this request asked for are kept, so warnings for CSS or
	 * entries that no longer exist drop out. A failed write is logged and
	 * otherwise ignored: the next read audits again.
	 *
	 * @param string $appPath The app root the audits read.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
	 */
	public function write(string $appPath): void {
		if ($this->dirty === false) {
			return;
		}

		$content = json_encode(['inputs' => $this->fingerprint(appPath: $appPath), 'warnings' => $this->touched]);
		if ($content === false) {
			return;
		}

		try {
			$this->writeFile(content: $content);
			$this->stored = $this->touched;
			$this->dirty = false;
		} catch (Throwable $e) {
			$this->logger->debug('thematiq: the token set audit warnings were not stored: {message}', ['message' => $e->getMessage()]);
		}
	}//end write()

	/**
	 * Replace the app data file with the given content, creating its folder and the file when absent.
	 *
	 * @param string $content The file content.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
	 */
	private function writeFile(string $content): void {
		try {
			$folder = $this->appData->getFolder(self::FOLDER);
		} catch (NotFoundException $e) {
			$folder = $this->appData->newFolder(self::FOLDER);
		}

		if ($folder->fileExists(self::FILE) === true) {
			$folder->getFile(self::FILE)->putContent($content);
			return;
		}

		$folder->newFile(self::FILE, $content);
	}//end writeFile()

	/**
	 * A fingerprint of the audit inputs the per-set key does not cover.
	 *
	 * @param string $appPath The app root.
	 *
	 * @return string The fingerprint.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
	 */
	public function fingerprint(string $appPath): string {
		if (isset($this->fingerprints[$appPath]) === true) {
			return $this->fingerprints[$appPath];
		}

		$lines = [];
		foreach ($this->inputFiles(appPath: $appPath) as $path) {
			$lines[] = $this->describe(path: $path, relative: substr($path, strlen($appPath) + 1));
		}

		sort($lines);
		$this->fingerprints[$appPath] = hash('xxh3', implode("\n", $lines));

		return $this->fingerprints[$appPath];
	}//end fingerprint()

	/**
	 * The files the audits read besides the set's own CSS: both manifests and every file under css/.
	 *
	 * @param string $appPath The app root.
	 *
	 * @return array<int, string> The absolute paths.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
	 */
	private function inputFiles(string $appPath): array {
		$paths = [$appPath . '/design-systems.json', $appPath . '/token-sets.json'];
		if (is_dir($appPath . '/css') === false) {
			return $paths;
		}

		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appPath . '/css', FilesystemIterator::SKIP_DOTS));
		foreach ($iterator as $file) {
			if ($file->isFile() === true) {
				$paths[] = $file->getPathname();
			}
		}

		return $paths;
	}//end inputFiles()

	/**
	 * One file's line in the fingerprint: its path, modification time and size.
	 *
	 * @param string $path The absolute path.
	 * @param string $relative The path relative to the app root.
	 *
	 * @return string The line; a missing file is named as missing.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
	 */
	private function describe(string $path, string $relative): string {
		if (is_file($path) === false) {
			return $relative . ':missing';
		}

		return $relative . ':' . (string)filemtime($path) . ':' . (string)filesize($path);
	}//end describe()

	/**
	 * The stored warnings, read once per request; empty when the file is absent, unreadable or made from other inputs.
	 *
	 * @param string $appPath The app root.
	 *
	 * @return array<string, array<int, mixed>> The warnings by set key.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
	 */
	private function stored(string $appPath): array {
		if ($this->stored !== null) {
			return $this->stored;
		}

		$this->stored = [];
		try {
			$raw = $this->appData->getFolder(self::FOLDER)->getFile(self::FILE)->getContent();
		} catch (Throwable $e) {
			return $this->stored;
		}

		$this->stored = $this->warningsFrom(raw: (string)$raw, fingerprint: $this->fingerprint(appPath: $appPath));

		return $this->stored;
	}//end stored()

	/**
	 * The warnings in a stored file, when it was made from the given inputs.
	 *
	 * @param string $raw The file content.
	 * @param string $fingerprint The fingerprint of the current inputs.
	 *
	 * @return array<string, array<int, mixed>> The warnings by set key; empty for a damaged file or other inputs.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
	 */
	private function warningsFrom(string $raw, string $fingerprint): array {
		$decoded = json_decode($raw, true);
		if (is_array($decoded) === false || ($decoded['inputs'] ?? null) !== $fingerprint) {
			return [];
		}

		$warnings = ($decoded['warnings'] ?? null);
		if (is_array($warnings) === false) {
			return [];
		}

		return $warnings;
	}//end warningsFrom()
}//end class
