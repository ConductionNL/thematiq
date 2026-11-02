<?php

/**
 * Golden test for resetting overrides to stock and importing an overrides file.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Controller
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
 * @spec openspec/specs/custom-css-overrides/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\OverridesController;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Resets to stock from the stock set or another one, with earlier overrides or none on either
 * and writes that fail on the first or second set; and imports seeded files: none uploaded, too
 * large, not .css, unreadable, without declarations, with editable and unknown tokens, a write
 * that fails, and a token set named or not. Records the response and every collaborator call in
 * order, case by case. Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Controller\OverridesController
 * @uses   \OCA\Thematiq\Service\TokenRegistry
 */
class OverridesResetImportGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/overrides-reset-import.golden.json';

	/**
	 * The temporary upload of the current case.
	 *
	 * @var string
	 */
	private string $upload = '';

	/**
	 * Remove the temporary upload.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ($this->upload !== '' && is_file($this->upload) === true) {
			unlink($this->upload);
		}

		parent::tearDown();
	}//end tearDown()

	/**
	 * Every seeded reset and import answers as recorded.
	 *
	 * @return void
	 */
	public function testResetAndImportMatchTheGoldenRecording(): void {
		$this->upload = (string)tempnam(sys_get_temp_dir(), 'thematiq-overrides-');
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
	}//end testResetAndImportMatchTheGoldenRecording()

	/**
	 * One seeded reset or import, as JSON text.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return string
	 */
	private function runCase(Randomizer $random): string {
		$calls = [];
		$reset = ($random->getInt(0, 1) === 0);
		$single = ['tokenSet' => ['', 'rijkshuisstijl', 7][$random->getInt(0, 2)]];
		$file = $this->uploadedFile(random: $random);
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParams')->willReturn(['reset' => true]);
		$request->method('getParam')->willReturnCallback(
			static function (string $key, $default = null) use (&$calls, $single) {
				$calls[] = ['getParam', $key, $default];
				return ($single[$key] ?? $default);
			}
		);
		$request->method('getUploadedFile')->willReturn($file);

		$failOn = $random->getInt(0, 6);
		$earlier = [[[], []], [['--a' => '1'], []], [[], ['--b' => '2']], [['--a' => '1'], ['--b' => '2']]][$random->getInt(0, 3)];
		$writes = 0;
		$reads = 0;
		$overrides = $this->createMock(originalClassName: CustomOverridesService::class);
		$overrides->method('read')->willReturnCallback(
			static function (...$arguments) use (&$calls, &$reads, $earlier): array {
				$calls[] = ['read', $arguments];
				return $earlier[min(1, $reads++)];
			}
		);
		$overrides->method('write')->willReturnCallback(
			static function (...$arguments) use (&$calls, &$writes, $failOn): void {
				$calls[] = ['write', $arguments];
				$writes++;
				if ($writes === $failOn) {
					throw new RuntimeException('disk full');
				}
			}
		);

		$active = ['nextcloud', 'rijkshuisstijl'][$random->getInt(0, 1)];
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (...$arguments) use (&$calls, $active): string {
				$calls[] = ['getAppValue', $arguments];
				return $active;
			}
		);
		foreach (['setAppValue', 'deleteAppValue'] as $method) {
			$config->method($method)->willReturnCallback(
				static function (...$arguments) use (&$calls, $method): void {
					$calls[] = [$method, $arguments];
				}
			);
		}

		$audit = $this->createMock(originalClassName: ThemingAuditService::class);
		$audit->method('log')->willReturnCallback(
			static function (...$arguments) use (&$calls): void {
				$calls[] = ['log', $arguments];
			}
		);
		$theming = $this->createMock(originalClassName: ThemingService::class);
		$theming->method('resetToDefaults')->willReturnCallback(
			static function () use (&$calls): array {
				$calls[] = ['resetToDefaults'];
				return [];
			}
		);

		$parsed = [
			null,
			[],
			['--nldesign-color-primary' => '#154273'],
			['--nldesign-color-primary' => '#154273', '--not-a-token' => '1px', '--nldesign-color-text' => '#111111'],
			['--unknown' => 'x'],
		][$random->getInt(0, 4)];
		$parser = $this->createMock(originalClassName: CssParserService::class);
		$parser->method('parseOverridesFile')->willReturnCallback(
			static function (string $css) use (&$calls, $parsed): ?array {
				$calls[] = ['parseOverridesFile', strlen($css)];
				return $parsed;
			}
		);

		$controller = new OverridesController(
			appName: 'thematiq',
			request: $request,
			overridesService: $overrides,
			cssParser: $parser,
			auditService: $audit,
			config: $config,
			themingService: $theming
		);
		if ($reset === true) {
			$response = $controller->setOverrides();
		} else {
			$response = $controller->importOverrides();
		}

		return (string)json_encode(['status' => $response->getStatus(), 'data' => $response->getData(), 'calls' => $calls], JSON_UNESCAPED_SLASHES);
	}//end runCase()

	/**
	 * A seeded upload entry, as Nextcloud hands it over.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return array<string, mixed>
	 */
	private function uploadedFile(Randomizer $random): array {
		file_put_contents($this->upload, ":root {\n  --nldesign-color-primary: #154273;\n}\n");
		$kind = $random->getInt(0, 9);
		if ($kind === 0) {
			return [];
		}

		$file = ['tmp_name' => $this->upload, 'name' => 'overrides.css', 'size' => 64];
		if ($kind === 1) {
			$file['size'] = (300 * 1024);
		}

		if ($kind === 2) {
			$file['name'] = 'overrides.txt';
		}

		return $file;
	}//end uploadedFile()
}//end class
