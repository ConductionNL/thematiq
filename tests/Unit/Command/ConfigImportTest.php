<?php

/**
 * Unit tests for the ConfigImport occ command.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/config-portability/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Command;

use OCA\Thematiq\Command\ConfigImport;
use OCA\Thematiq\Service\BrandingPackageService;
use OCA\Thematiq\Service\ConfigBundleService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Covers tasks.md#task-4.3: `--dry-run` exit 0 with no writes attempted,
 * an invalid bundle exit 1 with the error listing, and a missing/unreadable
 * file exit 1 without ever calling the service.
 */
class ConfigImportTest extends TestCase {

	/**
	 * The mocked configuration bundle service.
	 *
	 * @var ConfigBundleService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $service;

	/**
	 * The command tester.
	 *
	 * @var CommandTester
	 */
	private CommandTester $tester;

	/**
	 * A temp bundle file path (cleaned up in tearDown).
	 *
	 * @var string|null
	 */
	private ?string $bundlePath = null;

	/**
	 * Set up the command with a mocked service.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->service = $this->createMock(ConfigBundleService::class);

		$command = new ConfigImport($this->service, $this->createMock(BrandingPackageService::class));
		$application = new Application();
		$application->add($command);

		$this->tester = new CommandTester($application->find('nldesign:config:import'));
	}//end setUp()

	/**
	 * Remove the temp bundle file after each test.
	 */
	protected function tearDown(): void {
		if ($this->bundlePath !== null && file_exists($this->bundlePath) === true) {
			unlink($this->bundlePath);
		}

		parent::tearDown();
	}//end tearDown()

	/**
	 * Write a bundle JSON string to a temp file and return its path.
	 *
	 * @param string $content The file content.
	 *
	 * @return string The temp file path.
	 */
	private function writeBundleFile(string $content): string {
		$this->bundlePath = sys_get_temp_dir() . '/nldesign-config-import-cmd-' . uniqid() . '.json';
		file_put_contents($this->bundlePath, $content);

		return $this->bundlePath;
	}//end writeBundleFile()

	/**
	 * `--dry-run` calls the service with dryRun=true and exits 0, printing
	 * the would-be sections — the command itself never writes anything (all
	 * writing happens, or doesn't, inside the mocked service call).
	 */
	public function testDryRunCallsServiceWithDryRunTrueAndExitsZero(): void {
		$path = $this->writeBundleFile('{"format":"nldesign-config-bundle","bundleVersion":1}');

		$this->service->expects($this->once())
			->method('import')
			->with($this->anything(), true)
			->willReturn(
				[
					'valid' => true,
					'dryRun' => true,
					'applied' => false,
					'sections' => ['config' => ['tokenSet' => 'utrecht']],
				]
			);

		$exitCode = $this->tester->execute(['file' => $path, '--dry-run' => true]);

		$this->assertSame(0, $exitCode);
		$this->assertStringContainsString('Dry run', $this->tester->getDisplay());
	}//end testDryRunCallsServiceWithDryRunTrueAndExitsZero()

	/**
	 * A validation failure prints the full error listing and exits non-zero.
	 */
	public function testValidationFailureExitsNonZeroWithErrorListing(): void {
		$path = $this->writeBundleFile('{"format":"nldesign-config-bundle","bundleVersion":1}');

		$this->service->method('import')->willReturn(
			[
				'valid' => false,
				'errors' => [
					['section' => 'customTokenSets', 'message' => 'Forbidden value in custom-x.'],
					['section' => 'config', 'message' => 'Unresolvable token set "atlantis".'],
				],
			]
		);

		$exitCode = $this->tester->execute(['file' => $path]);

		$this->assertNotSame(0, $exitCode);
		$display = $this->tester->getDisplay();
		$this->assertStringContainsString('Forbidden value in custom-x.', $display);
		$this->assertStringContainsString('Unresolvable token set "atlantis".', $display);
	}//end testValidationFailureExitsNonZeroWithErrorListing()

	/**
	 * A missing file is exit 1 and never reaches the service.
	 */
	public function testMissingFileExitsNonZeroWithoutCallingService(): void {
		$this->service->expects($this->never())->method('import');

		$exitCode = $this->tester->execute(['file' => '/nonexistent/path/bundle.json']);

		$this->assertNotSame(0, $exitCode);
		$this->assertStringContainsString('Cannot read bundle file', $this->tester->getDisplay());
	}//end testMissingFileExitsNonZeroWithoutCallingService()

	/**
	 * A file that is not valid JSON is exit 1 and never reaches the service.
	 */
	public function testUndecodableJsonExitsNonZeroWithoutCallingService(): void {
		$path = $this->writeBundleFile('not json at all');

		$this->service->expects($this->never())->method('import');

		$exitCode = $this->tester->execute(['file' => $path]);

		$this->assertNotSame(0, $exitCode);
		$this->assertStringContainsString('does not contain valid JSON', $this->tester->getDisplay());
	}//end testUndecodableJsonExitsNonZeroWithoutCallingService()

	/**
	 * A successful (non-dry-run) import exits 0 and prints the applied sections.
	 */
	public function testSuccessfulImportExitsZeroAndPrintsSections(): void {
		$path = $this->writeBundleFile('{"format":"nldesign-config-bundle","bundleVersion":1}');

		$this->service->method('import')->willReturn(
			[
				'valid' => true,
				'dryRun' => false,
				'applied' => true,
				'sections' => ['config' => ['tokenSet' => 'utrecht']],
			]
		);

		$exitCode = $this->tester->execute(['file' => $path]);

		$this->assertSame(0, $exitCode);
		$this->assertStringContainsString('applied', $this->tester->getDisplay());
	}//end testSuccessfulImportExitsZeroAndPrintsSections()

	/**
	 * A service-level exception is exit 1.
	 */
	public function testServiceExceptionExitsNonZero(): void {
		$path = $this->writeBundleFile('{"format":"nldesign-config-bundle","bundleVersion":1}');

		$this->service->method('import')->willThrowException(new RuntimeException('boom'));

		$exitCode = $this->tester->execute(['file' => $path]);

		$this->assertNotSame(0, $exitCode);
		$this->assertStringContainsString('boom', $this->tester->getDisplay());
	}//end testServiceExceptionExitsNonZero()
}//end class
