<?php

/**
 * Unit tests for FontService.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/custom-fonts/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\FontValidator;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IConfig;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * A real-filesystem-backed fake for a single IAppData file, mirroring the
 * "real temp directory standing in for the app path" strategy already used
 * by {@see CustomTokenSetServiceTest} — the storage/lifecycle logic is
 * exercised against real reads/writes, not a fully mocked object graph.
 */
class FontServiceFakeSimpleFile implements ISimpleFile {
	/**
	 * The path.
	 *
	 * @var string
	 */
	private string $path;

	/**
	 * Constructor.
	 *
	 * @param string $path The path.
	 */
	public function __construct(string $path) {
		$this->path = $path;
	}

	/**
	 * ISimpleFile::getName(), as this test double answers it.
	 *
	 * @return string
	 */
	public function getName(): string {
		return basename($this->path);
	}

	/**
	 * ISimpleFile::getSize(), as this test double answers it.
	 *
	 * @return int|float
	 */
	public function getSize(): int|float {
		return (int)filesize($this->path);
	}

	/**
	 * ISimpleFile::getETag(), as this test double answers it.
	 *
	 * @return string
	 */
	public function getETag(): string {
		return (string)md5_file($this->path);
	}

	/**
	 * ISimpleFile::getMTime(), as this test double answers it.
	 *
	 * @return int
	 */
	public function getMTime(): int {
		return (int)filemtime($this->path);
	}

	/**
	 * ISimpleFile::getContent(), as this test double answers it.
	 *
	 * @return string
	 */
	public function getContent(): string {
		return (string)file_get_contents($this->path);
	}

	/**
	 * ISimpleFile::putContent(), as this test double answers it.
	 *
	 * @param mixed $data The data.
	 * @return void
	 */
	public function putContent($data): void {
		file_put_contents($this->path, $data);
	}

	/**
	 * ISimpleFile::delete(), as this test double answers it.
	 *
	 * @return void
	 */
	public function delete(): void {
		unlink($this->path);
	}

	/**
	 * ISimpleFile::getMimeType(), as this test double answers it.
	 *
	 * @return string
	 */
	public function getMimeType(): string {
		return 'font/woff2';
	}

	/**
	 * ISimpleFile::getExtension(), as this test double answers it.
	 *
	 * @return string
	 */
	public function getExtension(): string {
		return (string)pathinfo($this->path, PATHINFO_EXTENSION);
	}

	/**
	 * ISimpleFile::read(), as this test double answers it.
	 *
	 * @return mixed
	 */
	public function read() {
		return fopen($this->path, 'rb');
	}

	/**
	 * ISimpleFile::write(), as this test double answers it.
	 *
	 * @return mixed
	 */
	public function write() {
		return fopen($this->path, 'wb');
	}
}

/**
 * A real-filesystem-backed fake for a single IAppData folder (the `fonts/`
 * subfolder). Only the methods FontService actually calls are meaningfully
 * implemented; the rest satisfy the interface.
 */
class FontServiceFakeSimpleFolder implements ISimpleFolder {
	/**
	 * The dir.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Constructor.
	 *
	 * @param string $dir The directory this fake folder keeps its files in.
	 */
	public function __construct(string $dir) {
		$this->dir = $dir;
		if (is_dir($dir) === false) {
			mkdir($dir, 0777, true);
		}
	}

