<?php

/**
 * Tests for the dark block of the OpenProject plugin's set file.
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
 * @spec openspec/specs/openproject-set-file/spec.md#requirement-the-plugins-set-file-becomes-a-custom-token-set
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\OpenProjectSetFile;
use PHPUnit\Framework\TestCase;

/**
 * The dark block is read like the light one, and what it leaves out is named under `dark.`.
 */
class OpenProjectSetFileDarkBlockTest extends TestCase {

	/**
	 * The dark roles and vocabulary become the dark values; what cannot be used is named after the light block's.
	 *
	 * @return void
	 */
	public function testTheDarkBlockIsReadAfterTheLightBlock(): void {
		$skipped = [['path' => 'earlier', 'reason' => 'kept']];
		$document = (new OpenProjectSetFile())->toDocument(
			document: [
				'id' => 'x',
				'tokens' => ['primary' => '#123456', 'sparkle' => '#ffffff'],
				'dark' => [
					'tokens' => ['primary' => '#abcdef', 'ink' => 3],
					'vocabulary' => ['color-focus' => '#00ff00', 'color-primary' => '#111111', 'Bad_Key' => '1px'],
				],
			],
			skipped: $skipped
		);

		$extension = $document['$extensions']['nl.conduction.thematiq'];
		$this->assertSame(expected: ['--nldesign-color-primary' => '#123456'], actual: (array)$extension['cssOnly']);
		$this->assertSame(
			expected: ['--nldesign-color-primary' => '#abcdef', '--nldesign-color-focus' => '#00ff00'],
			actual: (array)$extension['dark']
		);
		$this->assertArrayNotHasKey(key: '$description', array: $document);
		$this->assertSame(
			expected: [
				['path' => 'earlier', 'reason' => 'kept'],
				['path' => 'tokens.sparkle', 'reason' => 'unmapped'],
				['path' => 'dark.tokens.ink', 'reason' => 'value-not-a-css-value'],
				['path' => 'dark.vocabulary.Bad_Key', 'reason' => 'unmapped'],
			],
			actual: $skipped
		);
	}//end testTheDarkBlockIsReadAfterTheLightBlock()

	/**
	 * Dark roles written on their own, without `tokens`, are read too; a set without a dark block has none.
	 *
	 * @return void
	 */
	public function testDarkRolesOnTheirOwnAreRead(): void {
		$skipped = [];
		$reader = new OpenProjectSetFile();
		$document = $reader->toDocument(
			document: ['id' => 'x', 'tokens' => [], 'dark' => ['primary' => '#abcdef', 'vocabulary' => ['color-focus' => '#00ff00']]],
			skipped: $skipped
		);
		$this->assertSame(
			expected: ['--nldesign-color-primary' => '#abcdef', '--nldesign-color-focus' => '#00ff00'],
			actual: (array)$document['$extensions']['nl.conduction.thematiq']['dark']
		);

		$plain = $reader->toDocument(document: ['id' => 'x', 'tokens' => ['primary' => '#123456']], skipped: $skipped);
		$this->assertArrayNotHasKey(key: 'dark', array: $plain['$extensions']['nl.conduction.thematiq']);
		$this->assertSame(expected: [], actual: $skipped);
	}//end testDarkRolesOnTheirOwnAreRead()
}//end class
