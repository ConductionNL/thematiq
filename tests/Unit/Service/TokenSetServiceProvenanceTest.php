<?php

/**
 * Unit tests for TokenSetService upstream provenance pass-through.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/upstream-token-freshness/tasks.md#task-1.3
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
 * Covers openspec/specs/token-sets/spec.md "Provenance fields are optional and
 * inert": upstreamVersion/upstreamRef manifest keys MUST survive the shipped
 * discovery merge unmodified, and their absence MUST NOT affect discovery.
 */
class TokenSetServiceProvenanceTest extends TestCase {

	/**
	 * The temp app directory standing in for the nldesign app path.
	 *
	 * @var string
	 */
	private string $appDir;

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

		$this->appDir = sys_get_temp_dir() . '/nldesign-provenance-' . uniqid();
		mkdir($this->appDir . '/css/tokens', 0777, true);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => $default
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
	 * A manifest entry with upstreamVersion/upstreamRef carries both fields
	 * through discovery unmodified.
	 */
	public function testProvenanceFieldsSurviveDiscovery(): void {
		$this->writeTokenFile('utrecht');
		file_put_contents(
			$this->appDir . '/token-sets.json',
			json_encode(
				[
					[
						'id' => 'utrecht',
						'name' => 'Gemeente Utrecht',
						'description' => 'Utrecht design tokens',
						'design_system' => 'nldesign',
						'upstreamVersion' => '1.2.0',
						'upstreamRef' => 'abc123def456',
					],
				]
			)
		);

		$byId = array_column($this->service->getAvailableTokenSets(), null, 'id');

		$this->assertArrayHasKey('utrecht', $byId);
		$this->assertSame('1.2.0', $byId['utrecht']['upstreamVersion']);
		$this->assertSame('abc123def456', $byId['utrecht']['upstreamRef']);
	}//end testProvenanceFieldsSurviveDiscovery()

	/**
	 * A manifest entry without provenance fields behaves identically to one
	 * with them, minus the two optional keys — absence never breaks discovery.
	 */
	public function testAbsentProvenanceFieldsDoNotAppear(): void {
		$this->writeTokenFile('nextcloud');
		file_put_contents(
			$this->appDir . '/token-sets.json',
			json_encode(
				[
					[
						'id' => 'nextcloud',
						'name' => 'Nextcloud',
						'description' => 'Stock Nextcloud',
						'design_system' => 'none',
					],
				]
			)
		);

		$byId = array_column($this->service->getAvailableTokenSets(), null, 'id');

		$this->assertArrayHasKey('nextcloud', $byId);
		$this->assertArrayNotHasKey('upstreamVersion', $byId['nextcloud']);
		$this->assertArrayNotHasKey('upstreamRef', $byId['nextcloud']);
	}//end testAbsentProvenanceFieldsDoNotAppear()

	/**
	 * A manifest entry with only upstreamRef (no version resolvable upstream)
	 * survives with just that one field.
	 */
	public function testUpstreamRefWithoutVersionSurvivesAlone(): void {
		$this->writeTokenFile('rotterdam');
		file_put_contents(
			$this->appDir . '/token-sets.json',
			json_encode(
				[
					[
						'id' => 'rotterdam',
						'name' => 'Rotterdam',
						'description' => 'Rotterdam design tokens',
						'design_system' => 'nldesign',
						'upstreamRef' => 'shaonly000',
					],
				]
			)
		);

		$byId = array_column($this->service->getAvailableTokenSets(), null, 'id');

		$this->assertSame('shaonly000', $byId['rotterdam']['upstreamRef']);
		$this->assertArrayNotHasKey('upstreamVersion', $byId['rotterdam']);
	}//end testUpstreamRefWithoutVersionSurvivesAlone()
}//end class
