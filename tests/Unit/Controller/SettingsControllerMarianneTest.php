<?php

/**
 * Unit tests for SettingsController's Marianne (French State typeface)
 * acknowledgement gate endpoints.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/marianne-font/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\SettingsController;
use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\AppThemingService;
use OCA\Thematiq\Service\ComplianceReportService;
use OCA\Thematiq\Service\EmailThemingSettings;
use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\SettingsConfigServices;
use OCA\Thematiq\Service\SettingsReportServices;
use OCA\Thematiq\Service\SettingsTokenSetServices;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Service\TokenSetPreviewService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\UpstreamFreshnessService;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Covers openspec/specs/marianne-font/spec.md "Marianne Activation Requires
 * an Admin Acknowledgement Gate": default off, POST persists, admin-only
 * posture on both routed methods (non-admin rejection is enforced by that
 * attribute, verified here the same way SettingsControllerDarkVariantsTest
 * does).
 */
class SettingsControllerMarianneTest extends TestCase {

	/**
	 * In-memory app config store backing the IConfig mock.
	 *
	 * @var array<string, string>
	 */
	private array $configStore = [];

	/**
	 * Build a SettingsController wired to the in-memory config store, with
	 * stubbed sibling dependencies not exercised by the Marianne endpoints.
	 *
	 * @return SettingsController The controller under test.
	 */
	private function makeController(): SettingsController {
		$config = $this->createMock(originalClassName: IConfig::class);
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

		return new SettingsController(
			appName: 'nldesign',
			request: $this->createMock(originalClassName: IRequest::class),
			settings: new SettingsConfigServices(
				config: $config,
				auditService: $this->createMock(originalClassName: ThemingAuditService::class),
				appThemingService: $this->createMock(originalClassName: AppThemingService::class),
				groupThemingService: $this->createMock(originalClassName: GroupThemingService::class)
			),
			tokenSets: new SettingsTokenSetServices(
				tokenSetService: $this->createMock(originalClassName: TokenSetService::class),
				previewService: $this->createMock(originalClassName: TokenSetPreviewService::class),
				activeTokenSet: $this->createMock(originalClassName: ActiveTokenSetService::class),
				themingService: $this->createMock(originalClassName: ThemingService::class)
			),
			reports: new SettingsReportServices(
				complianceService: $this->createMock(originalClassName: ComplianceReportService::class),
				emailTheming: $this->createMock(originalClassName: EmailThemingSettings::class),
				freshnessService: $this->createMock(originalClassName: UpstreamFreshnessService::class)
			)
		);
	}//end makeController()

	/**
	 * Both Marianne routes carry #[AuthorizedAdminSetting] — non-admin
	 * requests are rejected by that posture before the method body runs.
	 *
	 * @return void
	 */
	public function testBothEndpointsCarryAuthorizedAdminSetting(): void {
		foreach (['getMarianneEnabled', 'setMarianneEnabled'] as $method) {
			$reflection = new ReflectionMethod(SettingsController::class, $method);
			$attributes = $reflection->getAttributes(AuthorizedAdminSetting::class);
			$this->assertNotEmpty(actual: $attributes, message: "$method must carry #[AuthorizedAdminSetting]");
		}
	}//end testBothEndpointsCarryAuthorizedAdminSetting()

	/**
	 * A fresh install (no `marianne_enabled` config value) defaults to
	 * disabled — Marianne must be inert until an admin explicitly opts in.
	 *
	 * @return void
	 */
	public function testDefaultIsDisabled(): void {
		$controller = $this->makeController();

		$data = $controller->getMarianneEnabled()->getData();

		$this->assertFalse(condition: $data['enabled']);
	}//end testDefaultIsDisabled()

	/**
	 * A POST persists the enabled state, reflected by the subsequent GET,
	 * and stores the raw appconfig value as '1'.
	 *
	 * @return void
	 */
	public function testTogglePersists(): void {
		$controller = $this->makeController();

		$setResponse = $controller->setMarianneEnabled(true);
		$this->assertSame(expected: ['status' => 'ok', 'enabled' => true], actual: $setResponse->getData());

		$after = $controller->getMarianneEnabled()->getData();
		$this->assertTrue(condition: $after['enabled']);

		$this->assertSame(expected: '1', actual: $this->configStore['marianne_enabled']);
	}//end testTogglePersists()

	/**
	 * Disabling after an enable reverts to Inter — persisted as '0'.
	 *
	 * @return void
	 */
	public function testDisablingAfterEnablePersists(): void {
		$controller = $this->makeController();

		$controller->setMarianneEnabled(true);
		$controller->setMarianneEnabled(false);

		$this->assertSame(expected: '0', actual: $this->configStore['marianne_enabled']);
		$this->assertFalse(condition: $controller->getMarianneEnabled()->getData()['enabled']);
	}//end testDisablingAfterEnablePersists()
}//end class
