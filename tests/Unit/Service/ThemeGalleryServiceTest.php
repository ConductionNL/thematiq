<?php

/**
 * Unit tests for ThemeGalleryService: the opt-in, bounded read of the theme gallery index.
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

use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\GalleryEntryValidator;
use OCA\Thematiq\Service\GalleryIndexCache;
use OCA\Thematiq\Service\GalleryIndexFetch;
use OCA\Thematiq\Service\ThemeGalleryService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IAppConfig;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for the gallery index read.
 */
class ThemeGalleryServiceTest extends TestCase {

	/**
	 * In-memory app config.
	 *
	 * @var array<string, string>
	 */
	private array $appConfig = [];

	/**
	 * Every GET the client received: [url, options].
	 *
	 * @var array<int, array{0: string, 1: array<string, mixed>}>
	 */
	private array $requests = [];

	/**
	 * What the next GET answers: [status, body, etag] or a Throwable.
	 *
	 * @var array{0: int, 1: string, 2: string}|\Throwable
	 */
	private $answer = [200, '', ''];

	/**
	 * The client service mock.
	 *
	 * @var IClientService&MockObject
	 */
	private IClientService $clientService;

	/**
	 * Installed custom sets as list() returns them.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $installed = [];

	/**
	 * The current time.
	 *
	 * @var integer
	 */
	private int $now = 1_800_000_000;

	/**
	 * Build a valid index entry.
	 *
	 * @param array<string, mixed> $overrides Fields to replace.
	 *
	 * @return array<string, mixed> The entry.
	 */
	public static function entry(array $overrides = []): array {
		return array_merge(
			[
				'id' => 'provincie-utrecht',
				'name' => 'Provincie Utrecht',
				'organisation' => 'Provincie Utrecht',
				'description' => 'The house style of the province.',
				'licence' => 'EUPL-1.2',
				'sourceUrl' => 'https://github.com/example/utrecht-tokens',
				'fileUrl' => 'https://gallery.example.nl/sets/provincie-utrecht.css',
				'sha256' => str_repeat('a', 64),
				'format' => 'css',
				'swatches' => ['primary' => '#cc0000', 'background' => '#ffffff', 'text' => '#1a1a1a'],
				'contrast' => ['pass' => 42, 'fail' => 0],
				'addedOn' => '2026-09-30',
			],
			$overrides
		);
	}//end entry()

	/**
	 * Set up the mocks.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willReturnCallback(
			function (string $url, array $options) {
				$this->requests[] = [$url, $options];
				if ($this->answer instanceof \Throwable) {
					throw $this->answer;
				}

				$response = $this->createMock(originalClassName: IResponse::class);
				$response->method('getStatusCode')->willReturn($this->answer[0]);
				$response->method('getBody')->willReturn($this->answer[1]);
				$response->method('getHeader')->willReturnCallback(fn (string $key) => (match ($key === 'ETag') {
					true => $this->answer[2], default => ''
				}));
				return $response;
			}
		);
		$this->clientService = $this->createMock(originalClassName: IClientService::class);
		$this->clientService->method('newClient')->willReturn($client);
	}//end setUp()

	/**
	 * Build the service under test.
	 *
	 * @return ThemeGalleryService The service.
	 */
	private function service(): ThemeGalleryService {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->appConfig[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->appConfig[$key] = (string)$value;
			}
		);

		$customSets = $this->createMock(originalClassName: CustomTokenSetService::class);
		$customSets->method('list')->willReturnCallback(fn () => $this->installed);

		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('getTime')->willReturnCallback(fn () => $this->now);

		$appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$appConfig->method('getValueString')->willReturnCallback(
			function (string $app, string $key, string $default = '', bool $lazy = false): string {
				$this->assertTrue(condition: $lazy, message: 'The cached index is a lazy value.');
				return ($this->appConfig[$key] ?? $default);
			}
		);
		$appConfig->method('setValueString')->willReturnCallback(
			function (string $app, string $key, string $value, bool $lazy = false): bool {
				$this->assertTrue(condition: $lazy, message: 'The cached index is a lazy value.');
				$this->appConfig[$key] = $value;
				return true;
			}
		);

