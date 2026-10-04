<?php

/**
 * Shipped token-set contrast audit — PHPUnit inventory gate.
 *
 * Runs the existing ContrastService over every shipped token set (mirroring the
 * tests/Unit/IconAssetsTest.php static-inventory pattern) so the WCAG-AA claims
 * in docs/GOVERNMENT-FEATURES.md (F-09, A-01..A-05) are true by construction
 * rather than by prose. Asserts that every audited set yields a computed verdict,
 * that unevaluated pairs are never treated as passing, that the sets the
 * documentation presents as AA-compliant actually meet AA, and that the generated
 * docs/reference/contrast-report.md is deterministic and covers every set.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/archive/2026-07-07-shipped-token-set-contrast-audit/tasks.md#task-2.1
 * @spec openspec/changes/archive/2026-07-07-shipped-token-set-contrast-audit/tasks.md#task-2.2
 * @spec openspec/changes/archive/2026-07-07-shipped-token-set-contrast-audit/tasks.md#task-2.3
 * @spec openspec/changes/archive/2026-07-07-shipped-token-set-contrast-audit/tasks.md#task-2.4
 * @spec openspec/changes/archive/2026-07-07-shipped-token-set-contrast-audit/tasks.md#task-3.1
 * @spec openspec/changes/archive/2026-07-07-shipped-token-set-contrast-audit/tasks.md#task-3.2
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use PHPUnit\Framework\TestCase;

/**
 * Static contrast-inventory regression test (no Nextcloud runtime required).
 */
class TokenSetContrastAuditTest extends TestCase {
	/**
	 * The token sets the documentation explicitly presents as WCAG-AA compliant
	 * (the five hand-reviewed sets in docs/reference/token-audit.md). These MUST
	 * meet AA or the gate fails.
	 *
	 * @var array<int, string>
	 */
	private const DOCUMENTED_AA_SETS = ['rijkshuisstijl', 'utrecht', 'amsterdam', 'denhaag', 'rotterdam'];

	/**
	 * Repository root, derived from this test file's location.
	 */
	private function repoRoot(): string {
		return \dirname(__DIR__, 2);
	}

	/**
	 * Build the audit service from the real (pure) collaborators.
	 */
	private function service(): ShippedTokenSetAuditService {
		return new ShippedTokenSetAuditService(new ContrastService(), new CssParserService());
	}

	/**
	 * The manifest entries for every token set with a non-`none` design system.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function auditableManifest(): array {
		$json = json_decode((string)file_get_contents($this->repoRoot() . '/token-sets.json'), true);
		$this->assertIsArray($json, 'token-sets.json must decode to an array.');

		return array_values(array_filter(
			$json,
			static fn (array $s): bool => (($s['design_system'] ?? 'nldesign') !== 'none')
		));
	}

	/**
	 * Every audited set yields a computed verdict for both fixed pairs.
	 *
	 * @spec openspec/changes/archive/2026-07-07-shipped-token-set-contrast-audit/tasks.md#task-2.1
	 * @spec openspec/changes/archive/2026-07-07-shipped-token-set-contrast-audit/tasks.md#task-2.2
	 */
	public function testEveryAuditedSetYieldsAVerdict(): void {
		$service = $this->service();
		$manifest = $this->auditableManifest();
		$this->assertNotEmpty($manifest, 'There must be at least one auditable token set.');

		foreach ($manifest as $set) {
			$result = $service->auditSet(
				$this->repoRoot(),
				(string)$set['id'],
				($set['theming'] ?? [])
			);

			$this->assertContains(
				$result['verdict'],
				['pass', 'fail', 'unevaluated'],
				"Set {$set['id']} must classify to pass/fail/unevaluated."
			);

			// A resolved literal pair yields a numeric ratio; a non-literal pair is
			// reported unevaluated (null) — never silently omitted.
			if ($result['verdict'] !== 'unevaluated') {
				$this->assertIsFloat(
					$result['textRatio'],
					"Set {$set['id']} primary/text pair must yield a computed ratio."
				);
				$this->assertIsFloat(
					$result['uiRatio'],
					"Set {$set['id']} primary/background pair must yield a computed ratio."
				);
			}
		}
	}

	/**
	 * An unevaluated pair is never classified as passing.
	 *
	 * @spec openspec/changes/archive/2026-07-07-shipped-token-set-contrast-audit/tasks.md#task-2.4
	 */
	public function testUnevaluatedIsNeverPassing(): void {
		$service = $this->service();

		// A synthetic set whose primary references a var() cannot be resolved to a
		// literal, so its verdict must be `unevaluated`, not `pass`.
		$result = $service->auditSet(
			$this->repoRoot(),
			'__nonexistent_set_forcing_unresolved__',
			['background_color' => 'var(--whatever)']
		);

		$this->assertNotSame('pass', $result['verdict'], 'A non-literal pair must never be classified as passing.');
	}

