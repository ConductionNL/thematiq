<?php

/**
 * Unit tests for ThemingAuditService.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/theming-audit-log/tasks.md#task-5.1
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ThemingAuditService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * In-memory fake of ISimpleFile: content lives in a mutable property so
 * putContent()/getContent() round-trip exactly like the real appdata file.
 */
class FakeAuditFile implements ISimpleFile {

	/**
	 * @param string $name The file name.
	 * @param string $content The initial content.
	 */
	public function __construct(
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
		return 'fake-etag';
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
	}

	public function getMimeType(): string {
		return 'application/x-ndjson';
	}

	public function getExtension(): string {
		return 'jsonl';
	}

	public function read() {
		return false;
	}

	public function write() {
		return false;
	}
}

/**
 * In-memory fake of ISimpleFolder: keeps a name => FakeAuditFile map so
 * fileExists()/getFile()/newFile() behave exactly like the real appdata
 * folder, without needing a Nextcloud server.
 */
class FakeAuditFolder implements ISimpleFolder {

	/**
	 * @var array<string, FakeAuditFile>
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
		$file = new FakeAuditFile($name, (string)$content);
		$this->files[$name] = $file;

		return $file;
	}

	public function delete(): void {
	}

	public function getName(): string {
		return 'audit';
	}

	public function getFolder(string $name): ISimpleFolder {
		throw new NotFoundException();
	}

	public function newFolder(string $path): ISimpleFolder {
		throw new \RuntimeException('not supported by fake');
	}

	// ISimpleFolder::getOrCreateFolder() is @since 35.0.0. This fake is flat —
	// the audit log lives in one folder — so it refuses the way newFolder()
	// does, rather than pretending to nest.
	public function getOrCreateFolder(string $path, int $maxRetries = 5): ISimpleFolder {
		throw new \RuntimeException('not supported by fake');
	}
}

/**
 * In-memory fake of IAppData: lazily creates the `audit` folder on
 * newFolder(), mirroring the real appdata root's behaviour.
 */
class FakeAuditRoot implements IAppData {

	public FakeAuditFolder $auditFolder;

	public bool $folderExists = false;

	public function __construct() {
		$this->auditFolder = new FakeAuditFolder();
	}

	public function getFolder(string $name): ISimpleFolder {
		if ($this->folderExists === false) {
			throw new NotFoundException();
		}

		return $this->auditFolder;
	}

	public function getDirectoryListing(): array {
		if ($this->folderExists === false) {
			return [];
		}

		return [$this->auditFolder];
	}

	public function newFolder(string $name): ISimpleFolder {
		$this->folderExists = true;

		return $this->auditFolder;
	}
}

/**
 * Fake IAppDataFactory returning a fixed FakeAuditRoot.
 */
class FakeAuditAppDataFactory implements IAppDataFactory {

	public function __construct(
		private IAppData $appData,
	) {
	}

	public function get(string $appId): IAppData {
		return $this->appData;
	}
}

/**
 * Fake IAppDataFactory whose get() always throws — exercises log()'s
 * failure-swallowing contract.
 */
class ThrowingAuditAppDataFactory implements IAppDataFactory {

	public function get(string $appId): IAppData {
		throw new \RuntimeException('appdata unavailable');
	}
}

/**
 * Unit tests for the append-only theming audit trail service.
 *
 * Covers tasks.md#task-5.1: entry shape, actor fallback (uid -> cli ->
 * system), unknown-action rejection, counter monotonicity, CSS payload
 * hashing, size-capped rotation, getRecent()/exportAll() ordering, and
 * write-failure swallowing.
 */
class ThemingAuditServiceTest extends TestCase {

	/**
	 * In-memory appconfig store: key => value.
	 *
	 * @var array<string, string>
	 */
	private array $appConfig = [];

	/**
	 * The fake appdata root backing the service under test.
	 *
	 * @var FakeAuditRoot
	 */
	private FakeAuditRoot $root;

	/**
	 * The mocked user session.
	 *
	 * @var IUserSession&\PHPUnit\Framework\MockObject\MockObject
	 */
	private IUserSession $userSession;

	/**
	 * The mocked logger.
	 *
	 * @var LoggerInterface&\PHPUnit\Framework\MockObject\MockObject
	 */
	private LoggerInterface $logger;

	protected function setUp(): void {
		parent::setUp();

		$this->root = new FakeAuditRoot();
		$this->userSession = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}//end setUp()

	/**
	 * Build a service instance wired to the fake appdata root and an
	 * in-memory IConfig, with a fixed clock and no active user (actor
	 * resolves to 'cli', since PHPUnit itself runs under PHP_SAPI==='cli').
	 *
	 * @param int $time The fixed clock value.
	 *
	 * @return ThemingAuditService The service under test.
	 */
	private function makeService(int $time = 1700000000): ThemingAuditService {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->appConfig[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->appConfig[$key] = $value;
			}
		);

		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getTime')->willReturn($time);

