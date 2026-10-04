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
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileStore;
use OCA\Thematiq\Service\RuntimeFile\SetFileReader;
use OCP\App\IAppManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

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
 *     upstreamRef?: string
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
 *     upstreamRef?: string
 * }
 *
 * @spec openspec/specs/token-sets/spec.md
 * @spec openspec/specs/custom-token-sets/spec.md
 */
class TokenSetService {

	/**
	 * Marker a shipped set carries when the vocabulary audit reports it
	 * incomplete, on the `warnings` channel `applyWarnings()` already fills.
	 *
	 * WHAT REPLACED THE HAND-MAINTAINED LIST. Until this change, selectability
	 * was `SELECTABLE_SHIPPED_SETS = ['nextcloud', 'cunningham']` — two of 59
	 * shipped sets — because in 2026-09 all but a handful of set files declared
	 * none of the `--nldesign-*` vocabulary the theme reads, so picking one
	 * rendered Rijkshuisstijl with the wrong header. That constant's own
	 * docblock named its exit condition: a set returns once it passes
	 * `TokenSetVocabularyAuditService` and leaves
	 * `tests/Unit/fixtures/token-set-vocabulary-allowlist.json`, which is
	 * shrink-only and must reach empty, and then "every shipped set is
	 * selectable again and this constant is deleted rather than widened".
	 *
	 * That condition is met: the fixture reads `"sets": []` and the audit
	 * reports 57 of 57 auditable sets complete. So the constant is deleted, and
	 * the audit it named is the gate — which is what the docblock asked for and
	 * is strictly better than a wider list, because a list goes stale and a
	 * measurement cannot. A set that stops passing the audit stops being
	 * offered, with no edit here.
	 *
	 * The verdict is READ OFF THE ENTRY rather than recomputed:
	 * `getAvailableTokenSets()` already runs the vocabulary audit for every set
	 * through `applyWarnings()`, so asking the service again would both double
	 * the work on every admin page render and create a second answer to one
	 * question.
	 */
	public const INCOMPLETE_WARNING_KIND = 'incomplete';

	/**
	 * The app manager for resolving paths.
	 *
	 * @var IAppManager
	 */
	private IAppManager $appManager;

	/**
	 * The config service for reading the custom-set appconfig manifest.
	 *
	 * @var IConfig
	 */
	private IConfig $config;

	/**
	 * The logger for the defensive id-collision warning.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * The shipped-set contrast audit service (runtime warning surface, and
	 * the `wcagLevel` audit path the public catalogue projection reuses).
	 *
	 * @var ShippedTokenSetAuditService
	 */
	private ShippedTokenSetAuditService $audit;

