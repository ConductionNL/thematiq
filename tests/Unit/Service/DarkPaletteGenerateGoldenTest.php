<?php

/**
 * Golden test for DarkPaletteService::generateForSet().
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
use OCA\Thematiq\Service\DarkPins;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileStore;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Generates the dark stylesheet of every shipped set (with the release's manifest, so sets with
 * a dark logo, a skipped design system and a missing file are among them) and of seeded uploaded
 * sets: a shipped set's tokens with seeded colours, transparent fills, a hand-authored dark
 * block and dark values pinned from an uploaded file. Records the stylesheet and the warnings,
 * case by case. Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\DarkPaletteService
 * @uses   \OCA\Thematiq\Service\ContrastService
 * @uses   \OCA\Thematiq\Service\CssParserService
 * @uses   \OCA\Thematiq\Service\CssDeclarationScanner
 * @uses   \OCA\Thematiq\Service\DarkPins
 * @uses   \OCA\Thematiq\Service\ColourRgba
 * @uses   \OCA\Thematiq\Service\TokenSetManifestFile
 */
class DarkPaletteGenerateGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/dark-palette-generate.golden.json';

	private const COLOURS = [
		'#154273', '#ffffff', '#000000', '#e17000', '#d52b1e', '#39870c', 'transparent', 'rgba(0, 0, 0, 0.5)', '#777', 'not-a-colour',
	];

	private const TOKENS = [
		'--nldesign-color-primary',
		'--nldesign-color-primary-text',
		'--nldesign-color-background',
		'--nldesign-color-text',
		'--nldesign-color-error',
		'--nldesign-component-button-secondary-background-color',
		'--nldesign-component-button-secondary-color',
		'--nldesign-color-border',
	];

	/**
	 * Every shipped and seeded set generates the recorded stylesheet.
	 *
	 * @return void
	 */
	public function testGenerateMatchesTheGoldenRecording(): void {
		$appPath = dirname(__DIR__, 3);
		$uploads = [];
		$service = $this->service(appPath: $appPath, uploads: $uploads);

		$shipped = glob($appPath . '/css/tokens/*.css');
		sort($shipped);
		$ids = array_map(static fn (string $path): string => basename($path, '.css'), $shipped);

		$actual = [];
		foreach (array_merge($ids, ['no-such-set']) as $id) {
			$actual['set:' . $id] = $this->digest(result: $service->generateForSet(setId: $id));
		}

		for ($seed = 1; $seed <= 120; $seed++) {
			$random = new Randomizer(new Mt19937($seed));
			$id = 'custom-seed' . $seed;
			$base = (string)file_get_contents($shipped[$random->getInt(0, (count($shipped) - 1))]);
			$uploads = array_merge($uploads, $this->upload(random: $random, id: $id, base: $base));
			$actual['seed:' . $seed] = $this->digest(result: $service->generateForSet(setId: $id));
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame(expected: $json, actual: $actual[$name], message: $name);
		}
	}//end testGenerateMatchesTheGoldenRecording()

	/**
	 * A generated result as its size and SHA-256, the count of its declarations and warnings.
	 *
	 * @param array{css: string, warnings: array<int, mixed>}|null $result The result.
	 *
	 * @return string
	 */
	private function digest(?array $result): string {
		if ($result === null) {
			return 'null';
		}

		$json = (string)json_encode($result, JSON_UNESCAPED_SLASHES);

		return strlen($json) . ' ' . hash(algo: 'sha256', data: $json) . ' ' . substr_count($result['css'], ':') . ' ' . count($result['warnings']);
	}//end digest()

	/**
	 * The store files of one seeded upload: the set's stylesheet and, sometimes, its pinned dark values.
	 *
	 * @param Randomizer $random The case's randomizer.
	 * @param string $id The set id.
	 * @param string $base A shipped set's stylesheet to start from.
	 *
	 * @return array<string, string>
	 */
	private function upload(Randomizer $random, string $id, string $base): array {
		$extra = [];
		foreach (self::TOKENS as $token) {
			if ($random->getInt(0, 2) === 0) {
				$extra[] = '  ' . $token . ': ' . self::COLOURS[$random->getInt(0, (count(self::COLOURS) - 1))] . ';';
			}
		}

		$css = $base . "\n:root {\n" . implode("\n", $extra) . "\n}\n";
		if ($random->getInt(0, 3) === 0) {
			$css .= "@media (prefers-color-scheme: dark) {\n  :root {\n    --nldesign-color-background: #101010;\n    --nldesign-color-text: "
				. ['#f0f0f0', '#202020'][$random->getInt(0, 1)] . ";\n"
				. ['', "    --nldesign-component-button-error-color: #777777;\n"][$random->getInt(0, 1)] . "  }\n}\n";
		}

		$files = ['css/tokens/' . $id . '.css' => $css];
		if ($random->getInt(0, 2) === 0) {
			$files[DarkPins::DIR . $id . '.css'] = ":root {\n  --nldesign-color-primary: " . ['#9ec5ff', '#0b0b0b'][$random->getInt(0, 1)] . ";\n"
				. ['', "  --nldesign-color-background: #2a2a2a;\n"][$random->getInt(0, 1)] . "}\n";
		}

		return $files;
	}//end upload()

	/**
	 * The service under test, on the release's files and a store of uploads.
	 *
	 * @param string $appPath The app root.
	 * @param array<string, string> $uploads The store files, by name.
	 *
	 * @return DarkPaletteService
	 */
	private function service(string $appPath, array &$uploads): DarkPaletteService {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($appPath);
		$store = $this->createMock(originalClassName: RuntimeFileStore::class);
		$store->method('read')->willReturnCallback(
			static function (string $name) use (&$uploads): ?string {
				return ($uploads[$name] ?? null);
			}
		);

		return new DarkPaletteService(
			contrast: new ContrastService(),
			parser: new CssParserService(),
			appManager: $appManager,
			logger: $this->createMock(originalClassName: LoggerInterface::class),
			store: $store
		);
	}//end service()
}//end class
