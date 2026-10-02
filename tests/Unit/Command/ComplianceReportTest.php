<?php

/**
 * Unit tests for the ComplianceReport occ command.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/compliance-evidence/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Command;

use OCA\Thematiq\Command\ComplianceReport;
use OCA\Thematiq\Service\ComplianceReportService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Covers tasks.md#task-4.6: format/output options and exit codes.
 */
class ComplianceReportTest extends TestCase {

	/**
	 * The mocked compliance report service.
	 *
	 * @var ComplianceReportService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $service;

	/**
	 * The command tester.
	 *
	 * @var CommandTester
	 */
	private CommandTester $tester;

	/**
	 * A temp file path used by the --output tests (cleaned up in tearDown).
	 *
	 * @var string|null
	 */
	private ?string $outputPath = null;

	/**
	 * Set up the command with a mocked service.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->service = $this->createMock(ComplianceReportService::class);
		$this->service->method('renderJson')->willReturn("{\"scope\":\"x\"}\n");
		$this->service->method('renderMarkdown')->willReturn("# Report\n");

		$command = new ComplianceReport($this->service);

		$application = new Application();
		$application->add($command);

		$this->tester = new CommandTester($application->find('thematiq:compliance-report'));
	}//end setUp()

	/**
	 * Remove any temp output file after each test.
	 */
	protected function tearDown(): void {
		if ($this->outputPath !== null && file_exists($this->outputPath) === true) {
			unlink($this->outputPath);
		}

		parent::tearDown();
	}//end tearDown()

	/**
	 * Default format (json) prints the JSON report to stdout with exit code 0.
	 */
	public function testDefaultFormatPrintsJsonAndExitsZero(): void {
		$exitCode = $this->tester->execute([]);

		$this->assertSame(0, $exitCode);
		$this->assertStringContainsString('"scope":"x"', $this->tester->getDisplay());
	}//end testDefaultFormatPrintsJsonAndExitsZero()

	/**
	 * --format=markdown prints the Markdown report to stdout with exit code 0.
	 */
	public function testMarkdownFormatPrintsMarkdownAndExitsZero(): void {
		$exitCode = $this->tester->execute(['--format' => 'markdown']);

		$this->assertSame(0, $exitCode);
		$this->assertStringContainsString('# Report', $this->tester->getDisplay());
	}//end testMarkdownFormatPrintsMarkdownAndExitsZero()

	/**
	 * An unknown format is a generation failure: non-zero exit, no report content.
	 */
	public function testUnknownFormatFailsWithNonZeroExit(): void {
		$exitCode = $this->tester->execute(['--format' => 'yaml']);

		$this->assertNotSame(0, $exitCode);
		$this->assertStringContainsString('Unknown format', $this->tester->getDisplay());
	}//end testUnknownFormatFailsWithNonZeroExit()

	/**
	 * --output=<path> writes the identical bytes to the file instead of stdout,
	 * and still exits 0.
	 */
	public function testOutputOptionWritesIdenticalBytesToFile(): void {
		$this->outputPath = sys_get_temp_dir() . '/nldesign-compliance-cmd-test-' . uniqid() . '.md';

		$exitCode = $this->tester->execute(['--format' => 'markdown', '--output' => $this->outputPath]);

		$this->assertSame(0, $exitCode);
		$this->assertFileExists($this->outputPath);
		$this->assertSame("# Report\n", file_get_contents($this->outputPath));
		$this->assertStringNotContainsString('# Report', $this->tester->getDisplay());
	}//end testOutputOptionWritesIdenticalBytesToFile()

	/**
	 * The exit code is 0 even when the (mocked) report content represents a
	 * failing verdict — the command only fails on generation errors.
	 */
	public function testExitCodeIsZeroRegardlessOfVerdict(): void {
		$this->service = $this->createMock(ComplianceReportService::class);
		$this->service->method('renderJson')->willReturn("{\"summary\":{\"verdict\":\"fail\"}}\n");

		$command = new ComplianceReport($this->service);
		$application = new Application();
		$application->add($command);
		$tester = new CommandTester($application->find('thematiq:compliance-report'));

		$exitCode = $tester->execute([]);

		$this->assertSame(0, $exitCode);
	}//end testExitCodeIsZeroRegardlessOfVerdict()

	/**
	 * A generation exception is reported and yields a non-zero exit code.
	 */
	public function testGenerationFailureExitsNonZero(): void {
		$failingService = $this->createMock(ComplianceReportService::class);
		$failingService->method('renderJson')->willThrowException(new \RuntimeException('boom'));

		$command = new ComplianceReport($failingService);
		$application = new Application();
		$application->add($command);
		$tester = new CommandTester($application->find('thematiq:compliance-report'));

		$exitCode = $tester->execute([]);

		$this->assertNotSame(0, $exitCode);
		$this->assertStringContainsString('boom', $tester->getDisplay());
	}//end testGenerationFailureExitsNonZero()
}//end class
