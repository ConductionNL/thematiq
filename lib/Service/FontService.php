<?php

/**
 * NL Design Font Service.
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
 * @spec openspec/specs/custom-fonts/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\Files\GenericFileException;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\Lock\LockedException;
use RuntimeException;

/**
 * Stores, lists, and deletes admin-uploaded custom fonts, and generates the
 * `@font-face` + font-token stylesheet served at `GET /fonts/css`.
 *
 * Uploaded fonts land as `fonts/custom-{slug}.woff2` in the app's IAppData
 * storage (self-hosted, no external CDN, CSP-clean). Metadata (display name,
 * role, size, upload timestamp, per-font revision) lives in the
 * `custom_fonts` appconfig key, indexed by id — the manifest is the sole
 * authorization gate for what {@see getFont()} will ever serve, so a stray
 * file in appdata with no manifest entry is never reachable (mirrors
 * {@see CustomTokenSetService}, hardened for binary input).
 *
 * @spec openspec/specs/custom-fonts/spec.md
 */
class FontService {

	/**
	 * The appconfig key holding the font metadata manifest (JSON object).
	 *
	 * @var string
	 */
	public const MANIFEST_KEY = 'custom_fonts';

	/**
	 * The appconfig key holding the global font revision counter.
	 *
	 * @var string
	 */
	public const REVISION_KEY = 'custom_fonts_rev';

	/**
	 * The id prefix every custom font carries.
	 *
	 * @var string
	 */
	public const ID_PREFIX = 'custom-';

	/**
	 * The IAppData subfolder holding the stored `.woff2` files.
	 *
	 * @var string
	 */
	public const APPDATA_FOLDER = 'fonts';

	/**
	 * The bundled Fira Sans fallback chain, verbatim from
	 * css/systems/nldesign/defaults.css `--nldesign-font-family`. A generated
	 * font override always places the uploaded family first and preserves
	 * this chain unmodified afterwards, so a missing or unloadable font
	 * degrades to exactly today's rendering.
	 *
	 * @var string
	 */
	public const DEFAULT_FONT_FAMILY = "'Fira Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', "
		. "Roboto, Oxygen-Sans, Cantarell, Ubuntu, 'Helvetica Neue', Arial, sans-serif";

	/**
	 * The app's own storage area (self-hosted, no external requests).
	 *
	 * @var IAppData
	 */
	private IAppData $appData;

	/**
	 * The config service for the appconfig manifest and revision counter.
	 *
	 * @var IConfig
	 */
	private IConfig $config;

	/**
	 * The URL generator, for the self-hosted `src: url(...)` in the
	 * generated stylesheet.
	 *
	 * @var IURLGenerator
	 */
	private IURLGenerator $urlGenerator;

	/**
	 * The upload validator.
	 *
	 * @var FontValidator
	 */
	private FontValidator $validator;

	/**
	 * Constructor.
	 *
	 * @param IAppData $appData The app's IAppData storage.
	 * @param IConfig $config The config service.
	 * @param IURLGenerator $urlGenerator The URL generator.
	 * @param FontValidator $validator The upload validator.
	 */
	public function __construct(
		IAppData $appData,
		IConfig $config,
		IURLGenerator $urlGenerator,
		FontValidator $validator,
	) {
		$this->appData = $appData;
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->validator = $validator;
	}//end __construct()

