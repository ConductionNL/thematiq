<?php

/**
 * Unit tests for SettingsController's group theming endpoints.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/per-group-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\SettingsController;
use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\AppThemingService;
use OCA\Thematiq\Service\ComplianceReportService;
use OCA\Thematiq\Service\EmailThemingSettings;
use OCA\Thematiq\Service\Exception\GroupThemingValidationException;
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
 * Covers openspec/specs/per-group-theming/spec.md "Group Theming Admin
 * Endpoints" — payload shapes and 422 validation-error propagation.
 */
class SettingsControllerGroupThemingTest extends TestCase {

	/**
	 * Build a controller with a stubbed GroupThemingService and inert mocks
	 * for every other constructor dependency (unused by these tests).
	 *
	 * @param GroupThemingService $groupThemingService The stubbed service.
	 * @param TokenSetService $tokenSetService The token set service (defaults to an inert mock).
	 *
	 * @return SettingsController The controller under test.
	 */
	private function makeController(
		GroupThemingService $groupThemingService,
		?TokenSetService $tokenSetService = null,
	): SettingsController {
		return new SettingsController(
			appName: 'nldesign',
			request: $this->createMock(originalClassName: IRequest::class),
			settings: new SettingsConfigServices(
				config: $this->createMock(originalClassName: IConfig::class),
				auditService: $this->createMock(originalClassName: ThemingAuditService::class),
				appThemingService: $this->createMock(originalClassName: AppThemingService::class),
				groupThemingService: $groupThemingService
			),
			tokenSets: new SettingsTokenSetServices(
				tokenSetService: ($tokenSetService ?? $this->createMock(originalClassName: TokenSetService::class)),
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
	 * getGroupTheming() returns the mapping alongside the group and
	 * token-set option lists.
	 *
	 * @return void
	 */
	public function testGetGroupThemingReturnsMappingAndOptionLists(): void {
		$groupThemingService = $this->createMock(originalClassName: GroupThemingService::class);
		$groupThemingService->method('getMapping')->willReturn(
			[['group' => 'gemeente-a', 'tokenSet' => 'amsterdam']]
		);
		$groupThemingService->method('getAvailableGroups')->willReturn(
			[['id' => 'gemeente-a', 'displayName' => 'Gemeente A']]
		);

		$tokenSetService = $this->createMock(originalClassName: TokenSetService::class);
		// The group picker offers the SELECTABLE list, not the full catalogue.
		// TokenSetSelectionPolicy derives that from this catalogue, so the real
		// filter runs here; getAppPath() points at the repository so it reads the
		// real token-sets.json, where `amsterdam` is named and passes the audit.
		$tokenSetService->method('getAvailableTokenSets')->willReturn(
			[['id' => 'amsterdam', 'name' => 'Amsterdam']]
		);
		$tokenSetService->method('getAppPath')->willReturn(\dirname(__DIR__, 3));

		$controller = $this->makeController(groupThemingService: $groupThemingService, tokenSetService: $tokenSetService);
		$response = $controller->getGroupTheming();
		$data = $response->getData();

		$this->assertSame(expected: [['group' => 'gemeente-a', 'tokenSet' => 'amsterdam']], actual: $data['mapping']);
		$this->assertSame(expected: [['id' => 'gemeente-a', 'displayName' => 'Gemeente A']], actual: $data['groups']);
		$this->assertSame(expected: [['id' => 'amsterdam', 'name' => 'Amsterdam']], actual: $data['tokenSets']);
	}//end testGetGroupThemingReturnsMappingAndOptionLists()

	/**
	 * setGroupTheming() persists the ordered mapping and returns it on success.
	 *
	 * @return void
	 */
	public function testSetGroupThemingReturnsPersistedMappingOnSuccess(): void {
		$groupThemingService = $this->createMock(originalClassName: GroupThemingService::class);
		$groupThemingService->method('getMapping')->willReturn([]);
		$groupThemingService->expects($this->once())
			->method('setMapping')
			->with([['group' => 'gemeente-a', 'tokenSet' => 'amsterdam']])
			->willReturn([['group' => 'gemeente-a', 'tokenSet' => 'amsterdam']]);

		$controller = $this->makeController(groupThemingService: $groupThemingService);
		$response = $controller->setGroupTheming([['group' => 'gemeente-a', 'tokenSet' => 'amsterdam']]);

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$data = $response->getData();
		$this->assertSame(expected: 'ok', actual: $data['status']);
		$this->assertSame(expected: [['group' => 'gemeente-a', 'tokenSet' => 'amsterdam']], actual: $data['mapping']);
	}//end testSetGroupThemingReturnsPersistedMappingOnSuccess()

	/**
	 * A validation failure surfaces as HTTP 422 naming the offending entry
	 * and reason.
	 *
	 * @return void
	 */
	public function testSetGroupThemingReturns422OnValidationFailure(): void {
		$badEntry = ['group' => 'nonexistent', 'tokenSet' => 'amsterdam'];

		$groupThemingService = $this->createMock(originalClassName: GroupThemingService::class);
		$groupThemingService->method('getMapping')->willReturn([]);
		$groupThemingService->method('setMapping')
			->willThrowException(new GroupThemingValidationException(entry: $badEntry, reason: 'Group "nonexistent" does not exist.'));

		$controller = $this->makeController(groupThemingService: $groupThemingService);
		$response = $controller->setGroupTheming([$badEntry]);

		$this->assertSame(expected: 422, actual: $response->getStatus());
		$data = $response->getData();
		$this->assertSame(expected: 'invalid_mapping', actual: $data['error']);
		$this->assertSame(expected: $badEntry, actual: $data['entry']);
		$this->assertSame(expected: 'Group "nonexistent" does not exist.', actual: $data['reason']);
	}//end testSetGroupThemingReturns422OnValidationFailure()
}//end class
