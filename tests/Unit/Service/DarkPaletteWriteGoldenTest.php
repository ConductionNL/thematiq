<?php

/**
 * Golden test for DarkPaletteService::generateAndWrite(), generateAll() and discoverAllSetIds().
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
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileStore;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Writes the dark variants of a small seeded install: shipped and uploaded sets, eligible and
 * ineligible design systems, a set without a stylesheet, a store that refuses a write, fresh
 * and stale dark files, with and without force, in store mode and in build mode. Fills are
 * written as `transparent` in any case and spacing. The install's token directory holds files
 * that are not sets. Records every outcome, every store write and every discovered id list.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\DarkPaletteService
 * @uses   \OCA\Thematiq\Service\ContrastService
 * @uses   \OCA\Thematiq\Service\CssParserService
 * @uses   \OCA\Thematiq\Service\CssDeclarationScanner
 * @uses   \OCA\Thematiq\Service\DarkPins
 * @uses   \OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore
 */
class DarkPaletteWriteGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/dark-palette-write.golden.json';

	private const FILLS = [
		'--nldesign-component-textbox-background-color',
		'--nldesign-component-badge-background-color',
		'--nldesign-color-primary-light',
		'--nldesign-color-background',
	];

	private const TRANSPARENT = ['transparent', 'TRANSPARENT', ' Transparent ', '#ffffff00', 'rgba(0, 0, 0, 0)'];

	private const MANIFESTS = ['entries', 'missing', 'malformed', 'scalar'];

	/**
	 * The temporary files and directories made for this run, in creation order.
	 *
	 * @var array<int, string>
	 */
	private array $paths = [];

	/**
	 * Remove the temporary install.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach (array_reverse($this->paths) as $path) {
			if (is_dir($path) === true) {
				$this->removeTree(path: $path);
				continue;
			}

			if (is_file($path) === true) {
				unlink($path);
			}
		}

		$this->paths = [];
	}//end tearDown()

	/**
	 * Every case writes and discovers as recorded.
	 *
	 * @return void
	 */
	public function testWritesMatchTheGoldenRecording(): void {
		$actual = [];
		foreach (self::MANIFESTS as $manifest) {
			$appPath = $this->install(manifest: $manifest);
			$uploads = $this->uploads(seed: 1);
			$actual['discover:' . $manifest] = $this->service(appPath: $appPath, uploads: $uploads)->discoverAllSetIds();
		}

		$missing = sys_get_temp_dir() . '/thematiq-dark-write-none-' . getmypid();
		$uploads = $this->uploads(seed: 2);
		$actual['discover:no-tokens-dir'] = $this->service(appPath: $missing, uploads: $uploads)->discoverAllSetIds();

		foreach (self::MANIFESTS as $manifest) {
			for ($seed = 1; $seed <= 6; $seed++) {
				$appPath = $this->install(manifest: $manifest);
				$uploads = $this->uploads(seed: $seed);
				$writes = [];
				$service = $this->service(appPath: $appPath, uploads: $uploads, writes: $writes);
				$case = [];
				foreach (['amsterdam', 'shipped-hc', 'custom-a', 'custom-b', 'custom-none', 'custom-missing', 'custom-readonly', 'custom-t', 'custom-gone'] as $id) {
					$case[$id . ':first'] = $service->generateAndWrite(setId: $id);
					$case[$id . ':again'] = $service->generateAndWrite(setId: $id);
					$case[$id . ':forced'] = $service->generateAndWrite(setId: $id, force: true);
				}

				$case['all'] = $service->generateAll();
				$case['writes'] = $writes;
				$actual['store:' . $manifest . ':' . $seed] = (string)json_encode($case, (JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
			}
		}

		$appPath = $this->install(manifest: 'entries');
		$build = new DarkPaletteService(
			contrast: new ContrastService(),
			parser: new CssParserService(),
			appManager: $this->appManager(appPath: $appPath),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
		$case = [];
		foreach (['amsterdam', 'shipped-hc', 'shipped-plain'] as $id) {
			$case[$id . ':first'] = $build->generateAndWrite(setId: $id);
			$case[$id . ':again'] = $build->generateAndWrite(setId: $id);
			$case[$id . ':file'] = hash(algo: 'sha256', data: (string)@file_get_contents($appPath . '/css/tokens/dark/' . $id . '.css'));
		}

		$actual['build'] = (string)json_encode($case, (JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame(expected: $json, actual: $actual[$name], message: $name);
		}
	}//end testWritesMatchTheGoldenRecording()

	/**
	 * The uploaded sets of one seed: their stylesheets in the store, with seeded transparent fills.
	 *
	 * @param int $seed The seed.
	 *
	 * @return array<string, string> Store name => content.
	 */
	private function uploads(int $seed): array {
		$random = new Randomizer(engine: new Mt19937(seed: $seed));
		$root = \dirname(__DIR__, 3);
		$base = (string)file_get_contents($root . '/css/tokens/amsterdam.css');
		$fills = '';
		foreach (self::FILLS as $fill) {
			if ($random->getInt(0, 1) === 1) {
				$fills .= '  ' . $fill . ': ' . self::TRANSPARENT[$random->getInt(0, (count(self::TRANSPARENT) - 1))] . ";\n";
			}
		}

		$uploads = [];
		foreach (['custom-a', 'custom-b', 'custom-none', 'custom-readonly'] as $id) {
			$uploads['css/tokens/' . $id . '.css'] = $base;
		}

		$uploads['css/tokens/custom-t.css'] = ":root {\n  --nldesign-color-primary: #154273;\n  --nldesign-color-text: #222222;\n"
			. "  --nldesign-component-textbox-color: #333333;\n  --nldesign-component-badge-color: #ffffff;\n" . $fills . "}\n";

		return $uploads;
	}//end uploads()

	/**
	 * A temporary install: a few shipped sets, files that are not sets, and a manifest.
	 *
	 * @param string $manifest Which manifest: `entries`, `missing`, `malformed` or `scalar`.
	 *
	 * @return string The app path.
	 */
	private function install(string $manifest): string {
		$root = \dirname(__DIR__, 3);
		$appPath = sys_get_temp_dir() . '/thematiq-dark-write-' . getmypid() . '-' . count($this->paths);
		mkdir($appPath . '/css/tokens/folder.css', 0777, true);
		$this->paths[] = $appPath;
		$set = (string)file_get_contents($root . '/css/tokens/amsterdam.css');
		foreach (['amsterdam.css', 'shipped-hc.css', 'shipped-plain.css', 'Upper.CSS', 'notes.txt', 'half.cs', 'amsterdam.LICENSE.md'] as $file) {
			file_put_contents($appPath . '/css/tokens/' . $file, $set);
		}

		$entries = [
			['id' => 'amsterdam', 'design_system' => 'nldesign', 'theming' => ['primary_color' => '#ec0000']],
			['id' => 'shipped-hc', 'design_system' => 'high-contrast'],
			'not an entry',
			['id' => 'custom-a', 'theming' => 'not-a-block'],
			['id' => 'custom-b', 'design_system' => 'none', 'theming' => []],
			['id' => 'custom-t', 'design_system' => 'nldesign', 'theming' => ['primary_color' => '#154273']],
			['id' => 'custom-none', 'design_system' => 'other-system'],
			['id' => 'custom-gone', 'design_system' => 'none'],
		];
		$contents = [
			'entries' => (string)json_encode($entries),
			'malformed' => '{not json',
			'scalar' => '"just a string"',
		];
		if (isset($contents[$manifest]) === true) {
			file_put_contents($appPath . '/token-sets.json', $contents[$manifest]);
		}

		return $appPath;
	}//end install()

	/**
	 * The service in store mode over an in-memory store; writes to a name with `readonly` fail.
	 *
	 * @param string $appPath The app path.
	 * @param array<string, string> $uploads The store files, by name.
	 * @param array<int, array{0: string, 1: string}> $writes The recorded writes (by reference).
	 *
	 * @return DarkPaletteService
	 */
	private function service(string $appPath, array &$uploads, array &$writes = []): DarkPaletteService {
		$store = $this->createMock(originalClassName: RuntimeFileStore::class);
		$store->method('read')->willReturnCallback(
			static function (string $name) use (&$uploads): ?string {
				return ($uploads[$name] ?? null);
			}
		);
		$store->method('write')->willReturnCallback(
			static function (string $name, string $content) use (&$uploads, &$writes): void {
				if (str_contains($name, 'readonly') === true) {
					throw new RuntimeException('read-only store');
				}

				$writes[] = [$name, hash(algo: 'sha256', data: $content)];
				$uploads[$name] = $content;
			}
		);
		$store->method('listDirectory')->willReturnCallback(
			static function (string $directory) use (&$uploads): array {
				$names = [];
				foreach (array_keys($uploads) as $name) {
					if (dirname($name) === $directory) {
						$names[] = basename($name);
					}
				}

				return $names;
			}
		);

		return new DarkPaletteService(
			contrast: new ContrastService(),
			parser: new CssParserService(),
			appManager: $this->appManager(appPath: $appPath),
			logger: $this->createMock(originalClassName: LoggerInterface::class),
			store: $store
		);
	}//end service()

	/**
	 * An app manager answering the given app path.
	 *
	 * @param string $appPath The app path.
	 *
	 * @return IAppManager
	 */
	private function appManager(string $appPath): IAppManager {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($appPath);

		return $appManager;
	}//end appManager()

	/**
	 * Remove a directory tree.
	 *
	 * @param string $path The directory.
	 *
	 * @return void
	 */
	private function removeTree(string $path): void {
		foreach ((array)scandir($path) as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$child = $path . '/' . $entry;
			if (is_dir($child) === true && is_link($child) === false) {
				$this->removeTree(path: $child);
				continue;
			}

			unlink($child);
		}

		rmdir($path);
	}//end removeTree()
}//end class
