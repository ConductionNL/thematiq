<?php

/**
 * Seeded golden test for TokensStudioSets::darkSets(), merged() and darkTree().
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

use OCA\Thematiq\Service\TokensStudioSets;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Reads seeded Tokens Studio files: sets named light, dark, Dark or otherwise, a set order that
 * lists some of them, no theme, one theme or two (one dark by id or name, both, neither), sets
 * switched on as enabled or source or off, and set trees that overlap with groups and tokens at
 * the same paths. Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\TokensStudioSets
 */
class TokensStudioSetsGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/tokens-studio-sets.golden.json';

	private const SETS = ['basis', 'light', 'dark', 'Dark', 'merk', '$meta'];

	private const STATUSES = ['enabled', 'source', 'disabled', 'other'];

	/**
	 * Every seeded file reads as recorded.
	 *
	 * @return void
	 */
	public function testSetsMatchTheGoldenRecording(): void {
		$sets = new TokensStudioSets();
		$actual = [];
		for ($seed = 1; $seed <= 300; $seed++) {
			$random = new Randomizer(engine: new Mt19937(seed: $seed));
			$document = $this->document(random: $random);
			$skipped = [];
			$actual['seed:' . $seed] = (string)json_encode([
				'dark' => $sets->darkSets(document: $document),
				'merged' => $sets->merged(document: $document, skipped: $skipped),
				'skipped' => $skipped,
				'darkTree' => $sets->darkTree(document: $document),
			]);
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame(expected: $json, actual: $actual[$name], message: $name);
		}
	}//end testSetsMatchTheGoldenRecording()

	/**
	 * A seeded file.
	 *
	 * @param Randomizer $random The seeded source.
	 *
	 * @return array<string, mixed>
	 */
	private function document(Randomizer $random): array {
		$document = [];
		foreach (self::SETS as $index => $set) {
			if ($random->getInt(0, 3) > 0) {
				$document[$set] = $this->tree(random: $random, index: $index);
			}
		}

		if ($random->getInt(0, 2) === 0) {
			$document['$metadata'] = ['tokenSetOrder' => array_reverse(array_slice(self::SETS, 0, $random->getInt(0, 4)))];
		}

		$themes = $random->getInt(0, 3);
		if ($themes > 0) {
			$document['$themes'] = [];
			for ($i = 0; $i < $themes; $i++) {
				$selected = [];
				foreach (self::SETS as $set) {
					if ($random->getInt(0, 2) > 0) {
						$selected[$set] = self::STATUSES[$random->getInt(0, 3)];
					}
				}

				$theme = ['selectedTokenSets' => $selected];
				$label = ['light', 'dark', 'Dark', 'brand'][$random->getInt(0, 3)];
				$theme[['id', 'name'][$random->getInt(0, 1)]] = $label;
				$document['$themes'][] = $theme;
			}

			if ($random->getInt(0, 5) === 0) {
				$document['$themes'][] = 'not a theme';
			}
		}

		return $document;
	}//end document()

	/**
	 * A seeded set tree with tokens and groups at shared paths.
	 *
	 * @param Randomizer $random The seeded source.
	 * @param int $index The set's position.
	 *
	 * @return array<string, mixed>
	 */
	private function tree(Randomizer $random, int $index): array {
		$tree = [];
		foreach (['color.primary', 'color.text', 'color', 'space.small', '$extensions.x', '$extensions.s' . $index, 'a.b.c', 'a.b.s' . $index] as $path) {
			if ($random->getInt(0, 2) === 0) {
				continue;
			}

			$token = [['value' => '#' . $index . $index . $index, 'type' => 'color'], ['$value' => $index . 'px'], ['nested' => ['value' => 'x' . $index]]][$random->getInt(0, 2)];
			$node = &$tree;
			foreach (explode('.', $path) as $segment) {
				if (isset($node[$segment]) === false || is_array($node[$segment]) === false) {
					$node[$segment] = [];
				}

				$node = &$node[$segment];
			}

			$node = $token;
			unset($node);
		}

		return $tree;
	}//end tree()
}//end class
