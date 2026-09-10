<?php

/**
 * NL Design Custom Token Set Service.
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
 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-2.1
 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-2.2
 * @spec openspec/specs/custom-token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IConfig;
use RuntimeException;

/**
 * Stores, lists, exports and deletes admin-uploaded custom token sets.
 *
 * Uploaded sets land as `css/tokens/custom-{slug}.css` (same write target
 * pattern as custom-overrides.css) so the existing discovery, loader, preview
 * and apply machinery picks them up unchanged. Metadata (name, description,
 * derived theming colours, persisted contrast warnings) lives in the
 * `custom_token_sets` appconfig key, indexed by id.
 *
 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-2.1
 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-2.2
 * @spec openspec/specs/dark-mode/spec.md
 */
class CustomTokenSetService {

	/**
	 * The appconfig key holding the custom-set metadata manifest (JSON object).
	 *
	 * @var string
	 */
	public const MANIFEST_KEY = 'custom_token_sets';

	/**
	 * The id prefix every custom set carries.
	 *
	 * @var string
	 */
	public const ID_PREFIX = 'custom-';

	/**
	 * The app manager for resolving the app directory.
	 *
	 * @var IAppManager
	 */
	private IAppManager $appManager;

	/**
	 * The config service for the appconfig manifest and active token set.
	 *
	 * @var IConfig
	 */
	private IConfig $config;

	/**
	 * The CSS upload validator / re-serialiser.
	 *
	 * @var CustomTokenSetValidator
	 */
	private CustomTokenSetValidator $validator;

	/**
	 * The WCAG contrast service.
	 *
	 * @var ContrastService
	 */
	private ContrastService $contrast;

	/**
	 * The dark-variant derivation/generation service — generates
	 * `css/tokens/dark/custom-{slug}.css` at upload time and removes it on
	 * delete (see openspec/specs/dark-mode/spec.md). Appended last so no
	 * existing constructor call site needs reordering.
	 *
	 * @var DarkPaletteService
	 */
	private DarkPaletteService $darkPalette;

	/**
	 * Constructor.
	 *
	 * @param IAppManager $appManager The app manager.
	 * @param IConfig $config The config service.
	 * @param CustomTokenSetValidator $validator The CSS validator.
	 * @param ContrastService $contrast The contrast service.
	 * @param DarkPaletteService $darkPalette The dark-variant generation service.
	 */
	public function __construct(
		IAppManager $appManager,
		IConfig $config,
		CustomTokenSetValidator $validator,
		ContrastService $contrast,
		DarkPaletteService $darkPalette,
	) {
		$this->appManager = $appManager;
		$this->config = $config;
		$this->validator = $validator;
		$this->contrast = $contrast;
		$this->darkPalette = $darkPalette;
	}//end __construct()

	/**
	 * Derive a slug from an admin-supplied display name.
	 *
	 * Lowercases, replaces non-alphanumerics with hyphens, collapses repeats,
	 * trims hyphens, and caps at 64 characters.
	 *
	 * @param string $name The display name.
	 *
	 * @return string The derived slug (may be empty for all-symbol input).
	 *
	 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-2.1
	 */
	public function slugify(string $name): string {
		$slug = strtolower(trim($name));
		$slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
		$slug = trim($slug, '-');

		return substr($slug, 0, 64);
	}//end slugify()

