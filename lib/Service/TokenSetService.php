<?php

/**
 * NL Design Token Set Service.
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
 * @spec openspec/specs/token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\ICache;
use OCP\IConfig;

/**
 * Service for filesystem-based token set discovery.
 *
 * Discovers available token sets by scanning css/tokens/ directory and merging
 * metadata from token-sets.json (shipped sets) and the custom_token_sets
 * appconfig manifest (admin-uploaded custom-* sets).
 *
 * @phpstan-type TokenSetEntry array{
 *     id: string,
 *     name: string,
 *     description: string,
 *     design_system: string,
 *     theming?: array<string, mixed>,
 *     custom?: bool,
 *     warnings?: array<int, array<string, mixed>>,
 *     upstreamVersion?: string,
 *     upstreamRef?: string,
 *     licence?: array<string, mixed>
 * }
 * @psalm-type   TokenSetEntry array{
 *     id: string,
 *     name: string,
 *     description: string,
 *     design_system: string,
 *     theming?: array<string, mixed>,
 *     custom?: bool,
 *     warnings?: array<int, array<string, mixed>>,
 *     upstreamVersion?: string,
 *     upstreamRef?: string,
 *     licence?: array<string, mixed>
 * }
 *
 * @spec openspec/specs/token-sets/spec.md
 * @spec openspec/specs/custom-token-sets/spec.md
 */
class TokenSetService {

	use ProvidesLicenceGate;

	/**
	 * TTL (seconds) for a shipped set's cached warnings. The key already
	 * changes with the app version and the set's own CSS; the TTL bounds how
	 * long an edit to a shared design-system file, which the key does not
	 * cover, can go unseen on a development checkout.
	 */
	private const WARNINGS_CACHE_TTL = 3600;

	/**
	 * Distributed cache for the resolved WCAG level, keyed by set id.
	 * Deliberately the same `ICache` prefix (`thematiq_wcag_level`)
	 * `Capabilities` uses, so the public catalogue and the active-theme
	 * capability share one cache entry per set id. The two MUST be edited
	 * together — nothing enforces the pairing, and when the app-id rename moved
	 * only `Capabilities`, the sharing this comment promises silently stopped
	 * happening and the level was computed twice.
	 *
	 * @var ICache
	 */
	private ICache $wcagCache;

	/**
	 * Local cache for a shipped set's audit warnings (see cachedShippedWarnings()).
	 *
	 * @var ICache
	 */
	private ICache $warningsCache;

	/**
	 * Constructor.
	 *
	 * @param TokenSetFiles $setFiles The app manager, the runtime file store and the set file reader.
	 * @param TokenSetRuntime $runtime The config, the cache factory and the logger.
	 * @param TokenSetAudits $audits The contrast, vocabulary and typeface audits.
	 */
	public function __construct(
		private readonly TokenSetFiles $setFiles,
		private readonly TokenSetRuntime $runtime,
		private readonly TokenSetAudits $audits,
	) {
		$this->wcagCache = $runtime->cacheFactory->createDistributed(prefix: 'thematiq_wcag_level');
		$this->warningsCache = $runtime->cacheFactory->createLocal(prefix: 'thematiq_set_warnings');
	}//end __construct()

	/**
	 * Get the absolute path to the app's directory.
	 *
	 * Public because `TokenSetSelectionPolicy` reads `token-sets.json` from the
	 * same root this service discovers from, and resolving the path twice is how
	 * two layers end up disagreeing about which app directory they are in.
	 *
	 * @return string The app directory path.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-filesystem-based-discovery
	 */
	public function getAppPath(): string {
		return $this->setFiles->appManager->getAppPath('thematiq');
	}//end getAppPath()

	/**
	 * The config service the licence gate keeps its confirmations in.
	 *
	 * @return IConfig The config.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-every-path-that-applies-a-set-asks-the-same-gate
	 */
	protected function licenceConfig(): IConfig {
		return $this->runtime->config;
	}//end licenceConfig()