	/**
	 * ISimpleFolder::getDirectoryListing(), as this test double answers it.
	 *
	 * @return array
	 */
	public function getDirectoryListing(): array {
		$result = [];
		foreach (scandir($this->dir) as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}
			$result[] = new FontServiceFakeSimpleFile($this->dir . '/' . $entry);
		}
		return $result;
	}

	/**
	 * ISimpleFolder::fileExists(), as this test double answers it.
	 *
	 * @param string $name The name.
	 * @return bool
	 */
	public function fileExists(string $name): bool {
		return file_exists($this->dir . '/' . $name);
	}

	/**
	 * ISimpleFolder::getFile(), as this test double answers it.
	 *
	 * @param string $name The name.
	 * @return ISimpleFile
	 */
	public function getFile(string $name): ISimpleFile {
		$path = $this->dir . '/' . $name;
		if (file_exists($path) === false) {
			throw new NotFoundException();
		}
		return new FontServiceFakeSimpleFile($path);
	}

	/**
	 * ISimpleFolder::newFile(), as this test double answers it.
	 *
	 * @param string $name The name.
	 * @param mixed $content The content.
	 * @return ISimpleFile
	 */
	public function newFile(string $name, $content = null): ISimpleFile {
		$path = $this->dir . '/' . $name;
		file_put_contents($path, $content ?? '');
		return new FontServiceFakeSimpleFile($path);
	}

	/**
	 * ISimpleFolder::delete(), as this test double answers it.
	 *
	 * @return void
	 */
	public function delete(): void {
		foreach (scandir($this->dir) as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}
			unlink($this->dir . '/' . $entry);
		}
		rmdir($this->dir);
	}

	/**
	 * ISimpleFolder::getName(), as this test double answers it.
	 *
	 * @return string
	 */
	public function getName(): string {
		return basename($this->dir);
	}

	/**
	 * ISimpleFolder::getFolder(), as this test double answers it.
	 *
	 * @param string $name The name.
	 * @return ISimpleFolder
	 */
	public function getFolder(string $name): ISimpleFolder {
		$path = $this->dir . '/' . $name;
		if (is_dir($path) === false) {
			throw new NotFoundException();
		}
		return new FontServiceFakeSimpleFolder($path);
	}

	/**
	 * ISimpleFolder::newFolder(), as this test double answers it.
	 *
	 * @param string $path The path.
	 * @return ISimpleFolder
	 */
	public function newFolder(string $path): ISimpleFolder {
		return new FontServiceFakeSimpleFolder($this->dir . '/' . $path);
	}

	// ISimpleFolder::getOrCreateFolder() is @since 35.0.0. The fake implements
	// it so the suite loads on a stable35 leg; on the older legs it is simply
	// a method nothing calls.
	/**
	 * ISimpleFolder::getOrCreateFolder(), as this test double answers it.
	 *
	 * @param string $path The path.
	 * @param int $maxRetries The max retries.
	 * @return ISimpleFolder
	 */
	public function getOrCreateFolder(string $path, int $maxRetries = 5): ISimpleFolder {
		$full = $this->dir . '/' . $path;
		if (is_dir($full) === false) {
			mkdir($full, 0777, true);
		}

		return new FontServiceFakeSimpleFolder($full);
	}
}

/**
 * A real-filesystem-backed fake IAppData root, scoped to a temp directory.
 */
class FontServiceFakeAppData implements IAppData {
	/**
	 * The root.
	 *
	 * @var string
	 */
	private string $root;

	/**
	 * Constructor.
	 *
	 * @param string $root The root.
	 */
	public function __construct(string $root) {
		$this->root = $root;
	}

	/**
	 * IAppData::getFolder(), as this test double answers it.
	 *
	 * @param string $name The name.
	 * @return ISimpleFolder
	 */
	public function getFolder(string $name): ISimpleFolder {
		$path = $this->root . '/' . $name;
		if (is_dir($path) === false) {
			throw new NotFoundException();
		}
		return new FontServiceFakeSimpleFolder($path);
	}

	/**
	 * IAppData::getDirectoryListing(), as this test double answers it.
	 *
	 * @return array
	 */
	public function getDirectoryListing(): array {
		$result = [];
		foreach (scandir($this->root) as $entry) {
			if ($entry === '.' || $entry === '..' || is_dir($this->root . '/' . $entry) === false) {
				continue;
			}
			$result[] = new FontServiceFakeSimpleFolder($this->root . '/' . $entry);
		}
		return $result;
	}

	/**
	 * IAppData::newFolder(), as this test double answers it.
	 *
	 * @param string $name The name.
	 * @return ISimpleFolder
	 */
	public function newFolder(string $name): ISimpleFolder {
		return new FontServiceFakeSimpleFolder($this->root . '/' . $name);
	}
}

