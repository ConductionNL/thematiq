<?php

/**
 * Unit tests for GalleryEntryValidator.
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
 * @spec openspec/specs/theme-gallery/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\GalleryEntryValidator;
use PHPUnit\Framework\TestCase;

/**
 * Each pattern field refused on its own, and the exact entry an accepted one becomes.
 */
class GalleryEntryValidatorTest extends TestCase {

	/**
	 * A valid entry with the given fields replaced.
	 *
	 * @param array<string, mixed> $overrides The replaced fields.
	 *
	 * @return array<string, mixed>
	 */
	private static function entry(array $overrides = []): array {
		return array_merge(
			[
				'id' => 'provincie-utrecht',
				'name' => 'Provincie Utrecht',
				'organisation' => 'Provincie Utrecht',
				'description' => 'The house style of the province.',
				'licence' => 'EUPL-1.2',
				'sourceUrl' => 'https://github.com/example/utrecht-tokens',
				'fileUrl' => 'https://gallery.example.nl/sets/provincie-utrecht.css',
				'sha256' => str_repeat('a', 64),
				'format' => 'css',
				'swatches' => ['primary' => '#cc0000', 'background' => '#ffffff', 'text' => '#1a1a1a'],
				'contrast' => ['pass' => 42, 'fail' => 0],
				'addedOn' => '2026-09-30',
			],
			$overrides
		);
	}//end entry()

	/**
	 * One broken field refuses the entry; an accepted one keeps exactly the listed fields, in order.
	 *
	 * @return void
	 */
	public function testEachFieldAndTheAcceptedShape(): void {
		$validator = new GalleryEntryValidator();
		$broken = [
			['id' => 'Upper'], ['id' => 'a--b'], ['id' => 7], ['licence' => 'MIT and GPL'], ['licence' => ''],
			['sha256' => str_repeat('A', 64)], ['sha256' => 'abc'], ['sourceUrl' => 'http://x.example'], ['fileUrl' => 'https://a b'],
			['addedOn' => '2026-9-30'], ['addedOn' => null],
		];
		foreach ($broken as $override) {
			$this->assertNull(actual: $validator->validate(raw: self::entry($override)), message: (string)json_encode($override));
		}

		$this->assertNull(actual: $validator->validate(raw: 'not an entry'));
		$accepted = $validator->validate(raw: self::entry(['licence' => 'EUPL-1.2 OR MIT', 'extra' => 'dropped']));
		$this->assertSame(
			expected: ['id', 'name', 'organisation', 'description', 'licence', 'sourceUrl', 'fileUrl', 'sha256', 'format', 'swatches', 'contrast', 'addedOn'],
			actual: array_keys((array)$accepted)
		);
		$this->assertSame(expected: 'EUPL-1.2 OR MIT', actual: $accepted['licence']);
		$entry = self::entry();
		unset($entry['contrast']);
		$withoutContrast = (array)$validator->validate(raw: $entry);
		$this->assertArrayHasKey(key: 'contrast', array: $withoutContrast);
		$this->assertNull(actual: $withoutContrast['contrast']);
	}//end testEachFieldAndTheAcceptedShape()
}//end class
