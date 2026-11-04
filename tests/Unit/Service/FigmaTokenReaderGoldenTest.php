<?php

/**
 * Seeded golden test for FigmaTokenReader::normalise().
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
 * @spec openspec/specs/figma-token-files/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\FigmaTokenReader;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Normalises every Figma fixture, and seeded variants of them and of a thematiq export: values
 * replaced by references (to tokens the file holds and does not), sums over references, booleans,
 * blanks, numbers and Tokens Studio type names, a set id present or not, metadata keys, and
 * content that is no JSON object. Records the rewritten document and what was skipped.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\FigmaTokenReader
 * @uses   \OCA\Thematiq\Service\FigmaCssValue
 * @uses   \OCA\Thematiq\Service\FileDarkValues
 * @uses   \OCA\Thematiq\Service\TokensStudioSets
 */
class FigmaTokenReaderGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/figma-token-reader.golden.json';

	private const VALUES = [
		'#0b5fff', '{nldesign.color.primary}', '{nldesign.color.nope}', '{nldesign.space.a} + {nldesign.space.b}', true, '', '  ', 12,
		'4px', ' 8px ', '{Nldesign.color.primary}', 'a}b', ['x' => 1],
	];

	private const TYPES = ['color', 'borderRadius', 'spacing', 'fontFamilies', 'sizing', 'other', null];

	/**
	 * Every case normalises as recorded.
	 *
	 * @return void
	 */
	public function testNormaliseMatchesTheGoldenRecording(): void {
		$root = \dirname(__DIR__, 3);
		$files = glob($root . '/tests/fixtures/figma/*.json');
		$files = array_merge($files, glob($root . '/tests/fixtures/figma/plugin/*.json'));
		sort($files);
		$inputs = ['empty' => '', 'list' => '[1, 2]', 'text' => 'not json', 'broken' => '{"a": ', 'scalar-object' => ' {"a": 1}'];
		foreach ($files as $file) {
			$inputs['file:' . basename(dirname($file)) . '/' . basename($file)] = (string)file_get_contents($file);
		}

		$legacy = json_decode((string)file_get_contents($root . '/tests/fixtures/figma/tokens-studio-legacy.json'), true);
		for ($seed = 1; $seed <= 120; $seed++) {
			$random = new Randomizer(engine: new Mt19937(seed: $seed));
			$inputs['studio:' . $seed] = (string)json_encode($this->varied(node: $legacy, random: $random, depth: 0));
			$inputs['own:' . $seed] = (string)json_encode($this->ownFile(random: $random));
		}

		$reader = new FigmaTokenReader();
		$actual = [];
		foreach ($inputs as $name => $content) {
			$skipped = [];
			$output = $reader->normalise(content: $content, skipped: $skipped);
			$actual[$name] = (string)json_encode(['output' => $output, 'skipped' => $skipped], JSON_UNESCAPED_SLASHES);
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame(expected: $json, actual: $actual[$name], message: $name);
		}
	}//end testNormaliseMatchesTheGoldenRecording()

	/**
	 * A tree with seeded token values and types.
	 *
	 * @param mixed $node The node.
	 * @param Randomizer $random The seeded source.
	 * @param int $depth The depth.
	 *
	 * @return mixed
	 */
	private function varied(mixed $node, Randomizer $random, int $depth): mixed {
		if (is_array($node) === false || $depth > 8) {
			return $node;
		}

		if (array_key_exists('value', $node) === true && $random->getInt(0, 3) === 0) {
			$node['value'] = self::VALUES[$random->getInt(0, (count(self::VALUES) - 1))];
			$node['type'] = self::TYPES[$random->getInt(0, (count(self::TYPES) - 1))];
			return $node;
		}

		foreach ($node as $key => $child) {
			$node[$key] = $this->varied(node: $child, random: $random, depth: ($depth + 1));
		}

		return $node;
	}//end varied()

	/**
	 * A seeded thematiq export: an `nldesign` group of tokens, sometimes with a set id.
	 *
	 * @param Randomizer $random The seeded source.
	 *
	 * @return array<string, mixed>
	 */
	private function ownFile(Randomizer $random): array {
		$group = [];
		foreach (['color.primary', 'color.text', 'space.a', 'space.b', 'border.radius', 'Bad_Name.x', 'deep.a.b.c'] as $path) {
			if ($random->getInt(0, 3) === 0) {
				continue;
			}

			$token = ['$value' => self::VALUES[$random->getInt(0, (count(self::VALUES) - 1))]];
			$type = self::TYPES[$random->getInt(0, (count(self::TYPES) - 1))];
			if ($type !== null) {
				$token['$type'] = $type;
			}

			$node = &$group;
			foreach (explode('.', $path) as $segment) {
				$node[$segment] ??= [];
				$node = &$node[$segment];
			}

			$node = $token;
			unset($node);
		}

		$document = ['nldesign' => $group, '$description' => 'seeded'];
		if ($random->getInt(0, 2) === 0) {
			$document['$meta'] = ['spacing' => ['value' => '1px', 'type' => 'spacing']];
			$document['nldesign']['$meta'] = ['token' => ['value' => '2px', 'type' => 'sizing']];
		}
		$extension = $random->getInt(0, 3);
		if ($extension === 0) {
			$document['$extensions'] = ['nl.conduction.thematiq' => ['setId' => 'custom-x']];
		} elseif ($extension === 1) {
			$document['$extensions'] = ['nl.conduction.thematiq' => ['cssOnly' => ['--nldesign-kept' => '1px']]];
		}

		return $document;
	}//end ownFile()
}//end class
