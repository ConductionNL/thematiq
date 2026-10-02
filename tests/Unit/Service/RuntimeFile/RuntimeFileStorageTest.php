<?php

/**
 * Runtime files: the name policy, both stores and the locator.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/runtime-file-storage/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service\RuntimeFile;

use InvalidArgumentException;
use OCA\Thematiq\Service\RuntimeFile\AppDataRuntimeFileStore;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileLocator;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileNames;
use OCP\App\IAppManager;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\ITempManager;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

/**
 * What thematiq writes at runtime goes to a store, never to its app directory,
 * and every reader finds it through one locator.
 */
class RuntimeFileStorageTest extends TestCase {

	private string $appDir;
	private string $storeDir;

	protected function setUp(): void {
		parent::setUp();
		$base = sys_get_temp_dir() . '/thematiq-runtime-' . bin2hex(random_bytes(4));
		$this->appDir = $base . '/app';
		$this->storeDir = $base . '/store';
		mkdir($this->appDir . '/css/tokens/dark', 0777, true);
		mkdir($this->appDir . '/img/logos', 0777, true);
		mkdir($this->storeDir, 0777, true);
		file_put_contents($this->appDir . '/token-sets.json', json_encode([['id' => 'utrecht'], ['id' => 'nextcloud']]));
		file_put_contents($this->appDir . '/css/tokens/utrecht.css', ':root { --nldesign-color-primary: #24578f; }');
		file_put_contents($this->appDir . '/img/logos/utrecht.svg', '<svg/>');
	}//end setUp()

	protected function tearDown(): void {
		$this->remove(dirname($this->appDir));
		parent::tearDown();
	}//end tearDown()

	private function remove(string $path): void {
		if (is_dir($path) === true) {
			foreach (scandir($path) ?: [] as $entry) {
				if ($entry !== '.' && $entry !== '..') {
					$this->remove($path . '/' . $entry);
				}
			}

			rmdir($path);
			return;
		}

		if (is_file($path) === true) {
			unlink($path);
		}
	}//end remove()

	private function locator(): RuntimeFileLocator {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkTo')->willReturnCallback(static fn (string $app, string $file): string => '/custom_apps/' . $app . '/' . $file);
		$urls->method('linkToRoute')->willReturnCallback(static fn (string $route, array $args): string => '/index.php/apps/thematiq/runtime/' . $args['name']);
		$temp = $this->createMock(ITempManager::class);
		$temp->method('getTemporaryFile')->willReturnCallback(fn (string $postfix): string => $this->storeDir . '/tmp' . bin2hex(random_bytes(3)) . $postfix);

		return new RuntimeFileLocator($appManager, new DirectoryRuntimeFileStore($this->storeDir), $urls, $temp);
	}//end locator()

	/**
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public static function names(): array {
		return [
			'shared overrides' => ['css/custom-overrides.css', true],
			'per-set overrides' => ['css/custom-overrides-nextcloud.css', true],
			'custom css' => ['css/custom-css.css', true],
			'uploaded set' => ['css/tokens/gemeente-voorbeeld.css', true],
			'its dark variant' => ['css/tokens/dark/gemeente-voorbeeld.css', true],
			'captured logo' => ['img/logos/utrecht-captured-logoheader.png', true],
			'captured background' => ['img/backgrounds/utrecht-captured-background.jpg', true],
			'captured favicon' => ['img/logos/utrecht-captured-favicon.ico', true],
			'traversal' => ['css/tokens/../../config/config.php', false],
			'absolute' => ['/etc/passwd', false],
			'other directory' => ['lib/Service/Foo.php', false],
			'php in tokens' => ['css/tokens/evil.php', false],
			'upper case' => ['css/tokens/Utrecht.css', false],
			'leading dash' => ['css/tokens/-x.css', false],
			'empty' => ['', false],
		];
	}//end names()

	/**
	 * @dataProvider names
	 */
	public function testOnlyRuntimeNamesAreAllowed(string $name, bool $allowed): void {
		$this->assertSame($allowed, (new RuntimeFileNames())->isAllowed($name), $name);
	}//end testOnlyRuntimeNamesAreAllowed()

	public function testTheStoreRefusesANameOutsideTheSet(): void {
		$this->expectException(InvalidArgumentException::class);
		(new DirectoryRuntimeFileStore($this->storeDir))->write('css/tokens/../../config/config.php', 'x');
	}//end testTheStoreRefusesANameOutsideTheSet()

	public function testTheDirectoryStoreRoundTripsAndRevisesOnChange(): void {
		$store = new DirectoryRuntimeFileStore($this->storeDir);
		$this->assertNull($store->read('css/custom-css.css'));
		$this->assertSame('', $store->revision('css/custom-css.css'));

		$store->write('css/custom-css.css', 'a { color: red; }');
		$first = $store->revision('css/custom-css.css');
		$this->assertSame('a { color: red; }', $store->read('css/custom-css.css'));

		$store->write('css/custom-css.css', 'a { color: blue; }');
		$this->assertNotSame($first, $store->revision('css/custom-css.css'));

		$store->write('css/tokens/dark/x.css', ':root {}');
		$this->assertSame(['css/tokens/dark/x.css'], $store->listDirectory('css/tokens/dark'));

		$store->delete('css/custom-css.css');
		$store->delete('css/custom-css.css');
		$this->assertFalse($store->exists('css/custom-css.css'));
	}//end testTheDirectoryStoreRoundTripsAndRevisesOnChange()

