<?php

/**
 * Unit tests for ConfigBundleController.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/config-portability/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\ConfigBundleController;
use OCA\Thematiq\Service\ConfigBundleService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Covers tasks.md#task-4.3: export headers/body, and the import 400/413/415
 * paths plus the success/failure audit-logging contract.
 *
 * The service is mocked outright — {@see \OCA\Thematiq\Tests\Unit\Service\ConfigBundleServiceTest}
 * already covers its real export/import/validation logic end-to-end; this
 * suite only verifies the controller's HTTP mapping and its ONE call site
 * into {@see ThemingAuditService}.
 */
class ConfigBundleControllerTest extends TestCase {

	/**
	 * The mocked configuration bundle service.
	 *
	 * @var ConfigBundleService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $service;

	/**
	 * The mocked audit service.
	 *
	 * @var ThemingAuditService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $auditService;

	/**
	 * The mocked request.
	 *
	 * @var IRequest&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $request;

	/**
	 * The controller under test.
	 *
	 * @var ConfigBundleController
	 */
	private ConfigBundleController $controller;

	/**
	 * A temp file path used by the upload tests (cleaned up in tearDown).
	 *
	 * @var string|null
	 */
	private ?string $uploadPath = null;

	/**
	 * Set up the controller with mocked collaborators.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->service = $this->createMock(originalClassName: ConfigBundleService::class);
		$this->auditService = $this->createMock(originalClassName: ThemingAuditService::class);
		$this->request = $this->createMock(originalClassName: IRequest::class);

		$this->controller = new ConfigBundleController(
			appName: 'nldesign',
			request: $this->request,
			service: $this->service,
			auditService: $this->auditService
		);
	}//end setUp()

	/**
	 * Remove any temp upload file after each test.
	 */
	protected function tearDown(): void {
		if ($this->uploadPath !== null && file_exists($this->uploadPath) === true) {
			unlink($this->uploadPath);
		}

		parent::tearDown();
	}//end tearDown()

	/**
	 * Write a JSON string to a temp file for getUploadedFile()['tmp_name'].
	 *
	 * @param string $content The file content.
	 *
	 * @return string The temp file path.
	 */
	private function writeTempFile(string $content): string {
		$this->uploadPath = tempnam(sys_get_temp_dir(), 'nldesign-bundle-');
		file_put_contents($this->uploadPath, $content);

		return $this->uploadPath;
	}//end writeTempFile()

	/**
	 * Read the headers `DownloadResponse`'s constructor explicitly set,
	 * bypassing `Response::getHeaders()` — that method merges in
	 * request/session-derived defaults (CSP, cache-control, …) that need a
	 * live `\OC::$server` container unavailable in a standalone PHPUnit run.
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
	 * export() returns the exact service bundle as the download body, with
	 * the attachment filename and JSON content type.
	 */
	public function testExportReturnsAttachmentWithBundleBody(): void {
		$this->service->method('export')->willReturn(['format' => 'nldesign-config-bundle', 'bundleVersion' => 1]);

		$response = $this->controller->export();

		$this->assertInstanceOf(expected: DataDownloadResponse::class, actual: $response);
		$this->assertStringContainsString(needle: '"bundleVersion": 1', haystack: $response->render());

		$headers = $this->rawHeaders(response: $response);
		$this->assertStringContainsString(needle: 'attachment;', haystack: $headers['Content-Disposition']);
		$this->assertStringContainsString(needle: 'nldesign-config.json', haystack: $headers['Content-Disposition']);
		$this->assertSame(expected: 'application/json', actual: $headers['Content-Type']);
	}//end testExportReturnsAttachmentWithBundleBody()

	/**
	 * No uploaded file is a 400.
	 */
	public function testImportWithNoFileReturns400(): void {
		$this->request->method('getUploadedFile')->willReturn(null);

		$response = $this->controller->import();

		$this->assertSame(expected: 400, actual: $response->getStatus());
	}//end testImportWithNoFileReturns400()

