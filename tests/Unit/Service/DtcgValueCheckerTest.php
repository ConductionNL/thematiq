<?php

/**
 * Unit tests for DtcgValueChecker.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\DtcgValueChecker;
use OCA\Thematiq\Service\TokenValueValidator;
use PHPUnit\Framework\TestCase;

/**
 * Pins which colour tokens of a DTCG document are refused, by path and in order.
 */
class DtcgValueCheckerTest extends TestCase {

	/**
	 * The checker over the real value rules.
	 *
	 * @return DtcgValueChecker The checker.
	 */
	private function checker(): DtcgValueChecker {
		return new DtcgValueChecker(values: new TokenValueValidator());
	}//end checker()

	/**
	 * Types are inherited from the nearest group and overridden by a token's own.
	 *
	 * @return void
	 */
	public function testTheNearestTypeDecides(): void {
		$document = [
			'brand' => [
				'$type' => 'color',
				'primary' => ['$value' => 'not-a-colour'],
				'ok' => ['$value' => '#336699'],
				'size' => ['$type' => 'dimension', '$value' => 'not-a-colour'],
				'inner' => [
					'deep' => ['$value' => 'blue-ish'],
					'typed' => ['$type' => 'dimension', 'nested' => ['$value' => 'bad']],
				],
			],
			'loose' => ['$value' => 'not-a-colour'],
		];

		$this->assertSame(
			expected: ['brand.primary' => 'not-a-colour', 'brand.inner.deep' => 'blue-ish'],
			actual: $this->checker()->findInvalidColours(document: $document)
		);
	}//end testTheNearestTypeDecides()

	/**
	 * Aliases, colour functions, non-string values and dollar keys are not refused.
	 *
	 * @return void
	 */
	public function testWhatIsLeftToTheConverter(): void {
		$document = [
			'$type' => 'color',
			'$description' => ['$value' => 'nope'],
			'alias' => ['$value' => ' {brand.primary} '],
			'modern' => ['$value' => 'oklch(0.7 0.1 200 / 50%)'],
			'space' => ['$value' => 'hsl(0 100% 40%)'],
			'object' => ['$value' => ['colorSpace' => 'srgb', 'components' => [1, 0, 0]]],
			'number' => ['$value' => 7],
			'null' => ['$value' => null],
			'scalar' => 'red-ish',
			'named' => ['$value' => 'red'],
		];

		$this->assertSame(expected: [], actual: $this->checker()->findInvalidColours(document: $document));
	}//end testWhatIsLeftToTheConverter()

	/**
	 * Tokens Studio's `type` and `value` are read like the dollar forms; an array `value` is a group.
	 *
	 * @return void
	 */
	public function testTokensStudioForm(): void {
		$document = [
			'set' => [
				'type' => 'color',
				'bad' => ['value' => 'wrong'],
				'group' => ['value' => ['inner' => ['value' => 'wrong too']]],
				'both' => ['$value' => '#fff', 'value' => 'ignored'],
			],
		];

		$this->assertSame(
			expected: ['set.bad' => 'wrong', 'set.group.value.inner' => 'wrong too'],
			actual: $this->checker()->findInvalidColours(document: $document)
		);
	}//end testTokensStudioForm()

	/**
	 * Numeric keys stay keys, a top-level token has an empty path, and a repeated path keeps its first place.
	 *
	 * @return void
	 */
	public function testPathsAndOrder(): void {
		$this->assertSame(
			expected: ['' => 'bad'],
			actual: $this->checker()->findInvalidColours(document: ['$type' => 'color', '$value' => 'bad'])
		);

		$document = [
			'$type' => 'color',
			'a.b' => ['$value' => 'first'],
			0 => ['$value' => 'zero'],
			'z' => ['$value' => 'zed'],
			'a' => ['b' => ['$value' => 'second']],
			'1' => ['2' => ['$value' => 'deep']],
		];

		$this->assertSame(
			expected: ['a.b' => 'second', 0 => 'zero', 'z' => 'zed', '1.2' => 'deep'],
			actual: $this->checker()->findInvalidColours(document: $document)
		);
	}//end testPathsAndOrder()
}//end class
