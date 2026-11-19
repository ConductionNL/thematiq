<?php

/**
 * Unit tests for UserTokenSetResolver.
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
 * @spec openspec/specs/document-house-style/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\UserTokenSetResolver;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Golden of the set each user resolves to, and what is asked on the way.
 */
class UserTokenSetResolverTest extends TestCase {

	/**
	 * Every kind of user, with the calls made, in order.
	 *
	 * @return void
	 */
	public function testEveryUserGolden(): void {
		$calls = [];
		$user = function (string $uid): IUser {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($uid);

			return $user;
		};
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user('me'));
		$users = $this->createMock(originalClassName: IUserManager::class);
		$users->method('get')->willReturnCallback(
			static function (string $uid) use (&$calls, $user): ?IUser {
				$calls[] = 'user ' . $uid;

				return ($uid === 'ghost' ? null : $user($uid));
			}
		);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('getUserGroupIds')->willReturnCallback(
			static fn (IUser $u): array => ['ann' => ['b', 'c'], 'bob' => ['x'], 'cat' => ['a', 'c']][$u->getUID()] ?? []
		);
		$theming = $this->createMock(originalClassName: GroupThemingService::class);
		$theming->method('resolveTokenSetForRequest')->willReturnCallback(
			static function () use (&$calls): string {
				$calls[] = 'request';

				return 'session-set';
			}
		);
		$theming->method('getMapping')->willReturn([['group' => 'a', 'tokenSet' => 'gone'], ['group' => 'b', 'tokenSet' => 'utrecht'], ['group' => 'c', 'tokenSet' => 'leiden']]);
		$sets = $this->createMock(originalClassName: TokenSetService::class);
		$sets->method('isValidTokenSet')->willReturnCallback(
			static function (string $tokenSetId) use (&$calls): bool {
				$calls[] = 'valid? ' . $tokenSetId;

				return $tokenSetId !== 'gone';
			}
		);
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, string $default) use (&$calls): string {
				$calls[] = 'default';

				return 'instance-set';
			}
		);
		$resolver = new UserTokenSetResolver(groupTheming: $theming, tokenSets: $sets, groupManager: $groups, userManager: $users, userSession: $session, config: $config);

		$out = [];
		foreach ([null, '', 'me', 'ghost', 'ann', 'bob', 'cat'] as $uid) {
			$calls = [];
			$out[var_export($uid, true)] = [$resolver->forUser(uid: $uid), $calls];
		}

		$this->assertSame(
			expected: [
				'NULL' => ['instance-set', ['default']],
				"''" => ['instance-set', ['default']],
				"'me'" => ['session-set', ['request']],
				"'ghost'" => ['instance-set', ['user ghost', 'default']],
				"'ann'" => ['utrecht', ['user ann', 'valid? utrecht']],
				"'bob'" => ['instance-set', ['user bob', 'default']],
				"'cat'" => ['leiden', ['user cat', 'valid? gone', 'valid? leiden']],
			],
			actual: $out
		);
	}//end testEveryUserGolden()
}//end class
