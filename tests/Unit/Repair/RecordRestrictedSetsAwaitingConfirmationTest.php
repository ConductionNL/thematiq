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
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getDateTime')->willReturnCallback(fn () => new DateTime($this->now));
		$this->licences = new TokenSetLicenceService(
			$this->fixtureTokenSets($config),
			$config,
			new ScheduledSwitchStore($config),
			$this->createMock(ThemingAuditService::class),
			$time,
			$this->createMock(LoggerInterface::class)
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
		$this->assertNotFalse($xml, 'appinfo/info.xml does not parse');
		$steps = array_map('strval', (array)$xml->xpath('/info/repair-steps/post-migration/step'));
		$this->assertContains(RecordRestrictedSetsAwaitingConfirmation::class, $steps, 'the step is not registered for an upgrade');
		$this->assertSame(RecordRestrictedSetsAwaitingConfirmation::class, end($steps), 'it runs after ApplyConfigSource, which may apply a set');

		$class = RecordRestrictedSetsAwaitingConfirmation::class;
		$logger = $this->createMock(LoggerInterface::class);
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
		$output = $this->createMock(IOutput::class);
		$output->expects($this->once())->method('warning')->with($this->stringContains('owned, owned-two'));

		$this->registeredStep()->run($output);

		$this->assertSame('owned', $this->appValues['token_set'], 'the instance keeps its theme');
		$this->assertSame('[{"group":"ruimte","tokenSet":"owned-two"}]', $this->appValues['group_token_sets']);
		$this->assertSame(
			['owned' => $this->now, 'owned-two' => $this->now],
			json_decode($this->appValues[TokenSetLicenceService::AWAITING_KEY], true)
		);
		$this->assertSame(['owned', 'owned-two'], array_map(fn ($w) => $w[1]['set'], $this->warnings));

		$awaiting = $this->licences->awaitingConfirmation();
		$this->assertSame([$this->now, $this->now], array_column($awaiting, 'since'), 'the admin notice gets the date');
	}//end testAnAppliedRestrictedSetStaysAndIsRecorded()

	public function testASecondUpgradeKeepsTheFirstDateAndWarnsNoMore(): void {
		$this->appValues['token_set'] = 'owned';
		$this->registeredStep()->run($this->createMock(IOutput::class));
		$this->now = '2026-11-01T09:00:00+00:00';
		$this->warnings = [];

		$this->registeredStep()->run($this->createMock(IOutput::class));

		$this->assertSame(['owned' => '2026-10-14T09:00:00+00:00'], json_decode($this->appValues[TokenSetLicenceService::AWAITING_KEY], true));
		$this->assertSame([], $this->warnings);
	}//end testASecondUpgradeKeepsTheFirstDateAndWarnsNoMore()

	public function testAConfirmationEndsTheRecord(): void {
		$this->appValues['token_set'] = 'owned';
		$this->registeredStep()->run($this->createMock(IOutput::class));

		$this->licences->confirm('owned', 'ruben');

		$this->assertSame([], json_decode($this->appValues[TokenSetLicenceService::AWAITING_KEY], true));
		$this->assertSame([], $this->licences->awaitingConfirmation());
	}//end testAConfirmationEndsTheRecord()

	public function testNothingAppliedNothingRecorded(): void {
		$this->appValues['token_set'] = 'open';
		$output = $this->createMock(IOutput::class);
		$output->expects($this->never())->method('warning');
		$output->expects($this->once())->method('info');

		$this->registeredStep()->run($output);

		$this->assertSame([], json_decode($this->appValues[TokenSetLicenceService::AWAITING_KEY], true));
	}//end testNothingAppliedNothingRecorded()

	public function testAFailureNeverFailsTheUpgrade(): void {
		$licences = $this->createMock(TokenSetLicenceService::class);
		$licences->method('recordAwaitingConfirmation')->willThrowException(new \RuntimeException('disk'));
		$output = $this->createMock(IOutput::class);
		$output->expects($this->once())->method('warning');

		(new RecordRestrictedSetsAwaitingConfirmation($licences, $this->createMock(LoggerInterface::class)))->run($output);

		$this->assertNotSame('', (new RecordRestrictedSetsAwaitingConfirmation($licences, $this->createMock(LoggerInterface::class)))->getName());
	}//end testAFailureNeverFailsTheUpgrade()
}//end class
