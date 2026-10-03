<?php

/**
 * Unit tests for SettingsController's theming-audit call-site wiring.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/archive/2026-07-23-theming-audit-log/tasks.md#task-5.3
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
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Local (test-only) IAppDataFactory whose get() always throws — used to
 * exercise the REAL ThemingAuditService's failure-swallowing path from a
 * controller call site, rather than mocking the service itself.
 */
class SettingsControllerAuditThrowingAppDataFactory implements IAppDataFactory {
	public function get(string $appId): IAppData {
		throw new \RuntimeException('appdata unavailable');
	}
}

/**
 * Covers tasks.md#task-5.3 for SettingsController::setTokenSet(): a
 * successful call logs exactly one token_set_changed entry with the correct
 * old/new values, a rejected (invalid) token set logs nothing, and an audit
 * service that throws never changes the endpoint's own response — log()'s
 * failure-swallowing contract is exercised through the real service call
 * site, not re-tested here (see ThemingAuditServiceTest).
 */
class SettingsControllerAuditTest extends TestCase {

	/**
	 * In-memory appconfig store: key => value.
	 *
	 * @var array<string, string>
	 */
	private array $appConfig = ['token_set' => 'rijkshuisstijl'];

	/**
	 * The mocked audit service.
	 *
	 * @var ThemingAuditService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private ThemingAuditService $auditService;

	/**
	 * The mocked token set service.
	 *
	 * @var TokenSetService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private TokenSetService $tokenSetService;

	/**
	 * The controller under test.
	 *
	 * @var SettingsController
	 */
	private SettingsController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->tokenSetService = $this->createMock(TokenSetService::class);
		$this->auditService = $this->createMock(ThemingAuditService::class);

		$this->controller = new SettingsController(
			'nldesign',
			$this->createMock(IRequest::class),
			$this->makeConfigMock(),
			$this->tokenSetService,
			$this->createMock(ThemingService::class),
			$this->createMock(TokenSetPreviewService::class),
			$this->createMock(AppThemingService::class),
			$this->createMock(ComplianceReportService::class),
			$this->auditService,
			$this->createMock(EmailThemingService::class),
			$this->createMock(UpstreamFreshnessService::class),
			$this->createMock(GroupThemingService::class),
			new ActiveTokenSetService($this->makeConfigMock(), $this->tokenSetService, $this->auditService)
		);
	}//end setUp()

	/**
	 * A successful token set change logs exactly one token_set_changed
	 * entry with the correct old and new values.
	 */
	public function testSuccessfulTokenSetChangeLogsOneEntry(): void {
		$this->tokenSetService->method('isValidTokenSet')->willReturn(true);

		$this->auditService->expects($this->once())
			->method('log')
			->with(
				'token_set_changed',
				['old' => 'rijkshuisstijl', 'new' => 'amsterdam']
			);

		$response = $this->controller->setTokenSet(tokenSet: 'amsterdam');

		$this->assertSame(200, $response->getStatus());
	}//end testSuccessfulTokenSetChangeLogsOneEntry()

	/**
	 * A rejected (invalid) token set never reaches the audit service.
	 */
	public function testInvalidTokenSetLogsNothing(): void {
		$this->tokenSetService->method('isValidTokenSet')->willReturn(false);

		$this->auditService->expects($this->never())->method('log');

		$response = $this->controller->setTokenSet(tokenSet: 'not-a-real-set');

		$this->assertSame(400, $response->getStatus());
		$this->assertSame('rijkshuisstijl', $this->appConfig['token_set']);
	}//end testInvalidTokenSetLogsNothing()

	/**
	 * The audit service throwing internally does not change setTokenSet()'s
	 * response. Uses the REAL ThemingAuditService wired to a throwing
	 * IAppDataFactory (rather than mocking the service itself) so this
	 * exercises the actual failure-swallowing contract end-to-end from the
	 * controller call site, not just log()'s own unit test.
	 */
	public function testAuditServiceThrowingDoesNotChangeResponse(): void {
		$this->tokenSetService->method('isValidTokenSet')->willReturn(true);

		$auditConfig = $this->createMock(IConfig::class);
		$auditConfig->method('getAppValue')->willReturn('0');

		$realAuditService = new ThemingAuditService(
			appDataFactory: new SettingsControllerAuditThrowingAppDataFactory(),
			config: $auditConfig,
			userSession: $this->createMock(IUserSession::class),
			timeFactory: $this->createMock(ITimeFactory::class),
			logger: $this->createMock(LoggerInterface::class),
			versionService: $this->createMock(\OCA\Thematiq\Service\ThemeVersionService::class)
		);

		$controller = new SettingsController(
			'nldesign',
			$this->createMock(IRequest::class),
			$this->makeConfigMock(),
			$this->tokenSetService,
			$this->createMock(ThemingService::class),
			$this->createMock(TokenSetPreviewService::class),
			$this->createMock(AppThemingService::class),
			$this->createMock(ComplianceReportService::class),
			$realAuditService,
			$this->createMock(EmailThemingService::class),
			$this->createMock(UpstreamFreshnessService::class),
			$this->createMock(GroupThemingService::class),
			new ActiveTokenSetService($this->makeConfigMock(), $this->tokenSetService, $realAuditService)
		);

		$response = $controller->setTokenSet(tokenSet: 'amsterdam');

		$this->assertSame(200, $response->getStatus());
		$this->assertSame('amsterdam', $response->getData()['tokenSet']);
		$this->assertSame('amsterdam', $this->appConfig['token_set']);
	}//end testAuditServiceThrowingDoesNotChangeResponse()

	/**
	 * Build an in-memory IConfig mock backed by $this->appConfig.
	 *
	 * @return IConfig&\PHPUnit\Framework\MockObject\MockObject
	 */
	private function makeConfigMock(): IConfig {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->appConfig[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->appConfig[$key] = $value;
			}
		);

		return $config;
	}//end makeConfigMock()
}//end class
