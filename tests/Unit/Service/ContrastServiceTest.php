<?php

/**
 * Unit tests for ContrastService.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-5.3
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the WCAG 2.1 contrast service.
 *
 * Covers tasks.md#task-5.3: known-ratio fixtures, the boundary 4.5:1, and the
 * `unevaluated` path for non-literal values.
 */
class ContrastServiceTest extends TestCase {

	/**
	 * The service under test.
	 *
	 * @var ContrastService
	 */
	private ContrastService $contrast;

	/**
	 * Set up the service before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->contrast = new ContrastService();
	}//end setUp()

	/**
	 * Black on white is the maximum 21:1 ratio.
	 *
	 * @return void
	 */
	public function testBlackOnWhiteIsMaxRatio(): void {
		$ratio = $this->contrast->ratio(first: [0, 0, 0], second: [255, 255, 255]);
		$this->assertEqualsWithDelta(expected: 21.0, actual: $ratio, delta: 0.01);
	}//end testBlackOnWhiteIsMaxRatio()

	/**
	 * Identical colours give a 1:1 ratio.
	 *
	 * @return void
	 */
	public function testIdenticalColoursAreOneToOne(): void {
		$ratio = $this->contrast->ratio(first: [120, 120, 120], second: [120, 120, 120]);
		$this->assertEqualsWithDelta(expected: 1.0, actual: $ratio, delta: 0.001);
	}//end testIdenticalColoursAreOneToOne()

	/**
	 * A compliant primary/primary-text pair produces no warnings.
	 *
	 * @return void
	 */
	public function testCompliantPairProducesNoWarning(): void {
		// #154273 on #ffffff is well above 4.5:1.
		$warnings = $this->contrast->check(
			declarations: [
				'--nldesign-color-primary' => '#154273',
				'--nldesign-color-primary-text' => '#ffffff',
			]
		);

		$this->assertSame(expected: [], actual: $warnings);
	}//end testCompliantPairProducesNoWarning()

	/**
	 * A low-contrast pair produces a warning carrying the ratio and threshold.
	 *
	 * @return void
	 */
	public function testLowContrastPairProducesWarning(): void {
		// #cccccc text on #ffffff primary is ~1.6:1 — well below 4.5:1.
		$warnings = $this->contrast->check(
			declarations: [
				'--nldesign-color-primary' => '#ffffff',
				'--nldesign-color-primary-text' => '#cccccc',
			]
		);

		$this->assertCount(expectedCount: 1, haystack: $warnings);
		$this->assertSame(expected: 4.5, actual: $warnings[0]['threshold']);
		$this->assertSame(expected: 'AA', actual: $warnings[0]['level']);
		$this->assertLessThan(expected: 4.5, actual: $warnings[0]['ratio']);
		$this->assertArrayNotHasKey(key: 'unevaluated', array: $warnings[0]);
	}//end testLowContrastPairProducesWarning()

	/**
	 * A non-literal value (var()) is reported as unevaluated, never as passing.
	 *
	 * @return void
	 */
	public function testNonLiteralValueIsUnevaluated(): void {
		$warnings = $this->contrast->check(
			declarations: [
				'--nldesign-color-primary' => 'var(--some-other)',
				'--nldesign-color-primary-text' => '#ffffff',
			]
		);

		$this->assertCount(expectedCount: 1, haystack: $warnings);
		$this->assertTrue(condition: $warnings[0]['unevaluated']);
		$this->assertNull(actual: $warnings[0]['ratio']);
	}//end testNonLiteralValueIsUnevaluated()

	/**
	 * parseColor handles #rgb, #rrggbb and rgb()/rgba() literals.
	 *
	 * @return void
	 */
	public function testParseColorSupportsCommonLiterals(): void {
		$this->assertSame(expected: [0, 0, 0], actual: $this->contrast->parseColor(value: '#000'));
		$this->assertSame(expected: [255, 255, 255], actual: $this->contrast->parseColor(value: '#ffffff'));
		$this->assertSame(expected: [21, 66, 115], actual: $this->contrast->parseColor(value: '#154273'));
		$this->assertSame(expected: [0, 123, 199], actual: $this->contrast->parseColor(value: 'rgb(0, 123, 199)'));
		$this->assertSame(expected: [0, 123, 199], actual: $this->contrast->parseColor(value: 'rgba(0, 123, 199, 0.5)'));
	}//end testParseColorSupportsCommonLiterals()

	/**
	 * Thematiq#696: faint text is measured as it renders. `rgba(0, 0, 0, 0.2)`
	 * on white blends to #cccccc and must fail AA, although opaque black passes.
	 *
	 * @return void
	 */
	public function testFaintTextFailsAfterBlend(): void {
		$warnings = $this->contrast->check(
			declarations: [
				'--nldesign-color-primary-text' => 'rgba(0, 0, 0, 0.2)',
				'--nldesign-color-primary' => '#ffffff',
			]
		);

		$this->assertCount(expectedCount: 1, haystack: $warnings);
		$this->assertArrayNotHasKey(key: 'unevaluated', array: $warnings[0]);
		$this->assertSame(
			expected: round($this->contrast->ratio(first: [204, 204, 204], second: [255, 255, 255]), 2),
			actual: $warnings[0]['ratio']
		);
	}//end testFaintTextFailsAfterBlend()

