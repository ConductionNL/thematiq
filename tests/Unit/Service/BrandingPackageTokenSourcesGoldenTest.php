<?php

/**
 * Golden test for the DTCG token sources of a branding package.
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
 * @spec openspec/specs/theme-as-code/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\BrandingPackageReader;
use OCA\Thematiq\Service\BrandingPackageService;
use OCA\Thematiq\Service\ConfigBundleService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\DtcgValueChecker;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\FontValidator;
use OCA\Thematiq\Service\PackageTokenSourceConverter;
use OCA\Thematiq\Service\TokenSetConverterService;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Imports seeded packages as a dry run: token source ids that are and are not custom set ids,
 * documents that are not JSON or carry refused colours, a converter that throws, returns no CSS,
 * reports errors or converts (with and without theming and an upstream version), and bundles whose
 * custom sets are absent, not a list, keyed loosely or already name the source. Records the
 * result, the bundle handed to the bundle dry run and every converter call, case by case.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\BrandingPackageService
 * @covers \OCA\Thematiq\Service\PackageTokenSourceConverter
 */
class BrandingPackageTokenSourcesGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/branding-package-token-sources.golden.json';

	private const IDS = ['custom-alpha', 'custom-beta', 'custom-gamma', 'gemeente', 'custom-', 'Custom-Delta'];

	/**
	 * Every seeded package imports to the recorded result and calls.
	 *
	 * @return void
	 */
	public function testTokenSourcesMatchTheGoldenRecording(): void {
		$actual = [];
		for ($seed = 1; $seed <= 300; $seed++) {
			$actual['seed:' . $seed] = $this->runCase(random: new Randomizer(new Mt19937($seed)));
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame(expected: $json, actual: $actual[$name], message: $name);
		}
	}//end testTokenSourcesMatchTheGoldenRecording()

	/**
	 * One seeded package, as JSON text.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return string
	 */
	private function runCase(Randomizer $random): string {
		$calls = [];
		$package = [
			'bundle' => $this->bundle(random: $random),
			'fonts' => [],
			'tokens' => $this->tokens(random: $random),
			'revision' => 'r' . $random->getInt(1, 9),
			'hash' => 'sha256:' . $random->getInt(100, 999),
		];

		$reader = $this->createMock(originalClassName: BrandingPackageReader::class);
		$reader->method('read')->willReturn($package);

		$bundles = $this->createMock(originalClassName: ConfigBundleService::class);
		$bundles->method('import')->willReturnCallback(
			static function (array $bundle, bool $dryRun = false) use (&$calls): array {
				$calls[] = ['bundle', $bundle, $dryRun];
				return ['valid' => true, 'sections' => ['customTokenSets' => ['count' => count((array)($bundle['customTokenSets'] ?? []))]]];
			}
		);

		$fonts = $this->createMock(originalClassName: FontService::class);
		$fonts->method('getManifest')->willReturn([]);

		$converter = $this->createMock(originalClassName: TokenSetConverterService::class);
		$converter->method('convert')->willReturnCallback(
			static function (
				string $content,
				string $slug,
				string $displayName,
				?string $sourceName = null,
				?string $assetName = null
			) use (&$calls): array {
				$calls[] = ['convert', compact('content', 'slug', 'displayName', 'sourceName', 'assetName')];
				$mode = (json_decode($content, true)['mode'] ?? 'ok');
				if ($mode === 'throw') {
					throw new RuntimeException('broken source for ' . $slug);
				}

				$result = [
					'css' => ":root {\n  --nldesign-color-primary: #" . substr(md5($slug), 0, 6) . ";\n}\n",
					'errors' => [],
					'importWarnings' => ['w-' . $slug],
					'manifestEntry' => [],
				];
				if ($mode === 'blank') {
					$result['css'] = "  \n";
				}

				if ($mode === 'errors') {
					$result['errors'] = ['unmapped'];
				}

				if ($mode === 'themed') {
					$result['manifestEntry'] = ['theming' => ['primary_color' => '#0b71a1'], 'upstreamVersion' => '2.' . strlen($slug)];
				}

				return $result;
			}
		);

		$customSets = $this->createMock(originalClassName: CustomTokenSetService::class);
		$customSets->method('isCustomId')->willReturnCallback(
			static fn (string $id): bool => preg_match('/^custom-[a-z0-9][a-z0-9-]*$/', $id) === 1
		);

		$values = $this->createMock(originalClassName: DtcgValueChecker::class);
		$values->method('findInvalidColours')->willReturnCallback(
			static fn (array $document): array => array_map('strval', (array)($document['bad'] ?? []))
		);

		$service = $this->service(reader: $reader, bundles: $bundles, fonts: $fonts, converter: $converter, customSets: $customSets, values: $values);
		$result = $service->import(path: '/package', dryRun: true);
		$read = $service->readBundle(path: '/package');

		return (string)json_encode(['result' => $result, 'read' => $read, 'calls' => $calls], JSON_UNESCAPED_SLASHES);
	}//end runCase()

	/**
	 * The service under test.
	 *
	 * @param BrandingPackageReader $reader The reader.
	 * @param ConfigBundleService $bundles The bundle service.
	 * @param FontService $fonts The font service.
	 * @param TokenSetConverterService $converter The converter.
	 * @param CustomTokenSetService $customSets The custom set id rules.
	 * @param DtcgValueChecker $values The value rules.
	 *
	 * @return BrandingPackageService
	 */
	private function service(
		BrandingPackageReader $reader,
		ConfigBundleService $bundles,
		FontService $fonts,
		TokenSetConverterService $converter,
		CustomTokenSetService $customSets,
		DtcgValueChecker $values,
	): BrandingPackageService {
		return new BrandingPackageService(
			reader: $reader,
			bundleService: $bundles,
			fontService: $fonts,
			fontValidator: new FontValidator(),
			tokenSources: new PackageTokenSourceConverter(converter: $converter, customSets: $customSets, values: $values)
		);
	}//end service()

	/**
	 * A seeded bundle whose custom sets vary in shape.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return array<string, mixed>
	 */
	private function bundle(Randomizer $random): array {
		$bundle = ['format' => ConfigBundleService::FORMAT, 'config' => ['tokenSet' => 'nextcloud']];
		$shape = $random->getInt(0, 9);
		if ($shape === 0) {
			return $bundle;
		}

		if ($shape === 1) {
			$bundle['customTokenSets'] = 'not a list';
			return $bundle;
		}

		$sets = [];
		foreach (self::IDS as $index => $id) {
			if ($random->getInt(0, 2) !== 0) {
				continue;
			}

			$set = ['id' => $id, 'css' => ':root{}', 'theming' => ['background' => '#fff']];
			$name = $random->getInt(0, 3);
			if ($name > 0) {
				$set['name'] = ['', '  ', ' Bestaand ' . $index][$name - 1];
			}

			$sets[($index * 3) + 1] = $set;
		}

		if ($random->getInt(0, 4) === 0) {
			$sets[] = 'not a set';
		}

		$bundle['customTokenSets'] = $sets;

		return $bundle;
	}//end bundle()

	/**
	 * Seeded token sources keyed by id.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return array<string, string>
	 */
	private function tokens(Randomizer $random): array {
		$tokens = [];
		$count = $random->getInt(0, 4);
		for ($index = 0; $index < $count; $index++) {
			$id = self::IDS[$random->getInt(0, (count(self::IDS) - 1))];
			$document = ['mode' => ['ok', 'ok', 'themed', 'throw', 'blank', 'errors'][$random->getInt(0, 5)]];
			$kind = $random->getInt(0, 6);
			if ($kind === 0) {
				$tokens[$id] = '{not json';
				continue;
			}

			if ($kind === 1) {
				$document['bad'] = ['color.primary' => 'reddish', 'color.text' => 12];
			}

			$tokens[$id] = (string)json_encode($document);
		}

		return $tokens;
	}//end tokens()
}//end class
