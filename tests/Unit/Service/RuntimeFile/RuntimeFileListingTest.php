<?php

/**
 * Runtime files: what a store's directory listing holds, and in which order.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/runtime-file-storage/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service\RuntimeFile;

use OCA\Thematiq\Service\RuntimeFile\AppDataRuntimeFileStore;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Service\RuntimeFile\SetFileReader;
use OCP\Files\IAppData;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use PHPUnit\Framework\TestCase;

/**
 * A listing holds only names thematiq writes at runtime, sorted, and a read of
 * a file that is not there is null.
 */
class RuntimeFileListingTest extends TestCase {

	/**
	 * The store dir.
	 *
	 * @var string
	 */
	private string $storeDir;

	/**
	 * Set up each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->storeDir = sys_get_temp_dir() . '/thematiq-listing-' . bin2hex(random_bytes(4));
		mkdir($this->storeDir . '/css/tokens/dark', 0777, true);
		file_put_contents($this->storeDir . '/css/tokens/custom-zeta.css', 'z');
		file_put_contents($this->storeDir . '/css/tokens/custom-alpha.css', 'a');
		file_put_contents($this->storeDir . '/css/tokens/custom-mid.css', 'm');
		file_put_contents($this->storeDir . '/css/tokens/notes.txt', 'not a runtime name');
	}//end setUp()

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach (['css/tokens/custom-zeta.css', 'css/tokens/custom-alpha.css', 'css/tokens/custom-mid.css', 'css/tokens/notes.txt'] as $file) {
			@unlink($this->storeDir . '/' . $file);
		}

		@rmdir($this->storeDir . '/css/tokens/dark');
		@rmdir($this->storeDir . '/css/tokens');
		@rmdir($this->storeDir . '/css');
		@rmdir($this->storeDir);
		parent::tearDown();
	}//end tearDown()

	/**
	 * The directory store lists its runtime files sorted, without subdirectories or other files.
	 *
	 * @return void
	 */
	public function testTheDirectoryStoreListsRuntimeFilesSorted(): void {
		$store = new DirectoryRuntimeFileStore(root: $this->storeDir);

		$this->assertSame(
			expected: ['css/tokens/custom-alpha.css', 'css/tokens/custom-mid.css', 'css/tokens/custom-zeta.css'],
			actual: $store->listDirectory('css/tokens')
		);
		$this->assertSame(expected: 'm', actual: $store->read('css/tokens/custom-mid.css'));
		$this->assertNull(actual: $store->read('css/tokens/custom-none.css'));
	}//end testTheDirectoryStoreListsRuntimeFilesSorted()

	/**
	 * The app data store lists its runtime files sorted, without other files.
	 *
	 * @return void
	 */
	public function testTheAppDataStoreListsRuntimeFilesSorted(): void {
		$files = [];
		foreach (['custom-zeta.css', 'notes.txt', 'custom-alpha.css', 'custom-mid.css'] as $name) {
			$file = $this->createMock(originalClassName: ISimpleFile::class);
			$file->method('getName')->willReturn($name);
			$files[] = $file;
		}

		$folder = $this->createMock(originalClassName: ISimpleFolder::class);
		$folder->method('getDirectoryListing')->willReturn($files);
		$appData = $this->createMock(originalClassName: IAppData::class);
		$appData->method('getFolder')->willReturn($folder);

		$this->assertSame(
			expected: ['css/tokens/custom-alpha.css', 'css/tokens/custom-mid.css', 'css/tokens/custom-zeta.css'],
			actual: (new AppDataRuntimeFileStore(appData: $appData))->listDirectory('css/tokens')
		);
	}//end testTheAppDataStoreListsRuntimeFilesSorted()

	/**
	 * The set file reader reads a release file, and null for a file that is not there.
	 *
	 * @return void
	 */
	public function testTheSetFileReaderReadsAReleaseFile(): void {
		$reader = new SetFileReader();

		$this->assertSame(expected: 'a', actual: $reader->read(appPath: $this->storeDir . '/', name: 'css/tokens/custom-alpha.css', store: null));
		$this->assertNull(actual: $reader->read(appPath: $this->storeDir, name: 'css/tokens/absent.css', store: null));
		$this->assertNull(actual: $reader->read(appPath: $this->storeDir, name: 'css/tokens/dark', store: null));
	}//end testTheSetFileReaderReadsAReleaseFile()
}//end class
