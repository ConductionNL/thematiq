<?php

/**
 * Instances that apply Rijkshuisstijl keep a set that exists after the
 * converted set replaces the hand-written one.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/token-set-licences/spec.md#requirement-an-upgrade-records-every-restricted-set-that-is-already-applied
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
#[UsesClass(\OCA\Thematiq\Service\CssDeclarationScanner::class)]
#[UsesClass(\OCA\Thematiq\Service\CssParserService::class)]
#[UsesClass(\OCA\Thematiq\Service\IndexedJsonManifest::class)]
#[UsesClass(\OCA\Thematiq\Service\ListedTokenSet::class)]
#[UsesClass(\OCA\Thematiq\Service\RuntimeFile\LocalFile::class)]
#[UsesClass(\OCA\Thematiq\Service\RuntimeFile\AppFilePath::class)]
#[UsesClass(\OCA\Thematiq\Service\TokenSetAudits::class)]
#[UsesClass(\OCA\Thematiq\Service\TokenSetFiles::class)]
#[UsesClass(\OCA\Thematiq\Service\TokenSetRuntime::class)]
class RijkshuisstijlReplacementKeepsAppliedInstancesTest extends TestCase {

	use RestrictedSetFixture;

	private const SET = 'rijkshuisstijl';

	private const NOW = '2026-10-15T09:00:00+00:00';

	/**
	 * The token set service over the shipped release.
	 *
	 * @var TokenSetService
	 */
	private TokenSetService $tokenSets;

	/**
	 * The licence service the repair step reads and writes through.
	 *
	 * @var TokenSetLicenceService
	 */
	private TokenSetLicenceService $licences;

	/**
	 * Wire the licence service over the shipped release and an in-memory config.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		// The shipped release itself; removeAppDir() is never called on it.
		$this->appDir = dirname(__DIR__, 3);
		$config = $this->memoryConfig();
		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('getDateTime')->willReturnCallback(fn () => new DateTime(self::NOW));
		$this->tokenSets = $this->fixtureTokenSets(config: $config);
		$this->licences = new TokenSetLicenceService(
			tokenSets: $this->tokenSets,
			config: $config,
			switches: new ScheduledSwitchStore(config: $config),
			audit: $this->createMock(originalClassName: ThemingAuditService::class),
			time: $time,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end setUp()

	/**
	 * Run the repair step an upgrade runs.
	 *
	 * @return void
	 */
	private function upgrade(): void {
		$repair = new RecordRestrictedSetsAwaitingConfirmation(
			licences: $this->licences,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
		$repair->run(output: $this->createMock(originalClassName: IOutput::class));
	}//end upgrade()

	/**
	 * The converted set ships under the id instances already apply.
	 *
	 * @return void
	 */
	public function testTheConvertedSetShipsUnderTheIdInstancesApply(): void {
		$manifest = json_decode((string)file_get_contents($this->appDir . '/token-sets.json'), true);
		$entries = array_values(array_filter($manifest, fn (array $entry): bool => $entry['id'] === self::SET));

		$this->assertCount(expectedCount: 1, haystack: $entries, message: 'exactly one shipped entry answers to ' . self::SET);
		$this->assertSame(
			expected: '@rijkshuisstijl-community/design-tokens',
			actual: ($entries[0]['upstreamPackage'] ?? null),
			message: 'the entry is the converted set'
		);
		$this->assertTrue(condition: $entries[0]['licence']['restricted'], message: 'the converted set is licence-gated');
		$this->assertTrue(condition: $this->tokenSets->isValidTokenSet(tokenSetId: self::SET), message: 'css/tokens/' . self::SET . '.css is missing');
		$this->assertFileExists(filename: $this->appDir . '/css/tokens/dark/' . self::SET . '.css');
		$this->assertContains(needle: self::SET, haystack: array_column($this->tokenSets->getAvailableTokenSets(), 'id'));
	}//end testTheConvertedSetShipsUnderTheIdInstancesApply()

	/**
	 * An instance on the hand-written set keeps it and awaits confirmation.
	 *
	 * @return void
	 */
	public function testAnInstanceOnTheHandWrittenSetKeepsItAndAwaitsConfirmation(): void {
		$this->appValues['token_set'] = self::SET;
		$this->appValues['group_token_sets'] = '[{"group":"rijk","tokenSet":"rijkshuisstijl"}]';

		$this->upgrade();

		$this->assertSame(expected: self::SET, actual: $this->appValues['token_set'], message: 'the instance keeps its theme');
		$this->assertSame(expected: '[{"group":"rijk","tokenSet":"rijkshuisstijl"}]', actual: $this->appValues['group_token_sets']);
		$this->assertTrue(
			condition: $this->tokenSets->isValidTokenSet(tokenSetId: $this->appValues['token_set']),
			message: 'the applied id points at a set that exists'
		);
		$this->assertSame(
			expected: [self::SET => self::NOW],
			actual: json_decode($this->appValues[TokenSetLicenceService::AWAITING_KEY], true),
			message: 'the upgrade records the set as awaiting an administrator'
		);
	}//end testAnInstanceOnTheHandWrittenSetKeepsItAndAwaitsConfirmation()

	/**
	 * A confirmed instance keeps the set without a new record.
	 *
	 * @return void
	 */
	public function testAConfirmedInstanceKeepsItWithoutANewRecord(): void {
		$this->appValues['token_set'] = self::SET;
		$this->confirmSet(tokenSetId: self::SET);

		$this->upgrade();

		$this->assertSame(expected: self::SET, actual: $this->appValues['token_set']);
		$this->assertSame(expected: [], actual: json_decode($this->appValues[TokenSetLicenceService::AWAITING_KEY], true));
	}//end testAConfirmedInstanceKeepsItWithoutANewRecord()
}//end class
