<?php

/**
 * The selection and highlight colours a set can now move are audited.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/theme-vocabulary-complete/specs/token-set-contrast-audit/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\SettableContrastPairs;
use PHPUnit\Framework\TestCase;

class SettableContrastPairsTest extends TestCase {

	public function testASetThatOnlyMovesTheSelectedTextIsAuditedAgainstTheDerivedWash(): void {
		$pairs = (new SettableContrastPairs())->pairs([
			'--nldesign-color-primary' => '#154273',
			'--nldesign-color-background' => '#ffffff',
			'--nldesign-nc-color-text-selection' => '#1a1a1a',
		]);

		$this->assertCount(1, $pairs);
		$this->assertSame('selection', $pairs[0]['pair']);
		$this->assertGreaterThan(4.5, (float)$pairs[0]['ratio'], 'dark text on a 20% navy wash passes');
		$this->assertTrue($pairs[0]['passes']);
	}

	public function testAPaleHighlightUnderDarkTextFailsAndNamesBothTokens(): void {
		$pairs = (new SettableContrastPairs())->pairs([
			'--nldesign-color-text' => '#1a1a1a',
			'--nldesign-nc-color-mark' => '#222222',
		]);

		$this->assertCount(1, $pairs);
		$this->assertLessThan(4.5, (float)$pairs[0]['ratio']);
		$this->assertFalse($pairs[0]['passes']);
		$this->assertSame('--nldesign-color-text', $pairs[0]['foreground']);
		$this->assertSame('--nldesign-nc-color-mark', $pairs[0]['background']);
	}

	public function testASetThatDeclaresNeitherSideIsNotReported(): void {
		$this->assertSame([], (new SettableContrastPairs())->pairs(['--nldesign-color-primary' => '#154273']));
	}
}