	/**
	 * Derive a slug from an admin-supplied display name.
	 *
	 * Identical contract to {@see CustomTokenSetService::slugify()}:
	 * lowercases, replaces non-alphanumerics with hyphens, collapses
	 * repeats, trims hyphens, and caps at 64 characters.
	 *
	 * @param string $name The display name.
	 *
	 * @return string The derived slug (may be empty for all-symbol input).
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	public function slugify(string $name): string {
		return $this->manifestStore()->slugify(name: $name);
	}//end slugify()

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
	public function store(string $displayName, string $role, string $bytes, int $reportedSize): array {
		$this->validator->validateDisplayName(name: $displayName);
		$this->validator->validateSize(size: $reportedSize);
		$this->validator->validateRole(role: $role);

		$slug = $this->slugify(name: $displayName);
		$this->validator->validateSlug(slug: $slug);

		$this->validator->validateMagicBytes(bytes: $bytes);
		$this->validator->validateSize(size: strlen($bytes));

		$manifest = $this->getManifest();
		$this->validator->validateCap(currentCount: count($manifest));

		$id = self::ID_PREFIX . $slug;
		$filename = $id . '.woff2';
		$folder = $this->getFolder();

		if (isset($manifest[$id]) === true || $folder->fileExists($filename) === true) {
			throw new RuntimeException('A font named "' . $displayName . '" already exists. Delete it first.', 409);
		}

		try {
			$folder->newFile($filename, $bytes);
		} catch (NotPermittedException $e) {
			throw new RuntimeException('Could not write the font file to app data storage.', 500);
		}

		$rev = $this->bumpRevision();

		$manifest[$id] = [
			'name' => $displayName,
			'role' => $role,
			'size' => strlen($bytes),
			'uploadedAt' => time(),
			'rev' => $rev,
		];
		$this->saveManifest(manifest: $manifest);

		return ['id' => $id];
	}//end store()

	/**
	 * List stored fonts with their metadata.
	 *
	 * @return array<int, array<string, mixed>> The fonts with id + metadata.
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	public function list(): array {
		$result = [];
		foreach ($this->getManifest() as $id => $meta) {
			$result[] = $this->manifestStore()->listed(id: $id, meta: $meta);
		}

		return $this->manifestStore()->byName(entries: $result);
	}//end list()

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
	public function delete(string $id): bool {
		if ($this->isValidId(id: $id) === false) {
			return false;
		}

		$manifest = $this->getManifest();
		$removed = false;

		if (isset($manifest[$id]) === true) {
			unset($manifest[$id]);
			$removed = true;
		}

		try {
			$this->getFolder()->getFile($id . '.woff2')->delete();
			$removed = true;
		} catch (NotFoundException|NotPermittedException $e) {
			// Nothing to remove on disk — the manifest entry (if any) is
			// still removed above; the manifest is the gate, not the file.
		}

		if ($removed === true) {
			$this->saveManifest(manifest: $manifest);
			$this->bumpRevision();
		}

		return $removed;
	}//end delete()

	/**
	 * Resolve the stored font file for an id, gated purely by the manifest.
	 *
	 * An id not present in the manifest returns null even when a stray file
	 * of that name exists in appdata — the manifest is the authorization
	 * gate for what is ever served.
	 *
	 * @param string $id The font id.
	 *
	 * @return ISimpleFile|null The stored file, or null when not found.
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	public function getFont(string $id): ?ISimpleFile {
		if ($this->isValidId(id: $id) === false || isset($this->getManifest()[$id]) === false) {
			return null;
		}

		try {
			return $this->getFolder()->getFile($id . '.woff2');
		} catch (NotFoundException $e) {
			return null;
		}
	}//end getFont()

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
	public function readFontBytes(string $id): ?string {
		$file = $this->getFont(id: $id);
		if ($file === null) {
			return null;
		}

		try {
			return $file->getContent();
		} catch (GenericFileException|LockedException|NotFoundException|NotPermittedException $e) {
			return null;
		}
	}//end readFontBytes()

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
	public function getEntry(string $id): ?array {
		$manifest = $this->getManifest();
		if ($this->isValidId(id: $id) === false || isset($manifest[$id]) === false) {
			return null;
		}

		return $manifest[$id];
	}//end getEntry()

	/**
	 * Whether at least one font is currently configured.
	 *
	 * @return bool True when the manifest has at least one entry.
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	public function hasFonts(): bool {
		return empty($this->getManifest()) === false;
	}//end hasFonts()

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
	public function buildCss(): string {
		$manifest = $this->getManifest();
		if (empty($manifest) === true) {
			return '';
		}

		$faces = [];
		$roleMap = [
			'body' => null,
			'heading' => null,
		];

		foreach ($manifest as $id => $entry) {
			[$name, $role] = $this->resolveEntryNameAndRole(entry: $entry, id: (string)$id);
			$escaped = $this->escapeCssString(value: $name);
			$faces[] = $this->buildFontFaceRule(id: (string)$id, escapedName: $escaped);

			if (array_key_exists($role, $roleMap) === true && $roleMap[$role] === null) {
				$roleMap[$role] = $escaped;
			}
		}

		$css = implode(PHP_EOL, $faces) . PHP_EOL;
		$css .= $this->buildRootOverride(cssVariable: '--nldesign-font-family', family: $roleMap['body']);
		$css .= $this->buildRootOverride(cssVariable: '--nldesign-typography-heading-font-family', family: $roleMap['heading']);

		return $css;
	}//end buildCss()

	/**
	 * Resolve the display name and role of a manifest entry, defaulting
	 * defensively when the entry is malformed (not an array).
	 *
	 * @param mixed $entry The raw manifest entry.
	 * @param string $id The font id (fallback display name).
	 *
	 * @return array{0: string, 1: string} The `[name, role]` pair.
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	private function resolveEntryNameAndRole($entry, string $id): array {
		if (is_array($entry) === false) {
			return [$id, 'body'];
		}

		return [(string)($entry['name'] ?? $id), (string)($entry['role'] ?? 'body')];
	}//end resolveEntryNameAndRole()

	/**
	 * Build a single `@font-face` rule for a stored font.
	 *
	 * @param string $id The font id (used to build the serve URL).
	 * @param string $escapedName The CSS-escaped display name.
	 *
	 * @return string The `@font-face` rule.
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	private function buildFontFaceRule(string $id, string $escapedName): string {
		$url = $this->urlGenerator->linkToRoute('thematiq.font.serve', ['id' => $id]);

		return '@font-face {' . PHP_EOL
			. '	font-family: "' . $escapedName . '";' . PHP_EOL
			. '	src: url(\'' . $url . '\') format(\'woff2\');' . PHP_EOL
			. '	font-display: swap;' . PHP_EOL
			. '}';
	}//end buildFontFaceRule()

	/**
	 * Build a `:root` override for a font token, preserving the Fira Sans
	 * fallback chain after the uploaded family.
	 *
	 * @param string $cssVariable The `--nldesign-*` custom property name.
	 * @param string|null $family The escaped uploaded family, or null
	 *                            when no font is assigned to this role
	 *                            (no override is emitted).
	 *
	 * @return string The `:root { ... }` block, or an empty string.
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	private function buildRootOverride(string $cssVariable, ?string $family): string {
		if ($family === null) {
			return '';
		}

		return ':root {' . PHP_EOL
			. '	' . $cssVariable . ': "' . $family . '", ' . self::DEFAULT_FONT_FAMILY . ';' . PHP_EOL
			. '}' . PHP_EOL;
	}//end buildRootOverride()

	/**
	 * Get the decoded font manifest from appconfig.
	 *
	 * @return array<string, mixed> The manifest indexed by id (empty on
	 *                              absence/corruption).
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	public function getManifest(): array {
		return $this->manifestStore()->read();
	}//end getManifest()

	/**
	 * Get the current global font revision (bumped on every store/delete),
	 * used to cache-bust the generated stylesheet's injected URL.
	 *
	 * @return int The current revision (0 when never bumped).
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	public function getRevision(): int {
		return (int)(new AppConfigManifest(config: $this->config, key: self::REVISION_KEY))->value(default: '0');
	}//end getRevision()

	/**
	 * Whether an id is a safe font id (prefix + slug charset only).
	 *
	 * Guards against path traversal: a valid id may only contain the prefix
	 * plus `[a-z0-9-]`, so it can never escape the `fonts/` appdata folder.
	 *
	 * @param string $id The id to validate.
	 *
	 * @return bool True when the id is a safe font id.
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	public function isValidId(string $id): bool {
		return preg_match('/^custom-[a-z0-9-]+$/', $id) === 1;
	}//end isValidId()

	/**
	 * Increment and persist the global font revision.
	 *
	 * @return int The new revision.
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	private function bumpRevision(): int {
		$rev = ($this->getRevision() + 1);
		(new AppConfigManifest(config: $this->config, key: self::REVISION_KEY))->setValue(value: (string)$rev);

		return $rev;
	}//end bumpRevision()

	/**
	 * Persist the font manifest to appconfig.
	 *
	 * @param array<string, mixed> $manifest The manifest indexed by id.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	private function saveManifest(array $manifest): void {
		$this->manifestStore()->save(manifest: $manifest);
	}//end saveManifest()

	/**
	 * The manifest's app config key.
	 *
	 * @return AppConfigManifest The manifest store.
	 */
	private function manifestStore(): AppConfigManifest {
		return new AppConfigManifest(config: $this->config, key: self::MANIFEST_KEY);
	}//end manifestStore()

	/**
	 * Resolve (creating if necessary) the `fonts/` IAppData folder.
	 *
	 * @return ISimpleFolder The fonts storage folder.
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	private function getFolder(): ISimpleFolder {
		try {
			return $this->appData->getFolder(self::APPDATA_FOLDER);
		} catch (NotFoundException $e) {
			return $this->appData->newFolder(self::APPDATA_FOLDER);
		}
	}//end getFolder()

	/**
	 * CSS-escape a string for interpolation into a double-quoted CSS string
	 * literal (backslash first, then quote — so the inserted escapes are
	 * never themselves re-escaped).
	 *
	 * @param string $value The raw value.
	 *
	 * @return string The escaped value.
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	private function escapeCssString(string $value): string {
		$escaped = str_replace('\\', '\\\\', $value);

		return str_replace('"', '\\"', $escaped);
	}//end escapeCssString()
}//end class
