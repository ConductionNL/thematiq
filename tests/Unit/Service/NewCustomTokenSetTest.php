<?php

/**
 * Unit tests for NewCustomTokenSet.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\Thematiq\Service\NewCustomTokenSet;
use PHPUnit\Framework\TestCase;

/**
 * The value object store() takes: defaults for left-out fields, refusal of unknown and missing ones.
 *
 * @covers \OCA\Thematiq\Service\NewCustomTokenSet
 */
final class NewCustomTokenSetTest extends TestCase {
	/**
	 * Left-out optional fields take store()'s former parameter defaults.
	 *
	 * @return void
	 */
	public function testLeftOutFieldsTakeTheirDefaults(): void {
		$set = new NewCustomTokenSet(fields: ['displayName' => 'Zuid', 'description' => 'd', 'declarations' => ['--a' => '#000']]);

		$this->assertSame(
			expected: [
				'displayName' => 'Zuid',
				'description' => 'd',
				'declarations' => ['--a' => '#000'],
				'version' => null,
				'importWarnings' => [],
				'css' => null,
				'theming' => [],
				'logoAsset' => null,
				'designSystem' => null,
			],
			actual: get_object_vars($set)
		);
	}//end testLeftOutFieldsTakeTheirDefaults()

	/**
	 * A field store() never took is refused, so a misspelt name cannot be dropped silently.
	 *
	 * @return void
	 */
	public function testAnUnknownFieldIsRefused(): void {
		$this->expectException(exception: InvalidArgumentException::class);
		$this->expectExceptionMessage(message: 'Unknown fields [logo], missing fields []');

		new NewCustomTokenSet(fields: ['displayName' => 'Zuid', 'description' => '', 'declarations' => [], 'logo' => null]);
	}//end testAnUnknownFieldIsRefused()

	/**
	 * Each required field must be given.
	 *
	 * @return void
	 */
	public function testAMissingRequiredFieldIsRefused(): void {
		$this->expectException(exception: InvalidArgumentException::class);
		$this->expectExceptionMessage(message: 'Unknown fields [], missing fields [description, declarations]');

		new NewCustomTokenSet(fields: ['displayName' => 'Zuid']);
	}//end testAMissingRequiredFieldIsRefused()

	/**
	 * A field of the wrong type is refused like a wrongly typed argument was.
	 *
	 * @return void
	 */
	public function testAWronglyTypedFieldIsRefused(): void {
		$this->expectException(exception: \TypeError::class);

		new NewCustomTokenSet(fields: ['displayName' => 'Zuid', 'description' => '', 'declarations' => [], 'version' => 2]);
	}//end testAWronglyTypedFieldIsRefused()
}//end class
