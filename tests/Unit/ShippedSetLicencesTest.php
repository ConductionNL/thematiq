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

	/**
	 * The restricted sets are the audited ones.
	 *
	 * @return void
	 */
	public function testTheRestrictedSetsAreTheAuditedOnes(): void {
		$gate = new RestrictedSetGate(config: $this->createMock(originalClassName: IConfig::class), appPath: $this->root());
		$restricted = $gate->restrictedIds();
		sort($restricted);

		$this->assertSame(expected: self::RESTRICTED, actual: $restricted);
	}//end testTheRestrictedSetsAreTheAuditedOnes()

	/**
	 * Every converted set declares a licence.
	 *
	 * @return void
	 */
	public function testEveryConvertedSetDeclaresALicence(): void {
		foreach ($this->manifest() as $entry) {
			$converted = (isset($entry['upstreamRef']) === true || isset($entry['upstreamPackage']) === true);
			if ($converted === false) {
				continue;
			}

			$this->assertIsArray(actual: ($entry['licence'] ?? null), message: $entry['id'] . ' comes from a published theme and declares no licence');
		}
	}//end testEveryConvertedSetDeclaresALicence()

	/**
	 * Every declaration is complete and every restricted set ships its text.
	 *
	 * @return void
	 */
	public function testEveryDeclarationIsCompleteAndEveryRestrictedSetShipsItsText(): void {
		$gate = new RestrictedSetGate(config: $this->createMock(originalClassName: IConfig::class), appPath: $this->root());
		foreach ($this->manifest() as $entry) {
			if (isset($entry['licence']) === false) {
				continue;
			}

			$id = $entry['id'];
			foreach (['id', 'package', 'version', 'holder', 'restricted'] as $field) {
				$this->assertArrayHasKey(key: $field, array: $entry['licence'], message: $id . ' licence lacks ' . $field);
			}

			$this->assertIsBool(actual: $entry['licence']['restricted'], message: $id);
			$this->assertNotSame(expected: '', actual: $entry['licence']['holder'], message: $id);
			if ($entry['licence']['restricted'] === true) {
				$this->assertNotEmpty(actual: $gate->licenceText($id), message: $id . ' ships no css/tokens/' . $id . '.LICENSE.md');
			}
		}
	}//end testEveryDeclarationIsCompleteAndEveryRestrictedSetShipsItsText()

	/**
	 * The audit table lists every declared set.
	 *
	 * @return void
	 */
	public function testTheAuditTableListsEveryDeclaredSet(): void {
		$table = (string)file_get_contents($this->root() . '/docs/reference/token-set-licences.md');
		foreach ($this->manifest() as $entry) {
			if (isset($entry['licence']) === true) {
				$this->assertStringContainsString(needle: '| `' . $entry['id'] . '` |', haystack: $table, message: $entry['id'] . ' is missing from the audit table');
			}
		}
	}//end testTheAuditTableListsEveryDeclaredSet()

	/**
	 * No licence file without a set.
	 *
	 * @return void
	 */
	public function testNoLicenceFileWithoutASet(): void {
		foreach ((array)glob($this->root() . '/css/tokens/*.LICENSE.md') as $file) {
			$id = basename((string)$file, '.LICENSE.md');
			$this->assertFileExists(filename: $this->root() . '/css/tokens/' . $id . '.css', message: $id);
		}
	}//end testNoLicenceFileWithoutASet()
}//end class
