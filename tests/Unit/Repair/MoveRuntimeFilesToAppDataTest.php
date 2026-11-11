<?php

/**
 * Tests for the repair step that moves runtime files into app data.
 *
 * @category  Test
 * @package   OCA\Thematiq\Tests\Unit\Repair
 * @author    Conduction B.V. <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://www.conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/runtime-file-storage/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Repair;

use OCA\Thematiq\Repair\MoveRuntimeFilesToAppData;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileStore;
use OCP\App\IAppManager;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * What the step reports, what it keeps when the store refuses, and what it logs.
 */
class MoveRuntimeFilesToAppDataTest extends TestCase {

	/**
	 * The app dir.
	 *
	 * @var string
	 */
	private string $appDir;

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
		$base = sys_get_temp_dir() . '/thematiq-move-' . bin2hex(random_bytes(4));
		$this->appDir = $base . '/app';
		$this->storeDir = $base . '/store';
		mkdir($this->appDir . '/css/tokens', 0777, true);
		mkdir($this->storeDir, 0777, true);
		file_put_contents($this->appDir . '/css/tokens/custom-new.css', ':root { --a: 1; }');
		file_put_contents($this->appDir . '/css/tokens/custom-old.css', ':root { --a: 2; }');
	}//end setUp()

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		exec('rm -rf ' . escapeshellarg(dirname($this->appDir)));
		parent::tearDown();
	}//end tearDown()

	/**
	 * The app manager, pointing at the test app directory.
	 *
	 * @return IAppManager The app manager.
	 */
	private function appManager(): IAppManager {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir . '/');

		return $appManager;
	}//end appManager()

	/**
	 * Only a file the store did not hold yet counts as moved; both leave the app directory.
	 *
	 * @return void
	 */
	public function testOnlyACopiedFileCountsAsMoved(): void {
		$store = new DirectoryRuntimeFileStore(root: $this->storeDir);
		$store->write('css/tokens/custom-old.css', ':root { --a: 3; }');
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('info')
			->with('thematiq: moved 1 runtime file(s) from the app directory into app data.');

		(new MoveRuntimeFilesToAppData(
			appManager: $this->appManager(),
			store: $store,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		))->run($output);

		$this->assertSame(expected: ':root { --a: 1; }', actual: $store->read('css/tokens/custom-new.css'));
		$this->assertSame(expected: ':root { --a: 3; }', actual: $store->read('css/tokens/custom-old.css'));
		$this->assertFileDoesNotExist(filename: $this->appDir . '/css/tokens/custom-new.css');
		$this->assertFileDoesNotExist(filename: $this->appDir . '/css/tokens/custom-old.css');
	}//end testOnlyACopiedFileCountsAsMoved()

	/**
	 * A store that throws keeps the file in the app directory, logs it, and does not count it.
	 *
	 * @return void
	 */
	public function testAFailingStoreKeepsTheFileAndLogsIt(): void {
		$store = $this->createMock(originalClassName: RuntimeFileStore::class);
		$store->method('exists')->willReturn(false);
		$store->method('write')->willThrowException(new RuntimeException('disk full'));
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->exactly(2))->method('warning')
			->with($this->stringContains(string: 'into app data; it stays in the app directory.'));
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('info')
			->with('thematiq: moved 0 runtime file(s) from the app directory into app data.');

		(new MoveRuntimeFilesToAppData(appManager: $this->appManager(), store: $store, logger: $logger))->run($output);

		$this->assertFileExists(filename: $this->appDir . '/css/tokens/custom-new.css');
		$this->assertFileExists(filename: $this->appDir . '/css/tokens/custom-old.css');
	}//end testAFailingStoreKeepsTheFileAndLogsIt()
}//end class
