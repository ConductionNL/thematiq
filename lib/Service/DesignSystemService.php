<?php

/**
 * NL Design — Design System Service.
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
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-35
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-36
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-37
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-38
 * @spec openspec/specs/icon-packs/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IConfig;

/**
 * Service for resolving design system stylesheet bundles and icon packs.
 *
 * Reads design-systems.json and token-sets.json to determine which CSS
 * stylesheets should be loaded for a given token set, and which icon pack(s)
 * (openspec/specs/icon-packs/spec.md) the active design system serves.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-35
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-36
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-37
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-38
 * @spec openspec/specs/icon-packs/spec.md
 */
class DesignSystemService {

	/**
	 * The appconfig key holding the (optional) instance-wide icon-pack
	 * override — a directory name under img/icons/, e.g. "dsfr".
	 */
	private const ICON_PACK_CONFIG_KEY = 'icon_pack';

	/**
	 * Where the settings page's Documentation link goes for a design system
	 * that names no documentation of its own: this app's documentation.
	 *
	 * @var string
	 */
	public const DEFAULT_DOCUMENTATION_URL = 'https://thematiq.conduction.nl';

	/**
	 * The app manager for resolving paths.
	 *
	 * @var IAppManager
	 */
	private IAppManager $appManager;

	/**
	 * Reads the appconfig icon-pack override.
	 *
	 * @var IConfig
	 */
	private IConfig $config;

	/**
	 * Cached design systems (indexed by id).
	 *
	 * @var array<string, array>|null
	 */
	private ?array $designSystems = null;

	/**
	 * Cached token set metadata (indexed by id).
	 *
	 * @var array<string, array>|null
	 */
	private ?array $tokenSetMeta = null;

	/**
	 * The admin-created sets' manifest, decoded once per request.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $customTokenSetMeta = null;

	/**
	 * Constructor.
	 *
	 * @param IAppManager $appManager The app manager for resolving paths.
	 * @param IConfig $config Reads the appconfig icon-pack override.
	 */
	public function __construct(IAppManager $appManager, IConfig $config) {
		$this->appManager = $appManager;
		$this->config = $config;
	}//end __construct()

	/**
	 * Get the absolute path to the app's directory.
	 *
	 * @return string The app directory path.
	 */
	private function getAppPath(): string {
		return $this->appManager->getAppPath('thematiq');
	}//end getAppPath()

	/**
	 * Get all available design systems.
	 *
	 * @return array<string, array{id: string, name: string, description: string, stylesheets: string[], icon_pack?: string|string[]}> Indexed by id.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-35
	 */
	public function getDesignSystems(): array {
		if ($this->designSystems !== null) {
			return $this->designSystems;
		}

		$path = $this->getAppPath() . '/design-systems.json';
		$this->designSystems = $this->readJsonManifest(path: $path);

		return $this->designSystems;
	}//end getDesignSystems()

	/**
	 * Get a single design system by id.
	 *
	 * Returns a fallback with empty stylesheets if the id is not found.
	 *
	 * @param string $id The design system identifier.
	 *
	 * @return array{id: string, name: string, description: string, stylesheets: string[], icon_pack?: string|string[]} The design system.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-36
	 */
	public function getDesignSystem(string $id): array {
		$systems = $this->getDesignSystems();

		if (isset($systems[$id]) === true) {
			return $systems[$id];
		}

		// Unknown design system — fall back to no stylesheets for safety.
		return [
			'id' => $id,
			'name' => $id,
			'description' => 'Unknown design system',
			'stylesheets' => [],
		];
	}//end getDesignSystem()

	/**
	 * Get metadata for a token set (including its design_system field).
	 *
	 * @param string $tokenSetId The token set identifier.
	 *
	 * @return array The token set metadata from token-sets.json (empty array if not found).
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-37
	 */
	public function getTokenSetMeta(string $tokenSetId): array {
		if ($this->tokenSetMeta === null) {
			$path = $this->getAppPath() . '/token-sets.json';
			$this->tokenSetMeta = $this->readJsonManifest(path: $path);
		}

		if (isset($this->tokenSetMeta[$tokenSetId]) === true) {
			return $this->tokenSetMeta[$tokenSetId];
		}

		return $this->getCustomTokenSetMeta(tokenSetId: $tokenSetId);
	}//end getTokenSetMeta()

