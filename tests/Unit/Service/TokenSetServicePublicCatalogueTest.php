<?php

/**
 * Unit tests for TokenSetService::getPublicCatalogue(): the closed 5-field
 * projection and the wcagLevel cache-sharing contract with Capabilities.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/app-token-set-selection/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Capabilities;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\TokenSetVocabularyAuditService;
use OCP\App\IAppManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers spec.md's "wcagLevel matches the active-set capability computation"
 * scenario: `GET /api/token-sets`' `wcagLevel` for a set that is also the
 * active theme MUST equal `Capabilities`' own computed value for the same
 * set id, and the underlying audit MUST be computed at most once per set id
 * per cache TTL window (the shared `ICache` prefix `thematiq_wcag_level`).
 */
class TokenSetServicePublicCatalogueTest extends TestCase {

	/**
	 * Repository root, derived from this test file's location — used as the
	 * app path so real `token-sets.json`/`css/tokens/` discovery runs
	 * unmocked; only the audit VERDICT itself is stubbed (below) so the test
	 * does not depend on hand-computed contrast ratios.
	 */
	private function repoRoot(): string {
		return \dirname(__DIR__, 3);
	}//end repoRoot()

	/**
	 * An in-memory ICache mock backed by a shared array — the SAME instance
	 * is handed to both Capabilities and TokenSetService via their own
	 * ICacheFactory mocks, so `get()`/`set()` calls made by one are visible
	 * to the other, exactly as the real distributed cache would behave.
	 */
	private function sharedCache(): ICache {
		$store = [];
		$cache = $this->createMock(ICache::class);
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

		return $cache;
	}//end sharedCache()

