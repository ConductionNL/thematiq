<?php

/**
 * NL Design — Theming Capability.
 *
 * Contributes an `nldesign` entry to the Nextcloud capabilities document
 * (`/ocs/v2.php/cloud/capabilities`) describing the instance's active huisstijl:
 * the active token set, resolved design system, resolved icon pack(s), audited
 * WCAG contrast level, available logo variants, and the slogan/menu-label
 * presentation toggles. Implements `IPublicCapability` (not merely
 * `ICapability`) so unauthenticated clients — login pages, portals,
 * mobile/desktop clients pre-session — can read it, mirroring core
 * `apps/theming`'s public capability. Everything exposed here is already
 * visible to anyone loading the themed login page.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Capabilities
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/theming-capability/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCP\App\IAppManager;
use OCP\Capabilities\IPublicCapability;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IURLGenerator;
use stdClass;

/**
 * Advertises the active huisstijl as a public Nextcloud capability.
 *
 * @spec openspec/specs/theming-capability/spec.md
 */
class Capabilities implements IPublicCapability {

	/**
	 * TTL (seconds) for the cached WCAG audit level — at most one hour so the
	 * capabilities endpoint never re-parses token CSS per request.
	 */
	private const WCAG_CACHE_TTL = 3600;

	/**
	 * Distributed cache for the resolved WCAG level, keyed by active token set id.
	 *
	 * @var ICache
	 */
	private ICache $cache;

	/**
	 * Constructor.
	 *
	 * @param IConfig $config Reads the app's appconfig toggles.
	 * @param IAppManager $appManager Resolves the app version and app path.
	 * @param IURLGenerator $urlGenerator Resolves logo asset web paths.
	 * @param DesignSystemService $designSystemService Resolves the active set's design system.
	 * @param ShippedTokenSetAuditService $auditService Computes the WCAG contrast verdict.
	 * @param ICacheFactory $cacheFactory Creates the distributed WCAG-level cache.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly IAppManager $appManager,
		private readonly IURLGenerator $urlGenerator,
		private readonly DesignSystemService $designSystemService,
		private readonly ShippedTokenSetAuditService $auditService,
		ICacheFactory $cacheFactory,
	) {
		$this->cache = $cacheFactory->createDistributed(prefix: 'thematiq_wcag_level');
	}//end __construct()

	/**
	 * Return the `nldesign` capability payload.
	 *
	 * MUST NEVER throw: the Nextcloud capabilities endpoint aggregates every
	 * app's capability, so one throwing provider breaks the document for every
	 * client. Any internal failure degrades to a minimal payload instead of
	 * propagating.
	 *
	 * @return array<string, array<string, mixed>> The capabilities document fragment.
	 *
	 * @spec openspec/specs/theming-capability/spec.md
	 */
	public function getCapabilities(): array {
		try {
			return ['nldesign' => $this->buildPayload()];
		} catch (\Throwable $exception) {
			return ['nldesign' => $this->minimalPayload()];
		}
	}//end getCapabilities()

	/**
	 * Build the full eight-key payload from live appconfig + manifest state.
	 *
	 * @return array<string, mixed> The payload (allowlisted to exactly eight keys).
	 *
	 * @spec openspec/specs/theming-capability/spec.md
	 * @spec openspec/specs/icon-packs/spec.md
	 */
	private function buildPayload(): array {
		$tokenSetId = $this->config->getAppValue(appName: Application::APP_ID, key: 'token_set', default: 'nextcloud');
		$toggles = $this->toggles();

		$set = new CapabilityTokenSet(id: $tokenSetId, meta: $this->designSystemService->getTokenSetMeta(tokenSetId: $tokenSetId));

		return [
			'version' => $this->appManager->getAppVersion(appId: Application::APP_ID),
			'tokenSet' => $this->buildTokenSet(set: $set),
			'designSystem' => ($set->meta['design_system'] ?? 'nldesign'),
			'iconPacks' => $this->designSystemService->resolveActiveIconPacks(tokenSetId: $tokenSetId),
			'wcagLevel' => $this->computeWcagLevel(set: $set),
			'logos' => $this->buildLogos(set: $set),
		] + $toggles;
	}//end buildPayload()

	/**
	 * The slogan and menu-label presentation toggles.
	 *
	 * @return array{hideSlogan: bool, showMenuLabels: bool} The toggles.
	 */
	private function toggles(): array {
		return [
			'hideSlogan' => $this->config->getAppValue(appName: Application::APP_ID, key: 'hide_slogan', default: '0') === '1',
			'showMenuLabels' => $this->config->getAppValue(appName: Application::APP_ID, key: 'show_menu_labels', default: '0') === '1',
		];
	}//end toggles()

