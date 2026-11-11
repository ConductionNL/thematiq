<?php

/**
 * NL Design Upstream Freshness Service.
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
 * @spec openspec/specs/upstream-freshness/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Owns the state, comparison logic, and notice/dismissal semantics for the
 * upstream token freshness check (the app's first background job and first
 * outbound network egress).
 *
 * Default-disabled, opt-in only. When enabled, a run performs at most two
 * outbound HTTP requests (a conditional freshness GET, plus an optional
 * attribution compare GET), never auto-applies anything, and is
 * failure-inert: every error path degrades silently, leaving prior notice
 * state untouched and never throwing out of {@see runCheck()}.
 *
 * @spec openspec/specs/upstream-freshness/spec.md
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) - The branching IS the safety contract:
 * opt-in gate, ETag conditional-GET, 304 steady state, request cap, per-set attribution with
 * graceful fallback to a generic notice, per-(set, version) dismissal, and a silent-degradation
 * path for every failure mode (offline, timeout, malformed body). Each branch is individually
 * covered by a unit test; collapsing them would trade auditable failure handling for a lower score.
 */
class UpstreamFreshnessService {

	/**
	 * App config key: whether the check is enabled ('yes'/'no'), default 'no'.
	 *
	 * @var string
	 */
	private const CONFIG_ENABLED = 'upstream_freshness_enabled';

	/**
	 * App config key: the pinned manifest URL, overridable for mirrors/proxies.
	 *
	 * @var string
	 */
	private const CONFIG_MANIFEST_URL = 'upstream_manifest_url';

	/**
	 * App config key: the last stored ETag for the conditional GET.
	 *
	 * @var string
	 */
	private const CONFIG_ETAG = 'upstream_etag';

	/**
	 * App config key: the last observed upstream head SHA.
	 *
	 * @var string
	 */
	private const CONFIG_HEAD_SHA = 'upstream_head_sha';

	/**
	 * App config key: ISO-8601 timestamp of the last completed check.
	 *
	 * @var string
	 */
	private const CONFIG_CHECKED_AT = 'upstream_checked_at';

	/**
	 * App config key: JSON map of setId => notice.
	 *
	 * @var string
	 */
	private const CONFIG_UPDATES = 'upstream_updates';

	/**
	 * App config key: JSON map of setId => dismissed version/SHA marker.
	 *
	 * @var string
	 */
	private const CONFIG_DISMISSED = 'upstream_freshness_dismissed';

	/**
	 * The manifest key used for the generic (unattributed) notice — GitHub's
	 * compare API attribution failed, but upstream is known to have changed.
	 *
	 * @var string
	 */
	private const GENERIC_NOTICE_KEY = '__generic__';

	/**
	 * Default pinned manifest URL: the GitHub commits API for the pinned
	 * branch of nl-design-system/themes, requesting the raw head SHA via the
	 * `application/vnd.github.sha` media type.
	 *
	 * @var string
	 */
	private const DEFAULT_MANIFEST_URL = 'https://api.github.com/repos/nl-design-system/themes/commits/main';

	/**
	 * The config service for reading/writing app config state.
	 *
	 * @var IConfig
	 */
	private IConfig $config;

	/**
	 * The HTTP client service (first outbound egress in this app).
	 *
	 * @var IClientService
	 */
	private IClientService $clientService;

	/**
	 * The token set service — source of installed sets' upstream provenance.
	 *
	 * @var TokenSetService
	 */
	private TokenSetService $tokenSetService;

