<?php

/**
 * Unit tests for RestrictedSetGate.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\Exception\RestrictedSetNotConfirmedException;
use OCA\Thematiq\Service\RestrictedSetGate;
use OCA\Thematiq\Tests\Unit\Support\RestrictedSetFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A set declares its licence, a restricted set is refused until it is
 * confirmed, and the confirmation is kept per set with who and when.
 */
#[CoversClass(RestrictedSetGate::class)]
#[CoversClass(RestrictedSetNotConfirmedException::class)]
class RestrictedSetGateTest extends TestCase {

	use RestrictedSetFixture;

	/**
	 * The gate under test.
	 *
	 * @var RestrictedSetGate
	 */
	private RestrictedSetGate $gate;

	/**
	 * Set up each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->gate = new RestrictedSetGate(config: $this->memoryConfig(), appPath: $this->makeAppDir());
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
	 * A set declares identifier package version holder and restriction.
	 *
	 * @return void
	 */
	public function testASetDeclaresIdentifierPackageVersionHolderAndRestriction(): void {
		$this->assertSame(
			expected: ['id' => 'LicenseRef-Voorbeeld', 'package' => '@example/voorbeeld-design-tokens', 'version' => '1.0.3', 'holder' => 'Gemeente Voorbeeld', 'restricted' => true],
			actual: $this->gate->licenceOf('owned')
		);
		$this->assertFalse(condition: $this->gate->licenceOf('open')['restricted']);
		$this->assertNull(actual: $this->gate->licenceOf('nextcloud'));
		$this->assertNull(actual: $this->gate->licenceOf('custom-anything'));
	}//end testASetDeclaresIdentifierPackageVersionHolderAndRestriction()

	/**
	 * The licence text ships beside the set.
	 *
	 * @return void
	 */
	public function testTheLicenceTextShipsBesideTheSet(): void {
		$this->assertSame(expected: "All rights reserved. Use is limited to Gemeente Voorbeeld.\n", actual: $this->gate->licenceText('owned'));
		$this->assertNull(actual: $this->gate->licenceText('owned-two'));
		$this->assertNull(actual: $this->gate->licenceText('../owned'));
	}//end testTheLicenceTextShipsBesideTheSet()

	/**
	 * Only a declared open licence is unrestricted.
	 *
	 * @return void
	 */
	public function testOnlyADeclaredOpenLicenceIsUnrestricted(): void {
		$this->assertTrue(condition: $this->gate->isRestricted('owned'));
		$this->assertTrue(condition: $this->gate->isRestricted('sloppy'), message: 'a licence block without the flag counts as restricted');
		$this->assertFalse(condition: $this->gate->isRestricted('open'));
		$this->assertFalse(condition: $this->gate->isRestricted('nextcloud'));
		$this->assertSame(expected: ['owned', 'owned-two', 'sloppy'], actual: $this->gate->restrictedIds());
	}//end testOnlyADeclaredOpenLicenceIsUnrestricted()

	/**
	 * A restricted set is refused until it is confirmed.
	 *
	 * @return void
	 */
	public function testARestrictedSetIsRefusedUntilItIsConfirmed(): void {
		$this->assertFalse(condition: $this->gate->mayApply('owned'));
		$this->assertTrue(condition: $this->gate->mayApply('open'));
		$this->assertTrue(condition: $this->gate->mayApply('nextcloud'));

		try {
			$this->gate->assertMayApply('owned');
			$this->fail(message: 'an unconfirmed restricted set was let through');
		} catch (RestrictedSetNotConfirmedException $e) {
			$this->assertSame(expected: 403, actual: $e->getCode());
			$this->assertSame(expected: 'owned', actual: $e->getTokenSetId());
		}

		$this->gate->storeConfirmation('owned', 'ruben', '2026-10-12T20:00:00+00:00');
		$this->gate->assertMayApply('owned');
		$this->assertTrue(condition: $this->gate->mayApply('owned'));
		$this->assertSame(expected: ['uid' => 'ruben', 'at' => '2026-10-12T20:00:00+00:00'], actual: $this->gate->confirmation('owned'));
	}//end testARestrictedSetIsRefusedUntilItIsConfirmed()

	/**
	 * A confirmation counts for its own set only.
	 *
	 * @return void
	 */
	public function testAConfirmationCountsForItsOwnSetOnly(): void {
		$this->gate->storeConfirmation('owned', 'ruben', '2026-10-12T20:00:00+00:00');

		$this->assertFalse(condition: $this->gate->mayApply('owned-two'));
		$this->assertNull(actual: $this->gate->confirmation('owned-two'));
	}//end testAConfirmationCountsForItsOwnSetOnly()

