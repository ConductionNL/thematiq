<?php

/**
 * Golden test for OverridesController::setOverrides().
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
use OCA\Thematiq\Service\BrandingCaptureService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\OverridesThemingSync;
use OCA\Thematiq\Service\OverridesWrites;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Saves seeded override requests against recording doubles: a reset (with the stock set active
 * or another one, a write that fails, sets with and without earlier values), overrides and dark
 * overrides that are no object, tokens the service refuses, a write that fails, a token set named
 * or not, and branding capture asked for or not, with or without the capture service and on the
 * stock set or another. Records the response and every collaborator call in order, case by case.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Controller\OverridesController
 * @uses   \OCA\Thematiq\Service\OverridesThemingSync
 * @uses   \OCA\Thematiq\Service\OverridesWrites
 */
class OverridesSaveGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/overrides-save.golden.json';

	/**
	 * Every seeded request answers as recorded.
	 *
	 * @return void
	 */
	public function testSetOverridesMatchesTheGoldenRecording(): void {
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
	}//end testSetOverridesMatchesTheGoldenRecording()

	/**
	 * One seeded request, as JSON text.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return string
	 */
	private function runCase(Randomizer $random): string {
		$calls = [];
		$params = [];
		if ($random->getInt(0, 5) === 0) {
			$params['reset'] = [true, 'true', 1][$random->getInt(0, 2)];
		}

		if ($random->getInt(0, 9) !== 0) {
			$params['overrides'] = [['--color-primary' => '#154273'], [], 'not an object', ['--color-primary' => '#154273', '--x' => 'url(evil)']][$random->getInt(
				0,
				3
			)];
		}

		if ($random->getInt(0, 2) === 0) {
			$params['darkOverrides'] = [['--color-primary' => '#99ccff'], [], 'nope'][$random->getInt(0, 2)];
		}

		$single = [
			'tokenSet' => ['', 'rijkshuisstijl', 'nextcloud', 7][$random->getInt(0, 3)],
			'captureTheming' => [true, false, 'true'][$random->getInt(0, 2)],
		];
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParams')->willReturn($params);
		$request->method('getParam')->willReturnCallback(
			static function (string $key, $default = null) use (&$calls, $single) {
				$calls[] = ['getParam', $key, $default];
				return ($single[$key] ?? $default);
			}
		);

		$writeFails = ($random->getInt(0, 7) === 0);
		$earlier = [[], ['--color-primary' => '#000000']][$random->getInt(0, 1)];
		$overrides = $this->createMock(originalClassName: CustomOverridesService::class);
		$overrides->method('findRejected')->willReturnCallback(
			static function (...$arguments) use (&$calls): array {
				$calls[] = ['findRejected', $arguments];
				if (isset($arguments[0]['--x']) === true || isset($arguments['tokens']['--x']) === true) {
					return ['--x' => 'not an allowed value'];
				}

				return [];
			}
		);
		$overrides->method('read')->willReturnCallback(
			static function (...$arguments) use (&$calls, $earlier): array {
				$calls[] = ['read', $arguments];
				return $earlier;
			}
		);
		$overrides->method('write')->willReturnCallback(
			static function (...$arguments) use (&$calls, $writeFails): void {
				$calls[] = ['write', $arguments];
				if ($writeFails === true) {
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

		$capture = null;
		if ($random->getInt(0, 3) !== 0) {
			$capture = $this->createMock(originalClassName: BrandingCaptureService::class);
			$capture->method('capture')->willReturnCallback(
				static function (...$arguments) use (&$calls): array {
					$calls[] = ['capture', $arguments];
					return ['primary_color' => '#154273'];
				}
			);
		}

		$controller = new OverridesController(
			appName: 'thematiq',
			request: $request,
			writes: new OverridesWrites(
				overridesService: $overrides,
				cssParser: $this->createMock(originalClassName: CssParserService::class),
				auditService: $audit
			),
			themingSync: new OverridesThemingSync(config: $config, themingService: $theming, brandingCapture: $capture)
		);
		$response = $controller->setOverrides();

		return (string)json_encode(['status' => $response->getStatus(), 'data' => $response->getData(), 'calls' => $calls], JSON_UNESCAPED_SLASHES);
	}//end runCase()
}//end class
