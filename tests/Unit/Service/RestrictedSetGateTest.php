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

	protected function setUp(): void {
		parent::setUp();
		$this->gate = new RestrictedSetGate($this->memoryConfig(), $this->makeAppDir());
	}//end setUp()

	protected function tearDown(): void {
		$this->removeAppDir();
		parent::tearDown();
	}//end tearDown()

	public function testASetDeclaresIdentifierPackageVersionHolderAndRestriction(): void {
		$this->assertSame(
			['id' => 'LicenseRef-Voorbeeld', 'package' => '@example/voorbeeld-design-tokens', 'version' => '1.0.3', 'holder' => 'Gemeente Voorbeeld', 'restricted' => true],
			$this->gate->licenceOf('owned')
		);
		$this->assertFalse($this->gate->licenceOf('open')['restricted']);
		$this->assertNull($this->gate->licenceOf('nextcloud'));
		$this->assertNull($this->gate->licenceOf('custom-anything'));
	}//end testASetDeclaresIdentifierPackageVersionHolderAndRestriction()

	public function testTheLicenceTextShipsBesideTheSet(): void {
		$this->assertSame("All rights reserved. Use is limited to Gemeente Voorbeeld.\n", $this->gate->licenceText('owned'));
		$this->assertNull($this->gate->licenceText('owned-two'));
		$this->assertNull($this->gate->licenceText('../owned'));
	}//end testTheLicenceTextShipsBesideTheSet()

	public function testOnlyADeclaredOpenLicenceIsUnrestricted(): void {
		$this->assertTrue($this->gate->isRestricted('owned'));
		$this->assertTrue($this->gate->isRestricted('sloppy'), 'a licence block without the flag counts as restricted');
		$this->assertFalse($this->gate->isRestricted('open'));
		$this->assertFalse($this->gate->isRestricted('nextcloud'));
		$this->assertSame(['owned', 'owned-two', 'sloppy'], $this->gate->restrictedIds());
	}//end testOnlyADeclaredOpenLicenceIsUnrestricted()

	public function testARestrictedSetIsRefusedUntilItIsConfirmed(): void {
		$this->assertFalse($this->gate->mayApply('owned'));
		$this->assertTrue($this->gate->mayApply('open'));
		$this->assertTrue($this->gate->mayApply('nextcloud'));

		try {
			$this->gate->assertMayApply('owned');
			$this->fail('an unconfirmed restricted set was let through');
		} catch (RestrictedSetNotConfirmedException $e) {
			$this->assertSame(403, $e->getCode());
			$this->assertSame('owned', $e->getTokenSetId());
		}

		$this->gate->storeConfirmation('owned', 'ruben', '2026-10-12T20:00:00+00:00');
		$this->gate->assertMayApply('owned');
		$this->assertTrue($this->gate->mayApply('owned'));
		$this->assertSame(['uid' => 'ruben', 'at' => '2026-10-12T20:00:00+00:00'], $this->gate->confirmation('owned'));
	}//end testARestrictedSetIsRefusedUntilItIsConfirmed()

	public function testAConfirmationCountsForItsOwnSetOnly(): void {
		$this->gate->storeConfirmation('owned', 'ruben', '2026-10-12T20:00:00+00:00');

		$this->assertFalse($this->gate->mayApply('owned-two'));
		$this->assertNull($this->gate->confirmation('owned-two'));
	}//end testAConfirmationCountsForItsOwnSetOnly()

	public function testASetThatIsAlreadyThereStays(): void {
		$this->gate->assertMayApply('owned', 'owned');
		$this->expectException(RestrictedSetNotConfirmedException::class);
		$this->gate->assertMayApply('owned', 'open');
	}//end testASetThatIsAlreadyThereStays()

	public function testADroppedConfirmationRefusesAgain(): void {
		$this->gate->storeConfirmation('owned', 'ruben', '2026-10-12T20:00:00+00:00');
		$this->gate->storeConfirmation('owned-two', 'ruben', '2026-10-12T20:01:00+00:00');
		$this->gate->dropConfirmation('owned');

		$this->assertFalse($this->gate->mayApply('owned'));
		$this->assertTrue($this->gate->mayApply('owned-two'));
	}//end testADroppedConfirmationRefusesAgain()

	public function testAnUnreadableConfirmationIsNoConfirmation(): void {
		$this->appValues[RestrictedSetGate::CONFIG_KEY] = '{"owned": {"uid": "", "at": "2026-10-12"}, "owned-two": true}';
		$this->assertFalse($this->gate->mayApply('owned'));
		$this->assertFalse($this->gate->mayApply('owned-two'));

		$this->appValues[RestrictedSetGate::CONFIG_KEY] = 'not json';
		$this->assertFalse($this->gate->mayApply('owned'));
	}//end testAnUnreadableConfirmationIsNoConfirmation()

	public function testAMissingManifestRestrictsNothing(): void {
		$gate = new RestrictedSetGate($this->memoryConfig(), $this->appDir . '/nowhere');
		$this->assertSame([], $gate->restrictedIds());
		$this->assertNull($gate->licenceOf('owned'));
	}//end testAMissingManifestRestrictsNothing()
}//end class
