<?php

/**
 * NL Design Theming Audit Service.
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
 * @spec openspec/specs/theming-audit/spec.md#requirement-append-only-audit-entries
 * @spec openspec/specs/theming-audit/spec.md#requirement-jsonl-appdata-storage-with-capped-rotation
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IConfig;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Append-only theming audit trail (ADR: JSONL in appdata, not IConfig, not a
 * DB table — see the change proposal for the full storage rationale).
 *
 * `log()` is the ONLY write path. Every entry is one JSON line appended to the
 * app's appdata `audit/audit.jsonl`; once the file exceeds 1 MB it is rotated
 * to `audit/audit.jsonl.1` (replacing any previous generation) and a fresh
 * file is started, so total storage never exceeds ~2 MB. A monotonic IConfig
 * counter (`audit_entries_total`) is incremented on every successful append —
 * it feeds `nldesign_audit_entries_total` in MetricsController and is
 * immune to rotation (unlike counting lines in the file).
 *
 * Write failures are caught, logged as a warning, and never propagate: the
 * audit trail is evidence, not an enforcement gate, so a broken appdata mount
 * must never break the theming operation being audited.
 *
 * token-sets.json (verified against the shipped manifest) is a flat array of
 * set definitions with no top-level or per-set `version` field today, so
 * `tokenSetVersion` resolves to the app's `installed_version` app value for
 * every current call site. The manifest-version branch described in the spec
 * is intentionally not implemented by reading the file here (that would need
 * an additional IAppManager dependency outside this service's fixed
 * constructor list); if token-sets.json ever gains a version field, a caller
 * that already resolves it (e.g. TokenSetService) can pass it through
 * `$context['tokenSetVersion']`, which takes precedence over the app-value
 * fallback.
 *
 * @spec openspec/specs/theming-audit/spec.md#requirement-append-only-audit-entries
 * @spec openspec/specs/theming-audit/spec.md#requirement-jsonl-appdata-storage-with-capped-rotation
 */
class ThemingAuditService {

	/**
	 * The appdata folder name holding the audit trail.
	 *
	 * @var string
	 */
	private const FOLDER_NAME = 'audit';

	/**
	 * The current audit log file name.
	 *
	 * @var string
	 */
	private const FILE_NAME = 'audit.jsonl';

	/**
	 * The single rotated generation file name.
	 *
	 * @var string
	 */
	private const ROTATED_NAME = 'audit.jsonl.1';

	/**
	 * Rotation threshold in bytes (1 MB).
	 *
	 * @var int
	 */
	private const MAX_BYTES = 1048576;

	/**
	 * The IConfig app value key for the monotonic entry counter.
	 *
	 * @var string
	 */
	private const COUNTER_KEY = 'audit_entries_total';

	/**
	 * The closed action vocabulary. Extending it requires a spec change.
	 *
	 * `group_theming_changed` added by `openspec/specs/per-group-theming/spec.md`
	 * (the group→token-set mapping save).
	 *
	 * `scheduled_switch_applied` added by change `apply-scheduled-theme-switch`
	 * (a planned switch applied or failed by the background job; its entries
	 * pass `actor: system` in the context, because cron runs in CLI).
	 *
	 * @var array<int, string>
	 */
	private const VOCABULARY = [
		'token_set_changed',
		'toggle_changed',
		'overrides_written',
		'overrides_imported',
		'app_exclusions_changed',
		'custom_set_uploaded',
		'custom_set_deleted',
		'theming_sync_applied',
		'config_imported',
		'preview_published',
		'group_theming_changed',
		'version_restored',
		'scheduled_switch_applied',
		'custom_source_updated',
		'own_token_changed',
		'token_deprecation_changed',
	];

	/**
	 * Context keys that are never copied verbatim into the persisted entry —
	 * they are either consumed to build 'old'/'new' or feed tokenSetVersion.
	 *
	 * @var array<int, string>
	 */
	private const RESERVED_CONTEXT_KEYS = [
		'old',
		'new',
		'oldIsCss',
		'newIsCss',
		'contentHash',
		'tokenSetVersion',
	];

