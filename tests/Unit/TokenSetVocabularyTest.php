<?php

/**
 * Shipped token-set vocabulary audit — PHPUnit inventory gate.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * Turns "the examples look correct" into a mechanical statement: every shipped
 * `css/tokens/*.css` set whose design system reads the `--nldesign-*`
 * vocabulary MUST declare the required semantic tokens itself, MUST NOT declare
 * `--nldesign-*` names nothing can consume, and MUST agree with
 * `token-sets.json` about its own primary colour. A set that fails is not a
 * cosmetic problem: the cascade falls through to
 * `css/systems/nldesign/defaults.css`, so the set renders as Rijkshuisstijl
 * rather than as its own brand.
 *
 * The 41 sets that fail today are allow-listed in
 * `tests/Unit/fixtures/token-set-vocabulary-allowlist.json` so CI stays green
 * while the converter regenerates them; the gate fails BOTH on a set
 * that is incomplete and not listed AND on a listed set that has since started
 * passing (so the list can only shrink). The allow-list must be empty once
 * every shipped set is complete.
 *
 * No Nextcloud runtime required — pure filesystem work over the repo's own
 * `css/`, `token-sets.json` and `design-systems.json`, mirroring the
 * `tests/Unit/TokenSetContrastAuditTest.php` static-inventory pattern.
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit;

use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\TokenSetVocabularyAuditService;
use PHPUnit\Framework\TestCase;

/**
 * Static vocabulary-inventory regression test (no Nextcloud runtime required).
 */
class TokenSetVocabularyTest extends TestCase {

	/**
	 * Repository root, derived from this test file's location.
	 */
	private function repoRoot(): string {
		return \dirname(__DIR__, 2);
	}//end repoRoot()

	/**
	 * Build the audit service from its real (pure) collaborator.
	 */
	private function service(): TokenSetVocabularyAuditService {
		return new TokenSetVocabularyAuditService(new CssParserService());
	}//end service()

	/**
	 * The allow-listed known-incomplete set ids.
	 *
	 * @return array<int, string>
	 */
	private function allowlist(): array {
		$path = $this->repoRoot() . '/tests/Unit/fixtures/token-set-vocabulary-allowlist.json';
		$this->assertFileExists($path, 'The known-incomplete allow-list fixture must exist.');

		$decoded = json_decode((string)file_get_contents($path), true);
		$this->assertIsArray($decoded, 'The allow-list fixture must be a JSON object.');
		$this->assertArrayHasKey('sets', $decoded, 'The allow-list fixture must carry a "sets" array.');
		$this->assertIsArray($decoded['sets'], 'The allow-list "sets" key must be an array.');

		return $decoded['sets'];
	}//end allowlist()

	/**
	 * Every shipped set is either vocabulary-complete or explicitly
	 * allow-listed as known-incomplete.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
	 */
	public function testEveryShippedSetIsCompleteOrAllowListed(): void {
		$allowlist = $this->allowlist();
		$results = $this->service()->auditAll($this->repoRoot());

		$this->assertNotEmpty($results, 'css/tokens/ must contain at least one shipped set.');

		$offenders = [];
		foreach ($results as $result) {
			if ($result['auditable'] === false || $result['complete'] === true) {
				continue;
			}

			if (\in_array($result['id'], $allowlist, true) === true) {
				continue;
			}

			$reasons = [];
			if ($result['missingRequired'] !== []) {
				$reasons[] = \count($result['missingRequired']) . ' missing required ('
					. implode(', ', $result['missingRequired']) . ')';
			}

			if ($result['foreignNldesignNames'] !== []) {
				$reasons[] = \count($result['foreignNldesignNames']) . ' foreign --nldesign-* name(s) ('
					. implode(', ', $result['foreignNldesignNames']) . ')';
			}

			if ($result['primaryMismatch'] === true) {
				$reasons[] = 'primary colour ' . (string)$result['cssPrimary']
					. ' disagrees with token-sets.json ' . (string)$result['declaredPrimary'];
			}

			$offenders[] = $result['id'] . ': ' . implode('; ', $reasons);
		}//end foreach

		$this->assertSame(
			[],
			$offenders,
			"These shipped token sets are vocabulary-incomplete and NOT allow-listed.\n"
			. "Fix the set, or (only for a set the converter has yet to regenerate)\n"
			. "add its id to tests/Unit/fixtures/token-set-vocabulary-allowlist.json:\n  - "
			. implode("\n  - ", $offenders)
		);
	}//end testEveryShippedSetIsCompleteOrAllowListed()

