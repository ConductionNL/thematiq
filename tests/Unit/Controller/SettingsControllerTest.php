<?php

/**
 * Unit tests for SettingsController's email theming endpoints.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/email-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\SettingsController;
use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\AppThemingService;
use OCA\Thematiq\Service\ComplianceReportService;
use OCA\Thematiq\Service\EmailThemingService;
use OCA\Thematiq\Service\EmailThemingSettings;
use OCA\Thematiq\Service\Exception\ConfigReadOnlyException;
use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\SettingsConfigServices;
use OCA\Thematiq\Service\SettingsReportServices;
use OCA\Thematiq\Service\SettingsTokenSetServices;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Service\TokenSetPreviewService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\UpstreamFreshnessService;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SettingsController's email theming endpoints.
 */
class SettingsControllerTest extends TestCase {

	/**
	 * Build a controller with a stubbed EmailThemingService and inert mocks
	 * for the other constructor dependencies (unused by these tests).
	 *
	 * @param EmailThemingService $emailThemingService The stubbed service.
	 *
	 * @return SettingsController The controller under test.
	 */
	private function makeController(EmailThemingService $emailThemingService): SettingsController {
		return new SettingsController(
			appName: 'nldesign',
			request: $this->createMock(originalClassName: IRequest::class),
			settings: new SettingsConfigServices(
				config: $this->createMock(originalClassName: IConfig::class),
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
				emailTheming: new EmailThemingSettings(emailThemingService: $emailThemingService),
				freshnessService: $this->createMock(originalClassName: UpstreamFreshnessService::class)
			)
		);
	}//end makeController()

	/**
	 * setEmailTheming returns HTTP 409 with the occ command strings when the
	 * service reports config.php is read-only.
	 *
	 * @return void
	 */
	public function testSetEmailThemingReturns409WithOccCommandsOnReadOnly(): void {
		$emailThemingService = $this->createMock(originalClassName: EmailThemingService::class);
		$emailThemingService->expects($this->once())->method('setFooterConfig')
			->with('Org', '', '')
			->willReturn(['orgName' => 'Org', 'accessibilityUrl' => '', 'privacyUrl' => '']);
		$emailThemingService->method('enable')
			->willThrowException(new ConfigReadOnlyException(occEnableCommand: 'occ enable-cmd', occDisableCommand: 'occ disable-cmd'));
		$emailThemingService->method('getFooterConfig')
			->willReturn(['orgName' => 'Org', 'accessibilityUrl' => '', 'privacyUrl' => '']);

		$controller = $this->makeController(emailThemingService: $emailThemingService);
		$response = $controller->setEmailTheming(true, 'Org', '', '');

		$this->assertSame(expected: 409, actual: $response->getStatus());
		$data = $response->getData();
		$this->assertSame(expected: 'config_read_only', actual: $data['error']);
		$this->assertSame(expected: 'occ enable-cmd', actual: $data['occEnable']);
		$this->assertSame(expected: 'occ disable-cmd', actual: $data['occDisable']);
	}//end testSetEmailThemingReturns409WithOccCommandsOnReadOnly()

	/**
	 * The footer config save still succeeds (and is reported back) even
	 * when the toggle write fails because config.php is read-only —
	 * footer is applied first, independently of the toggle outcome.
	 *
	 * @return void
	 */
	public function testFooterSaveSucceedsWhenToggleFailsReadOnly(): void {
		$emailThemingService = $this->createMock(originalClassName: EmailThemingService::class);
		$emailThemingService->expects($this->once())->method('setFooterConfig')
			->with('Gemeente Voorbeeld', 'https://voorbeeld.nl/a11y', 'https://voorbeeld.nl/privacy')
			->willReturn(
				[
					'orgName' => 'Gemeente Voorbeeld',
					'accessibilityUrl' => 'https://voorbeeld.nl/a11y',
					'privacyUrl' => 'https://voorbeeld.nl/privacy',
				]
			);
		$emailThemingService->method('enable')
			->willThrowException(new ConfigReadOnlyException(occEnableCommand: 'occ enable-cmd', occDisableCommand: 'occ disable-cmd'));
		$emailThemingService->method('getFooterConfig')->willReturn(
			[
				'orgName' => 'Gemeente Voorbeeld',
				'accessibilityUrl' => 'https://voorbeeld.nl/a11y',
				'privacyUrl' => 'https://voorbeeld.nl/privacy',
			]
		);

		$controller = $this->makeController(emailThemingService: $emailThemingService);
		$response = $controller->setEmailTheming(
			true,
			'Gemeente Voorbeeld',
			'https://voorbeeld.nl/a11y',
			'https://voorbeeld.nl/privacy'
		);

		$this->assertSame(expected: 409, actual: $response->getStatus());
		$data = $response->getData();
		$this->assertSame(expected: 'Gemeente Voorbeeld', actual: $data['footer']['orgName']);
		$this->assertSame(expected: 'https://voorbeeld.nl/a11y', actual: $data['footer']['accessibilityUrl']);
		$this->assertSame(expected: 'https://voorbeeld.nl/privacy', actual: $data['footer']['privacyUrl']);
	}//end testFooterSaveSucceedsWhenToggleFailsReadOnly()

	/**
	 * A successful enable (writable config.php, nothing foreign configured)
	 * returns status ok with the updated state.
	 *
	 * @return void
	 */
	public function testSetEmailThemingSucceeds(): void {
		$emailThemingService = $this->createMock(originalClassName: EmailThemingService::class);
		$emailThemingService->expects($this->once())->method('setFooterConfig')
			->willReturn(['orgName' => '', 'accessibilityUrl' => '', 'privacyUrl' => '']);
		$emailThemingService->expects($this->once())->method('enable');
		$emailThemingService->method('getState')
			->willReturn(['state' => 'enabled', 'configReadOnly' => false, 'foreignClass' => null]);
		$emailThemingService->method('getFooterConfig')
			->willReturn(['orgName' => '', 'accessibilityUrl' => '', 'privacyUrl' => '']);

		$controller = $this->makeController(emailThemingService: $emailThemingService);
		$response = $controller->setEmailTheming(true, '', '', '');

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$data = $response->getData();
		$this->assertSame(expected: 'ok', actual: $data['status']);
		$this->assertSame(expected: 'enabled', actual: $data['state']['state']);
	}//end testSetEmailThemingSucceeds()
}//end class
