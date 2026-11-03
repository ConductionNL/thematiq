<?php

/**
 * Unit tests for DesignSystemService::hasGeneratedDarkVariant().
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/dark-mode/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\DesignSystemService;
use OCP\App\IAppManager;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

/**
 * Covers the small addition backing `Application::injectDarkVariantStyle()`'s
 * file-existence check (reused from this already-injected service instead of
 * adding a fresh IAppManager dependency to Application — see
 * tasks.md#task-3.1).
 */
class DesignSystemServiceHasDarkVariantTest extends TestCase {

	/**
	 * The temp app directory standing in for the nldesign app path.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * The service under test.
	 *
	 * @var DesignSystemService
	 */
	private DesignSystemService $service;

	/**
	 * Set up a temp app dir with a `css/tokens/dark/` directory.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/nldesign-dsservice-test-' . uniqid();
		mkdir($this->appDir . '/css/tokens/dark', 0777, true);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);

		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturn('');

		$this->service = new DesignSystemService(appManager: $appManager, config: $config);
	}//end setUp()

	/**
	 * Remove the temp app dir after each test.
	 */
	protected function tearDown(): void {
		$this->rrmdir(dir: $this->appDir);
		parent::tearDown();
	}//end tearDown()

	/**
	 * Recursively remove a directory tree.
	 *
	 * @param string $dir The directory to remove.
	 *
	 * @return void
	 */
	private function rrmdir(string $dir): void {
		if (is_dir($dir) === false) {
			return;
		}

		foreach (scandir($dir) as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$path = $dir . '/' . $entry;
			if (is_dir($path) === true) {
				$this->rrmdir(dir: $path);
			} else {
				unlink($path);
			}
		}

		rmdir($dir);
	}//end rrmdir()

	/**
	 * True when the generated file exists.
	 */
	public function testTrueWhenFileExists(): void {
		file_put_contents($this->appDir . '/css/tokens/dark/amsterdam.css', '/* generated */');

		$this->assertTrue(condition: $this->service->hasGeneratedDarkVariant(tokenSetId: 'amsterdam'));
	}//end testTrueWhenFileExists()

	/**
	 * False when the file is absent — no error.
	 */
	public function testFalseWhenFileAbsent(): void {
		$this->assertFalse(condition: $this->service->hasGeneratedDarkVariant(tokenSetId: 'nonexistent'));
	}//end testFalseWhenFileAbsent()
}//end class
