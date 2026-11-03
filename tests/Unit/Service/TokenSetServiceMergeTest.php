<?php

/**
 * Unit tests for TokenSetService custom-set discovery merge.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-5.5
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\TokenSetVocabularyAuditService;
use OCP\App\IAppManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for the shipped + custom token-set discovery merge.
 *
 * Covers tasks.md#task-5.5: custom metadata applied, file-without-manifest
 * fallback, manifest-without-file dropped, and shipped-precedence on collision.
 */
class TokenSetServiceMergeTest extends TestCase {

	/**
	 * The temp app directory standing in for the nldesign app path.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * The custom-set appconfig manifest JSON value.
	 *
	 * @var string
	 */
	private string $customManifest = '{}';

	/**
	 * The service under test.
	 *
	 * @var TokenSetService
	 */
	private TokenSetService $service;

	/**
	 * Set up a temp app dir + mocked config before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/nldesign-merge-' . uniqid();
		mkdir($this->appDir . '/css/tokens', 0777, true);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);

		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => (match ($key === 'custom_token_sets') {
				true => $this->customManifest, default => $default
			})
		);

		$audit = new ShippedTokenSetAuditService(contrast: new ContrastService(), parser: new CssParserService());
		$cacheFactory = $this->createMock(originalClassName: ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->createMock(originalClassName: ICache::class));

		$this->service = new TokenSetService(
			appManager: $appManager,
			config: $config,
			logger: $this->createMock(originalClassName: LoggerInterface::class),
			audit: $audit,
			cacheFactory: $cacheFactory,
			vocabularyAudit: new TokenSetVocabularyAuditService(parser: new CssParserService())
		);
	}//end setUp()

	/**
	 * Remove the temp app dir after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach (glob($this->appDir . '/css/tokens/*') ?: [] as $f) {
			unlink($f);
		}

		@unlink($this->appDir . '/token-sets.json');
		@rmdir($this->appDir . '/css/tokens');
		@rmdir($this->appDir . '/css');
		@rmdir($this->appDir);
		parent::tearDown();
	}//end tearDown()

	/**
	 * Write a token CSS file into the temp tokens dir.
	 *
	 * @param string $id The token set id (file basename).
	 *
	 * @return void
	 */
	private function writeTokenFile(string $id): void {
		file_put_contents($this->appDir . '/css/tokens/' . $id . '.css', ':root { --nldesign-color-primary: #007bc7; }');
	}//end writeTokenFile()

	/**
	 * A custom file with a manifest entry carries its metadata and custom flag.
	 *
	 * @return void
	 */
	public function testCustomFileWithManifestCarriesMetadata(): void {
		$this->writeTokenFile(id: 'custom-gemeente-voorbeeld');
		$this->customManifest = json_encode(
			[
				'custom-gemeente-voorbeeld' => [
					'name' => 'Gemeente Voorbeeld',
					'description' => 'Eigen huisstijl',
					'theming' => ['primary_color' => '#007bc7'],
					'warnings' => [['pair' => 'x vs y', 'ratio' => 1.6, 'threshold' => 4.5, 'level' => 'AA']],
				],
			]
		);

		$sets = $this->service->getAvailableTokenSets();
		$byId = array_column($sets, null, 'id');
		$custom = $byId['custom-gemeente-voorbeeld'];

		$this->assertSame(expected: 'Gemeente Voorbeeld', actual: $custom['name']);
		$this->assertTrue(condition: $custom['custom']);
		$this->assertSame(expected: '#007bc7', actual: $custom['theming']['primary_color']);
		$this->assertNotEmpty(actual: $custom['warnings']);
	}//end testCustomFileWithManifestCarriesMetadata()

	/**
	 * A custom file without a manifest entry falls back to an id-derived name.
	 *
	 * @return void
	 */
	public function testCustomFileWithoutManifestUsesIdFallback(): void {
		$this->writeTokenFile(id: 'custom-orphan-set');
		$this->customManifest = '{}';

		$byId = array_column($this->service->getAvailableTokenSets(), null, 'id');

		$this->assertArrayHasKey(key: 'custom-orphan-set', array: $byId);
		$this->assertTrue(condition: $byId['custom-orphan-set']['custom']);
		// Name is derived from the id (formatName), not empty.
		$this->assertNotEmpty(actual: $byId['custom-orphan-set']['name']);
	}//end testCustomFileWithoutManifestUsesIdFallback()

	/**
	 * A manifest entry without a backing file is dropped from discovery.
	 *
	 * @return void
	 */
	public function testManifestEntryWithoutFileIsDropped(): void {
		// No file written for custom-stale.
		$this->customManifest = json_encode(['custom-stale' => ['name' => 'Stale']]);

		$byId = array_column($this->service->getAvailableTokenSets(), null, 'id');

		$this->assertArrayNotHasKey(key: 'custom-stale', array: $byId);
	}//end testManifestEntryWithoutFileIsDropped()

	/**
	 * Malformed custom manifest JSON degrades to no custom sets, never an error.
	 *
	 * @return void
	 */
	public function testMalformedManifestDegradesGracefully(): void {
		$this->writeTokenFile(id: 'custom-x');
		$this->customManifest = 'not-json{';

		$byId = array_column($this->service->getAvailableTokenSets(), null, 'id');

		// The file is still discovered (filesystem is the source of truth),
		// just without manifest metadata.
		$this->assertArrayHasKey(key: 'custom-x', array: $byId);
		$this->assertTrue(condition: $byId['custom-x']['custom']);
	}//end testMalformedManifestDegradesGracefully()

