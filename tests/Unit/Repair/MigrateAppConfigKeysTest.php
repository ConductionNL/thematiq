<?php

/**
 * Unit tests for the nldesign -> thematiq app-config migration.
 *
 * This step is the reason a lost setting is not a crash. Nextcloud namespaces
 * `oc_appconfig` by app id, so the rename leaves every stored row unreachable —
 * and because every reader supplies a default, the app reverts to its defaults
 * without a single error or log line. For a theming app that means a themed
 * government instance silently snaps back to stock Nextcloud. These tests
 * therefore assert what the step actually WROTE, not merely that `run()`
 * returned.
 *
 * The IAppConfig double is a mock wired to a real in-memory store rather than
 * one with fixed return values: the step reads the old namespace, reads the new
 * one, then writes, so a stub that always returns the same thing would let a
 * step that never wrote anything pass.
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

use OCA\Thematiq\Repair\MigrateAppConfigKeys;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Covers the copy, the skips, and the throw paths that must not escape.
 */
final class MigrateAppConfigKeysTest extends TestCase {

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
	 * The in-memory app-config store: app id => key => value.
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $store = [];

	/**
	 * Keys whose read throws, to exercise the per-key failure path.
	 *
	 * @var array<int, string>
	 */
	private array $throwOn = [];

	/**
	 * Reset the store between tests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->store = [];
		$this->throwOn = [];

	}//end setUp()

	/**
	 * An IAppConfig mock backed by `$this->store`.
	 *
	 * @return IAppConfig
	 */
	private function appConfig(): IAppConfig {
		$appConfig = $this->createMock(originalClassName: IAppConfig::class);

		$appConfig->method('getKeys')
			->willReturnCallback(
				function (string $app): array {
					return array_keys(($this->store[$app] ?? []));
				}
			);

		$appConfig->method('getValueString')
			->willReturnCallback(
				function (string $app, string $key, string $default = ''): string {
					if (in_array($key, $this->throwOn, strict: true) === true) {
						throw new RuntimeException('unreadable');
					}

					return ($this->store[$app][$key] ?? $default);
				}
			);

		$appConfig->method('setValueString')
			->willReturnCallback(
				function (string $app, string $key, string $value): bool {
					$this->store[$app][$key] = $value;
					return true;
				}
			);

		return $appConfig;
	}//end appConfig()

	/**
	 * Build the step over the in-memory store.
	 *
	 * @param LoggerInterface|null $logger An optional logger to assert against.
	 *
	 * @return MigrateAppConfigKeys
	 */
	private function step(?LoggerInterface $logger = null): MigrateAppConfigKeys {
		return new MigrateAppConfigKeys(
			appConfig: $this->appConfig(),
			logger: ($logger ?? $this->createMock(originalClassName: LoggerInterface::class))
		);

	}//end step()

	/**
	 * A stored value reaches the new namespace.
	 *
	 * `token_set` is the worst case: its readers default to `'nextcloud'`, so
	 * losing it silently reverts a themed government instance to stock
	 * Nextcloud blue rather than failing in any visible way.
	 *
	 * @return void
	 */
	public function testCopiesStoredValuesToTheNewNamespace(): void {
		$this->store = [
			self::OLD => [
				'token_set' => 'rijkshuisstijl',
				'hide_slogan' => '1',
				'email_footer_accessibility_url' => 'https://gemeente.nl/toegankelijkheid',
			],
		];

		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		$this->assertSame(expected: 'rijkshuisstijl', actual: $this->store[self::NEW]['token_set']);
		$this->assertSame(expected: '1', actual: $this->store[self::NEW]['hide_slogan']);
		$this->assertSame(
			expected: 'https://gemeente.nl/toegankelijkheid',
			actual: $this->store[self::NEW]['email_footer_accessibility_url']
		);

	}//end testCopiesStoredValuesToTheNewNamespace()

	/**
	 * Admin-authored data that exists nowhere else survives the rename.
	 *
	 * `custom_token_sets` and `disabled_apps` are not recoverable from any
	 * shipped default: the first holds brands the admin wrote by hand, the
	 * second the apps they deliberately excluded from theming. Losing the
	 * latter does not just reset a setting, it starts theming apps that were
	 * excluded on purpose.
	 *
	 * @return void
	 */
	public function testCopiesAdminAuthoredDataThatHasNoDefault(): void {
		$this->store = [
			self::OLD => [
				'custom_token_sets' => '[{"id":"gemeente-x","label":"Gemeente X"}]',
				'disabled_apps' => '["mail","calendar"]',
				'group_token_sets' => '{"bestuur":"rijkshuisstijl"}',
			],
		];

		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		$this->assertSame(
			expected: '[{"id":"gemeente-x","label":"Gemeente X"}]',
			actual: $this->store[self::NEW]['custom_token_sets']
		);
		$this->assertSame(expected: '["mail","calendar"]', actual: $this->store[self::NEW]['disabled_apps']);
		$this->assertSame(expected: '{"bestuur":"rijkshuisstijl"}', actual: $this->store[self::NEW]['group_token_sets']);

	}//end testCopiesAdminAuthoredDataThatHasNoDefault()

	/**
	 * The old rows survive, so a rollback still finds its configuration.
	 *
	 * @return void
	 */
	public function testLeavesTheOldNamespaceIntact(): void {
		$this->store = [self::OLD => ['token_set' => 'rijkshuisstijl']];

		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		$this->assertSame(expected: 'rijkshuisstijl', actual: $this->store[self::OLD]['token_set']);

	}//end testLeavesTheOldNamespaceIntact()

