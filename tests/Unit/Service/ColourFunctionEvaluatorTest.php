<?php

/**
 * Tests for the CSS colour function evaluator.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Tests
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/changes/denhaag-component-tokens/specs/token-set-contrast-audit/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ColourFunctionEvaluator;
use PHPUnit\Framework\TestCase;

/**
 * Every expected hex below was computed by hand from the CSS Color 4 formulas,
 * so a wrong channel order or a wrong share fails here.
 */
class ColourFunctionEvaluatorTest extends TestCase {

	/**
	 * The evaluator under test.
	 *
	 * @var ColourFunctionEvaluator
	 */
	private ColourFunctionEvaluator $evaluator;

	/**
	 * Build the evaluator.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->evaluator = new ColourFunctionEvaluator();
	}

	/**
	 * Hsl in its space, comma, deg and alpha spellings.
	 *
	 * @return void
	 */
	public function testHslSpellings(): void {
		$this->assertSame('#ff0000', $this->evaluator->evaluate('hsl(0 100% 50%)'));
		$this->assertSame('#00ff00', $this->evaluator->evaluate('hsl(120, 100%, 50%)'));
		$this->assertSame('#0000ff', $this->evaluator->evaluate('hsl(240deg 100% 50%)'));
		$this->assertSame('#808080', $this->evaluator->evaluate('hsla(0, 0%, 50.2%, 0.5)'));
		$this->assertSame('#ff0000', $this->evaluator->evaluate('hsl(-360 100% 50%)'), 'A negative hue wraps.');
		// westervoort's warning, as the set declares it (Python colorsys agrees).
		$this->assertSame('#917036', $this->evaluator->evaluate('hsl(38deg 46% 39%)'));
	}

	/**
	 * Color-mix with black keeps the hue and drops the lightness by the share.
	 *
	 * @return void
	 */
	public function testColorMixWithBlack(): void {
		$this->assertSame('#713800', $this->evaluator->evaluate('color-mix(in srgb, #e17000 50%, #000)'));
		$this->assertSame('#808080', $this->evaluator->evaluate('color-mix(in srgb, #ffffff 50%, #000000)'));
		$this->assertSame('#800000', $this->evaluator->evaluate('color-mix(in srgb, hsl(0 100% 50%) 50%, #000)'));
		$this->assertSame('#ffffff', $this->evaluator->evaluate('color-mix(in srgb, #ffffff 150%, #000)'), 'A share above 100% is capped.');
	}

	/**
	 * A value it does not know comes back unchanged, never guessed.
	 *
	 * @return void
	 */
	public function testUnknownValuesAreLeftAlone(): void {
		$this->assertSame('#123456', $this->evaluator->evaluate(' #123456 '));
		$this->assertSame('transparent', $this->evaluator->evaluate('transparent'));
		$this->assertSame('color-mix(in oklch, #fff 50%, #000)', $this->evaluator->evaluate('color-mix(in oklch, #fff 50%, #000)'));
		$this->assertSame(
			'color-mix(in srgb, currentcolor 50%, #000)',
			$this->evaluator->evaluate('color-mix(in srgb, currentcolor 50%, #000)'),
			'A mix of a colour it cannot read stays unevaluated.'
		);
	}
}