	/**
	 * Get all available token sets with metadata.
	 *
	 * Scans css/tokens/ for CSS files and merges metadata from token-sets.json.
	 * Entries carry an optional upstreamVersion/upstreamRef pass-through when
	 * present in the manifest — inert for every consumer except the
	 * upstream-freshness comparison.
	 *
	 * @return array<int, TokenSetEntry> The available token sets.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	public function getAvailableTokenSets(): array {
		$appPath = $this->getAppPath();
		// Shipped metadata (token-sets.json), uploaded metadata (appconfig), and
		// the Nextcloud branding each set captured when it was saved (see
		// BrandingCaptureService): what the admin had on when they saved this
		// theme, which is what applying the theme is meant to bring back.
		$sources = [
			'appPath' => $appPath,
			'shipped' => $this->readManifest(manifestPath: $appPath . '/token-sets.json'),
			'custom' => $this->readCustomManifest(),
			'captured' => $this->readCapturedTheming(),
		];

		// Scan the release for shipped sets and the store for uploaded ones.
		$tokenSets = [];
		foreach ($this->setFiles->files->fileNames(appPath: $appPath, directory: 'css/tokens', store: $this->setFiles->store) as $file) {
			if (str_ends_with($file, '.css') === true) {
				$tokenSets[] = $this->catalogueEntry(id: basename($file, '.css'), sources: $sources);
			}
		}

		// Sort alphabetically by name.
		usort($tokenSets, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

		return $tokenSets;
	}//end getAvailableTokenSets()

	/**
	 * The Nextcloud branding each set captured when it was saved, by set id.
	 *
	 * @return array<string, mixed> The captured theming blocks; empty when none are stored or the value is unreadable.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	private function readCapturedTheming(): array {
		$captured = json_decode(
			(string)$this->runtime->config->getAppValue(Application::APP_ID, BrandingCaptureService::CAPTURED_KEY, '{}'),
			true
		);
		if (is_array($captured) === false) {
			return [];
		}

		return $captured;
	}//end readCapturedTheming()

	/**
	 * Project the catalogue to the closed, non-admin, 5-field public shape:
	 * `{ id, name, design_system, theming: {primary_color, background_color,
	 * logo?}, wcagLevel }`.
	 *
	 * Reuses `getAvailableTokenSets()` verbatim for discovery — no second
	 * scan, no second manifest-merge logic — and deliberately omits
	 * `description`, `custom`, `warnings`, `upstreamVersion`, and
	 * `upstreamRef`, which are internal/admin-only fields. `wcagLevel` is
	 * computed via the same `ShippedTokenSetAuditService::auditSet()` path
	 * `Capabilities::computeWcagLevel()` already uses for the active set,
	 * cached under the same `ICache` prefix (`thematiq_wcag_level`).
	 *
	 * The public catalogue entries.
	 *
	 * @return array<int, array{id: string, name: string, design_system: string, theming: array<string, string>, wcagLevel: string|null}>
	 *
	 * @spec openspec/specs/app-token-set-selection/spec.md
	 */
	public function getPublicCatalogue(): array {
		$appPath = $this->getAppPath();

		$catalogue = [];
		foreach ($this->getAvailableTokenSets() as $entry) {
			$theming = [
				'primary_color' => ($entry['theming']['primary_color'] ?? null),
				'background_color' => ($entry['theming']['background_color'] ?? null),
			];
			$logo = ($entry['theming']['logo'] ?? null);
			if (is_string($logo) === true && $logo !== '') {
				$theming['logo'] = $logo;
			}

			$catalogue[] = [
				'id' => $entry['id'],
				'name' => $entry['name'],
				'design_system' => $entry['design_system'],
				'theming' => $theming,
				'wcagLevel' => $this->audits->audit->computeCachedWcagLevel(
					cache: $this->wcagCache,
					appPath: $appPath,
					tokenSetId: $entry['id'],
					tokenSetMeta: $entry
				),
			];
		}//end foreach

		return $catalogue;
	}//end getPublicCatalogue()