	/**
	 * Nextcloud's own bookkeeping keys are never copied.
	 *
	 * TRAP 1. `enabled` is the dangerous one: AppManager writes it as type
	 * MIXED, and copying it with setValueString() stores type STRING. The next
	 * `app:enable` then fails with an AppConfigTypeConflictException —
	 * permanently, because the conflict is hit before the app can run anything
	 * that would repair it, so the app can never be enabled again.
	 * `installed_version` is the second trap: copying the old app's value would
	 * make Nextcloud believe `thematiq` is already at that version and skip its
	 * migrations.
	 *
	 * @return void
	 */
	public function testSkipsNextcloudReservedKeys(): void {
		$this->store = [
			self::OLD => [
				'enabled' => 'yes',
				'installed_version' => '1.0.0',
				'types' => 'filesystem',
				'token_set' => 'rijkshuisstijl',
			],
		];

		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		$this->assertArrayNotHasKey(key: 'enabled', array: ($this->store[self::NEW] ?? []));
		$this->assertArrayNotHasKey(key: 'installed_version', array: ($this->store[self::NEW] ?? []));
		$this->assertArrayNotHasKey(key: 'types', array: ($this->store[self::NEW] ?? []));
		$this->assertSame(expected: 'rijkshuisstijl', actual: $this->store[self::NEW]['token_set']);

	}//end testSkipsNextcloudReservedKeys()

	/**
	 * An admin edit made after the rename is never clobbered.
	 *
	 * @return void
	 */
	public function testDoesNotOverwriteAValueAlreadySetUnderTheNewAppId(): void {
		$this->store = [
			self::OLD => ['token_set' => 'oud'],
			self::NEW => ['token_set' => 'nieuw'],
		];

		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		$this->assertSame(expected: 'nieuw', actual: $this->store[self::NEW]['token_set']);

	}//end testDoesNotOverwriteAValueAlreadySetUnderTheNewAppId()

	/**
	 * A second run is a no-op, so re-running the repair is safe.
	 *
	 * @return void
	 */
	public function testIsIdempotent(): void {
		$this->store = [self::OLD => ['token_set' => 'rijkshuisstijl']];

		$step = $this->step();
		$step->run($this->createMock(originalClassName: IOutput::class));
		$step->run($this->createMock(originalClassName: IOutput::class));

		$this->assertSame(expected: 'rijkshuisstijl', actual: $this->store[self::NEW]['token_set']);

	}//end testIsIdempotent()

	/**
	 * An empty stored value earns no row under the new app id.
	 *
	 * @return void
	 */
	public function testSkipsKeysWithNoStoredValue(): void {
		$this->store = [self::OLD => ['primary_color' => '']];

		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		$this->assertArrayNotHasKey(key: 'primary_color', array: ($this->store[self::NEW] ?? []));

	}//end testSkipsKeysWithNoStoredValue()

	/**
	 * A fresh install has nothing to migrate and says so.
	 *
	 * @return void
	 */
	public function testReportsNothingToDoOnAFreshInstall(): void {
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())
			->method('info')
			->with($this->stringContains(string: 'nothing to do'));

		$this->step()->run($output);

		$this->assertSame(expected: [], actual: $this->store);

	}//end testReportsNothingToDoOnAFreshInstall()

	/**
	 * One unreadable key does not abort the install.
	 *
	 * The step is registered under `<install>` — the only hook that fires on
	 * the fresh install the rename actually performs — so an escaping throw
	 * would abort the install and the app would never enable at all. Every
	 * route in the app dies with it. Hence: the throwing key is skipped and the
	 * rest still migrate.
	 *
	 * @return void
	 */
	public function testOneUnreadableKeyDoesNotStopTheOthers(): void {
		$this->store = [
			self::OLD => [
				'token_set' => 'rijkshuisstijl',
				'custom_fonts' => 'boom',
				'hide_slogan' => '1',
			],
		];

		$this->throwOn = ['custom_fonts'];

		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		$this->assertSame(expected: 'rijkshuisstijl', actual: $this->store[self::NEW]['token_set']);
		$this->assertSame(expected: '1', actual: $this->store[self::NEW]['hide_slogan']);
		$this->assertArrayNotHasKey(key: 'custom_fonts', array: $this->store[self::NEW]);

	}//end testOneUnreadableKeyDoesNotStopTheOthers()

	/**
	 * A failing key is logged rather than swallowed silently.
	 *
	 * A migration that loses a value without saying so is the exact failure
	 * mode this step exists to prevent, so the warning is load-bearing.
	 *
	 * @return void
	 */
	public function testLogsAKeyItCouldNotMigrate(): void {
		$this->store = [self::OLD => ['custom_fonts' => 'boom']];
		$this->throwOn = ['custom_fonts'];

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())
			->method('warning')
			->with(
				$this->stringContains(string: 'could not migrate one app config key'),
				$this->arrayHasKey(key: 'key')
			);

		$this->step(logger: $logger)->run($this->createMock(originalClassName: IOutput::class));

	}//end testLogsAKeyItCouldNotMigrate()

	/**
	 * An unreadable old namespace skips the migration instead of throwing.
	 *
	 * @return void
	 */
	public function testUnreadableOldNamespaceIsLoggedAndSkipped(): void {
		$appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$appConfig->method('getKeys')
			->willThrowException(new RuntimeException('no database'));
		$appConfig->expects($this->never())->method('setValueString');

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())
			->method('warning')
			->with($this->stringContains(string: 'could not enumerate nldesign app config keys'));

		$step = new MigrateAppConfigKeys(appConfig: $appConfig, logger: $logger);
		$step->run($this->createMock(originalClassName: IOutput::class));

	}//end testUnreadableOldNamespaceIsLoggedAndSkipped()

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

}//end class
