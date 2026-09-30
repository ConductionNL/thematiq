<?php

/**
 * Unit tests for SettingsController::complianceReport().
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/compliance-evidence/spec.md
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
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * Covers tasks.md#task-4.7: unknown format => 400; attachment disposition headers.
 */
class SettingsControllerComplianceReportTest extends TestCase {

	/**
	 * The mocked compliance report service.
	 *
	 * @var ComplianceReportService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $complianceReportService;

	/**
	 * The controller under test.
	 *
	 * @var SettingsController
	 */
	private SettingsController $controller;

	/**
	 * Set up the controller with mocked collaborators.
	 */
	protected function setUp(): void {
		parent::setUp();

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($key === 'token_set' ? 'utrecht' : $default)
		);
		$config->method('getSystemValue')->willReturnCallback(
			fn (string $key, $default = '') => ($key === 'instanceid' ? 'inst123' : $default)
		);

		$this->complianceReportService = $this->createMock(ComplianceReportService::class);
		$this->complianceReportService->method('renderJson')->willReturn("{\"scope\":\"x\"}\n");
		$this->complianceReportService->method('renderMarkdown')->willReturn("# Report\n");

		$this->controller = new SettingsController(
			'nldesign',
			$this->createMock(IRequest::class),
			$config,
			$this->createMock(TokenSetService::class),
			$this->createMock(ThemingService::class),
			$this->createMock(TokenSetPreviewService::class),
			$this->createMock(AppThemingService::class),
			$this->complianceReportService,
			$this->createMock(ThemingAuditService::class),
			$this->createMock(EmailThemingService::class),
			$this->createMock(UpstreamFreshnessService::class),
			$this->createMock(GroupThemingService::class),
			$this->createMock(ActiveTokenSetService::class)
		);
	}//end setUp()

	/**
	 * Read a Response's raw, explicitly-set headers via reflection.
	 *
	 * `Response::getHeaders()` merges in request/CSP-derived headers via
	 * `Server::get(IRequest::class)`, which requires a live Nextcloud server
	 * container unavailable in a standalone PHPUnit run. This test only cares
	 * about the headers `DownloadResponse`'s constructor explicitly sets
	 * (Content-Disposition, Content-Type), so read the protected `$headers`
	 * property directly instead.
	 *
	 * @param \OCP\AppFramework\Http\Response $response The response to inspect.
	 *
	 * @return array<string, mixed> The explicitly-set headers.
	 */
	private function rawHeaders(\OCP\AppFramework\Http\Response $response): array {
		// Reflect via the declaring base class explicitly — PHP 8.4 does not
		// resolve an inherited private property by name from the leaf
		// subclass's own ReflectionClass/ReflectionProperty constructor.
		$property = (new \ReflectionClass(\OCP\AppFramework\Http\Response::class))->getProperty('headers');
		$property->setAccessible(true);

		return $property->getValue($response);
	}//end rawHeaders()

	/**
	 * The default (json) format returns a JSON download with attachment disposition.
	 */
	public function testDefaultFormatReturnsJsonDownload(): void {
		$response = $this->controller->complianceReport();

		$this->assertInstanceOf(DataDownloadResponse::class, $response);
		$headers = $this->rawHeaders(response: $response);
		$this->assertArrayHasKey('Content-Disposition', $headers);
		$this->assertStringStartsWith('attachment;', $headers['Content-Disposition']);
		$this->assertStringContainsString('nldesign-compliance-inst123-utrecht-', $headers['Content-Disposition']);
		// The real OCP DataDownloadResponse quotes the filename; the standalone
		// OCP stub does not — assert the extension without the quoting flavour.
		$this->assertStringContainsString('.json', $headers['Content-Disposition']);
		$this->assertSame('application/json', $headers['Content-Type']);
	}//end testDefaultFormatReturnsJsonDownload()

	/**
	 * format=markdown returns a Markdown download with attachment disposition.
	 */
	public function testMarkdownFormatReturnsMarkdownDownload(): void {
		$response = $this->controller->complianceReport(format: 'markdown');

		$this->assertInstanceOf(DataDownloadResponse::class, $response);
		$headers = $this->rawHeaders(response: $response);
		$this->assertStringContainsString('.md', $headers['Content-Disposition']);
		$this->assertSame('text/markdown', $headers['Content-Type']);
		$this->assertSame("# Report\n", $response->render());
	}//end testMarkdownFormatReturnsMarkdownDownload()

	/**
	 * An unknown format returns a 400 JSON error, not a report download.
	 */
	public function testUnknownFormatReturns400(): void {
		$response = $this->controller->complianceReport(format: 'yaml');

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(400, $response->getStatus());
	}//end testUnknownFormatReturns400()
}//end class
