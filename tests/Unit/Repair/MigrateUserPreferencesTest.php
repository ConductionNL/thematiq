<?php

/**
 * Unit tests for the nldesign -> thematiq per-user preference migration.
 *
 * Nextcloud namespaces `oc_preferences` by app id, so the rename strands every
 * user's stored preference. Nothing errors — each reader supplies a default —
 * so every user silently reverts to the app's defaults.
 *
 * THE ENUMERATION IS THE POINT. `getUsersForUserValue()` looks like the natural
 * way to find affected users, but it takes a VALUE to match: over an open value
 * set it matches nobody, migrates nothing, and reports success. This app's
 * per-user keys are exactly that — a token set id drawn from 43 shipped sets
 * plus any number of admin-authored ones, and a unix expiry timestamp that is
 * different for every user. The step therefore walks users with
 * `callForSeenUsers()` and asks each one's `getUserKeys()`, and
 * `testMigratesEveryStoredKeyRegardlessOfItsValue()` is the test that would
 * fail if anyone swapped that back.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Repair
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Repair;

use Closure;
use OCA\Thematiq\Repair\MigrateUserPreferences;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Covers the per-user walk, the skips, and the failure paths.
 */
final class MigrateUserPreferencesTest extends TestCase {

	/**
	 * The app id this app used before the rename.
	 *
	 * @var string
	 */
	private const OLD = 'nldesign';

	/**
	 * The app id this app uses now.
	 *
	 * @var string
	 */
	private const NEW = 'thematiq';

	/**
	 * The in-memory preference store: user => app id => key => value.
	 *
	 * @var array<string, array<string, array<string, string>>>
	 */
	private array $store = [];

	/**
	 * User ids whose key enumeration throws.
	 *
	 * @var array<int, string>
	 */
	private array $keysThrowFor = [];

	/**
	 * User ids whose writes throw.
	 *
	 * @var array<int, string>
	 */
	private array $writeThrowsFor = [];

	/**
	 * Reset the store between tests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->store = [];
		$this->keysThrowFor = [];
		$this->writeThrowsFor = [];

	}//end setUp()

	/**
	 * An IConfig mock backed by `$this->store`.
	 *
	 * @return IConfig
	 */
	private function config(): IConfig {
		$config = $this->createMock(originalClassName: IConfig::class);

		$config->method('getUserKeys')
			->willReturnCallback(
				function (string $userId, string $app): array {
					if (in_array($userId, $this->keysThrowFor, strict: true) === true) {
						throw new RuntimeException('unreadable');
					}

					return array_keys(($this->store[$userId][$app] ?? []));
				}
			);

		$config->method('getUserValue')
			->willReturnCallback(
				function (string $userId, string $app, string $key, string $default = ''): string {
					return ($this->store[$userId][$app][$key] ?? $default);
				}
			);

		$config->method('setUserValue')
			->willReturnCallback(
				function (string $userId, string $app, string $key, string $value): void {
					if (in_array($userId, $this->writeThrowsFor, strict: true) === true) {
						throw new RuntimeException('read-only');
					}

					$this->store[$userId][$app][$key] = $value;
				}
			);

		return $config;
	}//end config()

	/**
	 * An IUserManager mock that walks the users present in the store.
	 *
	 * @param array<int, string>|null $userIds Users to walk; defaults to the store's.
	 * @param bool $throw Whether enumeration itself throws.
	 *
	 * @return IUserManager
	 */
	private function userManager(?array $userIds = null, bool $throw = false): IUserManager {
		$userManager = $this->createMock(originalClassName: IUserManager::class);

		if ($throw === true) {
			$userManager->method('callForSeenUsers')
				->willThrowException(new RuntimeException('no user backend'));
			return $userManager;
		}

		$userManager->method('callForSeenUsers')
			->willReturnCallback(
				function (Closure $callback) use ($userIds): void {
					foreach (($userIds ?? array_keys($this->store)) as $userId) {
						$user = $this->createMock(originalClassName: IUser::class);
						$user->method('getUID')->willReturn($userId);
						$callback($user);
					}
				}
			);

		return $userManager;
	}//end userManager()

	/**
	 * Build the step over the in-memory store.
	 *
	 * @param IUserManager|null $userManager An optional user manager.
	 * @param LoggerInterface|null $logger An optional logger to assert against.
	 *
	 * @return MigrateUserPreferences
	 */
	private function step(
		?IUserManager $userManager = null,
		?LoggerInterface $logger = null,
	): MigrateUserPreferences {
		return new MigrateUserPreferences(
			config: $this->config(),
			userManager: ($userManager ?? $this->userManager()),
			logger: ($logger ?? $this->createMock(originalClassName: LoggerInterface::class))
		);

	}//end step()

