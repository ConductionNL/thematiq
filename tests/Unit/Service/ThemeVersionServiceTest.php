<?php

/**
 * Unit tests for ThemeVersionService.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/theme-versions/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ConfigBundleService;
use OCA\Thematiq\Service\ThemeVersionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * In-memory app data file whose delete() really removes it from its folder.
 */
class FakeVersionFile implements ISimpleFile {

	/**
	 * Constructor.
	 *
	 * @param FakeVersionFolder $folder The owning folder.
	 * @param string $name The file name.
	 * @param string $content The content.
	 */
	public function __construct(
		private FakeVersionFolder $folder,
		private string $name,
		private string $content,
	) {
	}

	public function getName(): string {
		return $this->name;
	}

	public function getSize(): int|float {
		return strlen($this->content);
	}

	public function getETag(): string {
		return 'etag';
	}

	public function getMTime(): int {
		return 0;
	}

	public function getContent(): string {
		return $this->content;
	}

	public function putContent($data): void {
		$this->content = (string)$data;
	}

	public function delete(): void {
		unset($this->folder->files[$this->name]);
	}

	public function getMimeType(): string {
		return 'application/json';
	}

	public function getExtension(): string {
		return 'json';
	}

	public function read() {
		return false;
	}

	public function write() {
		return false;
	}
}

/**
 * In-memory flat app data folder.
 */
class FakeVersionFolder implements ISimpleFolder {

	/**
	 * Files by name.
	 *
	 * @var array<string, FakeVersionFile>
	 */
	public array $files = [];

	public function getDirectoryListing(): array {
		return array_values($this->files);
	}

	public function fileExists(string $name): bool {
		return isset($this->files[$name]);
	}

	public function getFile(string $name): ISimpleFile {
		if (isset($this->files[$name]) === false) {
			throw new NotFoundException();
		}

		return $this->files[$name];
	}

	public function newFile(string $name, $content = null): ISimpleFile {
		$this->files[$name] = new FakeVersionFile(folder: $this, name: $name, content: (string)$content);
		return $this->files[$name];
	}

	public function delete(): void {
	}

	public function getName(): string {
		return 'versions';
	}

	public function getFolder(string $name): ISimpleFolder {
		throw new NotFoundException();
	}

	public function newFolder(string $path): ISimpleFolder {
		throw new RuntimeException('flat fake');
	}

	public function getOrCreateFolder(string $path, int $maxRetries = 5): ISimpleFolder {
		throw new RuntimeException('flat fake');
	}
}

/**
 * App data root holding one `versions` folder, created on first use.
 */
class FakeVersionRoot implements IAppData {

	public FakeVersionFolder $folder;

	public bool $exists = false;

	public function __construct() {
		$this->folder = new FakeVersionFolder();
	}

	public function getFolder(string $name): ISimpleFolder {
		if ($this->exists === false || $name !== 'versions') {
			throw new NotFoundException();
		}

		return $this->folder;
	}

	public function getDirectoryListing(): array {
		return match ($this->exists === true) {
			true => [$this->folder], default => []
		};
	}

	public function newFolder(string $name): ISimpleFolder {
		$this->exists = true;
		return $this->folder;
	}
}

/**
 * Keeping, listing, reading and capping versions.
 */
class ThemeVersionServiceTest extends TestCase {

	/**
	 * The fake app data root.
	 *
	 * @var FakeVersionRoot
	 */
	private FakeVersionRoot $root;

	/**
	 * The clock value.
	 *
	 * @var int
	 */
	private int $time = 1790700000;