/**
 * Unit tests for the font storage/lifecycle service.
 *
 * Covers tasks.md#task-5.2: store/manifest round-trip, collision without
 * touching the existing file, delete + rev bump, the manifest as the sole
 * authorization gate for getFont() (even when a stray file exists),
 * path-traversal ids never reaching appdata, the 21st-font cap, and
 * buildCss() content (@font-face, format('woff2'), font-display: swap, the
 * preserved Fira Sans fallback chain, and CSS-escaped display names).
 */
class FontServiceTest extends TestCase {

	/**
	 * The temp dir standing in for the app's IAppData root.
	 *
	 * @var string
	 */
	private string $dataDir;

	/**
	 * In-memory appconfig store: key => value.
	 *
	 * @var array<string, string>
	 */
	private array $appConfig = [];

	/**
	 * The service under test.
	 *
	 * @var FontService
	 */
	private FontService $service;

	/**
	 * Set up a temp appdata dir + mocked config/url-generator.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->dataDir = sys_get_temp_dir() . '/nldesign-font-test-' . uniqid();
		mkdir($this->dataDir, 0777, true);

		$appData = new FontServiceFakeAppData($this->dataDir);

		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->appConfig[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->appConfig[$key] = $value;
			}
		);

		$urlGenerator = $this->createMock(originalClassName: IURLGenerator::class);
		$urlGenerator->method('linkToRoute')->willReturnCallback(
			fn (string $route, array $args = []) => '/apps/thematiq/fonts/' . ($args['id'] ?? '') . '.woff2'
		);

		$this->service = new FontService(appData: $appData, config: $config, urlGenerator: $urlGenerator, validator: new FontValidator());
	}//end setUp()

	/**
	 * Remove the temp appdata dir after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->rrmdir(dir: $this->dataDir);
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
	 * A valid woff2 payload for tests that don't care about exact bytes.
	 *
	 * @return string
	 */
	private function validWoff2(): string {
		return "wOF2\x00\x01\x02\x03payload-bytes-not-a-real-font-but-passes-the-magic-check";
	}//end validWoff2()

	/**
	 * Storing writes `custom-{slug}.woff2` and a manifest entry.
	 *
	 * @return void
	 */
	public function testStoreWritesFileAndManifest(): void {
		$result = $this->service->store(
			displayName: 'Rijks Sans',
			role: 'body',
			bytes: $this->validWoff2(),
			reportedSize: strlen($this->validWoff2())
		);

		$this->assertSame(expected: 'custom-rijks-sans', actual: $result['id']);
		$this->assertFileExists(filename: $this->dataDir . '/fonts/custom-rijks-sans.woff2');

		$manifest = $this->service->getManifest();
		$this->assertArrayHasKey(key: 'custom-rijks-sans', array: $manifest);
		$this->assertSame(expected: 'Rijks Sans', actual: $manifest['custom-rijks-sans']['name']);
		$this->assertSame(expected: 'body', actual: $manifest['custom-rijks-sans']['role']);
	}//end testStoreWritesFileAndManifest()

	/**
	 * A collision (same derived id twice) is rejected with 409 and the
	 * existing file/manifest entry is left untouched.
	 *
	 * @return void
	 */
	public function testStoreCollisionRejectedWithoutTouchingExistingFile(): void {
		$this->service->store('Rijks Sans', 'body', $this->validWoff2(), strlen($this->validWoff2()));
		$originalContent = file_get_contents($this->dataDir . '/fonts/custom-rijks-sans.woff2');

		try {
			$this->service->store('Rijks Sans', 'heading', 'wOF2different-payload', 20);
			$this->fail(message: 'Expected a RuntimeException (409).');
		} catch (RuntimeException $e) {
			$this->assertSame(expected: 409, actual: $e->getCode());
		}

		$this->assertSame(expected: $originalContent, actual: file_get_contents($this->dataDir . '/fonts/custom-rijks-sans.woff2'));
		$this->assertSame(expected: 'body', actual: $this->service->getManifest()['custom-rijks-sans']['role']);
	}//end testStoreCollisionRejectedWithoutTouchingExistingFile()

