<?php

/**
 * Unit tests for ActiveTokenSetService, the one token set write path the
 * dropdown and the scheduled switch job share.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/scheduled-switch/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\CoreThemingFollow;
use OCA\Thematiq\Service\ScheduledCoreThemingSync;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\TokenSetService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

/**
 * The write validates, stores and audits; an invalid set writes and audits nothing.
 */
class ActiveTokenSetServiceTest extends TestCase {

	/**
	 * In-memory app config.
	 *
	 * @var array<string, string>
	 */
	private array $store = ['token_set' => 'rijkshuisstijl'];

	/**
	 * Build the service with a config double over $store.
	 *
	 * @param bool $valid What isValidTokenSet() answers.
	 * @param ThemingAuditService $audit The audit double.
	 *
	 * @return ActiveTokenSetService
	 */
	private function make(bool $valid, ThemingAuditService $audit): ActiveTokenSetService {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->store[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->store[$key] = $value;
			}
		);

		$tokenSets = $this->createMock(originalClassName: TokenSetService::class);
		$tokenSets->method('isValidTokenSet')->willReturn($valid);

		return new ActiveTokenSetService(config: $config, tokenSets: $tokenSets, auditService: $audit);
	}//end make()

	/**
	 * A valid switch stores the set, returns the previous one and audits it
	 * with the caller's action and context.
	 *
	 * @return void
	 */
	public function testSwitchStoresAuditsAndReturnsThePreviousSet(): void {
		$audit = $this->createMock(originalClassName: ThemingAuditService::class);
		$audit->expects($this->once())->method('log')->with(
			'scheduled_switch_applied',
			['old' => 'rijkshuisstijl', 'new' => 'amsterdam', 'actor' => 'system']
		);

		$service = $this->make(valid: true, audit: $audit);
		$previous = $service->switchTo(tokenSet: 'amsterdam', auditAction: 'scheduled_switch_applied', auditContext: ['actor' => 'system']);

		$this->assertSame(expected: 'rijkshuisstijl', actual: $previous);
		$this->assertSame(expected: 'amsterdam', actual: $this->store['token_set']);
		$this->assertSame(expected: 'amsterdam', actual: $service->getActive());
	}//end testSwitchStoresAuditsAndReturnsThePreviousSet()

	/**
	 * An invalid set throws and writes nothing.
	 *
	 * @return void
	 */
	public function testInvalidSetWritesNothing(): void {
		$audit = $this->createMock(originalClassName: ThemingAuditService::class);
		$audit->expects($this->never())->method('log');

		$service = $this->make(valid: false, audit: $audit);

		try {
			$service->switchTo(tokenSet: 'bestaat-niet');
			$this->fail(message: 'An invalid set was accepted.');
		} catch (\InvalidArgumentException $e) {
			$this->assertSame(expected: 'rijkshuisstijl', actual: $this->store['token_set']);
		}
	}//end testInvalidSetWritesNothing()

	/**
	 * A service whose Nextcloud theme follows the set, over the in-memory store.
	 *
	 * @param ScheduledCoreThemingSync $coreSync The core theming sync double.
	 * @param ThemingAuditService $audit The audit double.
	 *
	 * @return ActiveTokenSetService The service.
	 */
	private function makeFollowing(ScheduledCoreThemingSync $coreSync, ThemingAuditService $audit): ActiveTokenSetService {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->store[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->store[$key] = $value;
			}
		);
		$tokenSets = $this->createMock(originalClassName: TokenSetService::class);
		$tokenSets->method('isValidTokenSet')->willReturn(true);

		return new ActiveTokenSetService(
			config: $config,
			tokenSets: $tokenSets,
			auditService: $audit,
			follow: new CoreThemingFollow(config: $config, coreSync: $coreSync, auditService: $audit)
		);
	}//end makeFollowing()

	/**
	 * While `core_theming_follows_set` is 1, every switch also applies the set to Nextcloud's theme.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-nextcloud-theme-step-keeps-nextclouds-theme-in-line-with-the-set-req-tqfs-008
	 */
	public function testAFollowingThemeIsSyncedOnEverySwitch(): void {
		$this->store['core_theming_follows_set'] = '1';
		$coreSync = $this->createMock(originalClassName: ScheduledCoreThemingSync::class);
		$coreSync->expects($this->once())->method('sync')->with('amsterdam')->willReturn(['primary_color']);
		$actions = [];
		$audit = $this->createMock(originalClassName: ThemingAuditService::class);
		$audit->method('log')->willReturnCallback(
			function (string $action, array $context = []) use (&$actions): void {
				$actions[] = $action;
			}
		);

		$this->makeFollowing(coreSync: $coreSync, audit: $audit)->switchTo(tokenSet: 'amsterdam', auditContext: ['actor' => 'cli']);

		$this->assertSame(expected: ['token_set_changed', 'theming_sync_applied'], actual: $actions);
	}//end testAFollowingThemeIsSyncedOnEverySwitch()

	/**
	 * Without the value, a switch leaves Nextcloud's theme alone.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-nextcloud-theme-step-keeps-nextclouds-theme-in-line-with-the-set-req-tqfs-008
	 */
	public function testANotFollowingThemeIsLeftAlone(): void {
		$coreSync = $this->createMock(originalClassName: ScheduledCoreThemingSync::class);
		$coreSync->expects($this->never())->method('sync');

		$this->makeFollowing(coreSync: $coreSync, audit: $this->createMock(originalClassName: ThemingAuditService::class))->switchTo(tokenSet: 'amsterdam');

		$this->assertSame(expected: 'amsterdam', actual: $this->store['token_set']);
	}//end testANotFollowingThemeIsLeftAlone()

	/**
	 * Turning following on stores 1 and applies the active set now; off stores 0 and syncs nothing.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-settings-page-switches-nextclouds-theme-in-line-with-the-set-req-tqfs-013
	 */
	public function testTurningFollowingOnAppliesTheActiveSet(): void {
		$coreSync = $this->createMock(originalClassName: ScheduledCoreThemingSync::class);
		$coreSync->expects($this->once())->method('sync')->with('rijkshuisstijl')->willReturn(['primary_color', 'logo']);
		$service = $this->makeFollowing(coreSync: $coreSync, audit: $this->createMock(originalClassName: ThemingAuditService::class));

		$this->assertSame(expected: ['primary_color', 'logo'], actual: $service->setNextcloudFollows(follows: true));
		$this->assertSame(expected: '1', actual: $this->store['core_theming_follows_set']);
		$this->assertTrue(condition: $service->nextcloudFollows());

		$this->assertSame(expected: [], actual: $service->setNextcloudFollows(follows: false));
		$this->assertSame(expected: '0', actual: $this->store['core_theming_follows_set']);
	}//end testTurningFollowingOnAppliesTheActiveSet()

	/**
	 * With Nextcloud default active, turning following on only stores the value.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-settings-page-switches-nextclouds-theme-in-line-with-the-set-req-tqfs-013
	 */
	public function testFollowingOnNextcloudDefaultOnlyStores(): void {
		$this->store['token_set'] = 'nextcloud';
		$coreSync = $this->createMock(originalClassName: ScheduledCoreThemingSync::class);
		$coreSync->expects($this->never())->method('sync');
		$service = $this->makeFollowing(coreSync: $coreSync, audit: $this->createMock(originalClassName: ThemingAuditService::class));

		$this->assertSame(expected: [], actual: $service->setNextcloudFollows(follows: true));
		$this->assertSame(expected: '1', actual: $this->store['core_theming_follows_set']);
	}//end testFollowingOnNextcloudDefaultOnlyStores()
}//end class
