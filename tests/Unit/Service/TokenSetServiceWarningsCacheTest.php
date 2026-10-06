<?php

/**
 * Unit tests for the shipped-set audit cache and the single-set summary.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\RuntimeFile\SetFileReader;
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
 * The three audits read every stylesheet under `css/` for every set, and
 * `Capabilities` used to reach them on every page (~1.5 s for 61 sets,
 * measured 2026-10-06). These tests pin the two fixes: audits are cached on
 * their inputs, and one set's summary never runs them.
 *
 * @spec openspec/specs/token-sets/spec.md
 */
class TokenSetServiceWarningsCacheTest extends TestCase {

	/**
	 * The temp app directory standing in for the app path.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * In-memory store behind the distributed cache, shared across services.
	 *
	 * @var array<string, mixed>
	 */
	private array $distributed = [];

	/**
	 * In-memory store behind the local cache, shared across services.
	 *
	 * @var array<string, mixed>
	 */
	private array $local = [];

	/**
	 * How often the contrast audit ran.
	 *
	 * @var int
	 */
	private int $auditRuns = 0;

	/**
	 * Set up a temp app dir with two shipped sets.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/thematiq-warnings-cache-' . uniqid();
		mkdir($this->appDir . '/css/tokens', 0777, true);
		file_put_contents($this->appDir . '/css/tokens/utrecht.css', ':root { --a: #000; }');
		file_put_contents($this->appDir . '/css/tokens/denhaag.css', ':root { --a: #111; }');
		file_put_contents(
			$this->appDir . '/token-sets.json',
			json_encode(
				[
					['id' => 'utrecht', 'name' => 'Gemeente Utrecht', 'upstreamVersion' => '1.2.0'],
					['id' => 'denhaag', 'name' => 'Gemeente Den Haag'],
				]
			)
		);
	}//end setUp()

	/**
	 * Remove the temp app dir.
	 */
	protected function tearDown(): void {
		foreach (glob($this->appDir . '/css/tokens/*') ?: [] as $file) {
			unlink($file);
		}

		@unlink($this->appDir . '/token-sets.json');
		@rmdir($this->appDir . '/css/tokens');
		@rmdir($this->appDir . '/css');
		@rmdir($this->appDir);
		parent::tearDown();
	}//end tearDown()

	/**
	 * A cache double backed by one of this test's arrays.
	 *
	 * @param string $store Which array: `distributed` or `local`.
	 *
	 * @return ICache The cache.
	 */
	private function arrayCache(string $store): ICache {
		$cache = $this->createMock(ICache::class);
		$cache->method('get')->willReturnCallback(fn (string $key) => ($this->{$store}[$key] ?? null));
		$cache->method('set')->willReturnCallback(
			function (string $key, $value) use ($store): bool {
				$this->{$store}[$key] = $value;

				return true;
			}
		);

		return $cache;
	}//end arrayCache()

	/**
	 * A fresh service, as a new request would build it, on the shared caches.
	 *
	 * @return TokenSetService The service.
	 */
	private function buildService(): TokenSetService {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);
		$appManager->method('getAppVersion')->willReturn('1.2.24');

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => $default
		);

		$audit = $this->createMock(ShippedTokenSetAuditService::class);
		$audit->method('warningsFor')->willReturnCallback(
			function (string $appPath, string $id): array {
				$this->auditRuns++;

				return $id === 'denhaag' ? [['type' => 'contrast']] : [];
			}
		);
		$vocabulary = $this->createMock(TokenSetVocabularyAuditService::class);
		$vocabulary->method('warningsFor')->willReturn([]);
		$font = $this->createMock(TokenSetFontAuditService::class);
		$font->method('warningsFor')->willReturn([]);

		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturnCallback(fn () => $this->arrayCache('distributed'));
		$cacheFactory->method('createLocal')->willReturnCallback(fn () => $this->arrayCache('local'));

		return new TokenSetService(
			$appManager,
			$config,
			$this->createMock(LoggerInterface::class),
			$audit,
			$cacheFactory,
			$vocabulary,
			null,
			new SetFileReader(),
			$font
		);
	}//end buildService()

	/**
	 * A second request reuses the warnings and runs no audit.
	 */
	public function testSecondRequestServesWarningsFromTheCache(): void {
		$first = $this->buildService()->getAvailableTokenSets();
		$this->assertSame(2, $this->auditRuns);

		$second = $this->buildService()->getAvailableTokenSets();
		$this->assertSame(2, $this->auditRuns, 'the second request must not audit again');
		$this->assertSame($first, $second);
		$this->assertSame([['type' => 'contrast']], array_column($second, null, 'id')['denhaag']['warnings']);
	}//end testSecondRequestServesWarningsFromTheCache()

	/**
	 * An edited stylesheet re-audits once the fingerprint is recomputed.
	 */
	public function testAnEditedStylesheetIsAuditedAgain(): void {
		$this->buildService()->getAvailableTokenSets();
		$this->assertSame(2, $this->auditRuns);

		file_put_contents($this->appDir . '/css/tokens/utrecht.css', ':root { --a: #000; --b: #fff; }');
		// The fingerprint is trusted for a minute; expire it as time would.
		$this->local = [];

		$this->buildService()->getAvailableTokenSets();
		$this->assertSame(4, $this->auditRuns, 'a changed release must not serve stale warnings');
	}//end testAnEditedStylesheetIsAuditedAgain()

	/**
	 * One set's summary matches the catalogue entry and runs no audit.
	 */
	public function testSummaryMatchesTheCatalogueWithoutAuditing(): void {
		$summary = $this->buildService()->getTokenSetSummary(tokenSetId: 'utrecht');

		$this->assertSame(['name' => 'Gemeente Utrecht', 'upstreamVersion' => '1.2.0'], $summary);
		$this->assertSame(0, $this->auditRuns);
		$this->assertSame(['name' => 'Gemeente Den Haag', 'upstreamVersion' => null], $this->buildService()->getTokenSetSummary(tokenSetId: 'denhaag'));
	}//end testSummaryMatchesTheCatalogueWithoutAuditing()

	/**
	 * A set with no stylesheet has no summary.
	 */
	public function testSummaryOfAMissingSetIsNull(): void {
		$this->assertNull($this->buildService()->getTokenSetSummary(tokenSetId: 'onbekend'));
		$this->assertNull($this->buildService()->getTokenSetSummary(tokenSetId: '../etc'));
	}//end testSummaryOfAMissingSetIsNull()
}//end class
