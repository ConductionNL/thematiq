<?php

/**
 * Unit tests for BrandingPackageReader: which files a package directory or
 * ZIP carries, how they are parsed, the refusals and the size limit, and
 * how a package directory is written.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\BrandingPackageReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

/**
 * Pins the reader on a temporary directory and real ZIP files.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */
final class BrandingPackageReaderTest extends TestCase {

	/**
	 * The temporary directory.
	 *
	 * @var string
	 */
	private string $tmp = '';

	/**
	 * The reader under test.
	 *
	 * @var BrandingPackageReader
	 */
	private BrandingPackageReader $reader;

	/**
	 * Create the temporary directory.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->tmp = sys_get_temp_dir() . '/thematiq-package-reader-' . bin2hex(random_bytes(4));
		mkdir($this->tmp, 0777, true);
		$this->reader = new BrandingPackageReader();
	}//end setUp()

	/**
	 * Remove the temporary directory.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		exec('rm -rf ' . escapeshellarg($this->tmp));
		parent::tearDown();
	}//end tearDown()

	/**
	 * Write files under a directory.
	 *
	 * @param string $dir The directory.
	 * @param array<string, string> $files Relative path => content.
	 *
	 * @return string The directory.
	 */
	private function directory(string $dir, array $files): string {
		foreach ($files as $relative => $content) {
			@mkdir(dirname($dir . '/' . $relative), 0777, true);
			file_put_contents($dir . '/' . $relative, $content);
		}

		return $dir;
	}//end directory()

	/**
	 * Write a ZIP.
	 *
	 * @param string $name The ZIP file name.
	 * @param array<string, string> $files Entry name => content.
	 *
	 * @return string The ZIP path.
	 */
	private function zip(string $name, array $files): string {
		$path = $this->tmp . '/' . $name;
		$zip = new ZipArchive();
		$zip->open($path, ZipArchive::CREATE);
		foreach ($files as $entry => $content) {
			$zip->addFromString($entry, $content);
		}

		$zip->close();

		return $path;
	}//end zip()

	/**
	 * The files a package carries.
	 *
	 * @return array<string, string> Relative path => content.
	 */
	private function packageFiles(): array {
		return [
			'bundle.json' => '{"format":"x","config":{"tokenSet":"a"}}',
			'REVISION' => "  " . str_repeat('r', 90) . "\n",
			'fonts/inter.woff2' => 'FONT-I',
			'fonts/serif.woff2' => 'FONT-S',
			'tokens/brand.json' => '{"color":{}}',
			'README.md' => 'not part of the package',
			'fonts/inter.ttf' => 'not part of the package',
			'tokens/nested/deep.json' => 'not part of the package',
		];
	}//end packageFiles()

	/**
	 * A directory, a ZIP at the root and a ZIP one level down read to the same parts and hash.
	 *
	 * @return void
	 */
	public function testADirectoryAndBothZipLayoutsReadTheSameParts(): void {
		$files = $this->packageFiles();
		$dir = $this->directory(dir: $this->tmp . '/pkg', files: $files);
		$rooted = $this->zip(name: 'rooted.zip', files: $files);
		$nested = [];
		foreach ($files as $relative => $content) {
			$nested['top/' . $relative] = $content;
		}

		$nested['other/bundle.json'] = '{"ignored":true}';
		$nested['fonts/stray.woff2'] = 'outside the prefix';
		$nestedZip = $this->zip(name: 'nested.zip', files: $nested);

		$expected = [
			'bundle' => ['format' => 'x', 'config' => ['tokenSet' => 'a']],
			'fonts' => ['inter' => 'FONT-I', 'serif' => 'FONT-S'],
			'tokens' => ['brand' => '{"color":{}}'],
			'revision' => str_repeat('r', 80),
		];

		$fromDir = $this->reader->read(path: $dir . '/');
		$fromRooted = $this->reader->read(path: $rooted);
		$fromNested = $this->reader->read(path: $nestedZip);

		foreach ([$fromDir, $fromRooted, $fromNested] as $parts) {
			$hash = $parts['hash'];
			unset($parts['hash']);
			ksort($parts['fonts']);
			$this->assertSame(expected: $expected, actual: $parts);
			$this->assertMatchesRegularExpression(pattern: '/^sha256:[0-9a-f]{64}$/', string: $hash);
		}

		$this->assertSame(expected: $fromDir['hash'], actual: $fromRooted['hash']);
		$this->assertSame(expected: $fromDir['hash'], actual: $fromNested['hash']);
		$this->assertSame(expected: $fromDir['hash'], actual: $this->reader->hash(path: $dir));
	}//end testADirectoryAndBothZipLayoutsReadTheSameParts()

