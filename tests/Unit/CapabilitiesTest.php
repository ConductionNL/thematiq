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
	 * @param ShippedTokenSetAuditService|\PHPUnit\Framework\MockObject\MockObject $auditService
	 *
	 * @return Capabilities
	 */
	private function buildCapabilities(
		IConfig $config,
		IAppManager $appManager,
		IURLGenerator $urlGenerator,
		DesignSystemService $designSystemService,
		ShippedTokenSetAuditService $auditService,
	): Capabilities {
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

		return new Capabilities(
			config: $config,
			appManager: $appManager,
			urlGenerator: $urlGenerator,
			designSystemService: $designSystemService,
			auditService: $auditService,
			cacheFactory: $cacheFactory
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
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $appName, string $key, $default = '') => ($values[$key] ?? $default)
		);

		return $config;
	}//end configWith()

	/**
	 * Full payload shape + key allowlist for an active shipped set.
	 *
	 * @return void
	 */
	public function testFullPayloadShapeForShippedSet(): void {
		$config = $this->configWith(
			values: [
				'token_set' => 'rijkshuisstijl',
				'hide_slogan' => '1',
				'show_menu_labels' => '0',
			]
		);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppVersion')->willReturn('3.4.0');
		$appManager->method('getAppPath')->willReturn('/app');

		$urlGenerator = $this->createMock(originalClassName: IURLGenerator::class);
		$urlGenerator->method('imagePath')->with(Application::APP_ID, 'logos/rijkshuisstijl.svg')
			->willReturn('https://cloud.example/apps/thematiq/img/logos/rijkshuisstijl.svg');

		$designSystemService = $this->createMock(originalClassName: DesignSystemService::class);
		$designSystemService->method('getTokenSetMeta')->with('rijkshuisstijl')->willReturn(
			[
				'id' => 'rijkshuisstijl',
				'name' => 'Rijkshuisstijl',
				'design_system' => 'nldesign',
				'theming' => ['logo' => 'img/logos/rijkshuisstijl.svg'],
			]
		);
		$designSystemService->method('resolveActiveIconPacks')->with('rijkshuisstijl')->willReturn(
			['rvo', 'open-gemeenten', 'den-haag']
		);

		$auditService = $this->createMock(originalClassName: ShippedTokenSetAuditService::class);
		$auditService->method('auditSet')->willReturn(['verdict' => 'pass']);

		$capabilities = $this->buildCapabilities(config: $config, appManager: $appManager, urlGenerator: $urlGenerator, designSystemService: $designSystemService, auditService: $auditService);
		$payload = $capabilities->getCapabilities();

		$this->assertArrayHasKey(key: 'nldesign', array: $payload);
		$nldesign = $payload['nldesign'];

		$this->assertSame(expected: self::ALLOWED_KEYS, actual: array_keys($nldesign), message: 'Payload must contain exactly the eight allowlisted keys, in shape.');
		$this->assertSame(expected: '3.4.0', actual: $nldesign['version']);
		$this->assertSame(expected: ['id' => 'rijkshuisstijl', 'name' => 'Rijkshuisstijl', 'version' => null], actual: $nldesign['tokenSet']);
		$this->assertSame(expected: 'nldesign', actual: $nldesign['designSystem']);
		$this->assertSame(expected: ['rvo', 'open-gemeenten', 'den-haag'], actual: $nldesign['iconPacks']);
		$this->assertSame(expected: 'https://cloud.example/apps/thematiq/img/logos/rijkshuisstijl.svg', actual: $nldesign['logos']['default']);
		$this->assertArrayNotHasKey(key: 'emblem', array: $nldesign['logos'], message: 'No emblem file is shipped for this set.');
		$this->assertTrue(condition: $nldesign['hideSlogan']);
		$this->assertFalse(condition: $nldesign['showMenuLabels']);
		$this->assertSame(expected: 'AA', actual: $nldesign['wcagLevel']);
	}//end testFullPayloadShapeForShippedSet()

	/**
	 * A set that ships `img/logos/<id>-emblem.svg` exposes it as `logos.emblem`;
	 * the default logo stays. The Zuiddrecht set in this repository is the fixture.
	 *
	 * @spec openspec/specs/theming-capability/spec.md
	 *
	 * @return void
	 */
	public function testEmblemIsExposedWhenShipped(): void {
		$config = $this->configWith(values: ['token_set' => 'zuiddrecht']);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppVersion')->willReturn('3.4.0');
		$appManager->method('getAppPath')->willReturn(dirname(__DIR__, 2));

		$urlGenerator = $this->createMock(originalClassName: IURLGenerator::class);
		$urlGenerator->method('imagePath')->willReturnCallback(
			static fn (string $appName, string $file): string => 'https://cloud.example/apps/thematiq/img/' . $file
		);

		$designSystemService = $this->createMock(originalClassName: DesignSystemService::class);
		$designSystemService->method('getTokenSetMeta')->with('zuiddrecht')->willReturn(
			[
				'id' => 'zuiddrecht',
				'design_system' => 'nldesign',
				'theming' => ['logo' => 'img/logos/zuiddrecht.svg'],
			]
		);
		$designSystemService->method('resolveActiveIconPacks')->willReturn([]);

		$auditService = $this->createMock(originalClassName: ShippedTokenSetAuditService::class);
		$auditService->method('auditSet')->willReturn(['verdict' => 'pass']);

		$capabilities = $this->buildCapabilities(config: $config, appManager: $appManager, urlGenerator: $urlGenerator, designSystemService: $designSystemService, auditService: $auditService);
		$logos = $capabilities->getCapabilities()['nldesign']['logos'];

		$this->assertFileExists(filename: dirname(__DIR__, 2) . '/img/logos/zuiddrecht-emblem.svg');
		$this->assertSame(expected: 'https://cloud.example/apps/thematiq/img/logos/zuiddrecht.svg', actual: $logos['default']);
		$this->assertSame(expected: 'https://cloud.example/apps/thematiq/img/logos/zuiddrecht-emblem.svg', actual: $logos['emblem']);
	}//end testEmblemIsExposedWhenShipped()

	/**
	 * No `token_set` appconfig value → stock Nextcloud defaults, no audit call.
	 *
	 * @return void
	 */
	public function testDefaultConfigNoTokenSet(): void {
		$config = $this->configWith(values: []);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppVersion')->willReturn('3.4.0');
		$appManager->method('getAppPath')->willReturn('/app');

		$urlGenerator = $this->createMock(originalClassName: IURLGenerator::class);

		$designSystemService = $this->createMock(originalClassName: DesignSystemService::class);
		$designSystemService->method('getTokenSetMeta')->with('nextcloud')->willReturn(
			[
				'id' => 'nextcloud',
				'design_system' => 'none',
				'theming' => ['primary_color' => '#0082c9', 'background_color' => '#FFFFFF'],
			]
		);
		$designSystemService->method('resolveActiveIconPacks')->with('nextcloud')->willReturn([]);

		$auditService = $this->createMock(originalClassName: ShippedTokenSetAuditService::class);
		$auditService->expects($this->never())->method('auditSet');

		$capabilities = $this->buildCapabilities(config: $config, appManager: $appManager, urlGenerator: $urlGenerator, designSystemService: $designSystemService, auditService: $auditService);
		$nldesign = $capabilities->getCapabilities()['nldesign'];

		$this->assertSame(expected: 'nextcloud', actual: $nldesign['tokenSet']['id']);
		$this->assertSame(expected: 'none', actual: $nldesign['designSystem']);
		$this->assertSame(expected: [], actual: $nldesign['iconPacks']);
		$this->assertEquals(expected: new \stdClass(), actual: $nldesign['logos']);
		$this->assertSame(expected: '{}', actual: json_encode($nldesign['logos']), message: 'Empty logos must serialize as a JSON object, never an array.');
		$this->assertNull(actual: $nldesign['wcagLevel']);
		$this->assertFalse(condition: $nldesign['hideSlogan']);
		$this->assertFalse(condition: $nldesign['showMenuLabels']);
	}//end testDefaultConfigNoTokenSet()

	/**
	 * A `lasuite`-active instance advertises the resolved `["dsfr"]` pack on
	 * the public capability (`openspec/specs/icon-packs/spec.md`).
	 *
	 * @return void
	 */
	public function testLasuiteActiveInstanceAdvertisesDsfrPack(): void {
		$config = $this->configWith(values: ['token_set' => 'lasuite']);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppVersion')->willReturn('3.4.0');
		$appManager->method('getAppPath')->willReturn('/app');

		$urlGenerator = $this->createMock(originalClassName: IURLGenerator::class);

		$designSystemService = $this->createMock(originalClassName: DesignSystemService::class);
		$designSystemService->method('getTokenSetMeta')->with('lasuite')->willReturn(
			['id' => 'lasuite', 'design_system' => 'lasuite', 'theming' => []]
		);
		$designSystemService->method('resolveActiveIconPacks')->with('lasuite')->willReturn(['dsfr']);

		$auditService = $this->createMock(originalClassName: ShippedTokenSetAuditService::class);
		$auditService->method('auditSet')->willReturn(['verdict' => 'pass']);

		$capabilities = $this->buildCapabilities(config: $config, appManager: $appManager, urlGenerator: $urlGenerator, designSystemService: $designSystemService, auditService: $auditService);
		$nldesign = $capabilities->getCapabilities()['nldesign'];

		$this->assertSame(expected: 'lasuite', actual: $nldesign['designSystem']);
		$this->assertSame(expected: ['dsfr'], actual: $nldesign['iconPacks']);
	}//end testLasuiteActiveInstanceAdvertisesDsfrPack()

	/**
	 * A custom/unknown token set id degrades name/version/wcagLevel, never lies.
	 *
	 * @return void
	 */
	public function testCustomOrUnknownTokenSetDegradesButNeverLies(): void {
		$config = $this->configWith(values: ['token_set' => 'custom-onbekend']);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppVersion')->willReturn('3.4.0');
		$appManager->method('getAppPath')->willReturn('/app');

		$urlGenerator = $this->createMock(originalClassName: IURLGenerator::class);

		$designSystemService = $this->createMock(originalClassName: DesignSystemService::class);
		// Not in token-sets.json (shipped manifest) — empty array, matching
		// DesignSystemService::getTokenSetMeta()'s real not-found return value.
		$designSystemService->method('getTokenSetMeta')->with('custom-onbekend')->willReturn([]);
		$designSystemService->method('resolveActiveIconPacks')->with('custom-onbekend')->willReturn([]);

		$auditService = $this->createMock(originalClassName: ShippedTokenSetAuditService::class);
		$auditService->expects($this->never())->method('auditSet');

		$capabilities = $this->buildCapabilities(config: $config, appManager: $appManager, urlGenerator: $urlGenerator, designSystemService: $designSystemService, auditService: $auditService);
		$nldesign = $capabilities->getCapabilities()['nldesign'];

		$this->assertSame(expected: 'custom-onbekend', actual: $nldesign['tokenSet']['id']);
		$this->assertSame(expected: 'custom-onbekend', actual: $nldesign['tokenSet']['name']);
		$this->assertNull(actual: $nldesign['tokenSet']['version']);
		$this->assertNull(actual: $nldesign['wcagLevel']);
	}//end testCustomOrUnknownTokenSetDegradesButNeverLies()

	/**
	 * The name and the upstream version come from the active set's own
	 * manifest entry, the one `getTokenSetMeta()` returns, so building the
	 * capability never builds (and audits) the whole token-set catalogue.
	 *
	 * @return void
	 */
	public function testTokenSetNameAndVersionComeFromTheActiveSetsManifestEntry(): void {
		$config = $this->configWith(values: ['token_set' => 'utrecht']);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppVersion')->willReturn('3.4.0');
		$appManager->method('getAppPath')->willReturn('/app');

		$urlGenerator = $this->createMock(originalClassName: IURLGenerator::class);

		$designSystemService = $this->createMock(originalClassName: DesignSystemService::class);
		$designSystemService->method('getTokenSetMeta')->with('utrecht')->willReturn(
			[
				'id' => 'utrecht',
				'name' => 'Gemeente Utrecht',
				'design_system' => 'nldesign',
				'upstreamVersion' => '1.2.0',
				'theming' => [],
			]
		);
		$designSystemService->method('resolveActiveIconPacks')->willReturn([]);

		$auditService = $this->createMock(originalClassName: ShippedTokenSetAuditService::class);
		$auditService->method('auditSet')->willReturn(['verdict' => 'pass']);

		$capabilities = $this->buildCapabilities(config: $config, appManager: $appManager, urlGenerator: $urlGenerator, designSystemService: $designSystemService, auditService: $auditService);
		$nldesign = $capabilities->getCapabilities()['nldesign'];

		$this->assertSame(expected: ['id' => 'utrecht', 'name' => 'Gemeente Utrecht', 'version' => '1.2.0'], actual: $nldesign['tokenSet']);
	}//end testTokenSetNameAndVersionComeFromTheActiveSetsManifestEntry()

	/**
	 * The WCAG level is cached — a second call within the TTL never re-invokes
	 * the audit service, and both calls report the same level.
	 *
	 * @return void
	 */
	public function testWcagLevelIsCachedAcrossCalls(): void {
		$config = $this->configWith(values: ['token_set' => 'rijkshuisstijl']);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppVersion')->willReturn('3.4.0');
		$appManager->method('getAppPath')->willReturn('/app');

		$urlGenerator = $this->createMock(originalClassName: IURLGenerator::class);

		$designSystemService = $this->createMock(originalClassName: DesignSystemService::class);
		$designSystemService->method('getTokenSetMeta')->willReturn(
			['id' => 'rijkshuisstijl', 'design_system' => 'nldesign', 'theming' => []]
		);
		$designSystemService->method('resolveActiveIconPacks')->willReturn(['rvo', 'open-gemeenten', 'den-haag']);

		$auditService = $this->createMock(originalClassName: ShippedTokenSetAuditService::class);
		$auditService->expects($this->once())->method('auditSet')->willReturn(['verdict' => 'pass']);

		$capabilities = $this->buildCapabilities(config: $config, appManager: $appManager, urlGenerator: $urlGenerator, designSystemService: $designSystemService, auditService: $auditService);

		$first = $capabilities->getCapabilities()['nldesign']['wcagLevel'];
		$second = $capabilities->getCapabilities()['nldesign']['wcagLevel'];

		$this->assertSame(expected: 'AA', actual: $first);
		$this->assertSame(expected: $first, actual: $second);
	}//end testWcagLevelIsCachedAcrossCalls()

	/**
	 * A high-contrast set declaring `contrast_level: AAA` and passing AAA
	 * thresholds reports `AAA`.
	 *
	 * @return void
	 */
	public function testHighContrastSetReportsAaa(): void {
		$config = $this->configWith(values: ['token_set' => 'hoog-contrast']);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppVersion')->willReturn('3.4.0');
		$appManager->method('getAppPath')->willReturn('/app');

		$urlGenerator = $this->createMock(originalClassName: IURLGenerator::class);

		$designSystemService = $this->createMock(originalClassName: DesignSystemService::class);
		$designSystemService->method('getTokenSetMeta')->willReturn(
			['id' => 'hoog-contrast', 'design_system' => 'high-contrast', 'contrast_level' => 'AAA', 'theming' => []]
		);
		$designSystemService->method('resolveActiveIconPacks')->willReturn([]);

		$auditService = $this->createMock(originalClassName: ShippedTokenSetAuditService::class);
		$auditService->method('auditSet')->willReturn(['verdict' => 'pass']);

		$capabilities = $this->buildCapabilities(config: $config, appManager: $appManager, urlGenerator: $urlGenerator, designSystemService: $designSystemService, auditService: $auditService);
		$nldesign = $capabilities->getCapabilities()['nldesign'];

		$this->assertSame(expected: 'AAA', actual: $nldesign['wcagLevel']);
	}//end testHighContrastSetReportsAaa()

	/**
	 * A shipped set failing its audit reports `fail`, never a fabricated pass.
	 *
	 * @return void
	 */
	public function testFailingShippedSetReportsFail(): void {
		$config = $this->configWith(values: ['token_set' => 'noaberkracht']);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppVersion')->willReturn('3.4.0');
		$appManager->method('getAppPath')->willReturn('/app');

		$urlGenerator = $this->createMock(originalClassName: IURLGenerator::class);

		$designSystemService = $this->createMock(originalClassName: DesignSystemService::class);
		$designSystemService->method('getTokenSetMeta')->willReturn(
			['id' => 'noaberkracht', 'design_system' => 'nldesign', 'theming' => []]
		);
		$designSystemService->method('resolveActiveIconPacks')->willReturn(['rvo', 'open-gemeenten', 'den-haag']);

		$auditService = $this->createMock(originalClassName: ShippedTokenSetAuditService::class);
		$auditService->method('auditSet')->willReturn(['verdict' => 'fail']);

		$capabilities = $this->buildCapabilities(config: $config, appManager: $appManager, urlGenerator: $urlGenerator, designSystemService: $designSystemService, auditService: $auditService);
		$nldesign = $capabilities->getCapabilities()['nldesign'];

		$this->assertSame(expected: 'fail', actual: $nldesign['wcagLevel']);
	}//end testFailingShippedSetReportsFail()

	/**
	 * A throwing injected dependency degrades to the minimal payload — no
	 * exception escapes getCapabilities(), and no exception detail leaks.
	 *
	 * @return void
	 */
	public function testThrowingDependencyDegradesGracefully(): void {
		$config = $this->configWith(values: ['token_set' => 'rijkshuisstijl']);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppVersion')->willReturn('3.4.0');

		$urlGenerator = $this->createMock(originalClassName: IURLGenerator::class);

		$designSystemService = $this->createMock(originalClassName: DesignSystemService::class);
		$designSystemService->method('getTokenSetMeta')->willThrowException(new \RuntimeException('manifest unreadable: /secret/path'));

		$auditService = $this->createMock(originalClassName: ShippedTokenSetAuditService::class);
		$auditService->expects($this->never())->method('auditSet');

		$capabilities = $this->buildCapabilities(config: $config, appManager: $appManager, urlGenerator: $urlGenerator, designSystemService: $designSystemService, auditService: $auditService);

		$payload = $capabilities->getCapabilities();

		$this->assertArrayHasKey(key: 'nldesign', array: $payload);
		$nldesign = $payload['nldesign'];

		$this->assertSame(expected: self::ALLOWED_KEYS, actual: array_keys($nldesign));
		$this->assertSame(expected: '3.4.0', actual: $nldesign['version']);
		$this->assertSame(expected: 'rijkshuisstijl', actual: $nldesign['tokenSet']['id']);
		$this->assertSame(expected: 'rijkshuisstijl', actual: $nldesign['tokenSet']['name']);
		$this->assertNull(actual: $nldesign['tokenSet']['version']);
		$this->assertNull(actual: $nldesign['designSystem']);
		$this->assertSame(expected: [], actual: $nldesign['iconPacks']);
		$this->assertNull(actual: $nldesign['wcagLevel']);
		$this->assertEquals(expected: new \stdClass(), actual: $nldesign['logos']);
		$this->assertFalse(condition: $nldesign['hideSlogan']);
		$this->assertFalse(condition: $nldesign['showMenuLabels']);

		$encoded = (string)json_encode($payload);
		$this->assertStringNotContainsString(needle: 'secret', haystack: $encoded, message: 'No exception detail must leak into the payload.');
	}//end testThrowingDependencyDegradesGracefully()
}//end class
