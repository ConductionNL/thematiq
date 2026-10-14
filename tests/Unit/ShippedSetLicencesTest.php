<?php

/**
 * The licences the shipped token sets declare, read from the release itself.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/shipped-set-licences/specs/token-set-licences/spec.md#requirement-every-shipped-organisation-theme-declares-its-licence
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit;

use OCA\Thematiq\Service\RestrictedSetGate;
use OCP\IConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Every set converted from an organisation's published theme declares its
 * licence and ships the text; the audit table in the docs lists each one.
 */
#[CoversClass(RestrictedSetGate::class)]
class ShippedSetLicencesTest extends TestCase {

	/**
	 * The sets the audit of 14 October 2026 found restricted.
	 */
	private const RESTRICTED = [
		'amsterdam', 'bodegraven-reeuwijk', 'borne', 'buren', 'demodam', 'dinkelland', 'drechterland', 'duiven', 'duo',
		'enkhuizen', 'enschede', 'epe', 'groningen', 'haarlem', 'haarlemmermeer', 'hoeksche-waard', 'hoorn', 'horstaandemaas',
		'leiden', 'leidschendam-voorburg', 'losser', 'nijmegen', 'noaberkracht', 'noordoostpolder', 'noordwijk', 'nora',
		'provincie-zuid-holland', 'purmerend', 'riddeliemers', 'ridderkerk', 'rijkshuisstijl', 'rotterdam', 'stedebroec',
		'tilburg', 'tubbergen', 'utrecht', 'venray', 'vng', 'vught', 'westervoort', 'xxllnc', 'zaanstad', 'zevenaar', 'zwolle',
	];

	/**
	 * The app root.
	 *
	 * @return string The path.
	 */
	private function root(): string {
		return dirname(__DIR__, 2);
	}//end root()

	/**
	 * The shipped manifest.
	 *
	 * @return array<int, array<string, mixed>> The entries.
	 */
	private function manifest(): array {
		return json_decode((string)file_get_contents($this->root() . '/token-sets.json'), true);
	}//end manifest()

	public function testTheRestrictedSetsAreTheAuditedOnes(): void {
		$gate = new RestrictedSetGate($this->createMock(IConfig::class), $this->root());
		$restricted = $gate->restrictedIds();
		sort($restricted);

		$this->assertSame(self::RESTRICTED, $restricted);
	}//end testTheRestrictedSetsAreTheAuditedOnes()

	public function testEveryConvertedSetDeclaresALicence(): void {
		foreach ($this->manifest() as $entry) {
			$converted = (isset($entry['upstreamRef']) === true || isset($entry['upstreamPackage']) === true);
			if ($converted === false) {
				continue;
			}

			$this->assertIsArray(($entry['licence'] ?? null), $entry['id'] . ' comes from a published theme and declares no licence');
		}
	}//end testEveryConvertedSetDeclaresALicence()

	public function testEveryDeclarationIsCompleteAndEveryRestrictedSetShipsItsText(): void {
		$gate = new RestrictedSetGate($this->createMock(IConfig::class), $this->root());
		foreach ($this->manifest() as $entry) {
			if (isset($entry['licence']) === false) {
				continue;
			}

			$id = $entry['id'];
			foreach (['id', 'package', 'version', 'holder', 'restricted'] as $field) {
				$this->assertArrayHasKey($field, $entry['licence'], $id . ' licence lacks ' . $field);
			}

			$this->assertIsBool($entry['licence']['restricted'], $id);
			$this->assertNotSame('', $entry['licence']['holder'], $id);
			if ($entry['licence']['restricted'] === true) {
				$this->assertNotEmpty($gate->licenceText($id), $id . ' ships no css/tokens/' . $id . '.LICENSE.md');
			}
		}
	}//end testEveryDeclarationIsCompleteAndEveryRestrictedSetShipsItsText()

	public function testTheAuditTableListsEveryDeclaredSet(): void {
		$table = (string)file_get_contents($this->root() . '/docs/reference/token-set-licences.md');
		foreach ($this->manifest() as $entry) {
			if (isset($entry['licence']) === true) {
				$this->assertStringContainsString('| `' . $entry['id'] . '` |', $table, $entry['id'] . ' is missing from the audit table');
			}
		}
	}//end testTheAuditTableListsEveryDeclaredSet()

	public function testNoLicenceFileWithoutASet(): void {
		foreach ((array)glob($this->root() . '/css/tokens/*.LICENSE.md') as $file) {
			$id = basename((string)$file, '.LICENSE.md');
			$this->assertFileExists($this->root() . '/css/tokens/' . $id . '.css', $id);
		}
	}//end testNoLicenceFileWithoutASet()
}//end class
