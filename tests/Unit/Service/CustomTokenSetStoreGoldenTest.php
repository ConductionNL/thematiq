<?php

/**
 * Golden test for CustomTokenSetService::store().
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

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileStore;
use OCP\App\IAppManager;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Stores seeded token sets into an in-memory runtime store: names that slug to nothing or
 * collide with a stored file or a manifest entry, empty and given descriptions, declarations
 * taken from shipped sets, a given or derived file text, versions, import warnings, theming
 * overrides, logo assets of each extension (and refused ones), design systems, a store that
 * refuses the set file or the logo, and a dark palette that throws. Records the result or the
 * exception, every file the store holds and the app config afterwards, case by case.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\CustomTokenSetService
 * @uses   \OCA\Thematiq\Service\ContrastService
 * @uses   \OCA\Thematiq\Service\CssParserService
 * @uses   \OCA\Thematiq\Service\CssDeclarationScanner
 * @uses   \OCA\Thematiq\Service\CustomTokenSetValidator
 * @uses   \OCA\Thematiq\Service\DarkPaletteService
 * @uses   \OCA\Thematiq\Service\DarkPins
 */
class CustomTokenSetStoreGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/custom-token-set-store.golden.json';

	private const NAMES = ['Gemeente Zuid', 'gemeente zuid', '!!!', '', 'Ünïcode Stad', 'A', 'Lange naam met spaties en - streepjes', 'x/y'];

	private const LOGOS = [null, ['path' => 'logo.svg', 'contents' => '<svg/>'], ['path' => 'a/logo.PNG', 'contents' => 'png'], ['path' => 'logo.exe', 'contents' => 'x'], ['contents' => 'no path']];

	/**
	 * Every seeded store call has the recorded outcome.
	 *
	 * @return void
	 */
	public function testStoreMatchesTheGoldenRecording(): void {
		$parser = new CssParserService();
		$sets = glob(dirname(__DIR__, 3) . '/css/tokens/*.css');
		sort($sets);
		$sources = [];
		foreach (array_slice($sets, 0, 12) as $path) {
			$sources[] = ($parser->parseDeclarations(content: (string)file_get_contents($path)) ?? []);
		}

		$actual = [];
		for ($seed = 1; $seed <= 160; $seed++) {
			$actual['seed:' . $seed] = $this->runCase(random: new Randomizer(new Mt19937($seed)), sources: $sources);
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame(expected: $json, actual: $actual[$name], message: $name);
		}
	}//end testStoreMatchesTheGoldenRecording()

	/**
	 * One seeded store call, as JSON text.
	 *
	 * @param Randomizer $random The case's randomizer.
	 * @param array<int, array<string, string>> $sources Declarations of shipped sets.
	 *
	 * @return string
	 */
	private function runCase(Randomizer $random, array $sources): string {
		$appConfig = [];
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, $default = '') use (&$appConfig) {
				return ($appConfig[$key] ?? $default);
			}
		);
		$config->method('setAppValue')->willReturnCallback(
			static function (string $app, string $key, $value) use (&$appConfig): void {
				$appConfig[$key] = $value;
			}
		);

		$refused = [];
		if ($random->getInt(0, 9) === 0) {
			$refused[] = 'css/tokens/';
		}

		if ($random->getInt(0, 4) === 0) {
			$refused[] = 'img/logos/';
		}

		$store = $this->memoryStore(refused: $refused);
		$name = self::NAMES[$random->getInt(0, (count(self::NAMES) - 1))];
		$this->seedCollision(random: $random, context: ['store' => $store, 'name' => $name, 'config' => &$appConfig]);

		$declarations = $sources[$random->getInt(0, (count($sources) - 1))];
		if ($random->getInt(0, 3) === 0) {
			$declarations = array_slice($declarations, 0, $random->getInt(0, 5), true);
		}

		$arguments = [
			'displayName' => $name,
			'description' => ['', 'Beschrijving'][$random->getInt(0, 1)],
			'declarations' => $declarations,
		];
		$optional = [
			'version' => [null, '', '2.1.0'][$random->getInt(0, 2)],
			'importWarnings' => [[], [['path' => 'a.b', 'message' => 'deprecated']]][$random->getInt(0, 1)],
			'css' => [null, ":root {\n\t--nldesign-color-primary: #154273;\n}\n"][$random->getInt(0, 1)],
			'theming' => [[], ['background_color' => '#fafafa'], ['logo' => 'old.svg', 'primary_color' => '#000000']][$random->getInt(0, 2)],
			'logoAsset' => self::LOGOS[$random->getInt(0, (count(self::LOGOS) - 1))],
			'designSystem' => [null, '', 'lasuite', 'nldesign'][$random->getInt(0, 3)],
		];
		foreach ($optional as $key => $value) {
			if ($random->getInt(0, 4) !== 0) {
				$arguments[$key] = $value;
			}
		}

		$service = new CustomTokenSetService(
			store: $store,
			config: $config,
			validator: new CustomTokenSetValidator(),
			contrast: new ContrastService(),
			darkPalette: $this->darkPalette(throws: ($random->getInt(0, 5) === 0), store: $store)
		);

		try {
			$outcome = ['result' => $service->store(...$arguments)];
		} catch (RuntimeException $e) {
			$outcome = ['exception' => $e->getCode(), 'message' => $e->getMessage()];
		}

		$outcome['passed'] = array_keys($arguments);
		$outcome['files'] = $store->snapshot();
		$outcome['config'] = $appConfig;

		return (string)json_encode($outcome, (JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
	}//end runCase()

	/**
	 * Put a set that collides with the name in the store or the manifest, for some seeds.
	 *
	 * @param Randomizer $random The case's randomizer.
	 * @param array{store: RuntimeFileStore, name: string, config: array<string, string>} $context The case.
	 *
	 * @return void
	 */
	private function seedCollision(Randomizer $random, array $context): void {
		$id = 'custom-' . trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($context['name'])), '-');
		$roll = $random->getInt(0, 5);
		if ($roll === 0) {
			$context['store']->put(name: 'css/tokens/' . $id . '.css', content: ':root {}');
		}

		if ($roll === 1) {
			$context['config']['custom_token_sets'] = (string)json_encode([$id => ['name' => 'earlier'], 'custom-other' => ['name' => 'other']]);
		}

		if ($roll === 2) {
			$context['config']['custom_token_sets'] = (string)json_encode(['custom-other' => ['name' => 'other']]);
		}
	}//end seedCollision()

	/**
	 * The dark palette the service generates with: the real one on the same store, or one that throws.
	 *
	 * @param bool $throws Whether generation throws.
	 * @param RuntimeFileStore $store The case's store.
	 *
	 * @return DarkPaletteService
	 */
	private function darkPalette(bool $throws, RuntimeFileStore $store): DarkPaletteService {
		if ($throws === true) {
			$dark = $this->createMock(originalClassName: DarkPaletteService::class);
			$dark->method('generateAndWrite')->willThrowException(new RuntimeException('dark generation failed'));
			return $dark;
		}

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn(dirname(__DIR__, 3));

		return new DarkPaletteService(
			contrast: new ContrastService(),
			parser: new CssParserService(),
			appManager: $appManager,
			logger: $this->createMock(originalClassName: LoggerInterface::class),
			store: $store
		);
	}//end darkPalette()

	/**
	 * An in-memory runtime store that refuses writes under the given prefixes.
	 *
	 * @param array<int, string> $refused Name prefixes whose writes throw.
	 *
	 * @return RuntimeFileStore
	 */
	private function memoryStore(array $refused): RuntimeFileStore {
		return new class($refused) implements RuntimeFileStore {
			/**
			 * Files by name.
			 *
			 * @var array<string, string>
			 */
			private array $files = [];

			/**
			 * Constructor.
			 *
			 * @param array<int, string> $refused Name prefixes whose writes throw.
			 */
			public function __construct(
				private array $refused,
			) {
			}

			public function read(string $name): ?string {
				return ($this->files[$name] ?? null);
			}

			public function write(string $name, string $content): void {
				foreach ($this->refused as $prefix) {
					if (str_starts_with($name, $prefix) === true) {
						throw new RuntimeException('refused ' . $name);
					}
				}

				$this->files[$name] = $content;
			}

			/**
			 * Place a file without the refusal check.
			 *
			 * @param string $name The name.
			 * @param string $content The content.
			 *
			 * @return void
			 */
			public function put(string $name, string $content): void {
				$this->files[$name] = $content;
			}

			public function exists(string $name): bool {
				return isset($this->files[$name]);
			}

			public function delete(string $name): void {
				unset($this->files[$name]);
			}

			public function listDirectory(string $directory): array {
				$names = [];
				foreach (array_keys($this->files) as $name) {
					if (str_starts_with($name, rtrim($directory, '/') . '/') === true) {
						$names[] = basename($name);
					}
				}

				sort($names);
				return $names;
			}

			public function revision(string $name): string {
				return hash('sha256', ($this->files[$name] ?? ''));
			}

			/**
			 * Every file as name => sha256, sorted by name.
			 *
			 * @return array<string, string>
			 */
			public function snapshot(): array {
				$hashes = array_map(static fn (string $content): string => hash('sha256', $content), $this->files);
				ksort($hashes);
				return $hashes;
			}
		};
	}//end memoryStore()
}//end class
