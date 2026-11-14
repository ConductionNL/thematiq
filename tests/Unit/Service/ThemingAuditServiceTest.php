<?php

/**
 * Unit tests for ThemingAuditService.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/archive/2026-07-23-theming-audit-log/tasks.md#task-5.1
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ThemeVersionService;
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

	/**
	 * ISimpleFile::getName(), as this test double answers it.
	 *
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * ISimpleFile::getSize(), as this test double answers it.
	 *
	 * @return int|float
	 */
	public function getSize(): int|float {
		return strlen($this->content);
	}

	/**
	 * ISimpleFile::getETag(), as this test double answers it.
	 *
	 * @return string
	 */
	public function getETag(): string {
		return 'fake-etag';
	}

	/**
	 * ISimpleFile::getMTime(), as this test double answers it.
	 *
	 * @return int
	 */
	public function getMTime(): int {
		return 0;
	}

	/**
	 * ISimpleFile::getContent(), as this test double answers it.
	 *
	 * @return string
	 */
	public function getContent(): string {
		return $this->content;
	}

	/**
	 * ISimpleFile::putContent(), as this test double answers it.
	 *
	 * @param mixed $data The data.
	 * @return void
	 */
	public function putContent($data): void {
		$this->content = (string)$data;
	}

	/**
	 * ISimpleFile::delete(), as this test double answers it.
	 *
	 * @return void
	 */
	public function delete(): void {
	}

	/**
	 * ISimpleFile::getMimeType(), as this test double answers it.
	 *
	 * @return string
	 */
	public function getMimeType(): string {
		return 'application/x-ndjson';
	}

	/**
	 * ISimpleFile::getExtension(), as this test double answers it.
	 *
	 * @return string
	 */
	public function getExtension(): string {
		return 'jsonl';
	}

	/**
	 * ISimpleFile::read(), as this test double answers it.
	 *
	 * @return mixed
	 */
	public function read() {
		return false;
	}

	/**
	 * ISimpleFile::write(), as this test double answers it.
	 *
	 * @return mixed
	 */
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

	/**
	 * ISimpleFolder::getDirectoryListing(), as this test double answers it.
	 *
	 * @return array
	 */
	public function getDirectoryListing(): array {
		return array_values($this->files);
	}

	/**
	 * ISimpleFolder::fileExists(), as this test double answers it.
	 *
	 * @param string $name The name.
	 * @return bool
	 */
	public function fileExists(string $name): bool {
		return isset($this->files[$name]);
	}

	/**
	 * ISimpleFolder::getFile(), as this test double answers it.
	 *
	 * @param string $name The name.
	 * @return ISimpleFile
	 */
	public function getFile(string $name): ISimpleFile {
		if (isset($this->files[$name]) === false) {
			throw new NotFoundException();
		}

		return $this->files[$name];
	}

	/**
	 * ISimpleFolder::newFile(), as this test double answers it.
	 *
	 * @param string $name The name.
	 * @param mixed $content The content.
	 * @return ISimpleFile
	 */
	public function newFile(string $name, $content = null): ISimpleFile {
		$file = new FakeAuditFile($name, (string)$content);
		$this->files[$name] = $file;

		return $file;
	}

	/**
	 * ISimpleFolder::delete(), as this test double answers it.
	 *
	 * @return void
	 */
	public function delete(): void {
	}

	/**
	 * ISimpleFolder::getName(), as this test double answers it.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'audit';
	}

	/**
	 * ISimpleFolder::getFolder(), as this test double answers it.
	 *
	 * @param string $name The name.
	 * @return ISimpleFolder
	 */
	public function getFolder(string $name): ISimpleFolder {
		throw new NotFoundException();
	}

	/**
	 * ISimpleFolder::newFolder(), as this test double answers it.
	 *
	 * @param string $path The path.
	 * @return ISimpleFolder
	 */
	public function newFolder(string $path): ISimpleFolder {
		throw new \RuntimeException('not supported by fake');
	}

	// ISimpleFolder::getOrCreateFolder() is @since 35.0.0. This fake is flat —
	// the audit log lives in one folder — so it refuses the way newFolder()
	// does, rather than pretending to nest.
	/**
	 * ISimpleFolder::getOrCreateFolder(), as this test double answers it.
	 *
	 * @param string $path The path.
	 * @param int $maxRetries The max retries.
	 * @return ISimpleFolder
	 */
	public function getOrCreateFolder(string $path, int $maxRetries = 5): ISimpleFolder {
		throw new \RuntimeException('not supported by fake');
	}
}