	/**
	 * `getPublicCatalogue()`'s `wcagLevel` for `rijkshuisstijl` MUST equal
	 * `Capabilities`' own computed `wcagLevel` when `rijkshuisstijl` is the
	 * active set, AND the underlying `ShippedTokenSetAuditService::auditSet()`
	 * MUST be invoked at most once across both callers — proving the two
	 * consumers genuinely share one `ICache` entry rather than each
	 * computing (and potentially disagreeing on) the audit independently.
	 */
	public function testWcagLevelMatchesCapabilitiesAndAuditRunsOnce(): void {
		$appPath = $this->repoRoot();

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($appPath);
		$appManager->method('getAppVersion')->willReturn('3.4.0');

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $appName, string $key, $default = '') => match ($key) {
				'token_set' => 'rijkshuisstijl',
				default => $default,
			}
		);

		$cache = $this->sharedCache();
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturnCallback(static fn (string $prefix): ICache => $cache);

		// Partial mock: only `auditSet()` is stubbed (a fixed, cheap
		// verdict) so this test proves cache-sharing behaviour rather than
		// re-asserting the contrast math (already covered by
		// TokenSetContrastAuditTest). `computeCachedWcagLevel()` runs for
		// real, exercising the actual caching/branching logic. Note:
		// TokenSetService::getPublicCatalogue() computes wcagLevel for
		// EVERY discovered set (not just "rijkshuisstijl"), so auditSet()
		// legitimately runs once per uncached, non-"none"-design-system set
		// id across the whole catalogue sweep — this test tracks the call
		// count for "rijkshuisstijl" specifically, not the global total.
		$rijkshuisstijlAuditCalls = 0;
		$auditService = $this->getMockBuilder(ShippedTokenSetAuditService::class)
			->setConstructorArgs([new ContrastService(), new CssParserService()])
			->onlyMethods(['auditSet'])
			->getMock();
		$auditService->method('auditSet')->willReturnCallback(
			function (string $appPathArg, string $id, array $theming, string $level = 'AA') use (&$rijkshuisstijlAuditCalls) {
				if ($id === 'rijkshuisstijl') {
					$rijkshuisstijlAuditCalls++;
				}

				return [
					'id' => $id,
					'textRatio' => 8.99,
					'uiRatio' => 8.99,
					'textThreshold' => 4.5,
					'uiThreshold' => 3.0,
					'verdict' => 'pass',
				];
			}
		);

		$tokenSetService = new TokenSetService(
			$appManager,
			$config,
			$this->createMock(LoggerInterface::class),
			$auditService,
			$cacheFactory,
			new TokenSetVocabularyAuditService(new CssParserService())
		);

		$designSystemService = $this->createMock(DesignSystemService::class);
		$designSystemService->method('getTokenSetMeta')->with('rijkshuisstijl')->willReturn(
			[
				'id' => 'rijkshuisstijl',
				'design_system' => 'nldesign',
				'theming' => ['logo' => 'img/logos/rijkshuisstijl.svg'],
			]
		);

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('imagePath')->willReturn('https://cloud.example/apps/thematiq/img/logos/rijkshuisstijl.svg');

		$capabilities = new Capabilities(
			$config,
			$appManager,
			$urlGenerator,
			$designSystemService,
			$tokenSetService,
			$auditService,
			$cacheFactory
		);

		// Capabilities computes + caches "rijkshuisstijl" first.
		$capabilitiesLevel = $capabilities->getCapabilities()['nldesign']['wcagLevel'];
		$this->assertSame(1, $rijkshuisstijlAuditCalls, 'Capabilities must have triggered exactly one audit for "rijkshuisstijl".');

		// The catalogue projection must read the SAME cached value for the
		// same set id — the "rijkshuisstijl" audit counter must NOT
		// increment a second time here, proving the two consumers share one
		// ICache entry rather than each computing independently.
		$catalogue = $tokenSetService->getPublicCatalogue();
		$byId = array_column($catalogue, null, 'id');

		$this->assertArrayHasKey('rijkshuisstijl', $byId);
		$this->assertSame('AA', $capabilitiesLevel);
		$this->assertSame($capabilitiesLevel, $byId['rijkshuisstijl']['wcagLevel']);
		$this->assertSame(
			1,
			$rijkshuisstijlAuditCalls,
			'auditSet() must still have been called exactly once for "rijkshuisstijl" after the catalogue read — proving cache-sharing, not a second independent computation.'
		);
	}//end testWcagLevelMatchesCapabilitiesAndAuditRunsOnce()

	/**
	 * The public catalogue entry shape is exactly the closed 5 fields, with
	 * `theming` further closed to `primary_color`/`background_color`/
	 * `logo?` — no `description`, `custom`, `warnings`, `upstreamVersion`,
	 * or `upstreamRef` leakage from the discovery entry.
	 */
	public function testCatalogueEntryShapeIsClosed(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->repoRoot());

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn('{}');

		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->sharedCache());

		$auditService = new ShippedTokenSetAuditService(new ContrastService(), new CssParserService());

		$tokenSetService = new TokenSetService(
			$appManager,
			$config,
			$this->createMock(LoggerInterface::class),
			$auditService,
			$cacheFactory,
			new TokenSetVocabularyAuditService(new CssParserService())
		);

		$catalogue = $tokenSetService->getPublicCatalogue();
		$this->assertNotEmpty($catalogue);

		foreach ($catalogue as $entry) {
			$this->assertSame(['id', 'name', 'design_system', 'theming', 'wcagLevel'], array_keys($entry));
			$this->assertContains(
				array_keys($entry['theming']),
				[
					['primary_color', 'background_color'],
					['primary_color', 'background_color', 'logo'],
				],
				'theming for "' . $entry['id'] . '" must be exactly primary_color/background_color(/logo).'
			);
		}
	}//end testCatalogueEntryShapeIsClosed()

	/**
	 * A set without a declared logo omits `theming.logo` entirely (never
	 * `null`) — matching `TokenSetEntry`'s existing optionality.
	 */
	public function testLogoIsOmittedNotNullWhenAbsent(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->repoRoot());

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn('{}');

		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->sharedCache());

		$auditService = new ShippedTokenSetAuditService(new ContrastService(), new CssParserService());

		$tokenSetService = new TokenSetService(
			$appManager,
			$config,
			$this->createMock(LoggerInterface::class),
			$auditService,
			$cacheFactory,
			new TokenSetVocabularyAuditService(new CssParserService())
		);

		$byId = array_column($tokenSetService->getPublicCatalogue(), null, 'id');

		// "nextcloud" ships with no theming.logo in token-sets.json.
		$this->assertArrayHasKey('nextcloud', $byId);
		$this->assertArrayNotHasKey('logo', $byId['nextcloud']['theming']);
	}//end testLogoIsOmittedNotNullWhenAbsent()
}//end class
