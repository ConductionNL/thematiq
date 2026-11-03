<?php

/**
 * Unit tests for CustomTokenSetService.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-5.4
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCP\App\IAppManager;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Unit tests for the custom token set storage/lifecycle service.
 *
 * Covers tasks.md#task-5.4: slug derivation, collision (409), delete-active
 * fallback, and manifest round-trip. Uses a real temp directory as the app
 * path so the atomic file write/rename is exercised end-to-end.
 */
class CustomTokenSetServiceTest extends TestCase {

	/**
	 * The temp app directory standing in for the nldesign app path.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * In-memory appconfig store: key => value.
	 *
	 * @var array<string, string>
	 */
	private array $appConfig = [];

	/**
	 * The service under test.
	 *
	 * @var CustomTokenSetService
	 */
	private CustomTokenSetService $service;

	/**
	 * Set up a temp app dir + mocked config before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/nldesign-test-' . uniqid();
		mkdir($this->appDir . '/css/tokens', 0777, true);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);

		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->appConfig[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->appConfig[$key] = $value;
			}
		);

		$this->service = new CustomTokenSetService(
			store: new DirectoryRuntimeFileStore(root: $appManager->getAppPath('thematiq')),
			config: $config,
			validator: new CustomTokenSetValidator(),
			contrast: new ContrastService(),
			darkPalette: new DarkPaletteService(
				contrast: new ContrastService(),
				parser: new CssParserService(),
				appManager: $appManager,
				logger: $this->createMock(originalClassName: LoggerInterface::class)
			)
		);
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
	 * Slug derivation lowercases, hyphenates and caps at 64 chars.
	 */
	public function testSlugify(): void {
		$this->assertSame(expected: 'gemeente-voorbeeld', actual: $this->service->slugify(name: 'Gemeente Voorbeeld'));
		$this->assertSame(expected: 'gemeente-amsterdam', actual: $this->service->slugify(name: '  Gemeente   Amsterdam!!  '));
		$this->assertSame(expected: '', actual: $this->service->slugify(name: '!!!'));
		$this->assertLessThanOrEqual(expected: 64, actual: strlen($this->service->slugify(name: str_repeat('a', 200))));
	}//end testSlugify()

	/**
	 * Storing writes the canonical CSS file and persists a manifest entry.
	 */
	public function testStoreWritesFileAndManifest(): void {
		$result = $this->service->store(
			displayName: 'Gemeente Voorbeeld',
			description: '',
			declarations: [
				'--nldesign-color-primary' => '#007bc7',
				'--nldesign-color-primary-text' => '#ffffff',
			]
		);

		$this->assertSame(expected: 'custom-gemeente-voorbeeld', actual: $result['id']);

		$path = $this->appDir . '/css/tokens/custom-gemeente-voorbeeld.css';
		$this->assertFileExists(filename: $path);
		$this->assertStringContainsString(needle: '--nldesign-color-primary: #007bc7;', haystack: file_get_contents($path));

		$manifest = $this->service->getManifest();
		$this->assertArrayHasKey(key: 'custom-gemeente-voorbeeld', array: $manifest);
		$this->assertSame(expected: 'Gemeente Voorbeeld', actual: $manifest['custom-gemeente-voorbeeld']['name']);
		// Derived theming.
		$this->assertSame(expected: '#007bc7', actual: $manifest['custom-gemeente-voorbeeld']['theming']['primary_color']);
	}//end testStoreWritesFileAndManifest()

	/**
	 * A DTCG import's declared package version is persisted verbatim in the
	 * manifest and returned by list().
	 */
	public function testStorePersistsDeclaredVersion(): void {
		$this->service->store(
			displayName: 'Gemeente Voorbeeld',
			description: '',
			declarations: ['--nldesign-color-primary' => '#007bc7'],
			version: '2.3.1'
		);

		$manifest = $this->service->getManifest();
		$this->assertSame(expected: '2.3.1', actual: $manifest['custom-gemeente-voorbeeld']['version']);

		$listed = $this->service->list();
		$this->assertSame(expected: '2.3.1', actual: $listed[0]['version']);
	}//end testStorePersistsDeclaredVersion()

	/**
	 * Absent a declared version, the manifest entry never fabricates one.
	 */
	public function testStoreWithoutVersionOmitsVersionKey(): void {
		$this->service->store(
			displayName: 'Gemeente Voorbeeld',
			description: '',
			declarations: ['--nldesign-color-primary' => '#007bc7']
		);

		$manifest = $this->service->getManifest();
		$this->assertArrayNotHasKey(key: 'version', array: $manifest['custom-gemeente-voorbeeld']);
	}//end testStoreWithoutVersionOmitsVersionKey()