	/**
	 * Sets are sorted alphabetically across shipped and custom groups.
	 *
	 * @return void
	 */
	public function testAlphabeticalSortSpansBothGroups(): void {
		$this->writeTokenFile(id: 'nextcloud');
		$this->writeTokenFile(id: 'custom-aaa');
		$this->customManifest = json_encode(['custom-aaa' => ['name' => 'Aaa Custom']]);

		$names = array_column($this->service->getAvailableTokenSets(), 'name');
		$sorted = $names;
		usort($sorted, 'strcasecmp');

		$this->assertSame(expected: $sorted, actual: $names);
	}//end testAlphabeticalSortSpansBothGroups()

	/**
	 * Build a service over the temp app dir with a logger the test can inspect.
	 *
	 * @param LoggerInterface $logger The logger to inject.
	 *
	 * @return TokenSetService
	 */
	private function serviceWithLogger(LoggerInterface $logger): TokenSetService {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);

		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => (match ($key === 'custom_token_sets') {
				true => $this->customManifest, default => $default
			})
		);

		$cacheFactory = $this->createMock(originalClassName: ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->createMock(originalClassName: ICache::class));

		return new TokenSetService(
			appManager: $appManager,
			config: $config,
			logger: $logger,
			audit: new ShippedTokenSetAuditService(contrast: new ContrastService(), parser: new CssParserService()),
			cacheFactory: $cacheFactory,
			vocabularyAudit: new TokenSetVocabularyAuditService(parser: new CssParserService())
		);
	}//end serviceWithLogger()

	/**
	 * The same id in both manifests: the shipped entry wins, and the collision
	 * is logged at warning level.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-filesystem-based-discovery
	 *
	 * @return void
	 */
	public function testShippedManifestWinsACollisionAndLogsIt(): void {
		$this->writeTokenFile(id: 'custom-dup');
		file_put_contents(
			$this->appDir . '/token-sets.json',
			json_encode([['id' => 'custom-dup', 'name' => 'Shipped Name', 'description' => 'Shipped']])
		);
		$this->customManifest = json_encode(['custom-dup' => ['name' => 'Custom Name', 'description' => 'Custom']]);

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('warning')->with($this->stringContains(string: 'custom-dup'));

		$byId = array_column($this->serviceWithLogger(logger: $logger)->getAvailableTokenSets(), null, 'id');

		$this->assertSame(expected: 'Shipped Name', actual: $byId['custom-dup']['name']);
		$this->assertSame(expected: 'Shipped', actual: $byId['custom-dup']['description']);
	}//end testShippedManifestWinsACollisionAndLogsIt()

	/**
	 * A token-sets.json that is not valid JSON degrades to no metadata: every
	 * file is still discovered, with an id-derived name and the nldesign default.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-token-set-manifest-structure
	 *
	 * @return void
	 */
	public function testMalformedShippedManifestStillDiscoversFiles(): void {
		$this->writeTokenFile(id: 'gemeente-test');
		file_put_contents($this->appDir . '/token-sets.json', '[{"id": "gemeente-test", "name": ');

		$byId = array_column($this->service->getAvailableTokenSets(), null, 'id');

		$this->assertSame(expected: 'Gemeente Test', actual: $byId['gemeente-test']['name']);
		$this->assertSame(expected: 'Design tokens for Gemeente Test', actual: $byId['gemeente-test']['description']);
		$this->assertSame(expected: 'nldesign', actual: $byId['gemeente-test']['design_system']);
	}//end testMalformedShippedManifestStillDiscoversFiles()

	/**
	 * A token-sets.json the process cannot read degrades the same way.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-token-set-manifest-structure
	 *
	 * @return void
	 */
	public function testUnreadableShippedManifestStillDiscoversFiles(): void {
		$this->writeTokenFile(id: 'gemeente-test');
		$manifest = $this->appDir . '/token-sets.json';
		file_put_contents($manifest, json_encode([['id' => 'gemeente-test', 'name' => 'Named']]));
		chmod($manifest, 0000);
		if (is_readable($manifest) === true) {
			chmod($manifest, 0644);
			$this->markTestSkipped(message: 'Running as a user that can read a 0000 file (root); unreadability cannot be produced.');
		}

		try {
			// file_get_contents() warns before it returns false; the warning is
			// the platform's, the false return is what is under test.
			$sets = @$this->service->getAvailableTokenSets();
		} finally {
			chmod($manifest, 0644);
		}

		$byId = array_column($sets, null, 'id');
		$this->assertSame(expected: 'Gemeente Test', actual: $byId['gemeente-test']['name']);
	}//end testUnreadableShippedManifestStillDiscoversFiles()

	/**
	 * Manifest entries are indexed by `id`; an entry without one is skipped
	 * rather than attached to some other set.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-token-set-manifest-structure
	 *
	 * @return void
	 */
	public function testShippedManifestIsIndexedByIdAndSkipsEntriesWithoutOne(): void {
		$this->writeTokenFile(id: 'alpha');
		$this->writeTokenFile(id: 'beta');
		file_put_contents(
			$this->appDir . '/token-sets.json',
			json_encode(
				[
					['name' => 'No Id At All', 'description' => 'skipped'],
					['id' => 'beta', 'name' => 'Beta Named'],
					['id' => 'alpha', 'name' => 'Alpha Named'],
				]
			)
		);

		$byId = array_column($this->service->getAvailableTokenSets(), null, 'id');

		$this->assertSame(expected: 'Alpha Named', actual: $byId['alpha']['name']);
		$this->assertSame(expected: 'Beta Named', actual: $byId['beta']['name']);
		$this->assertNotContains(needle: 'No Id At All', haystack: array_column($byId, 'name'));
	}//end testShippedManifestIsIndexedByIdAndSkipsEntriesWithoutOne()
}//end class