		return new ThemeGalleryService(
			config: $config,
			fetching: new GalleryIndexFetch(
				clientService: $this->clientService,
				entries: new GalleryEntryValidator(),
				logger: $this->createMock(originalClassName: LoggerInterface::class)
			),
			customSets: $customSets,
			cache: new GalleryIndexCache(
				appConfig: $appConfig,
				time: $time
			)
		);
	}//end service()

	/**
	 * Answer the next GET with an index holding these entries.
	 *
	 * @param array<int, mixed> $entries The entries.
	 * @param string $etag The ETag to send.
	 *
	 * @return void
	 */
	private function serve(array $entries, string $etag = '"v1"'): void {
		$this->answer = [200, (string)json_encode(['entries' => $entries]), $etag];
	}//end serve()

	/**
	 * A fresh install is off and contacts nobody, and still names the host.
	 *
	 * @return void
	 */
	public function testAFreshInstallContactsNobody(): void {
		$this->clientService->expects($this->never())->method('newClient');

		$result = $this->service()->browse();

		$this->assertFalse(condition: $result['enabled']);
		$this->assertSame(expected: 'raw.githubusercontent.com', actual: $result['host']);
		$this->assertSame(expected: [], actual: $result['entries']);
		$this->assertSame(expected: [], actual: $this->requests);
	}//end testAFreshInstallContactsNobody()

	/**
	 * A mirror URL is read and named.
	 *
	 * @return void
	 */
	public function testAMirrorIsReadAndNamedWithATenSecondTimeout(): void {
		$this->appConfig['gallery_enabled'] = 'yes';
		$this->appConfig['gallery_index_url'] = 'https://intranet.example.nl/thematiq/index.json';
		$this->serve(entries: [self::entry()]);

		$result = $this->service()->browse();

		$this->assertSame(expected: 'intranet.example.nl', actual: $result['host']);
		$this->assertCount(expectedCount: 1, haystack: $this->requests);
		$this->assertSame(expected: 'https://intranet.example.nl/thematiq/index.json', actual: $this->requests[0][0]);
		$this->assertSame(expected: 10, actual: $this->requests[0][1]['timeout']);
		$this->assertArrayNotHasKey(key: 'cookies', array: $this->requests[0][1]);
		$this->assertTrue(condition: $result['reachable']);
		$this->assertSame(expected: 'Provincie Utrecht', actual: $result['entries'][0]['name']);
	}//end testAMirrorIsReadAndNamedWithATenSecondTimeout()

	/**
	 * An entry without a licence, or with a malformed field, is not listed.
	 *
	 * @return void
	 */
	public function testInvalidEntriesAreNotListed(): void {
		$this->appConfig['gallery_enabled'] = 'yes';
		$noLicence = self::entry(overrides: ['id' => 'no-licence']);
		unset($noLicence['licence']);
		$this->serve(
			entries: [
				self::entry(),
				$noLicence,
				self::entry(overrides: ['id' => 'bad-hash', 'sha256' => 'abc']),
				self::entry(overrides: ['id' => 'plain-http', 'fileUrl' => 'http://gallery.example.nl/x.css']),
				self::entry(overrides: ['id' => 'bad-colour', 'swatches' => ['primary' => 'red', 'background' => '#fff', 'text' => '#000']]),
				self::entry(overrides: ['id' => 'Bad Id']),
				'not an object',
			]
		);

		$ids = array_column($this->service()->browse()['entries'], 'id');

		$this->assertSame(expected: ['provincie-utrecht'], actual: $ids);
		$cached = json_decode($this->appConfig['gallery_index_cache'], true);
		$this->assertSame(expected: ['provincie-utrecht'], actual: array_column($cached['entries'], 'id'), message: 'Only valid entries are cached.');
	}//end testInvalidEntriesAreNotListed()

	/**
	 * An index larger than the cap is not read and not cached.
	 *
	 * @return void
	 */
	public function testAnOversizedIndexIsReportedAsUnreachable(): void {
		$this->appConfig['gallery_enabled'] = 'yes';
		$this->answer = [200, '{"entries":[]}' . str_repeat(' ', ThemeGalleryService::MAX_INDEX_BYTES), '"v1"'];

		$result = $this->service()->browse();

		$this->assertFalse(condition: $result['reachable']);
		$this->assertArrayNotHasKey(key: 'gallery_index_cache', array: $this->appConfig);
	}//end testAnOversizedIndexIsReportedAsUnreachable()

	/**
	 * An unreachable index is reported, not thrown.
	 *
	 * @return void
	 */
	public function testAnUnreachableIndexIsReported(): void {
		$this->appConfig['gallery_enabled'] = 'yes';
		$this->answer = new \RuntimeException('cURL error 28: timed out');

		$result = $this->service()->browse();

		$this->assertTrue(condition: $result['enabled']);
		$this->assertFalse(condition: $result['reachable']);
		$this->assertSame(expected: [], actual: $result['entries']);
	}//end testAnUnreachableIndexIsReported()

	/**
	 * A body that is not an index is treated as unreachable.
	 *
	 * @return void
	 */
	public function testAMalformedIndexIsReportedAsUnreachable(): void {
		$this->appConfig['gallery_enabled'] = 'yes';
		$this->answer = [200, '<html>captive portal</html>', ''];

		$this->assertFalse(condition: $this->service()->browse()['reachable']);
	}//end testAMalformedIndexIsReportedAsUnreachable()

	/**
	 * The index is read at most once an hour, then revalidated with its ETag.
	 *
	 * @return void
	 */
	public function testTheIndexIsCachedForAnHourThenRevalidated(): void {
		$this->appConfig['gallery_enabled'] = 'yes';
		$this->serve(entries: [self::entry()], etag: '"v1"');

		$this->service()->browse();
		$this->now += 600;
		$this->service()->browse();
		$this->assertCount(expectedCount: 1, haystack: $this->requests);

		$this->now += 3600;
		$this->answer = [304, '', ''];
		$result = $this->service()->browse();

		$this->assertCount(expectedCount: 2, haystack: $this->requests);
		$this->assertSame(expected: '"v1"', actual: $this->requests[1][1]['headers']['If-None-Match']);
		$this->assertSame(expected: 'Provincie Utrecht', actual: $result['entries'][0]['name']);
	}//end testTheIndexIsCachedForAnHourThenRevalidated()

	/**
	 * An installed set is marked, and a different checksum offers an update.
	 *
	 * @return void
	 */
	public function testAnInstalledSetWithANewChecksumOffersAnUpdate(): void {
		$this->appConfig['gallery_enabled'] = 'yes';
		$this->serve(entries: [self::entry(overrides: ['sha256' => str_repeat(
			'b',
			64
		)]), self::entry(overrides: ['id' => 'gemeente-epe', 'name' => 'Gemeente Epe'])]);
		$this->installed = [
			[
				'id' => 'custom-provincie-utrecht',
				'name' => 'Provincie Utrecht',
				'provenance' => ['galleryId' => 'provincie-utrecht', 'sha256' => str_repeat('a', 64)],
			],
		];

		$entries = $this->service()->browse()['entries'];

		$this->assertSame(expected: 'custom-provincie-utrecht', actual: $entries[0]['installed']);
		$this->assertTrue(condition: $entries[0]['updateAvailable']);
		$this->assertNull(actual: $entries[1]['installed']);
		$this->assertFalse(condition: $entries[1]['updateAvailable']);
	}//end testAnInstalledSetWithANewChecksumOffersAnUpdate()

	/**
	 * Finding an entry needs the gallery on.
	 *
	 * @return void
	 */
	public function testFindEntryReadsNothingWhileOff(): void {
		$this->serve(entries: [self::entry()]);

		$this->assertNull(actual: $this->service()->findEntry(galleryId: 'provincie-utrecht'));
		$this->assertSame(expected: [], actual: $this->requests);

		$this->appConfig['gallery_enabled'] = 'yes';
		$this->assertSame(expected: 'Provincie Utrecht', actual: $this->service()->findEntry(galleryId: 'provincie-utrecht')['name']);
	}//end testFindEntryReadsNothingWhileOff()

	/**
	 * Turning the gallery on and off is stored as yes and no.
	 *
	 * @return void
	 */
	public function testTheToggleIsStored(): void {
		$service = $this->service();
		$service->setEnabled(enabled: true);
		$this->assertSame(expected: 'yes', actual: $this->appConfig['gallery_enabled']);
		$service->setEnabled(enabled: false);
		$this->assertSame(expected: 'no', actual: $this->appConfig['gallery_enabled']);
	}//end testTheToggleIsStored()
}//end class
