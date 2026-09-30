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
use OCA\Thematiq\Service\GalleryInstallService;
use OCA\Thematiq\Service\ThemeGalleryService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\TokenSetConverterService;
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

		$tempApp = $this->createMock(IAppManager::class);
		$tempApp->method('getAppPath')->willReturn($this->appDir);
		$repoApp = $this->createMock(IAppManager::class);
		$repoApp->method('getAppPath')->willReturn(\dirname(__DIR__, 3));

		$config = $this->createMock(IConfig::class);
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
			$tempApp,
			$config,
			new CustomTokenSetValidator(),
			new ContrastService(),
			new DarkPaletteService(new ContrastService(), new CssParserService(), $tempApp, $this->createMock(LoggerInterface::class))
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
		$gallery = $this->createMock(ThemeGalleryService::class);
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

		$client = $this->createMock(IClient::class);
		$client->method('get')->willReturnCallback(
			function (string $url, array $options) {
				$this->downloads[] = $url;
				$this->assertSame(10, $options['timeout']);
				$response = $this->createMock(IResponse::class);
				$response->method('getStatusCode')->willReturn(200);
				$response->method('getBody')->willReturn($this->body);
				return $response;
			}
		);
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($client);

		$repoApp = $this->createMock(IAppManager::class);
		$repoApp->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$converter = new TokenSetConverterService(
			$repoApp,
			new CssParserService(),
			new ContrastService(),
			new DesignTokensMapper(),
			$this->createMock(FontService::class),
			$this->createMock(LoggerInterface::class)
		);

		$audit = $this->createMock(ThemingAuditService::class);
		$audit->method('log')->willReturnCallback(
			function (string $action, array $context = []): void {
				$this->audited[] = [$action, $context];
			}
		);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(
			fn (string $text, $params = []) => vsprintf(str_replace(['{set}', '{reason}'], '%s', $text), (array)$params)
		);

		$time = $this->createMock(ITimeFactory::class);
		$time->method('getTime')->willReturn(1_800_000_000);

		return new GalleryInstallService(
			$gallery,
			$clientService,
			$converter,
			new CustomTokenSetValidator(),
			new CssParserService(),
			$this->customSets,
			$audit,
			$this->config,
			$l10n,
			$time
		);
	}//end service()

	/**
	 * A matching file is stored as a custom set that carries its provenance.
	 *
	 * @return void
	 */
	public function testAMatchingFileIsInstalledWithProvenance(): void {
		$result = $this->service()->install(galleryId: 'provincie-utrecht');

		$this->assertSame('custom-provincie-utrecht', $result['id']);
		$this->assertFalse($result['updated']);
		$this->assertSame(['https://gallery.example.nl/sets/provincie-utrecht.css'], $this->downloads);
		$this->assertFileExists($this->appDir . '/css/tokens/custom-provincie-utrecht.css');

		$sets = $this->customSets->list();
		$this->assertCount(1, $sets);
		$this->assertSame(
			[
				'galleryId' => 'provincie-utrecht',
				'sourceUrl' => 'https://github.com/example/utrecht-tokens',
				'licence' => 'EUPL-1.2',
				'sha256' => hash('sha256', $this->body),
				'installedOn' => '2027-01-15T08:00:00Z',
			],
			$sets[0]['provenance']
		);
		$this->assertSame('custom_set_uploaded', $this->audited[0][0]);
		$this->assertSame('provincie-utrecht', $this->audited[0][1]['galleryId']);
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
			$this->fail('A tampered file was installed.');
		} catch (GalleryException $e) {
			$this->assertSame(422, $e->getCode());
			$this->assertStringContainsString('does not match the gallery index', $e->getMessage());
		}

		$this->assertSame([], $this->customSets->list());
		$this->assertSame([], $this->audited);
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
			$this->fail('A file the upload path refuses was installed.');
		} catch (GalleryException $e) {
			$this->assertSame(422, $e->getCode());
			$this->assertStringContainsString('The file was refused, so nothing was installed', $e->getMessage());
		}

		$this->assertSame([], $this->customSets->list());
	}//end testAFileTheUploadPathRefusesStoresNothing()

	/**
	 * An unknown gallery id is a 404 and downloads nothing.
	 *
	 * @return void
	 */
	public function testAnUnknownEntryIsNotFound(): void {
		try {
			$this->service()->install(galleryId: 'nope');
			$this->fail('An unknown entry was installed.');
		} catch (GalleryException $e) {
			$this->assertSame(404, $e->getCode());
		}

		$this->assertSame([], $this->downloads);
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

		$this->assertSame('custom-provincie-utrecht', $result['id']);
		$this->assertTrue($result['updated']);
		$this->assertSame('custom-provincie-utrecht', $this->appConfig['token_set']);
		$sets = $this->customSets->list();
		$this->assertCount(1, $sets);
		$this->assertSame(hash('sha256', $this->body), $sets[0]['provenance']['sha256']);
		$this->assertStringContainsStringIgnoringCase('#1d4a7a', (string)file_get_contents($this->appDir . '/css/tokens/custom-provincie-utrecht.css'));
	}//end testAnUpdateReplacesTheSetAndKeepsItActive()
}//end class
