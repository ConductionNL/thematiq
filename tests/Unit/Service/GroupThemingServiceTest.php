<?php

/**
 * Unit tests for GroupThemingService.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/per-group-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\Exception\GroupThemingValidationException;
use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\ThemePreviewService;
use OCA\Thematiq\Service\TokenSetService;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Covers the "Deterministic Resolution Precedence", "O(1)-ish Cached
 * Resolution", and "Group-to-Token-Set Mapping Storage" requirements of
 * openspec/specs/per-group-theming/spec.md.
 */
class GroupThemingServiceTest extends TestCase {

	/**
	 * Build a service instance with an in-memory cache store (real
	 * get/set/invalidation semantics, unlike a pure spy) so
	 * generation-bump invalidation can be exercised end-to-end.
	 *
	 * @param IConfig $config The config mock/stub.
	 * @param IGroupManager $groupManager The group manager mock/stub.
	 * @param IUserSession $userSession The user session mock/stub.
	 * @param TokenSetService $tokenSetService The token set service mock/stub.
	 *
	 * @return array{0: GroupThemingService, 1: ICache} The service and its backing cache double.
	 */
	private function buildService(
		IConfig $config,
		IGroupManager $groupManager,
		IUserSession $userSession,
		TokenSetService $tokenSetService,
	): array {
		$store = [];

		$cache = $this->createMock(originalClassName: ICache::class);
		$cache->method('get')->willReturnCallback(
			static function (string $key) use (&$store) {
				return ($store[$key] ?? null);
			}
		);
		$cache->method('set')->willReturnCallback(
			static function (string $key, $value, int $ttl = 0) use (&$store): bool {
				$store[$key] = $value;
				return true;
			}
		);

		$cacheFactory = $this->createMock(originalClassName: ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($cache);

		$service = new GroupThemingService(
			config: $config,
			groupManager: $groupManager,
			userSession: $userSession,
			tokenSetService: $tokenSetService,
			previewService: $this->inactivePreviewService(),
			cacheFactory: $cacheFactory
		);

		return [$service, $cache];
	}//end buildService()

	/**
	 * Build an IConfig stub backed by an in-memory array so
	 * getAppValue/setAppValue round-trip exactly like the real appconfig,
	 * which is required for the generation-bump invalidation test.
	 *
	 * @param array<string, string> $initial Seed values (appconfig key => value).
	 *
	 * @return IConfig The stub.
	 */
	private function fakeConfig(array $initial = []): IConfig {
		$store = $initial;
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, string $default = '') use (&$store) {
				return ($store[$key] ?? $default);
			}
		);
		$config->method('setAppValue')->willReturnCallback(
			static function (string $app, string $key, string $value = '') use (&$store): void {
				$store[$key] = $value;
			}
		);