	/**
	 * Apply optional upstream provenance fields (upstream-freshness spec)
	 * onto a token set entry. Passed through unmodified when present in the
	 * manifest; absence never affects discovery, validation, activation, or
	 * rendering — only the freshness comparison ever interprets them.
	 *
	 * @param array<string, mixed> $tokenSet The token set entry being built.
	 * @param array<string, mixed> $meta The merged manifest metadata for this id.
	 *
	 * @return array<string, mixed> The token set entry with provenance applied.
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	private function applyProvenance(array $tokenSet, array $meta): array {
		if (isset($meta['upstreamVersion']) === true) {
			$tokenSet['upstreamVersion'] = $meta['upstreamVersion'];
		}

		if (isset($meta['upstreamRef']) === true) {
			$tokenSet['upstreamRef'] = $meta['upstreamRef'];
		}

		$licence = $this->licenceGate()->licenceOf(tokenSetId: (string)$tokenSet['id']);
		if ($licence !== null) {
			$licence['confirmed'] = ($this->licenceGate()->confirmation(tokenSetId: (string)$tokenSet['id']) !== null);
			$tokenSet['licence'] = $licence;
		}

		return $tokenSet;
	}//end applyProvenance()

	/**
	 * The catalogue entry of one discovered set file.
	 *
	 * @param string $id The token set id (the file name without `.css`).
	 * @param array<string, mixed> $sources The app path, and the shipped and uploaded metadata and captured branding by id.
	 *
	 * @return array<string, mixed> The entry.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	private function catalogueEntry(string $id, array $sources): array {
		// Shipped manifest takes precedence on an (impossible) id collision;
		// resolveMeta() logs it so the operator can investigate.
		$shippedMeta = ($sources['shipped'][$id] ?? null);
		$meta = $this->resolveMeta(id: $id, shippedMeta: $shippedMeta, customMeta: ($sources['custom'][$id] ?? null));

		$tokenSet = $this->baseEntry(id: $id, meta: $meta);
		$theming = $this->entryTheming(candidates: [($sources['captured'][$id] ?? null), ($meta['theming'] ?? null)]);
		if ($theming !== null) {
			$tokenSet['theming'] = $theming;
		}

		return $this->applyWarnings(
			tokenSet: $this->applyProvenance(tokenSet: $tokenSet, meta: ($meta ?? [])),
			meta: ($meta ?? []),
			appPath: $sources['appPath'],
			id: $id,
			isCustom: (str_starts_with($id, 'custom-') === true && $shippedMeta === null)
		);
	}//end catalogueEntry()

	/**
	 * The id, name, description and design system of a catalogue entry, with
	 * defaults where the metadata has none.
	 *
	 * @param string $id The token set id.
	 * @param array<string, mixed>|null $meta The resolved metadata.
	 *
	 * @return array<string, mixed> The entry.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	private function baseEntry(string $id, ?array $meta): array {
		return [
			'id' => $id,
			'name' => $meta['name'] ?? $this->formatName(id: $id),
			'description' => $meta['description'] ?? 'Design tokens for ' . $this->formatName(id: $id),
			'design_system' => $meta['design_system'] ?? 'nldesign',
		];
	}//end baseEntry()

	/**
	 * The theming block of a catalogue entry: the captured branding when the
	 * set has one, else the manifest's own block.
	 *
	 * @param array<int, mixed> $candidates The captured branding, then the manifest's block.
	 *
	 * @return array<string, mixed>|null The first candidate that is a block, or null.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	private function entryTheming(array $candidates): ?array {
		foreach ($candidates as $theming) {
			if (is_array($theming) === true) {
				return $theming;
			}
		}

		return null;
	}//end entryTheming()

	/**
	 * Resolve the merged metadata for one discovered id, logging (and
	 * dropping) the custom-manifest side on an (impossible) id collision so
	 * the shipped manifest always takes precedence.
	 *
	 * @param string $id The token set id.
	 * @param array<string, mixed>|null $shippedMeta The shipped manifest entry, if any.
	 * @param array<string, mixed>|null $customMeta The custom manifest entry, if any.
	 *
	 * @return array<string, mixed>|null The resolved metadata, or null when neither manifest has an entry.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	private function resolveMeta(string $id, ?array $shippedMeta, ?array $customMeta): ?array {
		if ($shippedMeta !== null && $customMeta !== null) {
			$this->runtime->logger->warning(
				'NL Design token set id "' . $id . '" exists in both the shipped and custom manifests; using the shipped metadata.'
			);

			return $shippedMeta;
		}

		return ($shippedMeta ?? $customMeta);
	}//end resolveMeta()

	/**
	 * Resolve the WCAG contrast warnings for a token set entry: the
	 * uploader-supplied warnings for a genuine custom set, or a live audit
	 * against the shared ContrastService for a shipped set (or a custom id
	 * shadowed by a shipped manifest entry).
	 *
	 * @param array<string, mixed> $tokenSet The token set entry being built.
	 * @param array<string, mixed> $meta The merged manifest metadata for this id.
	 * @param string $appPath The app directory path.
	 * @param string $id The token set id.
	 * @param bool $isCustom Whether this is a genuine (unshadowed) custom upload.
	 *
	 * @return array<string, mixed> The token set entry with warnings applied, if any.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
	 */
	private function applyWarnings(array $tokenSet, array $meta, string $appPath, string $id, bool $isCustom): array {
		if ($isCustom === true) {
			$tokenSet['custom'] = true;
			if (isset($meta['warnings']) === true && is_array($meta['warnings']) === true) {
				$tokenSet['warnings'] = $meta['warnings'];
			}

			return $tokenSet;
		}

		$warnings = $this->cachedShippedWarnings(tokenSet: $tokenSet, meta: $meta, appPath: $appPath, id: $id);
		if (empty($warnings) === false) {
			$tokenSet['warnings'] = $warnings;
		}

		return $tokenSet;
	}//end applyWarnings()