	/**
	 * Every user's stored preferences reach the new app id.
	 *
	 * @return void
	 */
	public function testCopiesEveryUsersPreferences(): void {
		$this->store = [
			'alice' => [self::OLD => ['preview_token_set' => 'utrecht', 'preview_expires_at' => '1756000000']],
			'bob' => [self::OLD => ['preview_token_set' => 'amsterdam']],
		];

		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		$this->assertSame(expected: 'utrecht', actual: $this->store['alice'][self::NEW]['preview_token_set']);
		$this->assertSame(expected: '1756000000', actual: $this->store['alice'][self::NEW]['preview_expires_at']);
		$this->assertSame(expected: 'amsterdam', actual: $this->store['bob'][self::NEW]['preview_token_set']);

	}//end testCopiesEveryUsersPreferences()

	/**
	 * Keys are found by enumeration, not by matching a value.
	 *
	 * TRAP 2. This is the regression test for `getUsersForUserValue()`: that
	 * call needs a value to match, so over an open value set it migrates
	 * nothing and still reports success. Three users here hold three DIFFERENT
	 * values under the same key — which is the normal case for this app, where
	 * the value is whichever of 43+ token sets that user chose to preview — and
	 * all three must move. A value-driven implementation would move at most the
	 * users who happen to share one exact value, and none of these three do.
	 *
	 * @return void
	 */
	public function testMigratesEveryStoredKeyRegardlessOfItsValue(): void {
		$this->store = [
			'alice' => [self::OLD => ['preview_token_set' => 'utrecht']],
			'bob' => [self::OLD => ['preview_token_set' => 'amsterdam']],
			'carol' => [self::OLD => ['preview_token_set' => 'rotterdam']],
		];

		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		$this->assertSame(expected: 'utrecht', actual: $this->store['alice'][self::NEW]['preview_token_set']);
		$this->assertSame(expected: 'amsterdam', actual: $this->store['bob'][self::NEW]['preview_token_set']);
		$this->assertSame(expected: 'rotterdam', actual: $this->store['carol'][self::NEW]['preview_token_set']);

	}//end testMigratesEveryStoredKeyRegardlessOfItsValue()

	/**
	 * A key this release does not know about still migrates.
	 *
	 * The same property as the test above, on the KEY axis rather than the
	 * value axis: the step enumerates whatever the user actually stored, so a
	 * preference written by a past release — or a future one — moves without
	 * this step being taught its name. A hardcoded key list could not.
	 *
	 * @return void
	 */
	public function testMigratesAKeyTheStepDoesNotKnowAbout(): void {
		$this->store = [
			'alice' => [self::OLD => ['some_future_preference' => 'kept']],
		];

		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		$this->assertSame(expected: 'kept', actual: $this->store['alice'][self::NEW]['some_future_preference']);

	}//end testMigratesAKeyTheStepDoesNotKnowAbout()

	/**
	 * The old rows survive, so a rollback still finds the preferences.
	 *
	 * @return void
	 */
	public function testLeavesTheOldPreferencesIntact(): void {
		$this->store = ['alice' => [self::OLD => ['preview_token_set' => 'utrecht']]];

		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		$this->assertSame(expected: 'utrecht', actual: $this->store['alice'][self::OLD]['preview_token_set']);

	}//end testLeavesTheOldPreferencesIntact()

	/**
	 * A preference the user already set under the new app id wins.
	 *
	 * @return void
	 */
	public function testDoesNotOverwriteAPreferenceAlreadySetUnderTheNewAppId(): void {
		$this->store = [
			'alice' => [
				self::OLD => ['preview_token_set' => 'oud'],
				self::NEW => ['preview_token_set' => 'nieuw'],
			],
		];

		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		$this->assertSame(expected: 'nieuw', actual: $this->store['alice'][self::NEW]['preview_token_set']);

	}//end testDoesNotOverwriteAPreferenceAlreadySetUnderTheNewAppId()

	/**
	 * A second run is a no-op, so re-running the repair is safe.
	 *
	 * @return void
	 */
	public function testIsIdempotent(): void {
		$this->store = ['alice' => [self::OLD => ['preview_token_set' => 'utrecht']]];

		$step = $this->step();
		$step->run($this->createMock(originalClassName: IOutput::class));
		$step->run($this->createMock(originalClassName: IOutput::class));

		$this->assertSame(expected: 'utrecht', actual: $this->store['alice'][self::NEW]['preview_token_set']);

	}//end testIsIdempotent()

	/**
	 * An empty stored preference earns no row under the new app id.
	 *
	 * @return void
	 */
	public function testSkipsPreferencesWithNoStoredValue(): void {
		$this->store = ['alice' => [self::OLD => ['preview_token_set' => '']]];

		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		$this->assertArrayNotHasKey(key: self::NEW, array: $this->store['alice']);

	}//end testSkipsPreferencesWithNoStoredValue()

	/**
	 * A fresh install has nothing to migrate and says so.
	 *
	 * @return void
	 */
	public function testReportsNothingToDoWhenNoUserHasStoredPreferences(): void {
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())
			->method('info')
			->with($this->stringContains(string: 'nothing to do'));

		$this->step(userManager: $this->userManager(userIds: ['alice']))->run($output);