	/**
	 * Thematiq#696: an 8-digit hex gets a ratio and a verdict, not `unevaluated`.
	 *
	 * @return void
	 */
	public function testEightDigitHexIsEvaluated(): void {
		$failing = $this->contrast->check(
			declarations: [
				'--nldesign-color-primary-text' => '#00000033',
				'--nldesign-color-primary' => '#ffffff',
			]
		);
		$this->assertCount(expectedCount: 1, haystack: $failing);
		$this->assertArrayNotHasKey(key: 'unevaluated', array: $failing[0]);
		$this->assertNotNull(actual: $failing[0]['ratio']);

		$passing = $this->contrast->check(
			declarations: [
				'--nldesign-color-primary-text' => '#000000cc',
				'--nldesign-color-primary' => '#ffffff',
			]
		);
		$this->assertSame(expected: [], actual: $passing);
	}//end testEightDigitHexIsEvaluated()

	/**
	 * Thematiq#696: a translucent background is blended over the page
	 * background first. A 10% blue behind white text is nearly white.
	 *
	 * @return void
	 */
	public function testTranslucentBackgroundIsBlendedOverThePage(): void {
		$warnings = $this->contrast->check(
			declarations: [
				'--nldesign-color-primary-text' => '#ffffff',
				'--nldesign-color-primary' => 'rgba(21, 66, 115, 0.1)',
				'--nldesign-color-background' => '#ffffff',
			]
		);

		$pairs = array_column($warnings, 'pair');
		$this->assertContains(needle: '--nldesign-color-primary-text vs --nldesign-color-primary', haystack: $pairs);
	}//end testTranslucentBackgroundIsBlendedOverThePage()

	/**
	 * Thematiq#696: evaluate() blends a translucent candidate over the background too.
	 *
	 * @return void
	 */
	public function testEvaluateBlendsATranslucentCandidate(): void {
		$results = $this->contrast->evaluate(
			candidates: [['name' => 'faint', 'value' => 'rgba(0, 0, 0, 0.2)', 'role' => 'text']],
			background: '#ffffff'
		);

		$this->assertFalse(condition: $results[0]['pass']);
		$this->assertLessThan(expected: 2.0, actual: $results[0]['ratio']);
	}//end testEvaluateBlendsATranslucentCandidate()

	/**
	 * Thematiq#696: parseColorWithAlpha reads the alpha of every hex and rgba() form.
	 *
	 * @return void
	 */
	public function testParseColorWithAlphaReadsEveryForm(): void {
		$this->assertSame(expected: [0, 0, 0, 1.0], actual: $this->contrast->parseColorWithAlpha(value: '#000'));
		$this->assertSame(expected: [255, 255, 255, 1.0], actual: $this->contrast->parseColorWithAlpha(value: '#ffffff'));
		$this->assertSame(expected: [0, 0, 0, 0.8], actual: $this->contrast->parseColorWithAlpha(value: '#000000cc'));
		$this->assertSame(expected: [255, 0, 0, 0.6], actual: $this->contrast->parseColorWithAlpha(value: '#f009'));
		$this->assertSame(expected: [1, 2, 3, 0.5], actual: $this->contrast->parseColorWithAlpha(value: 'rgba(1, 2, 3, 0.5)'));
		$this->assertSame(expected: [1, 2, 3, 0.25], actual: $this->contrast->parseColorWithAlpha(value: 'rgba(1, 2, 3, 25%)'));
		$this->assertSame(expected: [0, 0, 0], actual: $this->contrast->parseColor(value: '#00000080'));
		$this->assertNull(actual: $this->contrast->parseColorWithAlpha(value: 'var(--x)'));
	}//end testParseColorWithAlphaReadsEveryForm()

	/**
	 * parseColor returns null for unparseable values.
	 *
	 * @return void
	 */
	public function testParseColorReturnsNullForUnparseable(): void {
		$this->assertNull(actual: $this->contrast->parseColor(value: 'var(--x)'));
		$this->assertNull(actual: $this->contrast->parseColor(value: 'rebeccapurple'));
		$this->assertNull(actual: $this->contrast->parseColor(value: 'hsl(200, 50%, 50%)'));
	}//end testParseColorReturnsNullForUnparseable()

	/**
	 * A pair where only one token is present is silently skipped (not evaluable).
	 *
	 * @return void
	 */
	public function testPartialPairIsSkipped(): void {
		$warnings = $this->contrast->check(
			declarations: ['--nldesign-color-primary' => '#154273']
		);

		$this->assertSame(expected: [], actual: $warnings);
	}//end testPartialPairIsSkipped()

	/**
	 * evaluate() answers every candidate in order: text at 4.5, anything else at 3, and an unmeasurable one unevaluated.
	 *
	 * @return void
	 */
	public function testEvaluateAnswersEveryCandidateExactly(): void {
		$results = $this->contrast->evaluate(
			candidates: [
				['name' => 'a', 'role' => 'text', 'value' => '#000000'],
				['name' => 'b', 'role' => 'ui', 'value' => '#777777'],
				['name' => 'c', 'role' => 'text', 'value' => 'var(--x)'],
				['name' => 'd', 'role' => 'text', 'value' => '#fff'],
			],
			background: '#ffffff'
		);

		$this->assertSame(
			expected: [
				['name' => 'a', 'ratio' => 21.0, 'threshold' => 4.5, 'level' => 'AA', 'pass' => true],
				['name' => 'b', 'ratio' => 4.48, 'threshold' => 3.0, 'level' => 'AA', 'pass' => true],
				['name' => 'c', 'ratio' => null, 'threshold' => 4.5, 'level' => 'AA', 'pass' => false, 'unevaluated' => true],
				['name' => 'd', 'ratio' => 1.0, 'threshold' => 4.5, 'level' => 'AA', 'pass' => false],
			],
			actual: $results
		);
	}//end testEvaluateAnswersEveryCandidateExactly()
}//end class
