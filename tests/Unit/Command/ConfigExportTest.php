<?php

/**
 * Unit tests for the ConfigExport occ command.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/config-portability/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Command;

use OCA\Thematiq\Command\ConfigExport;
use OCA\Thematiq\Service\BrandingPackageService;
use OCA\Thematiq\Service\ConfigBundleService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Pins every exit path of `thematiq:config:export`: package, export failure,
 * unencodable bundle, file write (success and failure) and stdout.
 */
class ConfigExportTest extends TestCase {

	/**
	 * The mocked configuration bundle service.
	 *
	 * @var ConfigBundleService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $service;

	/**
	 * The mocked branding package service.
	 *
	 * @var BrandingPackageService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $packages;

	/**
	 * The command tester.
	 *
	 * @var CommandTester
	 */
	private CommandTester $tester;

	/**
	 * A temp file path (cleaned up in tearDown).
	 *
	 * @var string|null
	 */
	private ?string $path = null;

	/**
	 * Set up the command with mocked services.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->service = $this->createMock(originalClassName: ConfigBundleService::class);
		$this->packages = $this->createMock(originalClassName: BrandingPackageService::class);

		$application = new Application();
		$application->add(new ConfigExport(service: $this->service, packages: $this->packages));

		$this->tester = new CommandTester($application->find('thematiq:config:export'));
	}//end setUp()

	/**
	 * Remove the temp file after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ($this->path !== null && file_exists($this->path) === true) {
			unlink($this->path);
		}

		parent::tearDown();
	}//end tearDown()

	/**
	 * Without a file the bundle goes to stdout as pretty JSON.
	 *
	 * @return void
	 */
	public function testPrintsTheBundleToStdout(): void {
		$this->service->method('export')->willReturn(['a' => 'b/c']);

		$this->assertSame(expected: Command::SUCCESS, actual: $this->tester->execute([]));
		$this->assertSame(expected: "{\n    \"a\": \"b/c\"\n}\n", actual: $this->tester->getDisplay());
	}//end testPrintsTheBundleToStdout()

	/**
	 * A file argument writes the JSON plus a newline and reports the path.
	 *
	 * @return void
	 */
	public function testWritesTheBundleToAFile(): void {
		$this->service->method('export')->willReturn(['a' => 1]);
		$this->path = (string)tempnam(sys_get_temp_dir(), 'thematiq-export-');

		$this->assertSame(expected: Command::SUCCESS, actual: $this->tester->execute(['file' => $this->path]));
		$this->assertSame(expected: "{\n    \"a\": 1\n}" . PHP_EOL, actual: file_get_contents($this->path));
		$this->assertSame(expected: 'Configuration bundle written to ' . $this->path . "\n", actual: $this->tester->getDisplay());
	}//end testWritesTheBundleToAFile()

	/**
	 * An unwritable file path fails with the path named.
	 *
	 * @return void
	 */
	public function testAnUnwritableFileFails(): void {
		$this->service->method('export')->willReturn(['a' => 1]);
		$path = sys_get_temp_dir() . '/thematiq-missing-dir-' . uniqid() . '/bundle.json';

		$this->assertSame(expected: Command::FAILURE, actual: @$this->tester->execute(['file' => $path]));
		$this->assertSame(expected: 'Could not write the bundle to ' . $path . "\n", actual: $this->tester->getDisplay());
	}//end testAnUnwritableFileFails()

	/**
	 * An empty file argument still prints to stdout.
	 *
	 * @return void
	 */
	public function testAnEmptyFileArgumentPrintsToStdout(): void {
		$this->service->method('export')->willReturn([]);

		$this->assertSame(expected: Command::SUCCESS, actual: $this->tester->execute(['file' => '']));
		$this->assertSame(expected: "[]\n", actual: $this->tester->getDisplay());
	}//end testAnEmptyFileArgumentPrintsToStdout()

	/**
	 * A failing export reports the reason and exits non-zero.
	 *
	 * @return void
	 */
	public function testAFailingExportFails(): void {
		$this->service->method('export')->willThrowException(new RuntimeException('boom'));

		$this->assertSame(expected: Command::FAILURE, actual: $this->tester->execute([]));
		$this->assertSame(expected: "Configuration export failed: boom\n", actual: $this->tester->getDisplay());
	}//end testAFailingExportFails()

	/**
	 * A bundle json_encode refuses fails without output of the bundle.
	 *
	 * @return void
	 */
	public function testAnUnencodableBundleFails(): void {
		$this->service->method('export')->willReturn(['a' => INF]);

		$this->assertSame(expected: Command::FAILURE, actual: $this->tester->execute([]));
		$this->assertSame(
			expected: "Configuration export failed: could not encode the bundle as JSON.\n",
			actual: $this->tester->getDisplay()
		);
	}//end testAnUnencodableBundleFails()

	/**
	 * `--package` writes a package and never exports a bare bundle.
	 *
	 * @return void
	 */
	public function testThePackageOptionWritesAPackage(): void {
		$this->service->expects($this->never())->method('export');
		$this->packages->expects($this->once())->method('export')->with('/tmp/pkg')->willReturn(['a', 'b']);

		$this->assertSame(expected: Command::SUCCESS, actual: $this->tester->execute(['--package' => '/tmp/pkg']));
		$this->assertSame(expected: "Branding package written to /tmp/pkg (2 files).\n", actual: $this->tester->getDisplay());
	}//end testThePackageOptionWritesAPackage()

	/**
	 * A failing package export reports the reason.
	 *
	 * @return void
	 */
	public function testAFailingPackageExportFails(): void {
		$this->packages->method('export')->willThrowException(new RuntimeException('no dir'));

		$this->assertSame(expected: Command::FAILURE, actual: $this->tester->execute(['--package' => '/tmp/pkg']));
		$this->assertSame(expected: "Branding package export failed: no dir\n", actual: $this->tester->getDisplay());
	}//end testAFailingPackageExportFails()

	/**
	 * An empty `--package` falls through to the bundle export.
	 *
	 * @return void
	 */
	public function testAnEmptyPackageOptionExportsTheBundle(): void {
		$this->service->method('export')->willReturn([]);
		$this->packages->expects($this->never())->method('export');

		$this->assertSame(expected: Command::SUCCESS, actual: $this->tester->execute(['--package' => '']));
	}//end testAnEmptyPackageOptionExportsTheBundle()
}//end class