	/**
	 * The warnings of a shipped set, from the local cache when its inputs are
	 * unchanged.
	 *
	 * The three audits parse the set's CSS and the design system's own files,
	 * which for the whole catalogue took seconds on every request that lists
	 * the sets (the admin page among them). Their result depends only on those
	 * files and on the entry itself, so the key covers the app version (the
	 * shipped files), the set's CSS content (a synced or edited set), and the
	 * entry's design system, theming and metadata (captured branding, manifest
	 * changes). Local, not distributed: the inputs are this server's files.
	 * Without a `memcache.local` Nextcloud hands out a NullCache, and the
	 * audits then run on every read, as they did before this cache.
	 *
	 * @param array<string, mixed> $tokenSet The token set entry being built.
	 * @param array<string, mixed> $meta The merged manifest metadata for this id.
	 * @param string $appPath The app directory path.
	 * @param string $id The token set id.
	 *
	 * @return array<int, mixed> The set's warnings, contrast first.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
	 */
	private function cachedShippedWarnings(array $tokenSet, array $meta, string $appPath, string $id): array {
		$css = $this->setFiles->files->read(appPath: $appPath, name: 'css/tokens/' . $id . '.css', store: $this->setFiles->store);
		$key = $id . '-' . md5(
			(string)json_encode(
				[
					$this->setFiles->appManager->getAppVersion('thematiq'),
					md5((string)$css),
					$tokenSet['design_system'],
					($tokenSet['theming'] ?? []),
					$meta,
				]
			)
		);

		$cached = $this->warningsCache->get(key: $key);
		if (is_array($cached) === true) {
			return $cached;
		}

		$warnings = $this->auditShippedSet(tokenSet: $tokenSet, meta: $meta, appPath: $appPath, id: $id);
		$this->warningsCache->set(key: $key, value: $warnings, ttl: self::WARNINGS_CACHE_TTL);

		return $warnings;
	}//end cachedShippedWarnings()