	/**
	 * The vocabulary-completeness audit service — the second, independent
	 * warning source: whether a shipped set defines the `--nldesign-*`
	 * vocabulary the design system reads at all (as opposed to whether its
	 * colours are legible, which is `$audit`'s job).
	 *
	 * @var TokenSetVocabularyAuditService
	 */
	private TokenSetVocabularyAuditService $vocabularyAudit;

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
	 * Constructor.
	 *
	 * @param IAppManager $appManager The app manager for resolving paths.
	 * @param IConfig $config The config service.
	 * @param LoggerInterface $logger The logger.
	 * @param ShippedTokenSetAuditService $audit The shipped-set contrast audit service.
	 * @param ICacheFactory $cacheFactory Creates the distributed WCAG-level cache.
	 * @param TokenSetVocabularyAuditService $vocabularyAudit The vocabulary-completeness audit service.
	 * @param RuntimeFileStore|null $store Where uploaded sets are kept (app data).
	 * @param SetFileReader $files Lists and checks a set's file in the release or the store.
	 * @param TokenSetFontAuditService $fontAudit The typeface audit service.
	 */
	public function __construct(
		IAppManager $appManager,
		IConfig $config,
		LoggerInterface $logger,
		ShippedTokenSetAuditService $audit,
		ICacheFactory $cacheFactory,
		TokenSetVocabularyAuditService $vocabularyAudit,
		private readonly ?RuntimeFileStore $store = null,
		private readonly SetFileReader $files = new SetFileReader(),
		private readonly TokenSetFontAuditService $fontAudit = new TokenSetFontAuditService(),
	) {
		$this->appManager = $appManager;
		$this->config = $config;
		$this->logger = $logger;
		$this->audit = $audit;
		$this->wcagCache = $cacheFactory->createDistributed(prefix: 'thematiq_wcag_level');
		$this->vocabularyAudit = $vocabularyAudit;
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
		$manifestPath = $appPath . '/token-sets.json';

		// Read metadata from token-sets.json (shipped sets).
		$metadata = $this->readManifest(manifestPath: $manifestPath);

		// Read metadata for admin-uploaded custom sets from appconfig.
		$customMetadata = $this->readCustomManifest();

		// Nextcloud branding a set captured when it was saved (see
		// BrandingCaptureService). It replaces the set's own theming block:
		// it is what the admin had on when they saved this theme, which is
		// what applying the theme is meant to bring back.
		$captured = $this->readCapturedTheming();

		// Scan the release for shipped sets and the store for uploaded ones.
		$tokenSets = [];
		$files = $this->files->fileNames(appPath: $appPath, directory: 'css/tokens', store: $this->store);
		if ($files !== []) {
			foreach ($files as $file) {
				if (str_ends_with($file, '.css') === true) {
					$id = basename($file, '.css');
					$isCustom = str_starts_with($id, 'custom-');

					// Shipped manifest takes precedence on an (impossible) id
					// collision; log it so the operator can investigate.
					$shippedMeta = $metadata[$id] ?? null;
					$meta = $this->resolveMeta(id: $id, shippedMeta: $shippedMeta, customMeta: ($customMetadata[$id] ?? null));

					$tokenSet = [
						'id' => $id,
						'name' => $meta['name'] ?? $this->formatName(id: $id),
						'description' => $meta['description'] ?? 'Design tokens for ' . $this->formatName(id: $id),
						'design_system' => $meta['design_system'] ?? 'nldesign',
					];
					if (isset($meta['theming']) === true && is_array($meta['theming']) === true) {
						$tokenSet['theming'] = $meta['theming'];
					}

					if (isset($captured[$id]) === true && is_array($captured[$id]) === true) {
						$tokenSet['theming'] = $captured[$id];
					}

					$tokenSet = $this->applyProvenance(tokenSet: $tokenSet, meta: ($meta ?? []));
					$tokenSet = $this->applyWarnings(
						tokenSet: $tokenSet,
						meta: ($meta ?? []),
						appPath: $appPath,
						id: $id,
						isCustom: ($isCustom === true && $shippedMeta === null)
					);

					$tokenSets[] = $tokenSet;
				}//end if
			}//end foreach
		}//end if

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
			(string)$this->config->getAppValue(Application::APP_ID, BrandingCaptureService::CAPTURED_KEY, '{}'),
			true
		);
		if (is_array($captured) === false) {
			return [];
		}