/**
 * In-memory fake of IAppData: lazily creates the `audit` folder on
 * newFolder(), mirroring the real appdata root's behaviour.
 */
class FakeAuditRoot implements IAppData {

	/**
	 * The audit folder.
	 *
	 * @var FakeAuditFolder
	 */
	public FakeAuditFolder $auditFolder;

	/**
	 * The folder exists.
	 *
	 * @var bool
	 */
	public bool $folderExists = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->auditFolder = new FakeAuditFolder();
	}

	/**
	 * IAppData::getFolder(), as this test double answers it.
	 *
	 * @param string $name The name.
	 * @return ISimpleFolder
	 */
	public function getFolder(string $name): ISimpleFolder {
		if ($this->folderExists === false) {
			throw new NotFoundException();
		}

		return $this->auditFolder;
	}

	/**
	 * IAppData::getDirectoryListing(), as this test double answers it.
	 *
	 * @return array
	 */
	public function getDirectoryListing(): array {
		if ($this->folderExists === false) {
			return [];
		}

		return [$this->auditFolder];
	}

	/**
	 * IAppData::newFolder(), as this test double answers it.
	 *
	 * @param string $name The name.
	 * @return ISimpleFolder
	 */
	public function newFolder(string $name): ISimpleFolder {
		$this->folderExists = true;

		return $this->auditFolder;
	}
}

/**
 * Fake IAppDataFactory returning a fixed FakeAuditRoot.
 */
class FakeAuditAppDataFactory implements IAppDataFactory {

	/**
	 * Constructor.
	 *
	 * @param IAppData $appData The app data.
	 */
	public function __construct(
		private IAppData $appData,
	) {
	}

	/**
	 * IAppDataFactory::get(), as this test double answers it.
	 *
	 * @param string $appId The app id.
	 * @return IAppData
	 */
	public function get(string $appId): IAppData {
		return $this->appData;
	}
}

/**
 * Fake IAppDataFactory whose get() always throws — exercises log()'s
 * failure-swallowing contract.
 */
class ThrowingAuditAppDataFactory implements IAppDataFactory {

	/**
	 * IAppDataFactory::get(), as this test double answers it.
	 *
	 * @param string $appId The app id.
	 * @return IAppData
	 */
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

	/**
	 * Set up each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->root = new FakeAuditRoot();
		$this->userSession = $this->createMock(originalClassName: IUserSession::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);
	}//end setUp()

	/**
	 * Build a service instance wired to the fake appdata root and an
	 * in-memory IConfig, with a fixed clock and no active user (actor
	 * resolves to 'cli', since PHPUnit itself runs under PHP_SAPI==='cli').
	 *
	 * @param int $time The fixed clock value.
	 * @param ?ThemeVersionService $versions The versions.
	 *
	 * @return ThemingAuditService The service under test.
	 */
	private function makeService(int $time = 1700000000, ?ThemeVersionService $versions = null): ThemingAuditService {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->appConfig[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->appConfig[$key] = $value;
			}
		);

		$timeFactory = $this->createMock(originalClassName: ITimeFactory::class);
		$timeFactory->method('getTime')->willReturn($time);

		$this->userSession->method('getUser')->willReturn(null);

