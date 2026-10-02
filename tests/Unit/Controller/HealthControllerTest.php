<?php

/**
 * Unit tests for HealthController without OpenRegister.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/adopt-apphost-2026-06-16/specs/prometheus-metrics/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\HealthController;
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * The health probe answers when OpenRegister's engine is absent
 * (thematiq#207): degraded at HTTP 200, never a 5xx.
 */
class HealthControllerTest extends TestCase {

	/**
	 * With nothing in the container under the engine's names, the endpoint
	 * reports itself degraded and still names the installed version.
	 *
	 * @return void
	 */
	public function testIndexDegradesWhenTheEngineIsAbsent(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnMap([['thematiq', 'installed_version', '', '1.2.10']]);

		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willThrowException(new RuntimeException('Class not found'));

		$controller = new HealthController(
			request: $this->createMock(IRequest::class),
			config: $config,
			container: $container
		);

		$response = $controller->index();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(
			[
				'status' => 'degraded',
				'app' => 'thematiq',
				'version' => '1.2.10',
				'checks' => ['openregister' => 'unavailable'],
			],
			$response->getData()
		);
	}//end testIndexDegradesWhenTheEngineIsAbsent()
}//end class
