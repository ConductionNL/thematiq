<?php

/**
 * Unit tests for ThemePreviewService.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/archive/2026-07-23-theme-preview-workflow/tasks.md#task-5.1
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ThemePreviewService;
use OCA\Thematiq\Service\TokenSetService;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers openspec/specs/theme-preview/spec.md: per-user preview state (start,
 * read, clear, publish), lazy expiry, the deleted-set defence, and the
 * render-layer effective-token-set resolution contract (demotion defence,
 * zero-cost path for users without a preview, fail-open on exception).
 */
class ThemePreviewServiceTest extends TestCase {

	/**
	 * In-memory app-value store: key => value.
	 *
	 * @var array<string, string>
	 */
	private array $appStore = [];

	/**
	 * In-memory user-value store: uid => key => value.
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $userStore = [];

	/**
	 * The mocked group manager.
	 *
	 * @var IGroupManager&\PHPUnit\Framework\MockObject\MockObject
	 */
	private IGroupManager $groupManager;

	/**
	 * Per-uid admin membership backing the group manager mock's isAdmin()
	 * callback — defaults to true unless a test overrides a uid to false
	 * (the demotion-defence scenario).
	 *
	 * @var array<string, bool>
	 */
	private array $adminByUid = [];

	/**
	 * The mocked token set service.
	 *
	 * @var TokenSetService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private TokenSetService $tokenSetService;

	/**
	 * Set up each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->appStore = [];
		$this->userStore = [];
		$this->adminByUid = [];
	}//end setUp()

	/**
	 * Build an IConfig mock backed by $this->appStore / $this->userStore.
	 *
	 * @return IConfig
	 */
	private function makeConfig(): IConfig {
		$config = $this->createMock(originalClassName: IConfig::class);

		$config->method('getAppValue')->willReturnCallback(
			function (string $app, string $key, $default = '') {
				return ($this->appStore[$key] ?? $default);
			}
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value) {
				$this->appStore[$key] = $value;
			}
		);
		$config->method('getUserValue')->willReturnCallback(
			function (?string $uid, string $app, string $key, $default = '') {
				return ($this->userStore[$uid][$key] ?? $default);
			}
		);
		$config->method('setUserValue')->willReturnCallback(
			function (string $uid, string $app, string $key, $value) {
				$this->userStore[$uid][$key] = $value;
			}
		);
		$config->method('deleteUserValue')->willReturnCallback(
			function (string $uid, string $app, string $key) {
				unset($this->userStore[$uid][$key]);
			}
		);

