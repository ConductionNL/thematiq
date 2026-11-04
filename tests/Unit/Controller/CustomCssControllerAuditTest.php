<?php

/**
 * Audit-trail tests for the freeform custom CSS controller.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Test
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/custom-css-freeform/spec.md
 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\CustomCssController;
use OCA\Thematiq\Service\CustomCssService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * Freeform CSS is the one theming surface where an administrator can author
 * arbitrary rules, so BOTH outcomes have to leave a trail: a successful write
 * and a rejected one. A rejection that is not logged would let someone probe
 * the validator silently.
 */
class CustomCssControllerAuditTest extends TestCase {

	/**
	 * The request mock.
	 *
	 * @var IRequest&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $request;

	/**
	 * The freeform CSS service mock.
	 *
	 * @var CustomCssService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $customCssService;

	/**
	 * The audit log mock.
	 *
	 * @var ThemingAuditService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $auditService;

	/**
	 * Set up mocks.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(originalClassName: IRequest::class);
		$this->customCssService = $this->createMock(originalClassName: CustomCssService::class);
		$this->auditService = $this->createMock(originalClassName: ThemingAuditService::class);

	}//end setUp()

	/**
	 * Build the controller under test.
	 *
	 * @return CustomCssController The controller.
	 */
	private function buildController(): CustomCssController {
		return new CustomCssController(
			appName: 'nldesign',
			request: $this->request,
			customCssService: $this->customCssService,
			auditService: $this->auditService
		);

	}//end buildController()

	/**
	 * A successful write is audit-logged.
	 *
	 * @return void
	 */
	public function testSuccessfulWriteIsAudited(): void {
		$this->request->method('getParams')->willReturn(['css' => '.a { color: red; }', 'enabled' => true]);
		$this->customCssService->method('read')->willReturn('');
		$this->customCssService->method('write')->willReturn([]);
		$this->customCssService->method('isEnabled')->willReturn(true);

		$this->auditService->expects($this->once())
			->method('log')
			->with($this->equalTo(value: 'custom_css_written'), $this->anything());

		$response = $this->buildController()->setCustomCss();

		$this->assertSame(expected: 200, actual: $response->getStatus());

	}//end testSuccessfulWriteIsAudited()

	/**
	 * A REJECTED submission is audit-logged too, and returns 422.
	 *
	 * @return void
	 */
	public function testRejectedSubmissionIsAudited(): void {
		$this->request->method('getParams')->willReturn(['css' => '@import url(x);']);
		$this->customCssService->method('read')->willReturn('');
		$this->customCssService->method('write')->willReturn(['@import and @charset are not allowed.']);

		$this->auditService->expects($this->once())
			->method('log')
			->with($this->equalTo(value: 'custom_css_rejected'), $this->anything());

		$response = $this->buildController()->setCustomCss();

		$this->assertSame(expected: 422, actual: $response->getStatus());
		$this->assertArrayHasKey(key: 'errors', array: $response->getData());

	}//end testRejectedSubmissionIsAudited()

	/**
	 * A rejected submission must not flip the enable flag.
	 *
	 * @return void
	 */
	public function testRejectionDoesNotEnableTheLayer(): void {
		$this->request->method('getParams')->willReturn(['css' => '@import url(x);', 'enabled' => true]);
		$this->customCssService->method('read')->willReturn('');
		$this->customCssService->method('write')->willReturn(['@import and @charset are not allowed.']);

		$this->customCssService->expects($this->never())->method('setEnabled');

		$this->buildController()->setCustomCss();

	}//end testRejectionDoesNotEnableTheLayer()

	/**
	 * A non-string payload is refused before anything is written.
	 *
	 * @return void
	 */
	public function testNonStringPayloadIsRefused(): void {
		$this->request->method('getParams')->willReturn(['css' => ['not', 'a', 'string']]);

		$this->customCssService->expects($this->never())->method('write');

		$response = $this->buildController()->setCustomCss();

		$this->assertSame(expected: 400, actual: $response->getStatus());

	}//end testNonStringPayloadIsRefused()

	/**
	 * Both endpoints are reachable only through the admin setting, which a
	 * delegated admin of the theming section also holds.
	 *
	 * @return void
	 */
	public function testEveryEndpointIsAdminOnly(): void {
		foreach (['getCustomCss', 'setCustomCss'] as $method) {
			$attributes = (new \ReflectionMethod(CustomCssController::class, $method))
				->getAttributes(\OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting::class);
			$this->assertNotEmpty(actual: $attributes, message: "CustomCssController::{$method}() must carry #[AuthorizedAdminSetting]");
			$this->assertSame(expected: \OCA\Thematiq\Settings\Admin::class, actual: $attributes[0]->getArguments()[0]);
		}

	}//end testEveryEndpointIsAdminOnly()

	/**
	 * The editor loads the stored CSS and the enabled state.
	 *
	 * @return void
	 */
	public function testReadReturnsTheStoredCssAndState(): void {
		$this->customCssService->method('read')->willReturn('.a { color: red; }');
		$this->customCssService->method('isEnabled')->willReturn(true);

		$data = $this->buildController()->getCustomCss()->getData();

		$this->assertSame(expected: '.a { color: red; }', actual: $data['css']);
		$this->assertTrue(condition: $data['enabled']);

	}//end testReadReturnsTheStoredCssAndState()

	/**
	 * A file that cannot be written is a 500 with the reason, and no audit entry.
	 *
	 * @return void
	 */
	public function testUnwritableFileIsAServerError(): void {
		$this->request->method('getParams')->willReturn(['css' => '.a { color: red; }']);
		$this->customCssService->method('read')->willReturn('');
		$this->customCssService->method('write')
			->willThrowException(new \RuntimeException('Could not write css/custom-css.css.tmp.'));

		$this->auditService->expects($this->never())->method('log');

		$response = $this->buildController()->setCustomCss();

		$this->assertSame(expected: 500, actual: $response->getStatus());
		$this->assertStringContainsString(needle: 'Could not write', haystack: $response->getData()['error']);

	}//end testUnwritableFileIsAServerError()
}//end class
