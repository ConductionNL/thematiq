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

		$client = $this->createMock(IClient::class);
		$client->method('get')->willReturnCallback(
			function (string $url, array $options) {
				$this->requests[] = [$url, $options];
				if ($this->answer instanceof \Throwable) {
					throw $this->answer;
				}

				$response = $this->createMock(IResponse::class);
				$response->method('getStatusCode')->willReturn($this->answer[0]);
				$response->method('getBody')->willReturn($this->answer[1]);
				$response->method('getHeader')->willReturnCallback(fn (string $key) => ($key === 'ETag' ? $this->answer[2] : ''));
				return $response;
			}
		);
		$this->clientService = $this->createMock(IClientService::class);
		$this->clientService->method('newClient')->willReturn($client);
	}//end setUp()

	/**
	 * Build the service under test.
	 *
	 * @return ThemeGalleryService The service.
	 */
	private function service(): ThemeGalleryService {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->appConfig[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->appConfig[$key] = (string)$value;
			}
		);

		$customSets = $this->createMock(CustomTokenSetService::class);
		$customSets->method('list')->willReturnCallback(fn () => $this->installed);

		$time = $this->createMock(ITimeFactory::class);
		$time->method('getTime')->willReturnCallback(fn () => $this->now);

		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getValueString')->willReturnCallback(
			function (string $app, string $key, string $default = '', bool $lazy = false): string {
				$this->assertTrue($lazy, 'The cached index is a lazy value.');
				return ($this->appConfig[$key] ?? $default);
			}
		);
		$appConfig->method('setValueString')->willReturnCallback(
			function (string $app, string $key, string $value, bool $lazy = false): bool {
				$this->assertTrue($lazy, 'The cached index is a lazy value.');
				$this->appConfig[$key] = $value;
				return true;
			}
		);

		return new ThemeGalleryService(
			$config,
			$this->clientService,
			$customSets,
			new GalleryEntryValidator(),
			$time,
			$this->createMock(LoggerInterface::class),
			$appConfig
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

		$this->assertFalse($result['enabled']);
		$this->assertSame('raw.githubusercontent.com', $result['host']);
		$this->assertSame([], $result['entries']);
		$this->assertSame([], $this->requests);
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

		$this->assertSame('intranet.example.nl', $result['host']);
		$this->assertCount(1, $this->requests);
		$this->assertSame('https://intranet.example.nl/thematiq/index.json', $this->requests[0][0]);
		$this->assertSame(10, $this->requests[0][1]['timeout']);
		$this->assertArrayNotHasKey('cookies', $this->requests[0][1]);
		$this->assertTrue($result['reachable']);
		$this->assertSame('Provincie Utrecht', $result['entries'][0]['name']);
	}//end testAMirrorIsReadAndNamedWithATenSecondTimeout()

	/**
	 * An entry without a licence, or with a malformed field, is not listed.
	 *
	 * @return void
	 */
	public function testInvalidEntriesAreNotListed(): void {
		$this->appConfig['gallery_enabled'] = 'yes';
		$noLicence = self::entry(['id' => 'no-licence']);
		unset($noLicence['licence']);
		$this->serve(
			entries: [
				self::entry(),
				$noLicence,
				self::entry(['id' => 'bad-hash', 'sha256' => 'abc']),
				self::entry(['id' => 'plain-http', 'fileUrl' => 'http://gallery.example.nl/x.css']),
				self::entry(['id' => 'bad-colour', 'swatches' => ['primary' => 'red', 'background' => '#fff', 'text' => '#000']]),
				self::entry(['id' => 'Bad Id']),
				'not an object',
			]
		);

		$ids = array_column($this->service()->browse()['entries'], 'id');

		$this->assertSame(['provincie-utrecht'], $ids);
		$cached = json_decode($this->appConfig['gallery_index_cache'], true);
		$this->assertSame(['provincie-utrecht'], array_column($cached['entries'], 'id'), 'Only valid entries are cached.');
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

		$this->assertFalse($result['reachable']);
		$this->assertArrayNotHasKey('gallery_index_cache', $this->appConfig);
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

		$this->assertTrue($result['enabled']);
		$this->assertFalse($result['reachable']);
		$this->assertSame([], $result['entries']);
	}//end testAnUnreachableIndexIsReported()

	/**
	 * A body that is not an index is treated as unreachable.
	 *
	 * @return void
	 */
	public function testAMalformedIndexIsReportedAsUnreachable(): void {
		$this->appConfig['gallery_enabled'] = 'yes';
		$this->answer = [200, '<html>captive portal</html>', ''];

		$this->assertFalse($this->service()->browse()['reachable']);
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
		$this->assertCount(1, $this->requests);

		$this->now += 3600;
		$this->answer = [304, '', ''];
		$result = $this->service()->browse();

		$this->assertCount(2, $this->requests);
		$this->assertSame('"v1"', $this->requests[1][1]['headers']['If-None-Match']);
		$this->assertSame('Provincie Utrecht', $result['entries'][0]['name']);
	}//end testTheIndexIsCachedForAnHourThenRevalidated()

	/**
	 * An installed set is marked, and a different checksum offers an update.
	 *
	 * @return void
	 */
	public function testAnInstalledSetWithANewChecksumOffersAnUpdate(): void {
		$this->appConfig['gallery_enabled'] = 'yes';
		$this->serve(entries: [self::entry(['sha256' => str_repeat('b', 64)]), self::entry(['id' => 'gemeente-epe', 'name' => 'Gemeente Epe'])]);
		$this->installed = [
			[
				'id' => 'custom-provincie-utrecht',
				'name' => 'Provincie Utrecht',
				'provenance' => ['galleryId' => 'provincie-utrecht', 'sha256' => str_repeat('a', 64)],
			],
		];

		$entries = $this->service()->browse()['entries'];

		$this->assertSame('custom-provincie-utrecht', $entries[0]['installed']);
		$this->assertTrue($entries[0]['updateAvailable']);
		$this->assertNull($entries[1]['installed']);
		$this->assertFalse($entries[1]['updateAvailable']);
	}//end testAnInstalledSetWithANewChecksumOffersAnUpdate()

	/**
	 * Finding an entry needs the gallery on.
	 *
	 * @return void
	 */
	public function testFindEntryReadsNothingWhileOff(): void {
		$this->serve(entries: [self::entry()]);

		$this->assertNull($this->service()->findEntry(galleryId: 'provincie-utrecht'));
		$this->assertSame([], $this->requests);

		$this->appConfig['gallery_enabled'] = 'yes';
		$this->assertSame('Provincie Utrecht', $this->service()->findEntry(galleryId: 'provincie-utrecht')['name']);
	}//end testFindEntryReadsNothingWhileOff()

	/**
	 * Turning the gallery on and off is stored as yes and no.
	 *
	 * @return void
	 */
	public function testTheToggleIsStored(): void {
		$service = $this->service();
		$service->setEnabled(enabled: true);
		$this->assertSame('yes', $this->appConfig['gallery_enabled']);
		$service->setEnabled(enabled: false);
		$this->assertSame('no', $this->appConfig['gallery_enabled']);
	}//end testTheToggleIsStored()
}//end class