	/**
	 * The logger — failures are logged at no higher than info level.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The config service.
	 * @param IClientService $clientService The HTTP client service.
	 * @param TokenSetService $tokenSetService The token set service.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		IConfig $config,
		IClientService $clientService,
		TokenSetService $tokenSetService,
		LoggerInterface $logger,
	) {
		$this->config = $config;
		$this->clientService = $clientService;
		$this->tokenSetService = $tokenSetService;
		$this->logger = $logger;
	}//end __construct()

	/**
	 * Whether the freshness check is enabled.
	 *
	 * @return bool True when the admin has opted in.
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	public function isEnabled(): bool {
		return $this->config->getAppValue(Application::APP_ID, self::CONFIG_ENABLED, 'no') === 'yes';
	}//end isEnabled()

	/**
	 * Enable or disable the freshness check.
	 *
	 * @param bool $enabled The new enabled state.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	public function setEnabled(bool $enabled): void {
		$value = 'no';
		if ($enabled === true) {
			$value = 'yes';
		}

		$this->config->setAppValue(Application::APP_ID, self::CONFIG_ENABLED, $value);
	}//end setEnabled()

	/**
	 * Get the current status for the admin settings panel: whether enabled,
	 * the last-checked timestamp, and the notices still visible after
	 * dismissal filtering.
	 *
	 * @return array{enabled: bool, lastChecked: string|null, notices: array<int, array<string, mixed>>} The status payload.
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	public function getStatus(): array {
		$checkedAt = $this->config->getAppValue(Application::APP_ID, self::CONFIG_CHECKED_AT, '');
		$lastChecked = null;
		if ($checkedAt !== '') {
			$lastChecked = $checkedAt;
		}

		return [
			'enabled' => $this->isEnabled(),
			'lastChecked' => $lastChecked,
			'notices' => $this->getVisibleNotices(),
		];
	}//end getStatus()

	/**
	 * Dismiss a notice for a set at a specific upstream version/SHA marker.
	 * A later detection carrying a different marker for the same set
	 * re-surfaces (dismissal is per-version, not permanent).
	 *
	 * @param string $setId The token set id, or the generic notice key.
	 * @param string $versionOrSha The version (or short/full SHA) being dismissed.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	public function dismiss(string $setId, string $versionOrSha): void {
		$dismissed = $this->getDismissedMap();
		$dismissed[$setId] = $versionOrSha;
		$this->setDismissedMap(map: $dismissed);
	}//end dismiss()

	/**
	 * Run the freshness check. Returns immediately (zero network, zero
	 * processing) when disabled. Every failure path is caught here — a run
	 * never throws, and a failure leaves prior notice state untouched.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	public function runCheck(): void {
		if ($this->isEnabled() === false) {
			return;
		}

		try {
			$this->doRunCheck();
		} catch (\Throwable $e) {
			// Silent degradation: a failed check never breaks theming, cron,
			// or the admin panel. Logged at info level only.
			$this->logger->info(
				'nldesign upstream-freshness check failed: ' . $e->getMessage(),
				['app' => Application::APP_ID, 'exception' => $e]
			);
		}
	}//end runCheck()

	/**
	 * The actual check body, run inside runCheck()'s catch-all. At most two
	 * outbound requests: one conditional freshness GET, plus one optional
	 * compare GET only when the head SHA has moved.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	private function doRunCheck(): void {
		$run = new FreshnessRun(client: $this->clientService->newClient(), manifestUrl: $this->getManifestUrl());
		$response = $run->client->get($run->manifestUrl, ['timeout' => 10, 'headers' => $this->manifestHeaders()]);

		$this->config->setAppValue(Application::APP_ID, self::CONFIG_CHECKED_AT, (string)time());

		$headSha = $this->acceptedHeadSha(response: $response);
		if ($headSha === null) {
			return;
		}

		$this->storeHead(response: $response, headSha: $headSha);

		$staleSets = $this->getStaleComparableSets(headSha: $headSha);
		if (empty($staleSets) === true) {
			// Nothing installed is behind this head revision.
			return;
		}

		$run->headSha = $headSha;
		$this->attributeAndStoreNotices(staleSets: $staleSets, run: $run);
	}//end doRunCheck()

	/**
	 * The request headers of the manifest check: the head revision only, and
	 * the stored ETag for a conditional request.
	 *
	 * @return array<string, string> The headers.
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	private function manifestHeaders(): array {
		$headers = ['Accept' => 'application/vnd.github.sha'];
		$etag = $this->config->getAppValue(Application::APP_ID, self::CONFIG_ETAG, '');
		if ($etag !== '') {
			$headers['If-None-Match'] = $etag;
		}

		return $headers;
	}//end manifestHeaders()

	/**
	 * The head revision a manifest answer carries, or null when this run has
	 * nothing more to do: 304 (steady state), another status than 200, or a
	 * malformed body. A malformed body leaves the stored ETag alone, so the
	 * next valid check is not masked by a bad one.
	 *
	 * @param IResponse $response The manifest answer.
	 *
	 * @return string|null The head revision, or null.
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	private function acceptedHeadSha(IResponse $response): ?string {
		$status = $response->getStatusCode();
		if ($status === 304) {
			return null;
		}

		if ($status !== 200) {
			$this->logger->info(
				'nldesign upstream-freshness check received unexpected status ' . $status,
				['app' => Application::APP_ID]
			);
			return null;
		}

		return $this->wellFormedHead(body: $response->getBody());
	}//end acceptedHeadSha()

	/**
	 * The head revision in a manifest body, or null when it is not one.
	 *
	 * @param string $body The manifest body.
	 *
	 * @return string|null The trimmed head revision, or null.
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	private function wellFormedHead(string $body): ?string {
		$headSha = trim($body);
		if ($headSha === '' || preg_match('/^[0-9a-f]{7,40}$/i', $headSha) !== 1) {
			$this->logger->info(
				'nldesign upstream-freshness check received a malformed head revision.',
				['app' => Application::APP_ID]
			);
			return null;
		}

		return $headSha;
	}//end wellFormedHead()

	/**
	 * Store the answer's ETag, when it has one, and the head revision.
	 *
	 * @param IResponse $response The manifest answer.
	 * @param string $headSha The accepted head revision.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	private function storeHead(IResponse $response, string $headSha): void {
		$newEtag = $response->getHeader('ETag');
		if ($newEtag !== '') {
			$this->config->setAppValue(Application::APP_ID, self::CONFIG_ETAG, $newEtag);
		}

		$this->config->setAppValue(Application::APP_ID, self::CONFIG_HEAD_SHA, $headSha);
	}//end storeHead()

	/**
	 * Installed, comparable sets whose recorded upstreamRef differs from the
	 * fetched head SHA. Sets without an upstreamRef and all custom-* sets are
	 * excluded from comparison entirely.
	 *
	 * @param string $headSha The fetched upstream head SHA.
	 *
	 * @return array<string, array{name: string, upstreamRef: string, upstreamVersion: string|null}> Stale sets indexed by id.
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	private function getStaleComparableSets(string $headSha): array {
		$stale = [];
		foreach ($this->tokenSetService->getAvailableTokenSets() as $set) {
			$id = $set['id'];
			if (str_starts_with($id, 'custom-') === true) {
				continue;
			}

			$upstreamRef = $set['upstreamRef'] ?? null;
			if (is_string($upstreamRef) === false || $upstreamRef === '') {
				continue;
			}

			if ($upstreamRef === $headSha) {
				continue;
			}

			$stale[$id] = [
				'name' => $set['name'],
				'upstreamRef' => $upstreamRef,
				'upstreamVersion' => ($set['upstreamVersion'] ?? null),
			];
		}//end foreach

		return $stale;
	}//end getStaleComparableSets()

	/**
	 * Attribute the upstream change to installed sets via one compare-API
	 * request, or degrade to a single generic notice on any failure
	 * (network error, non-200, unparseable body, or no path matching any
	 * stale set).
	 *
	 * @param array<string, array{name: string, upstreamRef: string, upstreamVersion: string|null}> $staleSets Stale sets indexed by id.
	 * @param FreshnessRun $run The check: the reused client, the manifest URL and the head SHA.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	private function attributeAndStoreNotices(array $staleSets, FreshnessRun $run): void {
		$changedPaths = $this->changedPathsOrNull(run: $run, baseRef: $this->pickBaseRef(staleSets: $staleSets));
		if ($changedPaths === null) {
			$this->storeGenericNotice(headSha: $run->headSha);
			return;
		}

		$run->changedPaths = $changedPaths;
		$notices = $this->getUpdatesMap();
		$matched = $this->matchedNotices(staleSets: $staleSets, run: $run);
		if ($matched === []) {
			$this->storeGenericNotice(headSha: $run->headSha);
			return;
		}

		$this->setUpdatesMap(map: array_replace($notices, $matched));
	}//end attributeAndStoreNotices()

	/**
	 * The paths the compare request lists, or null when it fails in any way (logged at info level).
	 *
	 * @param FreshnessRun $run The check.
	 * @param string $baseRef The compare base.
	 *
	 * @return array<int, string>|null The changed paths.
	 */
	private function changedPathsOrNull(FreshnessRun $run, string $baseRef): array|null {
		try {
			return $this->fetchChangedPaths(run: $run, baseRef: $baseRef);
		} catch (\Throwable $e) {
			$this->logger->info(
				'nldesign upstream-freshness attribution failed; storing a generic notice: ' . $e->getMessage(),
				['app' => Application::APP_ID]
			);
		}

		return null;
	}//end changedPathsOrNull()

