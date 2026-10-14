<?php

/**
 * Unit tests for TokenSetLicenceService.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use DateTime;
use InvalidArgumentException;
use OCA\Thematiq\Service\AppBrandService;
use OCA\Thematiq\Service\Exception\RestrictedSetInUseException;
use OCA\Thematiq\Service\RestrictedSetGate;
use OCA\Thematiq\Service\RuntimeFile\SetFileReader;
use OCA\Thematiq\Service\ScheduledSwitchStore;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\TokenSetFontAuditService;
use OCA\Thematiq\Service\TokenSetLicenceService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Tests\Unit\Support\RestrictedSetFixture;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * An administrator confirms a restricted set and the confirmation is logged;
 * a withdrawal is refused while the set is applied anywhere.
 */
#[CoversClass(TokenSetLicenceService::class)]
#[CoversClass(RestrictedSetInUseException::class)]
#[UsesClass(RestrictedSetGate::class)]
#[UsesClass(TokenSetService::class)]
#[UsesClass(ScheduledSwitchStore::class)]
#[UsesClass(SetFileReader::class)]
#[UsesClass(TokenSetFontAuditService::class)]
class TokenSetLicenceServiceTest extends TestCase {

	use RestrictedSetFixture;

	/**
	 * The audit entries written, as [action, context].
	 *
	 * @var array<int, array{0: string, 1: array<string, mixed>}>
	 */
	private array $audit = [];

	/**
	 * The log lines written, as [message, context].
	 *
	 * @var array<int, array{0: string, 1: array<string, mixed>}>
	 */
	private array $logged = [];

	/**
	 * The service under test.
	 *
	 * @var TokenSetLicenceService
	 */
	private TokenSetLicenceService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->makeAppDir();
		$config = $this->memoryConfig();

		$audit = $this->createMock(ThemingAuditService::class);
		$audit->method('log')->willReturnCallback(
			function (string $action, array $context = []): void {
				$this->audit[] = [$action, $context];
			}
		);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->method('warning')->willReturnCallback(
			function (string $message, array $context = []): void {
				$this->logged[] = [$message, $context];
			}
		);
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getDateTime')->willReturn(new DateTime('2026-10-12T20:15:00+00:00'));

