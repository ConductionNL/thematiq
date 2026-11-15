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
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
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
			[$this->row(id: 'amsterdam', verdict: 'pass'), $this->row(id: 'vng', verdict: 'fail', uiRatio: 2.5)]
		);

		$decoded = json_decode($rendered, true);
		$this->assertIsArray(actual: $decoded);
		$this->assertStringContainsString(needle: 'do not edit by hand', haystack: (string)$decoded['$comment']);
		$this->assertSame(expected: ['amsterdam', 'vng'], actual: array_keys($decoded['sets']));
		$this->assertSame(expected: 'pass', actual: $decoded['sets']['amsterdam']['verdict']);
		$this->assertSame(expected: 'fail', actual: $decoded['sets']['vng']['verdict']);
		$this->assertSame(expected: 2.5, actual: $decoded['sets']['vng']['uiRatio']);
		$this->assertSame(expected: 7.5, actual: $decoded['sets']['vng']['textRatio']);
		$this->assertSame(expected: 4.5, actual: $decoded['sets']['vng']['textThreshold']);
		$this->assertSame(expected: 3.0, actual: $decoded['sets']['vng']['uiThreshold']);
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
			[$this->row(id: 'probe', verdict: 'unevaluated', uiRatio: null)]
		);

		$this->assertStringContainsString(needle: '"uiRatio": null', haystack: $rendered);
		$decoded = json_decode($rendered, true);
		$this->assertNull(actual: $decoded['sets']['probe']['uiRatio']);
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
		$rows = [$this->row(id: 'b', verdict: 'pass'), $this->row(id: 'a', verdict: 'pass')];

		$first = $document->render($rows);

		$this->assertSame(expected: $first, actual: $document->render($rows));
		$this->assertStringEndsWith(suffix: "\n", string: $first);
		// Row order is the caller's: auditAll() sorts, this does not re-sort,
		// so a change of ordering there shows up in the file rather than being
		// silently normalised away.
		$this->assertSame(expected: ['b', 'a'], actual: array_keys(json_decode($first, true)['sets']));
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

		$this->assertStringContainsString(needle: '"sets": {}', haystack: $rendered);
		$this->assertSame(expected: [], actual: json_decode($rendered, true)['sets']);
	}//end testAnEmptyAuditRendersAnObjectNotAList()

	/**
	 * The document must name the pairs the audit actually compares.
	 *
	 * READ OFF THE SERVICE, NOT RETYPED. A label that repeats the token names
	 * as literals is a second copy that can drift from the pair being measured,
	 * and a wrong label is worse than none: it reads as fact. Asserting against
	 * the same constants `ShippedTokenSetAuditService::ratioFor()` passes to
	 * `pairRatio()` is what makes the published name true rather than hopeful.
	 *
	 * @return void
	 */
	public function testThePairsNameWhatTheAuditCompared(): void {
		$pairs = json_decode((new ContrastVerdictDocument())->render([]), true)['pairs'];

		$this->assertSame(expected: ShippedTokenSetAuditService::TEXT_PAIR, actual: $pairs['textRatio']);
		$this->assertSame(expected: ShippedTokenSetAuditService::UI_PAIR, actual: $pairs['uiRatio']);

		// Named explicitly too, so a change to BOTH the service and the document
		// still has to be deliberate: the assertion above alone would follow a
		// renamed constant anywhere it went.
		$this->assertSame(
			expected: [
				'foreground' => '--nldesign-color-primary-text',
				'background' => '--nldesign-color-primary',
			],
			actual: $pairs['textRatio'],
			message: 'textRatio is primary-text ON primary, not body ink on the page'
		);
		$this->assertSame(
			expected: [
				'foreground' => '--nldesign-color-primary',
				'background' => '--nldesign-color-background',
			],
			actual: $pairs['uiRatio']
		);
	}//end testThePairsNameWhatTheAuditCompared()
}//end class
