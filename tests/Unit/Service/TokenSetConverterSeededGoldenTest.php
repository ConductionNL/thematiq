<?php

/**
 * Seeded golden test for TokenSetConverterService::convert() over generated themes and tables.
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
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Converts seeded themes: several selector blocks and at-rules, names under known, unknown and
 * brand prefixes (so a role is declared twice), `var()` chains with cycles, fallbacks and stray
 * whitespace, JSON lists, and names the `never` policy matches directly or on their role. Each
 * seed converts against the shipped mapping table or a seeded variant of it (other transforms,
 * `mix` against a token, `sameColor` conditions, extra policy patterns) and against the shipped
 * stylesheets or a small stylesheet tree. A missing and a malformed table are refused.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\TokenSetConverterService
 * @uses   \OCA\Thematiq\Service\ColorSpaceConverter
 * @uses   \OCA\Thematiq\Service\ColourContrastAdjuster
 * @uses   \OCA\Thematiq\Service\ColourLiteralParser
 * @uses   \OCA\Thematiq\Service\ContrastService
 * @uses   \OCA\Thematiq\Service\ConversionRun
 * @uses   \OCA\Thematiq\Service\ConverterColourRepair
 * @uses   \OCA\Thematiq\Service\ConverterComponentRepair
 * @uses   \OCA\Thematiq\Service\ConverterPairRepair
 * @uses   \OCA\Thematiq\Service\CssColorParser
 * @uses   \OCA\Thematiq\Service\CssParserService
 * @uses   \OCA\Thematiq\Service\CssDeclarationScanner
 * @uses   \OCA\Thematiq\Service\DesignTokenRef
 * @uses   \OCA\Thematiq\Service\DesignTokensAliasResolver
 * @uses   \OCA\Thematiq\Service\DesignTokensMapper
 * @uses   \OCA\Thematiq\Service\DesignTokensMapping
 * @uses   \OCA\Thematiq\Service\DesignTokensWriter
 * @uses   \OCA\Thematiq\Service\DtcgValueTyper
 * @uses   \OCA\Thematiq\Service\FigmaTokenReader
 * @uses   \OCA\Thematiq\Service\FileDarkValues
 * @uses   \OCA\Thematiq\Service\OpenProjectSetFile
 * @uses   \OCA\Thematiq\Service\ThematiqExportLayers
 * @uses   \OCA\Thematiq\Service\ToolTokenFileNormaliser
 * @uses   \OCA\Thematiq\Service\TokensStudioSets
 */
class TokenSetConverterSeededGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/token-set-converter-seeded.golden.json';

	private const SEEDS = 240;

	private const COLOURS = [
		'#154273', '#ffffff', '#000000', '#FFFFFF', '#767676', '#fafafa', '#1a1a1a', '#e17000', '#0b71a1', '#888888',
		'rgb(21, 66, 115)', 'hsl(210, 60%, 40%)', 'transparent', 'rgba(0, 0, 0, 0.5)', 'navy', 'not-a-colour', '4px', '1.5rem',
	];

	private const PREFIXES = ['--brand-', '--acme-', '--utrecht-', '--ams-', '--golden-'];

	private const WORDS = ['x', 'block', 'top', 'small'];

	/**
	 * The temporary app directories made for this run.
	 *
	 * @var array<int, string>
	 */
	private array $directories = [];

	/**
	 * Remove the temporary app directories.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach (array_reverse($this->directories) as $path) {
			if (is_link($path) === true || is_file($path) === true) {
				unlink($path);
				continue;
			}

			if (is_dir($path) === true) {
				rmdir($path);
			}
		}

		$this->directories = [];
	}//end tearDown()

	/**
	 * Every seeded case converts to the recorded result.
	 *
	 * @return void
	 */
	public function testSeededConversionsMatchTheGoldenRecording(): void {
		$root = \dirname(__DIR__, 3);
		$table = json_decode((string)file_get_contents($root . '/scripts/mapping/nlds-to-nextcloud.json'), true);
		$names = $this->namePool(table: $table);
		$tree = $this->stylesheetTree();

		$actual = [];
		$actual['table-missing'] = $this->convertCase(appPath: $this->appDirectory(table: null, css: $tree), content: ".a {\n  --brand-color-primary: #154273;\n}\n", slug: 'brand');
		$actual['table-malformed'] = $this->convertCase(appPath: $this->appDirectory(table: ['rules' => 'none'], css: $tree), content: ".a {\n  --brand-color-primary: #154273;\n}\n", slug: 'brand');

		for ($seed = 1; $seed <= self::SEEDS; $seed++) {
			$random = new Randomizer(engine: new Mt19937(seed: $seed));
			$slug = ['brand', 'golden'][$random->getInt(0, 1)];
			$caseTable = $table;
			if ($random->getInt(0, 2) > 0) {
				$caseTable = $this->variedTable(table: $table, random: $random);
			}

			$css = $root . '/css';
			if ($random->getInt(0, 3) === 0) {
				$css = $tree;
			}

			$content = $this->content(names: $names, slug: $slug, random: $random);
			$actual['seed:' . $seed] = $this->convertCase(appPath: $this->appDirectory(table: $caseTable, css: $css), content: $content, slug: $slug);
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame(expected: $json, actual: $actual[$name], message: 'case ' . $name);
		}
	}//end testSeededConversionsMatchTheGoldenRecording()

	/**
	 * One conversion as JSON; a refusal as its message and code.
	 *
	 * @param string $appPath The app directory the converter reads its table and stylesheets from.
	 * @param string $content The input.
	 * @param string $slug The brand slug.
	 *
	 * @return string
	 */
	private function convertCase(string $appPath, string $content, string $slug): string {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($appPath);
		$converter = new TokenSetConverterService(
			appManager: $appManager,
			cssParser: new CssParserService(),
			contrast: new ContrastService(),
			dtcgMapper: new DesignTokensMapper(),
			fonts: $this->createMock(originalClassName: FontService::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);

		try {
			$result = $converter->convert(content: $content, slug: $slug, displayName: 'Seeded', options: ['sourceName' => 'seeded.css']);
		} catch (RuntimeException $exception) {
			$result = ['refused' => $exception->getMessage(), 'code' => $exception->getCode()];
		}

		if (isset($result['logoAsset']['contents']) === true) {
			$result['logoAsset']['contents'] = base64_encode($result['logoAsset']['contents']);
		}

		return (string)json_encode($result, (JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
	}//end convertCase()

	/**
	 * The declaration names a theme is drawn from: every rule source, the same roles under other
	 * prefixes, names the `never` policy matches, and palette steps.
	 *
	 * @param array<string, mixed> $table The shipped table.
	 *
	 * @return array<int, string>
	 */
	private function namePool(array $table): array {
		$names = [];
		foreach ($table['rules'] as $rule) {
			$names[] = (string)$rule['target'];
			foreach (($rule['sources'] ?? []) as $source) {
				$names[] = (string)$source;
			}
		}

		foreach (array_slice($names, 0, 60) as $index => $name) {
			foreach ([$index, ($index + 2)] as $offset) {
				$names[] = (string)preg_replace('/^--(utrecht|ams|denhaag|conduction|\{p\})-/', self::PREFIXES[($offset % 5)], $name);
			}
		}

		foreach (($table['never'] ?? []) as $index => $entry) {
			$match = str_replace('*', self::WORDS[($index % 4)], (string)$entry['match']);
			$names[] = $match;
			$names[] = (string)preg_replace('/^--(utrecht|ams|denhaag|conduction)-/', '--brand-', $match);
		}

		$names[] = '--brand-radius';
		foreach (['blue-500', 'grey-100', 'grey-900', 'grey-500'] as $step) {
			$names[] = '--{p}-color-' . $step;
		}

		return array_values(array_unique($names));
	}//end namePool()

	/**
	 * A seeded theme: one to three blocks (or a JSON list), an at-rule now and then.
	 *
	 * @param array<int, string> $names The name pool.
	 * @param string $slug The brand slug.
	 * @param Randomizer $random The seeded source.
	 *
	 * @return string
	 */
	private function content(array $names, string $slug, Randomizer $random): string {
		$picked = [];
		$count = $random->getInt(4, 22);
		for ($i = 0; $i < $count; $i++) {
			$picked[] = str_replace('{p}', $slug, $names[$random->getInt(0, (count($names) - 1))]);
		}

		if ($random->getInt(0, 9) === 0) {
			$leaves = [];
			foreach (array_slice($picked, 0, 4) as $name) {
				$leaves[] = ['name' => $name, 'value' => self::COLOURS[$random->getInt(0, (count(self::COLOURS) - 1))]];
			}

			return (string)json_encode($leaves);
		}

		$css = '';
		$blocks = $random->getInt(1, 3);
		for ($block = 0; $block < $blocks; $block++) {
			$selector = ['.theme', ':root', '.theme-' . $block][$random->getInt(0, 2)];
			$css .= $selector . " {\n";
			foreach ($picked as $name) {
				if ($random->getInt(0, $blocks) === 0) {
					continue;
				}

				$css .= '  ' . $name . ': ' . $this->value(picked: $picked, random: $random) . ";\n";
			}

			$css .= "}\n";
		}

		if ($random->getInt(0, 4) === 0) {
			$css .= "@media (prefers-color-scheme: dark) {\n  .theme {\n    " . $picked[0] . ": #000000;\n  }\n}\n@import url(\"x.css\");\n";
		}

		return $css;
	}//end content()

	/**
	 * A seeded value: a colour or length, or a `var()` reference with or without a fallback.
	 *
	 * @param array<int, string> $picked The names in this theme.
	 * @param Randomizer $random The seeded source.
	 *
	 * @return string
	 */
	private function value(array $picked, Randomizer $random): string {
		$literal = self::COLOURS[$random->getInt(0, (count(self::COLOURS) - 1))];
		$reference = $picked[$random->getInt(0, (count($picked) - 1))];
		$draw = $random->getInt(0, 9);
		if ($draw < 5) {
			return $literal;
		}

		if ($draw === 5) {
			return 'var(' . $reference . ')';
		}

		if ($draw === 6) {
			return 'var( ' . $reference . ' , ' . $literal . ' )';
		}

		if ($draw === 7) {
			return 'var(--missing-token, ' . $literal . ') ';
		}

		if ($draw === 8) {
			return '1px solid var(' . $reference . ', var(--missing-token, ' . $literal . '))';
		}

		return 'var(--missing-token, )';
	}//end value()

	/**
	 * The shipped table with seeded transforms, conditions and policy patterns.
	 *
	 * @param array<string, mixed> $table The shipped table.
	 * @param Randomizer $random The seeded source.
	 *
	 * @return array<string, mixed>
	 */
	private function variedTable(array $table, Randomizer $random): array {
		$transforms = [
			['darken', ['fraction' => 0.2]],
			['mix', ['with' => '#000000', 'weight' => 0.3]],
			['mix', ['with' => '--nldesign-color-primary', 'weight' => 0.4]],
			['mix', ['with' => '--nldesign-color-unset', 'weight' => 0.4]],
			['mix', []],
			['rgbTriplet', []],
			['alpha', ['fraction' => 0.25]],
			['radiusScale', ['factor' => 3, 'clampPx' => 10]],
			['onColor', ['light' => '#fafafa', 'dark' => '#111111']],
			['literal', ['template' => '0 0 0 2px {value}']],
			['literal', []],
			['copy', []],
			['unknown-kind', []],
		];
		foreach ($table['rules'] as $index => $rule) {
			if ($random->getInt(0, 3) === 0) {
				[$kind, $args] = $transforms[$random->getInt(0, (count($transforms) - 1))];
				$table['rules'][$index]['transform'] = $kind;
				$table['rules'][$index]['args'] = $args;
			}

			if ($random->getInt(0, 9) === 0) {
				$table['rules'][$index]['when'] = [
					'kind' => ['sameColor', 'isDark', 'other'][$random->getInt(0, 2)],
					'left' => '--nldesign-color-primary',
					'right' => ['--nldesign-color-action', '--nldesign-color-text', '--nldesign-color-unset'][$random->getInt(0, 2)],
					'ref' => '--nldesign-color-primary',
				];
			}
		}

		$table['never'][] = ['match' => '--utrecht-*-border-width', 'action' => 'skip', 'reason' => 'seeded-role'];
		$table['never'][] = ['match' => '--brand-*-width', 'action' => 'skip', 'reason' => 'seeded-brand-role'];
		$table['never'][] = ['match' => '--{p}-color-grey-*', 'action' => 'skip', 'reason' => 'seeded-brand'];
		$table['never'][] = ['match' => '', 'action' => 'skip', 'reason' => 'seeded-empty'];
		$table['never'][] = ['match' => 'radius', 'action' => 'skip', 'reason' => 'seeded-bare'];

		return $table;
	}//end variedTable()

	/**
	 * A small stylesheet tree: a nested directory, a token set and a runtime file to skip, a non-CSS file.
	 *
	 * @return string The tree's path.
	 */
	private function stylesheetTree(): string {
		$base = sys_get_temp_dir() . '/thematiq-seeded-css-' . getmypid();
		$files = [
			'a.css' => ":root {\n  --nldesign-color-unset: #123456;\n  color: var(--nldesign-color-border-subtle);\n}\n",
			'nested/b.css' => ".x {\n  background: var(--nldesign-color-background, #fff);\n  --nldesign-seeded-extra: 1px;\n}\n",
			'tokens/c.css' => ":root {\n  --nldesign-from-token-set: #000;\n}\n",
			'custom-overrides.css' => ":root {\n  --nldesign-from-runtime: #000;\n}\n",
			'notes.txt' => '--nldesign-from-text: #000;',
		];
		$this->mkdir(path: $base);
		$this->mkdir(path: $base . '/nested');
		$this->mkdir(path: $base . '/tokens');
		foreach ($files as $name => $content) {
			file_put_contents($base . '/' . $name, $content);
			$this->directories[] = $base . '/' . $name;
		}

		return $base;
	}//end stylesheetTree()

	/**
	 * A temporary app directory with the given table (none when null) and stylesheet directory.
	 *
	 * @param array<string, mixed>|null $table The mapping table.
	 * @param string $css The stylesheet directory, linked in as `css`.
	 *
	 * @return string The app path.
	 */
	private function appDirectory(?array $table, string $css): string {
		$base = sys_get_temp_dir() . '/thematiq-seeded-app-' . getmypid() . '-' . count($this->directories);
		$this->mkdir(path: $base);
		$this->mkdir(path: $base . '/scripts');
		$this->mkdir(path: $base . '/scripts/mapping');
		if ($table !== null) {
			file_put_contents($base . '/scripts/mapping/nlds-to-nextcloud.json', (string)json_encode($table));
			$this->directories[] = $base . '/scripts/mapping/nlds-to-nextcloud.json';
		}

		symlink($css, $base . '/css');
		$this->directories[] = $base . '/css';

		return $base;
	}//end appDirectory()

	/**
	 * Make a directory and remember it for removal.
	 *
	 * @param string $path The directory.
	 *
	 * @return void
	 */
	private function mkdir(string $path): void {
		if (is_dir($path) === false) {
			mkdir($path);
		}

		$this->directories[] = $path;
	}//end mkdir()
}//end class
