<?php

/**
 * Unit tests for SettingsController's upstream-freshness endpoints.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/upstream-token-freshness/tasks.md#task-5.3
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\SettingsController;
use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\AppThemingService;
use OCA\Thematiq\Service\ComplianceReportService;
use OCA\Thematiq\Service\EmailThemingService;
use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Service\TokenSetPreviewService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\UpstreamFreshnessService;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers openspec/specs/upstream-freshness/spec.md "Opt-In Egress With
 * Disclosure" (non-admin access denied via the AuthorizedAdminSetting
 * posture) and "Admin Notice Surface With Per-Version Dismissal": the three
 * routed methods carry the admin-only attribute, the toggle POST persists,
 * and a dismiss POST round-trips into the filtered getStatus() output.
 */
class SettingsControllerUpstreamFreshnessTest extends TestCase {

	/**
	 * In-memory app config store backing the IConfig mock.
	 *
	 * @var array<string, string>
	 */
	private array $configStore = [];

	/**
	 * Build a SettingsController wired to a real UpstreamFreshnessService
	 * (backed by the in-memory config store) and stubbed sibling
	 * dependencies not exercised by the upstream-freshness endpoints.
	 */
	private function makeController(): SettingsController {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			function (string $app, string $key, $default = '') {
				return ($this->configStore[$key] ?? $default);
			}
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value) {
				$this->configStore[$key] = $value;
			}
		);

		$tokenSetService = $this->createMock(TokenSetService::class);
		$tokenSetService->method('getAvailableTokenSets')->willReturn([]);

		$upstreamFreshnessService = new UpstreamFreshnessService(
			$config,
			$this->createMock(IClientService::class),
			$tokenSetService,
			$this->createMock(LoggerInterface::class)
		);

		return new SettingsController(
			'nldesign',
			$this->createMock(IRequest::class),
			$config,
			$tokenSetService,
			$this->createMock(ThemingService::class),
			$this->createMock(TokenSetPreviewService::class),
			$this->createMock(AppThemingService::class),
			$this->createMock(ComplianceReportService::class),
			$this->createMock(ThemingAuditService::class),
			$this->createMock(EmailThemingService::class),
			$upstreamFreshnessService,
			$this->createMock(GroupThemingService::class),
			$this->createMock(ActiveTokenSetService::class)
		);
	}//end makeController()

	/**
	 * All three upstream-freshness routes carry the AuthorizedAdminSetting
	 * attribute — non-admin requests are rejected by that posture before the
	 * method body ever runs.
	 */
	public function testAllThreeEndpointsCarryAuthorizedAdminSetting(): void {
		foreach (['getUpstreamFreshness', 'setUpstreamFreshness', 'dismissUpstreamNotice'] as $method) {
			$reflection = new \ReflectionMethod(SettingsController::class, $method);
			$attributes = $reflection->getAttributes(AuthorizedAdminSetting::class);
			$this->assertNotEmpty($attributes, "$method must carry #[AuthorizedAdminSetting]");
		}
	}//end testAllThreeEndpointsCarryAuthorizedAdminSetting()

	/**
	 * The toggle POST persists the enabled state, reflected by the GET.
	 */
	public function testToggleEnabledPersists(): void {
		$controller = $this->makeController();

		$before = $controller->getUpstreamFreshness()->getData();
		$this->assertFalse($before['enabled']);

		$setResponse = $controller->setUpstreamFreshness(true);
		$this->assertSame(['status' => 'ok', 'enabled' => true], $setResponse->getData());

		$after = $controller->getUpstreamFreshness()->getData();
		$this->assertTrue($after['enabled']);
	}//end testToggleEnabledPersists()

	/**
	 * A dismiss POST round-trips into the filtered getStatus() output: the
	 * dismissed notice disappears from the subsequent GET.
	 */
	public function testDismissRoundTripsIntoFilteredStatus(): void {
		$this->configStore['upstream_updates'] = json_encode(
			[
				'utrecht' => [
					'installedRef' => 'old-ref',
					'installedVersion' => '1.0.0',
					'headSha' => 'sha1',
					'upstreamVersion' => '1.0.0',
					'detectedAt' => 1,
				],
			]
		);

		$controller = $this->makeController();

		$before = $controller->getUpstreamFreshness()->getData();
		$this->assertCount(1, $before['notices']);

		$dismissResponse = $controller->dismissUpstreamNotice('utrecht', '1.0.0');
		$this->assertSame(['status' => 'ok'], $dismissResponse->getData());

		$after = $controller->getUpstreamFreshness()->getData();
		$this->assertSame([], $after['notices']);
	}//end testDismissRoundTripsIntoFilteredStatus()
}//end class
