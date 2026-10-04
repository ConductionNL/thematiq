<?php

/**
 * Unit tests for the GenerateDarkVariants occ command.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/dark-mode/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Command;

use OCA\Thematiq\Command\GenerateDarkVariants;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DarkPaletteService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Covers tasks.md#task-5.4: `--set` writes the file, ineligible sets are
 * skipped without failing the exit code, and `--force` rewrites a fresh file.
 */
class GenerateDarkVariantsTest extends TestCase {

	/**
	 * The temp app directory standing in for the nldesign app path.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * The command tester.
	 *
	 * @var CommandTester
	 */
	private CommandTester $tester;

	/**
	 * Set up a temp app dir with a shipped-set-shaped fixture:
	 * `amsterdam` (nldesign, eligible), `nextcloud` (none, skipped), and
	 * `hoog-contrast` (high-contrast, skipped) — mirroring the real manifest.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/nldesign-cmd-test-' . uniqid();
		mkdir($this->appDir . '/css/systems/nldesign', 0777, true);
		mkdir($this->appDir . '/css/tokens', 0777, true);

		file_put_contents(
			$this->appDir . '/css/systems/nldesign/defaults.css',
			":root {\n\t--nldesign-color-primary: #154273;\n\t--nldesign-color-primary-text: #ffffff;\n}\n"
		);
		file_put_contents(
			$this->appDir . '/token-sets.json',
			json_encode(
				[
					['id' => 'amsterdam', 'theming' => ['background_color' => '#FFFFFF']],
					['id' => 'nextcloud', 'design_system' => 'none'],
					['id' => 'hoog-contrast', 'design_system' => 'high-contrast'],
				]
			)
		);
		file_put_contents($this->appDir . '/css/tokens/amsterdam.css', ":root {\n\t--nldesign-color-primary: #004699;\n}\n");
		file_put_contents($this->appDir . '/css/tokens/nextcloud.css', ":root {\n\t--nldesign-color-primary: #0082c9;\n}\n");
		file_put_contents($this->appDir . '/css/tokens/hoog-contrast.css', ":root {\n\t--nldesign-color-primary: #000000;\n}\n");

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);

		$darkPalette = new DarkPaletteService(
			new ContrastService(),
			new CssParserService(),
			$appManager,
			$this->createMock(LoggerInterface::class)
		);

		$command = new GenerateDarkVariants($darkPalette);
		$app = new Application();
		$app->add($command);
		$this->tester = new CommandTester($app->find('thematiq:generate-dark-variants'));
	}//end setUp()

	/**
	 * Remove the temp app dir after each test.
	 */
	protected function tearDown(): void {
		$this->rrmdir($this->appDir);
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
				$this->rrmdir($path);
			} else {
				unlink($path);
			}
		}

		rmdir($dir);
	}//end rrmdir()

	/**
	 * `--set=amsterdam` writes only that set's file.
	 */
	public function testSetOptionWritesOnlyThatFile(): void {
		$exitCode = $this->tester->execute(['--set' => 'amsterdam']);

		$this->assertSame(0, $exitCode);
		$this->assertFileExists($this->appDir . '/css/tokens/dark/amsterdam.css');
		$this->assertFileDoesNotExist($this->appDir . '/css/tokens/dark/nextcloud.css');
	}//end testSetOptionWritesOnlyThatFile()

	/**
	 * A full run skips `none`/`high-contrast` sets without failing the exit code.
	 */
	public function testFullRunSkipsIneligibleSetsWithZeroExitCode(): void {
		$exitCode = $this->tester->execute([]);

		$this->assertSame(0, $exitCode);
		$this->assertFileExists($this->appDir . '/css/tokens/dark/amsterdam.css');
		$this->assertFileDoesNotExist($this->appDir . '/css/tokens/dark/nextcloud.css');
		$this->assertFileDoesNotExist($this->appDir . '/css/tokens/dark/hoog-contrast.css');

		$display = $this->tester->getDisplay();
		$this->assertStringContainsString('nextcloud: skipped (ineligible)', $display);
		$this->assertStringContainsString('hoog-contrast: skipped (ineligible)', $display);
	}//end testFullRunSkipsIneligibleSetsWithZeroExitCode()

	/**
	 * A forced full run, and a forced run of that set alone, leave a shipped
	 * hand-written high-contrast dark variant byte for byte (thematiq#1023):
	 * the generator only reaches AA, and that file is held to AAA by hand.
	 */
	public function testForcedRunLeavesAHandWrittenHighContrastVariantAlone(): void {
		mkdir($this->appDir . '/css/tokens/dark', 0777, true);
		$handWritten = "/* HAND-WRITTEN */\nbody[data-theme-dark],\nbody[data-themes*=dark] {\n\t--nldesign-color-background: #000000;\n}\n";
		file_put_contents($this->appDir . '/css/tokens/dark/hoog-contrast.css', $handWritten);

		$this->assertSame(0, $this->tester->execute(['--force' => true]));
		$this->assertSame(0, $this->tester->execute(['--set' => 'hoog-contrast', '--force' => true]));

		$this->assertSame($handWritten, file_get_contents($this->appDir . '/css/tokens/dark/hoog-contrast.css'));
		$this->assertStringContainsString('hoog-contrast: skipped (ineligible)', $this->tester->getDisplay());
	}//end testForcedRunLeavesAHandWrittenHighContrastVariantAlone()

	/**
	 * A second run without `--force` skips the now-fresh file.
	 */
	public function testSecondRunWithoutForceSkipsFreshFile(): void {
		$this->tester->execute(['--set' => 'amsterdam']);
		$mtimeFirst = filemtime($this->appDir . '/css/tokens/dark/amsterdam.css');

		// Ensure a distinguishable mtime if the file WERE rewritten.
		touch($this->appDir . '/css/tokens/dark/amsterdam.css', ($mtimeFirst - 100));

		$this->tester->execute(['--set' => 'amsterdam']);
		$display = $this->tester->getDisplay();

		$this->assertStringContainsString('amsterdam: skipped (fresh)', $display);
	}//end testSecondRunWithoutForceSkipsFreshFile()

	/**
	 * `--force` rewrites despite a fresh hash.
	 */
	public function testForceRewritesFreshFile(): void {
		$this->tester->execute(['--set' => 'amsterdam']);
		$this->tester->execute(['--set' => 'amsterdam', '--force' => true]);

		$display = $this->tester->getDisplay();
		$this->assertStringContainsString('amsterdam', $display);
		$this->assertStringNotContainsString('amsterdam: skipped (fresh)', $display);
	}//end testForceRewritesFreshFile()
}//end class