	/**
	 * A custom set's manifest entry, from appconfig rather than a shipped file.
	 *
	 * Custom sets used to answer `[]` here, so every one of them fell back to
	 * `nldesign` — and that quietly decided far more than the token list did. A
	 * theme saved from the stock Nextcloud set came back wearing Fira Sans,
	 * `defaults.css` and `element-overrides.css`, because "no metadata" was read
	 * as "NL Design System". An admin who saved the stock theme with one colour
	 * changed did not get the stock theme with one colour changed.
	 *
	 * So a custom set now carries the design system it was CREATED from, and
	 * this reads it back. One written before that field existed still has no
	 * `design_system`, and still resolves to `nldesign` through the same `??` in
	 * CssInjectionService — which is what those sets have always been, so they
	 * do not change under an admin who is not expecting it.
	 *
	 * Read straight from the appconfig key rather than through
	 * CustomTokenSetService: that service reaches back into this one, and this
	 * lookup runs on every themed page render.
	 *
	 * @param string $tokenSetId The token set identifier.
	 *
	 * @return array<string, mixed> The manifest entry, or [] when there is none.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function getCustomTokenSetMeta(string $tokenSetId): array {
		if (str_starts_with($tokenSetId, CustomTokenSetService::ID_PREFIX) === false) {
			return [];
		}

		if ($this->customTokenSetMeta === null) {
			$raw = $this->config->getAppValue(
				Application::APP_ID,
				CustomTokenSetService::MANIFEST_KEY,
				'{}'
			);

			$decoded = json_decode($raw, true);
			if (is_array($decoded) === false) {
				$decoded = [];
			}

			$this->customTokenSetMeta = $decoded;
		}

		$entry = ($this->customTokenSetMeta[$tokenSetId] ?? []);
		if (is_array($entry) === false) {
			return [];
		}

		return $entry;
	}//end getCustomTokenSetMeta()

	/**
	 * Get a design system's icon pack, normalized to an ordered list.
	 *
	 * The `icon_pack` manifest field accepts either a single pack directory
	 * name (a string, one-element-list shorthand) or an ordered array of
	 * pack directory names. A design system with no `icon_pack` field (or an
	 * unknown design system id) returns `[]` — no pack served, Nextcloud
	 * stock icons apply.
	 *
	 * @param string $designSystemId The design system identifier.
	 *
	 * @return string[] The ordered pack directory list (empty when no pack is declared).
	 *
	 * @spec openspec/specs/icon-packs/spec.md
	 */
	public function getIconPacks(string $designSystemId): array {
		$system = $this->getDesignSystem(id: $designSystemId);

		return $this->normalizeIconPack(pack: $system['icon_pack'] ?? null);
	}//end getIconPacks()

	/**
	 * Resolve the active icon pack for a token set, highest precedence first:
	 * (1) a valid appconfig `icon_pack` admin override, (2) the token set's
	 * own `icon_pack` (a reserved per-set override field, honored when
	 * present — not shipped on any token set today), (3) the `icon_pack` of
	 * the token set's `design_system`, (4) `[]` (no pack).
	 *
	 * Always safe: an unknown token set, an unknown design system, or an
	 * override naming a non-existent pack directory degrades to falling
	 * through the chain rather than throwing.
	 *
	 * @param string $tokenSetId The active token set identifier.
	 *
	 * @return string[] The resolved ordered pack directory list (possibly empty).
	 *
	 * @spec openspec/specs/icon-packs/spec.md
	 */
	public function resolveActiveIconPacks(string $tokenSetId): array {
		$override = trim((string)$this->config->getAppValue(appName: Application::APP_ID, key: self::ICON_PACK_CONFIG_KEY, default: ''));
		if ($override !== '' && $this->packDirectoryExists(pack: $override) === true) {
			return [$override];
		}

		$tokenSetMeta = $this->getTokenSetMeta(tokenSetId: $tokenSetId);

		// Reserved per-token-set override (future field) — honored when present.
		$tokenSetPack = $this->normalizeIconPack(pack: $tokenSetMeta['icon_pack'] ?? null);
		if ($tokenSetPack !== []) {
			return $tokenSetPack;
		}

		// An UNKNOWN token set (no manifest entry at all → empty meta) is not
		// themed, so it resolves to no pack.
		if ($tokenSetMeta === []) {
			return [];
		}

		// A KNOWN token set that merely OMITS design_system defaults to
		// `nldesign` — the same fallback TokenSetService::getAvailableTokenSets()
		// and Capabilities apply. Without this, the ~33 Dutch-government sets
		// that omit the field (rijkshuisstijl and the municipality sets carry
		// id/name/description/theming but no design_system) would resolve to NO
		// icon pack instead of the Dutch packs, silently breaking the
		// "Dutch government → Dutch icons" contract.
		$designSystemId = ($tokenSetMeta['design_system'] ?? '');
		if (is_string($designSystemId) === false || $designSystemId === '') {
			$designSystemId = 'nldesign';
		}

		return $this->getIconPacks(designSystemId: $designSystemId);
	}//end resolveActiveIconPacks()

	/**
	 * Resolve an icon name to its `imagePath`-relative path within the
	 * active pack(s) for a token set — the first pack (in declared order)
	 * whose `img/icons/{pack}/{name}.svg` exists on disk wins.
	 *
	 * Rejects a `$name` containing a path separator or `..` by returning
	 * `null` (no path traversal outside `img/icons/`), and returns `null`
	 * when no active pack contains the name (or no pack is active).
	 *
	 * @param string $name The icon's basename, without the `.svg` suffix.
	 * @param string $tokenSetId The active token set identifier.
	 *
	 * @return string|null The `icons/{pack}/{name}.svg` path, or null.
	 *
	 * @spec openspec/specs/icon-packs/spec.md
	 */
	public function resolveIconPath(string $name, string $tokenSetId): ?string {
		if ($this->isSafeIconName(name: $name) === false) {
			return null;
		}

		foreach ($this->resolveActiveIconPacks(tokenSetId: $tokenSetId) as $pack) {
			$file = $this->getAppPath() . '/img/icons/' . $pack . '/' . $name . '.svg';
			if (is_file($file) === true) {
				return 'icons/' . $pack . '/' . $name . '.svg';
			}
		}

		return null;
	}//end resolveIconPath()