	/**
	 * The bundle export() returns.
	 *
	 * @var array<string, mixed>
	 */
	private array $bundle = ['format' => 'nldesign-config-bundle', 'config' => ['tokenSet' => 'amsterdam']];

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->root = new FakeVersionRoot();
	}//end setUp()

	/**
	 * Build the service.
	 *
	 * @param LoggerInterface|null $logger The logger, or a silent mock.
	 * @param IAppDataFactory|null $appFactory The app data factory, or the fake.
	 *
	 * @return ThemeVersionService The service under test.
	 */
	private function service(?LoggerInterface $logger = null, ?IAppDataFactory $appFactory = null): ThemeVersionService {
		$bundleService = $this->createMock(originalClassName: ConfigBundleService::class);
		$bundleService->method('export')->willReturnCallback(fn (): array => $this->bundle);

		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('getTime')->willReturnCallback(fn (): int => $this->time);

		if ($appFactory === null) {
			$appFactory = $this->createMock(originalClassName: IAppDataFactory::class);
			$appFactory->method('get')->willReturn($this->root);
		}

		return new ThemeVersionService(
			appDataFactory: $appFactory,
			bundleService: $bundleService,
			timeFactory: $time,
			logger: ($logger ?? $this->createMock(originalClassName: LoggerInterface::class))
		);
	}//end service()

	/**
	 * A capture stores the bundle and lists it with its metadata.
	 *
	 * @return void
	 */
	public function testCaptureStoresTheBundleAndListsIt(): void {
		$service = $this->service();

		$id = $service->capture(auditAction: 'token_set_changed', actor: 'admin');

		$this->assertNotNull(actual: $id);
		$this->assertMatchesRegularExpression(pattern: '/^\d{14}-\d{4}$/', string: $id);
		$this->assertSame(expected: 'amsterdam', actual: $service->get(id: $id)['bundle']['config']['tokenSet']);
		$this->assertSame(
			expected: [['id' => $id, 'ts' => '2026-09-29T16:40:00Z', 'actor' => 'admin', 'action' => 'token_set_changed']],
			actual: $service->list()
		);
	}//end testCaptureStoresTheBundleAndListsIt()

	/**
	 * Versions list newest first, also within one second.
	 *
	 * @return void
	 */
	public function testListIsNewestFirst(): void {
		$service = $this->service();
		$first = $service->capture(auditAction: 'toggle_changed', actor: 'admin');
		$second = $service->capture(auditAction: 'overrides_written', actor: 'admin');
		$this->time += 60;
		$third = $service->capture(auditAction: 'config_imported', actor: 'cli');

		$this->assertSame(expected: [$third, $second, $first], actual: array_column($service->list(), 'id'));
	}//end testListIsNewestFirst()

	/**
	 * At most 50 versions are kept, oldest removed first.
	 *
	 * @return void
	 */
	public function testTheCountCapRemovesTheOldest(): void {
		$service = $this->service();
		$ids = [];
		for ($i = 0; $i < 52; $i++) {
			$this->time++;
			$ids[] = $service->capture(auditAction: 'toggle_changed', actor: 'admin');
		}

		$kept = array_column($service->list(), 'id');
		$this->assertCount(expectedCount: 50, haystack: $kept);
		$this->assertNotContains(needle: $ids[0], haystack: $kept);
		$this->assertNotContains(needle: $ids[1], haystack: $kept);
		$this->assertContains(needle: $ids[51], haystack: $kept);
	}//end testTheCountCapRemovesTheOldest()

	/**
	 * At most 20 MB of versions are kept, oldest removed first.
	 *
	 * @return void
	 */
	public function testTheSizeCapRemovesTheOldest(): void {
		$this->bundle['pad'] = str_repeat('x', (6 * 1024 * 1024));
		$service = $this->service();
		$ids = [];
		for ($i = 0; $i < 4; $i++) {
			$this->time++;
			$ids[] = $service->capture(auditAction: 'overrides_written', actor: 'admin');
		}

		$kept = array_column($service->list(), 'id');
		$this->assertSame(expected: [$ids[3], $ids[2], $ids[1]], actual: $kept);
	}//end testTheSizeCapRemovesTheOldest()

	/**
	 * A failing app data folder is logged and never thrown.
	 *
	 * @return void
	 */
	public function testAFailingAppDataFolderReturnsNullAndWarns(): void {
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('warning')->with($this->stringContains(string: 'no version was kept'));
		$factory = $this->createMock(originalClassName: IAppDataFactory::class);
		$factory->method('get')->willThrowException(new RuntimeException('read-only'));

		$this->assertNull(actual: $this->service(logger: $logger, appFactory: $factory)->capture(auditAction: 'toggle_changed', actor: 'admin'));
	}//end testAFailingAppDataFolderReturnsNullAndWarns()

	/**
	 * An unknown or malformed id reads as null, never as a path.
	 *
	 * @return void
	 */
	public function testUnknownAndMalformedIdsReadAsNull(): void {
		$service = $this->service();
		$service->capture(auditAction: 'toggle_changed', actor: 'admin');

		$this->assertNull(actual: $service->get(id: '20260101000000-0001'));
		$this->assertNull(actual: $service->get(id: '../audit/audit.jsonl'));
	}//end testUnknownAndMalformedIdsReadAsNull()
}//end class
