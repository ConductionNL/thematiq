<?php

/**
 * Unit tests for HealthController: the engine's verdict reaches the response.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/prometheus-metrics/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\HealthController;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * The OpenRegister AppHost engine runs the manifest's checks and resolves the
 * status and HTTP code (its own suite covers that policy:
 * openregister tests/Unit/AppHost/HealthCheckExecutorTest.php). What thematiq
 * owns is carrying that verdict into the `{status, app, version, checks}`
 * envelope unchanged, which a live instance cannot be made to show on demand:
 * neither a failing database nor a failing filesystem check can be induced
 * from a browser. These tests feed the controller an engine verdict and
 * assert the response it renders.
 */
class HealthControllerEngineResultTest extends TestCase {

	/**
	 * Build a controller whose container hands back a fake engine.
	 *
	 * @param string $status The engine's overall status.
	 * @param array<string,string> $checks The engine's per-check results.
	 * @param int $httpCode The HTTP code the engine's policy resolved.
	 *
	 * @return HealthController
	 */
	private function controllerWithEngineResult(string $status, array $checks, int $httpCode): HealthController {
		$manifest = new class {
			public bool $cors = false;
		};

		$loader = new class($manifest) {
			public function __construct(
				private object $manifest,
			) {
			}

			public function load(string $appId): object {
				return $this->manifest;
			}

			public function appVersion(string $appId): string {
				return '9.9.9';
			}
		};

		$result = new class($status, $checks, $httpCode) {
			public function __construct(
				public string $status,
				public array $checks,
				public int $httpStatusCode,
			) {
			}
		};

		$executor = new class($result) {
			public function __construct(
				private object $result,
			) {
			}

			public function execute(object $manifest): object {
				return $this->result;
			}
		};

		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnMap([
			['OCA\\OpenRegister\\AppHost\\Observability\\ManifestLoader', $loader],
			['OCA\\OpenRegister\\AppHost\\Observability\\HealthCheckExecutor', $executor],
		]);

		return new HealthController(
			request: $this->createMock(originalClassName: IRequest::class),
			config: $this->createMock(originalClassName: IConfig::class),
			container: $container
		);
	}//end controllerWithEngineResult()

	/**
	 * A failed critical check (engine status `error`, 503 under adr006)
	 * reaches the response as HTTP 503 with the failing check intact.
	 *
	 * Proves openspec/specs/prometheus-metrics/spec.md, scenario "Critical
	 * check failure yields 503 under adr006 policy".
	 *
	 * @return void
	 */
	public function testCriticalEngineFailureIsServedAs503(): void {
		$response = $this->controllerWithEngineResult(
			status: 'error',
			checks: ['database' => 'failed: database unreachable', 'filesystem' => 'ok', 'thematiq' => 'ok'],
			httpCode: 503
		)->index();

		$this->assertSame(expected: 503, actual: $response->getStatus());
		$data = $response->getData();
		$this->assertSame(expected: 'error', actual: $data['status']);
		$this->assertSame(expected: 'thematiq', actual: $data['app']);
		$this->assertSame(expected: '9.9.9', actual: $data['version']);
		$this->assertStringStartsWith(prefix: 'failed', string: $data['checks']['database']);
	}//end testCriticalEngineFailureIsServedAs503()

	/**
	 * A failed degraded check (engine status `degraded`, still 200 under
	 * adr006) reaches the response as HTTP 200 with status `degraded`.
	 *
	 * Proves openspec/specs/prometheus-metrics/spec.md, scenario "Degraded
	 * filesystem check does not error the overall status".
	 *
	 * @return void
	 */
	public function testDegradedFilesystemIsServedAs200Degraded(): void {
		$response = $this->controllerWithEngineResult(
			status: 'degraded',
			checks: ['database' => 'ok', 'filesystem' => 'failed: temp dir not writable', 'thematiq' => 'ok'],
			httpCode: 200
		)->index();

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$data = $response->getData();
		$this->assertSame(expected: 'degraded', actual: $data['status']);
		$this->assertStringStartsWith(prefix: 'failed', string: $data['checks']['filesystem']);
		$this->assertSame(expected: 'ok', actual: $data['checks']['database']);
	}//end testDegradedFilesystemIsServedAs200Degraded()

	/**
	 * Without OpenRegister the engine cannot be resolved, and the probe still
	 * answers: HTTP 200, `degraded`, `checks.openregister: unavailable`.
	 *
	 * Proves openspec/specs/prometheus-metrics/spec.md, scenario "Nextcloud
	 * boots when OpenRegister is absent" (the response half).
	 *
	 * @return void
	 */
	public function testEngineAbsentDegradesTo200(): void {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willThrowException(new \RuntimeException('class not found'));

		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturn('1.0.0');

		$response = (new HealthController(
			request: $this->createMock(originalClassName: IRequest::class),
			config: $config,
			container: $container
		))->index();

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(
			expected: ['status' => 'degraded', 'app' => 'thematiq', 'version' => '1.0.0', 'checks' => ['openregister' => 'unavailable']],
			actual: $response->getData()
		);
	}//end testEngineAbsentDegradesTo200()

	/**
	 * The controller names OpenRegister classes only as strings, never in a
	 * position the autoloader resolves (`extends`, `implements`, `use`, a
	 * type), so Nextcloud's router can reflect it on an instance without
	 * OpenRegister.
	 *
	 * Proves openspec/specs/prometheus-metrics/spec.md, scenario "Nextcloud
	 * boots when OpenRegister is absent" (the boot half).
	 *
	 * @return void
	 */
	public function testControllerNamesNoOpenRegisterClassInCode(): void {
		$source = (string)file_get_contents(__DIR__ . '/../../../lib/Controller/HealthController.php');

		$this->assertDoesNotMatchRegularExpression(pattern: '/^use\s+OCA\\\\OpenRegister\\\\/m', string: $source);
		$this->assertMatchesRegularExpression(pattern: '/^class HealthController extends Controller \{$/m', string: $source);
		$this->assertSame(expected: 'OCP\AppFramework\Controller', actual: get_parent_class(HealthController::class));
	}//end testControllerNamesNoOpenRegisterClassInCode()
}//end class
