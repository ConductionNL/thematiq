<?php

/**
 * Seeded golden test for DesignTokensMapper::map() over generated DTCG documents.
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

use OCA\Thematiq\Service\DesignTokensMapper;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Maps seeded documents whose leaves sit under mapped paths: colour objects in several spaces,
 * with and without a `hex` member, alpha and enough components, in and out of gamut, with the
 * CSS text the exporter writes beside them; dimensions as text and as `{value, unit}` objects;
 * font lists with blank and spaced names; typography composites; `$type` set and inherited;
 * `$deprecated` in every form; package versions in each place. Records each result.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\DesignTokensMapper
 * @uses   \OCA\Thematiq\Service\TypographyMember
 * @uses   \OCA\Thematiq\Service\DtcgColourSerializer
 * @uses   \OCA\Thematiq\Service\DtcgValueSerializer
 * @uses   \OCA\Thematiq\Service\DesignTokensAliasResolver
 * @uses   \OCA\Thematiq\Service\AliasWalk
 * @uses   \OCA\Thematiq\Service\ColorSpaceConverter
 * @uses   \OCA\Thematiq\Service\CssColorParser
 * @uses   \OCA\Thematiq\Service\DesignTokenRef
 * @uses   \OCA\Thematiq\Service\DesignTokensMapping
 * @uses   \OCA\Thematiq\Service\DtcgValueTyper
 * @uses \OCA\Thematiq\Service\ColourComponentScale
 * @uses \OCA\Thematiq\Service\ColourFunctionReading
 * @uses \OCA\Thematiq\Service\LeafMappingRun
 * @uses \OCA\Thematiq\Service\LeafScope
 * @uses \OCA\Thematiq\Service\MappedToken
 * @uses \OCA\Thematiq\Service\TypedValue
 */
class DesignTokensMapperSeededGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/design-tokens-mapper-seeded.golden.json';

	private const SPACES = ['srgb', 'SRGB', 'display-p3', 'oklch', 'hsl', 'lab', 'rec2020', 'no-such-space', ''];

	private const CSS_TEXTS = [
		'#154273', ' #154273 ', 'rgb(21, 66, 115)', 'hsl(211, 69%, 27%)', '#ff0000', 'lab(50% 20 30)', 'color(display-p3 1 0 0)',
		'', '   ', 'not-a-colour', '#15427380', 'RGB(21, 66, 115)',
	];

	private const DEPRECATED = [
		null, false, true, 'Use the other one.', '  ', ['severity' => 'error', 'message' => 'Gone.'], ['severity' => 'warning'], ['message' => ''],
	];

	private const FONTS = [['Fira Sans', 'Arial', 'sans-serif'], ['  ', 'Open Sans'], [], [3, 'serif'], 'Fira Sans, sans-serif', 12];

	/**
	 * Every seeded document maps to the recorded result.
	 *
	 * @return void
	 */
	public function testSeededDocumentsMatchTheGoldenRecording(): void {
		$paths = array_keys((new DesignTokensMapper())->getMappingTable());
		$actual = [];
		for ($seed = 1; $seed <= 260; $seed++) {
			$random = new Randomizer(engine: new Mt19937(seed: $seed));
			$document = $this->document(paths: $paths, random: $random);
			$result = (new DesignTokensMapper())->map(document: $document);
			$actual['seed:' . $seed] = (string)json_encode($result, (JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame(expected: $json, actual: $actual[$name], message: $name);
		}
	}//end testSeededDocumentsMatchTheGoldenRecording()

	/**
	 * A seeded document: leaves under mapped paths, a version somewhere, some metadata keys.
	 *
	 * @param array<int, string> $paths The mapped path suffixes.
	 * @param Randomizer $random The seeded source.
	 *
	 * @return array<string, mixed>
	 */
	private function document(array $paths, Randomizer $random): array {
		$document = [];
		$count = $random->getInt(1, 9);
		for ($i = 0; $i < $count; $i++) {
			$path = explode('.', $paths[$random->getInt(0, (count($paths) - 1))]);
			if ($random->getInt(0, 3) === 0) {
				array_unshift($path, ['brand', 'theme', '$meta'][$random->getInt(0, 2)]);
			}

			$leaf = $this->leaf(random: $random);
			$node = &$document;
			foreach ($path as $segment) {
				if (isset($node[$segment]) === false || is_array($node[$segment]) === false) {
					$node[$segment] = [];
				}

				$node = &$node[$segment];
			}

			$node = $leaf;
			unset($node);
		}

		if ($random->getInt(0, 2) === 0) {
			$document['color']['$type'] = 'color';
		}

		$version = $random->getInt(0, 5);
		if ($version === 0) {
			$document['$version'] = '1.2.3';
		} elseif ($version === 1) {
			$document['$extensions'] = ['org.nldesign.version' => '2.0.0', 'nl.nldesign.version' => ['not', 'a', 'string']];
		} elseif ($version === 2) {
			$document['version'] = '0.9.0';
			$document['$version'] = 7;
		} elseif ($version === 3) {
			$document['$extensions'] = ['nl.nldesign.version' => '3.1.0'];
			$document['version'] = '0.1.0';
		}

		return $document;
	}//end document()

	/**
	 * A seeded leaf.
	 *
	 * @param Randomizer $random The seeded source.
	 *
	 * @return array<string, mixed>
	 */
	private function leaf(Randomizer $random): array {
		$kind = $random->getInt(0, 6);
		$leaf = [];
		if ($kind <= 2) {
			$leaf = ['$type' => 'color', '$value' => $this->colourObject(random: $random)];
			if ($random->getInt(0, 1) === 1) {
				$leaf['$extensions'] = [
					['nl.conduction.thematiq', 'nl.conduction.openproject-thematiq'][$random->getInt(0, 1)] => [
						'css' => self::CSS_TEXTS[$random->getInt(0, (count(self::CSS_TEXTS) - 1))],
					],
				];
			}
		} elseif ($kind === 3) {
			$leaf = ['$value' => ['#154273', 'rgb(1, 2, 3)', 12][$random->getInt(0, 2)]];
		} elseif ($kind === 4) {
			$leaf = ['$type' => 'dimension', '$value' => [
				'4px', ' 1rem ', ['value' => 8, 'unit' => 'px'], ['value' => 1.5, 'unit' => 'rem'], ['value' => [1], 'unit' => 'px'],
				['value' => 2, 'unit' => 3], ['value' => 2], ['unit' => 'px'],
			][$random->getInt(0, 7)]];
		} elseif ($kind === 5) {
			$leaf = ['$type' => 'fontFamily', '$value' => self::FONTS[$random->getInt(0, (count(self::FONTS) - 1))]];
		} else {
			$leaf = ['$type' => 'typography', '$value' => [
				['fontFamily' => self::FONTS[$random->getInt(0, (count(self::FONTS) - 1))], 'fontSize' => '16px', 3 => 'x'],
				['fontWeight' => 700],
				['fontFamily' => 12],
				'not-an-object',
			][$random->getInt(0, 3)]];
		}

		$leaf['$deprecated'] = self::DEPRECATED[$random->getInt(0, (count(self::DEPRECATED) - 1))];
		if ($leaf['$deprecated'] === null) {
			unset($leaf['$deprecated']);
		}

		return $leaf;
	}//end leaf()

	/**
	 * A seeded colour value: an object in some space, or a string.
	 *
	 * @param Randomizer $random The seeded source.
	 *
	 * @return mixed
	 */
	private function colourObject(Randomizer $random): mixed {
		if ($random->getInt(0, 6) === 0) {
			return ['#154273', '#15427380', 'red'][$random->getInt(0, 2)];
		}

		$space = self::SPACES[$random->getInt(0, (count(self::SPACES) - 1))];
		$object = ['colorSpace' => $space];
		$components = $random->getInt(0, 4);
		if ($components === 0) {
			$object['components'] = [0.08, 0.26];
		} elseif ($components === 1) {
			$object['components'] = [1.2, -0.1, 0.5];
		} elseif ($components === 2) {
			$object['components'] = [0.0824, 0.2588, 0.451];
		} elseif ($components === 3) {
			$object['components'] = ['l' => 0.5, 'c' => 0.4, 'h' => 30];
		}

		if ($random->getInt(0, 1) === 1) {
			$object['hex'] = ['#154273', '#FF0000', 'zzz', '#abc'][$random->getInt(0, 3)];
		}

		if ($random->getInt(0, 2) === 0) {
			$object['alpha'] = [0.5, 1, 0, 1.5][$random->getInt(0, 3)];
		}

		return $object;
	}//end colourObject()
}//end class
