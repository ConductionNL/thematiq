<?php

/**
 * Unit tests for TokenSetService laying captured branding over a set's theming.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
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
 * The branding a theme captured when it was saved is what applying the theme
 * brings back, so it replaces the set's own theming block — for a shipped set
 * (whose metadata is a read-only file) as much as for a custom one.
 */
class TokenSetServiceCapturedThemingTest extends TestCase {

	/**
	 * The temp app directory.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * The app values, by key.
	 *
	 * @var array<string, string>
	 */
	private array $values = [];

	/**
	 * The service under test.
	 *
	 * @var TokenSetService
	 */
	private TokenSetService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/thematiq-captured-' . uniqid();
		mkdir($this->appDir . '/css/tokens', 0777, true);
		file_put_contents($this->appDir . '/css/tokens/amsterdam.css', ':root { --nldesign-color-primary: #ec0000; }');
		file_put_contents(
			$this->appDir . '/token-sets.json',
			json_encode([['id' => 'amsterdam', 'name' => 'Amsterdam', 'theming' => ['primary_color' => '#ec0000']]])
		);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->values[$key] ?? $default)
		);

		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->createMock(ICache::class));

		$this->service = new TokenSetService(
			$appManager,
			$config,
			$this->createMock(LoggerInterface::class),
			new ShippedTokenSetAuditService(new ContrastService(), new CssParserService()),
			$cacheFactory,
			new TokenSetVocabularyAuditService(new CssParserService())
		);
	}//end setUp()

	protected function tearDown(): void {
		@unlink($this->appDir . '/css/tokens/amsterdam.css');
		@unlink($this->appDir . '/token-sets.json');
		@rmdir($this->appDir . '/css/tokens');
		@rmdir($this->appDir . '/css');
		@rmdir($this->appDir);
		parent::tearDown();
	}//end tearDown()

	/**
	 * The theming of a set, by id.
	 *
	 * @param string $id The set id.
	 *
	 * @return array<string, mixed>|null The theming block.
	 */
	private function themingOf(string $id): ?array {
		$byId = array_column($this->service->getAvailableTokenSets(), null, 'id');
		return ($byId[$id]['theming'] ?? null);
	}//end themingOf()

	/**
	 * A set's own theming is served while it captured nothing.
	 */
	public function testAShippedSetKeepsItsOwnThemingUntilItCapturesAny(): void {
		$this->assertSame(['primary_color' => '#ec0000'], $this->themingOf('amsterdam'));
	}//end testAShippedSetKeepsItsOwnThemingUntilItCapturesAny()

	/**
	 * Captured branding replaces the set's own theming.
	 */
	public function testCapturedBrandingReplacesTheSetsOwn(): void {
		$captured = ['captured' => true, 'primary_color' => '#23845c', 'background_mode' => 'color'];
		$this->values['captured_theming'] = json_encode(['amsterdam' => $captured]);

		$this->assertSame($captured, $this->themingOf('amsterdam'));
	}//end testCapturedBrandingReplacesTheSetsOwn()

	/**
	 * An unreadable captured store is ignored rather than breaking discovery.
	 */
	public function testAnUnreadableStoreIsIgnored(): void {
		$this->values['captured_theming'] = 'not json';

		$this->assertSame(['primary_color' => '#ec0000'], $this->themingOf('amsterdam'));
	}//end testAnUnreadableStoreIsIgnored()
}//end class
