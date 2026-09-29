<?php

/**
 * Unit tests for SettingsController's dark-mode variants toggle endpoints.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/dark-mode/spec.md
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
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Covers openspec/specs/dark-mode/spec.md "Admin Dark Variants Toggle":
 * default enabled, POST persists, and both routed methods carry the
 * admin-only posture (non-admin rejection is enforced by that attribute,
 * verified here the same way SettingsControllerUpstreamFreshnessTest does).
 */
class SettingsControllerDarkVariantsTest extends TestCase {

	/**
	 * In-memory app config store backing the IConfig mock.
	 *
	 * @var array<string, string>
	 */
	private array $configStore = [];

	/**
	 * Build a SettingsController wired to the in-memory config store, with
	 * stubbed sibling dependencies not exercised by the dark-variants endpoints.
	 *
	 * @return SettingsController The controller under test.
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

		return new SettingsController(
			'nldesign',
			$this->createMock(IRequest::class),
			$config,
			$this->createMock(TokenSetService::class),
			$this->createMock(ThemingService::class),
			$this->createMock(TokenSetPreviewService::class),
			$this->createMock(AppThemingService::class),
			$this->createMock(ComplianceReportService::class),
			$this->createMock(ThemingAuditService::class),
			$this->createMock(EmailThemingService::class),
			$this->createMock(UpstreamFreshnessService::class),
			$this->createMock(GroupThemingService::class),
			$this->createMock(ActiveTokenSetService::class)
		);
	}//end makeController()

	/**
	 * Both dark-variants routes carry #[AuthorizedAdminSetting] — non-admin
	 * requests are rejected by that posture before the method body runs.
	 */
	public function testBothEndpointsCarryAuthorizedAdminSetting(): void {
		foreach (['getDarkVariants', 'setDarkVariants'] as $method) {
			$reflection = new ReflectionMethod(SettingsController::class, $method);
			$attributes = $reflection->getAttributes(AuthorizedAdminSetting::class);
			$this->assertNotEmpty($attributes, "$method must carry #[AuthorizedAdminSetting]");
		}
	}//end testBothEndpointsCarryAuthorizedAdminSetting()

	/**
	 * A fresh install (no `dark_variants` config value) defaults to enabled.
	 */
	public function testDefaultIsEnabled(): void {
		$controller = $this->makeController();

		$data = $controller->getDarkVariants()->getData();

		$this->assertTrue($data['enabled']);
	}//end testDefaultIsEnabled()

	/**
	 * A POST persists the disabled state, reflected by the subsequent GET.
	 */
	public function testTogglePersists(): void {
		$controller = $this->makeController();

		$setResponse = $controller->setDarkVariants(false);
		$this->assertSame(['status' => 'ok', 'enabled' => false], $setResponse->getData());

		$after = $controller->getDarkVariants()->getData();
		$this->assertFalse($after['enabled']);

		$this->assertSame('0', $this->configStore['dark_variants']);
	}//end testTogglePersists()

	/**
	 * Re-enabling after a disable persists '1'.
	 */
	public function testReEnablePersists(): void {
		$controller = $this->makeController();

		$controller->setDarkVariants(false);
		$controller->setDarkVariants(true);

		$this->assertSame('1', $this->configStore['dark_variants']);
		$this->assertTrue($controller->getDarkVariants()->getData()['enabled']);
	}//end testReEnablePersists()
}//end class
