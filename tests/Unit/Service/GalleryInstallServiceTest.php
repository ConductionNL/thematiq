<?php

/**
 * Unit tests for GalleryInstallService: download, checksum, then the real upload path.
 *
 * The converter, the validator, the CSS parser and CustomTokenSetService are the real
 * classes, writing into a temporary app directory, so an install is proven to land as a
 * custom token set the dropdown can offer, not only to call the right methods.
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
 * @spec openspec/specs/theme-gallery/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\Exception\GalleryException;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\GalleryDownloadSources;
use OCA\Thematiq\Service\GalleryInstallRecords;
use OCA\Thematiq\Service\GalleryInstallService;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Service\ThemeGalleryService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCA\Thematiq\Service\TokenSetUploadPipeline;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

// The entry fixture lives in ThemeGalleryServiceTest; tests/ is not autoloaded.
require_once __DIR__ . '/ThemeGalleryServiceTest.php';

/**
 * Tests for installing a gallery entry.
 */
class GalleryInstallServiceTest extends TestCase {

	/**
	 * Temporary app directory the custom sets are written into.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * In-memory app config.
	 *
	 * @var array<string, string>
	 */
	private array $appConfig = [];

	/**
	 * The downloaded file body.
	 *
	 * @var string
	 */
	private string $body;

	/**
	 * The index entry the gallery answers with.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $entry;

	/**
	 * Audit entries written.
	 *
	 * @var array<int, array{0: string, 1: array<string, mixed>}>
	 */
	private array $audited = [];

	/**
	 * URLs downloaded.
	 *
	 * @var array<int, string>
	 */
	private array $downloads = [];

	/**
	 * The real custom set service.
	 *
	 * @var CustomTokenSetService
	 */
	private CustomTokenSetService $customSets;

	/**
	 * Set up the real sibling classes on a temporary app directory.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/thematiq-gallery-' . uniqid();
		mkdir($this->appDir . '/css/tokens', 0777, true);
		mkdir($this->appDir . '/img/logos', 0777, true);

		$this->body = (string)file_get_contents(\dirname(__DIR__, 3) . '/css/tokens/utrecht.css');
		$this->entry = ThemeGalleryServiceTest::entry(['sha256' => hash('sha256', $this->body)]);

		$tempApp = $this->createMock(originalClassName: IAppManager::class);
		$tempApp->method('getAppPath')->willReturn($this->appDir);
		$repoApp = $this->createMock(originalClassName: IAppManager::class);
		$repoApp->method('getAppPath')->willReturn(\dirname(__DIR__, 3));

		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->appConfig[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->appConfig[$key] = (string)$value;
			}
		);
		$this->config = $config;

		$this->customSets = new CustomTokenSetService(
			store: new DirectoryRuntimeFileStore(root: $this->appDir),
			config: $config,
			validator: new CustomTokenSetValidator(),
			contrast: new ContrastService(),
			darkPalette: new DarkPaletteService(contrast: new ContrastService(), parser: new CssParserService(), appManager: $tempApp, logger: $this->createMock(originalClassName: LoggerInterface::class))
		);
	}//end setUp()

	/**
	 * The config mock, shared with the service under test.
	 *
	 * @var IConfig
	 */
	private IConfig $config;

