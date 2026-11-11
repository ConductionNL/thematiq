<?php

/**
 * Tests for TokenValueValidator: one value grammar per token type.
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
 * @spec openspec/specs/token-editor-ui/spec.md#requirement-the-server-checks-each-value-against-its-token-type
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\TokenValueValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The same cases run through the JS mirror in tests/vitest/tokenValueGrammar.spec.js.
 *
 * @spec openspec/specs/token-editor-ui/spec.md#requirement-the-server-checks-each-value-against-its-token-type
 */
class TokenValueValidatorTest extends TestCase {

	/**
	 * The shared cases: [type, value, accepted].
	 *
	 * @return array<string, array{0: string, 1: string, 2: bool}> The cases.
	 */
	public static function cases(): array {
		$cases = json_decode((string)file_get_contents(\dirname(__DIR__) . '/fixtures/token-value-grammar.json'), true);
		$result = [];
		foreach ($cases as $case) {
			$result[$case[0] . ' ' . $case[1]] = [$case[0], $case[1], $case[2]];
		}

		return $result;
	}//end cases()

	/**
	 * Every case is accepted or refused as the fixture says.
	 *
	 * @param string $type The token type.
	 * @param string $value The value.
	 * @param bool $accepted Whether it passes.
	 *
	 * @return void
	 */
	#[DataProvider('cases')]
	public function testGrammar(string $type, string $value, bool $accepted): void {
		$this->assertSame(expected: $accepted, actual: (new TokenValueValidator())->isValid(type: $type, value: $value));
	}//end testGrammar()
	/**
	 * Every light and dark rejection, with its reason, in order, a dark one replacing the light one of its name.
	 *
	 * @return void
	 */
	public function testEveryRejectionInOrder(): void {
		$validator = new TokenValueValidator();
		$rejected = $validator->findRejected(
			tokens: [
				'--color-primary' => 'not a colour',
				'--not-a-token' => '#fff',
				'--border-radius' => '4px',
				'--nldesign-animation-easing' => 'cubic-bezier(1.5, 0, 0, 1)',
				'--color-main-text' => ['array'],
				'--color-main-background' => '#fff; }',
				'--color-error' => '#c00',
			],
			darkTokens: [
				'--color-error' => 'dark? no',
				'--border-radius' => '#000',
				'--color-primary' => '#000',
				'--color-not-sent' => '#000',
				'--color-main-background' => 7,
			]
		);

		$this->assertSame(
			expected: [
				'--color-primary' => 'not a valid color value',
				'--not-a-token' => 'not an editable token',
				'--nldesign-animation-easing' => 'not a valid easing value',
				'--color-main-text' => 'not an allowed value',
				'--color-main-background' => 'dark value: not an allowed value',
				'--color-error' => 'dark value: not a valid color value',
				'--border-radius' => 'no dark value for this token',
				'--color-not-sent' => 'no dark value for this token',
			],
			actual: $rejected
		);
	}//end testEveryRejectionInOrder()

	/**
	 * Easing values: the keywords and a curve whose x values lie in 0..1.
	 *
	 * @return void
	 */
	public function testEasingValues(): void {
		$validator = new TokenValueValidator();
		$cases = [
			'ease' => true, 'ease-in-out' => true, 'steps(4)' => false, 'cubic-bezier(0, 0, 1, 1)' => true,
			'cubic-bezier( .25 , -3 , .75 , 9 )' => true, 'cubic-bezier(-0.1, 0, 1, 1)' => false, 'cubic-bezier(0, 0, 1.01, 1)' => false,
			'cubic-bezier(0, 0, 1)' => false, 'Ease' => false,
		];
		foreach ($cases as $value => $accepted) {
			$this->assertSame(expected: $accepted, actual: $validator->isValid(type: 'easing', value: $value), message: $value);
		}
	}//end testEasingValues()
}//end class
