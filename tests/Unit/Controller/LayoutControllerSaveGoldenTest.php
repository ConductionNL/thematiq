<?php

/**
 * Golden recording of LayoutController::save().
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-the-workplace-layout-is-an-admin-option
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\LayoutController;
use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\LayoutOptionsService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Seeded saves of the layout options: each case sends a random subset of the seven fields, from valid, empty and
 * unknown values, and records the status, the answer, what was stored and what was audited.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Controller\LayoutController
 * @uses   \OCA\Thematiq\Service\LayoutOptionsService
 * @uses   \OCA\Thematiq\Service\LayoutOptionValues
 */
class LayoutControllerSaveGoldenTest extends TestCase {
	/**
	 * The recording.
	 */
	private const FIXTURE = __DIR__ . '/../fixtures/golden/layout-controller-save.golden.json';

	/**
	 * The values each field is drawn from.
	 */
	private const POOLS = [
		'workplaceLayout' => ['', 'default', 'light', 'wide'],
		'brandStripe' => ['', '1', '0', 'true'],
		'navigationWidth' => ['', '200', '300', '480', '50', 'abc'],
		'navigationActiveStyle' => ['', 'default', 'soft', 'bold'],
		'brandStripePlacement' => ['', 'header-and-login', 'header', 'login', 'footer'],
		'loginWatermark' => ['', '1', '0', 'yes'],
		'headerStyle' => ['', 'default', 'workplace', 'dark'],
	];

	/**
	 * How many leading values of each pool are valid; seeds past 60 draw only from those.
	 */
	private const VALID = [
		'workplaceLayout' => 3,
		'brandStripe' => 3,
		'navigationWidth' => 4,
		'navigationActiveStyle' => 3,
		'brandStripePlacement' => 4,
		'loginWatermark' => 3,
		'headerStyle' => 3,
	];

	/**
	 * Every seeded save answers, stores and audits what was recorded.
	 *
	 * @return void
	 */
	public function testSaveMatchesTheGoldenRecording(): void {
		$actual = [];
		for ($seed = 1; $seed <= 100; $seed++) {
			$actual['seed:' . $seed] = $this->recordSeed(seed: $seed);
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		$this->assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $json) {
			$this->assertSame(expected: $json, actual: $actual[$name], message: $name);
		}
	}//end testSaveMatchesTheGoldenRecording()

	/**
	 * One seeded save, recorded as JSON text.
	 *
	 * @param int $seed The seed.
	 *
	 * @return string
	 */
	private function recordSeed(int $seed): string {
		$random = new Randomizer(new Mt19937($seed));
		$params = [];
		foreach (self::POOLS as $field => $pool) {
			$choices = match ($seed > 60) {
				true => self::VALID[$field],
				default => count($pool),
			};
			if ($random->getInt(0, 2) !== 0) {
				$params[$field] = $pool[$random->getInt(0, ($choices - 1))];
			}
		}

		$stored = [];
		$audit = [];
		$response = $this->save(params: $params, stored: $stored, audit: $audit);

		return (string)json_encode(
			['params' => $params, 'status' => $response->getStatus(), 'data' => $response->getData(), 'stored' => $stored, 'audit' => $audit],
			JSON_UNESCAPED_SLASHES
		);
	}//end recordSeed()

	/**
	 * Save through a controller on remembering doubles.
	 *
	 * The values go in as request parameters and, where save() declares parameters, also as its named arguments,
	 * so the recording describes the same request whichever way save() reads it.
	 *
	 * @param array<string, string> $params The fields sent.
	 * @param array<string, string> $stored The config writes, filled in.
	 * @param array<int, array<string, mixed>> $audit The audit entries, filled in.
	 *
	 * @return \OCP\AppFramework\Http\JSONResponse
	 */
	private function save(array $params, array &$stored, array &$audit): \OCP\AppFramework\Http\JSONResponse {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => ($stored[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			static function (string $app, string $key, string $value) use (&$stored): void {
				$stored[$key] = $value;
			}
		);
		$designSystems = $this->createMock(originalClassName: DesignSystemService::class);
		$designSystems->method('getTokenSetMeta')->willReturn(['layout' => ['workplace_layout' => 'light', 'brand_stripe' => true]]);
		$active = $this->createMock(originalClassName: ActiveTokenSetService::class);
		$active->method('getActive')->willReturn('zuiddrecht');
		$auditService = $this->createMock(originalClassName: ThemingAuditService::class);
		$auditService->method('log')->willReturnCallback(
			static function (string $action, array $context = []) use (&$audit): void {
				$audit[] = ['action' => $action, 'context' => $context];
			}
		);
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParam')->willReturnCallback(
			static fn (string $key, $default = null) => match (array_key_exists($key, $params)) {
				true => $params[$key], default => $default
			}
		);

		$controller = new LayoutController(
			appName: 'thematiq',
			request: $request,
			layoutOptions: new LayoutOptionsService(config: $config, designSystemService: $designSystems),
			activeTokenSet: $active,
			auditService: $auditService
		);
		$declared = array_map(
			static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
			(new \ReflectionMethod($controller, 'save'))->getParameters()
		);

		return $controller->save(...array_intersect_key($params, array_flip($declared)));
	}//end save()
}//end class
