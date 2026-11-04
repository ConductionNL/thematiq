<?php

/**
 * The selection and highlight colours a set can now move are audited.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/token-set-contrast-audit/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\SettableContrastPairs;
use PHPUnit\Framework\TestCase;

class SettableContrastPairsTest extends TestCase {

	/**
	 * A set that only moves the selected text is audited against the derived wash.
	 *
	 * @return void
	 */
	public function testASetThatOnlyMovesTheSelectedTextIsAuditedAgainstTheDerivedWash(): void {
		$pairs = (new SettableContrastPairs())->pairs([
			'--nldesign-color-primary' => '#154273',
			'--nldesign-color-background' => '#ffffff',
			'--nldesign-nc-color-text-selection' => '#1a1a1a',
		]);

		$this->assertCount(expectedCount: 1, haystack: $pairs);
		$this->assertSame(expected: 'selection', actual: $pairs[0]['pair']);
		$this->assertGreaterThan(expected: 4.5, actual: (float)$pairs[0]['ratio'], message: 'dark text on a 20% navy wash passes');
		$this->assertTrue(condition: $pairs[0]['passes']);
	}

	/**
	 * A pale highlight under dark text fails and names both tokens.
	 *
	 * @return void
	 */
	public function testAPaleHighlightUnderDarkTextFailsAndNamesBothTokens(): void {
		$pairs = (new SettableContrastPairs())->pairs([
			'--nldesign-color-text' => '#1a1a1a',
			'--nldesign-nc-color-mark' => '#222222',
		]);

		$this->assertCount(expectedCount: 1, haystack: $pairs);
		$this->assertLessThan(expected: 4.5, actual: (float)$pairs[0]['ratio']);
		$this->assertFalse(condition: $pairs[0]['passes']);
		$this->assertSame(expected: '--nldesign-color-text', actual: $pairs[0]['foreground']);
		$this->assertSame(expected: '--nldesign-nc-color-mark', actual: $pairs[0]['background']);
	}

	/**
	 * A set that declares neither side is not reported.
	 *
	 * @return void
	 */
	public function testASetThatDeclaresNeitherSideIsNotReported(): void {
		$this->assertSame(expected: [], actual: (new SettableContrastPairs())->pairs(['--nldesign-color-primary' => '#154273']));
	}
}
