<?php

/**
 * Seeded golden test for GalleryEntryValidator::validate().
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
 * @spec openspec/specs/theme-gallery/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\GalleryEntryValidator;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Validates seeded gallery entries: a valid entry with one or more fields replaced by a value
 * that breaks its pattern, a non-string, or nothing; texts blank, swatches incomplete, an
 * unknown format, a missing description. Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\GalleryEntryValidator
 */
class GalleryEntryValidatorGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/gallery-entry-validator.golden.json';

	private const VALID = [
		'id' => 'den-haag',
		'name' => 'Den Haag',
		'organisation' => 'Gemeente Den Haag',
		'description' => 'The city theme.',
		'licence' => 'EUPL-1.2 OR MIT',
		'sourceUrl' => 'https://example.org/source',
		'fileUrl' => 'https://example.org/file.css',
		'sha256' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
		'format' => 'css',
		'swatches' => ['primary' => '#154273', 'background' => '#fff', 'text' => '#000000'],
		'contrast' => 'AA',
		'addedOn' => '2026-11-03',
	];

	private const BAD = [
		'id' => ['Den Haag', 'den--haag', '-x', 'den-haag-2'],
		'name' => ['', '   ', 'Name'],
		'organisation' => ['', 'Org'],
		'description' => [7, 'Other text.'],
		'licence' => ['EUPL-1.2 or MIT', 'GPL-3.0-only WITH x', 'MIT AND'],
		'sourceUrl' => ['http://example.org', 'https://a b', 'https://ok.example'],
		'fileUrl' => ['ftp://x', 'https://x'],
		'sha256' => ['AAAA', 'b'],
		'format' => ['dtcg', 'design-tokens-css', 'json', 'CSS'],
		'swatches' => [['primary' => '#154273', 'background' => '#fff'], ['primary' => 'red', 'background' => '#fff', 'text' => '#000'], 'none', ['primary' => '#1234', 'background' => '#fff', 'text' => '#000']],
		'contrast' => [null, 'AAA'],
		'addedOn' => ['2026-1-3', '03-11-2026', '2026-11-03T00:00'],
	];

	/**
	 * Every seeded entry validates as recorded.
	 *
	 * @return void
	 */
	public function testValidationMatchesTheGoldenRecording(): void {
		$validator = new GalleryEntryValidator();
		$actual = ['scalar' => (string)json_encode($validator->validate(raw: 'entry')), 'valid' => (string)json_encode($validator->validate(raw: self::VALID))];
		$fields = array_keys(self::BAD);
		for ($seed = 1; $seed <= 400; $seed++) {
			$random = new Randomizer(engine: new Mt19937(seed: $seed));
			$entry = self::VALID;
			$changes = $random->getInt(1, 3);
			for ($i = 0; $i < $changes; $i++) {
				$field = $fields[$random->getInt(0, (count($fields) - 1))];
				$draw = $random->getInt(0, 5);
				if ($draw === 0) {
					unset($entry[$field]);
				} elseif ($draw === 1) {
					$entry[$field] = 42;
				} else {
					$entry[$field] = self::BAD[$field][$random->getInt(0, (count(self::BAD[$field]) - 1))];
				}
			}

			$actual['seed:' . $seed] = (string)json_encode($validator->validate(raw: $entry));
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame(expected: $json, actual: $actual[$name], message: $name);
		}
	}//end testValidationMatchesTheGoldenRecording()
}//end class
