<?php

/**
 * Shipped token-set typeface audit — PHPUnit inventory gate.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * Every shipped set must either name a typeface its own design system serves,
 * name only system families, or say in `token-sets.json` that the family cannot
 * be redistributed and what an administrator does instead. A set in none of
 * those three states renders a substitute typeface and says nothing, which is
 * the state 33 of 58 sets were in on 2026-10-04.
 *
 * No Nextcloud runtime required — filesystem work over `css/tokens/`,
 * `css/systems/*` and `token-sets.json`.
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-a-set-says-when-its-typeface-cannot-be-served
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\TokenSetFontAuditService;
use PHPUnit\Framework\TestCase;

/**
 * Static typeface-inventory regression test.
 */
class TokenSetFontAuditTest extends TestCase {

	/**
	 * Repository root, derived from this test file's location.
	 *
	 * @return string The repo root.
	 */
	private function repoRoot(): string {
		return \dirname(__DIR__, 3);
	}//end repoRoot()

	/**
	 * The audit service built from its real (pure) collaborator.
	 *
	 * @return TokenSetFontAuditService The service.
	 */
	private function service(): TokenSetFontAuditService {
		return new TokenSetFontAuditService();
	}//end service()

	/**
	 * No shipped set names a typeface that neither loads nor is declared.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-a-set-says-when-its-typeface-cannot-be-served
	 */
	public function testEverySetsTypefaceEitherLoadsOrIsDeclared(): void {
		$offenders = [];
		foreach ($this->service()->auditAll($this->repoRoot()) as $result) {
			if ($result['ok'] === true) {
				continue;
			}

			$offenders[] = $result['id'] . ': names ' . (string)$result['family']
				. ', which no stylesheet of its design system serves, and token-sets.json '
				. 'declares no font block for it';
		}

		$this->assertSame(
			[],
			$offenders,
			"These shipped sets would render a substitute typeface in silence.\n"
			. "Self-host the family (scripts/build-fonts.js FAMILIES) when its licence allows it,\n"
			. "or add a `font` block with licence, licenceHolder, action and note to its\n"
			. "token-sets.json entry so the admin UI asks for an upload:\n  - "
			. implode("\n  - ", $offenders)
		);
	}//end testEverySetsTypefaceEitherLoadsOrIsDeclared()

	/**
	 * The audit is not vacuous: it must be able to report BOTH a served family
	 * and an unserved one. A rule that answered "fine" to everything would pass
	 * the assertion above too.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-a-set-says-when-its-typeface-cannot-be-served
	 */
	public function testTheAuditDistinguishesAServedFamilyFromAnUnservedOne(): void {
		$service = $this->service();

		$served = $service->auditSet($this->repoRoot(), 'example-gemeente', ['design_system' => 'nldesign']);
		$this->assertSame('Source Sans 3', $served['family']);
		$this->assertSame('self-hosted', $served['kind']);
		$this->assertTrue($served['selfHosted']);

		// The same set file, judged against a design system that links no
		// stylesheet at all, must come back unserved — so the verdict is a
		// function of what is linked, not of the set's name.
		$unserved = $service->auditSet($this->repoRoot(), 'example-gemeente', ['design_system' => 'none']);
		$this->assertSame('undeclared', $unserved['kind']);
		$this->assertFalse($unserved['selfHosted']);
		$this->assertFalse($unserved['ok']);
	}//end testTheAuditDistinguishesAServedFamilyFromAnUnservedOne()

	/**
	 * A family written through a `var()` chain is resolved, not read as a name.
	 *
	 * conduction-new writes
	 * `--nldesign-font-family: var(--conduction-typography-font-family-body)`.
	 * Before the chain was followed the audit compared the literal string
	 * "var(--conduction-typography-font-family-body)" against the served
	 * families and of course found nothing, which looks identical to a missing
	 * typeface.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-a-set-says-when-its-typeface-cannot-be-served
	 */
	public function testAFamilyBehindAVarChainIsResolved(): void {
		$result = $this->service()->auditSet(
			$this->repoRoot(),
			'conduction-new',
			['design_system' => 'nldesign']
		);

		$this->assertStringContainsString('var(', (string)$result['stack'], 'The set must still write its family as a var().');
		$this->assertSame('Figtree', $result['family']);
		$this->assertSame('self-hosted', $result['kind']);
	}//end testAFamilyBehindAVarChainIsResolved()

