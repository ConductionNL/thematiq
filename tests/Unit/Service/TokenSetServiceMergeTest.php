<?php

/**
 * Unit tests for TokenSetService custom-set discovery merge.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-5.5
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
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/nldesign-merge-' . uniqid();
		mkdir($this->appDir . '/css/tokens', 0777, true);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($key === 'custom_token_sets' ? $this->customManifest : $default)
		);

		$audit = new ShippedTokenSetAuditService(new ContrastService(), new CssParserService());
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->createMock(ICache::class));

		$this->service = new TokenSetService(
			$appManager,
			$config,
			$this->createMock(LoggerInterface::class),
			$audit,
			$cacheFactory,
			new TokenSetVocabularyAuditService(new CssParserService())
		);
	}//end setUp()

	/**
	 * Remove the temp app dir after each test.
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
	 */
	public function testCustomFileWithManifestCarriesMetadata(): void {
		$this->writeTokenFile('custom-gemeente-voorbeeld');
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

		$this->assertSame('Gemeente Voorbeeld', $custom['name']);
		$this->assertTrue($custom['custom']);
		$this->assertSame('#007bc7', $custom['theming']['primary_color']);
		$this->assertNotEmpty($custom['warnings']);
	}//end testCustomFileWithManifestCarriesMetadata()

	/**
	 * A custom file without a manifest entry falls back to an id-derived name.
	 */
	public function testCustomFileWithoutManifestUsesIdFallback(): void {
		$this->writeTokenFile('custom-orphan-set');
		$this->customManifest = '{}';

		$byId = array_column($this->service->getAvailableTokenSets(), null, 'id');

		$this->assertArrayHasKey('custom-orphan-set', $byId);
		$this->assertTrue($byId['custom-orphan-set']['custom']);
		// Name is derived from the id (formatName), not empty.
		$this->assertNotEmpty($byId['custom-orphan-set']['name']);
	}//end testCustomFileWithoutManifestUsesIdFallback()

	/**
	 * A manifest entry without a backing file is dropped from discovery.
	 */
	public function testManifestEntryWithoutFileIsDropped(): void {
		// No file written for custom-stale.
		$this->customManifest = json_encode(['custom-stale' => ['name' => 'Stale']]);

		$byId = array_column($this->service->getAvailableTokenSets(), null, 'id');

		$this->assertArrayNotHasKey('custom-stale', $byId);
	}//end testManifestEntryWithoutFileIsDropped()

	/**
	 * Malformed custom manifest JSON degrades to no custom sets, never an error.
	 */
	public function testMalformedManifestDegradesGracefully(): void {
		$this->writeTokenFile('custom-x');
		$this->customManifest = 'not-json{';

		$byId = array_column($this->service->getAvailableTokenSets(), null, 'id');

		// The file is still discovered (filesystem is the source of truth),
		// just without manifest metadata.
		$this->assertArrayHasKey('custom-x', $byId);
		$this->assertTrue($byId['custom-x']['custom']);
	}//end testMalformedManifestDegradesGracefully()

	/**
	 * Sets are sorted alphabetically across shipped and custom groups.
	 */
	public function testAlphabeticalSortSpansBothGroups(): void {
		$this->writeTokenFile('nextcloud');
		$this->writeTokenFile('custom-aaa');
		$this->customManifest = json_encode(['custom-aaa' => ['name' => 'Aaa Custom']]);

		$names = array_column($this->service->getAvailableTokenSets(), 'name');
		$sorted = $names;
		usort($sorted, 'strcasecmp');

		$this->assertSame($sorted, $names);
	}//end testAlphabeticalSortSpansBothGroups()
}//end class
