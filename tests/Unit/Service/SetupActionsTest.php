<?php

/**
 * Tests for the first-time setup's apply and skip actions.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-nothing-changes-until-apply-req-tqfs-003
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\Thematiq\Kernel\Contracts\ThemingAuditServiceInterface;
use OCA\Thematiq\Kernel\Contracts\TokenSetLicenceServiceInterface;
use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\AppMenuSettings;
use OCA\Thematiq\Service\Exception\RestrictedSetNotConfirmedException;
use OCA\Thematiq\Service\LayoutOptionsService;
use OCA\Thematiq\Service\RestrictedSetGate;
use OCA\Thematiq\Service\ScheduledCoreThemingSync;
use OCA\Thematiq\Service\SetupActions;
use OCA\Thematiq\Service\TokenSetService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Apply checks the whole request first, writes each part, and completes only when every part was applied.
 */
class SetupActionsTest extends TestCase {

	/**
	 * The app values of thematiq.
	 *
	 * @var array<string, string>
	 */
	private array $store = [];

	/**
	 * The audit entries written, as [action, context].
	 *
	 * @var array<int, array{0: string, 1: array<string, mixed>}>
	 */
	private array $audit = [];

	/**
	 * The sets switched to.
	 *
	 * @var array<int, string>
	 */
	private array $switched = [];

	/**
	 * The licence confirmations stored.
	 *
	 * @var array<int, string>
	 */
	private array $confirmed = [];

	/**
	 * The core theming sync double.
	 *
	 * @var ScheduledCoreThemingSync&MockObject
	 */
	private ScheduledCoreThemingSync&MockObject $coreSync;

	/**
	 * The layout double.
	 *
	 * @var LayoutOptionsService&MockObject
	 */
	private LayoutOptionsService&MockObject $layout;

	/**
	 * Build the actions over in-memory doubles.
	 *
	 * @param array<int, string> $restricted The restricted sets.
	 * @param array<int, string> $unconfirmed The restricted sets without a confirmation.
	 *
	 * @return SetupActions The actions.
	 */
	private function make(array $restricted = [], array $unconfirmed = []): SetupActions {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->store[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->store[$key] = (string)$value;
			}
		);
		$config->method('deleteAppValue')->willReturnCallback(
			function (string $app, string $key): void {
				unset($this->store[$key]);
			}
		);

		$gate = $this->createMock(originalClassName: RestrictedSetGate::class);
		$gate->method('isRestricted')->willReturnCallback(fn (string $tokenSetId) => in_array($tokenSetId, $restricted, true));
		$gate->method('assertMayApply')->willReturnCallback(
			function (string $tokenSetId) use ($unconfirmed): void {
				if (in_array($tokenSetId, $unconfirmed, true) === true) {
					throw new RestrictedSetNotConfirmedException($tokenSetId);
				}
			}
		);
		$tokenSets = $this->createMock(originalClassName: TokenSetService::class);
		$tokenSets->method('isValidTokenSet')->willReturnCallback(fn (string $tokenSetId) => in_array($tokenSetId, ['nextcloud', 'amsterdam', 'zuiddrecht'], true));
		$tokenSets->method('licenceGate')->willReturn($gate);

		$active = $this->createMock(originalClassName: ActiveTokenSetService::class);
		$active->method('getActive')->willReturn('nextcloud');
		$active->method('switchTo')->willReturnCallback(
			function (string $tokenSet): string {
				$this->switched[] = $tokenSet;
				$this->store['token_set'] = $tokenSet;
				return 'nextcloud';
			}
		);

		$licences = $this->createMock(originalClassName: TokenSetLicenceServiceInterface::class);
		$licences->method('confirm')->willReturnCallback(
			function (string $tokenSetId, string $uid): array {
				$this->confirmed[] = $tokenSetId . ':' . $uid;
				return [];
			}
		);

		$this->coreSync = $this->createMock(originalClassName: ScheduledCoreThemingSync::class);
		$this->layout = $this->createMock(originalClassName: LayoutOptionsService::class);
		$this->layout->method('resolve')->willReturn('default');
		$this->layout->method('setOption')->willReturnCallback(
			function (string $key, string $value): string {
				$this->store[$key] = $value;
				return '';
			}
		);

