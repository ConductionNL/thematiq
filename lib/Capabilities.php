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
use OCA\Thematiq\Service\TokenSetService;
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
	 * @param TokenSetService $tokenSetService Resolves the active set's display name/version.
	 * @param ShippedTokenSetAuditService $auditService Computes the WCAG contrast verdict.
	 * @param ICacheFactory $cacheFactory Creates the distributed WCAG-level cache.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly IAppManager $appManager,
		private readonly IURLGenerator $urlGenerator,
		private readonly DesignSystemService $designSystemService,
		private readonly TokenSetService $tokenSetService,
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
		$hideSlogan = $this->config->getAppValue(appName: Application::APP_ID, key: 'hide_slogan', default: '0') === '1';
		$showMenuLabels = $this->config->getAppValue(appName: Application::APP_ID, key: 'show_menu_labels', default: '0') === '1';

		$tokenSetMeta = $this->designSystemService->getTokenSetMeta(tokenSetId: $tokenSetId);
		$designSystemId = ($tokenSetMeta['design_system'] ?? 'nldesign');

		return [
			'version' => $this->appManager->getAppVersion(appId: Application::APP_ID),
			'tokenSet' => $this->buildTokenSet(tokenSetId: $tokenSetId),
			'designSystem' => $designSystemId,
			'iconPacks' => $this->designSystemService->resolveActiveIconPacks(tokenSetId: $tokenSetId),
			'wcagLevel' => $this->computeWcagLevel(tokenSetId: $tokenSetId, tokenSetMeta: $tokenSetMeta),
			'logos' => $this->buildLogos(tokenSetId: $tokenSetId, tokenSetMeta: $tokenSetMeta),
			'hideSlogan' => $hideSlogan,
			'showMenuLabels' => $showMenuLabels,
		];
	}//end buildPayload()

	/**
	 * Resolve the active token set's `{ id, name, version }` triple.
	 *
	 * `name` falls back to the id and `version` is null when the active set has
	 * no entry in `TokenSetService::getAvailableTokenSets()` (custom/unknown set).
	 *
	 * @param string $tokenSetId The active token set id (appconfig `token_set`).
	 *
	 * @return array{id: string, name: string, version: string|null} The token set descriptor.
	 *
	 * @spec openspec/specs/theming-capability/spec.md
	 */
	private function buildTokenSet(string $tokenSetId): array {
		$available = $this->tokenSetService->getAvailableTokenSets();
		$byId = array_column($available, null, 'id');

		// A discovered entry always carries `name`. The version reported here is
		// the upstream provenance recorded by the token-sync workflow
		// (`upstreamVersion`), present only for sets generated from an upstream
		// release — null for hand-maintained and custom sets.
		$entry = ($byId[$tokenSetId] ?? null);
		$name = $tokenSetId;
		$version = null;
		if (is_array($entry) === true) {
			$name = (string)$entry['name'];
			if (isset($entry['upstreamVersion']) === true) {
				$version = (string)$entry['upstreamVersion'];
			}
		}

		return [
			'id' => $tokenSetId,
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
	 * @param string               $tokenSetId   The active token set id.
	 * @param array<string, mixed> $tokenSetMeta The active set's manifest entry (empty for custom/unknown sets).
	 *
	 * @return array<string, string>|stdClass The logo variant map.
	 *
	 * @spec openspec/specs/theming-capability/spec.md
	 */
	private function buildLogos(string $tokenSetId, array $tokenSetMeta): array|stdClass {
		$logoPath = ($tokenSetMeta['theming']['logo'] ?? null);
		if (is_string($logoPath) === false || $logoPath === '') {
			return new stdClass();
		}

		// Theming.logo is stored app-relative (e.g. "img/logos/x.svg");
		// IURLGenerator::imagePath() prepends "img/" itself.
		$relative = $logoPath;
		if (str_starts_with(haystack: $relative, needle: 'img/') === true) {
			$relative = substr($relative, 4);
		}

		$logos = ['default' => $this->urlGenerator->imagePath(appName: Application::APP_ID, file: $relative)];

		$emblem = 'logos/' . $tokenSetId . '-emblem.svg';
		$appPath = $this->appManager->getAppPath(appId: Application::APP_ID);
		if (is_file($appPath . '/img/' . $emblem) === true) {
			$logos['emblem'] = $this->urlGenerator->imagePath(appName: Application::APP_ID, file: $emblem);
		}

		return $logos;
	}//end buildLogos()

	/**
	 * Compute the audited WCAG contrast level for the active token set, cached.
	 *
	 * Null for a stock (`none` design system) or custom/unknown set — the audit
	 * has nothing to evaluate or cannot cover it, and the capability MUST NOT
	 * fabricate a conformance claim. Otherwise resolves via
	 * `ShippedTokenSetAuditService::auditSet()`, cached per active set id.
	 *
	 * @param string $tokenSetId The active token set id.
	 * @param array<string, mixed> $tokenSetMeta The active set's manifest entry (empty for custom/unknown sets).
	 *
	 * @return string|null One of `AAA`, `AA`, `fail`, or null.
	 *
	 * @spec openspec/specs/theming-capability/spec.md
	 */
	private function computeWcagLevel(string $tokenSetId, array $tokenSetMeta): ?string {
		$designSystemId = ($tokenSetMeta['design_system'] ?? null);
		if (empty($tokenSetMeta) === true || $designSystemId === 'none') {
			return null;
		}

		$cacheKey = 'level-' . $tokenSetId;
		$cached = $this->cache->get(key: $cacheKey);
		if (is_string($cached) === true) {
			return $cached;
		}

		$level = $this->auditWcagLevel(tokenSetId: $tokenSetId, tokenSetMeta: $tokenSetMeta, designSystemId: (string)$designSystemId);
		$this->cache->set(key: $cacheKey, value: $level, ttl: self::WCAG_CACHE_TTL);

		return $level;
	}//end computeWcagLevel()

	/**
	 * Run the contrast audit(s) for a set known to have a real design system.
	 *
	 * @param string $tokenSetId The active token set id.
	 * @param array<string, mixed> $tokenSetMeta The active set's manifest entry.
	 * @param string $designSystemId The set's resolved design system id.
	 *
	 * @return string One of `AAA`, `AA`, or `fail`.
	 *
	 * @spec openspec/specs/theming-capability/spec.md
	 */
	private function auditWcagLevel(string $tokenSetId, array $tokenSetMeta, string $designSystemId): string {
		$appPath = $this->appManager->getAppPath(appId: Application::APP_ID);
		$theming = ($tokenSetMeta['theming'] ?? []);

		$declaresAaa = ($designSystemId === 'high-contrast' || ($tokenSetMeta['contrast_level'] ?? null) === 'AAA');

		$passesAa = $this->auditService->auditSet(
			appPath: $appPath,
			id: $tokenSetId,
			theming: $theming,
			level: 'AA'
		)['verdict'] === 'pass';

		if ($declaresAaa === true) {
			$passesAaa = $this->auditService->auditSet(
				appPath: $appPath,
				id: $tokenSetId,
				theming: $theming,
				level: 'AAA'
			)['verdict'] === 'pass';

			if ($passesAaa === true) {
				return 'AAA';
			}
		}

		if ($passesAa === true) {
			return 'AA';
		}

		return 'fail';
	}//end auditWcagLevel()

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
		$version = 'unknown';
		try {
			$version = $this->appManager->getAppVersion(appId: Application::APP_ID);
		} catch (\Throwable $exception) {
			// Fall through to the safe literal — the degrade path must not itself throw.
		}

		$tokenSetId = 'nextcloud';
		try {
			$tokenSetId = $this->config->getAppValue(appName: Application::APP_ID, key: 'token_set', default: 'nextcloud');
		} catch (\Throwable $exception) {
			// Fall through to the safe literal.
		}

		return [
			'version' => $version,
			'tokenSet' => [
				'id' => $tokenSetId,
				'name' => $tokenSetId,
				'version' => null,
			],
			'designSystem' => null,
			'iconPacks' => [],
			'wcagLevel' => null,
			'logos' => new stdClass(),
			'hideSlogan' => false,
			'showMenuLabels' => false,
		];
	}//end minimalPayload()
}//end class
