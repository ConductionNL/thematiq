<?php

/**
 * Golden test for DarkPaletteService::verifyAndRepair().
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
 * @spec openspec/specs/dark-mode/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\OverridesCssBuilder;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileStore;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use ReflectionClassConstant;

/**
 * Runs the repair over every shipped token set as it is, and over seeded variants of them:
 * pair colours replaced with light, dark, translucent and unparseable values, hand-authored
 * tokens protected at random, and the editor's own pairs instead of the brand pairs.
 * Records the declarations that changed and the final warnings of each case.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\DarkPaletteService
 * @uses   \OCA\Thematiq\Service\ContrastService
 * @uses   \OCA\Thematiq\Service\CssParserService
 * @uses   \OCA\Thematiq\Service\CssDeclarationScanner
 */
class DarkPaletteRepairGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/dark-palette-repair.golden.json';

	private const COLOURS = [
		'#000000', '#ffffff', '#154273', '#767676', '#fafafa', '#1a1a1a', '#ff0000', '#ffff00', '#0000ff',
		'#888888', 'rgba(0, 0, 0, 0.5)', 'rgba(255, 255, 255, 0.6)', 'rgba(21, 66, 115, 0.4)', '#00000080', 'transparent', 'not-a-colour', 'var(--nldesign-color-primary)', '#fff',
	];

	private const PAGE_TOKENS = ['--nldesign-color-primary', '--nldesign-color-primary-text', '--nldesign-color-background', '--nldesign-color-text'];

	/**
	 * Every case repairs to the recorded result.
	 *
	 * @return void
	 */
	public function testRepairMatchesTheGoldenRecording(): void {
		$service = new DarkPaletteService(
			contrast: new ContrastService(),
			parser: new CssParserService(),
			appManager: $this->createMock(IAppManager::class),
			logger: $this->createMock(LoggerInterface::class),
			store: $this->createMock(RuntimeFileStore::class)
		);

		$parser = new CssParserService();
		$sets = glob(dirname(__DIR__, 3) . '/css/tokens/*.css');
		sort($sets);
		$bases = [];
		foreach ($sets as $path) {
			$bases[basename($path, '.css')] = ($parser->parseDeclarations(content: (string)file_get_contents($path)) ?? []);
		}

		$actual = [];
		foreach ($bases as $id => $declarations) {
			$actual['set:' . $id] = $this->encode(input: $declarations, result: $service->verifyAndRepair(declarations: $declarations));
		}

		$tokens = $this->pairTokens();
		$ids = array_keys($bases);
		for ($seed = 1; $seed <= 300; $seed++) {
			$random = new Randomizer(new Mt19937($seed));
			$declarations = $bases[$ids[$random->getInt(0, (count($ids) - 1))]];
			foreach ($random->pickArrayKeys(array_flip($tokens), $random->getInt(1, 8)) as $token) {
				$declarations[$token] = self::COLOURS[$random->getInt(0, (count(self::COLOURS) - 1))];
			}

			$protected = [];
			foreach ($tokens as $token) {
				if ($random->getInt(0, 5) === 0) {
					$protected[] = $token;
				}
			}

			$pairs = null;
			if ($random->getInt(0, 2) === 0) {
				$pairs = OverridesCssBuilder::DARK_PAIRS;
			}

			$actual['seed:' . $seed] = $this->encode(
				input: $declarations,
				result: $service->verifyAndRepair(declarations: $declarations, protectedTokens: $protected, pairs: $pairs)
			);
		}//end for

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(array_keys($expected), array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame($json, $actual[$name], $name);
		}
	}//end testRepairMatchesTheGoldenRecording()

	/**
	 * The tokens of every pair the repair measures.
	 *
	 * @return string[]
	 */
	private function pairTokens(): array {
		$tokens = self::PAGE_TOKENS;
		$pairs = array_merge(
			(new ReflectionClassConstant(DarkPaletteService::class, 'CONTROL_PAIRS'))->getValue(),
			(new ReflectionClassConstant(ContrastService::class, 'PAIRS'))->getValue(),
			OverridesCssBuilder::DARK_PAIRS
		);
		foreach ($pairs as $pair) {
			foreach (['fg', 'bg', 'foreground', 'background'] as $key) {
				if (isset($pair[$key]) === true && is_string($pair[$key]) === true) {
					$tokens[] = $pair[$key];
				}
			}
		}

		$tokens = array_values(array_unique($tokens));
		sort($tokens);
		return $tokens;
	}//end pairTokens()

	/**
	 * The declarations the repair changed and its final warnings, as JSON text.
	 *
	 * @param array<string, string> $input The declarations passed in.
	 * @param array{declarations: array<string, string>, warnings: array<int, array<string, mixed>>} $result The repair result.
	 *
	 * @return string
	 */
	private function encode(array $input, array $result): string {
		$changed = [];
		foreach ($result['declarations'] as $token => $value) {
			if (($input[$token] ?? null) !== $value) {
				$changed[$token] = $value;
			}
		}

		return (string)json_encode(
			['changed' => $changed, 'keys' => count($result['declarations']), 'warnings' => $result['warnings']],
			(JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)
		);
	}//end encode()
}//end class