		return $config;
	}//end fakeConfig()

	/**
	 * Build a user double with the given UID.
	 *
	 * @param string $uid The user id.
	 *
	 * @return IUser The user double.
	 */
	private function fakeUser(string $uid): IUser {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($uid);

		return $user;
	}//end fakeUser()

	/**
	 * A theme-preview service stub with no active preview, so these tests
	 * exercise group-mapping resolution only. Preview precedence has its own
	 * dedicated coverage in the theme-preview suite.
	 *
	 * @return ThemePreviewService The stub.
	 */
	private function inactivePreviewService(): ThemePreviewService {
		$preview = $this->createMock(originalClassName: ThemePreviewService::class);
		$preview->method('resolveEffectiveTokenSet')->willReturnCallback(
			static fn (IUserSession $session, string $activeTokenSet): array => [
				'tokenSet' => $activeTokenSet,
				'previewActive' => false,
				'expiresAt' => null,
			]
		);

		return $preview;
	}//end inactivePreviewService()

	/**
	 * A token set service stub where every id in $available validates and
	 * everything else is a "dead"/unknown set.
	 *
	 * @param string[] $available The valid token set ids.
	 *
	 * @return TokenSetService The stub.
	 */
	private function fakeTokenSetService(array $available): TokenSetService {
		$service = $this->createMock(originalClassName: TokenSetService::class);
		$service->method('isValidTokenSet')->willReturnCallback(
			static fn (string $tokenSetId) => in_array($tokenSetId, $available, true)
		);

		return $service;
	}//end fakeTokenSetService()

	// -------------------------------------------------------------------
	// Storage: getMapping() / setMapping()
	// -------------------------------------------------------------------

	/**
	 * Absent config reads as an empty mapping (today's global-theming behavior).
	 *
	 * @return void
	 */
	public function testGetMappingDefaultsToEmpty(): void {
		[$service] = $this->buildService(
			config: $this->fakeConfig(),
			groupManager: $this->createMock(originalClassName: IGroupManager::class),
			userSession: $this->createMock(originalClassName: IUserSession::class),
			tokenSetService: $this->fakeTokenSetService(available: [])
		);

		$this->assertSame(expected: [], actual: $service->getMapping());
	}//end testGetMappingDefaultsToEmpty()

	/**
	 * Malformed stored JSON reads as an empty mapping, never an error.
	 *
	 * @return void
	 */
	public function testGetMappingMalformedJsonIsEmpty(): void {
		[$service] = $this->buildService(
			config: $this->fakeConfig(initial: ['group_token_sets' => 'not-json{']),
			groupManager: $this->createMock(originalClassName: IGroupManager::class),
			userSession: $this->createMock(originalClassName: IUserSession::class),
			tokenSetService: $this->fakeTokenSetService(available: [])
		);

		$this->assertSame(expected: [], actual: $service->getMapping());
	}//end testGetMappingMalformedJsonIsEmpty()

	/**
	 * A valid mapping is saved verbatim, in order, and the generation counter
	 * is incremented.
	 *
	 * @return void
	 */
	public function testSetMappingPersistsInOrderAndBumpsGeneration(): void {
		$config = $this->fakeConfig();
		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('groupExists')->willReturn(true);

		[$service] = $this->buildService(
			config: $config,
			groupManager: $groupManager,
			userSession: $this->createMock(originalClassName: IUserSession::class),
			tokenSetService: $this->fakeTokenSetService(available: ['amsterdam', 'utrecht'])
		);

		$result = $service->setMapping(
			[
				['group' => 'gemeente-a', 'tokenSet' => 'amsterdam'],
				['group' => 'gemeente-b', 'tokenSet' => 'utrecht'],
			]
		);

		$this->assertSame(
			expected: [
				['group' => 'gemeente-a', 'tokenSet' => 'amsterdam'],
				['group' => 'gemeente-b', 'tokenSet' => 'utrecht'],
			],
			actual: $result
		);
		$this->assertSame(expected: $result, actual: $service->getMapping());
		$this->assertSame(expected: 1, actual: (int)$config->getAppValue('nldesign', 'group_token_sets_generation', '0'));
	}//end testSetMappingPersistsInOrderAndBumpsGeneration()

	/**
	 * An entry naming a nonexistent group is rejected; nothing is persisted
	 * and the generation counter is untouched.
	 *
	 * @return void
	 */
	public function testSetMappingRejectsUnknownGroupWithoutPartialWrite(): void {
		$config = $this->fakeConfig(initial: ['group_token_sets' => '[]', 'group_token_sets_generation' => '3']);
		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('groupExists')->willReturn(false);

		[$service] = $this->buildService(
			config: $config,
			groupManager: $groupManager,
			userSession: $this->createMock(originalClassName: IUserSession::class),
			tokenSetService: $this->fakeTokenSetService(available: ['amsterdam'])
		);

		try {
			$service->setMapping([['group' => 'nonexistent', 'tokenSet' => 'amsterdam']]);
			$this->fail(message: 'Expected GroupThemingValidationException.');
		} catch (GroupThemingValidationException $e) {
			$this->assertSame(expected: ['group' => 'nonexistent', 'tokenSet' => 'amsterdam'], actual: $e->getEntry());
			$this->assertStringContainsString(needle: 'nonexistent', haystack: $e->getReason());
		}

		$this->assertSame(expected: [], actual: $service->getMapping());
		$this->assertSame(expected: 3, actual: (int)$config->getAppValue('nldesign', 'group_token_sets_generation', '0'));
	}//end testSetMappingRejectsUnknownGroupWithoutPartialWrite()

	/**
	 * An entry naming an unavailable token set is rejected.
	 *
	 * @return void
	 */
	public function testSetMappingRejectsUnknownTokenSet(): void {
		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('groupExists')->willReturn(true);

		[$service] = $this->buildService(
			config: $this->fakeConfig(),
			groupManager: $groupManager,
			userSession: $this->createMock(originalClassName: IUserSession::class),
			tokenSetService: $this->fakeTokenSetService(available: ['amsterdam'])
		);

		$this->expectException(exception: GroupThemingValidationException::class);
		$service->setMapping([['group' => 'gemeente-a', 'tokenSet' => 'does-not-exist']]);
	}//end testSetMappingRejectsUnknownTokenSet()

	/**
	 * Two entries for the same group are rejected — priority handles
	 * multi-group users, not duplicate rows.
	 *
	 * @return void
	 */
	public function testSetMappingRejectsDuplicateGroupEntries(): void {
		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('groupExists')->willReturn(true);

		[$service] = $this->buildService(
			config: $this->fakeConfig(),
			groupManager: $groupManager,
			userSession: $this->createMock(originalClassName: IUserSession::class),
			tokenSetService: $this->fakeTokenSetService(available: ['amsterdam', 'utrecht'])
		);

		$this->expectException(exception: GroupThemingValidationException::class);
		$service->setMapping(
			[
				['group' => 'gemeente-a', 'tokenSet' => 'amsterdam'],
				['group' => 'gemeente-a', 'tokenSet' => 'utrecht'],
			]
		);
	}//end testSetMappingRejectsDuplicateGroupEntries()

	// -------------------------------------------------------------------
	// Resolution precedence
	// -------------------------------------------------------------------

	/**
	 * A user in a single mapped group gets that group's token set.
	 *
	 * @return void
	 */
	public function testResolveReturnsMappedSetForSingleGroupMatch(): void {
		$config = $this->fakeConfig(
			initial: [
				'group_token_sets' => json_encode([['group' => 'gemeente-a', 'tokenSet' => 'amsterdam']]),
				'token_set' => 'nextcloud',
			]
		);

		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn(['gemeente-a']);

		$userSession = $this->createMock(originalClassName: IUserSession::class);
		$userSession->method('getUser')->willReturn($this->fakeUser(uid: 'anna'));

		[$service] = $this->buildService(
			config: $config,
			groupManager: $groupManager,
			userSession: $userSession,
			tokenSetService: $this->fakeTokenSetService(available: ['amsterdam'])
		);

		$this->assertSame(expected: 'amsterdam', actual: $service->resolveTokenSetForRequest());
	}//end testResolveReturnsMappedSetForSingleGroupMatch()

	/**
	 * A user in multiple mapped groups gets the earliest (highest-priority)
	 * matching entry.
	 *
	 * @return void
	 */
	public function testResolveReturnsEarliestEntryForMultiGroupUser(): void {
		$config = $this->fakeConfig(
			initial: [
				'group_token_sets' => json_encode(
					[
						['group' => 'gemeente-a', 'tokenSet' => 'amsterdam'],
						['group' => 'gemeente-b', 'tokenSet' => 'utrecht'],
					]
				),
				'token_set' => 'nextcloud',
			]
		);

		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn(['gemeente-a', 'gemeente-b']);

		$userSession = $this->createMock(originalClassName: IUserSession::class);
		$userSession->method('getUser')->willReturn($this->fakeUser(uid: 'bob'));

		[$service] = $this->buildService(
			config: $config,
			groupManager: $groupManager,
			userSession: $userSession,
			tokenSetService: $this->fakeTokenSetService(available: ['amsterdam', 'utrecht'])
		);

		$this->assertSame(expected: 'amsterdam', actual: $service->resolveTokenSetForRequest());
	}//end testResolveReturnsEarliestEntryForMultiGroupUser()

	/**
	 * An entry mapping to a deleted token set is skipped and resolution
	 * continues to the next entry.
	 *
	 * @return void
	 */
	public function testResolveSkipsDeadSetEntryAndContinues(): void {
		$config = $this->fakeConfig(
			initial: [
				'group_token_sets' => json_encode(
					[
						['group' => 'gemeente-a', 'tokenSet' => 'custom-gone'],
						['group' => 'gemeente-a-fallbackgroup', 'tokenSet' => 'utrecht'],
					]
				),
				'token_set' => 'nextcloud',
			]
		);

		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn(['gemeente-a', 'gemeente-a-fallbackgroup']);

		$userSession = $this->createMock(originalClassName: IUserSession::class);
		$userSession->method('getUser')->willReturn($this->fakeUser(uid: 'dana'));

		// "custom-gone" is not in the available set — dead entry.
		[$service] = $this->buildService(
			config: $config,
			groupManager: $groupManager,
			userSession: $userSession,
			tokenSetService: $this->fakeTokenSetService(available: ['utrecht'])
		);

		$this->assertSame(expected: 'utrecht', actual: $service->resolveTokenSetForRequest());
	}//end testResolveSkipsDeadSetEntryAndContinues()

	/**
	 * A user matching ONLY a dead-set entry resolves to the instance default.
	 *
	 * @return void
	 */
	public function testResolveDeadSetOnlyMatchFallsBackToDefault(): void {
		$config = $this->fakeConfig(
			initial: [
				'group_token_sets' => json_encode([['group' => 'gemeente-a', 'tokenSet' => 'custom-gone']]),
				'token_set' => 'rijkshuisstijl',
			]
		);

		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn(['gemeente-a']);

		$userSession = $this->createMock(originalClassName: IUserSession::class);
		$userSession->method('getUser')->willReturn($this->fakeUser(uid: 'erik'));

		[$service] = $this->buildService(
			config: $config,
			groupManager: $groupManager,
			userSession: $userSession,
			tokenSetService: $this->fakeTokenSetService(available: [])
		);

		$this->assertSame(expected: 'rijkshuisstijl', actual: $service->resolveTokenSetForRequest());
	}//end testResolveDeadSetOnlyMatchFallsBackToDefault()

	/**
	 * A user in no mapped group gets the instance default.
	 *
	 * @return void
	 */
	public function testResolveReturnsDefaultForUnmappedUser(): void {
		$config = $this->fakeConfig(
			initial: [
				'group_token_sets' => json_encode([['group' => 'gemeente-a', 'tokenSet' => 'amsterdam']]),
				'token_set' => 'nextcloud',
			]
		);

		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn(['some-other-group']);

		$userSession = $this->createMock(originalClassName: IUserSession::class);
		$userSession->method('getUser')->willReturn($this->fakeUser(uid: 'carol'));

		[$service] = $this->buildService(
			config: $config,
			groupManager: $groupManager,
			userSession: $userSession,
			tokenSetService: $this->fakeTokenSetService(available: ['amsterdam'])
		);

		$this->assertSame(expected: 'nextcloud', actual: $service->resolveTokenSetForRequest());
	}//end testResolveReturnsDefaultForUnmappedUser()

	/**
	 * No session (login/public/error pages) resolves to the instance
	 * default WITHOUT any group-manager call.
	 *
	 * @return void
	 */
	public function testResolveReturnsDefaultForNoSessionWithoutGroupLookup(): void {
		$config = $this->fakeConfig(
			initial: [
				'group_token_sets' => json_encode([['group' => 'gemeente-a', 'tokenSet' => 'amsterdam']]),
				'token_set' => 'nextcloud',
			]
		);

		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->expects($this->never())->method('getUserGroupIds');

		$userSession = $this->createMock(originalClassName: IUserSession::class);
		$userSession->method('getUser')->willReturn(null);

		[$service] = $this->buildService(
			config: $config,
			groupManager: $groupManager,
			userSession: $userSession,
			tokenSetService: $this->fakeTokenSetService(available: ['amsterdam'])
		);

		$this->assertSame(expected: 'nextcloud', actual: $service->resolveTokenSetForRequest());
	}//end testResolveReturnsDefaultForNoSessionWithoutGroupLookup()

	/**
	 * An empty mapping short-circuits before any cache or group access —
	 * preserves today's zero-lookup behavior exactly.
	 *
	 * @return void
	 */
	public function testResolveReturnsDefaultForEmptyMappingWithoutGroupOrCacheAccess(): void {
		$config = $this->fakeConfig(initial: ['token_set' => 'nextcloud']);

		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->expects($this->never())->method('getUserGroupIds');

		$userSession = $this->createMock(originalClassName: IUserSession::class);
		$userSession->method('getUser')->willReturn($this->fakeUser(uid: 'frank'));

		$cache = $this->createMock(originalClassName: ICache::class);
		$cache->expects($this->never())->method('get');
		$cache->expects($this->never())->method('set');
		$cacheFactory = $this->createMock(originalClassName: ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($cache);

		$service = new GroupThemingService(
			config: $config,
			groupManager: $groupManager,
			userSession: $userSession,
			tokenSetService: $this->fakeTokenSetService(available: []),
			previewService: $this->inactivePreviewService(),
			cacheFactory: $cacheFactory
		);

		$this->assertSame(expected: 'nextcloud', actual: $service->resolveTokenSetForRequest());
	}//end testResolveReturnsDefaultForEmptyMappingWithoutGroupOrCacheAccess()

	/**
	 * A group-backend exception fails open to the instance default; no
	 * exception escapes.
	 *
	 * @return void
	 */
	public function testResolveFailsOpenOnGroupBackendException(): void {
		$config = $this->fakeConfig(
			initial: [
				'group_token_sets' => json_encode([['group' => 'gemeente-a', 'tokenSet' => 'amsterdam']]),
				'token_set' => 'nextcloud',
			]
		);

		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willThrowException(new \RuntimeException('backend down'));

		$userSession = $this->createMock(originalClassName: IUserSession::class);
		$userSession->method('getUser')->willReturn($this->fakeUser(uid: 'greta'));

		[$service] = $this->buildService(
			config: $config,
			groupManager: $groupManager,
			userSession: $userSession,
			tokenSetService: $this->fakeTokenSetService(available: ['amsterdam'])
		);

		$this->assertSame(expected: 'nextcloud', actual: $service->resolveTokenSetForRequest());
	}//end testResolveFailsOpenOnGroupBackendException()

	// -------------------------------------------------------------------
	// Caching
	// -------------------------------------------------------------------

	/**
	 * A second resolve under the same mapping generation hits the cache —
	 * IGroupManager is queried exactly once across both calls.
	 *
	 * @return void
	 */
	public function testSecondResolveHitsCacheWithoutGroupLookup(): void {
		$config = $this->fakeConfig(
			initial: [
				'group_token_sets' => json_encode([['group' => 'gemeente-a', 'tokenSet' => 'amsterdam']]),
				'token_set' => 'nextcloud',
			]
		);

		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->expects($this->once())->method('getUserGroupIds')->willReturn(['gemeente-a']);

		$userSession = $this->createMock(originalClassName: IUserSession::class);
		$userSession->method('getUser')->willReturn($this->fakeUser(uid: 'anna'));

		[$service] = $this->buildService(
			config: $config,
			groupManager: $groupManager,
			userSession: $userSession,
			tokenSetService: $this->fakeTokenSetService(available: ['amsterdam'])
		);

		$this->assertSame(expected: 'amsterdam', actual: $service->resolveTokenSetForRequest());
		$this->assertSame(expected: 'amsterdam', actual: $service->resolveTokenSetForRequest());
	}//end testSecondResolveHitsCacheWithoutGroupLookup()

	/**
	 * Saving a changed mapping bumps the generation, invalidating every
	 * cached resolution: the very next resolve re-queries group membership.
	 *
	 * @return void
	 */
	public function testGenerationBumpInvalidatesCache(): void {
		$config = $this->fakeConfig(initial: ['token_set' => 'nextcloud']);

		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('groupExists')->willReturn(true);
		$groupManager->expects($this->exactly(count: 2))->method('getUserGroupIds')->willReturn(['gemeente-a']);

		$userSession = $this->createMock(originalClassName: IUserSession::class);
		$userSession->method('getUser')->willReturn($this->fakeUser(uid: 'anna'));

		$tokenSetService = $this->fakeTokenSetService(available: ['amsterdam', 'utrecht']);

		[$service] = $this->buildService(config: $config, groupManager: $groupManager, userSession: $userSession, tokenSetService: $tokenSetService);

		$service->setMapping([['group' => 'gemeente-a', 'tokenSet' => 'amsterdam']]);
		$this->assertSame(expected: 'amsterdam', actual: $service->resolveTokenSetForRequest());
		// Cached — a same-generation resolve would not re-query, but we
		// change the mapping (bumping the generation) before resolving again.
		$service->setMapping([['group' => 'gemeente-a', 'tokenSet' => 'utrecht']]);
		$this->assertSame(expected: 'utrecht', actual: $service->resolveTokenSetForRequest());
	}//end testGenerationBumpInvalidatesCache()

	/**
	 * The cache write carries the documented TTL backstop (<= 1 hour).
	 *
	 * @return void
	 */
	public function testCacheWriteCarriesTtlBound(): void {
		$config = $this->fakeConfig(
			initial: [
				'group_token_sets' => json_encode([['group' => 'gemeente-a', 'tokenSet' => 'amsterdam']]),
				'token_set' => 'nextcloud',
			]
		);

		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn(['gemeente-a']);

		$userSession = $this->createMock(originalClassName: IUserSession::class);
		$userSession->method('getUser')->willReturn($this->fakeUser(uid: 'anna'));

		$cache = $this->createMock(originalClassName: ICache::class);
		$cache->method('get')->willReturn(null);
		$capturedTtl = null;
		$cache->expects($this->once())->method('set')->willReturnCallback(
			function ($key, $value, $ttl = 0) use (&$capturedTtl) {
				$capturedTtl = $ttl;
				return true;
			}
		);
		$cacheFactory = $this->createMock(originalClassName: ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($cache);

		$service = new GroupThemingService(
			config: $config,
			groupManager: $groupManager,
			userSession: $userSession,
			tokenSetService: $this->fakeTokenSetService(available: ['amsterdam']),
			previewService: $this->inactivePreviewService(),
			cacheFactory: $cacheFactory
		);

		$service->resolveTokenSetForRequest();

		$this->assertSame(expected: 3600, actual: $capturedTtl);
		$this->assertLessThanOrEqual(expected: 3600, actual: $capturedTtl);
	}//end testCacheWriteCarriesTtlBound()

	// -------------------------------------------------------------------
	// Available groups
	// -------------------------------------------------------------------

	/**
	 * getAvailableGroups() maps IGroup entries to {id, displayName}, sorted
	 * by display name.
	 *
	 * @return void
	 */
	public function testGetAvailableGroupsListsAndSorts(): void {
		$groupB = $this->createMock(originalClassName: \OCP\IGroup::class);
		$groupB->method('getGID')->willReturn('gemeente-b');
		$groupB->method('getDisplayName')->willReturn('Zeeburgstad');

		$groupA = $this->createMock(originalClassName: \OCP\IGroup::class);
		$groupA->method('getGID')->willReturn('gemeente-a');
		$groupA->method('getDisplayName')->willReturn('Amsteldam');

		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('search')->willReturn([$groupB, $groupA]);

		[$service] = $this->buildService(
			config: $this->fakeConfig(),
			groupManager: $groupManager,
			userSession: $this->createMock(originalClassName: IUserSession::class),
			tokenSetService: $this->fakeTokenSetService(available: [])
		);

		$this->assertSame(
			expected: [
				['id' => 'gemeente-a', 'displayName' => 'Amsteldam'],
				['id' => 'gemeente-b', 'displayName' => 'Zeeburgstad'],
			],
			actual: $service->getAvailableGroups()
		);
	}//end testGetAvailableGroupsListsAndSorts()
}//end class
