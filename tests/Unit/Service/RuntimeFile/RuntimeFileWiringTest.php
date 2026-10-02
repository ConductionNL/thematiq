<?php

/**
 * Runtime files end to end: nothing writes into the app directory, the route
 * serves only runtime files, and the repair step moves an older install's.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/runtime-file-storage/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service\RuntimeFile;

use OCA\Thematiq\Controller\RuntimeFileController;
use OCA\Thematiq\Repair\MoveRuntimeFilesToAppData;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomCssService;
use OCA\Thematiq\Service\CustomCssValidator;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileLocator;
use OCA\Thematiq\Service\RuntimeFile\SetFileReader;
use OCA\Thematiq\Service\TokenSetService;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IRequest;
use OCP\ITempManager;
use OCP\IURLGenerator;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The change exists for one user-visible fact: an admin who uploads their own
 * house style no longer gets a code integrity warning. Nextcloud raises that
 * warning when the signed app directory differs from the release, so the
 * headline test hashes a copy of the app directory, runs every writer, and
 * hashes it again.
 */
class RuntimeFileWiringTest extends TestCase {

	private string $base;
	private string $appDir;
	private string $storeDir;
	private DirectoryRuntimeFileStore $store;
	private IAppManager $appManager;

	/** @var array<string, string> */
	private array $appValues = [];

	protected function setUp(): void {
		parent::setUp();
		$this->base = sys_get_temp_dir() . '/thematiq-wiring-' . bin2hex(random_bytes(4));
		$this->appDir = $this->base . '/app';
		$this->storeDir = $this->base . '/store';
		$repo = \dirname(__DIR__, 4);
		foreach (['token-sets.json', 'css/systems/nldesign/defaults.css', 'css/tokens/utrecht.css', 'css/tokens/dark/utrecht.css', 'img/logos/utrecht.svg'] as $file) {
			@mkdir(\dirname($this->appDir . '/' . $file), 0777, true);
			copy($repo . '/' . $file, $this->appDir . '/' . $file);
		}

		mkdir($this->storeDir, 0777, true);
		$this->store = new DirectoryRuntimeFileStore($this->storeDir);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->appManager->method('getAppPath')->willReturn($this->appDir);
	}//end setUp()

	protected function tearDown(): void {
		$this->remove($this->base);
		parent::tearDown();
	}//end tearDown()

	private function remove(string $path): void {
		if (is_dir($path) === true) {
			foreach (scandir($path) ?: [] as $entry) {
				if ($entry !== '.' && $entry !== '..') {
					$this->remove($path . '/' . $entry);
				}
			}

			rmdir($path);
		} elseif (is_file($path) === true) {
			unlink($path);
		}
	}//end remove()

	/**
	 * Every file under a directory, relative path => sha256.
	 *
	 * @return array<string, string>
	 */
	private function hashTree(string $root): array {
		$hashes = [];
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
		foreach ($iterator as $file) {
			$hashes[substr($file->getPathname(), (strlen($root) + 1))] = hash_file('sha256', $file->getPathname());
		}

		ksort($hashes);

		return $hashes;
	}//end hashTree()