	/**
	 * An empty REVISION reads as none.
	 *
	 * @return void
	 */
	public function testAnEmptyRevisionIsNone(): void {
		$dir = $this->directory(dir: $this->tmp . '/pkg', files: ['bundle.json' => '{}', 'REVISION' => " \n"]);

		$this->assertNull(actual: $this->reader->read(path: $dir)['revision']);
	}//end testAnEmptyRevisionIsNone()

	/**
	 * Each unreadable package is refused with its own message.
	 *
	 * @return void
	 */
	public function testEachUnreadablePackageIsRefusedWithItsMessage(): void {
		$notZip = $this->tmp . '/plain.zip';
		file_put_contents($notZip, 'not a zip');
		$cases = [
			$this->tmp . '/missing' => 'The branding package ' . $this->tmp . '/missing does not exist or cannot be read.',
			$notZip => 'The branding package ' . $notZip . ' is neither a directory nor a ZIP file.',
			$this->zip(name: 'empty.zip', files: ['readme.txt' => 'x']) => 'The branding package holds no bundle.json.',
			$this->directory(dir: $this->tmp . '/nobundle', files: ['REVISION' => '1']) => 'The branding package holds no bundle.json.',
			$this->directory(dir: $this->tmp . '/badjson', files: ['bundle.json' => '"text"']) => 'The bundle.json of the branding package is not valid JSON.',
			$this->directory(dir: $this->tmp . '/big', files: ['bundle.json' => '{}', 'fonts/huge.woff2' => str_repeat('x', (BrandingPackageReader::MAX_FILE_BYTES + 1))])
				=> 'The package file fonts/huge.woff2 is larger than 4 MB.',
			$this->zip(name: 'big.zip', files: ['bundle.json' => '{}', 'tokens/huge.json' => str_repeat('x', (BrandingPackageReader::MAX_FILE_BYTES + 1))])
				=> 'The package file tokens/huge.json is larger than 4 MB.',
		];

		foreach ($cases as $path => $message) {
			try {
				$this->reader->read(path: (string)$path);
				$this->fail(message: 'read: ' . $path);
			} catch (RuntimeException $e) {
				$this->assertSame(expected: $message, actual: $e->getMessage());
			}
		}
	}//end testEachUnreadablePackageIsRefusedWithItsMessage()

	/**
	 * Writing a package writes bundle.json and the fonts with a safe id, and lists them.
	 *
	 * @return void
	 */
	public function testWritingAPackageListsWhatItWrote(): void {
		$written = $this->reader->write(
			dir: $this->tmp . '/out/',
			bundle: ['name' => 'Gemeente', 'url' => 'https://example.org/a'],
			fonts: ['inter' => 'FONT-I', 'Bad Id' => 'x', '../escape' => 'y', 'serif-2' => 'FONT-S']
		);

		$this->assertSame(expected: ['bundle.json', 'fonts/inter.woff2', 'fonts/serif-2.woff2'], actual: $written);
		$this->assertSame(
			expected: "{\n    \"name\": \"Gemeente\",\n    \"url\": \"https://example.org/a\"\n}\n",
			actual: file_get_contents($this->tmp . '/out/bundle.json')
		);
		$this->assertSame(expected: 'FONT-S', actual: file_get_contents($this->tmp . '/out/fonts/serif-2.woff2'));
		$this->assertSame(expected: ['inter.woff2', 'serif-2.woff2'], actual: array_values(array_diff(scandir($this->tmp . '/out/fonts'), ['.', '..'])));
	}//end testWritingAPackageListsWhatItWrote()
}//end class
