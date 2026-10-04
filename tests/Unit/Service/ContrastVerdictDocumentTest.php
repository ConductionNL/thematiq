<?php

/**
 * Unit tests for ContrastVerdictDocument: the machine-readable half of the
 * shipped contrast report.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * `scripts/audit-token-sets.mjs` reads this document instead of reimplementing
 * WCAG relative luminance in a second language, so what it must guarantee is
 * narrow and exact: the same verdicts the markdown report carries, in a stable
 * order, every time.
 *
 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-reproducible-contrast-report
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastVerdictDocument;
use PHPUnit\Framework\TestCase;

/**
 * The verdict document renders deterministically and keeps every field the
 * Node audit reads.
 */
class ContrastVerdictDocumentTest extends TestCase {

	/**
	 * One audit row, in the shape `ShippedTokenSetAuditService::auditAll()`
	 * returns.
	 *
	 * @param string $id The set id.
	 * @param string $verdict The verdict.
	 * @param float|null $uiRatio The primary/background ratio.
	 *
	 * @return array<string, mixed> The row.
	 */
	private function row(string $id, string $verdict, ?float $uiRatio = 5.0): array {
		return [
			'id' => $id,
			'verdict' => $verdict,
			'textRatio' => 7.5,
			'uiRatio' => $uiRatio,
			'textThreshold' => 4.5,
			'uiThreshold' => 3.0,
		];
	}//end row()

	/**
	 * Every field the Node coverage audit reads is carried through, keyed by
	 * set id.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-reproducible-contrast-report
	 */
	public function testEveryVerdictFieldIsCarriedThrough(): void {
		$rendered = (new ContrastVerdictDocument())->render(
			[$this->row('amsterdam', 'pass'), $this->row('vng', 'fail', 2.5)]
		);

		$decoded = json_decode($rendered, true);
		$this->assertIsArray($decoded);
		$this->assertStringContainsString('do not edit by hand', (string)$decoded['$comment']);
		$this->assertSame(['amsterdam', 'vng'], array_keys($decoded['sets']));
		$this->assertSame('pass', $decoded['sets']['amsterdam']['verdict']);
		$this->assertSame('fail', $decoded['sets']['vng']['verdict']);
		$this->assertSame(2.5, $decoded['sets']['vng']['uiRatio']);
		$this->assertSame(7.5, $decoded['sets']['vng']['textRatio']);
		$this->assertSame(4.5, $decoded['sets']['vng']['textThreshold']);
		$this->assertSame(3.0, $decoded['sets']['vng']['uiThreshold']);
	}//end testEveryVerdictFieldIsCarriedThrough()

	/**
	 * An unevaluated pair keeps its null rather than becoming `0`, because a
	 * zero ratio would read as a measured failure and `null` is "not measured".
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-reproducible-contrast-report
	 */
	public function testAnUnmeasuredPairStaysNullRatherThanZero(): void {
		$rendered = (new ContrastVerdictDocument())->render(
			[$this->row('probe', 'unevaluated', null)]
		);

		$this->assertStringContainsString('"uiRatio": null', $rendered);
		$decoded = json_decode($rendered, true);
		$this->assertNull($decoded['sets']['probe']['uiRatio']);
	}//end testAnUnmeasuredPairStaysNullRatherThanZero()

	/**
	 * Rendering is deterministic and newline-terminated, which is what makes
	 * the committed file comparable byte for byte by the staleness check.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-reproducible-contrast-report
	 */
	public function testRenderingIsDeterministicAndNewlineTerminated(): void {
		$document = new ContrastVerdictDocument();
		$rows = [$this->row('b', 'pass'), $this->row('a', 'pass')];

		$first = $document->render($rows);

		$this->assertSame($first, $document->render($rows));
		$this->assertStringEndsWith("\n", $first);
		// Row order is the caller's: auditAll() sorts, this does not re-sort,
		// so a change of ordering there shows up in the file rather than being
		// silently normalised away.
		$this->assertSame(['b', 'a'], array_keys(json_decode($first, true)['sets']));
	}//end testRenderingIsDeterministicAndNewlineTerminated()

	/**
	 * An empty audit renders an empty `sets` object, not a JSON list — the Node
	 * side indexes it by id, and `[]` would make that lookup fail differently
	 * on an empty catalogue than on a full one.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-reproducible-contrast-report
	 */
	public function testAnEmptyAuditRendersAnObjectNotAList(): void {
		$rendered = (new ContrastVerdictDocument())->render([]);

		$this->assertStringContainsString('"sets": {}', $rendered);
		$this->assertSame([], json_decode($rendered, true)['sets']);
	}//end testAnEmptyAuditRendersAnObjectNotAList()
}//end class