	/**
	 * Remove the temporary directory.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		exec('rm -rf ' . escapeshellarg($this->appDir));
		parent::tearDown();
	}//end tearDown()

	/**
	 * Build the service under test.
	 *
	 * @return GalleryInstallService The service.
	 */
	private function service(): GalleryInstallService {
		$gallery = $this->createMock(originalClassName: ThemeGalleryService::class);
		$gallery->method('findEntry')->willReturnCallback(
			fn (string $galleryId) => ($this->entry !== null && $this->entry['id'] === $galleryId ? $this->entry : null)
		);
		$gallery->method('installedFor')->willReturnCallback(
			function (string $galleryId): ?array {
				foreach ($this->customSets->list() as $set) {
					if (($set['provenance']['galleryId'] ?? null) === $galleryId) {
						return $set;
					}
				}

				return null;
			}
		);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willReturnCallback(
			function (string $url, array $options) {
				$this->downloads[] = $url;
				$this->assertSame(expected: 10, actual: $options['timeout']);
				$response = $this->createMock(originalClassName: IResponse::class);
				$response->method('getStatusCode')->willReturn(200);
				$response->method('getBody')->willReturn($this->body);
				return $response;
			}
		);
		$clientService = $this->createMock(originalClassName: IClientService::class);
		$clientService->method('newClient')->willReturn($client);

		$repoApp = $this->createMock(originalClassName: IAppManager::class);
		$repoApp->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$converter = new TokenSetConverterService(
			appManager: $repoApp,
			cssParser: new CssParserService(),
			contrast: new ContrastService(),
			dtcgMapper: new DesignTokensMapper(),
			fonts: $this->createMock(originalClassName: FontService::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);

		$audit = $this->createMock(originalClassName: ThemingAuditService::class);
		$audit->method('log')->willReturnCallback(
			function (string $action, array $context = []): void {
				$this->audited[] = [$action, $context];
			}
		);

		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnCallback(
			fn (string $text, $params = []) => vsprintf(str_replace(['{set}', '{reason}'], '%s', $text), (array)$params)
		);

		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('getTime')->willReturn(1_800_000_000);

		return new GalleryInstallService(
			downloads: new GalleryDownloadSources(
				gallery: $gallery,
				clientService: $clientService
			),
			pipeline: new TokenSetUploadPipeline(
				converter: $converter,
				validator: new CustomTokenSetValidator(),
				cssParser: new CssParserService()
			),
			records: new GalleryInstallRecords(
				customSets: $this->customSets,
				audit: $audit,
				config: $this->config,
				time: $time
			),
			l10n: $l10n
		);
	}//end service()

	/**
	 * A matching file is stored as a custom set that carries its provenance.
	 *
	 * @return void
	 */
	public function testAMatchingFileIsInstalledWithProvenance(): void {
		$result = $this->service()->install(galleryId: 'provincie-utrecht');

		$this->assertSame(expected: 'custom-provincie-utrecht', actual: $result['id']);
		$this->assertFalse(condition: $result['updated']);
		$this->assertSame(expected: ['https://gallery.example.nl/sets/provincie-utrecht.css'], actual: $this->downloads);
		$this->assertFileExists(filename: $this->appDir . '/css/tokens/custom-provincie-utrecht.css');

		$sets = $this->customSets->list();
		$this->assertCount(expectedCount: 1, haystack: $sets);
		$this->assertSame(
			expected: [
				'galleryId' => 'provincie-utrecht',
				'sourceUrl' => 'https://github.com/example/utrecht-tokens',
				'licence' => 'EUPL-1.2',
				'sha256' => hash('sha256', $this->body),
				'installedOn' => '2027-01-15T08:00:00Z',
			],
			actual: $sets[0]['provenance']
		);
		$this->assertSame(expected: 'custom_set_uploaded', actual: $this->audited[0][0]);
		$this->assertSame(expected: 'provincie-utrecht', actual: $this->audited[0][1]['galleryId']);
	}//end testAMatchingFileIsInstalledWithProvenance()

	/**
	 * A file that no longer matches the index stores nothing.
	 *
	 * @return void
	 */
	public function testATamperedFileIsRefused(): void {
		$this->body .= "\n:root { --nldesign-color-primary: #000000; }\n";

		try {
			$this->service()->install(galleryId: 'provincie-utrecht');
			$this->fail(message: 'A tampered file was installed.');
		} catch (GalleryException $e) {
			$this->assertSame(expected: 422, actual: $e->getCode());
			$this->assertStringContainsString(needle: 'does not match the gallery index', haystack: $e->getMessage());
		}

		$this->assertSame(expected: [], actual: $this->customSets->list());
		$this->assertSame(expected: [], actual: $this->audited);
	}//end testATamperedFileIsRefused()

	/**
	 * A file the upload path refuses (a stylesheet with selectors, not a theme) stores nothing.
	 *
	 * @return void
	 */
	public function testAFileTheUploadPathRefusesStoresNothing(): void {
		$this->body = "body { color: red; }\n";
		$this->entry = ThemeGalleryServiceTest::entry(['sha256' => hash('sha256', $this->body)]);

		try {
			$this->service()->install(galleryId: 'provincie-utrecht');
			$this->fail(message: 'A file the upload path refuses was installed.');
		} catch (GalleryException $e) {
			$this->assertSame(expected: 422, actual: $e->getCode());
			$this->assertStringContainsString(needle: 'The file was refused, so nothing was installed', haystack: $e->getMessage());
		}

		$this->assertSame(expected: [], actual: $this->customSets->list());
	}//end testAFileTheUploadPathRefusesStoresNothing()

	/**
	 * An unknown gallery id is a 404 and downloads nothing.
	 *
	 * @return void
	 */
	public function testAnUnknownEntryIsNotFound(): void {
		try {
			$this->service()->install(galleryId: 'nope');
			$this->fail(message: 'An unknown entry was installed.');
		} catch (GalleryException $e) {
			$this->assertSame(expected: 404, actual: $e->getCode());
		}

		$this->assertSame(expected: [], actual: $this->downloads);
	}//end testAnUnknownEntryIsNotFound()

	/**
	 * An update replaces the set under the same id and keeps it active.
	 *
	 * @return void
	 */
	public function testAnUpdateReplacesTheSetAndKeepsItActive(): void {
		$this->service()->install(galleryId: 'provincie-utrecht');
		$this->appConfig['token_set'] = 'custom-provincie-utrecht';

		$this->body = str_replace('#24578F', '#1d4a7a', $this->body);
		$this->entry = ThemeGalleryServiceTest::entry(['name' => 'Utrecht renamed', 'sha256' => hash('sha256', $this->body)]);
		$result = $this->service()->install(galleryId: 'provincie-utrecht');

		$this->assertSame(expected: 'custom-provincie-utrecht', actual: $result['id']);
		$this->assertTrue(condition: $result['updated']);
		$this->assertSame(expected: 'custom-provincie-utrecht', actual: $this->appConfig['token_set']);
		$sets = $this->customSets->list();
		$this->assertCount(expectedCount: 1, haystack: $sets);
		$this->assertSame(expected: hash('sha256', $this->body), actual: $sets[0]['provenance']['sha256']);
		$this->assertStringContainsStringIgnoringCase(needle: '#1d4a7a', haystack: (string)file_get_contents($this->appDir . '/css/tokens/custom-provincie-utrecht.css'));
	}//end testAnUpdateReplacesTheSetAndKeepsItActive()
}//end class
