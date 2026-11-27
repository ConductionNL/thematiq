<?php

/**
 * Unit tests for the shipped sets' audit warnings kept in app data.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\AuditWarningsStore;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Stored warnings come back for unchanged app files, never for changed ones,
 * and only the sets of the last catalogue read are kept.
 */
class AuditWarningsStoreTest extends TestCase {

	/**
	 * The temp app directory standing in for the app path.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * The app data files by folder and name: what a later request would read.
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $files = [];

	/**
	 * How often a file was written.
	 *
	 * @var integer
	 */
	private int $writes = 0;

	/**
	 * Set up a temp app dir with one stylesheet and both manifests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/thematiq-audit-store-' . uniqid();
		mkdir($this->appDir . '/css/tokens', 0777, true);
		file_put_contents($this->appDir . '/css/tokens/utrecht.css', ':root { --nldesign-color-primary: #007bc7; }');
		file_put_contents($this->appDir . '/token-sets.json', '[]');
		file_put_contents($this->appDir . '/design-systems.json', '[]');
	}//end setUp()

	/**
	 * Remove the temp app dir after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		@unlink($this->appDir . '/css/tokens/utrecht.css');
		@unlink($this->appDir . '/token-sets.json');
		@unlink($this->appDir . '/design-systems.json');
		@rmdir($this->appDir . '/css/tokens');
		@rmdir($this->appDir . '/css');
		@rmdir($this->appDir);
		parent::tearDown();
	}//end tearDown()

	/**
	 * A store over the in-memory app data, as a new request would build it.
	 *
	 * @return AuditWarningsStore The store.
	 */
	private function newStore(): AuditWarningsStore {
		$appData = $this->createMock(originalClassName: IAppData::class);
		$appData->method('getFolder')->willReturnCallback(
			function (string $name): ISimpleFolder {
				if (isset($this->files[$name]) === false) {
					throw new NotFoundException($name);
				}

				return $this->folder(name: $name);
			}
		);
		$appData->method('newFolder')->willReturnCallback(
			function (string $name): ISimpleFolder {
				$this->files[$name] = [];
				return $this->folder(name: $name);
			}
		);

		return new AuditWarningsStore(appData: $appData, logger: $this->createMock(originalClassName: LoggerInterface::class));
	}//end newStore()

	/**
	 * An in-memory app data folder.
	 *
	 * @param string $name The folder name.
	 *
	 * @return ISimpleFolder The folder.
	 */
	private function folder(string $name): ISimpleFolder {
		$folder = $this->createMock(originalClassName: ISimpleFolder::class);
		$folder->method('fileExists')->willReturnCallback(fn (string $file): bool => isset($this->files[$name][$file]));
		$folder->method('newFile')->willReturnCallback(
			function (string $file, $content = null) use ($name): ISimpleFile {
				$this->files[$name][$file] = (string)$content;
				$this->writes++;
				return $this->createMock(originalClassName: ISimpleFile::class);
			}
		);
		$folder->method('getFile')->willReturnCallback(
			function (string $file) use ($name): ISimpleFile {
				if (isset($this->files[$name][$file]) === false) {
					throw new NotFoundException($file);
				}

				$simple = $this->createMock(originalClassName: ISimpleFile::class);
				$simple->method('getContent')->willReturnCallback(fn (): string => $this->files[$name][$file]);
				$simple->method('putContent')->willReturnCallback(
					function ($content) use ($name, $file): void {
						$this->files[$name][$file] = (string)$content;
						$this->writes++;
					}
				);
				return $simple;
			}
		);

		return $folder;
	}//end folder()

	/**
	 * With nothing stored, a set has no stored warnings.
	 *
	 * @return void
	 */
	public function testNothingStoredAnswersNull(): void {
		$this->assertNull($this->newStore()->get(appPath: $this->appDir, key: 'utrecht-1'));
	}//end testNothingStoredAnswersNull()

	/**
	 * Warnings written by one request are read by the next while the app files are unchanged.
	 *
	 * @return void
	 */
	public function testWrittenWarningsComeBackInTheNextRequest(): void {
		$store = $this->newStore();
		$store->put(key: 'utrecht-1', warnings: [['pair' => 'text', 'verdict' => 'fail']]);
		$store->write(appPath: $this->appDir);

		$this->assertSame(
			expected: [['pair' => 'text', 'verdict' => 'fail']],
			actual: $this->newStore()->get(appPath: $this->appDir, key: 'utrecht-1')
		);
		$this->assertNull($this->newStore()->get(appPath: $this->appDir, key: 'utrecht-2'));
	}//end testWrittenWarningsComeBackInTheNextRequest()

	/**
	 * A changed stylesheet under css/ (an upgrade, or an edit on a checkout) makes the stored warnings unused.
	 *
	 * @return void
	 */
	public function testChangedAppFilesAreNotServedOldWarnings(): void {
		$store = $this->newStore();
		$store->put(key: 'utrecht-1', warnings: [['pair' => 'text', 'verdict' => 'fail']]);
		$store->write(appPath: $this->appDir);

		file_put_contents($this->appDir . '/css/tokens/utrecht.css', ':root { --nldesign-color-primary: #154273; --x: 1; }');
		clearstatcache();

		$this->assertNull($this->newStore()->get(appPath: $this->appDir, key: 'utrecht-1'));
	}//end testChangedAppFilesAreNotServedOldWarnings()

	/**
	 * A read the local cache answered completely writes nothing; one audit writes once.
	 *
	 * @return void
	 */
	public function testOnlyAnAuditCausesAWrite(): void {
		$store = $this->newStore();
		$store->put(key: 'utrecht-1', warnings: [], known: true);
		$store->write(appPath: $this->appDir);
		$this->assertSame(expected: 0, actual: $this->writes);

		$store->put(key: 'amsterdam-1', warnings: []);
		$store->write(appPath: $this->appDir);
		$store->write(appPath: $this->appDir);
		$this->assertSame(expected: 1, actual: $this->writes);

		$next = $this->newStore();
		$this->assertSame(expected: [], actual: $next->get(appPath: $this->appDir, key: 'utrecht-1'), message: 'known warnings are kept in the write');
		$this->assertSame(expected: [], actual: $next->get(appPath: $this->appDir, key: 'amsterdam-1'));
	}//end testOnlyAnAuditCausesAWrite()

	/**
	 * A write keeps only the sets of this request, so warnings of a set's old CSS drop out.
	 *
	 * @return void
	 */
	public function testAWriteDropsSetsThisReadDidNotAskFor(): void {
		$first = $this->newStore();
		$first->put(key: 'utrecht-old', warnings: []);
		$first->write(appPath: $this->appDir);

		$second = $this->newStore();
		$second->put(key: 'utrecht-new', warnings: []);
		$second->write(appPath: $this->appDir);

		$this->assertNull($this->newStore()->get(appPath: $this->appDir, key: 'utrecht-old'));
		$this->assertSame(expected: [], actual: $this->newStore()->get(appPath: $this->appDir, key: 'utrecht-new'));
	}//end testAWriteDropsSetsThisReadDidNotAskFor()

	/**
	 * A damaged file is read as empty, not as warnings.
	 *
	 * @return void
	 */
	public function testDamagedFileIsReadAsEmpty(): void {
		$this->files[AuditWarningsStore::FOLDER] = [AuditWarningsStore::FILE => '{not json'];

		$this->assertNull($this->newStore()->get(appPath: $this->appDir, key: 'utrecht-1'));
	}//end testDamagedFileIsReadAsEmpty()
}//end class
