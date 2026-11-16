<?php

/**
 * Contract of FontService
 *
 * The public surface of FontService that other components call, so they depend
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

use RuntimeException;

/**
 * Contract of FontService.
 *
 * @category Kernel
 * @package  OCA\Thematiq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/thematiq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface FontServiceInterface {

	/**
	 * Build the generated `@font-face` + font-token stylesheet.
	 *
	 * Emits one `@font-face` rule per stored font pointing at the
	 * self-hosted serve URL, plus a `:root` override per role: the body
	 * role overrides `--nldesign-font-family`, the heading role overrides
	 * `--nldesign-typography-heading-font-family`. In both cases the
	 * uploaded family is placed first and the bundled Fira Sans fallback
	 * chain is preserved verbatim afterwards. Display names are CSS-string
	 * escaped (backslash first, then quote) before interpolation, so a
	 * name containing `"` or `\` can never break out of the string literal
	 * or inject an extra declaration/rule.
	 *
	 * @return string The generated stylesheet (empty string when no fonts
	 *                are configured).
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	public function buildCss(): string;

	/**
	 * Delete a font: its appdata file and its manifest entry.
	 *
	 * The manifest is consulted and updated purely by the (already
	 * shape-validated) id — no user-supplied string is ever concatenated
	 * into a filesystem path.
	 *
	 * @param string $id The font id.
	 *
	 * @return bool True when something was removed, false when nothing
	 *              matched (including a malformed/traversal id, which is rejected
	 *              before any manifest or filesystem lookup).
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	public function delete(string $id): bool;

	/**
	 * Get a single manifest entry by id.
	 *
	 * @param string $id The font id.
	 *
	 * @return array<string, mixed>|null The manifest entry, or null when
	 *                                   the id is malformed or unknown.
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	public function getEntry(string $id): ?array;

	/**
	 * Get the current global font revision (bumped on every store/delete),
	 * used to cache-bust the generated stylesheet's injected URL.
	 *
	 * @return int The current revision (0 when never bumped).
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	public function getRevision(): int;

	/**
	 * List stored fonts with their metadata.
	 *
	 * @return array<int, array<string, mixed>> The fonts with id + metadata.
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	public function list(): array;

	/**
	 * Resolve and read a stored font's raw bytes in one call.
	 *
	 * Wraps {@see getFont()} plus `ISimpleFile::getContent()`, absorbing
	 * the (rare, TOCTOU-window) exceptions the underlying storage can throw
	 * between resolving the file and reading it, so the controller layer
	 * never needs to know about IAppData's exception contract — an unknown
	 * id and a read failure both simply resolve to null (404 upstream).
	 *
	 * @param string $id The font id.
	 *
	 * @return string|null The font's raw bytes, or null when not found or
	 *                     unreadable.
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	public function readFontBytes(string $id): ?string;

	/**
	 * Validate and store an uploaded font.
	 *
	 * @param string $displayName The admin-supplied display name.
	 * @param string $role The assigned font role (`body`|`heading`).
	 * @param string $bytes The raw uploaded file bytes.
	 * @param int $reportedSize The upload's reported size (checked before
	 *                          and independently of `strlen($bytes)`).
	 *
	 * @return array{id: string} The stored font's id.
	 *
	 * @throws RuntimeException Code 422 (bad name/role/content), 413
	 *                          (oversize), or 409 (collision or cap reached).
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	public function store(string $displayName, string $role, string $bytes, int $reportedSize): array;
}//end interface
