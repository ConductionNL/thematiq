<?php

/**
 * Contract of ThemingAuditService
 *
 * The public surface of ThemingAuditService that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category  Kernel
 * @package   OCA\Thematiq\Kernel\Contracts
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT: <git_id>
 * @link      https://github.com/ConductionNL/thematiq
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Thematiq\Kernel\Contracts;

/**
 * Contract of ThemingAuditService.
 *
 * @category Kernel
 * @package  OCA\Thematiq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/thematiq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface ThemingAuditServiceInterface {

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
	public function exportAll(): string;

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
	public function getRecent(int $limit): array;

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
	public function log(string $action, array $context = []): void;
}//end interface
