<?php

/**
 * NL Design Group Theming Service.
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
 * @spec openspec/specs/per-group-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\Exception\GroupThemingValidationException;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserSession;

/**
 * Resolves which token set applies to the current request, mapping
 * Nextcloud groups to token sets on top of the instance default.
 *
 * Storage: the `nldesign` appconfig key `group_token_sets` holds an ORDERED
 * JSON array of `{"group": string, "tokenSet": string}` entries — array
 * order IS priority order (index 0 wins first), so there is no separate
 * priority field that could drift out of sync with the array. A companion
 * integer appconfig key `group_token_sets_generation` is bumped on every
 * successful mapping write and used as a cache-busting component: no
 * enumeration is needed to invalidate every cached resolution at once.
 *
 * Resolution precedence (`resolveTokenSetForRequest()`):
 *   1. An active admin theme preview for the requesting session (change
 *      `theme-preview-workflow`) — see `getActivePreviewSet()`.
 *   2. The first entry of the ordered mapping whose group contains the
 *      user; entries referencing a token set that no longer exists are
 *      skipped.
 *   3. The instance default `token_set` appconfig value.
 *
 * Per-group theming covers ONLY which token-set CSS stack is injected. NC
 * core theming values (ThemingDefaults logo/primary/background) and
 * theming-sync stay instance-global by design — see
 * `openspec/specs/per-group-theming/spec.md` Requirement "Core Theming
 * Remains Instance-Global".
 *
 * @spec openspec/specs/per-group-theming/spec.md
 */
class GroupThemingService {

	/**
	 * The appconfig key holding the ordered JSON mapping array.
	 *
	 * @var string
	 */
	private const CONFIG_KEY = 'group_token_sets';

	/**
	 * The appconfig key holding the mapping generation counter.
	 *
	 * @var string
	 */
	private const GENERATION_KEY = 'group_token_sets_generation';

	/**
	 * The appconfig key holding the instance default token set.
	 *
	 * @var string
	 */
	private const DEFAULT_TOKEN_SET_KEY = 'token_set';

	/**
	 * TTL (seconds) backstop for a cached resolution, per design.md Decision 3.
	 *
	 * @var int
	 */
	private const CACHE_TTL = 3600;