		$this->userSession->method('getUser')->willReturn(null);

		return new ThemingAuditService(
			appDataFactory: new FakeAuditAppDataFactory(appData: $this->root),
			config: $config,
			userSession: $this->userSession,
			timeFactory: $timeFactory,
			logger: $this->logger
		);
	}//end makeService()

	/**
	 * A logged entry contains ts/actor/action/old/new/tokenSetVersion.
	 */
	public function testEntryShape(): void {
		$service = $this->makeService(time: 1700000000);

		$service->log(
			action: 'token_set_changed',
			context: ['old' => 'rijkshuisstijl', 'new' => 'amsterdam']
		);

		$entries = $service->getRecent(limit: 10);
		$this->assertCount(1, $entries);

		$entry = $entries[0];
		$this->assertSame('2023-11-14T22:13:20Z', $entry['ts']);
		$this->assertSame('cli', $entry['actor']);
		$this->assertSame('token_set_changed', $entry['action']);
		$this->assertSame('rijkshuisstijl', $entry['old']);
		$this->assertSame('amsterdam', $entry['new']);
		$this->assertArrayHasKey('tokenSetVersion', $entry);
	}//end testEntryShape()

	/**
	 * Actor resolution: uid wins when a user is active.
	 */
	public function testActorResolvesUidWhenUserPresent(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('ruben');
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userSession->method('getUser')->willReturn($user);

		$service = $this->makeService();
		$service->log(action: 'token_set_changed', context: ['old' => 'a', 'new' => 'b']);

		$this->assertSame('ruben', $service->getRecent(limit: 1)[0]['actor']);
	}//end testActorResolvesUidWhenUserPresent()

	/**
	 * Actor resolution: no user, running under CLI -> 'cli'.
	 */
	public function testActorFallsBackToCli(): void {
		$service = $this->makeService();
		$service->log(action: 'token_set_changed', context: ['old' => 'a', 'new' => 'b']);

		$this->assertSame('cli', $service->getRecent(limit: 1)[0]['actor']);
	}//end testActorFallsBackToCli()

	/**
	 * Actor resolution: no user, not CLI -> 'system'.
	 */
	public function testActorFallsBackToSystem(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->appConfig[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->appConfig[$key] = $value;
			}
		);

		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getTime')->willReturn(1700000000);

		$this->userSession->method('getUser')->willReturn(null);

		$service = new class(new FakeAuditAppDataFactory(appData: $this->root), $config, $this->userSession, $timeFactory, $this->logger) extends ThemingAuditService {
			protected function isRunningInCli(): bool {
				return false;
			}
		};

		$service->log(action: 'token_set_changed', context: ['old' => 'a', 'new' => 'b']);