	/**
	 * The allow-list can only shrink: an id that has started passing MUST be
	 * deleted from it, so progress is recorded rather than hidden.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
	 */
	public function testAllowlistHasNoStaleEntries(): void {
		$results = $this->service()->auditAll($this->repoRoot());

		$stillFailing = [];
		foreach ($results as $result) {
			if ($result['auditable'] === true && $result['complete'] === false) {
				$stillFailing[] = $result['id'];
			}
		}

		$stale = array_values(array_diff($this->allowlist(), $stillFailing));

		$this->assertSame(
			[],
			$stale,
			'These allow-list entries now pass the vocabulary audit and MUST be deleted from '
			. "tests/Unit/fixtures/token-set-vocabulary-allowlist.json:\n  - " . implode("\n  - ", $stale)
		);
	}//end testAllowlistHasNoStaleEntries()

	/**
	 * The gate is not vacuous: the rules must actually be able to pass and to
	 * fail. A vocabulary or required-token list broken in a way that fails (or
	 * passes) everything would otherwise look green forever.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
	 */
	public function testAuditDistinguishesCompleteFromIncompleteSets(): void {
		$results = $this->service()->auditAll($this->repoRoot());

		$complete = [];
		$incomplete = [];
		foreach ($results as $result) {
			if ($result['auditable'] === false) {
				continue;
			}

			if ($result['complete'] === true) {
				$complete[] = $result['id'];
				continue;
			}

			$incomplete[] = $result['id'];
		}

		$this->assertNotEmpty(
			$complete,
			'No shipped set passes the vocabulary audit — the required-token list or the '
			. 'vocabulary scan is broken, not all 48 sets.'
		);
		$this->assertNotEmpty(
			$incomplete,
			'Every shipped set passes — either every set has been regenerated (then empty the allow-list '
			. 'and delete this assertion) or the audit has stopped auditing anything.'
		);
		$this->assertContains(
			'rijkshuisstijl',
			array_merge($complete, $incomplete),
			'The default token set must always be audited.'
		);
	}//end testAuditDistinguishesCompleteFromIncompleteSets()

	/**
	 * A set whose design system reads no `--nldesign-*` token is reported as
	 * not auditable rather than as failing — `none` (stock Nextcloud) and
	 * `summer-breeze` (its own stack references no `--nldesign-*` name).
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
	 */
	public function testSetsOfNonConsumingDesignSystemsAreNotAudited(): void {
		$service = $this->service();
		$consuming = $service->nldesignConsumingSystems($this->repoRoot());

		$this->assertNotContains('none', $consuming, 'The "none" design system loads no stylesheet at all.');
		$this->assertContains('nldesign', $consuming, 'The nldesign stack reads the --nldesign-* vocabulary.');

		foreach ($service->auditAll($this->repoRoot()) as $result) {
			if (\in_array($result['designSystem'], $consuming, true) === true) {
				continue;
			}

			$this->assertFalse($result['auditable'], $result['id'] . ' must not be audited against a vocabulary its design system never reads.');
			$this->assertTrue($result['complete'], $result['id'] . ' must not be reported as incomplete when it is not audited.');
			$this->assertSame([], $result['missingRequired'], $result['id'] . ' must report no missing tokens when it is not audited.');
		}
	}//end testSetsOfNonConsumingDesignSystemsAreNotAudited()

	/**
	 * The Node CLI (`npm run audit:token-sets`) and this service MUST require
	 * the same tokens. The two lists are duplicated by design — one per runtime,
	 * no build step between them — so the duplication is guarded here rather
	 * than trusted.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
	 */
	public function testNodeMirrorRequiresTheSameTokens(): void {
		$script = (string)file_get_contents($this->repoRoot() . '/scripts/audit-token-sets.mjs');
		$this->assertNotSame('', $script, 'scripts/audit-token-sets.mjs must be readable.');

		$this->assertSame(
			1,
			preg_match('/const REQUIRED_TOKENS = \[(.*?)\]/s', $script, $block),
			'scripts/audit-token-sets.mjs must declare a REQUIRED_TOKENS array.'
		);

		preg_match_all("/'(--nldesign-[a-z0-9-]+)'/", $block[1], $matches);

		$this->assertSame(
			TokenSetVocabularyAuditService::REQUIRED_TOKENS,
			$matches[1],
			'scripts/audit-token-sets.mjs REQUIRED_TOKENS has drifted from '
			. 'TokenSetVocabularyAuditService::REQUIRED_TOKENS — update both.'
		);
	}//end testNodeMirrorRequiresTheSameTokens()

	/**
	 * Every required token is a name the app's own CSS layers can actually
	 * consume — the required list is a subset of the declared vocabulary, so
	 * the audit can never demand a token nothing reads.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
	 */
	public function testRequiredTokensAreThemselvesInTheVocabulary(): void {
		$vocabulary = $this->service()->declaredVocabulary($this->repoRoot());

		$orphans = array_values(array_diff(TokenSetVocabularyAuditService::REQUIRED_TOKENS, $vocabulary));

		$this->assertSame(
			[],
			$orphans,
			'These required tokens are not declared or read by any CSS layer, so requiring '
			. "them would be meaningless:\n  - " . implode("\n  - ", $orphans)
		);
	}//end testRequiredTokensAreThemselvesInTheVocabulary()
}//end class