	/**
	 * Store a validated set of declarations as a new custom token set.
	 *
	 * Derives the id `custom-{slug}`, rejects collisions (RuntimeException
	 * with code 409), writes the canonical CSS atomically, persists the
	 * manifest entry (name, description, derived theming, contrast warnings,
	 * and — for a DTCG import — the declared package `version` and any
	 * `importWarnings`, e.g. `$deprecated` notices). Best-effort generates
	 * `css/tokens/dark/{id}.css` in the same operation (never fails the
	 * upload itself — see openspec/specs/dark-mode/spec.md).
	 *
	 * `$version` and `$importWarnings` are DTCG-import concerns and are
	 * deliberately named apart from the pre-existing `warnings` key (WCAG
	 * contrast warnings, computed below): the two are unrelated diagnostics
	 * and merging them into one array would corrupt whichever consumer reads
	 * it (the contrast banner keys off `pair`/`ratio`; an import warning
	 * carries `path`/`message`).
	 *
	 * @param string $displayName The admin display name.
	 * @param string $description Optional description.
	 * @param array<string, string> $declarations The whitelisted declarations.
	 * @param string|null $version The declared DTCG package version, verbatim (never fabricated).
	 * @param array<int, array{path: string, message: string|null}> $importWarnings DTCG `$deprecated` import warnings, if any.
	 * @param string|null $css Canonical file text to write verbatim instead of re-serialising `$declarations`. The
	 *                         converter path passes its own emitted file, whose four commented sections and
	 *                         provenance block are the point of it — `serialize()` would flatten both away. The
	 *                         caller MUST have validated these exact bytes; this parameter never bypasses a gate,
	 *                         it only chooses which already-validated text is stored.
	 * @param array<string, string> $theming Theming values to merge OVER the derived ones. The converter routes a
	 *                                       theme's page background to `theming.background_color` on purpose (so
	 *                                       Nextcloud keeps owning `--color-main-background` and dark mode), and
	 *                                       `deriveTheming()` cannot see that decision from the declarations alone.
	 * @param array{path: string, contents: string}|null $logoAsset A logo the converter decoded out of the theme,
	 *                                       to be written under `img/logos/`. Nextcloud's core theming takes a
	 *                                       logo as a FILE (`ImageManager::updateImage()`), so a theme's inline
	 *                                       `data:` URI has to become one before `theming.logo` can mean anything.
	 *
	 * @return array{id: string, warnings: array<int, array<string, mixed>>} The result.
	 *
	 * @throws RuntimeException When the slug is empty (422), the id collides
	 *                          (409), or the file cannot be written (500).
	 *
	 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-2.1
	 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-2.2
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	public function store(
		string $displayName,
		string $description,
		array $declarations,
		?string $version = null,
		array $importWarnings = [],
		?string $css = null,
		array $theming = [],
		?array $logoAsset = null,
	): array {
		$slug = $this->slugify(name: $displayName);
		if ($slug === '') {
			throw new RuntimeException(message: 'A token set name must contain at least one letter or digit.', code: 422);
		}

		$id = self::ID_PREFIX . $slug;
		$path = $this->getCssPath(id: $id);

		if (file_exists($path) === true || isset($this->getManifest()[$id]) === true) {
			throw new RuntimeException(message: 'A custom token set named "' . $displayName . '" already exists. Delete or rename it first.', code: 409);
		}

		$this->writeFile(
			path: $path,
			contents: ($css ?? $this->validator->serialize(declarations: $declarations))
		);

		// Written before the manifest entry that points at it: `theming.logo`
		// is validated by ThemingService against the file EXISTING, so a
		// manifest entry naming a file that was never written would fail the
		// sync with "Image file not found" instead of updating the logo.
		if ($logoAsset !== null) {
			$logoPath = $this->writeLogoAsset(id: $id, asset: $logoAsset);
			if ($logoPath !== null) {
				$theming['logo'] = $logoPath;
			} else {
				unset($theming['logo']);
			}
		}

		$warnings = $this->contrast->check(declarations: $declarations);

		$resolvedDescription = $description;
		if ($resolvedDescription === '') {
			$resolvedDescription = 'Custom token set: ' . $displayName;
		}

		$entry = [
			'name' => $displayName,
			'description' => $resolvedDescription,
			'theming' => array_merge($this->deriveTheming(declarations: $declarations), $theming),
			'warnings' => $warnings,
		];

		if ($version !== null) {
			$entry['version'] = $version;
		}

		if (empty($importWarnings) === false) {
			$entry['importWarnings'] = $importWarnings;
		}

		$manifest = $this->getManifest();
		$manifest[$id] = $entry;
		$this->saveManifest(manifest: $manifest);

		// Best-effort dark-variant generation at upload time (never allowed
		// to fail the upload itself — theming is presentation, not a hard
		// dependency; see openspec/specs/dark-mode/spec.md).
		try {
			$this->darkPalette->generateAndWrite(setId: $id, force: true);
		} catch (\Throwable $e) {
			// Swallow: a dark-variant generation failure must never break
			// the (already-persisted) custom token set upload.
		}

		return [
			'id' => $id,
			'warnings' => $warnings,
		];
	}//end store()

	/**
	 * Create or replace a custom token set by an already-known id, writing
	 * the canonical CSS content and manifest entry verbatim.
	 *
	 * Unlike {@see store()} — which derives the id from a display name and
	 * rejects a collision (409, first-upload contract) — this is the
	 * OTAP-promotion path (`config-portability` bundle import): the target
	 * environment may already have an id-matching custom set (replace) or
	 * may never have seen it before (create), and either outcome is
	 * expected and valid. The caller MUST have already re-serialised `$css`
	 * from validated declarations (e.g. via
	 * {@see CustomTokenSetValidator::serialize()}) — this method never
	 * re-validates, it only writes.
	 *
	 * @param string $id The custom set id (already `isCustomId()`-checked by the caller).
	 * @param array<string, mixed> $entry The manifest entry to persist verbatim (name, description, theming, warnings, …).
	 * @param string $css The canonical CSS file content to write verbatim.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When the file cannot be written (500).
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function replace(string $id, array $entry, string $css): void {
		$this->writeFile(path: $this->getCssPath(id: $id), contents: $css);

		$manifest = $this->getManifest();
		$manifest[$id] = $entry;
		$this->saveManifest(manifest: $manifest);
	}//end replace()

	/**
	 * Delete a custom token set: its CSS file, manifest entry, any generated
	 * dark variant, and any logo the converter extracted for it.
	 *
	 * When the deleted set is the active token set, the active set is reset to
	 * `nextcloud` in the same operation.
	 *
	 * @param string $id The custom set id (must start with custom-).
	 *
	 * @return bool True when something was removed, false when nothing matched.
	 *
	 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-2.1
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	public function delete(string $id): bool {
		if ($this->isCustomId(id: $id) === false) {
			return false;
		}

		$path = $this->getCssPath(id: $id);
		$removed = false;

		if (file_exists($path) === true) {
			unlink($path);
			$removed = true;
		}

		$manifest = $this->getManifest();
		if (isset($manifest[$id]) === true) {
			unset($manifest[$id]);
			$this->saveManifest(manifest: $manifest);
			$removed = true;
		}

		$this->darkPalette->deleteDarkVariant(setId: $id);
		$this->deleteLogoAsset(id: $id);

		$active = $this->config->getAppValue(Application::APP_ID, 'token_set', 'nextcloud');
		if ($active === $id) {
			$this->config->setAppValue(Application::APP_ID, 'token_set', 'nextcloud');
		}

		return $removed;
	}//end delete()

	/**
	 * List stored custom sets that still have a backing CSS file.
	 *
	 * A manifest entry without a file is dropped (filesystem is the source of
	 * truth), mirroring the discovery contract.
	 *
	 * @return array<int, array<string, mixed>> The custom sets with id+metadata.
	 *
	 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-2.2
	 */
	public function list(): array {
		$result = [];
		foreach ($this->getManifest() as $id => $meta) {
			if (file_exists($this->getCssPath(id: (string)$id)) === false) {
				continue;
			}

			$entry = [];
			if (is_array($meta) === true) {
				$entry = $meta;
			}

			$entry['id'] = $id;
			$result[] = $entry;
		}

		usort($result, fn ($a, $b) => strcasecmp(($a['name'] ?? $a['id']), ($b['name'] ?? $b['id'])));

		return $result;
	}//end list()