	/**
	 * Run the contrast, vocabulary and typeface audits for a shipped set.
	 *
	 * @param array<string, mixed> $tokenSet The token set entry being built.
	 * @param array<string, mixed> $meta The merged manifest metadata for this id.
	 * @param string $appPath The app directory path.
	 * @param string $id The token set id.
	 *
	 * @return array<int, mixed> The set's warnings, contrast first.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
	 * @spec openspec/specs/token-sets/spec.md#requirement-a-set-says-when-its-typeface-cannot-be-served
	 */
	private function auditShippedSet(array $tokenSet, array $meta, string $appPath, string $id): array {
		// Shipped set: surface the same non-blocking WCAG contrast warning
		// the apply dialog raises for a custom upload, so a sub-AA or
		// unevaluated shipped set is not silently applied.
		$warnings = $this->audits->audit->warningsFor(
			appPath: $appPath,
			id: $id,
			designSystem: $tokenSet['design_system'],
			theming: ($tokenSet['theming'] ?? [])
		);

		// ...and the vocabulary verdict on the same channel: a set that never
		// declares the tokens the design system reads renders as the
		// defaults.css brand (Rijkshuisstijl), not as its own, which no
		// contrast ratio can reveal. Appended after the contrast warnings so
		// the existing ones keep their position in the list.
		$warnings = array_merge(
			$warnings,
			$this->audits->vocabularyAudit->warningsFor(appPath: $appPath, id: $id, meta: $meta)
		);

		// ...and the typeface verdict, on the same channel. A set that names a
		// family nothing serves renders the next one in its stack, usually
		// Arial, and no contrast ratio or missing-token count reveals that: the
		// page just looks like another organisation. Where the family cannot be
		// redistributed, the warning carries the licence position and the action
		// from the set's own `font` block, so the administrator is told rather
		// than left to notice.
		$warnings = array_merge(
			$warnings,
			$this->audits->fontAudit->warningsFor(appPath: $appPath, id: $id, meta: $meta)
		);

		return $warnings;
	}//end auditShippedSet()

	/**
	 * Check if a token set exists on the filesystem.
	 *
	 * @param string $tokenSetId The token set identifier.
	 *
	 * @return bool True if the CSS file exists.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	public function isValidTokenSet(string $tokenSetId): bool {
		// Prevent path traversal.
		if (str_contains($tokenSetId, '/') === true || str_contains($tokenSetId, '..') === true) {
			return false;
		}

		return $this->setFiles->files->exists(appPath: $this->getAppPath(), name: 'css/tokens/' . $tokenSetId . '.css', store: $this->setFiles->store);
	}//end isValidTokenSet()

	/**
	 * Read the token-sets.json manifest and index by id.
	 *
	 * @param string $manifestPath Path to token-sets.json.
	 *
	 * @return array<string, array<string, mixed>> Metadata indexed by id.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	private function readManifest(string $manifestPath): array {
		if (file_exists($manifestPath) === false) {
			return [];
		}

		$content = file_get_contents($manifestPath);
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
	}//end readManifest()

	/**
	 * Read the custom-set appconfig manifest, indexed by id.
	 *
	 * The manifest is a JSON object keyed by the custom set id, so it is
	 * already in the indexed shape readManifest() produces for the shipped
	 * list. Malformed JSON degrades to an empty map.
	 *
	 * @return array<string, array<string, mixed>> Custom metadata indexed by id.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function readCustomManifest(): array {
		$raw = $this->runtime->config->getAppValue(Application::APP_ID, 'custom_token_sets', '{}');
		$decoded = json_decode($raw, true);

		if (is_array($decoded) === false) {
			return [];
		}

		return $decoded;
	}//end readCustomManifest()

	/**
	 * Format a kebab-case id into a display name.
	 *
	 * @param string $id The kebab-case identifier.
	 *
	 * @return string The formatted display name.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	private function formatName(string $id): string {
		return ucwords(str_replace('-', ' ', $id));
	}//end formatName()
}//end class