		$this->assertSame('system', $service->getRecent(limit: 1)[0]['actor']);
	}//end testActorFallsBackToSystem()

	/**
	 * An action outside the closed vocabulary is dropped with a warning.
	 */
	public function testUnknownActionIsDroppedWithWarning(): void {
		$this->logger->expects($this->once())->method('warning');

		$service = $this->makeService();
		$service->log(action: 'not_a_real_action', context: ['old' => 'a', 'new' => 'b']);

		$this->assertSame([], $service->getRecent(limit: 10));
		$this->assertSame('0', ($this->appConfig['audit_entries_total'] ?? '0'));
	}//end testUnknownActionIsDroppedWithWarning()

	/**
	 * The counter app value increments by exactly one per successful entry.
	 */
	public function testCounterIncrementsPerEntry(): void {
		$service = $this->makeService();

		$service->log(action: 'token_set_changed', context: ['old' => 'a', 'new' => 'b']);
		$this->assertSame('1', $this->appConfig['audit_entries_total']);

		$service->log(action: 'toggle_changed', context: ['key' => 'hide_slogan', 'old' => false, 'new' => true]);
		$this->assertSame('2', $this->appConfig['audit_entries_total']);
	}//end testCounterIncrementsPerEntry()

	/**
	 * A CSS payload is summarized as a sha256 hash + byte size, never
	 * embedded verbatim.
	 */
	public function testCssPayloadIsHashedNotEmbedded(): void {
		$css = str_repeat('--nldesign-color-primary: #007bc7;', 200);
		$service = $this->makeService();

		$service->log(
			action: 'overrides_imported',
			context: [
				'imported' => 5,
				'skipped' => 1,
				'new' => $css,
				'newIsCss' => true,
			]
		);

		$entry = $service->getRecent(limit: 1)[0];
		$this->assertIsArray($entry['new']);
		$this->assertStringStartsWith('sha256:', $entry['new']['hash']);
		$this->assertSame(12, strlen(substr($entry['new']['hash'], 7)));
		$this->assertSame(strlen($css), $entry['new']['bytes']);
		$this->assertStringNotContainsString('--nldesign-color-primary', json_encode($entry));
	}//end testCssPayloadIsHashedNotEmbedded()

	/**
	 * Array old/new values are summarized as counts, with a 'changed' list
	 * of the identities that differ.
	 */
	public function testArrayValuesSummarizedWithChangedKeys(): void {
		$service = $this->makeService();

		$service->log(
			action: 'app_exclusions_changed',
			context: [
				'old' => ['calendar', 'mail'],
				'new' => ['calendar', 'contacts'],
			]
		);

		$entry = $service->getRecent(limit: 1)[0];
		$this->assertSame(['count' => 2], $entry['old']);
		$this->assertSame(['count' => 2], $entry['new']);
		sort($entry['changed']);
		$this->assertSame(['contacts', 'mail'], $entry['changed']);
	}//end testArrayValuesSummarizedWithChangedKeys()

	/**
	 * Rotation at the 1 MB size cap keeps exactly one generation, and the
	 * overflowing entry lands in the rotated file.
	 */
	public function testRotationAtSizeCapKeepsOneGeneration(): void {
		$service = $this->makeService();

		// Pad the current file close to the 1 MB cap directly (bypassing
		// log() for setup speed), then append one more entry that must
		// trigger rotation.
		$padding = str_repeat('a', 1048500) . "\n";
		$this->root->auditFolder->newFile('audit.jsonl', $padding);

		$service->log(action: 'token_set_changed', context: ['old' => 'a', 'new' => 'b']);

		$this->assertTrue($this->root->auditFolder->fileExists('audit.jsonl.1'));
		$this->assertStringContainsString('token_set_changed', $this->root->auditFolder->getFile('audit.jsonl.1')->getContent());
		$this->assertSame('', $this->root->auditFolder->getFile('audit.jsonl')->getContent());

		// A second small entry must land in the FRESH current file, not the
		// rotated generation, and rotation must not create a second
		// generation file.
		$service->log(action: 'token_set_changed', context: ['old' => 'b', 'new' => 'c']);
		$this->assertStringContainsString('"old":"b"', $this->root->auditFolder->getFile('audit.jsonl')->getContent());
	}//end testRotationAtSizeCapKeepsOneGeneration()

	/**
	 * getRecent() returns newest-first and respects the limit.
	 */
	public function testGetRecentOrderAndLimit(): void {
		$service = $this->makeService();

		$service->log(action: 'token_set_changed', context: ['old' => '1', 'new' => '2']);
		$service->log(action: 'token_set_changed', context: ['old' => '2', 'new' => '3']);
		$service->log(action: 'token_set_changed', context: ['old' => '3', 'new' => '4']);

		$recent = $service->getRecent(limit: 2);
		$this->assertCount(2, $recent);
		$this->assertSame('4', $recent[0]['new']);
		$this->assertSame('3', $recent[1]['new']);
	}//end testGetRecentOrderAndLimit()

	/**
	 * exportAll() returns the rotated generation before the current file,
	 * oldest first overall.
	 */
	public function testExportAllIncludesRotatedGenerationFirst(): void {
		$service = $this->makeService();

		$padding = str_repeat('a', 1048500) . "\n";
		$this->root->auditFolder->newFile('audit.jsonl', $padding);

		// Triggers rotation: the padded content + this entry become
		// audit.jsonl.1, and audit.jsonl starts fresh.
		$service->log(action: 'token_set_changed', context: ['old' => 'rotated-gen', 'new' => 'x']);
		// Lands in the fresh current file.
		$service->log(action: 'token_set_changed', context: ['old' => 'current-gen', 'new' => 'y']);

		$exported = $service->exportAll();
		$rotatedPos = strpos($exported, 'rotated-gen');
		$currentPos = strpos($exported, 'current-gen');

		$this->assertIsInt($rotatedPos);
		$this->assertIsInt($currentPos);
		$this->assertLessThan($currentPos, $rotatedPos);
	}//end testExportAllIncludesRotatedGenerationFirst()

	/**
	 * A Throwable from the appdata layer is caught, logged, and swallowed —
	 * log() never propagates it to the caller.
	 */
	public function testAppdataFailureIsSwallowedAndWarned(): void {
		$this->logger->expects($this->once())->method('warning');

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->appConfig[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->appConfig[$key] = $value;
			}
		);

		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getTime')->willReturn(1700000000);
		$this->userSession->method('getUser')->willReturn(null);

		$service = new ThemingAuditService(
			appDataFactory: new ThrowingAuditAppDataFactory(),
			config: $config,
			userSession: $this->userSession,
			timeFactory: $timeFactory,
			logger: $this->logger
		);

		// Must not throw.
		$service->log(action: 'token_set_changed', context: ['old' => 'a', 'new' => 'b']);

		// The counter must NOT have been incremented — the append failed.
		$this->assertSame('0', ($this->appConfig['audit_entries_total'] ?? '0'));
	}//end testAppdataFailureIsSwallowedAndWarned()
}//end class