	/**
	 * A file exceeding the 256 KB cap is a 413, without ever reading its content.
	 */
	public function testImportOversizeFileReturns413(): void {
		$this->request->method('getUploadedFile')->willReturn(
			[
				'tmp_name' => $this->writeTempFile(content: '{}'),
				'name' => 'bundle.json',
				'size' => ((256 * 1024) + 1),
			]
		);

		$this->service->expects($this->never())->method('import');

		$response = $this->controller->import();

		$this->assertSame(expected: 413, actual: $response->getStatus());
	}//end testImportOversizeFileReturns413()

	/**
	 * A non-.json file extension is a 415.
	 */
	public function testImportWrongExtensionReturns415(): void {
		$this->request->method('getUploadedFile')->willReturn(
			[
				'tmp_name' => $this->writeTempFile(content: '{}'),
				'name' => 'bundle.css',
				'size' => 2,
			]
		);

		$response = $this->controller->import();

		$this->assertSame(expected: 415, actual: $response->getStatus());
	}//end testImportWrongExtensionReturns415()

	/**
	 * Invalid JSON content is a 400, never reaching the service.
	 */
	public function testImportInvalidJsonReturns400(): void {
		$this->request->method('getUploadedFile')->willReturn(
			[
				'tmp_name' => $this->writeTempFile(content: 'not json'),
				'name' => 'bundle.json',
				'size' => 8,
			]
		);

		$this->service->expects($this->never())->method('import');

		$response = $this->controller->import();

		$this->assertSame(expected: 400, actual: $response->getStatus());
	}//end testImportInvalidJsonReturns400()

	/**
	 * A hard validation failure from the service is a 400 with the full
	 * per-section error listing, and logs NOTHING to the audit trail.
	 */
	public function testImportValidationFailureReturns400WithErrorsAndLogsNothing(): void {
		$this->request->method('getUploadedFile')->willReturn(
			[
				'tmp_name' => $this->writeTempFile(content: '{"format":"nldesign-config-bundle"}'),
				'name' => 'bundle.json',
				'size' => 30,
			]
		);

		$this->service->method('import')->willReturn(
			[
				'valid' => false,
				'applied' => false,
				'errors' => [['section' => 'envelope', 'message' => 'Unsupported bundleVersion.']],
			]
		);

		$this->auditService->expects($this->never())->method('log');

		$response = $this->controller->import();

		$this->assertSame(expected: 400, actual: $response->getStatus());
		$this->assertFalse(condition: $response->getData()['applied']);
		$this->assertSame(expected: 'envelope', actual: $response->getData()['errors'][0]['section']);
	}//end testImportValidationFailureReturns400WithErrorsAndLogsNothing()

	/**
	 * A successful import returns 200 with the per-section results and logs
	 * exactly one audit entry carrying those sections.
	 */
	public function testImportSuccessReturns200AndLogsOneAuditEntry(): void {
		$this->request->method('getUploadedFile')->willReturn(
			[
				'tmp_name' => $this->writeTempFile(content: '{"format":"nldesign-config-bundle"}'),
				'name' => 'bundle.json',
				'size' => 30,
			]
		);

		$sections = ['config' => ['tokenSet' => 'utrecht']];
		$this->service->method('import')->willReturn(
			[
				'valid' => true,
				'dryRun' => false,
				'applied' => true,
				'sections' => $sections,
			]
		);

		$this->auditService->expects($this->once())
			->method('log')
			->with('config_bundle_imported', ['sections' => $sections]);

		$response = $this->controller->import();

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertTrue(condition: $response->getData()['applied']);
		$this->assertSame(expected: $sections, actual: $response->getData()['sections']);
	}//end testImportSuccessReturns200AndLogsOneAuditEntry()

	/**
	 * An unexpected service exception is a 500, and logs nothing.
	 */
	public function testImportServiceExceptionReturns500(): void {
		$this->request->method('getUploadedFile')->willReturn(
			[
				'tmp_name' => $this->writeTempFile(content: '{"format":"nldesign-config-bundle"}'),
				'name' => 'bundle.json',
				'size' => 30,
			]
		);

		$this->service->method('import')->willThrowException(new RuntimeException('disk full'));

		$this->auditService->expects($this->never())->method('log');

		$response = $this->controller->import();

		$this->assertSame(expected: 500, actual: $response->getStatus());
	}//end testImportServiceExceptionReturns500()
}//end class
