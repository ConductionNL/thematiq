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
	 * A sub-AA set is surfaced (verdict fail), never silently passed.
	 *
	 * Every shipped set passes since vng declared its own page background
	 * (`--nldesign-color-background: #ffffff`, its own
	 * `--utrecht-document-background-color`): before that the audit fell back to
	 * `theming.background_color`, which for vng is Nextcloud's LOGIN background
	 * #0277BD, and 2.50:1 against it was the only `fail` in the table. So the
	 * "something can fail" half of this control runs on a probe, exactly as
	 * TokenSetVocabularyTest's does. An audit that has stopped classifying
	 * would pass a shipped-set-only assertion too.
	 *
	 * @spec openspec/changes/archive/2026-07-07-shipped-token-set-contrast-audit/tasks.md#task-2.3
	 */
	public function testSubAaSetsAreSurfacedNotSilentlyPassed(): void {
		$probe = $this->probeAppWithASubAaSet(
			primary: '#777777',
			primaryText: '#888888',
			background: '#8a8a8a'
		);

		$result = $this->service()->auditSet($probe, 'probe', []);

		$this->assertSame('fail', $result['verdict'], 'A probe set below both thresholds must be surfaced as fail.');
		$this->assertNotNull($result['uiRatio'], 'A literal pair must be measured, not reported unevaluated.');
		$this->assertLessThan(
			ShippedTokenSetAuditService::AA_UI,
			$result['uiRatio'],
			'The probe primary/background pair must be below the AA UI threshold.'
		);

		// ...and no shipped set is laundered into a pass: the audit must still
		// classify each one, and the report must carry that verdict.
		foreach ($this->service()->auditAll($this->repoRoot()) as $row) {
			$this->assertContains(
				$row['verdict'],
				['pass', 'fail', 'unevaluated'],
				$row['id'] . ' must carry a computed verdict.'
			);
		}
	}

	/**
	 * A pair written as a `var()` chain is measured, not reported unevaluated.
	 *
	 * conduction-new declares `--nldesign-color-primary:
	 * var(--conduction-color-brand-primary)` and its background through a
	 * second indirection. Both pairs read `unevaluated` until the audit
	 * resolved the chain the way a browser does, and `unevaluated` is never a
	 * pass, so the set could not be judged at all.
	 */
	public function testAVarChainPairIsMeasuredRatherThanUnevaluated(): void {
		$manifest = array_column($this->auditableManifest(), null, 'id');
		$this->assertArrayHasKey('conduction-new', $manifest, 'conduction-new must be a shipped set.');

		$declarations = $this->service()->resolveDeclarations(
			$this->repoRoot(),
			'conduction-new',
			($manifest['conduction-new']['theming'] ?? [])
		);

		// The raw file value is an indirection; what the audit measures is not.
		$this->assertStringContainsString(
			'var(',
			(string)$this->rawDeclaration('conduction-new', '--nldesign-color-primary'),
			'This control only means anything while conduction-new writes its primary as a var().'
		);
		$this->assertSame('#21468B', $declarations['--nldesign-color-primary']);

		$result = $this->service()->auditSet(
			$this->repoRoot(),
			'conduction-new',
			($manifest['conduction-new']['theming'] ?? [])
		);
		$this->assertNotNull($result['textRatio']);
		$this->assertNotNull($result['uiRatio']);
		$this->assertSame('pass', $result['verdict']);
	}

	/**
	 * A set that names no background at all is measured against the white page
	 * the rest of the app already assumes, not left unevaluated forever.
	 *
	 * purmerend declares neither `--nldesign-color-background` nor
	 * `theming.background_color`, and its upstream theme carries no page
	 * background token to derive one from, so before the terminus its UI pair
	 * had no second colour.
	 */
	public function testASetWithNoBackgroundIsMeasuredAgainstTheWhitePage(): void {
		$this->assertNull(
			$this->rawDeclaration('purmerend', '--nldesign-color-background'),
			'This control only means anything while purmerend declares no background.'
		);

		$declarations = $this->service()->resolveDeclarations($this->repoRoot(), 'purmerend', []);

		$this->assertSame(
			ShippedTokenSetAuditService::PAGE_BACKGROUND,
			$declarations['--nldesign-color-background']
		);
		$this->assertSame('pass', $this->service()->auditSet($this->repoRoot(), 'purmerend', [])['verdict']);
	}

	/**
	 * vng's primary is measured against vng's own page, not against Nextcloud's
	 * login background. The set declares the page itself so the verdict does
	 * not depend on a fallback, and the login colour is unchanged.
	 */
	public function testVngIsMeasuredAgainstItsOwnPageNotTheLoginBackground(): void {
		$manifest = array_column($this->auditableManifest(), null, 'id');
		$theming = ($manifest['vng']['theming'] ?? []);

		$this->assertSame('#0277BD', ($theming['background_color'] ?? null), 'vng keeps its login background.');
		$this->assertSame('#ffffff', $this->rawDeclaration('vng', '--nldesign-color-background'));

		$result = $this->service()->auditSet($this->repoRoot(), 'vng', $theming);

		$this->assertSame('pass', $result['verdict']);
		$this->assertGreaterThanOrEqual(ShippedTokenSetAuditService::AA_UI, $result['uiRatio']);
	}

	/**
	 * Read one declaration straight out of a shipped set file, with no layers
	 * and no resolution — so a test can state what the FILE says apart from
	 * what the audit computes.
	 *
	 * @param string $id    The token set id.
	 * @param string $token The custom-property name.
	 *
	 * @return string|null The raw value, or null when the set does not declare it.
	 */
	private function rawDeclaration(string $id, string $token): ?string {
		$css = (string)file_get_contents($this->repoRoot() . '/css/tokens/' . $id . '.css');
		$declarations = ((new CssParserService())->parseDeclarations(content: $css) ?? []);

		return ($declarations[$token] ?? null);
	}

	/**
	 * A throwaway app root with one token set whose colours genuinely fail.
	 *
	 * @param string $primary     The probe primary colour.
	 * @param string $primaryText The probe primary text colour.
	 * @param string $background  The probe page background.
	 *
	 * @return string The probe app path.
	 */
	private function probeAppWithASubAaSet(string $primary, string $primaryText, string $background): string {
		$probe = sys_get_temp_dir() . '/thematiq-contrast-probe-' . getmypid();
		@mkdir($probe . '/css/tokens', 0777, true);
		@mkdir($probe . '/css/systems/nldesign', 0777, true);
		file_put_contents(
			$probe . '/css/tokens/probe.css',
			sprintf(
				":root {\n\t--nldesign-color-primary: %s;\n\t--nldesign-color-primary-text: %s;\n\t--nldesign-color-background: %s;\n}\n",
				$primary,
				$primaryText,
				$background
			)
		);

		return $probe;
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