		return $config;
	}//end makeConfig()

	/**
	 * Build the service under test with a given IConfig and an
	 * always-valid-unless-told-otherwise TokenSetService stub.
	 *
	 * @param IConfig $config The config.
	 * @param bool $validTokenSet Whether the stub treats every token set as valid.
	 *
	 * @return ThemePreviewService
	 */
	private function makeService(IConfig $config, bool $validTokenSet = true): ThemePreviewService {
		$this->groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$this->groupManager->method('isAdmin')->willReturnCallback(
			fn (string $uid): bool => ($this->adminByUid[$uid] ?? true)
		);

		$this->tokenSetService = $this->createMock(originalClassName: TokenSetService::class);
		$this->tokenSetService->method('isValidTokenSet')->willReturn($validTokenSet);

		return new ThemePreviewService(
			config: $config,
			groupManager: $this->groupManager,
			tokenSetService: $this->tokenSetService,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end makeService()

	/**
	 * Build a mocked IUserSession returning a user with the given uid, or no
	 * user at all when $uid is null.
	 *
	 * @param ?string $uid The uid.
	 *
	 * @return IUserSession
	 */
	private function makeUserSession(?string $uid): IUserSession {
		$userSession = $this->createMock(originalClassName: IUserSession::class);

		if ($uid === null) {
			$userSession->method('getUser')->willReturn(null);

			return $userSession;
		}

		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($uid);
		$userSession->method('getUser')->willReturn($user);

		return $userSession;
	}//end makeUserSession()

	/**
	 * startPreview() writes both user values with a ~24h expiry.
	 *
	 * @return void
	 */
	public function testStartPreviewWritesBothUserValuesWithExpiry(): void {
		$config = $this->makeConfig();
		$service = $this->makeService(config: $config);

		$before = time();
		$state = $service->startPreview('admin', 'amsterdam');
		$after = time();

		$this->assertSame(expected: 'amsterdam', actual: $state['tokenSet']);
		$this->assertSame(expected: 'amsterdam', actual: $this->userStore['admin']['preview_token_set']);
		$this->assertSame(expected: (string)$state['expiresAt'], actual: $this->userStore['admin']['preview_expires_at']);
		$this->assertGreaterThanOrEqual(expected: $before + 86400, actual: $state['expiresAt']);
		$this->assertLessThanOrEqual(expected: $after + 86400, actual: $state['expiresAt']);
	}//end testStartPreviewWritesBothUserValuesWithExpiry()

	/**
	 * An invalid token set id is rejected and no user values are written.
	 *
	 * @return void
	 */
	public function testStartPreviewRejectsInvalidId(): void {
		$config = $this->makeConfig();
		$service = $this->makeService(config: $config, validTokenSet: false);

		$this->expectException(exception: \InvalidArgumentException::class);

		try {
			$service->startPreview('admin', 'does-not-exist');
		} finally {
			$this->assertArrayNotHasKey(key: 'admin', array: $this->userStore);
		}
	}//end testStartPreviewRejectsInvalidId()

	/**
	 * getActivePreview() returns null when no preview value is set.
	 *
	 * @return void
	 */
	public function testGetActivePreviewReturnsNullWhenAbsent(): void {
		$config = $this->makeConfig();
		$service = $this->makeService(config: $config);

		$this->assertNull(actual: $service->getActivePreview('admin'));
	}//end testGetActivePreviewReturnsNullWhenAbsent()

	/**
	 * An expired preview is treated as absent and the stale values are
	 * opportunistically cleared.
	 *
	 * @return void
	 */
	public function testGetActivePreviewReturnsNullWhenExpiredAndClearsState(): void {
		$config = $this->makeConfig();
		$service = $this->makeService(config: $config);

		$this->userStore['admin']['preview_token_set'] = 'amsterdam';
		$this->userStore['admin']['preview_expires_at'] = (string)(time() - 10);

		$this->assertNull(actual: $service->getActivePreview('admin'));
		$this->assertArrayNotHasKey(key: 'preview_token_set', array: $this->userStore['admin']);
		$this->assertArrayNotHasKey(key: 'preview_expires_at', array: $this->userStore['admin']);
	}//end testGetActivePreviewReturnsNullWhenExpiredAndClearsState()

	/**
	 * A no-longer-valid token set (e.g. a deleted custom set) is treated as
	 * absent and the stale values are opportunistically cleared.
	 *
	 * @return void
	 */
	public function testGetActivePreviewReturnsNullWhenTokenSetNoLongerValid(): void {
		$config = $this->makeConfig();
		$service = $this->makeService(config: $config, validTokenSet: false);

		$this->userStore['admin']['preview_token_set'] = 'deleted-custom-set';
		$this->userStore['admin']['preview_expires_at'] = (string)(time() + 3600);

		$this->assertNull(actual: $service->getActivePreview('admin'));
		$this->assertArrayNotHasKey(key: 'preview_token_set', array: $this->userStore['admin']);
	}//end testGetActivePreviewReturnsNullWhenTokenSetNoLongerValid()

	/**
	 * A non-expired, still-valid preview is returned as-is.
	 *
	 * @return void
	 */
	public function testGetActivePreviewReturnsStateWhenActive(): void {
		$config = $this->makeConfig();
		$service = $this->makeService(config: $config);

		$expiresAt = (time() + 3600);
		$this->userStore['admin']['preview_token_set'] = 'amsterdam';
		$this->userStore['admin']['preview_expires_at'] = (string)$expiresAt;

		$this->assertSame(
			expected: ['tokenSet' => 'amsterdam', 'expiresAt' => $expiresAt],
			actual: $service->getActivePreview('admin')
		);
	}//end testGetActivePreviewReturnsStateWhenActive()

	/**
	 * clearPreview() deletes both user values.
	 *
	 * @return void
	 */
	public function testClearPreviewDeletesBothValues(): void {
		$config = $this->makeConfig();
		$service = $this->makeService(config: $config);

		$this->userStore['admin']['preview_token_set'] = 'amsterdam';
		$this->userStore['admin']['preview_expires_at'] = (string)(time() + 3600);

		$service->clearPreview('admin');

		$this->assertArrayNotHasKey(key: 'preview_token_set', array: $this->userStore['admin']);
		$this->assertArrayNotHasKey(key: 'preview_expires_at', array: $this->userStore['admin']);
	}//end testClearPreviewDeletesBothValues()

	/**
	 * publishPreview() sets the app value and clears the user's preview.
	 *
	 * @return void
	 */
	public function testPublishPreviewSetsAppValueAndClearsUserValues(): void {
		$config = $this->makeConfig();
		$service = $this->makeService(config: $config);

		$this->appStore['token_set'] = 'rijkshuisstijl';
		$this->userStore['admin']['preview_token_set'] = 'amsterdam';
		$this->userStore['admin']['preview_expires_at'] = (string)(time() + 3600);

		$published = $service->publishPreview('admin');

		$this->assertSame(expected: 'amsterdam', actual: $published);
		$this->assertSame(expected: 'amsterdam', actual: $this->appStore['token_set']);
		$this->assertArrayNotHasKey(key: 'preview_token_set', array: $this->userStore['admin']);
		$this->assertArrayNotHasKey(key: 'preview_expires_at', array: $this->userStore['admin']);
	}//end testPublishPreviewSetsAppValueAndClearsUserValues()

	/**
	 * publishPreview() throws and changes nothing when there is no active preview.
	 *
	 * @return void
	 */
	public function testPublishPreviewThrowsWhenNoActivePreview(): void {
		$config = $this->makeConfig();
		$service = $this->makeService(config: $config);

		$this->appStore['token_set'] = 'rijkshuisstijl';

		$this->expectException(exception: \RuntimeException::class);

		try {
			$service->publishPreview('admin');
		} finally {
			$this->assertSame(expected: 'rijkshuisstijl', actual: $this->appStore['token_set']);
		}
	}//end testPublishPreviewThrowsWhenNoActivePreview()

	/**
	 * No user session (e.g. an anonymous/login-page request) falls back to
	 * the active set.
	 *
	 * @return void
	 */
	public function testResolveEffectiveTokenSetFallsBackWhenNoUser(): void {
		$config = $this->makeConfig();
		$service = $this->makeService(config: $config);
		$userSession = $this->makeUserSession(uid: null);

		$result = $service->resolveEffectiveTokenSet($userSession, 'rijkshuisstijl');

		$this->assertSame(
			expected: ['tokenSet' => 'rijkshuisstijl', 'previewActive' => false, 'expiresAt' => null],
			actual: $result
		);
	}//end testResolveEffectiveTokenSetFallsBackWhenNoUser()

	/**
	 * No preview value set: falls back to the active set, and the (costlier)
	 * admin check is never even called — the zero-cost path.
	 *
	 * @return void
	 */
	public function testResolveEffectiveTokenSetSkipsAdminCheckWhenNoPreviewValue(): void {
		$config = $this->makeConfig();
		$service = $this->makeService(config: $config);
		$userSession = $this->makeUserSession(uid: 'regularuser');

		$this->groupManager->expects($this->never())->method('isAdmin');

		$result = $service->resolveEffectiveTokenSet($userSession, 'rijkshuisstijl');

		$this->assertFalse(condition: $result['previewActive']);
		$this->assertSame(expected: 'rijkshuisstijl', actual: $result['tokenSet']);
	}//end testResolveEffectiveTokenSetSkipsAdminCheckWhenNoPreviewValue()

	/**
	 * Demotion defence: a non-admin user with a (manually planted) preview
	 * value still gets the ACTIVE set, not the preview.
	 *
	 * @return void
	 */
	public function testResolveEffectiveTokenSetFallsBackWhenUserIsNotAdmin(): void {
		$config = $this->makeConfig();
		$service = $this->makeService(config: $config);
		$userSession = $this->makeUserSession(uid: 'demoted');

		$this->userStore['demoted']['preview_token_set'] = 'amsterdam';
		$this->userStore['demoted']['preview_expires_at'] = (string)(time() + 3600);
		$this->adminByUid['demoted'] = false;

		$result = $service->resolveEffectiveTokenSet($userSession, 'rijkshuisstijl');

		$this->assertFalse(condition: $result['previewActive']);
		$this->assertSame(expected: 'rijkshuisstijl', actual: $result['tokenSet']);
	}//end testResolveEffectiveTokenSetFallsBackWhenUserIsNotAdmin()

	/**
	 * An expired preview is ignored — the active set renders.
	 *
	 * @return void
	 */
	public function testResolveEffectiveTokenSetFallsBackWhenExpired(): void {
		$config = $this->makeConfig();
		$service = $this->makeService(config: $config);
		$userSession = $this->makeUserSession(uid: 'admin');

		$this->userStore['admin']['preview_token_set'] = 'amsterdam';
		$this->userStore['admin']['preview_expires_at'] = (string)(time() - 10);

		$result = $service->resolveEffectiveTokenSet($userSession, 'rijkshuisstijl');

		$this->assertFalse(condition: $result['previewActive']);
		$this->assertSame(expected: 'rijkshuisstijl', actual: $result['tokenSet']);
	}//end testResolveEffectiveTokenSetFallsBackWhenExpired()

	/**
	 * A no-longer-valid previewed token set falls back to the active set.
	 *
	 * @return void
	 */
	public function testResolveEffectiveTokenSetFallsBackWhenTokenSetInvalid(): void {
		$config = $this->makeConfig();
		$service = $this->makeService(config: $config, validTokenSet: false);
		$userSession = $this->makeUserSession(uid: 'admin');

		$this->userStore['admin']['preview_token_set'] = 'deleted-custom-set';
		$this->userStore['admin']['preview_expires_at'] = (string)(time() + 3600);

		$result = $service->resolveEffectiveTokenSet($userSession, 'rijkshuisstijl');

		$this->assertFalse(condition: $result['previewActive']);
		$this->assertSame(expected: 'rijkshuisstijl', actual: $result['tokenSet']);
	}//end testResolveEffectiveTokenSetFallsBackWhenTokenSetInvalid()

	/**
	 * All conditions hold: the previewed set substitutes the active one.
	 *
	 * @return void
	 */
	public function testResolveEffectiveTokenSetReturnsPreviewedSetWhenAllValid(): void {
		$config = $this->makeConfig();
		$service = $this->makeService(config: $config);
		$userSession = $this->makeUserSession(uid: 'admin');

		$expiresAt = (time() + 3600);
		$this->userStore['admin']['preview_token_set'] = 'amsterdam';
		$this->userStore['admin']['preview_expires_at'] = (string)$expiresAt;

		$result = $service->resolveEffectiveTokenSet($userSession, 'rijkshuisstijl');

		$this->assertSame(
			expected: ['tokenSet' => 'amsterdam', 'previewActive' => true, 'expiresAt' => $expiresAt],
			actual: $result
		);
	}//end testResolveEffectiveTokenSetReturnsPreviewedSetWhenAllValid()

	/**
	 * Any exception resolving the user session (CLI/occ/cron) falls back to
	 * the active set and never propagates — boot must never crash.
	 *
	 * @return void
	 */
	public function testResolveEffectiveTokenSetFallsBackWhenUserSessionThrows(): void {
		$config = $this->makeConfig();
		$service = $this->makeService(config: $config);
		$userSession = $this->createMock(originalClassName: IUserSession::class);
		$userSession->method('getUser')->willThrowException(new \RuntimeException('no session'));

		$result = $service->resolveEffectiveTokenSet($userSession, 'rijkshuisstijl');

		$this->assertSame(
			expected: ['tokenSet' => 'rijkshuisstijl', 'previewActive' => false, 'expiresAt' => null],
			actual: $result
		);
	}//end testResolveEffectiveTokenSetFallsBackWhenUserSessionThrows()
}//end class