		return new ThemingAuditService(
			appDataFactory: new FakeAuditAppDataFactory(appData: $this->root),
			config: $config,
			userSession: $this->userSession,
			timeFactory: $timeFactory,
			logger: $this->logger,
			versionService: ($versions ?? $this->createMock(originalClassName: ThemeVersionService::class))
		);
	}//end makeService()

	/**
	 * A logged entry contains ts/actor/action/old/new/tokenSetVersion.
	 *
	 * @return void
	 */
	public function testEntryShape(): void {
		$service = $this->makeService(time: 1700000000);

		$service->log(
			action: 'token_set_changed',
			context: ['old' => 'rijkshuisstijl', 'new' => 'amsterdam']
		);

		$entries = $service->getRecent(limit: 10);
		$this->assertCount(expectedCount: 1, haystack: $entries);

		$entry = $entries[0];
		$this->assertSame(expected: '2023-11-14T22:13:20Z', actual: $entry['ts']);
		$this->assertSame(expected: 'cli', actual: $entry['actor']);
		$this->assertSame(expected: 'token_set_changed', actual: $entry['action']);
		$this->assertSame(expected: 'rijkshuisstijl', actual: $entry['old']);
		$this->assertSame(expected: 'amsterdam', actual: $entry['new']);
		$this->assertArrayHasKey(key: 'tokenSetVersion', array: $entry);
	}//end testEntryShape()

	/**
	 * Actor resolution: uid wins when a user is active.
	 *
	 * @return void
	 */
	public function testActorResolvesUidWhenUserPresent(): void {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('ruben');
		$this->userSession = $this->createMock(originalClassName: IUserSession::class);
		$this->userSession->method('getUser')->willReturn($user);

		$service = $this->makeService();
		$service->log(action: 'token_set_changed', context: ['old' => 'a', 'new' => 'b']);

		$this->assertSame(expected: 'ruben', actual: $service->getRecent(limit: 1)[0]['actor']);
	}//end testActorResolvesUidWhenUserPresent()

	/**
	 * Actor resolution: no user, running under CLI -> 'cli'.
	 *
	 * @return void
	 */
	public function testActorFallsBackToCli(): void {
		$service = $this->makeService();
		$service->log(action: 'token_set_changed', context: ['old' => 'a', 'new' => 'b']);

		$this->assertSame(expected: 'cli', actual: $service->getRecent(limit: 1)[0]['actor']);
	}//end testActorFallsBackToCli()

	/**
	 * Actor resolution: no user, not CLI -> 'system'.
	 *
	 * @return void
	 */
	public function testActorFallsBackToSystem(): void {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->appConfig[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->appConfig[$key] = $value;
			}
		);

		$timeFactory = $this->createMock(originalClassName: ITimeFactory::class);
		$timeFactory->method('getTime')->willReturn(1700000000);

		$this->userSession->method('getUser')->willReturn(null);

		$service = new class(new FakeAuditAppDataFactory(appData: $this->root), $config, $this->userSession, $timeFactory, $this->logger, $this->createMock(originalClassName: ThemeVersionService::class)) extends ThemingAuditService {
			/**
			 * ThemingAuditService::isRunningInCli(), as this test double answers it.
			 *
			 * @return bool
			 */
			protected function isRunningInCli(): bool {
				return false;
			}
		};

		$service->log(action: 'token_set_changed', context: ['old' => 'a', 'new' => 'b']);

		$this->assertSame(expected: 'system', actual: $service->getRecent(limit: 1)[0]['actor']);
	}//end testActorFallsBackToSystem()

	/**
	 * An action outside the closed vocabulary is dropped with a warning.
	 *
	 * @return void
	 */
	public function testUnknownActionIsDroppedWithWarning(): void {
		$this->logger->expects($this->once())->method('warning');

		$service = $this->makeService();
		$service->log(action: 'not_a_real_action', context: ['old' => 'a', 'new' => 'b']);

		$this->assertSame(expected: [], actual: $service->getRecent(limit: 10));
		$this->assertSame(expected: '0', actual: ($this->appConfig['audit_entries_total'] ?? '0'));
	}//end testUnknownActionIsDroppedWithWarning()

	/**
	 * The counter app value increments by exactly one per successful entry.
	 *
	 * @return void
	 */
	public function testCounterIncrementsPerEntry(): void {
		$service = $this->makeService();

		$service->log(action: 'token_set_changed', context: ['old' => 'a', 'new' => 'b']);
		$this->assertSame(expected: '1', actual: $this->appConfig['audit_entries_total']);

		$service->log(action: 'toggle_changed', context: ['key' => 'hide_slogan', 'old' => false, 'new' => true]);
		$this->assertSame(expected: '2', actual: $this->appConfig['audit_entries_total']);
	}//end testCounterIncrementsPerEntry()

	/**
	 * A CSS payload is summarized as a sha256 hash + byte size, never
	 * embedded verbatim.
	 *
	 * @return void
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
		$this->assertIsArray(actual: $entry['new']);
		$this->assertStringStartsWith(prefix: 'sha256:', string: $entry['new']['hash']);
		$this->assertSame(expected: 12, actual: strlen(substr($entry['new']['hash'], 7)));
		$this->assertSame(expected: strlen($css), actual: $entry['new']['bytes']);
		$this->assertStringNotContainsString(needle: '--nldesign-color-primary', haystack: json_encode($entry));
	}//end testCssPayloadIsHashedNotEmbedded()

	/**
	 * Array old/new values are summarized as counts, with a 'changed' list
	 * of the identities that differ.
	 *
	 * @return void
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
		$this->assertSame(expected: ['count' => 2], actual: $entry['old']);
		$this->assertSame(expected: ['count' => 2], actual: $entry['new']);
		sort($entry['changed']);
		$this->assertSame(expected: ['contacts', 'mail'], actual: $entry['changed']);
	}//end testArrayValuesSummarizedWithChangedKeys()

	/**
	 * Issue #943: POST /settings/group-theming logs the mapping as a list of
	 * `{group, tokenSet}` objects. That list was run through strval(), which
	 * raised "Array to string conversion" and audited `"changed":["Array"]`.
	 * The changed GROUPS are recorded: added, removed, re-pointed, delegated,
	 * or moved in priority.
	 *
	 * @return void
	 */
	public function testGroupThemingMappingRecordsTheChangedGroups(): void {
		$service = $this->makeService();
		$warnings = [];
		set_error_handler(
			static function (int $severity, string $message) use (&$warnings): bool {
				$warnings[] = $message;

				return true;
			},
			E_WARNING | E_NOTICE
		);

		try {
			// The shape GroupThemingService::getMapping() and setMapping() return.
			$service->log(
				action: 'group_theming_changed',
				context: [
					'old' => [
						['group' => 'admin', 'tokenSet' => 'rijkshuisstijl'],
						['group' => 'staff', 'tokenSet' => 'utrecht'],
						['group' => 'guests', 'tokenSet' => 'nextcloud'],
					],
					'new' => [
						['group' => 'admin', 'tokenSet' => 'rijkshuisstijl'],
						['group' => 'staff', 'tokenSet' => 'amsterdam'],
						['group' => 'students', 'tokenSet' => 'utrecht', 'delegated' => true, 'allowedTokenSets' => ['utrecht', 'amsterdam']],
					],
				]
			);
		} finally {
			restore_error_handler();
		}

		$this->assertSame(expected: [], actual: $warnings);
		$entry = $service->getRecent(limit: 1)[0];
		$this->assertSame(expected: ['count' => 3], actual: $entry['old']);
		$this->assertSame(expected: ['count' => 3], actual: $entry['new']);
		sort($entry['changed']);
		$this->assertSame(expected: ['guests', 'staff', 'students'], actual: $entry['changed']);
	}//end testGroupThemingMappingRecordsTheChangedGroups()

	/**
	 * Reordering the mapping changes which theme wins, so both moved groups are recorded;
	 * an unchanged mapping records none.
	 *
	 * @return void
	 */
	public function testGroupThemingReorderAndNoChange(): void {
		$service = $this->makeService();
		$admin = ['group' => 'admin', 'tokenSet' => 'rijkshuisstijl'];
		$staff = ['group' => 'staff', 'tokenSet' => 'utrecht'];

		$service->log(action: 'group_theming_changed', context: ['old' => [$admin, $staff], 'new' => [$staff, $admin]]);
		$moved = $service->getRecent(limit: 1)[0]['changed'];
		sort($moved);
		$this->assertSame(expected: ['admin', 'staff'], actual: $moved);

		$service->log(action: 'group_theming_changed', context: ['old' => [$admin, $staff], 'new' => [$admin, $staff]]);
		$this->assertSame(expected: [], actual: $service->getRecent(limit: 1)[0]['changed']);
	}//end testGroupThemingReorderAndNoChange()

	/**
	 * Rotation at the 1 MB size cap keeps exactly one generation, and the
	 * overflowing entry lands in the rotated file.
	 *
	 * @return void
	 */
	public function testRotationAtSizeCapKeepsOneGeneration(): void {
		$service = $this->makeService();

		// Pad the current file close to the 1 MB cap directly (bypassing
		// log() for setup speed), then append one more entry that must
		// trigger rotation.
		$padding = str_repeat('a', 1048500) . "\n";
		$this->root->auditFolder->newFile('audit.jsonl', $padding);

		$service->log(action: 'token_set_changed', context: ['old' => 'a', 'new' => 'b']);

		$this->assertTrue(condition: $this->root->auditFolder->fileExists('audit.jsonl.1'));
		$this->assertStringContainsString(needle: 'token_set_changed', haystack: $this->root->auditFolder->getFile('audit.jsonl.1')->getContent());
		$this->assertSame(expected: '', actual: $this->root->auditFolder->getFile('audit.jsonl')->getContent());

		// A second small entry must land in the FRESH current file, not the
		// rotated generation, and rotation must not create a second
		// generation file.
		$service->log(action: 'token_set_changed', context: ['old' => 'b', 'new' => 'c']);
		$this->assertStringContainsString(needle: '"old":"b"', haystack: $this->root->auditFolder->getFile('audit.jsonl')->getContent());
	}//end testRotationAtSizeCapKeepsOneGeneration()

	/**
	 * The lifetime counter keeps counting across a rotation, even though the
	 * current audit.jsonl is empty right after it.
	 *
	 * Proves openspec/specs/prometheus-metrics/spec.md, scenario "Counter
	 * survives log rotation": MetricsController emits this app value, never a
	 * line count of the file.
	 *
	 * @return void
	 */
	public function testCounterKeepsCountingAcrossRotation(): void {
		$this->appConfig['audit_entries_total'] = '41';
		$service = $this->makeService();

		$padding = str_repeat('a', 1048500) . "\n";
		$this->root->auditFolder->newFile('audit.jsonl', $padding);

		$service->log(action: 'token_set_changed', context: ['old' => 'a', 'new' => 'b']);

		$this->assertTrue(condition: $this->root->auditFolder->fileExists('audit.jsonl.1'));
		$this->assertSame(expected: '', actual: $this->root->auditFolder->getFile('audit.jsonl')->getContent());
		$this->assertSame(expected: '42', actual: $this->appConfig['audit_entries_total']);
	}//end testCounterKeepsCountingAcrossRotation()

	/**
	 * getRecent() returns newest-first and respects the limit.
	 *
	 * @return void
	 */
	public function testGetRecentOrderAndLimit(): void {
		$service = $this->makeService();

		$service->log(action: 'token_set_changed', context: ['old' => '1', 'new' => '2']);
		$service->log(action: 'token_set_changed', context: ['old' => '2', 'new' => '3']);
		$service->log(action: 'token_set_changed', context: ['old' => '3', 'new' => '4']);

		$recent = $service->getRecent(limit: 2);
		$this->assertCount(expectedCount: 2, haystack: $recent);
		$this->assertSame(expected: '4', actual: $recent[0]['new']);
		$this->assertSame(expected: '3', actual: $recent[1]['new']);
	}//end testGetRecentOrderAndLimit()

	/**
	 * exportAll() returns the rotated generation before the current file,
	 * oldest first overall.
	 *
	 * @return void
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

		$this->assertIsInt(actual: $rotatedPos);
		$this->assertIsInt(actual: $currentPos);
		$this->assertLessThan(expected: $currentPos, actual: $rotatedPos);
	}//end testExportAllIncludesRotatedGenerationFirst()

	/**
	 * A Throwable from the appdata layer is caught, logged, and swallowed —
	 * log() never propagates it to the caller.
	 *
	 * @return void
	 */
	public function testAppdataFailureIsSwallowedAndWarned(): void {
		$this->logger->expects($this->once())->method('warning');

		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->appConfig[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->appConfig[$key] = $value;
			}
		);

		$timeFactory = $this->createMock(originalClassName: ITimeFactory::class);
		$timeFactory->method('getTime')->willReturn(1700000000);
		$this->userSession->method('getUser')->willReturn(null);

		$service = new ThemingAuditService(
			appDataFactory: new ThrowingAuditAppDataFactory(),
			config: $config,
			userSession: $this->userSession,
			timeFactory: $timeFactory,
			logger: $this->logger,
			versionService: $this->createMock(originalClassName: ThemeVersionService::class)
		);

		// Must not throw.
		$service->log(action: 'token_set_changed', context: ['old' => 'a', 'new' => 'b']);

		// The counter must NOT have been incremented — the append failed.
		$this->assertSame(expected: '0', actual: ($this->appConfig['audit_entries_total'] ?? '0'));
	}//end testAppdataFailureIsSwallowedAndWarned()

	/**
	 * An entry for which a version was kept names it.
	 *
	 * @spec openspec/specs/theming-audit/spec.md
	 *
	 * @return void
	 */
	public function testAnEntryNamesTheVersionItProduced(): void {
		$versions = $this->createMock(originalClassName: ThemeVersionService::class);
		$versions->expects($this->once())->method('capture')->with('token_set_changed', 'cli')->willReturn('20260929164000-0001');

		$service = $this->makeService(versions: $versions);
		$service->log(action: 'token_set_changed', context: ['old' => 'a', 'new' => 'b']);

		$this->assertSame(expected: '20260929164000-0001', actual: $service->getRecent(limit: 1)[0]['versionId']);
	}//end testAnEntryNamesTheVersionItProduced()

	/**
	 * An entry for which no version was kept omits the field.
	 *
	 * @spec openspec/specs/theming-audit/spec.md
	 *
	 * @return void
	 */
	public function testAnEntryWithoutAVersionOmitsTheField(): void {
		$versions = $this->createMock(originalClassName: ThemeVersionService::class);
		$versions->method('capture')->willReturn(null);

		$service = $this->makeService(versions: $versions);
		$service->log(action: 'toggle_changed', context: ['key' => 'hide_slogan', 'old' => '0', 'new' => '1']);

		$this->assertArrayNotHasKey(key: 'versionId', array: $service->getRecent(limit: 1)[0]);
	}//end testAnEntryWithoutAVersionOmitsTheField()

	/**
	 * A capture that throws still writes the entry.
	 *
	 * @return void
	 */
	public function testAThrowingCaptureStillWritesTheEntry(): void {
		$versions = $this->createMock(originalClassName: ThemeVersionService::class);
		$versions->method('capture')->willThrowException(new \RuntimeException('boom'));

		$service = $this->makeService(versions: $versions);
		$service->log(action: 'overrides_written', context: []);

		$entries = $service->getRecent(limit: 1);
		$this->assertSame(expected: 'overrides_written', actual: $entries[0]['action']);
		$this->assertArrayNotHasKey(key: 'versionId', array: $entries[0]);
	}//end testAThrowingCaptureStillWritesTheEntry()

	/**
	 * version_restored is part of the closed vocabulary.
	 *
	 * @spec openspec/specs/theming-audit/spec.md
	 *
	 * @return void
	 */
	public function testVersionRestoredIsAcceptedAction(): void {
		$service = $this->makeService();
		$service->log(action: 'version_restored', context: ['old' => '20260929164000-0002', 'new' => '20260929164000-0001']);

		$entry = $service->getRecent(limit: 1)[0];
		$this->assertSame(expected: 'version_restored', actual: $entry['action']);
		$this->assertSame(expected: '20260929164000-0001', actual: $entry['new']);
	}//end testVersionRestoredIsAcceptedAction()

	/**
	 * scheduled_switch_applied is part of the closed vocabulary, and the
	 * background job's entry carries actor `system` although cron runs in CLI.
	 *
	 * @spec openspec/specs/theming-audit/spec.md
	 *
	 * @return void
	 */
	public function testScheduledSwitchAppliedIsAcceptedWithSystemActor(): void {
		$service = $this->makeService();
		$service->log(
			action: 'scheduled_switch_applied',
			context: ['old' => 'rijkshuisstijl', 'new' => 'koningsdag-oranje', 'actor' => 'system', 'switchId' => 'abc123']
		);

		$entry = $service->getRecent(limit: 1)[0];
		$this->assertSame(expected: 'scheduled_switch_applied', actual: $entry['action']);
		$this->assertSame(expected: 'system', actual: $entry['actor']);
		$this->assertSame(expected: 'rijkshuisstijl', actual: $entry['old']);
		$this->assertSame(expected: 'koningsdag-oranje', actual: $entry['new']);
		$this->assertSame(expected: 'abc123', actual: $entry['switchId']);
	}//end testScheduledSwitchAppliedIsAcceptedWithSystemActor()

	/**
	 * Each dropped or degraded entry logs its own warning; a smuggled versionId never reaches the entry.
	 *
	 * @return void
	 */
	public function testTheWarningsOfADroppedOrDegradedEntry(): void {
		$warnings = [];
		$this->logger->method('warning')->willReturnCallback(
			function (string $message, array $context = []) use (&$warnings): void {
				$warnings[] = [$message, $context];
			}
		);
		$throwing = $this->createMock(originalClassName: ThemeVersionService::class);
		$throwing->method('capture')->willThrowException(new \RuntimeException('boom'));

		$this->makeService()->log(action: 'not_an_action', context: []);
		$this->makeService(versions: $throwing)->log(action: 'overrides_written', context: ['versionId' => 'forged']);
		$quiet = $this->createMock(originalClassName: ThemeVersionService::class);
		$quiet->method('capture')->willReturn(null);
		$this->makeService(versions: $quiet)->log(action: 'overrides_written', context: ['note' => "\xB1"]);

		$this->assertSame(
			expected: [
				['nldesign audit: rejected unknown action "{action}" — entry dropped', ['action' => 'not_an_action']],
				['nldesign audit: no version was kept for action "{action}": {message}', ['action' => 'overrides_written', 'message' => 'boom']],
				['nldesign audit: could not encode entry for action "{action}"', ['action' => 'overrides_written']],
			],
			actual: $warnings
		);
		$entries = $this->makeService(versions: $quiet)->getRecent(limit: 5);
		$this->assertCount(expectedCount: 1, haystack: $entries);
		$this->assertArrayNotHasKey(key: 'versionId', array: $entries[0]);
		$this->assertSame(expected: '1', actual: $this->appConfig['audit_entries_total']);
	}//end testTheWarningsOfADroppedOrDegradedEntry()

	/**
	 * An entry cannot be edited or deleted: a later log() leaves the earlier
	 * line byte for byte as it was, and the class offers no public way to
	 * change or remove an entry.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theming-audit/spec.md#requirement-an-audit-entry-cannot-be-edited-or-deleted
	 */
	public function testAnEntryCannotBeEditedOrDeleted(): void {
		$service = $this->makeService(time: 1700000000);

		$service->log(action: 'token_set_changed', context: ['old' => 'rijkshuisstijl', 'new' => 'amsterdam']);
		$first = $this->root->auditFolder->getFile('audit.jsonl')->getContent();
		$this->assertStringEndsWith(suffix: "\n", string: $first);

		$service->log(action: 'token_set_changed', context: ['old' => 'amsterdam', 'new' => 'utrecht']);
		$both = $this->root->auditFolder->getFile('audit.jsonl')->getContent();

		$this->assertStringStartsWith(prefix: $first, string: $both);
		$this->assertSame(expected: 2, actual: substr_count($both, "\n"));

		$public = array_map(
			static fn (\ReflectionMethod $method): string => $method->getName(),
			(new \ReflectionClass(ThemingAuditService::class))->getMethods(\ReflectionMethod::IS_PUBLIC)
		);
		sort($public);
		$this->assertSame(expected: ['__construct', 'exportAll', 'getRecent', 'log'], actual: $public);
	}//end testAnEntryCannotBeEditedOrDeleted()
}//end class
