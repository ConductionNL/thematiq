<?php

/**
 * Instances that apply Rijkshuisstijl keep a set that exists after the
 * converted set replaces the hand-written one.
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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Runs the upgrade's repair step against the shipped release (not a fixture),
 * for an instance whose theme, group theme or default is `rijkshuisstijl`.
 */
#[CoversClass(RecordRestrictedSetsAwaitingConfirmation::class)]
#[CoversClass(TokenSetLicenceService::class)]
#[UsesClass(RestrictedSetGate::class)]
#[UsesClass(TokenSetService::class)]
#[UsesClass(ActiveTokenSetService::class)]
#[UsesClass(ScheduledSwitchStore::class)]
#[UsesClass(SetFileReader::class)]
#[UsesClass(TokenSetFontAuditService::class)]
class RijkshuisstijlReplacementKeepsAppliedInstancesTest extends TestCase {

	use RestrictedSetFixture;

	private const SET = 'rijkshuisstijl';

	private const NOW = '2026-10-15T09:00:00+00:00';

	private TokenSetService $tokenSets;

	private TokenSetLicenceService $licences;

	protected function setUp(): void {
		parent::setUp();
		// The shipped release itself; removeAppDir() is never called on it.
		$this->appDir = dirname(__DIR__, 3);
		$config = $this->memoryConfig();
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getDateTime')->willReturnCallback(fn () => new DateTime(self::NOW));
		$this->tokenSets = $this->fixtureTokenSets($config);
		$this->licences = new TokenSetLicenceService(
			$this->tokenSets,
			$config,
			new ScheduledSwitchStore($config),
			$this->createMock(ThemingAuditService::class),
			$time,
			$this->createMock(LoggerInterface::class)
		);
	}//end setUp()

	/**
	 * Run the repair step an upgrade runs.
	 *
	 * @return void
	 */
	private function upgrade(): void {
		(new RecordRestrictedSetsAwaitingConfirmation($this->licences, $this->createMock(LoggerInterface::class)))
			->run($this->createMock(IOutput::class));
	}//end upgrade()

	public function testTheConvertedSetShipsUnderTheIdInstancesApply(): void {
		$manifest = json_decode((string)file_get_contents($this->appDir . '/token-sets.json'), true);
		$entries = array_values(array_filter($manifest, fn (array $entry): bool => $entry['id'] === self::SET));

		$this->assertCount(1, $entries, 'exactly one shipped entry answers to ' . self::SET);
		$this->assertSame('@rijkshuisstijl-community/design-tokens', ($entries[0]['upstreamPackage'] ?? null), 'the entry is the converted set');
		$this->assertTrue($entries[0]['licence']['restricted'], 'the converted set is licence-gated');
		$this->assertTrue($this->tokenSets->isValidTokenSet(self::SET), 'css/tokens/' . self::SET . '.css is missing');
		$this->assertFileExists($this->appDir . '/css/tokens/dark/' . self::SET . '.css');
		$this->assertContains(self::SET, array_column($this->tokenSets->getAvailableTokenSets(), 'id'));
	}//end testTheConvertedSetShipsUnderTheIdInstancesApply()

	public function testAnInstanceOnTheHandWrittenSetKeepsItAndAwaitsConfirmation(): void {
		$this->appValues['token_set'] = self::SET;
		$this->appValues['group_token_sets'] = '[{"group":"rijk","tokenSet":"rijkshuisstijl"}]';

		$this->upgrade();

		$this->assertSame(self::SET, $this->appValues['token_set'], 'the instance keeps its theme');
		$this->assertSame('[{"group":"rijk","tokenSet":"rijkshuisstijl"}]', $this->appValues['group_token_sets']);
		$this->assertTrue($this->tokenSets->isValidTokenSet($this->appValues['token_set']), 'the applied id points at a set that exists');
		$this->assertSame(
			[self::SET => self::NOW],
			json_decode($this->appValues[TokenSetLicenceService::AWAITING_KEY], true),
			'the upgrade records the set as awaiting an administrator'
		);
	}//end testAnInstanceOnTheHandWrittenSetKeepsItAndAwaitsConfirmation()

	public function testAConfirmedInstanceKeepsItWithoutANewRecord(): void {
		$this->appValues['token_set'] = self::SET;
		$this->confirmSet(self::SET);

		$this->upgrade();

		$this->assertSame(self::SET, $this->appValues['token_set']);
		$this->assertSame([], json_decode($this->appValues[TokenSetLicenceService::AWAITING_KEY], true));
	}//end testAConfirmedInstanceKeepsItWithoutANewRecord()
}//end class
