<?php

/**
 * Golden test for DesignTokensMapper::map().
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
use OCA\Thematiq\Service\DesignTokensWriter;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Maps the DTCG fixtures of the suite, a document as thematiq exports it,
 * and seeded synthetic documents (every token type, aliases that resolve, dangle and loop,
 * every `$deprecated` shape, typography composites, duplicate targets, extension names),
 * and compares each result with the recording. Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\DesignTokensMapper
 * @uses   \OCA\Thematiq\Service\DesignTokensAliasResolver
 * @uses   \OCA\Thematiq\Service\ColorSpaceConverter
 * @uses   \OCA\Thematiq\Service\DesignTokensWriter
 * @uses   \OCA\Thematiq\Service\DtcgValueTyper
 */
class DesignTokensMapperGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/design-tokens-mapper.golden.json';

	private const FILES = [
		'tests/Unit/fixtures/dtcg/01-legacy-scalar-regression.tokens.json',
		'tests/Unit/fixtures/dtcg/02-group-type-inheritance.tokens.json',
		'tests/Unit/fixtures/dtcg/03-object-color-and-dimension.tokens.json',
		'tests/Unit/fixtures/dtcg/04-composite-typography.tokens.json',
		'tests/Unit/fixtures/dtcg/05-alias-chain-3-hop.tokens.json',
		'tests/Unit/fixtures/dtcg/06-alias-cycle.tokens.json',
		'tests/Unit/fixtures/dtcg/07-alias-dangling.tokens.json',
		'tests/Unit/fixtures/dtcg/08-alias-depth-bomb.tokens.json',
		'tests/Unit/fixtures/dtcg/09-deprecated.tokens.json',
		'tests/Unit/fixtures/dtcg/10-extensions-heavy.tokens.json',
		'tests/Unit/fixtures/dtcg/11-version-carrying.tokens.json',
		'tests/Unit/fixtures/dtcg/12-zero-yield.tokens.json',
		'tests/Unit/fixtures/dtcg/14-utrecht-real-excerpt.tokens.json',
		'tests/Unit/fixtures/dtcg/15-amsterdam-real-excerpt.tokens.json',
		'tests/Unit/fixtures/dtcg/colour-spaces.tokens.json',
		'tests/Unit/fixtures/multi-brand/three-themes-legacy.tokens.json',
		'tests/Unit/fixtures/multi-brand/three-themes.tokens.json',
		'tests/fixtures/figma/plugin/cunningham.tokens-studio-legacy.json',
		'tests/fixtures/figma/plugin/cunningham.tokens-studio.json',
		'tests/fixtures/figma/plugin/cunningham.w3c.tokens.json',
		'tests/fixtures/figma/plugin/zuiddrecht.w3c.tokens.json',
		'tests/fixtures/figma/tokens-studio-dtcg.json',
		'tests/fixtures/figma/tokens-studio-legacy.json',
		'tests/fixtures/openproject/cunningham.json',
		'tests/fixtures/openproject/zuiddrecht.json',
	];

	private const EXPORTED = [
		'--nldesign-color-primary' => '#154273',
		'--nldesign-color-primary-text' => '#ffffff',
		'--nldesign-color-background' => 'rgb(250, 250, 250)',
		'--nldesign-font-family' => '"Fira Sans", Arial, sans-serif',
		'--nldesign-border-radius' => '4px',
		'--nldesign-font-weight-bold' => '700',
		'--nldesign-transition-duration' => '200ms',
		'--nldesign-shadow' => '0 1px 2px rgba(0, 0, 0, 0.2)',
		'--golden-color-blue-500' => '#0b71a1',
		'--nldesign-color-action' => 'var(--golden-color-blue-500)',
		'--nldesign-header-background' => 'linear-gradient(90deg, #000, #fff)',
	];

	private const VALUES = [
		'color' => ['#154273', '#abc', 'rgb(1, 2, 3)', ['colorSpace' => 'srgb', 'components' => [0.1, 0.2, 0.3]], ['colorSpace' => 'srgb', 'components' => [0, 0, 0], 'hex' => '#000000', 'alpha' => 0.25], ['colorSpace' => 'display-p3', 'components' => [1, 0, 0]], ['colorSpace' => 'oklch', 'components' => [0.5, 0.1, 250]], ['colorSpace' => 'nope', 'components' => [1, 1, 1]], 12, ['x']],
		'dimension' => ['4px', ['value' => 8, 'unit' => 'px'], ['value' => 1.5, 'unit' => 'rem'], ['value' => 'x', 'unit' => 'em'], 0, true],
		'duration' => ['200ms', ['value' => 150, 'unit' => 'ms']],
		'cubicBezier' => [[0.4, 0, 0.2, 1], 'ease-in', [1, 2], ['a', 0, 0, 1]],
		'fontFamily' => ['Fira Sans', ['Fira Sans', 'Arial', 'sans-serif'], ['Open Sans', 7], '', 3],
		'fontWeight' => ['bold', 'semi-bold', 700, 'heaviest', 1001, '400'],
		'shadow' => ['0 1px 2px #000', ['offsetX' => '1px']],
		'number' => [1.5, 'x'],
		'typography' => [['fontFamily' => 'Fira Sans', 'fontSize' => '16px'], ['fontFamily' => ['A', 'B'], 'lineHeight' => 1.4], ['fontFamily' => 7], ['Fira Sans'], 'not an object', ['fontWeight' => 700]],
	];

	private const DEPRECATED = [null, true, false, '', 'Use another token', ['severity' => 'error', 'message' => 'Gone'], ['severity' => 'warning'], ['message' => '  ']];

	private const PATHS = ['color.primary', 'color.primary-text', 'color.on-primary', 'color.action', 'color.action-hover', 'color.background', 'color.text', 'brand.primary', 'fontfamily.base', 'typography.font-family', 'border-radius.base', 'dimension.border-radius', 'brand.accent', 'space.small', 'typography.heading', 'typography.body', 'motion.ease'];

	/**
	 * Every recorded case maps to the recorded result.
	 *
	 * @return void
	 */
	public function testMapMatchesTheGoldenRecording(): void {
		$actual = [];
		foreach ($this->cases() as $name => $case) {
			$mapper = new DesignTokensMapper();
			$actual[$name] = json_encode(
				$mapper->map(document: $case['document'], referenceOnlyPaths: $case['referenceOnly']),
				(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)
			);
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			if (is_dir(dirname(self::FIXTURE)) === false) {
				mkdir(dirname(self::FIXTURE), 0777, true);
			}

			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(array_keys($expected), array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame($json, $actual[$name], 'case ' . $name);
		}
	}//end testMapMatchesTheGoldenRecording()

	/**
	 * The cases: fixture documents, an exported document, seeded synthetic documents.
	 *
	 * @return array<string, array{document: array<mixed>, referenceOnly: array<int, string>}>
	 */
	private function cases(): array {
		$root = \dirname(__DIR__, 3);
		$cases = [];
		foreach (self::FILES as $file) {
			$document = json_decode((string)file_get_contents($root . '/' . $file), true);
			$cases['file:' . $file] = ['document' => (array)$document, 'referenceOnly' => []];
		}

		$document = (new DesignTokensWriter())->write(declarations: self::EXPORTED, setId: 'golden', setName: 'Golden', appVersion: 'golden');
		$cases['export'] = ['document' => json_decode((string)json_encode($document), true), 'referenceOnly' => []];
		$cases['out-of-gamut'] = [
			'document' => ['color' => ['primary' => ['$type' => 'color', '$value' => ['colorSpace' => 'display-p3', 'components' => [1, 0, 0]], '$deprecated' => 'Old']]],
			'referenceOnly' => [],
		];

		for ($seed = 1; $seed <= 80; $seed++) {
			$cases['seed:' . $seed] = $this->synthetic(random: new Randomizer(new Mt19937($seed)));
		}

		return $cases;
	}//end cases()

	/**
	 * A seeded synthetic document.
	 *
	 * @param Randomizer $random The seeded source.
	 *
	 * @return array{document: array<mixed>, referenceOnly: array<int, string>}
	 */
	private function synthetic(Randomizer $random): array {
		$types = array_keys(self::VALUES);
		$document = [];
		$paths = [];
		$count = $random->getInt(1, 14);
		for ($index = 0; $index < $count; $index++) {
			$path = self::PATHS[$random->getInt(0, (count(self::PATHS) - 1))];
			if ($random->getInt(0, 4) === 0) {
				$path .= '.v' . $index;
			}

			$type = $types[$random->getInt(0, (count($types) - 1))];
			$values = self::VALUES[$type];
			$leaf = ['$value' => $values[$random->getInt(0, (count($values) - 1))]];
			$typing = $random->getInt(0, 5);
			if ($typing > 0) {
				$leaf['$type'] = $type;
			}

			if ($paths !== [] && $random->getInt(0, 4) === 0) {
				$leaf['$value'] = '{' . $paths[$random->getInt(0, (count($paths) - 1))] . '}';
			}

			if ($random->getInt(0, 7) === 0) {
				$leaf['$value'] = '{' . ['missing.token', $path][$random->getInt(0, 1)] . '}';
			}

			$deprecated = self::DEPRECATED[$random->getInt(0, (count(self::DEPRECATED) - 1))];
			if ($deprecated !== null) {
				$leaf['$deprecated'] = $deprecated;
			}

			if ($random->getInt(0, 6) === 0) {
				$leaf['$extensions'] = ['nl.conduction.thematiq' => ['cssVariable' => ['--nldesign-color-primary', '--x-custom-' . $index, 'not-a-name'][$random->getInt(0, 2)]]];
			}

			$this->place(document: $document, path: $path, leaf: $leaf);
			$paths[] = $path;
		}//end for

		if ($random->getInt(0, 3) === 0) {
			$document['$extensions'] = ['nl.conduction.thematiq' => ['setId' => 'seeded', 'cssOnly' => ['--nldesign-extra' => 'x', 'bad name' => 'y', '--nldesign-num' => 3]]];
		}

		if ($random->getInt(0, 4) === 0) {
			$document['$extensions']['org.example']['version'] = '1.' . $random->getInt(0, 9);
		}

		$referenceOnly = [];
		if ($paths !== [] && $random->getInt(0, 3) === 0) {
			$referenceOnly[] = $paths[$random->getInt(0, (count($paths) - 1))];
		}

		return ['document' => $document, 'referenceOnly' => $referenceOnly];
	}//end synthetic()

	/**
	 * Put a leaf at a dotted path, a group-level `$type` when the slot is taken by a group.
	 *
	 * @param array<mixed> $document The document (by reference).
	 * @param string $path The dotted path.
	 * @param array<string, mixed> $leaf The leaf.
	 *
	 * @return void
	 */
	private function place(array &$document, string $path, array $leaf): void {
		$node = &$document;
		foreach (explode('.', $path) as $segment) {
			if (isset($node[$segment]) === false || is_array($node[$segment]) === false) {
				$node[$segment] = [];
			}

			$node = &$node[$segment];
		}

		foreach ($leaf as $key => $value) {
			$node[$key] = $value;
		}
	}//end place()
}//end class
