<?php

/**
 * Golden test for ConverterColourRepair::repairContrast().
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
 * @spec openspec/specs/token-sync-workflow/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\ConverterColourRepair;
use OCA\Thematiq\Service\CssParserService;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Repairs the contrast pairs of every shipped token set with the shipped mapping table, and of
 * seeded variants: pair colours replaced with light, dark, translucent and unparseable values,
 * pair tokens removed so the `from` step or a default chain stands in, and the table's pairs
 * varied in threshold, adjustment, repaired side and fallback. Records the declarations that
 * changed, the manifest, the repaired component layer and the report of each case.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\ConverterColourRepair
 * @covers \OCA\Thematiq\Service\ConverterPairRepair
 * @uses   \OCA\Thematiq\Service\ColourContrastAdjuster
 * @uses   \OCA\Thematiq\Service\ConverterComponentRepair
 * @uses   \OCA\Thematiq\Service\ColourLiteralParser
 * @uses   \OCA\Thematiq\Service\ContrastService
 * @uses   \OCA\Thematiq\Service\CssParserService
 * @uses   \OCA\Thematiq\Service\CssDeclarationScanner
 */
class ConverterColourRepairGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/converter-colour-repair.golden.json';

	private const COLOURS = [
		'#000000', '#ffffff', '#154273', '#767676', '#fafafa', '#1a1a1a', '#ff0000', '#ffff00', '#0000ff', '#888888',
		'rgba(0, 0, 0, 0.5)', 'rgba(255, 255, 255, 0.6)', '#00000080', 'transparent', 'not-a-colour', '#fff', 'hsl(210, 60%, 40%)', 'navy',
	];

	private const PAGES = ['#ffffff', '#000000', '#f5f5f5', '#1e1e1e', 'not-a-colour'];

	/**
	 * Every case repairs to the recorded result.
	 *
	 * @return void
	 */
	public function testRepairMatchesTheGoldenRecording(): void {
		$service = new ConverterColourRepair(contrast: new ContrastService());
		$parser = new CssParserService();
		$table = json_decode((string)file_get_contents(dirname(__DIR__, 3) . '/scripts/mapping/nlds-to-nextcloud.json'), true);
		$sets = glob(dirname(__DIR__, 3) . '/css/tokens/*.css');
		sort($sets);
		$bases = [];
		foreach ($sets as $path) {
			$bases[basename($path, '.css')] = ($parser->parseDeclarations(content: (string)file_get_contents($path)) ?? []);
		}

		$actual = [];
		foreach ($bases as $id => $declarations) {
			$actual['set:' . $id] = $this->repairCase(service: $service, case: ['semantic' => $declarations, 'table' => $table, 'component' => []]);
		}

		$tokens = $this->pairTokens(spec: $table['contrastRepair']);
		$ids = array_keys($bases);
		for ($seed = 1; $seed <= 400; $seed++) {
			$random = new Randomizer(engine: new Mt19937(seed: $seed));
			$semantic = $bases[$ids[$random->getInt(0, (count($ids) - 1))]];
			foreach ($tokens as $token) {
				$draw = $random->getInt(0, 9);
				if ($draw < 3) {
					$semantic[$token] = self::COLOURS[$random->getInt(0, (count(self::COLOURS) - 1))];
				} else if ($draw === 3) {
					unset($semantic[$token]);
				}
			}

			$actual['seed:' . $seed] = $this->repairCase(
				service: $service,
				case: [
					'semantic' => $semantic,
					'table' => $this->variedTable(table: $table, random: $random),
					'component' => $this->componentLayer(spec: $table['contrastRepair'], random: $random),
				]
			);
		}//end for

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame(expected: $json, actual: $actual[$name], message: $name);
		}
	}//end testRepairMatchesTheGoldenRecording()

	/**
	 * The shipped table with its contrast repair section varied.
	 *
	 * @param array<string, mixed> $table The shipped table.
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return array<string, mixed> The varied table.
	 */
	private function variedTable(array $table, Randomizer $random): array {
		$spec = $table['contrastRepair'];
		$spec['page'] = self::PAGES[$random->getInt(0, (count(self::PAGES) - 1))];
		if ($random->getInt(0, 3) === 0) {
			$spec['defaults'] = [];
		}

		$pairs = [];
		foreach ($spec['pairs'] as $pair) {
			if ($random->getInt(0, 5) === 0) {
				continue;
			}

			$pair = $this->variedPair(pair: $pair, random: $random);
			$pairs[] = $pair;
		}

		$spec['pairs'] = $pairs;
		$table['contrastRepair'] = $spec;

		return $table;
	}//end variedTable()

	/**
	 * One pair with its threshold, adjustment, repaired side and fallback varied.
	 *
	 * @param array<string, mixed> $pair The pair.
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return array<string, mixed> The varied pair.
	 */
	private function variedPair(array $pair, Randomizer $random): array {
		$min = $random->getInt(0, 4);
		if ($min === 0) {
			unset($pair['min']);
		} else if ($min === 1) {
			$pair['min'] = 3;
		} else if ($min === 2) {
			$pair['min'] = 7.0;
		}

		$adjust = $random->getInt(0, 3);
		if ($adjust === 0) {
			$pair['adjust'] = 'flip';
		} else if ($adjust === 1) {
			$pair['adjust'] = 'lightness';
		}

		$repair = $random->getInt(0, 5);
		if ($repair === 0) {
			$pair['repair'] = 'background';
		} else if ($repair === 1) {
			unset($pair['repair']);
		}

		if ($random->getInt(0, 4) === 0) {
			$pair['from'] = '--nldesign-color-primary';
		}

		return $pair;
	}//end variedPair()

	/**
	 * A seeded component layer over the table's component pairs: declared, missing or transparent.
	 *
	 * @param array<string, mixed> $spec The contrast repair section.
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return array<string, string> The component layer.
	 */
	private function componentLayer(array $spec, Randomizer $random): array {
		$component = [];
		foreach ($spec['componentPairs'] as $pair) {
			foreach (['foreground', 'background'] as $side) {
				$draw = $random->getInt(0, 4);
				if ($draw === 0) {
					continue;
				}

				$component[(string)$pair[$side]] = self::COLOURS[$random->getInt(0, (count(self::COLOURS) - 1))];
				if ($draw === 1) {
					$component[(string)$pair[$side]] = 'transparent';
				}
			}
		}

		return $component;
	}//end componentLayer()

	/**
	 * Every token a pair of the table names.
	 *
	 * @param array<string, mixed> $spec The contrast repair section.
	 *
	 * @return string[]
	 */
	private function pairTokens(array $spec): array {
		$tokens = [];
		foreach ($spec['pairs'] as $pair) {
			foreach (['foreground', 'background', 'from'] as $key) {
				if (isset($pair[$key]) === true) {
					$tokens[] = (string)$pair[$key];
				}
			}
		}

		$tokens = array_values(array_unique($tokens));
		sort($tokens);
		return $tokens;
	}//end pairTokens()

	/**
	 * One repair, as JSON text: the declarations that changed, the manifest and the report.
	 *
	 * @param ConverterColourRepair $service The service.
	 * @param array{semantic: array<string, string>, table: array<string, mixed>, component: array<string, string>} $case The input.
	 *
	 * @return string
	 */
	private function repairCase(ConverterColourRepair $service, array $case): string {
		$manifest = [];
		if (count($case['semantic']) % 2 === 0) {
			$manifest['theming.primary_color'] = (string)($case['semantic']['--nldesign-color-primary'] ?? '#0000ff');
		}

		$report = [];
		$result = $service->repairContrast(semantic: $case['semantic'], table: $case['table'], manifest: $manifest, report: $report);
		$changed = [];
		foreach ($result as $token => $value) {
			if (($case['semantic'][$token] ?? null) !== $value) {
				$changed[$token] = $value;
			}
		}

		$components = [];
		foreach ($service->repairComponents(component: $case['component'], semantic: $result, table: $case['table'], report: $report) as $token => $value) {
			if (($case['component'][$token] ?? null) !== $value) {
				$components[$token] = $value;
			}
		}

		return (string)json_encode(
			['changed' => $changed, 'keys' => count($result), 'manifest' => $manifest, 'components' => $components, 'report' => $report],
			(JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)
		);
	}//end repairCase()
}//end class
