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
	private string $path;

	public function __construct(string $path) {
		$this->path = $path;
	}

	public function getName(): string {
		return basename($this->path);
	}

	public function getSize(): int|float {
		return (int)filesize($this->path);
	}

	public function getETag(): string {
		return (string)md5_file($this->path);
	}

	public function getMTime(): int {
		return (int)filemtime($this->path);
	}

	public function getContent(): string {
		return (string)file_get_contents($this->path);
	}

	public function putContent($data): void {
		file_put_contents($this->path, $data);
	}

	public function delete(): void {
		unlink($this->path);
	}

	public function getMimeType(): string {
		return 'font/woff2';
	}

	public function getExtension(): string {
		return (string)pathinfo($this->path, PATHINFO_EXTENSION);
	}

	public function read() {
		return fopen($this->path, 'rb');
	}

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
	private string $dir;

	public function __construct(string $dir) {
		$this->dir = $dir;
		if (is_dir($dir) === false) {
			mkdir($dir, 0777, true);
		}
	}

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

	public function fileExists(string $name): bool {
		return file_exists($this->dir . '/' . $name);
	}

	public function getFile(string $name): ISimpleFile {
		$path = $this->dir . '/' . $name;
		if (file_exists($path) === false) {
			throw new NotFoundException();
		}
		return new FontServiceFakeSimpleFile($path);
	}

	public function newFile(string $name, $content = null): ISimpleFile {
		$path = $this->dir . '/' . $name;
		file_put_contents($path, $content ?? '');
		return new FontServiceFakeSimpleFile($path);
	}

	public function delete(): void {
		foreach (scandir($this->dir) as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}
			unlink($this->dir . '/' . $entry);
		}
		rmdir($this->dir);
	}

	public function getName(): string {
		return basename($this->dir);
	}

	public function getFolder(string $name): ISimpleFolder {
		$path = $this->dir . '/' . $name;
		if (is_dir($path) === false) {
			throw new NotFoundException();
		}
		return new FontServiceFakeSimpleFolder($path);
	}

	public function newFolder(string $path): ISimpleFolder {
		return new FontServiceFakeSimpleFolder($this->dir . '/' . $path);
	}

	// ISimpleFolder::getOrCreateFolder() is @since 35.0.0. The fake implements
	// it so the suite loads on a stable35 leg; on the older legs it is simply
	// a method nothing calls.
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
	private string $root;

	public function __construct(string $root) {
		$this->root = $root;
	}

	public function getFolder(string $name): ISimpleFolder {
		$path = $this->root . '/' . $name;
		if (is_dir($path) === false) {
			throw new NotFoundException();
		}
		return new FontServiceFakeSimpleFolder($path);
	}

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
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->dataDir = sys_get_temp_dir() . '/nldesign-font-test-' . uniqid();
		mkdir($this->dataDir, 0777, true);

		$appData = new FontServiceFakeAppData($this->dataDir);

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->appConfig[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->appConfig[$key] = $value;
			}
		);

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRoute')->willReturnCallback(
			fn (string $route, array $args = []) => '/apps/thematiq/fonts/' . ($args['id'] ?? '') . '.woff2'
		);

		$this->service = new FontService($appData, $config, $urlGenerator, new FontValidator());
	}//end setUp()

	/**
	 * Remove the temp appdata dir after each test.
	 */
	protected function tearDown(): void {
		$this->rrmdir($this->dataDir);
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
	 * A valid woff2 payload for tests that don't care about exact bytes.
	 *
	 * @return string
	 */
	private function validWoff2(): string {
		return "wOF2\x00\x01\x02\x03payload-bytes-not-a-real-font-but-passes-the-magic-check";
	}//end validWoff2()

	/**
	 * Storing writes `custom-{slug}.woff2` and a manifest entry.
	 */
	public function testStoreWritesFileAndManifest(): void {
		$result = $this->service->store(
			displayName: 'Rijks Sans',
			role: 'body',
			bytes: $this->validWoff2(),
			reportedSize: strlen($this->validWoff2())
		);

		$this->assertSame('custom-rijks-sans', $result['id']);
		$this->assertFileExists($this->dataDir . '/fonts/custom-rijks-sans.woff2');

		$manifest = $this->service->getManifest();
		$this->assertArrayHasKey('custom-rijks-sans', $manifest);
		$this->assertSame('Rijks Sans', $manifest['custom-rijks-sans']['name']);
		$this->assertSame('body', $manifest['custom-rijks-sans']['role']);
	}//end testStoreWritesFileAndManifest()

	/**
	 * A collision (same derived id twice) is rejected with 409 and the
	 * existing file/manifest entry is left untouched.
	 */
	public function testStoreCollisionRejectedWithoutTouchingExistingFile(): void {
		$this->service->store('Rijks Sans', 'body', $this->validWoff2(), strlen($this->validWoff2()));
		$originalContent = file_get_contents($this->dataDir . '/fonts/custom-rijks-sans.woff2');

		try {
			$this->service->store('Rijks Sans', 'heading', 'wOF2different-payload', 20);
			$this->fail('Expected a RuntimeException (409).');
		} catch (RuntimeException $e) {
			$this->assertSame(409, $e->getCode());
		}

		$this->assertSame($originalContent, file_get_contents($this->dataDir . '/fonts/custom-rijks-sans.woff2'));
		$this->assertSame('body', $this->service->getManifest()['custom-rijks-sans']['role']);
	}//end testStoreCollisionRejectedWithoutTouchingExistingFile()

	/**
	 * Deleting a font removes the file, the manifest entry, and bumps the
	 * global revision.
	 */
	public function testDeleteRemovesFileManifestAndBumpsRev(): void {
		$this->service->store('Rijks Sans', 'body', $this->validWoff2(), strlen($this->validWoff2()));
		$revBefore = $this->service->getRevision();

		$this->assertTrue($this->service->delete(id: 'custom-rijks-sans'));

		$this->assertFileDoesNotExist($this->dataDir . '/fonts/custom-rijks-sans.woff2');
		$this->assertArrayNotHasKey('custom-rijks-sans', $this->service->getManifest());
		$this->assertGreaterThan($revBefore, $this->service->getRevision());
	}//end testDeleteRemovesFileManifestAndBumpsRev()

	/**
	 * Deleting an unknown (but well-formed) id returns false.
	 */
	public function testDeleteUnknownIdReturnsFalse(): void {
		$this->assertFalse($this->service->delete(id: 'custom-ghost'));
	}//end testDeleteUnknownIdReturnsFalse()

	/**
	 * getFont() returns null for an id absent from the manifest, even when
	 * a stray file of that name exists on disk — the manifest is the gate.
	 */
	public function testGetFontReturnsNullForUnknownIdEvenIfFileExists(): void {
		mkdir($this->dataDir . '/fonts', 0777, true);
		file_put_contents($this->dataDir . '/fonts/custom-ghost.woff2', $this->validWoff2());

		$this->assertNull($this->service->getFont(id: 'custom-ghost'));
	}//end testGetFontReturnsNullForUnknownIdEvenIfFileExists()

	/**
	 * getFont() returns the file for a font actually present in the
	 * manifest.
	 */
	public function testGetFontReturnsFileForKnownId(): void {
		$this->service->store('Rijks Sans', 'body', $this->validWoff2(), strlen($this->validWoff2()));

		$file = $this->service->getFont(id: 'custom-rijks-sans');
		$this->assertNotNull($file);
		$this->assertSame($this->validWoff2(), $file->getContent());
	}//end testGetFontReturnsFileForKnownId()

	/**
	 * readFontBytes() returns the raw bytes for a known font.
	 */
	public function testReadFontBytesReturnsContentForKnownId(): void {
		$this->service->store('Rijks Sans', 'body', $this->validWoff2(), strlen($this->validWoff2()));

		$this->assertSame($this->validWoff2(), $this->service->readFontBytes(id: 'custom-rijks-sans'));
	}//end testReadFontBytesReturnsContentForKnownId()

	/**
	 * readFontBytes() returns null for an unknown id (manifest is the gate).
	 */
	public function testReadFontBytesReturnsNullForUnknownId(): void {
		$this->assertNull($this->service->readFontBytes(id: 'custom-ghost'));
	}//end testReadFontBytesReturnsNullForUnknownId()

	/**
	 * Path-traversal-shaped ids never reach appdata lookups for delete() or
	 * getFont() — they are rejected purely by id-shape validation.
	 */
	public function testPathTraversalIdsNeverReachAppData(): void {
		$traversalIds = ['../../config', 'custom-a/../b', "custom-a\0b", '../../../etc/passwd'];

		foreach ($traversalIds as $id) {
			$this->assertFalse($this->service->delete(id: $id), 'delete() must reject: ' . $id);
			$this->assertNull($this->service->getFont(id: $id), 'getFont() must reject: ' . $id);
		}
	}//end testPathTraversalIdsNeverReachAppData()

	/**
	 * The 21st font is rejected with 409 (per-instance cap of 20).
	 */
	public function test21stFontRejected(): void {
		for ($i = 0; $i < 20; $i++) {
			$this->service->store('Font ' . $i, 'body', $this->validWoff2(), strlen($this->validWoff2()));
		}

		$this->expectException(RuntimeException::class);
		$this->expectExceptionCode(409);
		$this->service->store('One Too Many', 'body', $this->validWoff2(), strlen($this->validWoff2()));
	}//end test21stFontRejected()

	/**
	 * hasFonts() reflects the manifest state.
	 */
	public function testHasFontsFalseWhenEmpty(): void {
		$this->assertFalse($this->service->hasFonts());
		$this->service->store('Rijks Sans', 'body', $this->validWoff2(), strlen($this->validWoff2()));
		$this->assertTrue($this->service->hasFonts());
	}//end testHasFontsFalseWhenEmpty()

	/**
	 * buildCss() returns an empty string when no fonts are configured.
	 */
	public function testBuildCssEmptyWhenNoFonts(): void {
		$this->assertSame('', $this->service->buildCss());
	}//end testBuildCssEmptyWhenNoFonts()

	/**
	 * buildCss() emits @font-face + the body font-family override with the
	 * Fira Sans fallback chain preserved verbatim after the custom family.
	 */
	public function testBuildCssBodyOverride(): void {
		$this->service->store('Rijks Sans', 'body', $this->validWoff2(), strlen($this->validWoff2()));

		$css = $this->service->buildCss();

		$this->assertStringContainsString('@font-face', $css);
		$this->assertStringContainsString("format('woff2')", $css);
		$this->assertStringContainsString('font-display: swap', $css);
		$this->assertStringContainsString(
			'--nldesign-font-family: "Rijks Sans", ' . FontService::DEFAULT_FONT_FAMILY . ';',
			$css
		);
		$this->assertStringContainsString("'Fira Sans'", $css);
	}//end testBuildCssBodyOverride()

	/**
	 * buildCss() overrides the heading font token when a heading-role font
	 * is stored, independently of the body override.
	 */
	public function testBuildCssHeadingOverride(): void {
		$this->service->store('Rijks Display', 'heading', $this->validWoff2(), strlen($this->validWoff2()));

		$css = $this->service->buildCss();

		$this->assertStringContainsString(
			'--nldesign-typography-heading-font-family: "Rijks Display", ' . FontService::DEFAULT_FONT_FAMILY . ';',
			$css
		);
		$this->assertStringNotContainsString('--nldesign-font-family: "Rijks Display"', $css);
	}//end testBuildCssHeadingOverride()

	/**
	 * A display name with an embedded quote is CSS-escaped so the
	 * stylesheet remains parseable with no injected rules.
	 */
	public function testBuildCssEscapesDisplayName(): void {
		$this->service->store('Test"Font', 'body', $this->validWoff2(), strlen($this->validWoff2()));

		$css = $this->service->buildCss();

		$this->assertStringContainsString('Test\\"Font', $css);
		$this->assertStringNotContainsString('Test"Font"', $css);
	}//end testBuildCssEscapesDisplayName()

	/**
	 * isValidId() only accepts the prefixed slug charset.
	 */
	public function testIsValidId(): void {
		$this->assertTrue($this->service->isValidId(id: 'custom-rijks-sans'));
		$this->assertFalse($this->service->isValidId(id: 'rijks-sans'));
		$this->assertFalse($this->service->isValidId(id: 'custom-../x'));
		$this->assertFalse($this->service->isValidId(id: 'custom-UPPER'));
	}//end testIsValidId()
}//end class
