<?php

/**
 * Unit tests for the audit warnings kept in app data behind the local cache.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\AuditWarningsStore;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\TokenSetAudits;
use OCA\Thematiq\Service\TokenSetFiles;
use OCA\Thematiq\Service\TokenSetFontAuditService;
use OCA\Thematiq\Service\TokenSetRuntime;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\TokenSetVocabularyAuditService;
use OCP\App\IAppManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * After the local cache is emptied (a PHP-FPM restart), the catalogue takes a
 * shipped set's warnings from app data instead of auditing it again, and
 * stores what it did audit.
 */
class TokenSetServiceStoredWarningsTest extends TestCase {

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
	 * The app data store behind the local cache.
	 *
	 * @var AuditWarningsStore&\PHPUnit\Framework\MockObject\MockObject
	 */
	private AuditWarningsStore $stored;

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

		$this->appDir = sys_get_temp_dir() . '/thematiq-stored-warnings-' . uniqid();
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

		$this->stored = $this->createMock(originalClassName: AuditWarningsStore::class);

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
			setFiles: new TokenSetFiles(
				appManager: $appManager
			),
			runtime: new TokenSetRuntime(
				config: $config,
				cacheFactory: $cacheFactory,
				logger: $this->createMock(originalClassName: LoggerInterface::class)
			),
			audits: new TokenSetAudits(
				audit: $audit,
				vocabularyAudit: $vocabularyAudit,
				fontAudit: $fontAudit,
				stored: $this->stored
			)
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
	 * Warnings app data holds for the same key are used and the set is not audited.
	 *
	 * @return void
	 */
	public function testStoredWarningsAreUsedInsteadOfAnAudit(): void {
		$this->stored->method('get')->willReturn([['pair' => 'link', 'verdict' => 'fail']]);
		$this->stored->expects($this->never())->method('put');

		$sets = array_column($this->service->getAvailableTokenSets(), null, 'id');

		$this->assertSame(expected: 0, actual: $this->auditRuns, message: 'stored warnings must spare the audit');
		$this->assertSame(expected: [['pair' => 'link', 'verdict' => 'fail']], actual: $sets['utrecht']['warnings']);
	}//end testStoredWarningsAreUsedInsteadOfAnAudit()

	/**
	 * A set app data has nothing for is audited, handed to the store, and the read writes the store once.
	 *
	 * @return void
	 */
	public function testAnAuditedSetIsStoredAndWrittenOnce(): void {
		$this->stored->method('get')->willReturn(null);
		$this->stored->expects($this->once())->method('put')->with(
			$this->stringStartsWith(prefix: 'utrecht-'),
			[['pair' => 'text', 'verdict' => 'fail']]
		);
		$this->stored->expects($this->once())->method('write')->with($this->appDir);

		$this->service->getAvailableTokenSets();

		$this->assertSame(expected: 1, actual: $this->auditRuns);
	}//end testAnAuditedSetIsStoredAndWrittenOnce()

	/**
	 * A set the local cache answers is handed to the store to keep, so the store's next write holds it.
	 *
	 * @return void
	 */
	public function testLocalCacheHitIsKept(): void {
		$this->stored->method('get')->willReturn(null);
		$this->stored->expects($this->once())->method('put');
		$this->stored->expects($this->once())->method('keep')->with(
			$this->stringStartsWith(prefix: 'utrecht-'),
			[['pair' => 'text', 'verdict' => 'fail']]
		);

		$this->service->getAvailableTokenSets();
		$this->service->getAvailableTokenSets();

		$this->assertSame(expected: 1, actual: $this->auditRuns);
	}//end testLocalCacheHitIsKept()
}//end class
