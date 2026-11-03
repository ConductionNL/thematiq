<?php

/**
 * Seeded golden test for MultiBrandSource::detectBrands() and cut().
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
 * @spec openspec/specs/multi-brand-token-sources/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\MultiBrandSource;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Detects and cuts the brands of the multi-brand fixtures and of seeded sources: CSS with
 * brand classes (repeated), modifiers of a brand, shared blocks and `:root`; Tokens Studio
 * files whose themes switch sets on as enabled or source, with a set order that lists some
 * sets, and set trees that overlap. Every brand is cut, and one key no source has.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\MultiBrandSource
 * @uses   \OCA\Thematiq\Service\BrandCssCut
 * @uses   \OCA\Thematiq\Service\CssBlockReader
 * @uses   \OCA\Thematiq\Service\TokensStudioSets
 */
class MultiBrandSourceGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/multi-brand-source.golden.json';

	private const SELECTORS = ['.zuid-theme', '.noord-theme', '.zuid-theme--dark', ':root', '.shared', '.oost-theme', '.Bad-theme'];

	private const SETS = ['global', 'zuid', 'noord', 'oost', 'dark'];

	/**
	 * Every source detects and cuts as recorded.
	 *
	 * @return void
	 */
	public function testCutsMatchTheGoldenRecording(): void {
		$root = \dirname(__DIR__, 3);
		$sources = [];
		foreach (['three-themes.tokens.json', 'three-themes-legacy.tokens.json', 'two-brand-classes.css'] as $file) {
			$sources['file:' . $file] = (string)file_get_contents($root . '/tests/Unit/fixtures/multi-brand/' . $file);
		}

		for ($seed = 1; $seed <= 150; $seed++) {
			$random = new Randomizer(engine: new Mt19937(seed: $seed));
			$sources['css:' . $seed] = $this->css(random: $random);
			$sources['studio:' . $seed] = (string)json_encode($this->studio(random: $random));
		}

		$source = new MultiBrandSource();
		$actual = [];
		foreach ($sources as $name => $content) {
			$brands = $source->detectBrands(content: $content);
			$cuts = [];
			foreach (array_merge(array_column($brands, 'key'), ['zuid', 'no-such-brand']) as $key) {
				try {
					$cuts[$key] = $source->cut(content: $content, key: (string)$key);
				} catch (RuntimeException $exception) {
					$cuts[$key] = ['refused' => $exception->getMessage(), 'code' => $exception->getCode()];
				}
			}

			$actual[$name] = (string)json_encode(['brands' => $brands, 'cuts' => $cuts], JSON_UNESCAPED_SLASHES);
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame(expected: $json, actual: $actual[$name], message: $name);
		}
	}//end testCutsMatchTheGoldenRecording()

	/**
	 * A seeded CSS source.
	 *
	 * @param Randomizer $random The seeded source.
	 *
	 * @return string
	 */
	private function css(Randomizer $random): string {
		$css = '';
		$blocks = $random->getInt(1, 7);
		for ($i = 0; $i < $blocks; $i++) {
			$selector = self::SELECTORS[$random->getInt(0, (count(self::SELECTORS) - 1))];
			$css .= $selector . " {\n  --x-color-" . $i . ': #' . str_repeat((string)($i % 10), 6) . ";\n  --x-shared: " . $i . "px;\n}\n";
		}

		return $css;
	}//end css()

	/**
	 * A seeded Tokens Studio source.
	 *
	 * @param Randomizer $random The seeded source.
	 *
	 * @return array<string, mixed>
	 */
	private function studio(Randomizer $random): array {
		$document = [];
		foreach (self::SETS as $index => $set) {
			$document[$set] = [
				'color' => [
					'primary' => [
						['value' => '#' . str_repeat((string)$index, 6), 'type' => 'color'],
						['$value' => '#' . str_repeat((string)$index, 6)],
					][($index % 2)],
					's' . $index => ['$value' => $index . 'px'],
				],
				'only' . $index => ['value' => 'x'],
			];
		}

		if ($random->getInt(0, 1) === 1) {
			$document['$metadata'] = ['tokenSetOrder' => array_slice(array_reverse(self::SETS), 0, $random->getInt(0, 5))];
		}

		$themes = [];
		$count = $random->getInt(0, 4);
		for ($i = 0; $i < $count; $i++) {
			$selected = [];
			foreach (self::SETS as $set) {
				$selected[$set] = ['enabled', 'source', 'disabled'][$random->getInt(0, 2)];
			}

			$theme = ['id' => ['zuid', 'noord', 'oost', 'dark'][$i], 'selectedTokenSets' => $selected];
			if ($random->getInt(0, 1) === 1) {
				$theme['name'] = 'Brand ' . $i;
			}

			if ($random->getInt(0, 2) === 0) {
				$theme['group'] = 'brands';
			}

			$themes[] = $theme;
		}

		$document['$themes'] = $themes;

		return $document;
	}//end studio()
}//end class