	/**
	 * Get the raw served CSS content for a custom set, for export.
	 *
	 * @param string $id The custom set id.
	 *
	 * @return string|null The file content, or null when the set does not exist.
	 *
	 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-2.1
	 */
	public function getRawContent(string $id): ?string {
		if ($this->isCustomId(id: $id) === false) {
			return null;
		}

		$path = $this->getCssPath(id: $id);
		if (file_exists($path) === false) {
			return null;
		}

		$content = file_get_contents($path);
		if ($content === false) {
			return null;
		}

		return $content;
	}//end getRawContent()

	/**
	 * Get the decoded custom-set manifest from appconfig.
	 *
	 * @return array<string, mixed> The manifest indexed by id (empty on absence/corruption).
	 *
	 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-2.2
	 */
	public function getManifest(): array {
		$raw = $this->config->getAppValue(Application::APP_ID, self::MANIFEST_KEY, '{}');
		$decoded = json_decode($raw, true);

		if (is_array($decoded) === false) {
			return [];
		}

		return $decoded;
	}//end getManifest()

	/**
	 * Persist the custom-set manifest to appconfig.
	 *
	 * @param array<string, mixed> $manifest The manifest indexed by id.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-2.2
	 */
	private function saveManifest(array $manifest): void {
		$this->config->setAppValue(
			Application::APP_ID,
			self::MANIFEST_KEY,
			json_encode($manifest, JSON_UNESCAPED_SLASHES)
		);
	}//end saveManifest()