	/**
	 * Distributed cache for per-user resolved token sets.
	 *
	 * @var ICache
	 */
	private ICache $cache;

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The config service.
	 * @param IGroupManager $groupManager The group manager, for membership and existence checks.
	 * @param IUserSession $userSession The user session, to resolve the requesting user.
	 * @param TokenSetService $tokenSetService The token set service, for availability checks.
	 * @param ThemePreviewService $previewService The admin theme-preview resolver (wins over group mapping).
	 * @param ICacheFactory $cacheFactory Creates the distributed per-user resolution cache.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly IGroupManager $groupManager,
		private readonly IUserSession $userSession,
		private readonly TokenSetService $tokenSetService,
		private readonly ThemePreviewService $previewService,
		ICacheFactory $cacheFactory,
	) {
		$this->cache = $cacheFactory->createDistributed(prefix: 'nldesign-group-theming');
	}//end __construct()

	/**
	 * Get the stored mapping in priority order.
	 *
	 * An absent or malformed stored value reads as an empty mapping — never
	 * an error — reproducing today's global-theming behavior exactly.
	 *
	 * @return array<int, array{group: string, tokenSet: string}> The ordered mapping.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function getMapping(): array {
		$raw = $this->config->getAppValue(appName: Application::APP_ID, key: self::CONFIG_KEY, default: '[]');

		$decoded = json_decode($raw, true);
		if (is_array($decoded) === false) {
			return [];
		}

		$result = [];
		foreach ($decoded as $entry) {
			if (is_array($entry) === false) {
				continue;
			}

			$group = ($entry['group'] ?? null);
			$tokenSet = ($entry['tokenSet'] ?? null);
			if (is_string($group) === false || is_string($tokenSet) === false) {
				continue;
			}

			$result[] = (new GroupDelegationRules())->read(entry: $entry, clean: ['group' => $group, 'tokenSet' => $tokenSet]);
		}

		return $result;
	}//end getMapping()

	/**
	 * Replace the full ordered mapping after validation.
	 *
	 * Validates every entry BEFORE persisting anything: an unknown group, an
	 * unavailable token set, or a duplicate group aborts the whole save with
	 * a {@see GroupThemingValidationException} naming the offending entry —
	 * the previously stored mapping and generation counter are left
	 * untouched (atomicity, per the "Unknown group or set is rejected
	 * without partial writes" scenario). The generation counter is bumped
	 * only on a successful write.
	 *
	 * @param array<int, mixed> $entries The desired ordered mapping (`{group, tokenSet}` each).
	 *
	 * @return array<int, array{group: string, tokenSet: string}> The persisted mapping.
	 *
	 * @throws GroupThemingValidationException When any entry fails validation.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function setMapping(array $entries): array {
		$clean = [];
		$seenGroups = [];

		foreach ($entries as $entry) {
			$clean[] = $this->validateEntry(entry: $entry, seenGroups: $seenGroups);
		}

		// Nothing above persisted anything — only reached once every entry
		// validated, so a failed save never leaves a partial mapping.
		$this->config->setAppValue(appName: Application::APP_ID, key: self::CONFIG_KEY, value: json_encode($clean));
		$this->bumpGeneration();

		return $clean;
	}//end setMapping()

	/**
	 * Replace the stored entry of one group, validating that entry only.
	 *
	 * The delegated choice of a subadmin goes through here rather than
	 * through setMapping(): that validates every entry again, so one deleted
	 * group or removed set anywhere in the mapping refused every delegated
	 * save, and told a non-admin about groups that are not theirs. The other
	 * entries are written back as they are stored.
	 *
	 * @param array<string, mixed> $entry The group's new entry.
	 *
	 * @return array<string, mixed> The persisted entry.
	 *
	 * @throws GroupThemingValidationException When the entry fails validation or the group has no entry.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function replaceEntry(array $entry): array {
		$seenGroups = [];
		$clean = $this->validateEntry(entry: $entry, seenGroups: $seenGroups);

		$mapping = $this->getMapping();
		$index = array_search($clean['group'], array_column($mapping, 'group'), true);
		if ($index === false) {
			throw new GroupThemingValidationException(entry: $entry, reason: 'The group has no mapping entry.');
		}

		$mapping[$index] = $clean;
		$this->config->setAppValue(appName: Application::APP_ID, key: self::CONFIG_KEY, value: json_encode($mapping));
		$this->bumpGeneration();

		return $clean;
	}//end replaceEntry()

	/**
	 * Validate one raw mapping entry against the group/token-set/duplicate
	 * rules, tracking groups already seen in this batch by reference so a
	 * duplicate anywhere in the payload is caught.
	 *
	 * @param mixed $entry The raw entry (may be malformed).
	 * @param array<string, bool> $seenGroups Groups already validated in this batch (mutated by reference).
	 *
	 * @return array{group: string, tokenSet: string} The cleaned entry.
	 *
	 * @throws GroupThemingValidationException When the entry fails any validation rule.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	private function validateEntry(mixed $entry, array &$seenGroups): array {
		$group = $this->extractField(entry: $entry, field: 'group');
		$tokenSet = $this->extractField(entry: $entry, field: 'tokenSet');

		if (is_string($group) === false || $group === '') {
			throw new GroupThemingValidationException(entry: $entry, reason: 'Missing or invalid group id.');
		}

		if (is_string($tokenSet) === false || $tokenSet === '') {
			throw new GroupThemingValidationException(entry: $entry, reason: 'Missing or invalid token set id.');
		}

		if (isset($seenGroups[$group]) === true) {
			throw new GroupThemingValidationException(
				entry: $entry,
				reason: sprintf('Only one mapping entry is allowed per group ("%s" appears more than once).', $group)
			);
		}

		if ($this->groupManager->groupExists($group) === false) {
			throw new GroupThemingValidationException(entry: $entry, reason: sprintf('Group "%s" does not exist.', $group));
		}

		if ($this->tokenSetService->isValidTokenSet(tokenSetId: $tokenSet) === false) {
			throw new GroupThemingValidationException(entry: $entry, reason: sprintf('Token set "%s" is not available.', $tokenSet));
		}

		$seenGroups[$group] = true;

		return (new GroupDelegationRules())->validate(
			entry: $entry,
			clean: ['group' => $group, 'tokenSet' => $tokenSet],
			isAvailable: fn (string $setId): bool => $this->tokenSetService->isValidTokenSet(tokenSetId: $setId)
		);
	}//end validateEntry()

	/**
	 * Extract a string field from a raw, possibly malformed mapping entry.
	 *
	 * @param mixed $entry The raw entry.
	 * @param string $field The field name (`group` or `tokenSet`).
	 *
	 * @return mixed The field value, or null when the entry is not an array or lacks the field.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	private function extractField(mixed $entry, string $field): mixed {
		if (is_array($entry) === false) {
			return null;
		}

		return ($entry[$field] ?? null);
	}//end extractField()

	/**
	 * List all Nextcloud groups for the admin UI's group picker.
	 *
	 * @return array<int, array{id: string, displayName: string}> The available groups, sorted by display name.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function getAvailableGroups(): array {
		$groups = $this->groupManager->search(search: '');

		$result = [];
		foreach ($groups as $group) {
			$result[] = [
				'id' => $group->getGID(),
				'displayName' => $group->getDisplayName(),
			];
		}

		usort($result, fn (array $a, array $b) => strcasecmp($a['displayName'], $b['displayName']));

		return $result;
	}//end getAvailableGroups()

	/**
	 * Resolve the effective token set for the current request.
	 *
	 * Wrapped fail-open to the instance default set — presentation, never
	 * security, mirroring `Application::isThemingDisabled()`'s catch-all: a
	 * broken group backend, cache, or malformed stored mapping must never
	 * brick theming or escape into `Application::boot()`.
	 *
	 * @return string The resolved token set id.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function resolveTokenSetForRequest(): string {
		try {
			return $this->resolveTokenSetForRequestUnsafe();
		} catch (\Throwable $e) {
			// Fail open: presentation, not security. A broken group backend,
			// cache, or malformed mapping must not strip theming or crash
			// the boot path.
			return $this->getDefaultTokenSet();
		}
	}//end resolveTokenSetForRequest()

	/**
	 * The un-guarded resolution pipeline; see {@see resolveTokenSetForRequest()}
	 * for the fail-open wrapper.
	 *
	 * @return string The resolved token set id.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	private function resolveTokenSetForRequestUnsafe(): string {
		// 1. Admin preview wins over group mapping for the previewing admin.
		// No-op stub until change `theme-preview-workflow` lands (its
		// ThemePreviewService does not exist in this codebase yet) — this is
		// a deliberate, clearly-marked integration point, not dead code.
		$previewSet = $this->getActivePreviewSet();
		if ($previewSet !== null) {
			return $previewSet;
		}

		// 2. No session (login/public/error pages, occ/cron): explicit
		// branch to the instance default — by design, not by exception
		// fallback — and it MUST NOT touch IGroupManager.
		$user = $this->userSession->getUser();
		if ($user === null) {
			return $this->getDefaultTokenSet();
		}

		// 3. Empty mapping fast path: no cache access, no group lookup —
		// reproduces today's zero-lookup behavior exactly for non-adopters.
		$mapping = $this->getMapping();
		if ($mapping === []) {
			return $this->getDefaultTokenSet();
		}

		$generation = $this->getGeneration();
		$cacheKey = sprintf('resolve:%s:%d', $user->getUID(), $generation);

		$cached = $this->cache->get(key: $cacheKey);
		if (is_string($cached) === true) {
			return $cached;
		}

		$userGroupIds = $this->groupManager->getUserGroupIds(user: $user);
		$resolved = $this->matchFirstEntry(mapping: $mapping, userGroupIds: $userGroupIds);
		if ($resolved === null) {
			$resolved = $this->getDefaultTokenSet();
		}

		$this->cache->set(key: $cacheKey, value: $resolved, ttl: self::CACHE_TTL);

		return $resolved;
	}//end resolveTokenSetForRequestUnsafe()

	/**
	 * Find the first mapping entry (in stored/priority order) matching one
	 * of the user's groups, skipping entries whose token set no longer
	 * exists.
	 *
	 * @param array<int, array{group: string, tokenSet: string}> $mapping The ordered mapping.
	 * @param string[] $userGroupIds The requesting user's group ids.
	 *
	 * @return string|null The matched token set id, or null when nothing matches.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	private function matchFirstEntry(array $mapping, array $userGroupIds): ?string {
		foreach ($mapping as $entry) {
			// Dead-set entries (a mapped custom set since deleted) are
			// skipped, not fatal — resolution continues with later entries.
			if ($this->tokenSetService->isValidTokenSet(tokenSetId: $entry['tokenSet']) === false) {
				continue;
			}

			if (in_array($entry['group'], $userGroupIds, true) === true) {
				return $entry['tokenSet'];
			}
		}

		return null;
	}//end matchFirstEntry()

	/**
	 * The active admin theme preview's token set for the requesting
	 * session, if any.
	 *
	 * Wired to `ThemePreviewService` (change `theme-preview-workflow`): an
	 * admin previewing a token set sees it in their own session only, ahead
	 * of any group mapping. Returns null for everyone else, so group mapping
	 * and the instance default behave exactly as specified.
	 *
	 * Fails open (null) on any error — a broken preview lookup must never
	 * cost a normal user their theme.
	 *
	 * @return string|null The previewed token set, or null when no preview is active.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 * @spec openspec/specs/theme-preview/spec.md#requirement-preview-isolation
	 */
	protected function getActivePreviewSet(): ?string {
		try {
			$preview = $this->previewService->resolveEffectiveTokenSet(
				userSession: $this->userSession,
				activeTokenSet: $this->getDefaultTokenSet()
			);

			if ($preview['previewActive'] === true) {
				return $preview['tokenSet'];
			}
		} catch (\Throwable $e) {
			return null;
		}

		return null;
	}//end getActivePreviewSet()

	/**
	 * Read the instance default token set appconfig value.
	 *
	 * @return string The default token set id (falls back to `nextcloud`).
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	private function getDefaultTokenSet(): string {
		return $this->config->getAppValue(appName: Application::APP_ID, key: self::DEFAULT_TOKEN_SET_KEY, default: 'nextcloud');
	}//end getDefaultTokenSet()

	/**
	 * Read the current mapping generation counter.
	 *
	 * @return int The generation (0 when never written).
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	private function getGeneration(): int {
		return (int)$this->config->getAppValue(appName: Application::APP_ID, key: self::GENERATION_KEY, default: '0');
	}//end getGeneration()

	/**
	 * Increment and persist the mapping generation counter.
	 *
	 * Bumping the generation invalidates every cached resolution in O(1):
	 * old-generation cache keys become unreachable and age out via TTL —
	 * no enumeration, no flush of unrelated keys.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	private function bumpGeneration(): void {
		$next = ($this->getGeneration() + 1);
		$this->config->setAppValue(appName: Application::APP_ID, key: self::GENERATION_KEY, value: (string)$next);
	}//end bumpGeneration()
}//end class
