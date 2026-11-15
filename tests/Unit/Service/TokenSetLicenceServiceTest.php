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
use OCA\Thematiq\Service\CssDeclarationScanner;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\Exception\RestrictedSetInUseException;
use OCA\Thematiq\Service\RestrictedSetGate;
use OCA\Thematiq\Service\RuntimeFile\SetFileReader;
use OCA\Thematiq\Service\ScheduledSwitchStore;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\TokenSetAudits;
use OCA\Thematiq\Service\TokenSetFiles;
use OCA\Thematiq\Service\TokenSetFontAuditService;
use OCA\Thematiq\Service\TokenSetLicenceService;
use OCA\Thematiq\Service\TokenSetRuntime;
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
#[UsesClass(TokenSetAudits::class)]
#[UsesClass(TokenSetFiles::class)]
#[UsesClass(TokenSetRuntime::class)]
#[UsesClass(ScheduledSwitchStore::class)]
#[UsesClass(SetFileReader::class)]
#[UsesClass(TokenSetFontAuditService::class)]
#[UsesClass(CssParserService::class)]
#[UsesClass(CssDeclarationScanner::class)]
#[UsesClass(\OCA\Thematiq\Service\IndexedJsonManifest::class)]
#[UsesClass(\OCA\Thematiq\Service\ListedTokenSet::class)]
#[UsesClass(\OCA\Thematiq\Service\RuntimeFile\LocalFile::class)]
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

	/**
	 * Set up each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->makeAppDir();
		$config = $this->memoryConfig();

		$audit = $this->createMock(originalClassName: ThemingAuditService::class);
		$audit->method('log')->willReturnCallback(
			function (string $action, array $context = []): void {
				$this->audit[] = [$action, $context];
			}
		);
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->method('warning')->willReturnCallback(
			function (string $message, array $context = []): void {
				$this->logged[] = [$message, $context];
			}
		);
		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('getDateTime')->willReturn(new DateTime('2026-10-12T20:15:00+00:00'));

		$this->service = new TokenSetLicenceService(
			tokenSets: $this->fixtureTokenSets(config: $config),
			config: $config,
			switches: new ScheduledSwitchStore(config: $config),
			audit: $audit,
			time: $time,
			logger: $logger
		);
	}//end setUp()

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->removeAppDir();
		parent::tearDown();
	}//end tearDown()

	/**
	 * A confirmation is stored with who and when and logged.
	 *
	 * @return void
	 */
	public function testAConfirmationIsStoredWithWhoAndWhenAndLogged(): void {
		$confirmation = $this->service->confirm('owned', 'ruben');

		$this->assertSame(expected: ['uid' => 'ruben', 'at' => '2026-10-12T20:15:00+00:00'], actual: $confirmation);
		$stored = json_decode($this->appValues[RestrictedSetGate::CONFIG_KEY], true);
		$this->assertSame(expected: ['owned' => ['uid' => 'ruben', 'at' => '2026-10-12T20:15:00+00:00']], actual: $stored);

		$this->assertCount(expectedCount: 1, haystack: $this->audit);
		$this->assertSame(expected: 'restricted_set_confirmed', actual: $this->audit[0][0]);
		$this->assertSame(expected: 'owned', actual: $this->audit[0][1]['new']);
		$this->assertSame(expected: 'ruben', actual: $this->audit[0][1]['actor']);
		$this->assertSame(expected: 'LicenseRef-Voorbeeld', actual: $this->audit[0][1]['licence']);
		$this->assertSame(expected: 'Gemeente Voorbeeld', actual: $this->audit[0][1]['holder']);

		$this->assertCount(expectedCount: 1, haystack: $this->logged);
		$this->assertSame(expected: ['uid' => 'ruben', 'set' => 'owned', 'app' => 'thematiq'], actual: $this->logged[0][1]);
	}//end testAConfirmationIsStoredWithWhoAndWhenAndLogged()

	/**
	 * A second confirmation keeps the first.
	 *
	 * @return void
	 */
	public function testASecondConfirmationKeepsTheFirst(): void {
		$this->service->confirm('owned', 'ruben');

		$this->assertSame(expected: 'ruben', actual: $this->service->confirm('owned', 'someone-else')['uid']);
		$this->assertCount(expectedCount: 1, haystack: $this->audit);
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

	/**
	 * Only a restricted set is confirmed and only by name.
	 *
	 * @param string $set The set.
	 * @param string $uid The uid.
	 * @return void
	 */
	#[DataProvider('notConfirmable')]
	public function testOnlyARestrictedSetIsConfirmedAndOnlyByName(string $set, string $uid): void {
		try {
			$this->service->confirm($set, $uid);
			$this->fail(message: 'a confirmation was stored');
		} catch (InvalidArgumentException $e) {
			$this->assertArrayNotHasKey(key: RestrictedSetGate::CONFIG_KEY, array: $this->appValues);
			$this->assertSame(expected: [], actual: $this->audit);
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
	 * @param string $key The key.
	 * @param string $value The value.
	 *
	 * @return void
	 */
	#[DataProvider('places')]
	public function testAWithdrawalIsRefusedWhileTheSetIsApplied(string $key, string $value, array $place): void {
		$this->service->confirm('owned', 'ruben');
		$this->appValues[$key] = $value;

		try {
			$this->service->withdraw('owned', 'ruben');
			$this->fail(message: 'the confirmation was withdrawn while the set is applied');
		} catch (RestrictedSetInUseException $e) {
			$this->assertSame(expected: 409, actual: $e->getCode());
			$this->assertSame(expected: [$place], actual: $e->getAppliedIn());
		}

		$this->assertArrayHasKey(key: 'owned', array: json_decode($this->appValues[RestrictedSetGate::CONFIG_KEY], true));
		$this->assertCount(expectedCount: 1, haystack: $this->audit, message: 'only the confirmation is in the audit trail');
	}//end testAWithdrawalIsRefusedWhileTheSetIsApplied()

	/**
	 * A finished switch does not hold the confirmation.
	 *
	 * @return void
	 */
	public function testAFinishedSwitchDoesNotHoldTheConfirmation(): void {
		$this->service->confirm('owned', 'ruben');
		$this->appValues['scheduled_switches'] = '[{"id":"s1","tokenSet":"owned","status":"done"},{"id":"s2","tokenSet":"owned","status":"failed"}]';

		$this->service->withdraw('owned', 'ruben');

		$this->assertSame(expected: '{}', actual: $this->appValues[RestrictedSetGate::CONFIG_KEY]);
	}//end testAFinishedSwitchDoesNotHoldTheConfirmation()

	/**
	 * A withdrawal is logged with the confirmation it ends.
	 *
	 * @return void
	 */
	public function testAWithdrawalIsLoggedWithTheConfirmationItEnds(): void {
		$this->service->confirm('owned', 'ruben');
		$this->service->withdraw('owned', 'noor');

		$this->assertSame(expected: 'restricted_set_confirmation_withdrawn', actual: $this->audit[1][0]);
		$this->assertSame(expected: 'owned', actual: $this->audit[1][1]['old']);
		$this->assertSame(expected: 'noor', actual: $this->audit[1][1]['actor']);
		$this->assertSame(expected: 'ruben', actual: $this->audit[1][1]['confirmedBy']);
		$this->assertSame(expected: '2026-10-12T20:15:00+00:00', actual: $this->audit[1][1]['confirmedAt']);
		$this->assertCount(expectedCount: 2, haystack: $this->logged);

		$this->service->withdraw('owned', 'noor');
		$this->assertCount(expectedCount: 2, haystack: $this->audit, message: 'withdrawing what is not confirmed writes nothing');
	}//end testAWithdrawalIsLoggedWithTheConfirmationItEnds()

	/**
	 * A set applied without a confirmation awaits one.
	 *
	 * @return void
	 */
	public function testASetAppliedWithoutAConfirmationAwaitsOne(): void {
		$this->assertSame(expected: [], actual: $this->service->awaitingConfirmation());

		$this->appValues['token_set'] = 'owned';
		$this->appValues['group_token_sets'] = '[{"group":"ruimte","tokenSet":"owned-two"}]';
		$this->assertSame(
			expected: [
				['tokenSet' => 'owned', 'appliedIn' => [['kind' => 'instance', 'id' => '']], 'since' => ''],
				['tokenSet' => 'owned-two', 'appliedIn' => [['kind' => 'group', 'id' => 'ruimte']], 'since' => ''],
			],
			actual: $this->service->awaitingConfirmation()
		);

		$this->service->confirm('owned', 'ruben');
		$this->assertSame(expected: ['owned-two'], actual: array_column($this->service->awaitingConfirmation(), 'tokenSet'));
	}//end testASetAppliedWithoutAConfirmationAwaitsOne()

	/**
	 * The settings page gets every restricted set and its licence.
	 *
	 * @return void
	 */
	public function testTheSettingsPageGetsEveryRestrictedSetAndItsLicence(): void {
		$this->service->confirm('owned', 'ruben');

		$sets = array_column($this->service->restrictedSets(), null, 'id');
		$this->assertSame(expected: ['owned-two', 'owned', 'sloppy'], actual: array_keys($sets), message: 'sorted by name');
		$this->assertSame(expected: 'Gemeente Voorbeeld', actual: $sets['owned']['name']);
		$this->assertSame(expected: '@example/voorbeeld-design-tokens', actual: $sets['owned']['licence']['package']);
		$this->assertSame(expected: 'ruben', actual: $sets['owned']['confirmation']['uid']);
		$this->assertNull(actual: $sets['owned-two']['confirmation']);

		$described = $this->service->describe('owned');
		$this->assertSame(expected: "All rights reserved. Use is limited to Gemeente Voorbeeld.\n", actual: $described['text']);
		$this->assertSame(expected: '1.0.3', actual: $described['licence']['version']);
		$this->assertSame(expected: [], actual: $described['appliedIn']);
		$this->assertNull(actual: $this->service->describe('nextcloud'));
		$this->assertFalse(condition: $this->service->describe('open')['licence']['restricted']);
	}//end testTheSettingsPageGetsEveryRestrictedSetAndItsLicence()
	/**
	 * Golden: confirming, the refusals, every place that holds a confirmation and the withdrawal, with their records.
	 *
	 * @return void
	 */
	public function testTheConfirmAndWithdrawGolden(): void {
		$this->appValues[TokenSetLicenceService::AWAITING_KEY] = '{"owned":"2026-01-01","owned-two":"2026-02-02"}';
		$this->assertSame(expected: ['uid' => 'ruben', 'at' => '2026-10-12T20:15:00+00:00'], actual: $this->service->confirm('owned', 'ruben'));
		$this->assertSame(expected: '{"owned-two":"2026-02-02"}', actual: $this->appValues[TokenSetLicenceService::AWAITING_KEY]);
		$this->assertSame(
			expected: [['restricted_set_confirmed', ['old' => null, 'new' => 'owned', 'actor' => 'ruben', 'licence' => 'LicenseRef-Voorbeeld', 'holder' => 'Gemeente Voorbeeld']]],
			actual: $this->audit
		);
		$this->assertSame(
			expected: [['thematiq: {uid} confirmed that this organisation may use the restricted token set {set}', ['uid' => 'ruben', 'set' => 'owned', 'app' => 'thematiq']]],
			actual: $this->logged
		);

		$refused = [];
		foreach ([['open', 'ruben'], ['ghost', 'ruben'], ['owned-two', '']] as [$set, $uid]) {
			try {
				$this->service->confirm($set, $uid);
			} catch (InvalidArgumentException $e) {
				$refused[] = $e->getMessage();
			}
		}

		$this->assertSame(expected: ['Not a restricted token set: open', 'Not a restricted token set: ghost', 'Not a restricted token set: owned-two'], actual: $refused);

		$this->appValues['token_set'] = 'owned';
		$this->appValues['group_token_sets'] = '[{"group":"ruimte","tokenSet":"owned"},{"tokenSet":"owned"},"junk",{"group":"other","tokenSet":"open"}]';
		$this->appValues[AppBrandService::CONFIG_KEY] = '{"collectives":{"tokenSet":"owned"},"deck":{"tokenSet":"open"},"files":"owned"}';
		$this->appValues['scheduled_switches'] = '[{"id":"s1","tokenSet":"owned","status":"planned"},{"tokenSet":"open","revertTo":"owned","status":"running"},{"id":"s3","tokenSet":"owned"}]';
		$this->assertSame(
			expected: [
				['kind' => 'instance', 'id' => ''],
				['kind' => 'group', 'id' => 'ruimte'],
				['kind' => 'group', 'id' => '1'],
				['kind' => 'app', 'id' => 'collectives'],
				['kind' => 'switch', 'id' => 's1'],
				['kind' => 'switch', 'id' => ''],
			],
			actual: $this->service->appliedIn('owned')
		);
		try {
			$this->service->withdraw('owned', 'noor');
			$this->fail(message: 'withdrawn while applied');
		} catch (RestrictedSetInUseException $e) {
			$this->assertCount(expectedCount: 6, haystack: $e->getAppliedIn());
		}

		unset($this->appValues['token_set'], $this->appValues['group_token_sets'], $this->appValues[AppBrandService::CONFIG_KEY], $this->appValues['scheduled_switches']);
		$this->service->withdraw('owned', 'noor');
		$this->assertSame(
			expected: ['restricted_set_confirmation_withdrawn', ['old' => 'owned', 'new' => null, 'actor' => 'noor', 'confirmedBy' => 'ruben', 'confirmedAt' => '2026-10-12T20:15:00+00:00']],
			actual: $this->audit[1]
		);
		$this->assertSame(
			expected: ['thematiq: {uid} withdrew the confirmation for the restricted token set {set}', ['uid' => 'noor', 'set' => 'owned', 'app' => 'thematiq']],
			actual: $this->logged[1]
		);
		$this->assertCount(expectedCount: 2, haystack: $this->audit);
	}//end testTheConfirmAndWithdrawGolden()

	/**
	 * The settings page's entry per restricted set, exactly.
	 *
	 * @return void
	 */
	public function testTheRestrictedSetEntriesExactly(): void {
		$sets = $this->service->restrictedSets();
		$this->assertSame(expected: ['id', 'name', 'licence', 'confirmation'], actual: array_keys($sets[0]));
		$this->assertSame(expected: [null, null, null], actual: array_column($sets, 'confirmation'));
		foreach ($sets as $set) {
			$this->assertIsArray(actual: $set['licence']);
			$this->assertIsString(actual: $set['name']);
		}
	}//end testTheRestrictedSetEntriesExactly()
}//end class
