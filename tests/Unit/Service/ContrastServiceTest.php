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
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->contrast = new ContrastService();
	}//end setUp()

	/**
	 * Black on white is the maximum 21:1 ratio.
	 */
	public function testBlackOnWhiteIsMaxRatio(): void {
		$ratio = $this->contrast->ratio(first: [0, 0, 0], second: [255, 255, 255]);
		$this->assertEqualsWithDelta(21.0, $ratio, 0.01);
	}//end testBlackOnWhiteIsMaxRatio()

	/**
	 * Identical colours give a 1:1 ratio.
	 */
	public function testIdenticalColoursAreOneToOne(): void {
		$ratio = $this->contrast->ratio(first: [120, 120, 120], second: [120, 120, 120]);
		$this->assertEqualsWithDelta(1.0, $ratio, 0.001);
	}//end testIdenticalColoursAreOneToOne()

	/**
	 * A compliant primary/primary-text pair produces no warnings.
	 */
	public function testCompliantPairProducesNoWarning(): void {
		// #154273 on #ffffff is well above 4.5:1.
		$warnings = $this->contrast->check(
			declarations: [
				'--nldesign-color-primary' => '#154273',
				'--nldesign-color-primary-text' => '#ffffff',
			]
		);

		$this->assertSame([], $warnings);
	}//end testCompliantPairProducesNoWarning()

	/**
	 * A low-contrast pair produces a warning carrying the ratio and threshold.
	 */
	public function testLowContrastPairProducesWarning(): void {
		// #cccccc text on #ffffff primary is ~1.6:1 — well below 4.5:1.
		$warnings = $this->contrast->check(
			declarations: [
				'--nldesign-color-primary' => '#ffffff',
				'--nldesign-color-primary-text' => '#cccccc',
			]
		);

		$this->assertCount(1, $warnings);
		$this->assertSame(4.5, $warnings[0]['threshold']);
		$this->assertSame('AA', $warnings[0]['level']);
		$this->assertLessThan(4.5, $warnings[0]['ratio']);
		$this->assertArrayNotHasKey('unevaluated', $warnings[0]);
	}//end testLowContrastPairProducesWarning()

	/**
	 * A non-literal value (var()) is reported as unevaluated, never as passing.
	 */
	public function testNonLiteralValueIsUnevaluated(): void {
		$warnings = $this->contrast->check(
			declarations: [
				'--nldesign-color-primary' => 'var(--some-other)',
				'--nldesign-color-primary-text' => '#ffffff',
			]
		);

		$this->assertCount(1, $warnings);
		$this->assertTrue($warnings[0]['unevaluated']);
		$this->assertNull($warnings[0]['ratio']);
	}//end testNonLiteralValueIsUnevaluated()

	/**
	 * parseColor handles #rgb, #rrggbb and rgb()/rgba() literals.
	 */
	public function testParseColorSupportsCommonLiterals(): void {
		$this->assertSame([0, 0, 0], $this->contrast->parseColor(value: '#000'));
		$this->assertSame([255, 255, 255], $this->contrast->parseColor(value: '#ffffff'));
		$this->assertSame([21, 66, 115], $this->contrast->parseColor(value: '#154273'));
		$this->assertSame([0, 123, 199], $this->contrast->parseColor(value: 'rgb(0, 123, 199)'));
		$this->assertSame([0, 123, 199], $this->contrast->parseColor(value: 'rgba(0, 123, 199, 0.5)'));
	}//end testParseColorSupportsCommonLiterals()

	/**
	 * Thematiq#696: faint text is measured as it renders. `rgba(0, 0, 0, 0.2)`
	 * on white blends to #cccccc and must fail AA, although opaque black passes.
	 */
	public function testFaintTextFailsAfterBlend(): void {
		$warnings = $this->contrast->check(
			declarations: [
				'--nldesign-color-primary-text' => 'rgba(0, 0, 0, 0.2)',
				'--nldesign-color-primary' => '#ffffff',
			]
		);

		$this->assertCount(1, $warnings);
		$this->assertArrayNotHasKey('unevaluated', $warnings[0]);
		$this->assertSame(
			round($this->contrast->ratio(first: [204, 204, 204], second: [255, 255, 255]), 2),
			$warnings[0]['ratio']
		);
	}//end testFaintTextFailsAfterBlend()

	/**
	 * Thematiq#696: an 8-digit hex gets a ratio and a verdict, not `unevaluated`.
	 */
	public function testEightDigitHexIsEvaluated(): void {
		$failing = $this->contrast->check(
			declarations: [
				'--nldesign-color-primary-text' => '#00000033',
				'--nldesign-color-primary' => '#ffffff',
			]
		);
		$this->assertCount(1, $failing);
		$this->assertArrayNotHasKey('unevaluated', $failing[0]);
		$this->assertNotNull($failing[0]['ratio']);

		$passing = $this->contrast->check(
			declarations: [
				'--nldesign-color-primary-text' => '#000000cc',
				'--nldesign-color-primary' => '#ffffff',
			]
		);
		$this->assertSame([], $passing);
	}//end testEightDigitHexIsEvaluated()

	/**
	 * Thematiq#696: a translucent background is blended over the page
	 * background first. A 10% blue behind white text is nearly white.
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
		$this->assertContains('--nldesign-color-primary-text vs --nldesign-color-primary', $pairs);
	}//end testTranslucentBackgroundIsBlendedOverThePage()

	/**
	 * Thematiq#696: evaluate() blends a translucent candidate over the background too.
	 */
	public function testEvaluateBlendsATranslucentCandidate(): void {
		$results = $this->contrast->evaluate(
			candidates: [['name' => 'faint', 'value' => 'rgba(0, 0, 0, 0.2)', 'role' => 'text']],
			background: '#ffffff'
		);

		$this->assertFalse($results[0]['pass']);
		$this->assertLessThan(2.0, $results[0]['ratio']);
	}//end testEvaluateBlendsATranslucentCandidate()

	/**
	 * Thematiq#696: parseColorWithAlpha reads the alpha of every hex and rgba() form.
	 */
	public function testParseColorWithAlphaReadsEveryForm(): void {
		$this->assertSame([0, 0, 0, 1.0], $this->contrast->parseColorWithAlpha(value: '#000'));
		$this->assertSame([255, 255, 255, 1.0], $this->contrast->parseColorWithAlpha(value: '#ffffff'));
		$this->assertSame([0, 0, 0, 0.8], $this->contrast->parseColorWithAlpha(value: '#000000cc'));
		$this->assertSame([255, 0, 0, 0.6], $this->contrast->parseColorWithAlpha(value: '#f009'));
		$this->assertSame([1, 2, 3, 0.5], $this->contrast->parseColorWithAlpha(value: 'rgba(1, 2, 3, 0.5)'));
		$this->assertSame([1, 2, 3, 0.25], $this->contrast->parseColorWithAlpha(value: 'rgba(1, 2, 3, 25%)'));
		$this->assertSame([0, 0, 0], $this->contrast->parseColor(value: '#00000080'));
		$this->assertNull($this->contrast->parseColorWithAlpha(value: 'var(--x)'));
	}//end testParseColorWithAlphaReadsEveryForm()

	/**
	 * parseColor returns null for unparseable values.
	 */
	public function testParseColorReturnsNullForUnparseable(): void {
		$this->assertNull($this->contrast->parseColor(value: 'var(--x)'));
		$this->assertNull($this->contrast->parseColor(value: 'rebeccapurple'));
		$this->assertNull($this->contrast->parseColor(value: 'hsl(200, 50%, 50%)'));
	}//end testParseColorReturnsNullForUnparseable()

	/**
	 * A pair where only one token is present is silently skipped (not evaluable).
	 */
	public function testPartialPairIsSkipped(): void {
		$warnings = $this->contrast->check(
			declarations: ['--nldesign-color-primary' => '#154273']
		);

		$this->assertSame([], $warnings);
	}//end testPartialPairIsSkipped()
}//end class
