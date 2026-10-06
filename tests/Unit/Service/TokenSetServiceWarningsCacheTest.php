<?php

/**
 * Unit tests for the local cache of a shipped set's audit warnings.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\TokenSetFontAuditService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\TokenSetVocabularyAuditService;
use OCP\App\IAppManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The catalogue audits every shipped set; with unchanged inputs a second read
 * must take the warnings from the cache, and a changed set must be audited
 * again.
 */
class TokenSetServiceWarningsCacheTest extends TestCase {

	/**
	 * The temp app directory standing in for the app path.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * How often the contrast audit ran.
	 *
	 * @var integer
	 */
	private int $auditRuns = 0;

	/**
	 * The service under test.
	 *
	 * @var TokenSetService
	 */
	private TokenSetService $service;

	/**
	 * Set up a temp app dir with one shipped set, and an in-memory local cache.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/thematiq-warnings-cache-' . uniqid();
		mkdir($this->appDir . '/css/tokens', 0777, true);
		file_put_contents(
			$this->appDir . '/token-sets.json',
			json_encode([['id' => 'utrecht', 'name' => 'Gemeente Utrecht', 'design_system' => 'nldesign']])
		);
		$this->writeTokenFile(css: ':root { --nldesign-color-primary: #007bc7; }');

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);
		$appManager->method('getAppVersion')->willReturn('1.0.0');

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => $default
		);

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
		$cacheFactory->method('createDistributed')->willReturn($this->createMock(ICache::class));
		$cacheFactory->method('createLocal')->willReturn($cache);

		$audit = $this->createMock(ShippedTokenSetAuditService::class);
		$audit->method('warningsFor')->willReturnCallback(
			function (): array {
				$this->auditRuns++;
				return [['pair' => 'text', 'verdict' => 'fail']];
			}
		);

		$vocabularyAudit = $this->createMock(TokenSetVocabularyAuditService::class);
		$vocabularyAudit->method('warningsFor')->willReturn([]);
		$fontAudit = $this->createMock(TokenSetFontAuditService::class);
		$fontAudit->method('warningsFor')->willReturn([]);

		$this->service = new TokenSetService(
			$appManager,
			$config,
			$this->createMock(LoggerInterface::class),
			$audit,
			$cacheFactory,
			$vocabularyAudit,
			fontAudit: $fontAudit
		);
	}//end setUp()

	/**
	 * Remove the temp app dir after each test.
	 */
	protected function tearDown(): void {
		@unlink($this->appDir . '/css/tokens/utrecht.css');
		@unlink($this->appDir . '/token-sets.json');
		@rmdir($this->appDir . '/css/tokens');
		@rmdir($this->appDir . '/css');
		@rmdir($this->appDir);
		parent::tearDown();
	}//end tearDown()

	/**
	 * Write the shipped set's token CSS.
	 *
	 * @param string $css The file content.
	 *
	 * @return void
	 */
	private function writeTokenFile(string $css): void {
		file_put_contents($this->appDir . '/css/tokens/utrecht.css', $css);
	}//end writeTokenFile()

	/**
	 * A second catalogue read with unchanged inputs takes the warnings from the
	 * cache and returns the same warnings.
	 */
	public function testSecondReadReusesTheCachedWarnings(): void {
		$first = array_column($this->service->getAvailableTokenSets(), null, 'id');
		$second = array_column($this->service->getAvailableTokenSets(), null, 'id');

		$this->assertSame(1, $this->auditRuns, 'the audits must run once for unchanged inputs');
		$this->assertSame([['pair' => 'text', 'verdict' => 'fail']], $first['utrecht']['warnings']);
		$this->assertSame($first['utrecht']['warnings'], $second['utrecht']['warnings']);
	}//end testSecondReadReusesTheCachedWarnings()

	/**
	 * A set whose CSS changed is audited again rather than served stale warnings.
	 */
	public function testChangedSetCssIsAuditedAgain(): void {
		$this->service->getAvailableTokenSets();
		$this->writeTokenFile(css: ':root { --nldesign-color-primary: #154273; }');
		$this->service->getAvailableTokenSets();

		$this->assertSame(2, $this->auditRuns, 'a changed set must not be served the warnings of its old CSS');
	}//end testChangedSetCssIsAuditedAgain()
}//end class
