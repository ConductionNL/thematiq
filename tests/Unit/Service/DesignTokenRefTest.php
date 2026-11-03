<?php

/**
 * DesignTokenRef keeps a token's path and `$deprecated` value as given.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://github.com/ConductionNL/thematiq
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\DesignTokenRef;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * @covers \OCA\Thematiq\Service\DesignTokenRef
 */
class DesignTokenRefTest extends TestCase {

	/**
	 * Every `$deprecated` shape is kept unchanged.
	 *
	 * @return void
	 */
	public function testKeepsPathAndDeprecatedAsGiven(): void {
		foreach ([null, false, true, '', 'Use another token', ['severity' => 'error', 'message' => 'Gone']] as $deprecated) {
			$token = new DesignTokenRef(path: 'color.primary', deprecated: $deprecated);
			self::assertSame(expected: 'color.primary', actual: $token->path);
			self::assertSame(expected: $deprecated, actual: $token->deprecated);
		}
	}//end testKeepsPathAndDeprecatedAsGiven()

	/**
	 * A path that is not a string is refused.
	 *
	 * @return void
	 */
	public function testRefusesANonStringPath(): void {
		$this->expectException(exception: TypeError::class);
		new DesignTokenRef(path: 12, deprecated: null);
	}//end testRefusesANonStringPath()
}//end class
