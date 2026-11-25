<?php

/**
 * Tests for AppDataRuntimeFileStore.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Tests
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/runtime-file-storage/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service\RuntimeFile;

use OCA\Thematiq\Service\RuntimeFile\AppDataRuntimeFileStore;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Writing over a file, a write that fails, deleting and the revision.
 *
 * @spec openspec/specs/runtime-file-storage/spec.md
 */
final class AppDataRuntimeFileStoreTest extends TestCase {

	/**
	 * A store whose app data hands out this folder for `css`.
	 *
	 * @param ISimpleFolder $folder The folder.
	 *
	 * @return AppDataRuntimeFileStore
	 */
	private function store(ISimpleFolder $folder): AppDataRuntimeFileStore {
		$appData = $this->createMock(originalClassName: IAppData::class);
		$appData->method('getFolder')->with('css')->willReturn($folder);

		return new AppDataRuntimeFileStore(appData: $appData);
	}//end store()

	/**
	 * A file that exists is overwritten in place, not created again.
	 *
	 * @return void
	 */
	public function testAWriteReplacesAnExistingFileInPlace(): void {
		$file = $this->createMock(originalClassName: ISimpleFile::class);
		$file->expects($this->once())->method('putContent')->with('b {}');
		$folder = $this->createMock(originalClassName: ISimpleFolder::class);
		$folder->method('fileExists')->with('custom-css.css')->willReturn(true);
		$folder->method('getFile')->with('custom-css.css')->willReturn($file);
		$folder->expects($this->never())->method('newFile');

		$this->store(folder: $folder)->write('css/custom-css.css', 'b {}');
	}//end testAWriteReplacesAnExistingFileInPlace()

	/**
	 * A write that fails says which file and keeps the cause.
	 *
	 * @return void
	 */
	public function testAFailedWriteNamesTheFileAndKeepsTheCause(): void {
		$cause = new NotPermittedException('read-only');
		$folder = $this->createMock(originalClassName: ISimpleFolder::class);
		$folder->method('fileExists')->willReturn(false);
		$folder->method('newFile')->willThrowException($cause);

		try {
			$this->store(folder: $folder)->write('css/custom-css.css', 'a {}');
			$this->fail(message: 'The write did not fail.');
		} catch (RuntimeException $e) {
			$this->assertSame(expected: 'Could not store runtime file css/custom-css.css: read-only', actual: $e->getMessage());
			$this->assertSame(expected: $cause, actual: $e->getPrevious());
		}
	}//end testAFailedWriteNamesTheFileAndKeepsTheCause()

	/**
	 * A delete removes the file, and a file the store does not hold is not an error.
	 *
	 * @return void
	 */
	public function testADeleteRemovesTheFileAndAMissingOneIsNoError(): void {
		$file = $this->createMock(originalClassName: ISimpleFile::class);
		$file->expects($this->once())->method('delete');
		$folder = $this->createMock(originalClassName: ISimpleFolder::class);
		$folder->method('getFile')->willReturnCallback(
			function (string $name) use ($file): ISimpleFile {
				if ($name !== 'custom-css.css') {
					throw new NotFoundException();
				}

				return $file;
			}
		);
		$store = $this->store(folder: $folder);

		$store->delete('css/custom-css.css');
		$store->delete('css/custom-overrides.css');
	}//end testADeleteRemovesTheFileAndAMissingOneIsNoError()

	/**
	 * The revision is twelve characters that follow the etag, the time and the size.
	 *
	 * @return void
	 */
	public function testTheRevisionFollowsTheEtagTimeAndSize(): void {
		$revision = function (string $etag, int $mtime, int $size): string {
			$file = $this->createMock(originalClassName: ISimpleFile::class);
			$file->method('getETag')->willReturn($etag);
			$file->method('getMTime')->willReturn($mtime);
			$file->method('getSize')->willReturn($size);
			$folder = $this->createMock(originalClassName: ISimpleFolder::class);
			$folder->method('getFile')->willReturn($file);

			return $this->store(folder: $folder)->revision('css/custom-css.css');
		};

		$this->assertSame(expected: substr(sha1('abc:100:7'), 0, 12), actual: $revision('abc', 100, 7));
		$this->assertSame(expected: $revision('abc', 100, 7), actual: $revision('abc', 100, 7));
		$this->assertNotSame(expected: $revision('abc', 100, 7), actual: $revision('abd', 100, 7));
		$this->assertNotSame(expected: $revision('abc', 100, 7), actual: $revision('abc', 101, 7));
		$this->assertNotSame(expected: $revision('abc', 100, 7), actual: $revision('abc', 100, 8));
	}//end testTheRevisionFollowsTheEtagTimeAndSize()
}//end class
