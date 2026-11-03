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
use OCA\Thematiq\Service\OverridesStorage;
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
		$this->store = new DirectoryRuntimeFileStore(root: $this->storeDir);
		$this->appManager = $this->createMock(originalClassName: IAppManager::class);
		$this->appManager->method('getAppPath')->willReturn($this->appDir);
	}//end setUp()

	protected function tearDown(): void {
		$this->remove(path: $this->base);
		parent::tearDown();
	}//end tearDown()

	private function remove(string $path): void {
		if (is_dir($path) === true) {
			foreach (scandir($path) ?: [] as $entry) {
				if ($entry !== '.' && $entry !== '..') {
					$this->remove(path: $path . '/' . $entry);
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
		$config = $this->createMock(originalClassName: IConfig::class);
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
		return new DarkPaletteService(contrast: new ContrastService(), parser: new CssParserService(), appManager: $this->appManager, logger: $this->createMock(originalClassName: LoggerInterface::class), store: $this->store);
	}//end darkPalette()

	private function locator(): RuntimeFileLocator {
		$urls = $this->createMock(originalClassName: IURLGenerator::class);
		$urls->method('linkToRoute')->willReturnCallback(static fn (string $route, array $args): string => '/runtime/' . $args['name']);

		return new RuntimeFileLocator(appManager: $this->appManager, store: $this->store, urlGenerator: $urls, tempManager: $this->createMock(originalClassName: ITempManager::class));
	}//end locator()

	public function testACustomHouseStyleLeavesTheAppDirectoryUnchanged(): void {
		$before = $this->hashTree(root: $this->appDir);
		$config = $this->config();
		$dark = $this->darkPalette();

		$sets = new CustomTokenSetService(store: $this->store, config: $config, validator: new CustomTokenSetValidator(), contrast: new ContrastService(), darkPalette: $dark);
		$stored = $sets->store(
			displayName: 'Gemeente Voorbeeld',
			description: '',
			declarations: ['--nldesign-color-primary' => '#24578f'],
			css: ':root { --nldesign-color-primary: #24578f; }',
			logoAsset: ['path' => 'logo.png', 'contents' => 'PNGDATA'],
		);
		$dark->generateAndWrite(setId: (string)$stored['id'], force: true);
		(new CustomOverridesService(
			storage: new OverridesStorage(
				store: $this->store,
				cssParser: new CssParserService(),
				darkPalette: $dark
			)
		))->write(tokens: ['--color-primary' => '#24578f']);
		$css = new CustomCssService(store: $this->store, appConfig: $this->createMock(originalClassName: IAppConfig::class), validator: new CustomCssValidator());
		$this->assertSame(expected: [], actual: $css->write(css: '.header { color: red; }'));

		$this->assertSame(expected: $before, actual: $this->hashTree(root: $this->appDir), message: 'a writer changed, added or removed a file in the app directory');
		$this->assertTrue(condition: $this->store->exists('css/tokens/' . $stored['id'] . '.css'));
		$this->assertTrue(condition: $this->store->exists('img/logos/' . $stored['id'] . '.png'));
		$this->assertTrue(condition: $this->store->exists('css/custom-overrides.css'));
		$this->assertTrue(condition: $this->store->exists(CustomCssService::FILE));
	}//end testACustomHouseStyleLeavesTheAppDirectoryUnchanged()

	public function testAnUploadedSetIsListedAndValid(): void {
		$this->store->write('css/tokens/custom-gemeente-voorbeeld.css', ':root {}');
		$this->appValues['thematiq/custom_token_sets'] = json_encode(['custom-gemeente-voorbeeld' => ['name' => 'Gemeente Voorbeeld']]);
		$service = new TokenSetService(
			appManager: $this->appManager,
			config: $this->config(),
			logger: $this->createMock(originalClassName: LoggerInterface::class),
			audit: $this->createMock(originalClassName: \OCA\Thematiq\Service\ShippedTokenSetAuditService::class),
			cacheFactory: $this->createMock(originalClassName: \OCP\ICacheFactory::class),
			vocabularyAudit: $this->createMock(originalClassName: \OCA\Thematiq\Service\TokenSetVocabularyAuditService::class),
			store: $this->store,
		);

		$this->assertTrue(condition: $service->isValidTokenSet('custom-gemeente-voorbeeld'));
		$this->assertContains(needle: 'custom-gemeente-voorbeeld', haystack: array_column($service->getAvailableTokenSets(), 'id'));
		$this->assertContains(needle: 'utrecht', haystack: array_column($service->getAvailableTokenSets(), 'id'));
	}//end testAnUploadedSetIsListedAndValid()

	public function testAShippedDarkVariantIsNeverRewrittenAtRuntime(): void {
		$before = $this->hashTree(root: $this->appDir);
		$result = $this->darkPalette()->generateAndWrite(setId: 'utrecht', force: true);

		$this->assertSame(expected: 'shipped', actual: $result['reason']);
		$this->assertFalse(condition: $result['written']);
		$this->assertSame(expected: $before, actual: $this->hashTree(root: $this->appDir));
		$this->assertFalse(condition: $this->store->exists('css/tokens/dark/utrecht.css'));
	}//end testAShippedDarkVariantIsNeverRewrittenAtRuntime()

	public function testAnUploadedSetGetsItsDarkVariantInTheStore(): void {
		$this->store->write('css/tokens/custom-gemeente-voorbeeld.css', ':root { --nldesign-color-primary: #24578f; }');
		$result = $this->darkPalette()->generateAndWrite(setId: 'custom-gemeente-voorbeeld');

		$this->assertTrue(condition: $result['written'], message: $result['reason']);
		$this->assertStringContainsString(needle: 'DarkPaletteService v' . DarkPaletteService::GENERATOR_VERSION, haystack: (string)$this->store->read('css/tokens/dark/custom-gemeente-voorbeeld.css'));
		$this->assertFileDoesNotExist(filename: $this->appDir . '/css/tokens/dark/custom-gemeente-voorbeeld.css');
	}//end testAnUploadedSetGetsItsDarkVariantInTheStore()

	public function testTheRouteServesARuntimeFileWithAPolicyThatRunsNothing(): void {
		$this->store->write('img/logos/custom-gemeente-voorbeeld.svg', '<svg><script>alert(1)</script></svg>');
		$controller = new RuntimeFileController(appName: 'thematiq', request: $this->createMock(originalClassName: IRequest::class), files: $this->locator());

		$response = $controller->serve('img/logos/custom-gemeente-voorbeeld.svg');
		$this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
		$headers = $response->getHeaders();
		$this->assertSame(expected: 'image/svg+xml', actual: $headers['Content-Type']);
		$this->assertSame(expected: 'nosniff', actual: $headers['X-Content-Type-Options']);
		$this->assertStringContainsString(needle: 'immutable', haystack: $headers['Cache-Control']);
		$policy = $response->getContentSecurityPolicy()->buildPolicy();
		$this->assertStringContainsString(needle: "default-src 'none'", haystack: $policy);
		$this->assertStringNotContainsString(needle: 'script-src', haystack: str_replace("script-src 'none'", '', $policy));
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
	 *
	 * @return void
	 */
	public function testTheRouteRefusesEverythingElse(string $name): void {
		$this->store->write('css/tokens/utrecht.css', ':root { --nldesign-color-primary: red; }');
		$controller = new RuntimeFileController(appName: 'thematiq', request: $this->createMock(originalClassName: IRequest::class), files: $this->locator());

		$this->assertSame(expected: Http::STATUS_NOT_FOUND, actual: $controller->serve($name)->getStatus());
	}//end testTheRouteRefusesEverythingElse()

	public function testTheRepairStepMovesAnOlderInstallsFilesAndKeepsShippedOnes(): void {
		file_put_contents($this->appDir . '/css/custom-overrides.css', ':root { --color-primary: #111111; }');
		file_put_contents($this->appDir . '/css/tokens/custom-oud.css', ':root {}');
		file_put_contents($this->appDir . '/img/logos/utrecht-captured-logo.png', 'PNG');
		$shippedBefore = hash_file('sha256', $this->appDir . '/css/tokens/utrecht.css');

		(new MoveRuntimeFilesToAppData(appManager: $this->appManager, store: $this->store, logger: $this->createMock(originalClassName: LoggerInterface::class)))->run($this->createMock(originalClassName: IOutput::class));

		foreach (['css/custom-overrides.css', 'css/tokens/custom-oud.css', 'img/logos/utrecht-captured-logo.png'] as $name) {
			$this->assertTrue(condition: $this->store->exists($name), message: $name . ' was not moved into the store');
			$this->assertFileDoesNotExist(filename: $this->appDir . '/' . $name, message: $name . ' is still in the app directory');
		}

		$this->assertSame(expected: $shippedBefore, actual: hash_file('sha256', $this->appDir . '/css/tokens/utrecht.css'), message: 'a shipped file was touched');
		$this->assertFileExists(filename: $this->appDir . '/img/logos/utrecht.svg');
	}//end testTheRepairStepMovesAnOlderInstallsFilesAndKeepsShippedOnes()

	public function testTheRepairStepKeepsANewerStoredCopy(): void {
		$this->store->write('css/custom-overrides.css', ':root { --color-primary: #222222; }');
		file_put_contents($this->appDir . '/css/custom-overrides.css', ':root { --color-primary: #111111; }');

		(new MoveRuntimeFilesToAppData(appManager: $this->appManager, store: $this->store, logger: $this->createMock(originalClassName: LoggerInterface::class)))->run($this->createMock(originalClassName: IOutput::class));

		$this->assertStringContainsString(needle: '#222222', haystack: (string)$this->store->read('css/custom-overrides.css'));
		$this->assertFileDoesNotExist(filename: $this->appDir . '/css/custom-overrides.css');
	}//end testTheRepairStepKeepsANewerStoredCopy()

	public function testEveryShippedDarkVariantIsFreshForItsSource(): void {
		// Shipped dark variants are build output now: the release may never
		// rewrite them, so a generator change without regeneration has to fail here.
		$repo = \dirname(__DIR__, 4);
		$reader = new SetFileReader();
		// A set the generator skips may ship a hand-written variant instead
		// (hoog-contrast, thematiq#1023): the generator never touches it, so
		// it cannot go stale. Only an ineligible set may carry one.
		$skipped = (new \ReflectionClassConstant(DarkPaletteService::class, 'SKIPPED_DESIGN_SYSTEMS'))->getValue();
		$designSystems = array_column((json_decode((string)file_get_contents($repo . '/token-sets.json'), true) ?: []), 'design_system', 'id');
		$stale = [];
		foreach (glob($repo . '/css/tokens/dark/*.css') ?: [] as $dark) {
			$id = basename($dark, '.css');
			$source = $reader->read($repo, 'css/tokens/' . $id . '.css', null);
			$header = substr((string)file_get_contents($dark), 0, 512);
			if (str_starts_with($header, '/* HAND-WRITTEN ') === true
				&& in_array(($designSystems[$id] ?? 'nldesign'), $skipped, true) === true
			) {
				continue;
			}

			if ($source === null
				|| str_contains($header, 'DarkPaletteService v' . DarkPaletteService::GENERATOR_VERSION . ' ') === false
				|| str_contains($header, 'sha256:' . hash('sha256', $source)) === false
			) {
				$stale[] = $id;
			}
		}

		$this->assertSame(expected: [], actual: $stale, message: 'run php scripts/generate-dark-variants.php and commit the result');
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

		$this->assertSame(expected: [], actual: $offenders, message: 'write runtime files through RuntimeFileStore, or add the file here with a reason');
	}//end testNothingInLibWritesFilesOutsideTheKnownPlaces()
}//end class