		$this->service = new TokenSetLicenceService(
			$this->fixtureTokenSets($config),
			$config,
			new ScheduledSwitchStore($config),
			$audit,
			$time,
			$logger
		);
	}//end setUp()

	protected function tearDown(): void {
		$this->removeAppDir();
		parent::tearDown();
	}//end tearDown()

	public function testAConfirmationIsStoredWithWhoAndWhenAndLogged(): void {
		$confirmation = $this->service->confirm('owned', 'ruben');

		$this->assertSame(['uid' => 'ruben', 'at' => '2026-10-12T20:15:00+00:00'], $confirmation);
		$stored = json_decode($this->appValues[RestrictedSetGate::CONFIG_KEY], true);
		$this->assertSame(['owned' => ['uid' => 'ruben', 'at' => '2026-10-12T20:15:00+00:00']], $stored);

		$this->assertCount(1, $this->audit);
		$this->assertSame('restricted_set_confirmed', $this->audit[0][0]);
		$this->assertSame('owned', $this->audit[0][1]['new']);
		$this->assertSame('ruben', $this->audit[0][1]['actor']);
		$this->assertSame('LicenseRef-Voorbeeld', $this->audit[0][1]['licence']);
		$this->assertSame('Gemeente Voorbeeld', $this->audit[0][1]['holder']);

		$this->assertCount(1, $this->logged);
		$this->assertSame(['uid' => 'ruben', 'set' => 'owned', 'app' => 'thematiq'], $this->logged[0][1]);
	}//end testAConfirmationIsStoredWithWhoAndWhenAndLogged()

	public function testASecondConfirmationKeepsTheFirst(): void {
		$this->service->confirm('owned', 'ruben');

		$this->assertSame('ruben', $this->service->confirm('owned', 'someone-else')['uid']);
		$this->assertCount(1, $this->audit);
	}//end testASecondConfirmationKeepsTheFirst()

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function notConfirmable(): array {
		return [
			'an open-licensed set' => ['open', 'ruben'],
			'a set without a declaration' => ['nextcloud', 'ruben'],
			'a set that does not exist' => ['ghost', 'ruben'],
			'nobody named' => ['owned', ''],
		];
	}//end notConfirmable()

	#[DataProvider('notConfirmable')]
	public function testOnlyARestrictedSetIsConfirmedAndOnlyByName(string $set, string $uid): void {
		try {
			$this->service->confirm($set, $uid);
			$this->fail('a confirmation was stored');
		} catch (InvalidArgumentException $e) {
			$this->assertArrayNotHasKey(RestrictedSetGate::CONFIG_KEY, $this->appValues);
			$this->assertSame([], $this->audit);
		}
	}//end testOnlyARestrictedSetIsConfirmedAndOnlyByName()

	/**
	 * @return array<string, array{0: string, 1: string, 2: array{kind: string, id: string}}>
	 */
	public static function places(): array {
		return [
			'the instance' => ['token_set', 'owned', ['kind' => 'instance', 'id' => '']],
			'a group' => ['group_token_sets', '[{"group":"ruimte","tokenSet":"owned"}]', ['kind' => 'group', 'id' => 'ruimte']],
			'an app brand' => [AppBrandService::CONFIG_KEY, '{"collectives":{"tokenSet":"owned"}}', ['kind' => 'app', 'id' => 'collectives']],
			'a planned switch' => ['scheduled_switches', '[{"id":"s1","tokenSet":"owned","status":"planned"}]', ['kind' => 'switch', 'id' => 's1']],
			'the set a running switch returns to' => [
				'scheduled_switches',
				'[{"id":"s2","tokenSet":"open","revertTo":"owned","status":"running"}]',
				['kind' => 'switch', 'id' => 's2'],
			],
		];
	}//end places()

	/**
	 * @param array{kind: string, id: string} $place
	 */
	#[DataProvider('places')]
	public function testAWithdrawalIsRefusedWhileTheSetIsApplied(string $key, string $value, array $place): void {
		$this->service->confirm('owned', 'ruben');
		$this->appValues[$key] = $value;

		try {
			$this->service->withdraw('owned', 'ruben');
			$this->fail('the confirmation was withdrawn while the set is applied');
		} catch (RestrictedSetInUseException $e) {
			$this->assertSame(409, $e->getCode());
			$this->assertSame([$place], $e->getAppliedIn());
		}

		$this->assertArrayHasKey('owned', json_decode($this->appValues[RestrictedSetGate::CONFIG_KEY], true));
		$this->assertCount(1, $this->audit, 'only the confirmation is in the audit trail');
	}//end testAWithdrawalIsRefusedWhileTheSetIsApplied()

	public function testAFinishedSwitchDoesNotHoldTheConfirmation(): void {
		$this->service->confirm('owned', 'ruben');
		$this->appValues['scheduled_switches'] = '[{"id":"s1","tokenSet":"owned","status":"done"},{"id":"s2","tokenSet":"owned","status":"failed"}]';

		$this->service->withdraw('owned', 'ruben');

		$this->assertSame('{}', $this->appValues[RestrictedSetGate::CONFIG_KEY]);
	}//end testAFinishedSwitchDoesNotHoldTheConfirmation()

	public function testAWithdrawalIsLoggedWithTheConfirmationItEnds(): void {
		$this->service->confirm('owned', 'ruben');
		$this->service->withdraw('owned', 'noor');

		$this->assertSame('restricted_set_confirmation_withdrawn', $this->audit[1][0]);
		$this->assertSame('owned', $this->audit[1][1]['old']);
		$this->assertSame('noor', $this->audit[1][1]['actor']);
		$this->assertSame('ruben', $this->audit[1][1]['confirmedBy']);
		$this->assertSame('2026-10-12T20:15:00+00:00', $this->audit[1][1]['confirmedAt']);
		$this->assertCount(2, $this->logged);

		$this->service->withdraw('owned', 'noor');
		$this->assertCount(2, $this->audit, 'withdrawing what is not confirmed writes nothing');
	}//end testAWithdrawalIsLoggedWithTheConfirmationItEnds()

	public function testASetAppliedWithoutAConfirmationAwaitsOne(): void {
		$this->assertSame([], $this->service->awaitingConfirmation());

		$this->appValues['token_set'] = 'owned';
		$this->appValues['group_token_sets'] = '[{"group":"ruimte","tokenSet":"owned-two"}]';
		$this->assertSame(
			[
				['tokenSet' => 'owned', 'appliedIn' => [['kind' => 'instance', 'id' => '']], 'since' => ''],
				['tokenSet' => 'owned-two', 'appliedIn' => [['kind' => 'group', 'id' => 'ruimte']], 'since' => ''],
			],
			$this->service->awaitingConfirmation()
		);

		$this->service->confirm('owned', 'ruben');
		$this->assertSame(['owned-two'], array_column($this->service->awaitingConfirmation(), 'tokenSet'));
	}//end testASetAppliedWithoutAConfirmationAwaitsOne()

	public function testTheSettingsPageGetsEveryRestrictedSetAndItsLicence(): void {
		$this->service->confirm('owned', 'ruben');

		$sets = array_column($this->service->restrictedSets(), null, 'id');
		$this->assertSame(['owned-two', 'owned', 'sloppy'], array_keys($sets), 'sorted by name');
		$this->assertSame('Gemeente Voorbeeld', $sets['owned']['name']);
		$this->assertSame('@example/voorbeeld-design-tokens', $sets['owned']['licence']['package']);
		$this->assertSame('ruben', $sets['owned']['confirmation']['uid']);
		$this->assertNull($sets['owned-two']['confirmation']);

		$described = $this->service->describe('owned');
		$this->assertSame("All rights reserved. Use is limited to Gemeente Voorbeeld.\n", $described['text']);
		$this->assertSame('1.0.3', $described['licence']['version']);
		$this->assertSame([], $described['appliedIn']);
		$this->assertNull($this->service->describe('nextcloud'));
		$this->assertFalse($this->service->describe('open')['licence']['restricted']);
	}//end testTheSettingsPageGetsEveryRestrictedSetAndItsLicence()
}//end class