	/**
	 * Constructor.
	 *
	 * @param IAppDataFactory $appDataFactory The appdata factory (folder `audit`, file `audit.jsonl`).
	 * @param IConfig $config The config service (counter + installed_version).
	 * @param IUserSession $userSession The user session (actor resolution).
	 * @param ITimeFactory $timeFactory The time factory (testable timestamps).
	 * @param LoggerInterface $logger Logger for warnings on unknown actions / write failures.
	 * @param ThemeVersionService $versionService Keeps the configuration after each change (theme-versions spec).
	 */
	public function __construct(
		private readonly IAppDataFactory $appDataFactory,
		private readonly IConfig $config,
		private readonly IUserSession $userSession,
		private readonly ITimeFactory $timeFactory,
		private readonly LoggerInterface $logger,
		private readonly ThemeVersionService $versionService,
	) {
	}//end __construct()

	/**
	 * Append one audit entry.
	 *
	 * Builds `{ts, actor, action, old, new, tokenSetVersion}` plus any extra
	 * scalar context keys (e.g. `key` for toggle_changed, `id`/`name` for
	 * custom set operations) copied through verbatim. Unknown actions are
	 * refused (warning logged, nothing written, counter untouched). Every
	 * filesystem/appdata Throwable is caught, logged as a warning, and
	 * swallowed — the caller's operation MUST succeed regardless.
	 *
	 * Context contract:
	 * - `old` / `new` (mixed, optional): scalars are stored verbatim; arrays
	 *   (exclusion lists, override maps) are summarized as an entry count
	 *   plus a top-level `changed` list of identities that differ between
	 *   old and new.
	 * - `oldIsCss` / `newIsCss` (bool, optional): when true, the matching
	 *   `old`/`new` value is treated as a raw CSS payload and summarized as
	 *   `{hash: "sha256:<12 hex>", bytes: <int>}` — the CSS text itself is
	 *   never persisted.
	 * - `contentHash` (string, optional): a precomputed `sha256:<12 hex>`
	 *   hash appended to `tokenSetVersion` for custom-* set entries.
	 * - `tokenSetVersion` (string, optional): overrides the resolved
	 *   version (see class docblock for why the service does not read
	 *   token-sets.json itself).
	 * - any other key is copied into the entry verbatim (small scalars only
	 *   — never CSS bodies). `actor` is one of them: it overrides the resolved
	 *   actor, which the scheduled switch job uses to record `system`.
	 *
	 * @param string $action One of the closed vocabulary actions.
	 * @param array<string, mixed> $context The entry context (see above).
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theming-audit/spec.md#requirement-append-only-audit-entries
	 * @spec openspec/specs/theme-versions/spec.md
	 */
	public function log(string $action, array $context = []): void {
		if (in_array($action, self::VOCABULARY, true) === false) {
			$this->logger->warning(
				'nldesign audit: rejected unknown action "{action}" — entry dropped',
				['action' => $action]
			);
			return;
		}

		$entry = $this->buildEntry(action: $action, context: $context);

		// Keep the configuration this change produced, so the entry can be
		// restored later. A capture that fails leaves the entry without a
		// versionId and never blocks it (openspec/specs/theme-versions/spec.md).
		unset($entry['versionId']);
		try {
			$versionId = $this->versionService->capture(auditAction: $action, actor: (string)$entry['actor']);
			if ($versionId !== null) {
				$entry['versionId'] = $versionId;
			}
		} catch (\Throwable $e) {
			$this->logger->warning(
				'nldesign audit: no version was kept for action "{action}": {message}',
				['action' => $action, 'message' => $e->getMessage()]
			);
		}

		$line = json_encode($entry, JSON_UNESCAPED_SLASHES);
		if ($line === false) {
			$this->logger->warning('nldesign audit: could not encode entry for action "{action}"', ['action' => $action]);
			return;
		}

		try {
			$this->appendLine(line: $line);

			$total = ((int)$this->config->getAppValue(Application::APP_ID, self::COUNTER_KEY, '0') + 1);
			$this->config->setAppValue(Application::APP_ID, self::COUNTER_KEY, (string)$total);
		} catch (\Throwable $e) {
			$this->logger->warning(
				'nldesign audit: failed to persist entry for action "{action}": {message}',
				['action' => $action, 'message' => $e->getMessage()]
			);
		}
	}//end log()