	/**
	 * Resolve the active token set's `{ id, name, version }` triple.
	 *
	 * Read from the active set's own manifest entry, not from
	 * `TokenSetService::getAvailableTokenSets()`: that builds the whole
	 * catalogue and audits every set in it, and Nextcloud renders the
	 * capabilities into every page, so every page paid for auditing all sets
	 * to read one name. `name` falls back to the id and `version` is null when
	 * the active set has no manifest entry (custom/unknown set).
	 *
	 * @param CapabilityTokenSet $set The active set and its manifest entry (empty for an unknown set).
	 *
	 * @return array{id: string, name: string, version: string|null} The token set descriptor.
	 *
	 * @spec openspec/specs/theming-capability/spec.md#requirement-theming-capability-payload
	 */
	private function buildTokenSet(CapabilityTokenSet $set): array {
		// The version reported here is the upstream provenance recorded by the
		// token-sync workflow (`upstreamVersion`), present only for sets
		// generated from an upstream release — null for hand-maintained and
		// custom sets.
		$name = $set->id;
		if (is_string($set->meta['name'] ?? null) === true && $set->meta['name'] !== '') {
			$name = $set->meta['name'];
		}

		$version = null;
		if (isset($set->meta['upstreamVersion']) === true) {
			$version = (string)$set->meta['upstreamVersion'];
		}

		return [
			'id' => $set->id,
			'name' => $name,
			'version' => $version,
		];
	}//end buildTokenSet()

	/**
	 * Resolve the available logo variants for the active set.
	 *
	 * `default` comes from `theming.logo` in the set's manifest entry. `emblem`
	 * is added when the set ships `img/logos/<id>-emblem.svg` beside it: the
	 * mark without the wordmark, which an app draws small (the shield at the
	 * top of the dossiq navigation on the Zuiddrecht workplace boards). Empty
	 * when the set declares no logo — always an empty object, never `[]`, so
	 * it serializes as JSON `{}`.
	 *
	 * @param CapabilityTokenSet $set The active set and its manifest entry (empty for custom/unknown sets).
	 *
	 * @return array<string, string>|stdClass The logo variant map.
	 *
	 * @spec openspec/specs/theming-capability/spec.md
	 */
	private function buildLogos(CapabilityTokenSet $set): array|stdClass {
		$logoPath = ($set->meta['theming']['logo'] ?? null);
		if (is_string($logoPath) === false || $logoPath === '') {
			return new stdClass();
		}

		$logos = ['default' => $this->urlGenerator->imagePath(appName: Application::APP_ID, file: $this->imageRelative(path: $logoPath))];

		$emblem = $this->emblemUrl(tokenSetId: $set->id);
		if ($emblem !== null) {
			$logos['emblem'] = $emblem;
		}

		return $logos;
	}//end buildLogos()

	/**
	 * A logo path relative to `img/`: theming.logo is stored app-relative
	 * (e.g. "img/logos/x.svg"), and IURLGenerator::imagePath() prepends "img/" itself.
	 *
	 * @param string $path The app-relative path.
	 *
	 * @return string The path without its `img/` prefix.
	 */
	private function imageRelative(string $path): string {
		if (str_starts_with(haystack: $path, needle: 'img/') === true) {
			return substr($path, 4);
		}

		return $path;
	}//end imageRelative()

	/**
	 * The web path of the set's emblem, when it ships `img/logos/<id>-emblem.svg`.
	 *
	 * @param string $tokenSetId The active token set id.
	 *
	 * @return string|null The web path, or null.
	 */
	private function emblemUrl(string $tokenSetId): string|null {
		$emblem = 'logos/' . $tokenSetId . '-emblem.svg';
		$appPath = $this->appManager->getAppPath(appId: Application::APP_ID);
		if (is_file($appPath . '/img/' . $emblem) === false) {
			return null;
		}

		return $this->urlGenerator->imagePath(appName: Application::APP_ID, file: $emblem);
	}//end emblemUrl()

