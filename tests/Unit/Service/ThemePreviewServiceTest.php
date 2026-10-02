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

	protected function setUp(): void {
		parent::setUp();
		$this->appStore = [];
		$this->userStore = [];
		$this->adminByUid = [];
	}//end setUp()

	/**
	 * Build an IConfig mock backed by $this->appStore / $this->userStore.
	 */
	private function makeConfig(): IConfig {
		$config = $this->createMock(IConfig::class);

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
	 */
	private function makeService(IConfig $config, bool $validTokenSet = true): ThemePreviewService {
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->groupManager->method('isAdmin')->willReturnCallback(
			fn (string $uid): bool => ($this->adminByUid[$uid] ?? true)
		);

		$this->tokenSetService = $this->createMock(TokenSetService::class);
		$this->tokenSetService->method('isValidTokenSet')->willReturn($validTokenSet);

		return new ThemePreviewService(
			$config,
			$this->groupManager,
			$this->tokenSetService,
			$this->createMock(LoggerInterface::class)
		);
	}//end makeService()

	/**
	 * Build a mocked IUserSession returning a user with the given uid, or no
	 * user at all when $uid is null.
	 */
	private function makeUserSession(?string $uid): IUserSession {
		$userSession = $this->createMock(IUserSession::class);

		if ($uid === null) {
			$userSession->method('getUser')->willReturn(null);

			return $userSession;
		}

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$userSession->method('getUser')->willReturn($user);

		return $userSession;
	}//end makeUserSession()

	/**
	 * startPreview() writes both user values with a ~24h expiry.
	 */
	public function testStartPreviewWritesBothUserValuesWithExpiry(): void {
		$config = $this->makeConfig();
		$service = $this->makeService($config);

		$before = time();
		$state = $service->startPreview('admin', 'amsterdam');
		$after = time();

		$this->assertSame('amsterdam', $state['tokenSet']);
		$this->assertSame('amsterdam', $this->userStore['admin']['preview_token_set']);
		$this->assertSame((string)$state['expiresAt'], $this->userStore['admin']['preview_expires_at']);
		$this->assertGreaterThanOrEqual($before + 86400, $state['expiresAt']);
		$this->assertLessThanOrEqual($after + 86400, $state['expiresAt']);
	}//end testStartPreviewWritesBothUserValuesWithExpiry()

	/**
	 * An invalid token set id is rejected and no user values are written.
	 */
	public function testStartPreviewRejectsInvalidId(): void {
		$config = $this->makeConfig();
		$service = $this->makeService($config, validTokenSet: false);

		$this->expectException(\InvalidArgumentException::class);

		try {
			$service->startPreview('admin', 'does-not-exist');
		} finally {
			$this->assertArrayNotHasKey('admin', $this->userStore);
		}
	}//end testStartPreviewRejectsInvalidId()

	/**
	 * getActivePreview() returns null when no preview value is set.
	 */
	public function testGetActivePreviewReturnsNullWhenAbsent(): void {
		$config = $this->makeConfig();
		$service = $this->makeService($config);

		$this->assertNull($service->getActivePreview('admin'));
	}//end testGetActivePreviewReturnsNullWhenAbsent()

	/**
	 * An expired preview is treated as absent and the stale values are
	 * opportunistically cleared.
	 */
	public function testGetActivePreviewReturnsNullWhenExpiredAndClearsState(): void {
		$config = $this->makeConfig();
		$service = $this->makeService($config);

		$this->userStore['admin']['preview_token_set'] = 'amsterdam';
		$this->userStore['admin']['preview_expires_at'] = (string)(time() - 10);

		$this->assertNull($service->getActivePreview('admin'));
		$this->assertArrayNotHasKey('preview_token_set', $this->userStore['admin']);
		$this->assertArrayNotHasKey('preview_expires_at', $this->userStore['admin']);
	}//end testGetActivePreviewReturnsNullWhenExpiredAndClearsState()

	/**
	 * A no-longer-valid token set (e.g. a deleted custom set) is treated as
	 * absent and the stale values are opportunistically cleared.
	 */
	public function testGetActivePreviewReturnsNullWhenTokenSetNoLongerValid(): void {
		$config = $this->makeConfig();
		$service = $this->makeService($config, validTokenSet: false);

		$this->userStore['admin']['preview_token_set'] = 'deleted-custom-set';
		$this->userStore['admin']['preview_expires_at'] = (string)(time() + 3600);

		$this->assertNull($service->getActivePreview('admin'));
		$this->assertArrayNotHasKey('preview_token_set', $this->userStore['admin']);
	}//end testGetActivePreviewReturnsNullWhenTokenSetNoLongerValid()

	/**
	 * A non-expired, still-valid preview is returned as-is.
	 */
	public function testGetActivePreviewReturnsStateWhenActive(): void {
		$config = $this->makeConfig();
		$service = $this->makeService($config);

		$expiresAt = (time() + 3600);
		$this->userStore['admin']['preview_token_set'] = 'amsterdam';
		$this->userStore['admin']['preview_expires_at'] = (string)$expiresAt;

		$this->assertSame(
			['tokenSet' => 'amsterdam', 'expiresAt' => $expiresAt],
			$service->getActivePreview('admin')
		);
	}//end testGetActivePreviewReturnsStateWhenActive()

	/**
	 * clearPreview() deletes both user values.
	 */
	public function testClearPreviewDeletesBothValues(): void {
		$config = $this->makeConfig();
		$service = $this->makeService($config);

		$this->userStore['admin']['preview_token_set'] = 'amsterdam';
		$this->userStore['admin']['preview_expires_at'] = (string)(time() + 3600);

		$service->clearPreview('admin');

		$this->assertArrayNotHasKey('preview_token_set', $this->userStore['admin']);
		$this->assertArrayNotHasKey('preview_expires_at', $this->userStore['admin']);
	}//end testClearPreviewDeletesBothValues()

	/**
	 * publishPreview() sets the app value and clears the user's preview.
	 */
	public function testPublishPreviewSetsAppValueAndClearsUserValues(): void {
		$config = $this->makeConfig();
		$service = $this->makeService($config);

		$this->appStore['token_set'] = 'rijkshuisstijl';
		$this->userStore['admin']['preview_token_set'] = 'amsterdam';
		$this->userStore['admin']['preview_expires_at'] = (string)(time() + 3600);

		$published = $service->publishPreview('admin');

		$this->assertSame('amsterdam', $published);
		$this->assertSame('amsterdam', $this->appStore['token_set']);
		$this->assertArrayNotHasKey('preview_token_set', $this->userStore['admin']);
		$this->assertArrayNotHasKey('preview_expires_at', $this->userStore['admin']);
	}//end testPublishPreviewSetsAppValueAndClearsUserValues()

	/**
	 * publishPreview() throws and changes nothing when there is no active preview.
	 */
	public function testPublishPreviewThrowsWhenNoActivePreview(): void {
		$config = $this->makeConfig();
		$service = $this->makeService($config);

		$this->appStore['token_set'] = 'rijkshuisstijl';

		$this->expectException(\RuntimeException::class);

		try {
			$service->publishPreview('admin');
		} finally {
			$this->assertSame('rijkshuisstijl', $this->appStore['token_set']);
		}
	}//end testPublishPreviewThrowsWhenNoActivePreview()

	/**
	 * No user session (e.g. an anonymous/login-page request) falls back to
	 * the active set.
	 */
	public function testResolveEffectiveTokenSetFallsBackWhenNoUser(): void {
		$config = $this->makeConfig();
		$service = $this->makeService($config);
		$userSession = $this->makeUserSession(null);

		$result = $service->resolveEffectiveTokenSet($userSession, 'rijkshuisstijl');

		$this->assertSame(
			['tokenSet' => 'rijkshuisstijl', 'previewActive' => false, 'expiresAt' => null],
			$result
		);
	}//end testResolveEffectiveTokenSetFallsBackWhenNoUser()

	/**
	 * No preview value set: falls back to the active set, and the (costlier)
	 * admin check is never even called — the zero-cost path.
	 */
	public function testResolveEffectiveTokenSetSkipsAdminCheckWhenNoPreviewValue(): void {
		$config = $this->makeConfig();
		$service = $this->makeService($config);
		$userSession = $this->makeUserSession('regularuser');

		$this->groupManager->expects($this->never())->method('isAdmin');

		$result = $service->resolveEffectiveTokenSet($userSession, 'rijkshuisstijl');

		$this->assertFalse($result['previewActive']);
		$this->assertSame('rijkshuisstijl', $result['tokenSet']);
	}//end testResolveEffectiveTokenSetSkipsAdminCheckWhenNoPreviewValue()

	/**
	 * Demotion defence: a non-admin user with a (manually planted) preview
	 * value still gets the ACTIVE set, not the preview.
	 */
	public function testResolveEffectiveTokenSetFallsBackWhenUserIsNotAdmin(): void {
		$config = $this->makeConfig();
		$service = $this->makeService($config);
		$userSession = $this->makeUserSession('demoted');

		$this->userStore['demoted']['preview_token_set'] = 'amsterdam';
		$this->userStore['demoted']['preview_expires_at'] = (string)(time() + 3600);
		$this->adminByUid['demoted'] = false;

		$result = $service->resolveEffectiveTokenSet($userSession, 'rijkshuisstijl');

		$this->assertFalse($result['previewActive']);
		$this->assertSame('rijkshuisstijl', $result['tokenSet']);
	}//end testResolveEffectiveTokenSetFallsBackWhenUserIsNotAdmin()

	/**
	 * An expired preview is ignored — the active set renders.
	 */
	public function testResolveEffectiveTokenSetFallsBackWhenExpired(): void {
		$config = $this->makeConfig();
		$service = $this->makeService($config);
		$userSession = $this->makeUserSession('admin');

		$this->userStore['admin']['preview_token_set'] = 'amsterdam';
		$this->userStore['admin']['preview_expires_at'] = (string)(time() - 10);

		$result = $service->resolveEffectiveTokenSet($userSession, 'rijkshuisstijl');

		$this->assertFalse($result['previewActive']);
		$this->assertSame('rijkshuisstijl', $result['tokenSet']);
	}//end testResolveEffectiveTokenSetFallsBackWhenExpired()

	/**
	 * A no-longer-valid previewed token set falls back to the active set.
	 */
	public function testResolveEffectiveTokenSetFallsBackWhenTokenSetInvalid(): void {
		$config = $this->makeConfig();
		$service = $this->makeService($config, validTokenSet: false);
		$userSession = $this->makeUserSession('admin');

		$this->userStore['admin']['preview_token_set'] = 'deleted-custom-set';
		$this->userStore['admin']['preview_expires_at'] = (string)(time() + 3600);

		$result = $service->resolveEffectiveTokenSet($userSession, 'rijkshuisstijl');

		$this->assertFalse($result['previewActive']);
		$this->assertSame('rijkshuisstijl', $result['tokenSet']);
	}//end testResolveEffectiveTokenSetFallsBackWhenTokenSetInvalid()

	/**
	 * All conditions hold: the previewed set substitutes the active one.
	 */
	public function testResolveEffectiveTokenSetReturnsPreviewedSetWhenAllValid(): void {
		$config = $this->makeConfig();
		$service = $this->makeService($config);
		$userSession = $this->makeUserSession('admin');

		$expiresAt = (time() + 3600);
		$this->userStore['admin']['preview_token_set'] = 'amsterdam';
		$this->userStore['admin']['preview_expires_at'] = (string)$expiresAt;

		$result = $service->resolveEffectiveTokenSet($userSession, 'rijkshuisstijl');

		$this->assertSame(
			['tokenSet' => 'amsterdam', 'previewActive' => true, 'expiresAt' => $expiresAt],
			$result
		);
	}//end testResolveEffectiveTokenSetReturnsPreviewedSetWhenAllValid()

	/**
	 * Any exception resolving the user session (CLI/occ/cron) falls back to
	 * the active set and never propagates — boot must never crash.
	 */
	public function testResolveEffectiveTokenSetFallsBackWhenUserSessionThrows(): void {
		$config = $this->makeConfig();
		$service = $this->makeService($config);
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willThrowException(new \RuntimeException('no session'));

		$result = $service->resolveEffectiveTokenSet($userSession, 'rijkshuisstijl');

		$this->assertSame(
			['tokenSet' => 'rijkshuisstijl', 'previewActive' => false, 'expiresAt' => null],
			$result
		);
	}//end testResolveEffectiveTokenSetFallsBackWhenUserSessionThrows()
}//end class