	/**
	 * A set that is already there stays.
	 *
	 * @return void
	 */
	public function testASetThatIsAlreadyThereStays(): void {
		$this->gate->assertMayApply('owned', 'owned');
		$this->expectException(exception: RestrictedSetNotConfirmedException::class);
		$this->gate->assertMayApply('owned', 'open');
	}//end testASetThatIsAlreadyThereStays()

	/**
	 * A dropped confirmation refuses again.
	 *
	 * @return void
	 */
	public function testADroppedConfirmationRefusesAgain(): void {
		$this->gate->storeConfirmation('owned', 'ruben', '2026-10-12T20:00:00+00:00');
		$this->gate->storeConfirmation('owned-two', 'ruben', '2026-10-12T20:01:00+00:00');
		$this->gate->dropConfirmation('owned');

		$this->assertFalse(condition: $this->gate->mayApply('owned'));
		$this->assertTrue(condition: $this->gate->mayApply('owned-two'));
	}//end testADroppedConfirmationRefusesAgain()

	/**
	 * An unreadable confirmation is no confirmation.
	 *
	 * @return void
	 */
	public function testAnUnreadableConfirmationIsNoConfirmation(): void {
		$this->appValues[RestrictedSetGate::CONFIG_KEY] = '{"owned": {"uid": "", "at": "2026-10-12"}, "owned-two": true}';
		$this->assertFalse(condition: $this->gate->mayApply('owned'));
		$this->assertFalse(condition: $this->gate->mayApply('owned-two'));

		$this->appValues[RestrictedSetGate::CONFIG_KEY] = 'not json';
		$this->assertFalse(condition: $this->gate->mayApply('owned'));
	}//end testAnUnreadableConfirmationIsNoConfirmation()

	/**
	 * A missing manifest restricts nothing.
	 *
	 * @return void
	 */
	public function testAMissingManifestRestrictsNothing(): void {
		$gate = new RestrictedSetGate(config: $this->memoryConfig(), appPath: $this->appDir . '/nowhere');
		$this->assertSame(expected: [], actual: $gate->restrictedIds());
		$this->assertNull(actual: $gate->licenceOf('owned'));
	}//end testAMissingManifestRestrictsNothing()
	/**
	 * Golden: what each stored confirmation reads as, and whether the set may then be applied.
	 *
	 * @return void
	 */
	public function testEveryConfirmationShapeGolden(): void {
		$cases = [
			'{"owned": {"uid": "ruben", "at": "2026-10-12T20:15:00+00:00", "extra": 1}}' => [['uid' => 'ruben', 'at' => '2026-10-12T20:15:00+00:00'], true],
			'{"owned": {"uid": "ruben", "at": ""}}' => [null, false],
			'{"owned": {"uid": 7, "at": "x"}}' => [null, false],
			'{"owned": {"uid": "ruben"}}' => [null, false],
			'{"owned": {"at": "x"}}' => [null, false],
			'{"owned": "ruben"}' => [null, false],
			'{"other": {"uid": "ruben", "at": "x"}}' => [null, false],
			'[]' => [null, false],
			'' => [null, false],
		];
		foreach ($cases as $stored => [$confirmation, $mayApply]) {
			$this->appValues[RestrictedSetGate::CONFIG_KEY] = $stored;
			$this->assertSame(expected: [$confirmation, $mayApply], actual: [$this->gate->confirmation('owned'), $this->gate->mayApply('owned')], message: $stored);
		}
	}//end testEveryConfirmationShapeGolden()

	/**
	 * A manifest that is not a list, or entries without a string id, restrict nothing; a later entry wins.
	 *
	 * @return void
	 */
	public function testOddManifests(): void {
		$dir = sys_get_temp_dir() . '/thematiq-odd-' . bin2hex(random_bytes(4));
		mkdir($dir);
		$restricted = ['licence' => ['id' => 'LicenseRef-X', 'restricted' => true]];
		foreach (['"a string"' => [], 'not json' => [], (string)json_encode([['id' => 7] + $restricted, 'x', ['id' => 'b'] + $restricted, ['id' => 'b'], ['id' => 'c'] + $restricted]) => ['c']] as $json => $ids) {
			file_put_contents($dir . '/token-sets.json', $json);
			$gate = new RestrictedSetGate(config: $this->memoryConfig(), appPath: $dir);
			$this->assertSame(expected: $ids, actual: $gate->restrictedIds(), message: $json);
		}

		unlink($dir . '/token-sets.json');
		rmdir($dir);
	}//end testOddManifests()
}//end class