	/**
	 * Deleting a font removes the file, the manifest entry, and bumps the
	 * global revision.
	 *
	 * @return void
	 */
	public function testDeleteRemovesFileManifestAndBumpsRev(): void {
		$this->service->store('Rijks Sans', 'body', $this->validWoff2(), strlen($this->validWoff2()));
		$revBefore = $this->service->getRevision();

		$this->assertTrue(condition: $this->service->delete(id: 'custom-rijks-sans'));

		$this->assertFileDoesNotExist(filename: $this->dataDir . '/fonts/custom-rijks-sans.woff2');
		$this->assertArrayNotHasKey(key: 'custom-rijks-sans', array: $this->service->getManifest());
		$this->assertGreaterThan(expected: $revBefore, actual: $this->service->getRevision());
	}//end testDeleteRemovesFileManifestAndBumpsRev()

	/**
	 * Deleting an unknown (but well-formed) id returns false.
	 *
	 * @return void
	 */
	public function testDeleteUnknownIdReturnsFalse(): void {
		$this->assertFalse(condition: $this->service->delete(id: 'custom-ghost'));
	}//end testDeleteUnknownIdReturnsFalse()

	/**
	 * getFont() returns null for an id absent from the manifest, even when
	 * a stray file of that name exists on disk — the manifest is the gate.
	 *
	 * @return void
	 */
	public function testGetFontReturnsNullForUnknownIdEvenIfFileExists(): void {
		mkdir($this->dataDir . '/fonts', 0777, true);
		file_put_contents($this->dataDir . '/fonts/custom-ghost.woff2', $this->validWoff2());

		$this->assertNull(actual: $this->service->getFont(id: 'custom-ghost'));
	}//end testGetFontReturnsNullForUnknownIdEvenIfFileExists()

	/**
	 * getFont() returns the file for a font actually present in the
	 * manifest.
	 *
	 * @return void
	 */
	public function testGetFontReturnsFileForKnownId(): void {
		$this->service->store('Rijks Sans', 'body', $this->validWoff2(), strlen($this->validWoff2()));

		$file = $this->service->getFont(id: 'custom-rijks-sans');
		$this->assertNotNull(actual: $file);
		$this->assertSame(expected: $this->validWoff2(), actual: $file->getContent());
	}//end testGetFontReturnsFileForKnownId()

	/**
	 * readFontBytes() returns the raw bytes for a known font.
	 *
	 * @return void
	 */
	public function testReadFontBytesReturnsContentForKnownId(): void {
		$this->service->store('Rijks Sans', 'body', $this->validWoff2(), strlen($this->validWoff2()));

		$this->assertSame(expected: $this->validWoff2(), actual: $this->service->readFontBytes(id: 'custom-rijks-sans'));
	}//end testReadFontBytesReturnsContentForKnownId()

	/**
	 * readFontBytes() returns null for an unknown id (manifest is the gate).
	 *
	 * @return void
	 */
	public function testReadFontBytesReturnsNullForUnknownId(): void {
		$this->assertNull(actual: $this->service->readFontBytes(id: 'custom-ghost'));
	}//end testReadFontBytesReturnsNullForUnknownId()

	/**
	 * Path-traversal-shaped ids never reach appdata lookups for delete() or
	 * getFont() — they are rejected purely by id-shape validation.
	 *
	 * @return void
	 */
	public function testPathTraversalIdsNeverReachAppData(): void {
		$traversalIds = ['../../config', 'custom-a/../b', "custom-a\0b", '../../../etc/passwd'];

		foreach ($traversalIds as $id) {
			$this->assertFalse(condition: $this->service->delete(id: $id), message: 'delete() must reject: ' . $id);
			$this->assertNull(actual: $this->service->getFont(id: $id), message: 'getFont() must reject: ' . $id);
		}
	}//end testPathTraversalIdsNeverReachAppData()