	/**
	 * Every set the documentation presents as WCAG-AA compliant actually meets AA.
	 *
	 * @spec openspec/changes/archive/2026-07-07-shipped-token-set-contrast-audit/tasks.md#task-2.3
	 */
	public function testDocumentedAaSetsMeetAa(): void {
		$service = $this->service();
		$manifest = array_column($this->auditableManifest(), null, 'id');

		foreach (self::DOCUMENTED_AA_SETS as $id) {
			$this->assertArrayHasKey($id, $manifest, "Documented AA set '{$id}' must exist in token-sets.json.");

			$result = $service->auditSet($this->repoRoot(), $id, ($manifest[$id]['theming'] ?? []));

			$this->assertSame(
				'pass',
				$result['verdict'],
				sprintf(
					"Documented AA set '%s' must meet WCAG AA: primary/text %s (need >= %s), primary/bg %s (need >= %s).",
					$id,
					(string)($result['textRatio'] ?? 'unevaluated'),
					(string)$result['textThreshold'],
					(string)($result['uiRatio'] ?? 'unevaluated'),
					(string)$result['uiThreshold']
				)
			);
		}
	}

	/**
	 * Sub-AA community sets are surfaced (verdict fail), never silently passed.
	 *
	 * @spec openspec/changes/archive/2026-07-07-shipped-token-set-contrast-audit/tasks.md#task-2.3
	 */
	public function testSubAaSetsAreSurfacedNotSilentlyPassed(): void {
		$service = $this->service();
		$manifest = array_column($this->auditableManifest(), null, 'id');

		// vng (primary/bg below 3.0) is a known sub-AA community set: the audit must
		// classify it `fail`, and the report must record that fact rather than
		// launder it into a pass. noaberkracht left this list when the token sync
		// converted it with the contrast repair (thematiq#993, Ruben's decision).
		foreach (['vng'] as $id) {
			if (isset($manifest[$id]) === false) {
				continue;
			}

			$result = $service->auditSet($this->repoRoot(), $id, ($manifest[$id]['theming'] ?? []));
			$this->assertSame('fail', $result['verdict'], "Sub-AA set '{$id}' must be surfaced as fail.");
		}
	}

	/**
	 * A set tagged high-contrast must meet WCAG AAA (>= 7:1 text, >= 4.5:1 UI).
	 *
	 * @spec openspec/changes/archive/2026-07-07-high-contrast-token-set/tasks.md#task-4.1
	 */
	public function testHighContrastSetMeetsAaa(): void {
		$manifest = array_column($this->auditableManifest(), null, 'id');
		if (isset($manifest['hoog-contrast']) === false) {
			$this->markTestSkipped('hoog-contrast set not present.');
		}

		$this->assertSame(
			'high-contrast',
			$manifest['hoog-contrast']['design_system'] ?? null,
			'hoog-contrast must be bound to the high-contrast design system.'
		);

		// auditAll applies the AAA thresholds automatically for high-contrast sets.
		$rows = array_column($this->service()->auditAll($this->repoRoot()), null, 'id');
		$row = $rows['hoog-contrast'];

		$this->assertSame(7.0, $row['textThreshold'], 'hoog-contrast must be audited at the AAA text threshold.');
		$this->assertSame(4.5, $row['uiThreshold'], 'hoog-contrast must be audited at the AAA UI threshold.');
		$this->assertSame(
			'pass',
			$row['verdict'],
			sprintf(
				'hoog-contrast must meet AAA: primary/text %s (>= 7), primary/bg %s (>= 4.5).',
				(string)($row['textRatio'] ?? 'unevaluated'),
				(string)($row['uiRatio'] ?? 'unevaluated')
			)
		);
		$this->assertGreaterThanOrEqual(7.0, $row['textRatio'], 'hoog-contrast primary/text must be >= 7:1.');
		$this->assertGreaterThanOrEqual(4.5, $row['uiRatio'], 'hoog-contrast primary/background must be >= 4.5:1.');
	}

	/**
	 * The generated report is byte-identical on regeneration and covers every set.
	 *
	 * @spec openspec/changes/archive/2026-07-07-shipped-token-set-contrast-audit/tasks.md#task-3.1
	 * @spec openspec/changes/archive/2026-07-07-shipped-token-set-contrast-audit/tasks.md#task-3.2
	 */
	public function testReportIsDeterministicAndComplete(): void {
		$service = $this->service();

		$first = $service->renderReport($this->repoRoot());
		$second = $service->renderReport($this->repoRoot());
		$this->assertSame($first, $second, 'The contrast report must be deterministic (byte-identical regeneration).');

		// Every audited set must appear as a row.
		foreach ($this->auditableManifest() as $set) {
			$this->assertMatchesRegularExpression(
				'/^\| ' . preg_quote((string)$set['id'], '/') . ' \|/m',
				$first,
				"Report must contain a row for set '{$set['id']}'."
			);
		}

		// The committed report must be up to date with the current token files.
		$committed = file_get_contents($this->repoRoot() . '/docs/reference/contrast-report.md');
		$this->assertSame(
			$first,
			$committed,
			'docs/reference/contrast-report.md is stale. Run: composer docs:contrast-report'
		);
	}
}