	/**
	 * Compute the audited WCAG contrast level for the active token set, cached.
	 *
	 * Null for a stock (`none` design system) or custom/unknown set — the audit
	 * has nothing to evaluate or cannot cover it, and the capability MUST NOT
	 * fabricate a conformance claim. Otherwise resolves via
	 * `ShippedTokenSetAuditService::auditSet()`, cached per active set id.
	 *
	 * @param CapabilityTokenSet $set The active set and its manifest entry (empty for custom/unknown sets).
	 *
	 * @return string|null One of `AAA`, `AA`, `fail`, or null.
	 *
	 * @spec openspec/specs/theming-capability/spec.md
	 */
	private function computeWcagLevel(CapabilityTokenSet $set): ?string {
		$designSystemId = ($set->meta['design_system'] ?? null);
		if (empty($set->meta) === true || $designSystemId === 'none') {
			return null;
		}

		$cacheKey = 'level-' . $set->id;
		$cached = $this->cache->get(key: $cacheKey);
		if (is_string($cached) === true) {
			return $cached;
		}

		$level = $this->auditWcagLevel(set: $set, designSystemId: (string)$designSystemId);
		$this->cache->set(key: $cacheKey, value: $level, ttl: self::WCAG_CACHE_TTL);

		return $level;
	}//end computeWcagLevel()

	/**
	 * Run the contrast audit(s) for a set known to have a real design system.
	 *
	 * @param CapabilityTokenSet $set The active set and its manifest entry.
	 * @param string $designSystemId The set's resolved design system id.
	 *
	 * @return string One of `AAA`, `AA`, or `fail`.
	 *
	 * @spec openspec/specs/theming-capability/spec.md
	 */
	private function auditWcagLevel(CapabilityTokenSet $set, string $designSystemId): string {
		$levels = ['AA'];
		if ($designSystemId === 'high-contrast' || ($set->meta['contrast_level'] ?? null) === 'AAA') {
			$levels[] = 'AAA';
		}

		$passes = $this->auditPasses(set: $set, levels: $levels);
		if (($passes['AAA'] ?? false) === true) {
			return 'AAA';
		}

		if ($passes['AA'] === true) {
			return 'AA';
		}

		return 'fail';
	}//end auditWcagLevel()

	/**
	 * Run the contrast audit at each level, in order.
	 *
	 * @param CapabilityTokenSet $set The active set and its manifest entry.
	 * @param array<int, string> $levels The levels, `AA` first.
	 *
	 * @return array<string, bool> Whether the set passes, per level.
	 */
	private function auditPasses(CapabilityTokenSet $set, array $levels): array {
		$appPath = $this->appManager->getAppPath(appId: Application::APP_ID);
		$theming = ($set->meta['theming'] ?? []);

		$passes = [];
		foreach ($levels as $level) {
			$passes[$level] = $this->auditService->auditSet(appPath: $appPath, id: $set->id, theming: $theming, level: $level)['verdict'] === 'pass';
		}

		return $passes;
	}//end auditPasses()

	/**
	 * The degraded minimal payload returned when `buildPayload()` throws.
	 *
	 * `version` and `tokenSet.id` are re-derived from raw appconfig (best
	 * effort — a nested failure here still falls back to a safe literal rather
	 * than propagating); `name` falls back to the id; every other field is
	 * `null`, `{}`, `[]`, or `false`. Never includes exception details.
	 *
	 * @return array<string, mixed> The minimal payload.
	 *
	 * @spec openspec/specs/theming-capability/spec.md
	 * @spec openspec/specs/icon-packs/spec.md
	 */
	private function minimalPayload(): array {
		$version = $this->safeVersion();
		$tokenSetId = $this->safeTokenSetId();

		return [
			'version' => $version,
			'tokenSet' => $this->minimalTokenSet(tokenSetId: $tokenSetId),
			'designSystem' => null,
			'iconPacks' => [],
			'wcagLevel' => null,
			'logos' => new stdClass(),
			'hideSlogan' => false,
			'showMenuLabels' => false,
		];
	}//end minimalPayload()

	/**
	 * The degraded token set descriptor: `name` falls back to the id, no version.
	 *
	 * @param string $tokenSetId The active token set id, or the safe literal.
	 *
	 * @return array{id: string, name: string, version: null} The descriptor.
	 */
	private function minimalTokenSet(string $tokenSetId): array {
		return [
			'id' => $tokenSetId,
			'name' => $tokenSetId,
			'version' => null,
		];
	}//end minimalTokenSet()

	/**
	 * The app version, `unknown` when it cannot be read: the degrade path must not itself throw.
	 *
	 * @return string The version.
	 */
	private function safeVersion(): string {
		try {
			return $this->appManager->getAppVersion(appId: Application::APP_ID);
		} catch (\Throwable $exception) {
			return 'unknown';
		}
	}//end safeVersion()

	/**
	 * The active token set id, `nextcloud` when it cannot be read.
	 *
	 * @return string The id.
	 */
	private function safeTokenSetId(): string {
		try {
			return $this->config->getAppValue(appName: Application::APP_ID, key: 'token_set', default: 'nextcloud');
		} catch (\Throwable $exception) {
			return 'nextcloud';
		}
	}//end safeTokenSetId()
}//end class
