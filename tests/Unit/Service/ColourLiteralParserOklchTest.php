<?php

/**
 * ColourLiteralParser reads oklch() and oklab() and writes them as hex, the same hex
 * js/lib/tokenConverter.js writes (Q-thematiq-10: Rijkshuisstijl 18 writes every colour
 * as oklch(), and an admin upload of that theme must convert like the repository does).
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
 * @spec openspec/changes/converter-oklch-and-manifest-primary/specs/token-set-converter/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ColourLiteralParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * oklch() and oklab() literals.
 *
 * @spec openspec/changes/converter-oklch-and-manifest-primary/specs/token-set-converter/spec.md
 */
final class ColourLiteralParserOklchTest extends TestCase {

	/**
	 * The values tests/vitest/converterOklchAndPrimary.spec.js pins for the JavaScript runtime.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function literals(): array {
		return [
			'red, L as a percentage' => ['oklch(62.8% 0.2577 29.23)', '#ff0000'],
			'blue' => ['oklch(45.2% 0.313 264.05)', '#0000ff'],
			'white' => ['oklch(100% 0 0)', '#ffffff'],
			'out of gamut, clipped per channel' => ['oklch(0.5 0.5 30)', '#ff0000'],
			'chroma as a percentage, hue in deg' => ['oklch(50% 25% 120deg)', '#5c6b21'],
			'oklab' => ['oklab(0.628 0.2249 0.1258)', '#ff0000'],
			'none and an alpha' => ['oklch(0% 0 none / 0.102)', 'rgba(0, 0, 0, 0.1)'],
		];
	}//end literals()

	/**
	 * Each literal becomes the same hex the JavaScript converter writes.
	 *
	 * @param string $value    The literal.
	 * @param string $expected The normalised colour.
	 *
	 * @return void
	 */
	#[DataProvider('literals')]
	public function testTheLiteralBecomesHex(string $value, string $expected): void {
		self::assertSame(expected: $expected, actual: (new ColourLiteralParser())->normalise(value: $value));
	}//end testTheLiteralBecomesHex()

	/**
	 * A value the parser cannot read stays unread.
	 *
	 * @return void
	 */
	public function testAnUnreadableValueIsLeftAlone(): void {
		$parser = new ColourLiteralParser();
		self::assertNull(actual: $parser->normalise(value: 'oklch(bad 0 0)'));
		self::assertNull(actual: $parser->normalise(value: 'oklch(from red l c h)'));
	}//end testAnUnreadableValueIsLeftAlone()
}//end class
