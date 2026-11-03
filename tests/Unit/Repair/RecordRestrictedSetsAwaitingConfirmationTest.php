<?php

/**
 * Unit tests for RecordRestrictedSetsAwaitingConfirmation, started from its
 * registration in appinfo/info.xml.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/shipped-set-licences/specs/token-set-licences/spec.md#requirement-an-upgrade-records-every-restricted-set-that-is-already-applied
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Repair;

use DateTime;
use OCA\Thematiq\Repair\RecordRestrictedSetsAwaitingConfirmation;
use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\RestrictedSetGate;
use OCA\Thematiq\Service\RuntimeFile\SetFileReader;
use OCA\Thematiq\Service\ScheduledSwitchStore;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\TokenSetFontAuditService;
use OCA\Thematiq\Service\TokenSetLicenceService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Tests\Unit\Support\RestrictedSetFixture;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * An upgrade keeps a restricted set applied and records that it needs a confirmation.
 */
#[CoversClass(RecordRestrictedSetsAwaitingConfirmation::class)]
#[CoversClass(TokenSetLicenceService::class)]
#[UsesClass(RestrictedSetGate::class)]
#[UsesClass(TokenSetService::class)]
#[UsesClass(ActiveTokenSetService::class)]
#[UsesClass(ScheduledSwitchStore::class)]
#[UsesClass(SetFileReader::class)]
#[UsesClass(TokenSetFontAuditService::class)]
class RecordRestrictedSetsAwaitingConfirmationTest extends TestCase {

	use RestrictedSetFixture;

	/**
	 * The clock.
	 *
	 * @var string
	 */
	private string $now = '2026-10-14T09:00:00+00:00';

	/**
	 * Log warnings, as [message, context].
	 *
	 * @var array<int, array{0: string, 1: array<string, mixed>}>
	 */
	private array $warnings = [];

	/**
	 * The licence service the step uses.
	 *
	 * @var TokenSetLicenceService
	 */
	private TokenSetLicenceService $licences;

	protected function setUp(): void {
		parent::setUp();
		$this->makeAppDir();
		$config = $this->memoryConfig();
		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('getDateTime')->willReturnCallback(fn () => new DateTime($this->now));
		$this->licences = new TokenSetLicenceService(
			tokenSets: $this->fixtureTokenSets(config: $config),
			config: $config,
			switches: new ScheduledSwitchStore(config: $config),
			audit: $this->createMock(originalClassName: ThemingAuditService::class),
			time: $time,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end setUp()

	protected function tearDown(): void {
		$this->removeAppDir();
		parent::tearDown();
	}//end tearDown()

	/**
	 * The step as an upgrade runs it: the class named in info.xml, last in post-migration.
	 *
	 * @return IRepairStep The step.
	 */
	private function registeredStep(): IRepairStep {
		$xml = simplexml_load_string((string)file_get_contents(dirname(__DIR__, 3) . '/appinfo/info.xml'));
		$this->assertNotFalse(condition: $xml, message: 'appinfo/info.xml does not parse');
		$steps = array_map('strval', (array)$xml->xpath('/info/repair-steps/post-migration/step'));
		$this->assertContains(needle: RecordRestrictedSetsAwaitingConfirmation::class, haystack: $steps, message: 'the step is not registered for an upgrade');
		$this->assertSame(expected: RecordRestrictedSetsAwaitingConfirmation::class, actual: end($steps), message: 'it runs after ApplyConfigSource, which may apply a set');

		$class = RecordRestrictedSetsAwaitingConfirmation::class;
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->method('warning')->willReturnCallback(
			function (string $message, array $context = []): void {
				$this->warnings[] = [$message, $context];
			}
		);

		return new $class($this->licences, $logger);
	}//end registeredStep()

	public function testAnAppliedRestrictedSetStaysAndIsRecorded(): void {
		$this->appValues['token_set'] = 'owned';
		$this->appValues['group_token_sets'] = '[{"group":"ruimte","tokenSet":"owned-two"}]';
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('warning')->with($this->stringContains(string: 'owned, owned-two'));

		$this->registeredStep()->run($output);

		$this->assertSame(expected: 'owned', actual: $this->appValues['token_set'], message: 'the instance keeps its theme');
		$this->assertSame(expected: '[{"group":"ruimte","tokenSet":"owned-two"}]', actual: $this->appValues['group_token_sets']);
		$this->assertSame(
			expected: ['owned' => $this->now, 'owned-two' => $this->now],
			actual: json_decode($this->appValues[TokenSetLicenceService::AWAITING_KEY], true)
		);
		$this->assertSame(expected: ['owned', 'owned-two'], actual: array_map(fn ($w) => $w[1]['set'], $this->warnings));

		$awaiting = $this->licences->awaitingConfirmation();
		$this->assertSame(expected: [$this->now, $this->now], actual: array_column($awaiting, 'since'), message: 'the admin notice gets the date');
	}//end testAnAppliedRestrictedSetStaysAndIsRecorded()

	public function testASecondUpgradeKeepsTheFirstDateAndWarnsNoMore(): void {
		$this->appValues['token_set'] = 'owned';
		$this->registeredStep()->run($this->createMock(originalClassName: IOutput::class));
		$this->now = '2026-11-01T09:00:00+00:00';
		$this->warnings = [];

		$this->registeredStep()->run($this->createMock(originalClassName: IOutput::class));

		$this->assertSame(expected: ['owned' => '2026-10-14T09:00:00+00:00'], actual: json_decode($this->appValues[TokenSetLicenceService::AWAITING_KEY], true));
		$this->assertSame(expected: [], actual: $this->warnings);
	}//end testASecondUpgradeKeepsTheFirstDateAndWarnsNoMore()

	public function testAConfirmationEndsTheRecord(): void {
		$this->appValues['token_set'] = 'owned';
		$this->registeredStep()->run($this->createMock(originalClassName: IOutput::class));

		$this->licences->confirm('owned', 'ruben');

		$this->assertSame(expected: [], actual: json_decode($this->appValues[TokenSetLicenceService::AWAITING_KEY], true));
		$this->assertSame(expected: [], actual: $this->licences->awaitingConfirmation());
	}//end testAConfirmationEndsTheRecord()

	public function testNothingAppliedNothingRecorded(): void {
		$this->appValues['token_set'] = 'open';
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->never())->method('warning');
		$output->expects($this->once())->method('info');

		$this->registeredStep()->run($output);

		$this->assertSame(expected: [], actual: json_decode($this->appValues[TokenSetLicenceService::AWAITING_KEY], true));
	}//end testNothingAppliedNothingRecorded()

	public function testAFailureNeverFailsTheUpgrade(): void {
		$licences = $this->createMock(originalClassName: TokenSetLicenceService::class);
		$licences->method('recordAwaitingConfirmation')->willThrowException(new \RuntimeException('disk'));
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('warning');

		(new RecordRestrictedSetsAwaitingConfirmation(licences: $licences, logger: $this->createMock(originalClassName: LoggerInterface::class)))->run($output);

		$this->assertNotSame(expected: '', actual: (new RecordRestrictedSetsAwaitingConfirmation(licences: $licences, logger: $this->createMock(originalClassName: LoggerInterface::class)))->getName());
	}//end testAFailureNeverFailsTheUpgrade()
}//end class
