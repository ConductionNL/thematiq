<?php

/**
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileStore;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\TokenSetAudits;
use OCA\Thematiq\Service\TokenSetFiles;
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
 * An uploaded set lives in the runtime store, not in the release: the token set list finds it there.
 */
class TokenSetServiceUploadedSetTest extends TestCase {

	/**
	 * A release directory with no token set files of its own.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * Lay down the empty release directory.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->appDir = sys_get_temp_dir() . '/thematiq-uploaded-set-test-' . uniqid();
		mkdir(directory: $this->appDir . '/css/tokens', recursive: true);
	}//end setUp()

	/**
	 * Remove the release directory.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		rmdir(directory: $this->appDir . '/css/tokens');
		rmdir(directory: $this->appDir . '/css');
		rmdir(directory: $this->appDir);
		parent::tearDown();
	}//end tearDown()

	/**
	 * An uploaded set that exists only in the store is a valid set.
	 *
	 * @return void
	 */
	public function testAnUploadedSetInTheStoreIsValid(): void {
		$store = $this->createMock(originalClassName: RuntimeFileStore::class);
		$store->method('exists')->willReturnMap(valueMap: [['css/tokens/custom-proef.css', true]]);

		$this->assertTrue(condition: $this->service(store: $store)->isValidTokenSet(tokenSetId: 'custom-proef'));
	}//end testAnUploadedSetInTheStoreIsValid()

	/**
	 * The set list scans the store as well as the release.
	 *
	 * @return void
	 */
	public function testTheSetListIncludesTheStoresSets(): void {
		$store = $this->createMock(originalClassName: RuntimeFileStore::class);
		$store->method('listDirectory')->willReturnMap(valueMap: [['css/tokens', ['css/tokens/custom-proef.css']]]);
		$store->method('read')->willReturn(":root {\n  --nldesign-color-primary: #154273;\n}\n");

		$ids = array_column(array: $this->service(store: $store)->getAvailableTokenSets(), column_key: 'id');

		$this->assertContains(needle: 'custom-proef', haystack: $ids);
	}//end testTheSetListIncludesTheStoresSets()

	/**
	 * The service over the empty release and the given store.
	 *
	 * @param RuntimeFileStore $store The runtime store.
	 *
	 * @return TokenSetService
	 */
	private function service(RuntimeFileStore $store): TokenSetService {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnArgument(2);
		$cacheFactory = $this->createMock(originalClassName: ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->createMock(originalClassName: ICache::class));
		$cacheFactory->method('createLocal')->willReturn($this->createMock(originalClassName: ICache::class));
		$parser = new CssParserService();

		return new TokenSetService(
			setFiles: new TokenSetFiles(appManager: $appManager, store: $store),
			runtime: new TokenSetRuntime(config: $config, cacheFactory: $cacheFactory, logger: $this->createMock(originalClassName: LoggerInterface::class)),
			audits: new TokenSetAudits(
				audit: new ShippedTokenSetAuditService(contrast: new ContrastService(), parser: $parser),
				vocabularyAudit: new TokenSetVocabularyAuditService(parser: $parser)
			)
		);
	}//end service()
}//end class
