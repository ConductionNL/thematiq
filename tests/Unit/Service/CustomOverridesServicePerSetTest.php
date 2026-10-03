<?php

/**
 * Unit tests for which overrides file CustomOverridesService reads and writes.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCP\App\IAppManager;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * A set on no design system — the stock set and every theme saved off it —
 * keeps its edits in a file of its own, so a value pinned on another theme can
 * never show on stock. These pin which file each kind of set reads and writes.
 */
class CustomOverridesServicePerSetTest extends TestCase {

	/**
	 * Temporary app root with a token-sets.json.
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
	 * @var CustomOverridesService
	 */
	private CustomOverridesService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/thematiq-perset-' . bin2hex(random_bytes(4));
		mkdir($this->appDir . '/css', 0777, true);
		file_put_contents(
			$this->appDir . '/token-sets.json',
			json_encode(
				[
					['id' => 'nextcloud', 'name' => 'Nextcloud (Base)', 'design_system' => 'none'],
					['id' => 'amsterdam', 'name' => 'Amsterdam', 'design_system' => 'nldesign'],
				]
			)
		);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->values[$key] ?? $default)
		);

		$parser = new CssParserService();
		$darkPalette = new DarkPaletteService(new ContrastService(), $parser, $appManager, $this->createMock(LoggerInterface::class));

		$this->service = new CustomOverridesService(
			new DirectoryRuntimeFileStore($appManager->getAppPath('thematiq')),
			$parser,
			$darkPalette,
			$config,
			new DesignSystemService($appManager, $config)
		);
	}//end setUp()

	protected function tearDown(): void {
		foreach (glob($this->appDir . '/css/*') ?: [] as $file) {
			unlink($file);
		}

		rmdir($this->appDir . '/css');
		unlink($this->appDir . '/token-sets.json');
		rmdir($this->appDir);
		parent::tearDown();
	}//end tearDown()

	/**
	 * The file name each kind of set is given.
	 */
	public function testFileForNamesAFileOfItsOwnOnlyForASetOnNoDesignSystem(): void {
		$this->assertSame('custom-overrides', CustomOverridesService::fileFor(tokenSet: 'amsterdam', designSystemId: 'nldesign'));
		$this->assertSame('custom-overrides-nextcloud', CustomOverridesService::fileFor(tokenSet: 'nextcloud', designSystemId: 'nldesign'));
		$this->assertSame('custom-overrides-custom-openwoo', CustomOverridesService::fileFor(tokenSet: 'custom-openwoo', designSystemId: 'none'));
		$this->assertSame('custom-overrides-custom-openwoo', CustomOverridesService::fileFor(tokenSet: 'Custom-Open/Woo', designSystemId: 'none'));
	}//end testFileForNamesAFileOfItsOwnOnlyForASetOnNoDesignSystem()

	/**
	 * The stock set writes its own file, and the shared file is untouched.
	 */
	public function testTheStockSetWritesItsOwnFile(): void {
		$this->service->write(tokens: ['--color-primary' => '#112233'], tokenSet: 'nextcloud');

		$this->assertFileExists($this->appDir . '/css/custom-overrides-nextcloud.css');
		$this->assertFileDoesNotExist($this->appDir . '/css/custom-overrides.css');
		$this->assertSame(['--color-primary' => '#112233'], $this->service->read(tokenSet: 'nextcloud'));
	}//end testTheStockSetWritesItsOwnFile()

	/**
	 * A set on a design system shares the one file, and the stock set does
	 * not see what it pinned.
	 */
	public function testASetOnADesignSystemSharesTheFileAndStockDoesNotSeeIt(): void {
		$this->service->write(tokens: ['--color-primary' => '#445566'], tokenSet: 'amsterdam');

		$this->assertFileExists($this->appDir . '/css/custom-overrides.css');
		$this->assertSame([], $this->service->read(tokenSet: 'nextcloud'));
	}//end testASetOnADesignSystemSharesTheFileAndStockDoesNotSeeIt()

	/**
	 * Without a set named, the instance's active set is meant.
	 */
	public function testNoSetNamedMeansTheActiveSet(): void {
		$this->values['token_set'] = 'nextcloud';
		$this->service->ensureExists();

		$this->assertFileExists($this->appDir . '/css/custom-overrides-nextcloud.css');
		$this->assertSame([], $this->service->read());
	}//end testNoSetNamedMeansTheActiveSet()

	/**
	 * The overrides file is the only store: writing, reading and creating it
	 * never writes or deletes an app config value, so a file that survives a
	 * disable/enable cycle is all a re-enabled app needs.
	 *
	 * @spec openspec/specs/custom-css-overrides/spec.md#token-overrides-survive-app-reinstall-if-css-directory-is-preserved
	 */
	public function testOverridesLiveInTheFileAndNeverInAppConfig(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($key === 'token_set' ? 'amsterdam' : $default)
		);
		$config->expects($this->never())->method('setAppValue');
		$config->expects($this->never())->method('deleteAppValue');
		$parser = new CssParserService();
		$service = new CustomOverridesService(
			new DirectoryRuntimeFileStore($this->appDir),
			$parser,
			new DarkPaletteService(new ContrastService(), $parser, $appManager, $this->createMock(LoggerInterface::class)),
			$config,
			new DesignSystemService($appManager, $config)
		);

		$service->ensureExists();
		$service->write(tokens: ['--color-primary' => '#445566']);

		// The value comes back from the file alone.
		$this->assertSame(['--color-primary' => '#445566'], $service->read());
		$this->assertStringContainsString('--color-primary: #445566', (string)file_get_contents($this->appDir . '/css/custom-overrides.css'));
	}//end testOverridesLiveInTheFileAndNeverInAppConfig()
}//end class
