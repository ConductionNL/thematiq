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
use OCA\Thematiq\Service\OverridesSetContext;
use OCA\Thematiq\Service\OverridesStorage;
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

	/**
	 * Set up each test.
	 *
	 * @return void
	 */
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

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);

		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->values[$key] ?? $default)
		);

		$parser = new CssParserService();
		$darkPalette = new DarkPaletteService(contrast: new ContrastService(), parser: $parser, appManager: $appManager, logger: $this->createMock(originalClassName: LoggerInterface::class));

		$this->service = new CustomOverridesService(
			storage: new OverridesStorage(
				store: new DirectoryRuntimeFileStore(root: $appManager->getAppPath('thematiq')),
				cssParser: $parser,
				darkPalette: $darkPalette
			),
			setContext: new OverridesSetContext(
				config: $config,
				designSystems: new DesignSystemService(appManager: $appManager, config: $config)
			)
		);
	}//end setUp()

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
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
	 *
	 * @return void
	 */
	public function testFileForNamesAFileOfItsOwnOnlyForASetOnNoDesignSystem(): void {
		$this->assertSame(expected: 'custom-overrides', actual: CustomOverridesService::fileFor(tokenSet: 'amsterdam', designSystemId: 'nldesign'));
		$this->assertSame(expected: 'custom-overrides-nextcloud', actual: CustomOverridesService::fileFor(tokenSet: 'nextcloud', designSystemId: 'nldesign'));
		$this->assertSame(expected: 'custom-overrides-custom-openwoo', actual: CustomOverridesService::fileFor(tokenSet: 'custom-openwoo', designSystemId: 'none'));
		$this->assertSame(expected: 'custom-overrides-custom-openwoo', actual: CustomOverridesService::fileFor(tokenSet: 'Custom-Open/Woo', designSystemId: 'none'));
	}//end testFileForNamesAFileOfItsOwnOnlyForASetOnNoDesignSystem()

	/**
	 * The stock set writes its own file, and the shared file is untouched.
	 *
	 * @return void
	 */
	public function testTheStockSetWritesItsOwnFile(): void {
		$this->service->write(tokens: ['--color-primary' => '#112233'], tokenSet: 'nextcloud');

		$this->assertFileExists(filename: $this->appDir . '/css/custom-overrides-nextcloud.css');
		$this->assertFileDoesNotExist(filename: $this->appDir . '/css/custom-overrides.css');
		$this->assertSame(expected: ['--color-primary' => '#112233'], actual: $this->service->read(tokenSet: 'nextcloud'));
	}//end testTheStockSetWritesItsOwnFile()

	/**
	 * A set on a design system shares the one file, and the stock set does
	 * not see what it pinned.
	 *
	 * @return void
	 */
	public function testASetOnADesignSystemSharesTheFileAndStockDoesNotSeeIt(): void {
		$this->service->write(tokens: ['--color-primary' => '#445566'], tokenSet: 'amsterdam');

		$this->assertFileExists(filename: $this->appDir . '/css/custom-overrides.css');
		$this->assertSame(expected: [], actual: $this->service->read(tokenSet: 'nextcloud'));
	}//end testASetOnADesignSystemSharesTheFileAndStockDoesNotSeeIt()

	/**
	 * Without a set named, the instance's active set is meant.
	 *
	 * @return void
	 */
	public function testNoSetNamedMeansTheActiveSet(): void {
		$this->values['token_set'] = 'nextcloud';
		$this->service->ensureExists();

		$this->assertFileExists(filename: $this->appDir . '/css/custom-overrides-nextcloud.css');
		$this->assertSame(expected: [], actual: $this->service->read());
	}//end testNoSetNamedMeansTheActiveSet()

	/**
	 * The overrides file is the only store: writing, reading and creating it
	 * never writes or deletes an app config value, so a file that survives a
	 * disable/enable cycle is all a re-enabled app needs.
	 *
	 * @spec openspec/specs/custom-css-overrides/spec.md#token-overrides-survive-app-reinstall-if-css-directory-is-preserved
	 *
	 * @return void
	 */
	public function testOverridesLiveInTheFileAndNeverInAppConfig(): void {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($key === 'token_set' ? 'amsterdam' : $default)
		);
		$config->expects($this->never())->method('setAppValue');
		$config->expects($this->never())->method('deleteAppValue');
		$parser = new CssParserService();
		$service = new CustomOverridesService(
			storage: new OverridesStorage(
				store: new DirectoryRuntimeFileStore(root: $this->appDir),
				cssParser: $parser,
				darkPalette: new DarkPaletteService(contrast: new ContrastService(), parser: $parser, appManager: $appManager, logger: $this->createMock(originalClassName: LoggerInterface::class))
			),
			setContext: new OverridesSetContext(
				config: $config,
				designSystems: new DesignSystemService(appManager: $appManager, config: $config)
			)
		);

		$service->ensureExists();
		$service->write(tokens: ['--color-primary' => '#445566']);

		// The value comes back from the file alone.
		$this->assertSame(expected: ['--color-primary' => '#445566'], actual: $service->read());
		$this->assertStringContainsString(needle: '--color-primary: #445566', haystack: (string)file_get_contents($this->appDir . '/css/custom-overrides.css'));
	}//end testOverridesLiveInTheFileAndNeverInAppConfig()
}//end class