	/**
	 * Read the most recent entries, newest first.
	 *
	 * Spans the rotated generation and the current file when needed. Read
	 * failures are logged and degrade to an empty array rather than
	 * propagating — the admin panel should show "no entries" rather than 500.
	 *
	 * @param int $limit Maximum number of entries to return (0 returns none).
	 *
	 * @return array<int, array<string, mixed>> The parsed entries, newest first.
	 *
	 * @spec openspec/specs/theming-audit/spec.md#requirement-jsonl-appdata-storage-with-capped-rotation
	 */
	public function getRecent(int $limit): array {
		$capped = $limit;
		if ($capped < 0) {
			$capped = 0;
		}

		try {
			$folder = $this->getAuditFolder();
			$rotated = $this->parseLines(raw: $this->readRaw(folder: $folder, name: self::ROTATED_NAME));
			$current = $this->parseLines(raw: $this->readRaw(folder: $folder, name: self::FILE_NAME));
		} catch (\Throwable $e) {
			$this->logger->warning('nldesign audit: failed to read entries: {message}', ['message' => $e->getMessage()]);
			return [];
		}

		// Oldest-first overall (rotated generation precedes the current file),
		// then reversed for the newest-first contract.
		$chronological = array_merge($rotated, $current);
		$newestFirst = array_reverse($chronological);

		return array_slice($newestFirst, 0, $capped);
	}//end getRecent()

	/**
	 * Export the full retained log as raw JSONL, oldest first.
	 *
	 * The rotated generation (if any) precedes the current file — exactly
	 * the byte layout an admin downloads via AuditController::export().
	 * Read failures degrade to an empty string rather than propagating.
	 *
	 * @return string The concatenated JSONL content (may be empty).
	 *
	 * @spec openspec/specs/theming-audit/spec.md#requirement-jsonl-appdata-storage-with-capped-rotation
	 */
	public function exportAll(): string {
		try {
			$folder = $this->getAuditFolder();

			return $this->readRaw(folder: $folder, name: self::ROTATED_NAME) . $this->readRaw(folder: $folder, name: self::FILE_NAME);
		} catch (\Throwable $e) {
			$this->logger->warning('nldesign audit: failed to export entries: {message}', ['message' => $e->getMessage()]);
			return '';
		}
	}//end exportAll()

	/**
	 * Build the entry array for one log() call (before JSON encoding).
	 *
	 * @param string $action The validated action.
	 * @param array<string, mixed> $context The caller-supplied context.
	 *
	 * @return array<string, mixed> The entry.
	 */
	private function buildEntry(string $action, array $context): array {
		$entry = [
			'ts' => gmdate('Y-m-d\TH:i:s\Z', $this->timeFactory->getTime()),
			'actor' => $this->resolveActor(),
			'action' => $action,
		];

		$oldRaw = ($context['old'] ?? null);
		$newRaw = ($context['new'] ?? null);
		$oldIsCss = (($context['oldIsCss'] ?? false) === true);
		$newIsCss = (($context['newIsCss'] ?? false) === true);

		$entry['old'] = $this->summarizeValue(value: $oldRaw, isCss: $oldIsCss);
		$entry['new'] = $this->summarizeValue(value: $newRaw, isCss: $newIsCss);

		if (is_array($oldRaw) === true && is_array($newRaw) === true && $oldIsCss === false && $newIsCss === false) {
			$entry['changed'] = (new AuditArrayDiff())->changed(old: $oldRaw, new: $newRaw);
		}

		foreach ($context as $key => $value) {
			if (in_array($key, self::RESERVED_CONTEXT_KEYS, true) === true) {
				continue;
			}

			$entry[$key] = $value;
		}

		$entry['tokenSetVersion'] = $this->resolveTokenSetVersion(context: $context);

		return $entry;
	}//end buildEntry()

	/**
	 * Resolve the acting identity: session uid, else `cli` for occ/CLI
	 * contexts, else `system`.
	 *
	 * @return string The resolved actor.
	 */
	private function resolveActor(): string {
		$user = $this->userSession->getUser();
		if ($user !== null) {
			return $user->getUID();
		}

		if ($this->isRunningInCli() === true) {
			return 'cli';
		}

		return 'system';
	}//end resolveActor()

	/**
	 * Whether the current request runs in a CLI/occ context.
	 *
	 * Extracted to its own (overridable) method so unit tests can exercise
	 * the `system` fallback branch, which PHP_SAPI itself cannot express
	 * inside a PHPUnit process (always `cli`).
	 *
	 * @return bool True when running under PHP's CLI SAPI.
	 */
	protected function isRunningInCli(): bool {
		return PHP_SAPI === 'cli';
	}//end isRunningInCli()

