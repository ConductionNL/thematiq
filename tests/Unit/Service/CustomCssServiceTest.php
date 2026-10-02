<?php

/**
 * Unit tests for CustomCssService, the freeform custom CSS file.
 *
 * Runs against a real temporary app directory and the real validator, so a
 * save and a load go through the same bytes on disk the stylesheet is served
 * from.
 *
 * @category Tests
 * @package  OCA\Thematiq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/custom-css-freeform/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\CustomCssService;
use OCA\Thematiq\Service\CustomCssValidator;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests for CustomCssService.
 */
class CustomCssServiceTest extends TestCase {

	/**
	 * The temporary app directory.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * The service under test.
	 *
	 * @var CustomCssService
	 */
	private CustomCssService $service;

	/**
	 * The app config mock.
	 *
	 * @var IAppConfig&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $appConfig;

	/**
	 * Build the service over a fresh temporary app directory.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->appDir = sys_get_temp_dir() . '/thematiq-custom-css-' . bin2hex(random_bytes(6));
		mkdir($this->appDir . '/css', 0777, true);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);

		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->service = new CustomCssService(
			appManager: $appManager,
			appConfig: $this->appConfig,
			validator: new CustomCssValidator(),
		);
	}

	/**
	 * Remove the temporary app directory.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		chmod($this->appDir . '/css', 0777);
		foreach (glob($this->appDir . '/css/*') as $file) {
			unlink($file);
		}

		rmdir($this->appDir . '/css');
		rmdir($this->appDir);
		parent::tearDown();
	}

	/**
	 * The stored file.
	 *
	 * @return string
	 */
	private function file(): string {
		return $this->appDir . '/css/custom-css.css';
	}

	/**
	 * The enabled flag is one app config value, not a database table.
	 *
	 * @return void
	 */
	public function testEnabledFlagLivesInAppConfig(): void {
		$this->appConfig->expects($this->once())
			->method('setValueString')
			->with('thematiq', CustomCssService::ENABLED_KEY, '1');
		$this->appConfig->method('getValueString')
			->with('thematiq', CustomCssService::ENABLED_KEY, '0')
			->willReturn('0');

		$this->service->setEnabled(enabled: true);

		$this->assertFalse($this->service->isEnabled(), 'off by default');
	}

	/**
	 * A fresh install has nothing stored and nothing to serve.
	 *
	 * @return void
	 */
	public function testMissingFileReadsAsEmpty(): void {
		$this->assertSame('', $this->service->read());
		$this->assertFalse($this->service->hasContent());
	}

	/**
	 * What the administrator saves is what the editor shows again.
	 *
	 * @return void
	 */
	public function testSavedCssReadsBackExactlyAsWritten(): void {
		$css = ".header {\n\tcolor: red;\n}";

		$this->assertSame([], $this->service->write(css: $css));

		$this->assertSame($css, $this->service->read());
		$this->assertStringStartsWith(CustomCssService::FILE_HEADER, (string) file_get_contents($this->file()));
	}

	/**
	 * Saving the loaded text again must not stack a second header.
	 *
	 * @return void
	 */
	public function testSavingTwiceKeepsOneHeader(): void {
		$this->service->write(css: 'a { color: red; }');
		$this->service->write(css: $this->service->read());

		$this->assertSame(1, substr_count((string) file_get_contents($this->file()), CustomCssService::FILE_HEADER));
		$this->assertSame('a { color: red; }', $this->service->read());
	}

	/**
	 * A file that already stacked headers under the old read() heals on load.
	 *
	 * @return void
	 */
	public function testStackedHeadersFromEarlierSavesArePeeled(): void {
		$header = CustomCssService::FILE_HEADER;
		file_put_contents($this->file(), $header . $header . "a { color: red; }\n\n");

		$this->assertSame('a { color: red; }', $this->service->read());
	}

	/**
	 * Clearing the CSS leaves nothing to serve, so no stylesheet is emitted.
	 *
	 * @return void
	 */
	public function testSavingEmptyCssLeavesNothingToServe(): void {
		$this->service->write(css: 'a { color: red; }');
		$this->service->write(css: '');

		$this->assertSame('', $this->service->read());
		$this->assertFalse($this->service->hasContent());
	}

	/**
	 * A file without the header (hand-edited) is returned untouched.
	 *
	 * @return void
	 */
	public function testFileWithoutHeaderIsReturnedAsIs(): void {
		file_put_contents($this->file(), "b { color: blue; }\n");

		$this->assertSame("b { color: blue; }\n", $this->service->read());
	}

	/**
	 * A rejected submission writes nothing and leaves no temp file.
	 *
	 * @return void
	 */
	public function testValidationFailureWritesNothing(): void {
		$this->service->write(css: 'a { color: red; }');
		$before = file_get_contents($this->file());

		$errors = $this->service->write(css: '@import url("https://evil.example/x.css");');

		$this->assertNotEmpty($errors);
		$this->assertSame($before, file_get_contents($this->file()));
		$this->assertFileDoesNotExist($this->file() . '.tmp');
	}

	/**
	 * An unwritable css/ directory fails loudly and keeps the old file.
	 *
	 * @return void
	 */
	public function testUnwritableDirectoryThrowsAndKeepsTheOldFile(): void {
		$this->service->write(css: 'a { color: red; }');
		chmod($this->appDir . '/css', 0555);
		if (is_writable($this->appDir . '/css') === true) {
			$this->markTestSkipped('Running as a user that ignores directory permissions.');
		}

		// file_put_contents() warns before it returns false; the service turns
		// the false into the exception asserted here.
		set_error_handler(static fn (): bool => true, E_WARNING);
		try {
			$this->service->write(css: 'b { color: blue; }');
			$this->fail('Expected a RuntimeException.');
		} catch (RuntimeException $e) {
			$this->assertStringContainsString('write access', $e->getMessage());
		} finally {
			restore_error_handler();
		}

		$this->assertSame('a { color: red; }', $this->service->read());
	}
}//end class