	/**
	 * The 21st font is rejected with 409 (per-instance cap of 20).
	 *
	 * @return void
	 */
	public function test21stFontRejected(): void {
		for ($i = 0; $i < 20; $i++) {
			$this->service->store('Font ' . $i, 'body', $this->validWoff2(), strlen($this->validWoff2()));
		}

		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionCode(code: 409);
		$this->service->store('One Too Many', 'body', $this->validWoff2(), strlen($this->validWoff2()));
	}//end test21stFontRejected()

	/**
	 * hasFonts() reflects the manifest state.
	 *
	 * @return void
	 */
	public function testHasFontsFalseWhenEmpty(): void {
		$this->assertFalse(condition: $this->service->hasFonts());
		$this->service->store('Rijks Sans', 'body', $this->validWoff2(), strlen($this->validWoff2()));
		$this->assertTrue(condition: $this->service->hasFonts());
	}//end testHasFontsFalseWhenEmpty()

	/**
	 * buildCss() returns an empty string when no fonts are configured.
	 *
	 * @return void
	 */
	public function testBuildCssEmptyWhenNoFonts(): void {
		$this->assertSame(expected: '', actual: $this->service->buildCss());
	}//end testBuildCssEmptyWhenNoFonts()

	/**
	 * buildCss() emits @font-face + the body font-family override with the
	 * Fira Sans fallback chain preserved verbatim after the custom family.
	 *
	 * @return void
	 */
	public function testBuildCssBodyOverride(): void {
		$this->service->store('Rijks Sans', 'body', $this->validWoff2(), strlen($this->validWoff2()));

		$css = $this->service->buildCss();

		$this->assertStringContainsString(needle: '@font-face', haystack: $css);
		$this->assertStringContainsString(needle: "format('woff2')", haystack: $css);
		$this->assertStringContainsString(needle: 'font-display: swap', haystack: $css);
		$this->assertStringContainsString(
			needle: '--nldesign-font-family: "Rijks Sans", ' . FontService::DEFAULT_FONT_FAMILY . ';',
			haystack: $css
		);
		$this->assertStringContainsString(needle: "'Fira Sans'", haystack: $css);
	}//end testBuildCssBodyOverride()

	/**
	 * buildCss() overrides the heading font token when a heading-role font
	 * is stored, independently of the body override.
	 *
	 * @return void
	 */
	public function testBuildCssHeadingOverride(): void {
		$this->service->store('Rijks Display', 'heading', $this->validWoff2(), strlen($this->validWoff2()));

		$css = $this->service->buildCss();

		$this->assertStringContainsString(
			needle: '--nldesign-typography-heading-font-family: "Rijks Display", ' . FontService::DEFAULT_FONT_FAMILY . ';',
			haystack: $css
		);
		$this->assertStringNotContainsString(needle: '--nldesign-font-family: "Rijks Display"', haystack: $css);
	}//end testBuildCssHeadingOverride()

	/**
	 * A display name with an embedded quote is CSS-escaped so the
	 * stylesheet remains parseable with no injected rules.
	 *
	 * @return void
	 */
	public function testBuildCssEscapesDisplayName(): void {
		$this->service->store('Test"Font', 'body', $this->validWoff2(), strlen($this->validWoff2()));

		$css = $this->service->buildCss();

		$this->assertStringContainsString(needle: 'Test\\"Font', haystack: $css);
		$this->assertStringNotContainsString(needle: 'Test"Font"', haystack: $css);
	}//end testBuildCssEscapesDisplayName()

	/**
	 * isValidId() only accepts the prefixed slug charset.
	 *
	 * @return void
	 */
	public function testIsValidId(): void {
		$this->assertTrue(condition: $this->service->isValidId(id: 'custom-rijks-sans'));
		$this->assertFalse(condition: $this->service->isValidId(id: 'rijks-sans'));
		$this->assertFalse(condition: $this->service->isValidId(id: 'custom-../x'));
		$this->assertFalse(condition: $this->service->isValidId(id: 'custom-UPPER'));
	}//end testIsValidId()
}//end class