	/**
	 * Resolve `tokenSetVersion`: an explicit context override, else the app's
	 * `installed_version`, with a `contentHash` (custom-* sets) appended.
	 *
	 * @param array<string, mixed> $context The log() context.
	 *
	 * @return string The resolved version string.
	 */
	private function resolveTokenSetVersion(array $context): string {
		$version = $context['tokenSetVersion'] ?? null;
		if (is_string($version) === false || $version === '') {
			$version = (string)$this->config->getAppValue(Application::APP_ID, 'installed_version', '0.0.0');
		}

		$contentHash = ($context['contentHash'] ?? null);
		if (is_string($contentHash) === true && $contentHash !== '') {
			$version .= '+' . $contentHash;
		}

		return $version;
	}//end resolveTokenSetVersion()

	/**
	 * Summarize a value per the entry's value-summary rules.
	 *
	 * @param mixed $value The raw value.
	 * @param bool $isCss Whether the value is a raw CSS payload to be hashed.
	 *
	 * @return mixed The verbatim scalar, a CSS hash+size map, or an array count map.
	 */
	private function summarizeValue(mixed $value, bool $isCss): mixed {
		if ($isCss === true && is_string($value) === true) {
			return [
				'hash' => 'sha256:' . substr(hash(algo: 'sha256', data: $value), 0, 12),
				'bytes' => strlen($value),
			];
		}

		if (is_array($value) === true) {
			return ['count' => count($value)];
		}

		return $value;
	}//end summarizeValue()

	/**
	 * Parse a raw JSONL blob into an ordered list of entry arrays, skipping
	 * any malformed line rather than failing the whole read.
	 *
	 * @param string $raw The raw file content.
	 *
	 * @return array<int, array<string, mixed>> The parsed entries, file order.
	 */
	private function parseLines(string $raw): array {
		$entries = [];
		foreach (explode("\n", $raw) as $line) {
			if (trim($line) === '') {
				continue;
			}

			$decoded = json_decode($line, true);
			if (is_array($decoded) === true) {
				$entries[] = $decoded;
			}
		}

		return $entries;
	}//end parseLines()

	/**
	 * Append one JSON line to `audit.jsonl`, rotating when the result exceeds
	 * the size cap.
	 *
	 * @param string $line The JSON-encoded entry (without trailing newline).
	 *
	 * @return void
	 *
	 * @throws \Throwable Any appdata failure — caught by the caller (log()).
	 */
	private function appendLine(string $line): void {
		$folder = $this->getAuditFolder();
		$current = $this->readRaw(folder: $folder, name: self::FILE_NAME);
		$updated = $current . $line . "\n";

		if (strlen($updated) > self::MAX_BYTES) {
			// The full (overflowing) content becomes the single rotated
			// generation; the current file starts fresh and empty.
			$this->writeRaw(folder: $folder, name: self::ROTATED_NAME, content: $updated);
			$this->writeRaw(folder: $folder, name: self::FILE_NAME, content: '');
			return;
		}

		$this->writeRaw(folder: $folder, name: self::FILE_NAME, content: $updated);
	}//end appendLine()

	/**
	 * Get (creating if needed) the `audit` appdata folder.
	 *
	 * @return ISimpleFolder The audit folder.
	 *
	 * @throws \Throwable Any appdata failure.
	 */
	private function getAuditFolder(): ISimpleFolder {
		$root = $this->appDataFactory->get(Application::APP_ID);

		try {
			return $root->getFolder(self::FOLDER_NAME);
		} catch (NotFoundException $e) {
			return $root->newFolder(self::FOLDER_NAME);
		}
	}//end getAuditFolder()

	/**
	 * Read a file's content, or an empty string when it does not exist.
	 *
	 * @param ISimpleFolder $folder The audit folder.
	 * @param string $name The file name.
	 *
	 * @return string The file content (empty when absent).
	 */
	private function readRaw(ISimpleFolder $folder, string $name): string {
		if ($folder->fileExists($name) === false) {
			return '';
		}

		return $folder->getFile($name)->getContent();
	}//end readRaw()

	/**
	 * Write (creating or overwriting) a file's full content.
	 *
	 * @param ISimpleFolder $folder The audit folder.
	 * @param string $name The file name.
	 * @param string $content The full content to persist.
	 *
	 * @return void
	 */
	private function writeRaw(ISimpleFolder $folder, string $name, string $content): void {
		if ($folder->fileExists($name) === true) {
			$folder->getFile($name)->putContent($content);
			return;
		}

		$folder->newFile($name, $content);
	}//end writeRaw()
}//end class