		$this->assertSame(expected: [], actual: $this->store);

	}//end testReportsNothingToDoWhenNoUserHasStoredPreferences()

	/**
	 * One unreadable user does not cost the others their preferences.
	 *
	 * @return void
	 */
	public function testOneUnreadableUserDoesNotStopTheWalk(): void {
		$this->store = [
			'alice' => [self::OLD => ['preview_token_set' => 'utrecht']],
			'bob' => [self::OLD => ['preview_token_set' => 'amsterdam']],
			'carol' => [self::OLD => ['preview_token_set' => 'rotterdam']],
		];

		$this->keysThrowFor = ['bob'];

		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		$this->assertSame(expected: 'utrecht', actual: $this->store['alice'][self::NEW]['preview_token_set']);
		$this->assertSame(expected: 'rotterdam', actual: $this->store['carol'][self::NEW]['preview_token_set']);
		$this->assertArrayNotHasKey(key: self::NEW, array: $this->store['bob']);

	}//end testOneUnreadableUserDoesNotStopTheWalk()

	/**
	 * A failing write is counted and logged, not swallowed.
	 *
	 * @return void
	 */
	public function testLogsAPreferenceItCouldNotMigrate(): void {
		$this->store = ['alice' => [self::OLD => ['preview_token_set' => 'utrecht']]];
		$this->writeThrowsFor = ['alice'];

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())
			->method('warning')
			->with(
				$this->stringContains(string: 'could not migrate one user preference'),
				$this->arrayHasKey(key: 'key')
			);

		$this->step(logger: $logger)->run($this->createMock(originalClassName: IOutput::class));

	}//end testLogsAPreferenceItCouldNotMigrate()

	/**
	 * A broken user backend warns instead of aborting the install.
	 *
	 * The step runs under `<install>`, the only hook that fires on the fresh
	 * install a rename performs. An escaping throw would abort that install and
	 * the app would never enable at all — so failing to enumerate users has to
	 * degrade to a warning.
	 *
	 * @return void
	 */
	public function testUserEnumerationFailureWarnsInsteadOfThrowing(): void {
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())
			->method('warning')
			->with($this->stringContains(string: 'user enumeration failed'));
		$output->expects($this->never())->method('info');

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())
			->method('warning')
			->with($this->stringContains(string: 'could not enumerate users'));

		$step = $this->step(userManager: $this->userManager(throw: true), logger: $logger);
		$step->run($output);

	}//end testUserEnumerationFailureWarnsInsteadOfThrowing()

	/**
	 * The step names both app ids, so the repair output is self-explanatory.
	 *
	 * @return void
	 */
	public function testGetNameNamesBothAppIds(): void {
		$name = $this->step()->getName();

		$this->assertStringContainsString(needle: self::OLD, haystack: $name);
		$this->assertStringContainsString(needle: 'thematiq', haystack: $name);

	}//end testGetNameNamesBothAppIds()

	/**
	 * Golden: the counts, the store and every message of a walk over users that migrate, skip, collide and fail.
	 *
	 * @return void
	 */
	public function testAMixedWalkGolden(): void {
		$this->store = [
			'ann' => [self::OLD => ['token_set' => 'utrecht', 'empty' => '', 'mode' => 'dark']],
			'bob' => [self::OLD => ['token_set' => 'leiden'], self::NEW => ['token_set' => 'kept']],
			'cat' => [self::OLD => ['token_set' => 'x']],
			'dan' => [self::OLD => ['a' => '1']],
		];
		$this->writeThrowsFor = ['cat'];
		$this->keysThrowFor = ['dan'];
		$messages = [];
		$output = $this->createMock(originalClassName: IOutput::class);
		foreach (['info', 'warning'] as $level) {
			$output->method($level)->willReturnCallback(
				static function (string $message) use (&$messages, $level): void {
					$messages[] = 'output ' . $level . ': ' . $message;
				}
			);
		}

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->method('warning')->willReturnCallback(
			static function (string $message, array $context) use (&$messages): void {
				$messages[] = 'log: ' . $message . ' ' . json_encode($context);
			}
		);

		$this->step(logger: $logger)->run($output);

		$this->assertSame(
			expected: [
				'ann' => [self::OLD => ['token_set' => 'utrecht', 'empty' => '', 'mode' => 'dark'], self::NEW => ['token_set' => 'utrecht', 'mode' => 'dark']],
				'bob' => [self::OLD => ['token_set' => 'leiden'], self::NEW => ['token_set' => 'kept']],
				'cat' => [self::OLD => ['token_set' => 'x']],
				'dan' => [self::OLD => ['a' => '1']],
			],
			actual: $this->store
		);
		$this->assertSame(
			expected: [
				'log: Thematiq: could not migrate one user preference; leaving it under the old app id {"key":"token_set","exception":"read-only"}',
				'log: Thematiq: could not enumerate nldesign preference keys for a user; skipping that user {"exception":"unreadable"}',
				'output info: MigrateUserPreferences: migrated 2 preference(s); 1 already set under thematiq; 1 failed.',
			],
			actual: $messages
		);
	}//end testAMixedWalkGolden()
}//end class
