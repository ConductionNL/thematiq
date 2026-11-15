<?php

/**
 * Unit tests for AuditController.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/archive/2026-07-23-theming-audit-log/tasks.md#task-5.2
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\AuditController;
use OCA\Thematiq\Service\ThemeVersionRestoreService;
use OCA\Thematiq\Service\ThemeVersionService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the admin-only audit endpoints.
 *
 * Covers tasks.md#task-5.2: list() caps the limit, export() carries the
 * expected download headers, and both methods are admin-annotated.
 */
class AuditControllerTest extends TestCase {

	/**
	 * The mocked audit service.
	 *
	 * @var ThemingAuditService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private ThemingAuditService $auditService;

	/**
	 * The controller under test.
	 *
	 * @var AuditController
	 */
	private AuditController $controller;

	/**
	 * @var ThemeVersionService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $versions;

	/**
	 * @var ThemeVersionRestoreService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $restorer;

	/**
	 * Set up each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->auditService = $this->createMock(originalClassName: ThemingAuditService::class);
		$this->versions = $this->createMock(originalClassName: ThemeVersionService::class);
		$this->restorer = $this->createMock(originalClassName: ThemeVersionRestoreService::class);
		$this->controller = new AuditController(
			appName: 'nldesign',
			request: $this->createMock(originalClassName: IRequest::class),
			auditService: $this->auditService,
			versionService: $this->versions,
			restoreService: $this->restorer
		);
	}//end setUp()

	/**
	 * The default limit (20) is passed straight through to getRecent().
	 *
	 * @return void
	 */
	public function testListUsesDefaultLimit(): void {
		$this->auditService->expects($this->once())
			->method('getRecent')
			->with(20)
			->willReturn([]);

		$response = $this->controller->list();

		$this->assertSame(expected: ['entries' => []], actual: $response->getData());
	}//end testListUsesDefaultLimit()

	/**
	 * A limit above 200 is capped at 200 before reaching the service.
	 *
	 * @return void
	 */
	public function testListCapsExcessiveLimit(): void {
		$this->auditService->expects($this->once())
			->method('getRecent')
			->with(200)
			->willReturn([]);

		$this->controller->list(limit: 500);
	}//end testListCapsExcessiveLimit()

	/**
	 * A negative limit is clamped to zero.
	 *
	 * @return void
	 */
	public function testListClampsNegativeLimitToZero(): void {
		$this->auditService->expects($this->once())
			->method('getRecent')
			->with(0)
			->willReturn([]);

		$this->controller->list(limit: -5);
	}//end testListClampsNegativeLimitToZero()

	/**
	 * export() streams the full log with the JSONL download headers.
	 *
	 * Reads the response's own headers via reflection rather than
	 * Response::getHeaders() — that method merges in request/session-derived
	 * headers (Server::get(IRequest::class)/IUserSession) which need a full
	 * Nextcloud server bootstrap unavailable in this standalone unit test.
	 *
	 * @return void
	 */
	public function testExportHeaders(): void {
		$this->auditService->method('exportAll')->willReturn('{"a":1}' . "\n");

		$response = $this->controller->export();

		$property = new \ReflectionProperty(\OCP\AppFramework\Http\Response::class, 'headers');
		$property->setAccessible(true);
		$headers = $property->getValue($response);

		$this->assertSame(expected: 'application/x-ndjson', actual: $headers['Content-Type']);
		// The real OCP DataDownloadResponse does not quote the filename; the
		// standalone stub does. Assert the parts, not the quoting flavour.
		$this->assertStringContainsString(needle: 'attachment;', haystack: $headers['Content-Disposition']);
		$this->assertStringContainsString(needle: 'nldesign-audit.jsonl', haystack: $headers['Content-Disposition']);
		$this->assertSame(expected: '{"a":1}' . "\n", actual: $response->render());
	}//end testExportHeaders()

	/**
	 * Both endpoints carry #[AuthorizedAdminSetting(Admin::class)] — the
	 * route-auth/semantic-auth gates' expectation for this controller.
	 *
	 * @return void
	 */
	public function testBothMethodsAreAdminAnnotated(): void {
		foreach (['list', 'export', 'versions', 'previewVersion', 'restoreVersion'] as $method) {
			$reflection = new \ReflectionMethod(AuditController::class, $method);
			$attributes = $reflection->getAttributes(AuthorizedAdminSetting::class);

			$this->assertNotEmpty(actual: $attributes, message: "AuditController::{$method}() must carry #[AuthorizedAdminSetting]");
			$this->assertSame(expected: Admin::class, actual: $attributes[0]->getArguments()['settings'] ?? $attributes[0]->getArguments()[0]);
		}
	}//end testBothMethodsAreAdminAnnotated()

	/**
	 * The version list is the kept versions, newest first.
	 *
	 * @spec openspec/specs/theme-versions/spec.md
	 *
	 * @return void
	 */
	public function testVersionsListsTheKeptVersions(): void {
		$this->versions->method('list')->willReturn([['id' => '20260929164000-0001']]);

		$this->assertSame(expected: ['versions' => [['id' => '20260929164000-0001']]], actual: $this->controller->versions()->getData());
	}//end testVersionsListsTheKeptVersions()

	/**
	 * Preview and restore of an unknown id answer 404.
	 *
	 * @spec openspec/specs/theme-versions/spec.md
	 *
	 * @return void
	 */
	public function testAnUnknownVersionIs404(): void {
		$this->restorer->method('preview')->willReturn(null);
		$this->restorer->method('restore')->willReturn(null);

		$this->assertSame(expected: 404, actual: $this->controller->previewVersion(id: 'nope')->getStatus());
		$this->assertSame(expected: 404, actual: $this->controller->restoreVersion(id: 'nope')->getStatus());
	}//end testAnUnknownVersionIs404()

	/**
	 * A refused restore answers 422 with the errors; an applied one 200.
	 *
	 * @spec openspec/specs/theme-versions/spec.md
	 *
	 * @return void
	 */
	public function testRestoreAnswersByOutcome(): void {
		$this->restorer->method('restore')->willReturnOnConsecutiveCalls(
			['applied' => false, 'valid' => false, 'errors' => [['id' => 'custom-x']]],
			['applied' => true, 'valid' => true]
		);

		$this->assertSame(expected: 422, actual: $this->controller->restoreVersion(id: '20260929164000-0001')->getStatus());
		$this->assertSame(expected: 200, actual: $this->controller->restoreVersion(id: '20260929164000-0001')->getStatus());
	}//end testRestoreAnswersByOutcome()

	/**
	 * The three version routes are registered and reach these methods.
	 *
	 * @return void
	 */
	public function testTheVersionRoutesAreRegistered(): void {
		$routes = (string)file_get_contents(dirname(__DIR__, 3) . '/appinfo/routes.php');
		$this->assertStringContainsString(needle: "['name' => 'audit#versions', 'url' => '/settings/versions', 'verb' => 'GET']", haystack: $routes);
		$this->assertStringContainsString(
			needle: "['name' => 'audit#previewVersion', 'url' => '/settings/versions/{id}/preview', 'verb' => 'POST']",
			haystack: $routes
		);
		$this->assertStringContainsString(
			needle: "['name' => 'audit#restoreVersion', 'url' => '/settings/versions/{id}/restore', 'verb' => 'POST']",
			haystack: $routes
		);
	}//end testTheVersionRoutesAreRegistered()
}//end class