	/**
	 * A notice for every stale set a changed path belongs to.
	 *
	 * @param array<string, array{name: string, upstreamRef: string, upstreamVersion: string|null}> $staleSets Stale sets indexed by id.
	 * @param FreshnessRun $run The check, with the head SHA and the changed paths.
	 *
	 * @return array<string, array<string, mixed>> The notices, indexed by set id.
	 */
	private function matchedNotices(array $staleSets, FreshnessRun $run): array {
		$notices = [];
		foreach ($staleSets as $setId => $set) {
			if ($this->changedPathsMatchOrg(changedPaths: $run->changedPaths, orgId: $setId) === true) {
				$notices[$setId] = [
					'installedRef' => $set['upstreamRef'],
					'installedVersion' => $set['upstreamVersion'],
					'headSha' => $run->headSha,
					'upstreamVersion' => $set['upstreamVersion'],
					'detectedAt' => time(),
				];
			}
		}

		return $notices;
	}//end matchedNotices()

	/**
	 * Pick the compare-request base ref from the stale sets' recorded
	 * upstreamRef values. Distinct installed refs are rare in practice (all
	 * sets are normally regenerated together by the same sync run, sharing
	 * one ref); when they do differ, a deterministic choice (the first in
	 * sorted order) is taken so the compare's changed-file list stays a
	 * best-effort superset. Attribution is always best-effort — a
	 * non-covering choice here degrades to the generic notice, never an
	 * error.
	 *
	 * @param array<string, array{name: string, upstreamRef: string, upstreamVersion: string|null}> $staleSets Stale sets indexed by id.
	 *
	 * @return string The chosen base ref.
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	private function pickBaseRef(array $staleSets): string {
		$refs = array_unique(array_column($staleSets, 'upstreamRef'));
		sort($refs);

		return (string)$refs[0];
	}//end pickBaseRef()

	/**
	 * Fetch the list of changed file paths between the base ref and the
	 * fetched head SHA via one GitHub compare-API GET. Returns null on any
	 * non-200 status or unparseable body (caller degrades to a generic
	 * notice) — this is the second and last request of the run.
	 *
	 * @param FreshnessRun $run The check: the reused client, the manifest URL and the head SHA.
	 * @param string $baseRef The compare base.
	 *
	 * @return array<int, string>|null The list of changed file paths, or null when unresolvable.
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	private function fetchChangedPaths(FreshnessRun $run, string $baseRef): ?array {
		$compareUrl = $this->buildCompareUrl(run: $run, baseRef: $baseRef);
		if ($compareUrl === null) {
			return null;
		}

		$response = $run->client->get($compareUrl, ['timeout' => 10, 'headers' => ['Accept' => 'application/vnd.github+json']]);
		if ($response->getStatusCode() !== 200) {
			return null;
		}

		$data = json_decode($response->getBody(), true);
		if (is_array($data) === false || isset($data['files']) === false || is_array($data['files']) === false) {
			return null;
		}

		$paths = [];
		foreach ($data['files'] as $file) {
			if (isset($file['filename']) === true && is_string($file['filename']) === true) {
				$paths[] = $file['filename'];
			}
		}

		return $paths;
	}//end fetchChangedPaths()

	/**
	 * Derive the compare-API URL from the pinned manifest URL by replacing
	 * its `/commits/<branch>` suffix with `/compare/{baseRef}...{headSha}`.
	 * Returns null (attribution unavailable, generic notice) when the
	 * manifest URL does not follow the expected GitHub commits-API shape —
	 * e.g. an admin-overridden mirror with an incompatible layout.
	 *
	 * @param FreshnessRun $run The check: the manifest URL and the head SHA.
	 * @param string $baseRef The compare base.
	 *
	 * @return string|null The compare URL, or null when it cannot be derived.
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	private function buildCompareUrl(FreshnessRun $run, string $baseRef): ?string {
		$marker = '/commits/';
		$pos = strpos($run->manifestUrl, $marker);
		if ($pos === false) {
			return null;
		}

		$repoBase = substr($run->manifestUrl, 0, $pos);

		return $repoBase . '/compare/' . rawurlencode($baseRef) . '...' . rawurlencode($run->headSha);
	}//end buildCompareUrl()

	/**
	 * Whether any changed path in the compare result belongs to the given
	 * organization id (matches `proprietary/{orgId}(-design-tokens)?/`).
	 *
	 * @param array<int, string> $changedPaths The changed file paths from the compare result.
	 * @param string $orgId The token-set id to match against.
	 *
	 * @return bool True when at least one changed path belongs to the org.
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	private function changedPathsMatchOrg(array $changedPaths, string $orgId): bool {
		foreach ($changedPaths as $path) {
			if (preg_match('#^proprietary/([^/]+)/#', $path, $matches) !== 1) {
				continue;
			}

			$pathOrgId = strtolower((string)preg_replace('/-design-tokens$/', '', $matches[1]));
			if ($pathOrgId === $orgId) {
				return true;
			}
		}

		return false;
	}//end changedPathsMatchOrg()

	/**
	 * Store a single generic (unattributed) notice keyed by the head SHA —
	 * the fallback when attribution fails or matches nothing. Dismissed by
	 * head SHA rather than per set.
	 *
	 * @param string $headSha The fetched upstream head SHA.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	private function storeGenericNotice(string $headSha): void {
		$notices = $this->getUpdatesMap();
		$notices[self::GENERIC_NOTICE_KEY] = [
			'installedRef' => null,
			'installedVersion' => null,
			'headSha' => $headSha,
			'upstreamVersion' => null,
			'detectedAt' => time(),
		];
		$this->setUpdatesMap(map: $notices);
	}//end storeGenericNotice()

	/**
	 * The stored notices still visible after filtering out ones dismissed
	 * for their current version/SHA marker. A newer detection (different
	 * marker) for the same set re-surfaces regardless of a prior dismissal.
	 *
	 * @return array<int, array<string, mixed>> The visible notices, each carrying its setId.
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	private function getVisibleNotices(): array {
		$notices = $this->getUpdatesMap();
		$dismissed = $this->getDismissedMap();
		$visible = [];

		foreach ($notices as $setId => $notice) {
			$marker = ($notice['upstreamVersion'] ?? $notice['headSha']);
			$dismissedMarker = ($dismissed[$setId] ?? null);
			if ($dismissedMarker !== null && $dismissedMarker === $marker) {
				continue;
			}

			$entry = $notice;
			$entry['setId'] = $setId;
			$visible[] = $entry;
		}

		return $visible;
	}//end getVisibleNotices()

	/**
	 * The pinned manifest URL, defaulting to the GitHub commits API for
	 * nl-design-system/themes' pinned branch. Overridable via app config so
	 * egress-filtered deployments can point at an internal mirror.
	 *
	 * @return string The manifest URL.
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	private function getManifestUrl(): string {
		return $this->config->getAppValue(Application::APP_ID, self::CONFIG_MANIFEST_URL, self::DEFAULT_MANIFEST_URL);
	}//end getManifestUrl()

	/**
	 * Read the JSON-encoded notices map from app config.
	 *
	 * @return array<string, array<string, mixed>> Notices indexed by setId.
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	private function getUpdatesMap(): array {
		$raw = $this->config->getAppValue(Application::APP_ID, self::CONFIG_UPDATES, '{}');
		$decoded = json_decode($raw, true);
		if (is_array($decoded) === false) {
			return [];
		}

		return $decoded;
	}//end getUpdatesMap()

	/**
	 * Write the notices map to app config.
	 *
	 * @param array<string, array<string, mixed>> $map Notices indexed by setId.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	private function setUpdatesMap(array $map): void {
		$this->config->setAppValue(Application::APP_ID, self::CONFIG_UPDATES, json_encode($map));
	}//end setUpdatesMap()

	/**
	 * Read the JSON-encoded dismissal map from app config.
	 *
	 * @return array<string, string> Dismissed version/SHA markers indexed by setId.
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	private function getDismissedMap(): array {
		$raw = $this->config->getAppValue(Application::APP_ID, self::CONFIG_DISMISSED, '{}');
		$decoded = json_decode($raw, true);
		if (is_array($decoded) === false) {
			return [];
		}

		return $decoded;
	}//end getDismissedMap()

	/**
	 * Write the dismissal map to app config.
	 *
	 * @param array<string, string> $map Dismissed version/SHA markers indexed by setId.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	private function setDismissedMap(array $map): void {
		$this->config->setAppValue(Application::APP_ID, self::CONFIG_DISMISSED, json_encode($map));
	}//end setDismissedMap()
}//end class
