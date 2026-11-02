<?php

/**
 * Golden test for the pairs and summary of ComplianceReportService::generate().
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
 * @spec openspec/specs/compliance-evidence/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ComplianceReportService;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\DesignSystemService;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use ReflectionClassConstant;

/**
 * Builds seeded app directories (a design system's defaults, the --color-* mapping, the active
 * set's file) with seeded custom overrides and set metadata: literal, translucent and unparseable
 * colours, var() chains that resolve, dangle and run past the depth cap, unmapped tokens, every
 * main-background fallback. Compares the pairs and the summary of each report with the recording.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\ComplianceReportService
 * @uses   \OCA\Thematiq\Service\ContrastService
 * @uses   \OCA\Thematiq\Service\CssParserService
 * @uses   \OCA\Thematiq\Service\CssColorParser
 */
class ComplianceReportGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/compliance-report.golden.json';

	private const COLOURS = ['#000000', '#ffffff', '#154273', '#767676', '#fff', 'rgb(0, 0, 0)', 'rgba(0, 0, 0, 0.5)', 'transparent', 'not-a-colour', 'red', '#12345'];

	private const NLDESIGN = ['--nldesign-color-primary', '--nldesign-color-primary-text', '--nldesign-color-background', '--nldesign-color-text', '--nldesign-color-a', '--nldesign-color-b', '--nldesign-color-c'];

	private string $directory = '';

	/**
	 * Remove the seeded app directory.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->removeDirectory();
		parent::tearDown();
	}//end tearDown()

	/**
	 * Every seeded report has the recorded pairs and summary.
	 *
	 * @return void
	 */
	public function testPairsMatchTheGoldenRecording(): void {
		$actual = [];
		for ($seed = 1; $seed <= 60; $seed++) {
			$report = $this->report(random: new Randomizer(new Mt19937($seed)));
			$actual['seed:' . $seed] = (string)json_encode(['pairs' => $report['pairs'], 'summary' => $report['summary']], (JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(array_keys($expected), array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame($json, $actual[$name], 'case ' . $name);
		}
	}//end testPairsMatchTheGoldenRecording()

	/**
	 * One report over a seeded app directory.
	 *
	 * @param Randomizer $random The seeded source.
	 *
	 * @return array<string, mixed>
	 */
	private function report(Randomizer $random): array {
		$this->removeDirectory();
		$this->directory = sys_get_temp_dir() . '/thematiq-compliance-golden-' . getmypid();
		mkdir($this->directory . '/css/systems/nldesign', 0777, true);
		mkdir($this->directory . '/css/tokens', 0777, true);

		$designSystem = ['nldesign', 'nldesign', 'none', null][$random->getInt(0, 3)];
		$meta = [];
		if ($designSystem !== null) {
			$meta['design_system'] = $designSystem;
		}

		if ($random->getInt(0, 2) === 0) {
			$meta['theming'] = ['background_color' => ['#fafafa', '', 'rgba(0, 0, 0, 0.1)'][$random->getInt(0, 2)]];
		}

		$tokens = array_map(static fn (array $pair): array => [$pair['fg'], $pair['bg']], (new ReflectionClassConstant(ComplianceReportService::class, 'PAIR_MATRIX'))->getValue());
		$tokens = array_values(array_unique(array_merge(...$tokens)));

		$mapping = [];
		$overrides = [];
		foreach ($tokens as $token) {
			if ($random->getInt(0, 5) > 0) {
				$mapping[$token] = $this->pick(random: $random, values: ['var(' . $this->pick(random: $random, values: self::NLDESIGN) . ')', $this->pick(random: $random, values: self::COLOURS)]);
			}

			if ($random->getInt(0, 7) === 0) {
				$overrides[$token] = $this->pick(random: $random, values: self::COLOURS);
			}
		}

		$defaults = [];
		$set = [];
		foreach (self::NLDESIGN as $index => $name) {
			$value = $this->pick(random: $random, values: self::COLOURS);
			if ($random->getInt(0, 3) === 0) {
				$value = 'var(' . (self::NLDESIGN[($index + 1)] ?? '--nldesign-missing') . ')';
			}

			if ($random->getInt(0, 1) === 0) {
				$defaults[$name] = $value;
			}

			if ($random->getInt(0, 2) === 0) {
				$set[$name] = $value;
			}
		}

		$this->writeCss(name: 'css/systems/nldesign/defaults.css', declarations: $defaults);
		$this->writeCss(name: 'css/systems/nldesign/overrides.css', declarations: $mapping);
		$this->writeCss(name: 'css/tokens/golden.css', declarations: $set);

		return $this->service(meta: $meta, overrides: $overrides)->generate();
	}//end report()

	/**
	 * The service over the seeded directory.
	 *
	 * @param array<string, mixed> $meta The active set's metadata.
	 * @param array<string, string> $overrides The custom overrides.
	 *
	 * @return ComplianceReportService
	 */
	private function service(array $meta, array $overrides): ComplianceReportService {
		$overridesService = $this->createMock(CustomOverridesService::class);
		$overridesService->method('read')->willReturn($overrides);
		$designSystems = $this->createMock(DesignSystemService::class);
		$designSystems->method('getTokenSetMeta')->willReturn($meta);
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->directory);
		$appManager->method('getAppVersion')->willReturn('0.0.0');
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn('golden');
		$config->method('getSystemValue')->willReturn('');

		return new ComplianceReportService(
			new ContrastService(),
			new CssParserService(),
			$overridesService,
			$designSystems,
			$this->createMock(CustomTokenSetService::class),
			$appManager,
			$config,
			$this->createMock(IURLGenerator::class),
			$this->createMock(ITimeFactory::class)
		);
	}//end service()

	/**
	 * A seeded pick from a list.
	 *
	 * @param Randomizer $random The seeded source.
	 * @param array<int, string> $values The list.
	 *
	 * @return string
	 */
	private function pick(Randomizer $random, array $values): string {
		return $values[$random->getInt(0, (count($values) - 1))];
	}//end pick()

	/**
	 * Write a `:root` block into the seeded directory.
	 *
	 * @param string $name The app-relative file name.
	 * @param array<string, string> $declarations The declarations.
	 *
	 * @return void
	 */
	private function writeCss(string $name, array $declarations): void {
		$css = ":root {\n";
		foreach ($declarations as $property => $value) {
			$css .= '  ' . $property . ': ' . $value . ";\n";
		}

		file_put_contents($this->directory . '/' . $name, $css . "}\n");
	}//end writeCss()

	/**
	 * Remove the seeded directory, when there is one.
	 *
	 * @return void
	 */
	private function removeDirectory(): void {
		if ($this->directory === '' || is_dir($this->directory) === false) {
			return;
		}

		foreach (['css/systems/nldesign/defaults.css', 'css/systems/nldesign/overrides.css', 'css/tokens/golden.css'] as $file) {
			@unlink($this->directory . '/' . $file);
		}

		foreach (['css/systems/nldesign', 'css/systems', 'css/tokens', 'css', ''] as $directory) {
			@rmdir($this->directory . '/' . $directory);
		}
	}//end removeDirectory()
}//end class
