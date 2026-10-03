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
		$this->assertSame($accepted, (new TokenValueValidator())->isValid(type: $type, value: $value));
	}//end testGrammar()
}//end class