	/**
	 * DTCG `$deprecated` import warnings are persisted apart from the
	 * pre-existing WCAG contrast `warnings` key and exposed via list().
	 */
	public function testStorePersistsImportWarningsSeparatelyFromContrastWarnings(): void {
		$result = $this->service->store(
			displayName: 'Gemeente Voorbeeld',
			description: '',
			declarations: ['--nldesign-color-primary' => '#007bc7'],
			importWarnings: [['path' => 'color.primary', 'message' => 'Use color.brand.primary instead']]
		);

		// The store() result's `warnings` key remains the contrast warnings.
		$this->assertSame(expected: [], actual: $result['warnings']);

		$manifest = $this->service->getManifest();
		$this->assertSame(
			expected: [['path' => 'color.primary', 'message' => 'Use color.brand.primary instead']],
			actual: $manifest['custom-gemeente-voorbeeld']['importWarnings']
		);
		$this->assertSame(expected: [], actual: $manifest['custom-gemeente-voorbeeld']['warnings']);

		$listed = $this->service->list();
		$this->assertSame(
			expected: [['path' => 'color.primary', 'message' => 'Use color.brand.primary instead']],
			actual: $listed[0]['importWarnings']
		);
	}//end testStorePersistsImportWarningsSeparatelyFromContrastWarnings()

	/**
	 * Storing the same display name twice is a 409 collision.
	 */
	public function testStoreCollisionThrows409(): void {
		$this->service->store('Gemeente Voorbeeld', '', ['--nldesign-color-primary' => '#007bc7']);

		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionCode(code: 409);
		$this->service->store('Gemeente Voorbeeld', '', ['--nldesign-color-primary' => '#000000']);
	}//end testStoreCollisionThrows409()

	/**
	 * An all-symbol name (empty slug) is a 422.
	 */
	public function testStoreEmptySlugThrows422(): void {
		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionCode(code: 422);
		$this->service->store('!!!', '', ['--nldesign-color-primary' => '#007bc7']);
	}//end testStoreEmptySlugThrows422()

	/**
	 * A low-contrast upload persists a contrast warning in the manifest.
	 */
	public function testStorePersistsContrastWarning(): void {
		$result = $this->service->store(
			displayName: 'Low Contrast',
			description: '',
			declarations: [
				'--nldesign-color-primary' => '#ffffff',
				'--nldesign-color-primary-text' => '#cccccc',
			]
		);

		$this->assertNotEmpty(actual: $result['warnings']);
		$manifest = $this->service->getManifest();
		$this->assertNotEmpty(actual: $manifest['custom-low-contrast']['warnings']);
	}//end testStorePersistsContrastWarning()

	/**
	 * Deleting a set removes the file and the manifest entry.
	 */
	public function testDeleteRemovesFileAndManifest(): void {
		$this->service->store('Gemeente Voorbeeld', '', ['--nldesign-color-primary' => '#007bc7']);
		$path = $this->appDir . '/css/tokens/custom-gemeente-voorbeeld.css';
		$this->assertFileExists(filename: $path);

		$this->assertTrue(condition: $this->service->delete(id: 'custom-gemeente-voorbeeld'));
		$this->assertFileDoesNotExist(filename: $path);
		$this->assertArrayNotHasKey(key: 'custom-gemeente-voorbeeld', array: $this->service->getManifest());
	}//end testDeleteRemovesFileAndManifest()

	/**
	 * Deleting the active set resets the active token_set to nextcloud.
	 */
	public function testDeleteActiveSetFallsBackToNextcloud(): void {
		$this->service->store('Gemeente Voorbeeld', '', ['--nldesign-color-primary' => '#007bc7']);
		$this->appConfig['token_set'] = 'custom-gemeente-voorbeeld';

		$this->service->delete(id: 'custom-gemeente-voorbeeld');

		$this->assertSame(expected: 'nextcloud', actual: $this->appConfig['token_set']);
	}//end testDeleteActiveSetFallsBackToNextcloud()

	/**
	 * A non-custom id is never deletable (path-traversal guard).
	 */
	public function testDeleteRejectsNonCustomId(): void {
		$this->assertFalse(condition: $this->service->delete(id: 'utrecht'));
		$this->assertFalse(condition: $this->service->delete(id: '../../../etc/passwd'));
	}//end testDeleteRejectsNonCustomId()

	/**
	 * list() drops manifest entries whose CSS file no longer exists.
	 */
	public function testListDropsManifestEntryWithoutFile(): void {
		$this->service->store('Gemeente Voorbeeld', '', ['--nldesign-color-primary' => '#007bc7']);
		// Delete the file behind the service's back, leaving a stale manifest entry.
		unlink($this->appDir . '/css/tokens/custom-gemeente-voorbeeld.css');

		$this->assertSame(expected: [], actual: $this->service->list());
	}//end testListDropsManifestEntryWithoutFile()

	/**
	 * isCustomId only accepts the prefixed slug charset.
	 */
	public function testIsCustomId(): void {
		$this->assertTrue(condition: $this->service->isCustomId(id: 'custom-gemeente-voorbeeld'));
		$this->assertFalse(condition: $this->service->isCustomId(id: 'utrecht'));
		$this->assertFalse(condition: $this->service->isCustomId(id: 'custom-../x'));
		$this->assertFalse(condition: $this->service->isCustomId(id: 'custom-UPPER'));
	}//end testIsCustomId()
}//end class
