<?php

/**
 * Unit tests for the public theming Capabilities class.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/archive/2026-07-23-theming-capability-api/tasks.md#task-3.1
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Capabilities;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\TokenSetService;
use OCP\App\IAppManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Covers tasks.md#task-3.1: full payload shape, default config, custom/unknown
 * degrade, WCAG cache behaviour, and exception-swallowing.
 */
class CapabilitiesTest extends TestCase {

	/**
	 * The eight-key allowlist the payload MUST match exactly.
	 *
	 * @var array<int, string>
	 */
	private const ALLOWED_KEYS = [
		'version',
		'tokenSet',
		'designSystem',
		'iconPacks',
		'wcagLevel',
		'logos',
		'hideSlogan',
		'showMenuLabels',
	];

	/**
	 * In-memory fake backing an ICache mock so get()/set() persist across
	 * sequential getCapabilities() calls within one test.
	 *
	 * @param IConfig $config
	 * @param IAppManager|\PHPUnit\Framework\MockObject\MockObject $appManager
	 * @param IURLGenerator|\PHPUnit\Framework\MockObject\MockObject $urlGenerator
	 * @param DesignSystemService|\PHPUnit\Framework\MockObject\MockObject $designSystemService
	 * @param TokenSetService|\PHPUnit\Framework\MockObject\MockObject $tokenSetService
	 * @param ShippedTokenSetAuditService|\PHPUnit\Framework\MockObject\MockObject $auditService
	 *
	 * @return Capabilities
	 */
	private function buildCapabilities(
		IConfig $config,
		IAppManager $appManager,
		IURLGenerator $urlGenerator,
		DesignSystemService $designSystemService,
		TokenSetService $tokenSetService,
		ShippedTokenSetAuditService $auditService,
	): Capabilities {
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

		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($cache);

		return new Capabilities(
			$config,
			$appManager,
			$urlGenerator,
			$designSystemService,
			$tokenSetService,
			$auditService,
			$cacheFactory
		);
	}//end buildCapabilities()