	public function testAShippedSetIsReadFromTheReleaseEvenWhenTheStoreHoldsTheSameName(): void {
		$locator = $this->locator();
		$locator->store()->write('css/tokens/utrecht.css', ':root { --nldesign-color-primary: red; }');

		$this->assertTrue($locator->isProtected('css/tokens/utrecht.css'));
		$this->assertTrue($locator->isProtected('css/tokens/dark/utrecht.css'));
		$this->assertTrue($locator->isProtected('img/logos/utrecht.svg'));
		$this->assertStringContainsString('#24578f', (string)$locator->read('css/tokens/utrecht.css'));
		$this->assertSame('/custom_apps/thematiq/css/tokens/utrecht.css', $locator->url('css/tokens/utrecht.css'));
		$this->assertSame(['css/tokens/utrecht.css'], $locator->listDirectory('css/tokens', '.css'));
	}//end testAShippedSetIsReadFromTheReleaseEvenWhenTheStoreHoldsTheSameName()

	public function testAnUploadedSetIsReadFromTheStoreAndServedByRoute(): void {
		$locator = $this->locator();
		$locator->store()->write('css/tokens/gemeente-voorbeeld.css', ':root {}');

		$this->assertFalse($locator->isProtected('css/tokens/gemeente-voorbeeld.css'));
		$this->assertSame(':root {}', $locator->read('css/tokens/gemeente-voorbeeld.css'));
		$this->assertStringStartsWith('/index.php/apps/thematiq/runtime/css/tokens/gemeente-voorbeeld.css?v=', (string)$locator->url('css/tokens/gemeente-voorbeeld.css'));
		$this->assertSame(['css/tokens/gemeente-voorbeeld.css', 'css/tokens/utrecht.css'], $locator->listDirectory('css/tokens', '.css'));
		$this->assertFileDoesNotExist($this->appDir . '/css/tokens/gemeente-voorbeeld.css');
	}//end testAnUploadedSetIsReadFromTheStoreAndServedByRoute()

	public function testTheStoreWinsOverAStaleAppDirectoryCopyOfARuntimeFile(): void {
		// An older install whose copy could not be moved: saves go to the store,
		// so reads must too, or every save would silently stop applying.
		file_put_contents($this->appDir . '/css/custom-overrides.css', ':root { --color-primary: stale; }');
		$locator = $this->locator();
		$this->assertStringContainsString('stale', (string)$locator->read('css/custom-overrides.css'));

		$locator->store()->write('css/custom-overrides.css', ':root { --color-primary: fresh; }');
		$this->assertStringContainsString('fresh', (string)$locator->read('css/custom-overrides.css'));
		$this->assertStringStartsWith('/index.php/apps/thematiq/runtime/', (string)$locator->url('css/custom-overrides.css'));
	}//end testTheStoreWinsOverAStaleAppDirectoryCopyOfARuntimeFile()

	public function testALocalPathOfARuntimeImageIsATemporaryCopy(): void {
		$locator = $this->locator();
		$locator->store()->write('img/backgrounds/utrecht-captured-background.jpg', 'JPEGDATA');

		$path = (string)$locator->localPath('img/backgrounds/utrecht-captured-background.jpg');
		$this->assertStringEndsWith('.jpg', $path);
		$this->assertSame('JPEGDATA', file_get_contents($path));
		$this->assertStringStartsNotWith($this->appDir, $path);
		$this->assertNull($locator->localPath('img/backgrounds/missing.jpg'));
	}//end testALocalPathOfARuntimeImageIsATemporaryCopy()

	public function testTheAppDataStoreUsesOneFlatFolderPerDirectory(): void {
		$file = $this->createMock(ISimpleFile::class);
		$file->method('getContent')->willReturn('dark css');
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->method('getFile')->with('example.css')->willReturn($file);
		$folder->method('fileExists')->with('example.css')->willReturn(true);
		$appData = $this->createMock(IAppData::class);
		$appData->method('getFolder')->with('css-tokens-dark')->willReturn($folder);

		$store = new AppDataRuntimeFileStore($appData);
		$this->assertSame('dark css', $store->read('css/tokens/dark/example.css'));
		$this->assertTrue($store->exists('css/tokens/dark/example.css'));
	}//end testTheAppDataStoreUsesOneFlatFolderPerDirectory()

	public function testTheAppDataStoreCreatesItsFolderOnFirstWrite(): void {
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->method('fileExists')->willReturn(false);
		$folder->expects($this->once())->method('newFile')->with('custom-css.css', 'a {}');
		$appData = $this->createMock(IAppData::class);
		$appData->method('getFolder')->willThrowException(new NotFoundException());
		$appData->expects($this->once())->method('newFolder')->with('css')->willReturn($folder);

		(new AppDataRuntimeFileStore($appData))->write('css/custom-css.css', 'a {}');
	}//end testTheAppDataStoreCreatesItsFolderOnFirstWrite()

	public function testAMissingAppDataFileReadsAsAbsent(): void {
		$appData = $this->createMock(IAppData::class);
		$appData->method('getFolder')->willThrowException(new NotFoundException());

		$store = new AppDataRuntimeFileStore($appData);
		$this->assertNull($store->read('css/custom-css.css'));
		$this->assertFalse($store->exists('css/custom-css.css'));
		$this->assertSame('', $store->revision('css/custom-css.css'));
		$this->assertSame([], $store->listDirectory('css/tokens'));
	}//end testAMissingAppDataFileReadsAsAbsent()
}//end class