		return $captured;
	}//end readCapturedTheming()

	/**
	 * Get the token sets an admin may select: every NAMED shipped set the
	 * vocabulary audit passes, plus every admin-imported `custom-*` set.
	 *
	 * Two conditions, both measured rather than listed:
	 *
	 *  A. The set has an entry in `token-sets.json`. Discovery is
	 *     filesystem-based, so `css/tokens/conduction.css` is found like any
	 *     other file — but it is the shared role layer
	 *     `scripts/generate-brand-set.mjs` copies into a brand set, not a
	 *     theme, and it has no name, description or theming of its own. A set
	 *     with no manifest entry would be offered as "Conduction" with a
	 *     generated description, which is the one set that genuinely should not
	 *     be in the picker.
	 *  B. The vocabulary audit does not report it incomplete. A set that
	 *     declares none of the `--nldesign-*` vocabulary the theme reads
	 *     renders as `defaults.css`'s brand rather than its own, and an admin
	 *     cannot tell that from a dropdown.
	 *
	 * The BRIDGE figure is deliberately NOT a condition. A set that declares
	 * none of the 87 `--utrecht-*` names the bridge reads adopts the NL Design
	 * System's component geometry and type scale, and still brands the
	 * component COLOURS from its own semantic layer: measured on
	 * `css/systems/nldesign/utrecht-bridge.css`, 42 of its 84 declarations fall
	 * back to a `--nldesign-*` token the set declares, 38 to a non-colour
	 * literal (`1rem`, `1px`, `transparent`, `700`, `underline`) and 3 to a
	 * colour literal, those three being `#e5e5e5`/`#696969` (disabled button)
	 * and `#ffffff` (textbox fill). Resolved over the real cascade, `amsterdam`
	 * and `rijkshuisstijl` are both bridge-zero and differ on 32 of the 84
	 * component tokens, with Amsterdam's buttons at `#004699` and
	 * Rijkshuisstijl's at `#154273`. Withholding such a set would withhold a
	 * working theme.
	 *
	 * Three ids are never filtered out, whatever the audit says, because
	 * narrowing a picker must not be able to change what an instance is doing:
	 *
	 *  1. The set the instance is CURRENTLY running. Dropping it would render
	 *     the panel with no option selected, and the first save would silently
	 *     re-theme the instance to whatever happened to be first.
	 *  2. Any set a per-group mapping points at, for the same reason — the
	 *     group picker is fed from this list too, and a group's theme would
	 *     disappear from the UI while still applying.
	 *  3. Every `custom-*` set, unconditionally. The converter tells the admin
	 *     their upload was "added and selectable"; a filter that then hid it
	 *     would make the converter a liar.
	 *
	 * Read straight from `IConfig` rather than through `GroupThemingService`,
	 * which depends on this service — the group key is a plain JSON array of
	 * `{group, tokenSet}` and re-reading it here avoids a circular dependency.
	 *
	 * @return array<int, TokenSetEntry> The selectable token sets, same shape and order as `getAvailableTokenSets()`.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function getSelectableTokenSets(): array {
		$all = $this->getAvailableTokenSets();
		$named = $this->readManifest(manifestPath: $this->getAppPath() . '/token-sets.json');

		$keep = [];

		// (1) Whatever the instance is running right now.
		$active = $this->config->getAppValue(Application::APP_ID, 'token_set', 'nextcloud');
		if ($active !== '') {
			$keep[$active] = true;
		}

		// (2) Every set a group mapping points at.
		$rawMapping = $this->config->getAppValue(Application::APP_ID, 'group_token_sets', '[]');
		$decodedMapping = json_decode($rawMapping, true);
		if (is_array($decodedMapping) === true) {
			foreach ($decodedMapping as $entry) {
				if (is_array($entry) === true && is_string($entry['tokenSet'] ?? null) === true) {
					$keep[$entry['tokenSet']] = true;
				}
			}
		}

		$selectable = [];
		foreach ($all as $tokenSet) {
			$id = $tokenSet['id'];

			// (3) An imported set is always selectable, and so is anything the
			// two survival rules above kept.
			if (isset($keep[$id]) === true || str_starts_with($id, 'custom-') === true) {
				$selectable[] = $tokenSet;
				continue;
			}

			if (isset($named[$id]) === false) {
				continue;
			}

			if ($this->isVocabularyIncomplete(tokenSet: $tokenSet) === true) {
				continue;
			}

			$selectable[] = $tokenSet;
		}//end foreach

		return $selectable;
	}//end getSelectableTokenSets()

	/**
	 * Whether a catalogue entry carries the vocabulary audit's "incomplete"
	 * finding.
	 *
	 * The finding is already on the entry: `applyWarnings()` put it there from
	 * `TokenSetVocabularyAuditService::warningsFor()`, on the same channel the
	 * admin UI renders its "Incomplete set" banner from. Reading it here rather
	 * than re-auditing keeps one answer to one question, and keeps the dropdown
	 * and the banner from ever disagreeing.
	 *
	 * @param array<string, mixed> $tokenSet The catalogue entry.
	 *
	 * @return bool True when the set is vocabulary-incomplete.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	private function isVocabularyIncomplete(array $tokenSet): bool {
		$warnings = ($tokenSet['warnings'] ?? []);
		if (is_array($warnings) === false) {
			return false;
		}

		foreach ($warnings as $warning) {
			if (is_array($warning) === true
				&& ($warning['kind'] ?? null) === self::INCOMPLETE_WARNING_KIND
			) {
				return true;
			}
		}

		return false;
	}//end isVocabularyIncomplete()

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
				'wcagLevel' => $this->audit->computeCachedWcagLevel(
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

		return $tokenSet;
	}//end applyProvenance()

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
			$this->logger->warning(
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

		// Shipped set: surface the same non-blocking WCAG contrast warning
		// the apply dialog raises for a custom upload, so a sub-AA or
		// unevaluated shipped set is not silently applied.
		$warnings = $this->audit->warningsFor(
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
			$this->vocabularyAudit->warningsFor(appPath: $appPath, id: $id, meta: $meta)
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
			$this->fontAudit->warningsFor(appPath: $appPath, id: $id, meta: $meta)
		);

		if (empty($warnings) === false) {
			$tokenSet['warnings'] = $warnings;
		}

		return $tokenSet;
	}//end applyWarnings()

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

		return $this->files->exists(appPath: $this->getAppPath(), name: 'css/tokens/' . $tokenSetId . '.css', store: $this->store);
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
		$raw = $this->config->getAppValue(Application::APP_ID, 'custom_token_sets', '{}');
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
