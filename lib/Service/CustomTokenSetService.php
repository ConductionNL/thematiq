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
 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-2.1
 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-2.2
 * @spec openspec/specs/custom-token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileStore;
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
 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-2.1
 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-2.2
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
	public const ID_PREFIX = CustomTokenSetId::PREFIX;

	/**
	 * Where an uploaded set's stylesheet and logo are stored: app data, never the app directory.
	 *
	 * @var RuntimeFileStore
	 */
	private RuntimeFileStore $store;

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
	 * @param RuntimeFileStore $store Where an uploaded set's files are stored.
	 * @param IConfig $config The config service.
	 * @param CustomTokenSetValidator $validator The CSS validator.
	 * @param ContrastService $contrast The contrast service.
	 * @param DarkPaletteService $darkPalette The dark-variant generation service.
	 */
	public function __construct(
		RuntimeFileStore $store,
		IConfig $config,
		CustomTokenSetValidator $validator,
		ContrastService $contrast,
		DarkPaletteService $darkPalette,
	) {
		$this->store = $store;
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
	 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-2.1
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
	 * @param NewCustomTokenSet $set The new set's fields, each documented on NewCustomTokenSet.
	 *
	 * @return array{id: string, warnings: array<int, array<string, mixed>>} The result.
	 *
	 * @throws RuntimeException When the slug is empty (422), the id collides
	 *                          (409), or the file cannot be written (500).
	 *
	 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-2.1
	 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-2.2
	 * @spec openspec/specs/custom-token-sets/spec.md
	 *
	 */
	public function store(NewCustomTokenSet $set): array {
		$id = $this->reserveId(displayName: $set->displayName);
		$this->writeFile(
			name: $this->getCssPath(id: $id),
			contents: ($set->css ?? $this->validator->serialize(declarations: $set->declarations))
		);

		// Written before the manifest entry that points at it: `theming.logo`
		// is validated by ThemingService against the file EXISTING, so a
		// manifest entry naming a file that was never written would fail the
		// sync with "Image file not found" instead of updating the logo.
		$theming = $set->theming;
		if ($set->logoAsset !== null) {
			$theming = $this->withLogoAsset(theming: $theming, logo: ['id' => $id, 'asset' => $set->logoAsset]);
		}

		$warnings = $this->contrast->check(declarations: $set->declarations);
		$entry = $this->withOptionalFields(
			entry: [
				'name' => $set->displayName,
				'description' => $this->describe(description: $set->description, displayName: $set->displayName),
				'theming' => array_merge($this->deriveTheming(declarations: $set->declarations), $theming),
				'warnings' => $warnings,
			],
			optional: ['version' => $set->version, 'importWarnings' => $set->importWarnings, 'designSystem' => $set->designSystem]
		);
		$this->register(id: $id, entry: $entry);

		return [
			'id' => $id,
			'warnings' => $warnings,
		];
	}//end store()

	/**
	 * The id a new set of this name gets, refused when the name slugs to nothing or is taken.
	 *
	 * @param string $displayName The admin display name.
	 *
	 * @return string The set id.
	 *
	 * @throws RuntimeException When the slug is empty (422) or the id collides (409).
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function reserveId(string $displayName): string {
		$slug = $this->slugify(name: $displayName);
		if ($slug === '') {
			throw new RuntimeException(message: 'A token set name must contain at least one letter or digit.', code: 422);
		}

		$id = self::ID_PREFIX . $slug;
		if ($this->store->exists(name: $this->getCssPath(id: $id)) === true || isset($this->getManifest()[$id]) === true) {
			throw new RuntimeException(message: 'A custom token set named "' . $displayName . '" already exists. Delete or rename it first.', code: 409);
		}

		return $id;
	}//end reserveId()

	/**
	 * Write a converter-decoded logo and point the theming at it.
	 *
	 * A logo the store refuses (or an unsupported extension) drops any `logo` the theming named.
	 *
	 * @param array<string, string> $theming The theming overrides.
	 * @param array{id: string, asset: array{path: string, contents: string}} $logo The set id and the decoded asset.
	 *
	 * @return array<string, string> The theming overrides with the logo path.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function withLogoAsset(array $theming, array $logo): array {
		$logoPath = $this->writeLogoAsset(id: $logo['id'], asset: $logo['asset']);
		unset($theming['logo']);
		if ($logoPath !== null) {
			$theming['logo'] = $logoPath;
		}

		return $theming;
	}//end withLogoAsset()

	/**
	 * The description a set is listed with: the given one, or one naming the set.
	 *
	 * @param string $description The given description, possibly empty.
	 * @param string $displayName The admin display name.
	 *
	 * @return string
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function describe(string $description, string $displayName): string {
		if ($description === '') {
			return 'Custom token set: ' . $displayName;
		}

		return $description;
	}//end describe()

	/**
	 * Add the fields a caller may leave out to a manifest entry.
	 *
	 * The design system is the one the set was MADE from, when the caller knows it. Without it
	 * every custom set resolved to `nldesign`, so a theme saved from the stock Nextcloud set came
	 * back with the whole NL Design System layer on top of it. Left unset when the caller has no
	 * opinion (an upload of an unknown document), which still resolves to `nldesign` downstream.
	 *
	 * @param array<string, mixed> $entry The manifest entry.
	 * @param array{version: string|null, importWarnings: array<int, array<string, mixed>>, designSystem: string|null} $optional The optional fields.
	 *
	 * @return array<string, mixed> The entry with the given optional fields.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function withOptionalFields(array $entry, array $optional): array {
		if ($optional['version'] !== null) {
			$entry['version'] = $optional['version'];
		}

		if ($optional['importWarnings'] !== []) {
			$entry['importWarnings'] = $optional['importWarnings'];
		}

		if ($optional['designSystem'] !== null && $optional['designSystem'] !== '') {
			$entry['design_system'] = $optional['designSystem'];
		}

		return $entry;
	}//end withOptionalFields()

	/**
	 * Add the entry to the manifest and make the set's dark variant.
	 *
	 * Dark-variant generation is best effort: theming is presentation, not a hard dependency, so
	 * a failure never breaks the already-persisted upload (openspec/specs/dark-mode/spec.md).
	 *
	 * @param string $id The set id.
	 * @param array<string, mixed> $entry The manifest entry.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function register(string $id, array $entry): void {
		$manifest = $this->getManifest();
		$manifest[$id] = $entry;
		$this->saveManifest(manifest: $manifest);

		try {
			$this->darkPalette->generateAndWrite(setId: $id, force: true);
		} catch (\Throwable $e) {
			// Swallow: a dark-variant generation failure must never break
			// the (already-persisted) custom token set upload.
		}
	}//end register()

	/**
	 * Keep the dark values an uploaded file brought for a stored set, and make its dark variant again.
	 *
	 * They win over the derived dark values in this and every later generation, and leave with the set.
	 *
	 * @param string $id The stored set's id.
	 * @param array<string, string> $declarations Validated token name => dark value.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dark-colours-from-a-file/spec.md#requirement-dark-colours-from-a-file-are-kept
	 */
	public function keepDarkValues(string $id, array $declarations): void {
		(new DarkPins(store: $this->store))->write(setId: $id, declarations: $declarations);

		try {
			$this->darkPalette->generateAndWrite(setId: $id, force: true);
		} catch (\Throwable $e) {
			// As at upload: a dark-variant failure never breaks the stored set.
		}
	}//end keepDarkValues()

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
		$this->writeFile(name: $this->getCssPath(id: $id), contents: $css);

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
	 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-2.1
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	public function delete(string $id): bool {
		if ($this->isCustomId(id: $id) === false) {
			return false;
		}

		$name = $this->getCssPath(id: $id);
		$removed = false;

		if ($this->store->exists(name: $name) === true) {
			$this->store->delete(name: $name);
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
	 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-2.2
	 */
	public function list(): array {
		$result = [];
		foreach ($this->getManifest() as $id => $meta) {
			if ($this->isCustomId(id: (string)$id) === false
				|| $this->store->exists(name: $this->getCssPath(id: (string)$id)) === false
			) {
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
	 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-2.1
	 */
	public function getRawContent(string $id): ?string {
		if ($this->isCustomId(id: $id) === false) {
			return null;
		}

		return $this->store->read(name: $this->getCssPath(id: $id));
	}//end getRawContent()

	/**
	 * Get the decoded custom-set manifest from appconfig.
	 *
	 * @return array<string, mixed> The manifest indexed by id (empty on absence/corruption).
	 *
	 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-2.2
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
	 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-2.2
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
	 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-2.2
	 */
	private function deriveTheming(array $declarations): array {
		$theming = [];
		$page = $this->contrast->parseColor(value: (string)($declarations['--nldesign-color-background'] ?? '#ffffff')) ?? [255, 255, 255];
		$wanted = array_intersect_key(['--nldesign-color-primary' => 'primary_color', '--nldesign-color-background' => 'background_color'], $declarations);
		foreach ($wanted as $token => $key) {
			$theming[$key] = $declarations[$token];
			// Core theming has no transparency (it refuses 8 digits), so it gets the blend over the page.
			$flat = $this->contrast->flatten(value: $declarations[$token], under: $page);
			if ($flat !== null) {
				$theming[$key] = $flat;
				$theming[$key . '_original'] = $declarations[$token];
			}
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
	 * @param string $id The custom set id.
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
		try {
			$this->store->write(name: $relative, content: (string)$asset['contents']);
		} catch (RuntimeException $e) {
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
		foreach (['svg', 'png', 'jpg', 'gif', 'webp'] as $extension) {
			$this->store->delete(name: 'img/logos/' . $id . '.' . $extension);
		}
	}//end deleteLogoAsset()

	/**
	 * The runtime file name of a custom set's stylesheet.
	 *
	 * @param string $id The custom set id (assumed already namespace-checked).
	 *
	 * @return string The name in the store, under `css/tokens/`.
	 *
	 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-2.1
	 */
	private function getCssPath(string $id): string {
		return 'css/tokens/' . $id . '.css';
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
	 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-2.1
	 */
	public function isCustomId(string $id): bool {
		return preg_match('/^custom-[a-z0-9-]+$/', $id) === 1;
	}//end isCustomId()

	/**
	 * Write the CSS file to the store.
	 *
	 * @param string $name The runtime file name.
	 * @param string $contents The canonical CSS content.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When the store cannot write the file (code 500).
	 *
	 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-2.1
	 */
	private function writeFile(string $name, string $contents): void {
		try {
			$this->store->write(name: $name, content: $contents);
		} catch (RuntimeException $e) {
			throw new RuntimeException(message: 'The token set could not be stored: ' . $e->getMessage(), code: 500, previous: $e);
		}
	}//end writeFile()
}//end class
