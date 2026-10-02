<?php

/**
 * Unit tests for CustomTokenSetService.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-5.4
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

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->appConfig[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->appConfig[$key] = $value;
			}
		);

		$this->service = new CustomTokenSetService(
			new DirectoryRuntimeFileStore($appManager->getAppPath('thematiq')),
			$config,
			new CustomTokenSetValidator(),
			new ContrastService(),
			new DarkPaletteService(
				new ContrastService(),
				new CssParserService(),
				$appManager,
				$this->createMock(LoggerInterface::class)
			)
		);
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
	 * Slug derivation lowercases, hyphenates and caps at 64 chars.
	 */
	public function testSlugify(): void {
		$this->assertSame('gemeente-voorbeeld', $this->service->slugify(name: 'Gemeente Voorbeeld'));
		$this->assertSame('gemeente-amsterdam', $this->service->slugify(name: '  Gemeente   Amsterdam!!  '));
		$this->assertSame('', $this->service->slugify(name: '!!!'));
		$this->assertLessThanOrEqual(64, strlen($this->service->slugify(name: str_repeat('a', 200))));
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

		$this->assertSame('custom-gemeente-voorbeeld', $result['id']);

		$path = $this->appDir . '/css/tokens/custom-gemeente-voorbeeld.css';
		$this->assertFileExists($path);
		$this->assertStringContainsString('--nldesign-color-primary: #007bc7;', file_get_contents($path));

		$manifest = $this->service->getManifest();
		$this->assertArrayHasKey('custom-gemeente-voorbeeld', $manifest);
		$this->assertSame('Gemeente Voorbeeld', $manifest['custom-gemeente-voorbeeld']['name']);
		// Derived theming.
		$this->assertSame('#007bc7', $manifest['custom-gemeente-voorbeeld']['theming']['primary_color']);
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
		$this->assertSame('2.3.1', $manifest['custom-gemeente-voorbeeld']['version']);

		$listed = $this->service->list();
		$this->assertSame('2.3.1', $listed[0]['version']);
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
		$this->assertArrayNotHasKey('version', $manifest['custom-gemeente-voorbeeld']);
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
		$this->assertSame([], $result['warnings']);

		$manifest = $this->service->getManifest();
		$this->assertSame(
			[['path' => 'color.primary', 'message' => 'Use color.brand.primary instead']],
			$manifest['custom-gemeente-voorbeeld']['importWarnings']
		);
		$this->assertSame([], $manifest['custom-gemeente-voorbeeld']['warnings']);

		$listed = $this->service->list();
		$this->assertSame(
			[['path' => 'color.primary', 'message' => 'Use color.brand.primary instead']],
			$listed[0]['importWarnings']
		);
	}//end testStorePersistsImportWarningsSeparatelyFromContrastWarnings()

	/**
	 * Storing the same display name twice is a 409 collision.
	 */
	public function testStoreCollisionThrows409(): void {
		$this->service->store('Gemeente Voorbeeld', '', ['--nldesign-color-primary' => '#007bc7']);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionCode(409);
		$this->service->store('Gemeente Voorbeeld', '', ['--nldesign-color-primary' => '#000000']);
	}//end testStoreCollisionThrows409()

	/**
	 * An all-symbol name (empty slug) is a 422.
	 */
	public function testStoreEmptySlugThrows422(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionCode(422);
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

		$this->assertNotEmpty($result['warnings']);
		$manifest = $this->service->getManifest();
		$this->assertNotEmpty($manifest['custom-low-contrast']['warnings']);
	}//end testStorePersistsContrastWarning()

	/**
	 * Deleting a set removes the file and the manifest entry.
	 */
	public function testDeleteRemovesFileAndManifest(): void {
		$this->service->store('Gemeente Voorbeeld', '', ['--nldesign-color-primary' => '#007bc7']);
		$path = $this->appDir . '/css/tokens/custom-gemeente-voorbeeld.css';
		$this->assertFileExists($path);

		$this->assertTrue($this->service->delete(id: 'custom-gemeente-voorbeeld'));
		$this->assertFileDoesNotExist($path);
		$this->assertArrayNotHasKey('custom-gemeente-voorbeeld', $this->service->getManifest());
	}//end testDeleteRemovesFileAndManifest()

	/**
	 * Deleting the active set resets the active token_set to nextcloud.
	 */
	public function testDeleteActiveSetFallsBackToNextcloud(): void {
		$this->service->store('Gemeente Voorbeeld', '', ['--nldesign-color-primary' => '#007bc7']);
		$this->appConfig['token_set'] = 'custom-gemeente-voorbeeld';

		$this->service->delete(id: 'custom-gemeente-voorbeeld');

		$this->assertSame('nextcloud', $this->appConfig['token_set']);
	}//end testDeleteActiveSetFallsBackToNextcloud()

	/**
	 * A non-custom id is never deletable (path-traversal guard).
	 */
	public function testDeleteRejectsNonCustomId(): void {
		$this->assertFalse($this->service->delete(id: 'utrecht'));
		$this->assertFalse($this->service->delete(id: '../../../etc/passwd'));
	}//end testDeleteRejectsNonCustomId()

	/**
	 * list() drops manifest entries whose CSS file no longer exists.
	 */
	public function testListDropsManifestEntryWithoutFile(): void {
		$this->service->store('Gemeente Voorbeeld', '', ['--nldesign-color-primary' => '#007bc7']);
		// Delete the file behind the service's back, leaving a stale manifest entry.
		unlink($this->appDir . '/css/tokens/custom-gemeente-voorbeeld.css');

		$this->assertSame([], $this->service->list());
	}//end testListDropsManifestEntryWithoutFile()

	/**
	 * isCustomId only accepts the prefixed slug charset.
	 */
	public function testIsCustomId(): void {
		$this->assertTrue($this->service->isCustomId(id: 'custom-gemeente-voorbeeld'));
		$this->assertFalse($this->service->isCustomId(id: 'utrecht'));
		$this->assertFalse($this->service->isCustomId(id: 'custom-../x'));
		$this->assertFalse($this->service->isCustomId(id: 'custom-UPPER'));
	}//end testIsCustomId()
}//end class