	/**
	 * Derive the theming metadata from the uploaded declarations.
	 *
	 * @param array<string, string> $declarations The accepted declarations.
	 *
	 * @return array<string, string> The theming block (may be empty).
	 *
	 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-2.2
	 */
	private function deriveTheming(array $declarations): array {
		$theming = [];
		if (isset($declarations['--nldesign-color-primary']) === true) {
			$theming['primary_color'] = $declarations['--nldesign-color-primary'];
		}

		if (isset($declarations['--nldesign-color-background']) === true) {
			$theming['background_color'] = $declarations['--nldesign-color-background'];
		}

		return $theming;
	}//end deriveTheming()

	/**
	 * Write a converter-extracted logo under `img/logos/`.
	 *
	 * The accepted name is pinned to the set's own id — `img/logos/{id}.{ext}`
	 * and nothing else — so an uploaded theme can neither escape the directory
	 * nor overwrite a shipped municipality logo, whatever path the converter
	 * put in the asset. A write failure is not fatal: the token set itself is
	 * already on disk and only the Nextcloud logo sync is lost, so the caller
	 * drops `theming.logo` rather than failing the whole upload.
	 *
	 * @param string                              $id    The custom set id.
	 * @param array{path: string, contents: string} $asset The decoded logo.
	 *
	 * @return string|null The app-relative path that was written, or null on refusal/failure.
	 *
	 * @spec openspec/specs/theming-sync/spec.md
	 */
	private function writeLogoAsset(string $id, array $asset): ?string {
		$extension = strtolower(pathinfo((string)($asset['path'] ?? ''), PATHINFO_EXTENSION));
		if (in_array($extension, ['svg', 'png', 'jpg', 'gif', 'webp'], true) === false) {
			return null;
		}

		$relative = 'img/logos/' . $id . '.' . $extension;
		$directory = $this->appManager->getAppPath('thematiq') . '/img/logos';

		if (is_dir($directory) === false && mkdir($directory, 0755, true) === false && is_dir($directory) === false) {
			return null;
		}

		if (file_put_contents($directory . '/' . $id . '.' . $extension, (string)$asset['contents']) === false) {
			return null;
		}

		return $relative;
	}//end writeLogoAsset()

	/**
	 * Remove any logo written for a custom set by {@see self::writeLogoAsset()}.
	 *
	 * @param string $id The custom set id.
	 *
	 * @return void
	 */
	private function deleteLogoAsset(string $id): void {
		$directory = $this->appManager->getAppPath('thematiq') . '/img/logos';

		foreach (['svg', 'png', 'jpg', 'gif', 'webp'] as $extension) {
			$path = $directory . '/' . $id . '.' . $extension;
			if (is_file($path) === true) {
				unlink($path);
			}
		}
	}//end deleteLogoAsset()

	/**
	 * Resolve the absolute CSS path for a custom set id.
	 *
	 * @param string $id The custom set id (assumed already namespace-checked).
	 *
	 * @return string The absolute path under css/tokens/.
	 *
	 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-2.1
	 */
	private function getCssPath(string $id): string {
		return $this->appManager->getAppPath('thematiq') . '/css/tokens/' . $id . '.css';
	}//end getCssPath()

	/**
	 * Whether an id is a safe custom-set id (prefix + slug charset only).
	 *
	 * Guards against path traversal: a custom id may only contain the prefix
	 * plus `[a-z0-9-]`, so it can never escape css/tokens/.
	 *
	 * @param string $id The id to validate.
	 *
	 * @return bool True when the id is a safe custom id.
	 *
	 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-2.1
	 */
	public function isCustomId(string $id): bool {
		return preg_match('/^custom-[a-z0-9-]+$/', $id) === 1;
	}//end isCustomId()

	/**
	 * Write the CSS file atomically via a temp file + rename.
	 *
	 * @param string $path The destination path.
	 * @param string $contents The canonical CSS content.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When the file cannot be written or renamed.
	 *
	 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-2.1
	 */
	private function writeFile(string $path, string $contents): void {
		$tmpPath = $path . '.tmp';

		if (file_put_contents($tmpPath, $contents) === false) {
			throw new RuntimeException(
				message: 'Could not write ' . $tmpPath . '. Ensure the web server has write access to css/tokens/.',
				code: 500
			);
		}

		if (rename($tmpPath, $path) === false) {
			if (file_exists($tmpPath) === true) {
				unlink($tmpPath);
			}

			throw new RuntimeException(message: 'Temp file could not be renamed to ' . $path . '.', code: 500);
		}
	}//end writeFile()
}//end class