	/**
	 * Build an IConfig mock returning fixed values for the three appconfig
	 * reads Capabilities performs, falling back to the caller-supplied default
	 * for any other key (mirrors real getAppValue() semantics).
	 *
	 * @param array<string, string> $values Key => value overrides.
	 *
	 * @return IConfig&\PHPUnit\Framework\MockObject\MockObject
	 */
	private function configWith(array $values): IConfig {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $appName, string $key, $default = '') => ($values[$key] ?? $default)
		);

		return $config;
	}//end configWith()

	/**
	 * Full payload shape + key allowlist for an active shipped set.
	 */
	public function testFullPayloadShapeForShippedSet(): void {
		$config = $this->configWith(
			[
				'token_set' => 'rijkshuisstijl',
				'hide_slogan' => '1',
				'show_menu_labels' => '0',
			]
		);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppVersion')->willReturn('3.4.0');
		$appManager->method('getAppPath')->willReturn('/app');

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('imagePath')->with(Application::APP_ID, 'logos/rijkshuisstijl.svg')
			->willReturn('https://cloud.example/apps/thematiq/img/logos/rijkshuisstijl.svg');

		$designSystemService = $this->createMock(DesignSystemService::class);
		$designSystemService->method('getTokenSetMeta')->with('rijkshuisstijl')->willReturn(
			[
				'id' => 'rijkshuisstijl',
				'design_system' => 'nldesign',
				'theming' => ['logo' => 'img/logos/rijkshuisstijl.svg'],
			]
		);
		$designSystemService->method('resolveActiveIconPacks')->with('rijkshuisstijl')->willReturn(
			['rvo', 'open-gemeenten', 'den-haag']
		);

		$tokenSetService = $this->createMock(TokenSetService::class);
		$tokenSetService->method('getTokenSetSummary')->willReturn(
			['name' => 'Rijkshuisstijl', 'upstreamVersion' => null]
		);
		// Core renders capabilities into every page. The full discovery runs
		// three audits per set (~1.5 s for 61 sets, measured 2026-10-06), so
		// the capability must never reach it.
		$tokenSetService->expects($this->never())->method('getAvailableTokenSets');

		$auditService = $this->createMock(ShippedTokenSetAuditService::class);
		$auditService->method('auditSet')->willReturn(['verdict' => 'pass']);

		$capabilities = $this->buildCapabilities($config, $appManager, $urlGenerator, $designSystemService, $tokenSetService, $auditService);
		$payload = $capabilities->getCapabilities();

		$this->assertArrayHasKey('nldesign', $payload);
		$nldesign = $payload['nldesign'];

		$this->assertSame(self::ALLOWED_KEYS, array_keys($nldesign), 'Payload must contain exactly the eight allowlisted keys, in shape.');
		$this->assertSame('3.4.0', $nldesign['version']);
		$this->assertSame(['id' => 'rijkshuisstijl', 'name' => 'Rijkshuisstijl', 'version' => null], $nldesign['tokenSet']);
		$this->assertSame('nldesign', $nldesign['designSystem']);
		$this->assertSame(['rvo', 'open-gemeenten', 'den-haag'], $nldesign['iconPacks']);
		$this->assertSame('https://cloud.example/apps/thematiq/img/logos/rijkshuisstijl.svg', $nldesign['logos']['default']);
		$this->assertTrue($nldesign['hideSlogan']);
		$this->assertFalse($nldesign['showMenuLabels']);
		$this->assertSame('AA', $nldesign['wcagLevel']);
	}//end testFullPayloadShapeForShippedSet()

	/**
	 * No `token_set` appconfig value → stock Nextcloud defaults, no audit call.
	 */
	public function testDefaultConfigNoTokenSet(): void {
		$config = $this->configWith([]);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppVersion')->willReturn('3.4.0');
		$appManager->method('getAppPath')->willReturn('/app');

		$urlGenerator = $this->createMock(IURLGenerator::class);

		$designSystemService = $this->createMock(DesignSystemService::class);
		$designSystemService->method('getTokenSetMeta')->with('nextcloud')->willReturn(
			[
				'id' => 'nextcloud',
				'design_system' => 'none',
				'theming' => ['primary_color' => '#0082c9', 'background_color' => '#FFFFFF'],
			]
		);
		$designSystemService->method('resolveActiveIconPacks')->with('nextcloud')->willReturn([]);

		$tokenSetService = $this->createMock(TokenSetService::class);
		$tokenSetService->method('getTokenSetSummary')->willReturn(
			['name' => 'Nextcloud (default)', 'upstreamVersion' => null]
		);

		$auditService = $this->createMock(ShippedTokenSetAuditService::class);
		$auditService->expects($this->never())->method('auditSet');

		$capabilities = $this->buildCapabilities($config, $appManager, $urlGenerator, $designSystemService, $tokenSetService, $auditService);
		$nldesign = $capabilities->getCapabilities()['nldesign'];

		$this->assertSame('nextcloud', $nldesign['tokenSet']['id']);
		$this->assertSame('none', $nldesign['designSystem']);
		$this->assertSame([], $nldesign['iconPacks']);
		$this->assertEquals(new \stdClass(), $nldesign['logos']);
		$this->assertSame('{}', json_encode($nldesign['logos']), 'Empty logos must serialize as a JSON object, never an array.');
		$this->assertNull($nldesign['wcagLevel']);
		$this->assertFalse($nldesign['hideSlogan']);
		$this->assertFalse($nldesign['showMenuLabels']);
	}//end testDefaultConfigNoTokenSet()

	/**
	 * A `lasuite`-active instance advertises the resolved `["dsfr"]` pack on
	 * the public capability (`openspec/specs/icon-packs/spec.md`).
	 */
	public function testLasuiteActiveInstanceAdvertisesDsfrPack(): void {
		$config = $this->configWith(['token_set' => 'lasuite']);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppVersion')->willReturn('3.4.0');
		$appManager->method('getAppPath')->willReturn('/app');

		$urlGenerator = $this->createMock(IURLGenerator::class);

		$designSystemService = $this->createMock(DesignSystemService::class);
		$designSystemService->method('getTokenSetMeta')->with('lasuite')->willReturn(
			['id' => 'lasuite', 'design_system' => 'lasuite', 'theming' => []]
		);
		$designSystemService->method('resolveActiveIconPacks')->with('lasuite')->willReturn(['dsfr']);

		$tokenSetService = $this->createMock(TokenSetService::class);
		$tokenSetService->method('getTokenSetSummary')->willReturn(
			['name' => 'La Suite numérique', 'upstreamVersion' => null]
		);

		$auditService = $this->createMock(ShippedTokenSetAuditService::class);
		$auditService->method('auditSet')->willReturn(['verdict' => 'pass']);

		$capabilities = $this->buildCapabilities($config, $appManager, $urlGenerator, $designSystemService, $tokenSetService, $auditService);
		$nldesign = $capabilities->getCapabilities()['nldesign'];

		$this->assertSame('lasuite', $nldesign['designSystem']);
		$this->assertSame(['dsfr'], $nldesign['iconPacks']);
	}//end testLasuiteActiveInstanceAdvertisesDsfrPack()

	/**
	 * A custom/unknown token set id degrades name/version/wcagLevel, never lies.
	 */
	public function testCustomOrUnknownTokenSetDegradesButNeverLies(): void {
		$config = $this->configWith(['token_set' => 'custom-onbekend']);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppVersion')->willReturn('3.4.0');
		$appManager->method('getAppPath')->willReturn('/app');

		$urlGenerator = $this->createMock(IURLGenerator::class);

		$designSystemService = $this->createMock(DesignSystemService::class);
		// Not in token-sets.json (shipped manifest) — empty array, matching
		// DesignSystemService::getTokenSetMeta()'s real not-found return value.
		$designSystemService->method('getTokenSetMeta')->with('custom-onbekend')->willReturn([]);
		$designSystemService->method('resolveActiveIconPacks')->with('custom-onbekend')->willReturn([]);

		$tokenSetService = $this->createMock(TokenSetService::class);
		// Not discovered on disk either — absent from the available-sets list.
		$tokenSetService->method('getTokenSetSummary')->willReturn(null);

		$auditService = $this->createMock(ShippedTokenSetAuditService::class);
		$auditService->expects($this->never())->method('auditSet');

		$capabilities = $this->buildCapabilities($config, $appManager, $urlGenerator, $designSystemService, $tokenSetService, $auditService);
		$nldesign = $capabilities->getCapabilities()['nldesign'];

		$this->assertSame('custom-onbekend', $nldesign['tokenSet']['id']);
		$this->assertSame('custom-onbekend', $nldesign['tokenSet']['name']);
		$this->assertNull($nldesign['tokenSet']['version']);
		$this->assertNull($nldesign['wcagLevel']);
	}//end testCustomOrUnknownTokenSetDegradesButNeverLies()

	/**
	 * The WCAG level is cached — a second call within the TTL never re-invokes
	 * the audit service, and both calls report the same level.
	 */
	public function testWcagLevelIsCachedAcrossCalls(): void {
		$config = $this->configWith(['token_set' => 'rijkshuisstijl']);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppVersion')->willReturn('3.4.0');
		$appManager->method('getAppPath')->willReturn('/app');

		$urlGenerator = $this->createMock(IURLGenerator::class);

		$designSystemService = $this->createMock(DesignSystemService::class);
		$designSystemService->method('getTokenSetMeta')->willReturn(
			['id' => 'rijkshuisstijl', 'design_system' => 'nldesign', 'theming' => []]
		);
		$designSystemService->method('resolveActiveIconPacks')->willReturn(['rvo', 'open-gemeenten', 'den-haag']);

		$tokenSetService = $this->createMock(TokenSetService::class);
		$tokenSetService->method('getTokenSetSummary')->willReturn(
			['name' => 'Rijkshuisstijl', 'upstreamVersion' => null]
		);

		$auditService = $this->createMock(ShippedTokenSetAuditService::class);
		$auditService->expects($this->once())->method('auditSet')->willReturn(['verdict' => 'pass']);

		$capabilities = $this->buildCapabilities($config, $appManager, $urlGenerator, $designSystemService, $tokenSetService, $auditService);

		$first = $capabilities->getCapabilities()['nldesign']['wcagLevel'];
		$second = $capabilities->getCapabilities()['nldesign']['wcagLevel'];

		$this->assertSame('AA', $first);
		$this->assertSame($first, $second);
	}//end testWcagLevelIsCachedAcrossCalls()

	/**
	 * A high-contrast set declaring `contrast_level: AAA` and passing AAA
	 * thresholds reports `AAA`.
	 */
	public function testHighContrastSetReportsAaa(): void {
		$config = $this->configWith(['token_set' => 'hoog-contrast']);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppVersion')->willReturn('3.4.0');
		$appManager->method('getAppPath')->willReturn('/app');

		$urlGenerator = $this->createMock(IURLGenerator::class);

		$designSystemService = $this->createMock(DesignSystemService::class);
		$designSystemService->method('getTokenSetMeta')->willReturn(
			['id' => 'hoog-contrast', 'design_system' => 'high-contrast', 'contrast_level' => 'AAA', 'theming' => []]
		);
		$designSystemService->method('resolveActiveIconPacks')->willReturn([]);

		$tokenSetService = $this->createMock(TokenSetService::class);
		$tokenSetService->method('getTokenSetSummary')->willReturn(
			['name' => 'Hoog Contrast', 'upstreamVersion' => null]
		);

		$auditService = $this->createMock(ShippedTokenSetAuditService::class);
		$auditService->method('auditSet')->willReturn(['verdict' => 'pass']);

		$capabilities = $this->buildCapabilities($config, $appManager, $urlGenerator, $designSystemService, $tokenSetService, $auditService);
		$nldesign = $capabilities->getCapabilities()['nldesign'];

		$this->assertSame('AAA', $nldesign['wcagLevel']);
	}//end testHighContrastSetReportsAaa()

	/**
	 * A shipped set failing its audit reports `fail`, never a fabricated pass.
	 */
	public function testFailingShippedSetReportsFail(): void {
		$config = $this->configWith(['token_set' => 'noaberkracht']);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppVersion')->willReturn('3.4.0');
		$appManager->method('getAppPath')->willReturn('/app');

		$urlGenerator = $this->createMock(IURLGenerator::class);

		$designSystemService = $this->createMock(DesignSystemService::class);
		$designSystemService->method('getTokenSetMeta')->willReturn(
			['id' => 'noaberkracht', 'design_system' => 'nldesign', 'theming' => []]
		);
		$designSystemService->method('resolveActiveIconPacks')->willReturn(['rvo', 'open-gemeenten', 'den-haag']);

		$tokenSetService = $this->createMock(TokenSetService::class);
		$tokenSetService->method('getTokenSetSummary')->willReturn(
			['name' => 'Noaberkracht', 'upstreamVersion' => null]
		);

		$auditService = $this->createMock(ShippedTokenSetAuditService::class);
		$auditService->method('auditSet')->willReturn(['verdict' => 'fail']);

		$capabilities = $this->buildCapabilities($config, $appManager, $urlGenerator, $designSystemService, $tokenSetService, $auditService);
		$nldesign = $capabilities->getCapabilities()['nldesign'];

		$this->assertSame('fail', $nldesign['wcagLevel']);
	}//end testFailingShippedSetReportsFail()

	/**
	 * A throwing injected dependency degrades to the minimal payload — no
	 * exception escapes getCapabilities(), and no exception detail leaks.
	 */
	public function testThrowingDependencyDegradesGracefully(): void {
		$config = $this->configWith(['token_set' => 'rijkshuisstijl']);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppVersion')->willReturn('3.4.0');

		$urlGenerator = $this->createMock(IURLGenerator::class);

		$designSystemService = $this->createMock(DesignSystemService::class);
		$designSystemService->method('getTokenSetMeta')->willThrowException(new \RuntimeException('manifest unreadable: /secret/path'));

		$tokenSetService = $this->createMock(TokenSetService::class);
		$auditService = $this->createMock(ShippedTokenSetAuditService::class);
		$auditService->expects($this->never())->method('auditSet');

		$capabilities = $this->buildCapabilities($config, $appManager, $urlGenerator, $designSystemService, $tokenSetService, $auditService);

		$payload = $capabilities->getCapabilities();

		$this->assertArrayHasKey('nldesign', $payload);
		$nldesign = $payload['nldesign'];

		$this->assertSame(self::ALLOWED_KEYS, array_keys($nldesign));
		$this->assertSame('3.4.0', $nldesign['version']);
		$this->assertSame('rijkshuisstijl', $nldesign['tokenSet']['id']);
		$this->assertSame('rijkshuisstijl', $nldesign['tokenSet']['name']);
		$this->assertNull($nldesign['tokenSet']['version']);
		$this->assertNull($nldesign['designSystem']);
		$this->assertSame([], $nldesign['iconPacks']);
		$this->assertNull($nldesign['wcagLevel']);
		$this->assertEquals(new \stdClass(), $nldesign['logos']);
		$this->assertFalse($nldesign['hideSlogan']);
		$this->assertFalse($nldesign['showMenuLabels']);

		$encoded = (string)json_encode($payload);
		$this->assertStringNotContainsString('secret', $encoded, 'No exception detail must leak into the payload.');
	}//end testThrowingDependencyDegradesGracefully()
}//end class