	/**
	 * Normalize an `icon_pack` manifest value to an ordered string list.
	 *
	 * @param mixed $pack The raw `icon_pack` value (string, array, or absent/null).
	 *
	 * @return string[] The normalized ordered pack list.
	 */
	private function normalizeIconPack(mixed $pack): array {
		if (is_string($pack) === true && $pack !== '') {
			return [$pack];
		}

		if (is_array($pack) === true) {
			return array_values(
				array_filter(
					$pack,
					static fn ($entry): bool => is_string($entry) === true && $entry !== ''
				)
			);
		}

		return [];
	}//end normalizeIconPack()

	/**
	 * Whether `img/icons/{pack}/` exists on disk, rejecting a `$pack`
	 * containing a path separator or `..` (no traversal outside img/icons/).
	 *
	 * @param string $pack The candidate pack directory name.
	 *
	 * @return bool True when the pack directory exists.
	 */
	private function packDirectoryExists(string $pack): bool {
		if ($this->isSafePathSegment(segment: $pack) === false) {
			return false;
		}

		return is_dir($this->getAppPath() . '/img/icons/' . $pack);
	}//end packDirectoryExists()

	/**
	 * Whether an icon name is safe to interpolate into a filesystem path
	 * (non-empty, no path separators, no `..`).
	 *
	 * @param string $name The candidate icon name.
	 *
	 * @return bool True when safe.
	 */
	private function isSafeIconName(string $name): bool {
		return $name !== '' && $this->isSafePathSegment(segment: $name);
	}//end isSafeIconName()

	/**
	 * Whether a string is safe to use as a single path segment (no `/`, `\`,
	 * or `..`).
	 *
	 * @param string $segment The candidate path segment.
	 *
	 * @return bool True when safe.
	 */
	private function isSafePathSegment(string $segment): bool {
		return $segment !== ''
			&& str_contains($segment, '/') === false
			&& str_contains($segment, '\\') === false
			&& str_contains($segment, '..') === false;
	}//end isSafePathSegment()

	/**
	 * Whether a generated `css/tokens/dark/{id}.css` file exists for a token
	 * set. Reuses this service's existing `IAppManager` dependency so the
	 * bootstrap-time injection point (`Application::injectThemeCSS()`) does
	 * not need its own path-resolving dependency (see
	 * openspec/specs/dark-mode/spec.md).
	 *
	 * @param string $tokenSetId The token set identifier.
	 *
	 * @return bool True when the generated dark variant file exists.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	public function hasGeneratedDarkVariant(string $tokenSetId): bool {
		return is_file($this->getAppPath() . '/css/tokens/dark/' . $tokenSetId . '.css');
	}//end hasGeneratedDarkVariant()

	/**
	 * The documentation link for every design system, by design system id.
	 *
	 * A design system names its documentation in `documentation_url` in
	 * design-systems.json. One that names none, or names something that is not
	 * an https URL, gets this app's own documentation instead, so the settings
	 * page never sends an admin to another design system's docs (#662).
	 *
	 * @return array<string, string> Documentation URLs indexed by design system id.
	 *
	 * @spec openspec/specs/admin-settings/spec.md#requirement-documentation-link-follows-the-design-system
	 */
	public function getDocumentationUrls(): array {
		$urls = [];
		foreach ($this->getDesignSystems() as $id => $designSystem) {
			$url = ($designSystem['documentation_url'] ?? '');
			if (is_string($url) === false || str_starts_with($url, 'https://') === false) {
				$url = self::DEFAULT_DOCUMENTATION_URL;
			}

			$urls[(string)$id] = $url;
		}

		return $urls;
	}//end getDocumentationUrls()

	/**
	 * Get all design systems as a flat list (for API responses).
	 *
	 * @return array<array{id: string, name: string, description: string, stylesheets: string[], icon_pack?: string|string[]}> List of design systems.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-35
	 */
	public function getDesignSystemsList(): array {
		return array_values($this->getDesignSystems());
	}//end getDesignSystemsList()

	/**
	 * Read a JSON manifest file and index entries by their 'id' field.
	 *
	 * @param string $path Absolute path to the JSON file.
	 *
	 * @return array<string, array> Entries indexed by id.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-38
	 */
	private function readJsonManifest(string $path): array {
		if (file_exists($path) === false) {
			return [];
		}

		$content = file_get_contents($path);
		if ($content === false) {
			return [];
		}

		$data = json_decode($content, true);
		if (is_array($data) === false) {
			return [];
		}

		$indexed = [];
		foreach ($data as $entry) {
			if (isset($entry['id']) === true) {
				$indexed[$entry['id']] = $entry;
			}
		}

		return $indexed;
	}//end readJsonManifest()
}//end class