	private function config(): IConfig {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = ''): string => ($this->appValues[$app . '/' . $key] ?? (string)$default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->appValues[$app . '/' . $key] = (string)$value;
			}
		);

		return $config;
	}//end config()

	private function darkPalette(): DarkPaletteService {
		return new DarkPaletteService(new ContrastService(), new CssParserService(), $this->appManager, $this->createMock(LoggerInterface::class), $this->store);
	}//end darkPalette()

	private function locator(): RuntimeFileLocator {
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkToRoute')->willReturnCallback(static fn (string $route, array $args): string => '/runtime/' . $args['name']);

		return new RuntimeFileLocator($this->appManager, $this->store, $urls, $this->createMock(ITempManager::class));
	}//end locator()

	public function testACustomHouseStyleLeavesTheAppDirectoryUnchanged(): void {
		$before = $this->hashTree($this->appDir);
		$config = $this->config();
		$dark = $this->darkPalette();

		$sets = new CustomTokenSetService($this->store, $config, new CustomTokenSetValidator(), new ContrastService(), $dark);
		$stored = $sets->store(
			displayName: 'Gemeente Voorbeeld',
			description: '',
			declarations: ['--nldesign-color-primary' => '#24578f'],
			css: ':root { --nldesign-color-primary: #24578f; }',
			logoAsset: ['path' => 'logo.png', 'contents' => 'PNGDATA'],
		);
		$dark->generateAndWrite(setId: (string)$stored['id'], force: true);
		(new CustomOverridesService($this->store, new CssParserService(), $dark))->write(tokens: ['--color-primary' => '#24578f']);
		$css = new CustomCssService($this->store, $this->createMock(IAppConfig::class), new CustomCssValidator());
		$this->assertSame([], $css->write(css: '.header { color: red; }'));

		$this->assertSame($before, $this->hashTree($this->appDir), 'a writer changed, added or removed a file in the app directory');
		$this->assertTrue($this->store->exists('css/tokens/' . $stored['id'] . '.css'));
		$this->assertTrue($this->store->exists('img/logos/' . $stored['id'] . '.png'));
		$this->assertTrue($this->store->exists('css/custom-overrides.css'));
		$this->assertTrue($this->store->exists(CustomCssService::FILE));
	}//end testACustomHouseStyleLeavesTheAppDirectoryUnchanged()

	public function testAnUploadedSetIsListedAndValid(): void {
		$this->store->write('css/tokens/custom-gemeente-voorbeeld.css', ':root {}');
		$this->appValues['thematiq/custom_token_sets'] = json_encode(['custom-gemeente-voorbeeld' => ['name' => 'Gemeente Voorbeeld']]);
		$service = new TokenSetService(
			$this->appManager,
			$this->config(),
			$this->createMock(LoggerInterface::class),
			$this->createMock(\OCA\Thematiq\Service\ShippedTokenSetAuditService::class),
			$this->createMock(\OCP\ICacheFactory::class),
			$this->createMock(\OCA\Thematiq\Service\TokenSetVocabularyAuditService::class),
			$this->store,
		);

		$this->assertTrue($service->isValidTokenSet('custom-gemeente-voorbeeld'));
		$this->assertContains('custom-gemeente-voorbeeld', array_column($service->getAvailableTokenSets(), 'id'));
		$this->assertContains('utrecht', array_column($service->getAvailableTokenSets(), 'id'));
	}//end testAnUploadedSetIsListedAndValid()

	public function testAShippedDarkVariantIsNeverRewrittenAtRuntime(): void {
		$before = $this->hashTree($this->appDir);
		$result = $this->darkPalette()->generateAndWrite(setId: 'utrecht', force: true);

		$this->assertSame('shipped', $result['reason']);
		$this->assertFalse($result['written']);
		$this->assertSame($before, $this->hashTree($this->appDir));
		$this->assertFalse($this->store->exists('css/tokens/dark/utrecht.css'));
	}//end testAShippedDarkVariantIsNeverRewrittenAtRuntime()

	public function testAnUploadedSetGetsItsDarkVariantInTheStore(): void {
		$this->store->write('css/tokens/custom-gemeente-voorbeeld.css', ':root { --nldesign-color-primary: #24578f; }');
		$result = $this->darkPalette()->generateAndWrite(setId: 'custom-gemeente-voorbeeld');

		$this->assertTrue($result['written'], $result['reason']);
		$this->assertStringContainsString('DarkPaletteService v' . DarkPaletteService::GENERATOR_VERSION, (string)$this->store->read('css/tokens/dark/custom-gemeente-voorbeeld.css'));
		$this->assertFileDoesNotExist($this->appDir . '/css/tokens/dark/custom-gemeente-voorbeeld.css');
	}//end testAnUploadedSetGetsItsDarkVariantInTheStore()

	public function testTheRouteServesARuntimeFileWithAPolicyThatRunsNothing(): void {
		$this->store->write('img/logos/custom-gemeente-voorbeeld.svg', '<svg><script>alert(1)</script></svg>');
		$controller = new RuntimeFileController('thematiq', $this->createMock(IRequest::class), $this->locator());

		$response = $controller->serve('img/logos/custom-gemeente-voorbeeld.svg');
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$headers = $response->getHeaders();
		$this->assertSame('image/svg+xml', $headers['Content-Type']);
		$this->assertSame('nosniff', $headers['X-Content-Type-Options']);
		$this->assertStringContainsString('immutable', $headers['Cache-Control']);
		$policy = $response->getContentSecurityPolicy()->buildPolicy();
		$this->assertStringContainsString("default-src 'none'", $policy);
		$this->assertStringNotContainsString('script-src', str_replace("script-src 'none'", '', $policy));
	}//end testTheRouteServesARuntimeFileWithAPolicyThatRunsNothing()

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function refusedNames(): array {
		return [
			'traversal' => ['css/tokens/../../config/config.php'],
			'absolute' => ['/etc/passwd'],
			'outside the set' => ['appinfo/info.xml'],
			'absent' => ['css/tokens/custom-missing.css'],
			'a shipped set, even when stored' => ['css/tokens/utrecht.css'],
		];
	}//end refusedNames()

	/**
	 * @dataProvider refusedNames
	 */
	public function testTheRouteRefusesEverythingElse(string $name): void {
		$this->store->write('css/tokens/utrecht.css', ':root { --nldesign-color-primary: red; }');
		$controller = new RuntimeFileController('thematiq', $this->createMock(IRequest::class), $this->locator());

		$this->assertSame(Http::STATUS_NOT_FOUND, $controller->serve($name)->getStatus());
	}//end testTheRouteRefusesEverythingElse()

	public function testTheRepairStepMovesAnOlderInstallsFilesAndKeepsShippedOnes(): void {
		file_put_contents($this->appDir . '/css/custom-overrides.css', ':root { --color-primary: #111111; }');
		file_put_contents($this->appDir . '/css/tokens/custom-oud.css', ':root {}');
		file_put_contents($this->appDir . '/img/logos/utrecht-captured-logo.png', 'PNG');
		$shippedBefore = hash_file('sha256', $this->appDir . '/css/tokens/utrecht.css');

		(new MoveRuntimeFilesToAppData($this->appManager, $this->store, $this->createMock(LoggerInterface::class)))->run($this->createMock(IOutput::class));

		foreach (['css/custom-overrides.css', 'css/tokens/custom-oud.css', 'img/logos/utrecht-captured-logo.png'] as $name) {
			$this->assertTrue($this->store->exists($name), $name . ' was not moved into the store');
			$this->assertFileDoesNotExist($this->appDir . '/' . $name, $name . ' is still in the app directory');
		}

		$this->assertSame($shippedBefore, hash_file('sha256', $this->appDir . '/css/tokens/utrecht.css'), 'a shipped file was touched');
		$this->assertFileExists($this->appDir . '/img/logos/utrecht.svg');
	}//end testTheRepairStepMovesAnOlderInstallsFilesAndKeepsShippedOnes()

	public function testTheRepairStepKeepsANewerStoredCopy(): void {
		$this->store->write('css/custom-overrides.css', ':root { --color-primary: #222222; }');
		file_put_contents($this->appDir . '/css/custom-overrides.css', ':root { --color-primary: #111111; }');

		(new MoveRuntimeFilesToAppData($this->appManager, $this->store, $this->createMock(LoggerInterface::class)))->run($this->createMock(IOutput::class));

		$this->assertStringContainsString('#222222', (string)$this->store->read('css/custom-overrides.css'));
		$this->assertFileDoesNotExist($this->appDir . '/css/custom-overrides.css');
	}//end testTheRepairStepKeepsANewerStoredCopy()

	public function testEveryShippedDarkVariantIsFreshForItsSource(): void {
		// Shipped dark variants are build output now: the release may never
		// rewrite them, so a generator change without regeneration has to fail here.
		$repo = \dirname(__DIR__, 4);
		$reader = new SetFileReader();
		$stale = [];
		foreach (glob($repo . '/css/tokens/dark/*.css') ?: [] as $dark) {
			$id = basename($dark, '.css');
			$source = $reader->read($repo, 'css/tokens/' . $id . '.css', null);
			$header = substr((string)file_get_contents($dark), 0, 512);
			if ($source === null
				|| str_contains($header, 'DarkPaletteService v' . DarkPaletteService::GENERATOR_VERSION . ' ') === false
				|| str_contains($header, 'sha256:' . hash('sha256', $source)) === false
			) {
				$stale[] = $id;
			}
		}

		$this->assertSame([], $stale, 'run php scripts/generate-dark-variants.php and commit the result');
	}//end testEveryShippedDarkVariantIsFreshForItsSource()

	public function testNothingInLibWritesFilesOutsideTheKnownPlaces(): void {
		// Every place allowed to write a file, and why. A new write anywhere
		// else is how runtime files used to end up in the signed app directory.
		$allowed = [
			'Service/RuntimeFile/DirectoryRuntimeFileStore.php' => 'the store tests and the build script use; never wired in the container',
			'Service/RuntimeFile/RuntimeFileLocator.php' => 'a temporary copy from ITempManager, for core APIs that only take a path',
			'Repair/MoveRuntimeFilesToAppData.php' => 'removes an older install\'s runtime files from the app directory',
			'Command/ComplianceReport.php' => 'writes the report to the path the admin passes on the command line',
			'Command/ConfigExport.php' => 'writes the export to the path the admin passes on the command line',
			'Service/BrandingPackageReader.php' => 'writes a branding package into the directory the admin names, to keep a house style in Git',
		];
		$lib = \dirname(__DIR__, 4) . '/lib';
		$offenders = [];
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($lib, \FilesystemIterator::SKIP_DOTS));
		foreach ($iterator as $file) {
			if ($file->getExtension() !== 'php') {
				continue;
			}

			$relative = substr($file->getPathname(), (strlen($lib) + 1));
			$code = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', (string)file_get_contents($file->getPathname()));
			if (preg_match('/\b(file_put_contents|rename|unlink|mkdir|copy|touch|rmdir)\s*\(|fopen\s*\([^)]*[\'"][wax]/', (string)$code) === 1
				&& isset($allowed[$relative]) === false
			) {
				$offenders[] = $relative;
			}
		}

		$this->assertSame([], $offenders, 'write runtime files through RuntimeFileStore, or add the file here with a reason');
	}//end testNothingInLibWritesFilesOutsideTheKnownPlaces()
}//end class