	/**
	 * A set whose family cannot be redistributed carries the licence position
	 * and the action, and the warning passes them to the admin UI.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-a-set-says-when-its-typeface-cannot-be-served
	 */
	public function testAnUndistributableFamilyCarriesItsLicencePositionAndAction(): void {
		$manifest = $this->manifest();

		foreach (['vng' => 'Avenir', 'zwolle' => 'DIN', 'duo' => 'RijksoverheidSans'] as $id => $family) {
			$meta = ($manifest[$id] ?? []);
			$block = ($meta['font'] ?? null);

			$this->assertIsArray($block, $id . ' must declare a font block.');
			$this->assertSame($family, ($block['family'] ?? null), $id . ' must declare the family it names.');
			$this->assertFalse(($block['selfHosted'] ?? null), $id . ' must not claim the family is self-hosted.');
			$this->assertNotSame('', trim((string)($block['note'] ?? '')), $id . ' must record why.');

			$warnings = $this->service()->warningsFor($this->repoRoot(), $id, $meta);
			$this->assertCount(1, $warnings, $id . ' must raise exactly one typeface warning.');
			$this->assertSame('font', $warnings[0]['kind']);
			$this->assertSame($family, $warnings[0]['family']);
			$this->assertContains($warnings[0]['licence'], ['proprietary', 'restricted']);
			$this->assertContains($warnings[0]['action'], ['upload', 'acknowledge']);
		}
	}//end testAnUndistributableFamilyCarriesItsLicencePositionAndAction()

	/**
	 * A declared family's name must be the one the set's CSS actually names, so
	 * the manifest cannot drift away from the stylesheet it describes.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-a-set-says-when-its-typeface-cannot-be-served
	 */
	public function testADeclaredFontBlockMatchesTheSetsOwnCss(): void {
		$drift = [];
		foreach ($this->manifest() as $id => $meta) {
			$block = ($meta['font'] ?? null);
			if (is_array($block) === false) {
				continue;
			}

			$result = $this->service()->auditSet($this->repoRoot(), $id, $meta);
			if (($block['family'] ?? null) !== $result['family']) {
				$drift[] = $id . ': token-sets.json says ' . (string)($block['family'] ?? 'nothing')
					. ', css/tokens/' . $id . '.css names ' . (string)($result['family'] ?? 'nothing');
			}

			if ($result['selfHosted'] === true) {
				$drift[] = $id . ': declares a font block, but ' . (string)$result['family']
					. ' is now self-hosted — delete the block.';
			}
		}

		$this->assertSame([], $drift, "A font block has drifted from the set it describes:\n  - " . implode("\n  - ", $drift));
	}//end testADeclaredFontBlockMatchesTheSetsOwnCss()

	/**
	 * Arial and the CSS generics are reported as system families, not as a
	 * missing upload: there is nothing to self-host and nothing to ask for.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-a-set-says-when-its-typeface-cannot-be-served
	 */
	public function testASystemFamilyIsNotReportedAsMissing(): void {
		$result = $this->service()->auditSet($this->repoRoot(), 'tilburg', $this->manifest()['tilburg'] ?? []);

		$this->assertSame('Arial', $result['family']);
		$this->assertSame('system', $result['kind']);
		$this->assertSame([], $this->service()->warningsFor($this->repoRoot(), 'tilburg', $this->manifest()['tilburg'] ?? []));
	}//end testASystemFamilyIsNotReportedAsMissing()

	/**
	 * `token-sets.json` indexed by id.
	 *
	 * @return array<string, array<string, mixed>> The manifest entries.
	 */
	private function manifest(): array {
		$decoded = json_decode((string)file_get_contents($this->repoRoot() . '/token-sets.json'), true);
		$this->assertIsArray($decoded, 'token-sets.json must be a JSON array.');

		$byId = [];
		foreach ($decoded as $entry) {
			if (is_array($entry) === true && is_string(($entry['id'] ?? null)) === true) {
				$byId[$entry['id']] = $entry;
			}
		}

		return $byId;
	}//end manifest()
}//end class
