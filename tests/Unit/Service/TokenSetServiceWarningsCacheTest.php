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
	 *
	 * @return void
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

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);
		$appManager->method('getAppVersion')->willReturn('1.0.0');

		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => $default
		);

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
		$cacheFactory->method('createDistributed')->willReturn($this->createMock(originalClassName: ICache::class));
		$cacheFactory->method('createLocal')->willReturn($cache);

		$audit = $this->createMock(originalClassName: ShippedTokenSetAuditService::class);
		$audit->method('warningsFor')->willReturnCallback(
			function (): array {
				$this->auditRuns++;
				return [['pair' => 'text', 'verdict' => 'fail']];
			}
		);

		$vocabularyAudit = $this->createMock(originalClassName: TokenSetVocabularyAuditService::class);
		$vocabularyAudit->method('warningsFor')->willReturn([]);
		$fontAudit = $this->createMock(originalClassName: TokenSetFontAuditService::class);
		$fontAudit->method('warningsFor')->willReturn([]);

		$this->service = new TokenSetService(
			appManager: $appManager,
			config: $config,
			logger: $this->createMock(originalClassName: LoggerInterface::class),
			audit: $audit,
			cacheFactory: $cacheFactory,
			vocabularyAudit: $vocabularyAudit,
			fontAudit: $fontAudit
		);
	}//end setUp()

	/**
	 * Remove the temp app dir after each test.
	 *
	 * @return void
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
	 *
	 * @return void
	 */
	public function testSecondReadReusesTheCachedWarnings(): void {
		$first = array_column($this->service->getAvailableTokenSets(), null, 'id');
		$second = array_column($this->service->getAvailableTokenSets(), null, 'id');

		$this->assertSame(expected: 1, actual: $this->auditRuns, message: 'the audits must run once for unchanged inputs');
		$this->assertSame(expected: [['pair' => 'text', 'verdict' => 'fail']], actual: $first['utrecht']['warnings']);
		$this->assertSame(expected: $first['utrecht']['warnings'], actual: $second['utrecht']['warnings']);
	}//end testSecondReadReusesTheCachedWarnings()

	/**
	 * A set whose CSS changed is audited again rather than served stale warnings.
	 *
	 * @return void
	 */
	public function testChangedSetCssIsAuditedAgain(): void {
		$this->service->getAvailableTokenSets();
		$this->writeTokenFile(css: ':root { --nldesign-color-primary: #154273; }');
		$this->service->getAvailableTokenSets();

		$this->assertSame(expected: 2, actual: $this->auditRuns, message: 'a changed set must not be served the warnings of its old CSS');
	}//end testChangedSetCssIsAuditedAgain()
}//end class