		$audit = $this->createMock(originalClassName: ThemingAuditServiceInterface::class);
		$audit->method('log')->willReturnCallback(
			function (string $action, array $context = []): void {
				$this->audit[] = [$action, $context];
			}
		);
		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('getTime')->willReturn(1795786320);

		return new SetupActions(
			config: $config,
			tokenSets: $tokenSets,
			active: $active,
			licences: $licences,
			coreSync: $this->coreSync,
			layout: $this->layout,
			menu: new AppMenuSettings(config: $config),
			audit: $audit,
			time: $time,
		);
	}//end make()

	/**
	 * A full apply writes every part through its own path, then completes.
	 *
	 * @return void
	 */
	public function testApplyWritesEveryPartThenCompletes(): void {
		$actions = $this->make();
		$this->coreSync->expects($this->once())->method('sync')->with('amsterdam')->willReturn(['primary_color', 'logo']);

		$result = $actions->apply(
			request: [
				'tokenSet' => 'amsterdam',
				'nextcloudThemeFollows' => true,
				'loginStyle' => 'house-style',
				'hideSlogan' => true,
				'menu' => ['enabled' => true, 'columns' => 3, 'payoff' => false],
			],
			uid: 'admin'
		);

		$this->assertSame(expected: ['house-style', 'nextcloud-theme', 'login-page', 'apps-menu'], actual: $result['applied']);
		$this->assertSame(expected: [], actual: $result['failed']);
		$this->assertTrue(condition: $result['completed']);
		$this->assertSame(expected: ['amsterdam'], actual: $this->switched);
		$this->assertSame(expected: '1', actual: $this->store['core_theming_follows_set']);
		$this->assertSame(expected: '1', actual: $this->store['hide_slogan']);
		$this->assertSame(expected: '"workplace"', actual: $this->store['menu.style']);
		$this->assertSame(expected: '3', actual: $this->store['menu.columns']);
		$this->assertSame(expected: 'false', actual: $this->store['menu.payoff']);
		$this->assertSame(expected: 'workplace', actual: $this->store['header_style']);
		$this->assertSame(expected: ['by' => 'admin', 'at' => gmdate('Y-m-d\TH:i:s\Z', 1795786320)], actual: json_decode($this->store['setup.completed'], true));
		$this->assertContains(needle: 'setup_completed', haystack: array_column($this->audit, 0));
		$this->assertContains(needle: 'theming_sync_applied', haystack: array_column($this->audit, 0));
	}//end testApplyWritesEveryPartThenCompletes()

	/**
	 * A wrong value refuses the whole request and stores nothing.
	 *
	 * @return void
	 */
	public function testAWrongValueStoresNothing(): void {
		$actions = $this->make();

		try {
			$actions->apply(request: ['tokenSet' => 'zuiddrecht', 'menu' => ['columns' => 9]], uid: 'admin');
			$this->fail('A wrong value was accepted');
		} catch (InvalidArgumentException $e) {
			$this->assertSame(expected: 'menu.columns', actual: $e->getMessage());
		}

		$this->assertSame(expected: [], actual: $this->store);
		$this->assertSame(expected: [], actual: $this->switched);
	}//end testAWrongValueStoresNothing()

	/**
	 * Unknown keys, unknown sets and wrong types are named.
	 *
	 * @return void
	 */
	public function testUnknownKeysAndWrongTypesAreNamed(): void {
		$actions = $this->make();
		$cases = [
			'seed' => ['tokenSet' => 'amsterdam', 'seed' => true],
			'tokenSet' => ['tokenSet' => 'nope'],
			'hideSlogan' => ['tokenSet' => 'amsterdam', 'hideSlogan' => 'yes'],
			'loginStyle' => ['tokenSet' => 'amsterdam', 'loginStyle' => 'dark'],
			'menu.style' => ['tokenSet' => 'amsterdam', 'menu' => ['style' => 'classic']],
		];
		foreach ($cases as $key => $request) {
			try {
				$actions->check(request: $request);
				$this->fail('Accepted ' . $key);
			} catch (InvalidArgumentException $e) {
				$this->assertSame(expected: $key, actual: $e->getMessage());
			}
		}
	}//end testUnknownKeysAndWrongTypesAreNamed()

	/**
	 * An unconfirmed restricted set is refused without the confirmation, and confirmed then applied with it.
	 *
	 * @return void
	 */
	public function testTheLicenceGate(): void {
		$actions = $this->make(restricted: ['zuiddrecht'], unconfirmed: ['zuiddrecht']);

		try {
			$actions->apply(request: ['tokenSet' => 'zuiddrecht'], uid: 'admin');
			$this->fail('An unconfirmed restricted set was applied');
		} catch (RestrictedSetNotConfirmedException $e) {
			$this->assertSame(expected: [], actual: $this->switched);
		}

		$result = $actions->apply(request: ['tokenSet' => 'zuiddrecht', 'confirmRestricted' => true], uid: 'admin');
		$this->assertSame(expected: ['zuiddrecht:admin'], actual: $this->confirmed);
		$this->assertSame(expected: 'licence', actual: $result['applied'][0]);
		$this->assertSame(expected: ['zuiddrecht'], actual: $this->switched);
	}//end testTheLicenceGate()

	/**
	 * A failed part is reported, the others still apply, and completion waits.
	 *
	 * @return void
	 */
	public function testAFailedPartIsReportedAndCompletionWaits(): void {
		$actions = $this->make();
		$this->coreSync->method('sync')->willThrowException(new RuntimeException('disk full'));

		$result = $actions->apply(request: ['tokenSet' => 'amsterdam', 'nextcloudThemeFollows' => true, 'menu' => ['enabled' => false]], uid: 'admin');

		$this->assertSame(expected: ['nextcloud-theme'], actual: $result['failed']);
		$this->assertSame(expected: ['house-style', 'login-page', 'apps-menu'], actual: $result['applied']);
		$this->assertFalse(condition: $result['completed']);
		$this->assertArrayNotHasKey(key: 'setup.completed', array: $this->store);
	}//end testAFailedPartIsReportedAndCompletionWaits()

	/**
	 * The Nextcloud login removes the login context; following the house style keeps an empty list.
	 *
	 * @return void
	 */
	public function testTheNextcloudLoginRemovesTheLoginContext(): void {
		$actions = $this->make();
		$actions->apply(request: ['tokenSet' => 'amsterdam', 'loginStyle' => 'nextcloud'], uid: 'admin');
		$this->assertSame(expected: ['user', 'guest', 'public', 'error'], actual: json_decode($this->store['themed_contexts'], true));

		$actions->apply(request: ['tokenSet' => 'amsterdam', 'loginStyle' => 'house-style'], uid: 'admin');
		$this->assertSame(expected: ['user', 'guest', 'public', 'error', 'login'], actual: json_decode($this->store['themed_contexts'], true));
	}//end testTheNextcloudLoginRemovesTheLoginContext()

	/**
	 * The menu off stores classic alone and leaves the top bar.
	 *
	 * @return void
	 */
	public function testTheMenuOffStoresClassicAlone(): void {
		$actions = $this->make();
		$this->layout->expects($this->never())->method('setOption');

		$actions->apply(request: ['tokenSet' => 'nextcloud', 'menu' => ['enabled' => false]], uid: 'admin');

		$this->assertSame(expected: '"classic"', actual: $this->store['menu.style']);
		$this->assertArrayNotHasKey(key: 'menu.columns', array: $this->store);
		$this->assertArrayNotHasKey(key: 'header_style', array: $this->store);
	}//end testTheMenuOffStoresClassicAlone()

	/**
	 * Skip stores who and when, and nothing else.
	 *
	 * @return void
	 */
	public function testSkipChangesNothingElse(): void {
		$actions = $this->make();
		$actions->skip(uid: 'noor');

		$this->assertSame(expected: ['setup.skipped'], actual: array_keys($this->store));
		$this->assertSame(expected: 'noor', actual: json_decode($this->store['setup.skipped'], true)['by']);
		$this->assertSame(expected: [['setup_skipped', []]], actual: $this->audit);
	}//end testSkipChangesNothingElse()
}//end class
