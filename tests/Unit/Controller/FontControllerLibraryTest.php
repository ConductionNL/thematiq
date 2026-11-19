<?php

/**
 * Unit tests for the font library endpoints of FontController.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/custom-fonts/spec.md#requirement-an-administrator-picks-a-font-from-an-approved-library
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\FontController;
use OCA\Thematiq\Service\FontService;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

/**
 * Listing, assigning and unassigning library fonts.
 *
 * @covers \OCA\Thematiq\Controller\FontController
 */
class FontControllerLibraryTest extends TestCase {

	/**
	 * The font service.
	 *
	 * @var FontService&MockObject
	 */
	private FontService $service;

	/**
	 * The request.
	 *
	 * @var IRequest&MockObject
	 */
	private IRequest $request;

	/**
	 * The controller under test.
	 *
	 * @var FontController
	 */
	private FontController $controller;

	/**
	 * Build the controller over a mocked service and request.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->service = $this->createMock(originalClassName: FontService::class);
		$this->request = $this->createMock(originalClassName: IRequest::class);
		$l = $this->createMock(originalClassName: IL10N::class);
		$l->method('t')->willReturnCallback(fn (string $text) => $text);

		$this->controller = new FontController(appName: 'thematiq', request: $this->request, service: $this->service, l: $l);
	}//end setUp()

	/**
	 * The library is answered as the service lists it.
	 *
	 * @return void
	 */
	public function testLibraryListsTheBundledFonts(): void {
		$library = [['name' => 'Barlow', 'licence' => 'OFL-1.1', 'roles' => ['heading']]];
		$this->service->method('library')->willReturn($library);

		$response = $this->controller->library();

		$this->assertSame(200, $response->getStatus());
		$this->assertSame(['library' => $library], $response->getData());
	}//end testLibraryListsTheBundledFonts()

	/**
	 * Assigning passes the trimmed name and role to the service; no file is read.
	 *
	 * @return void
	 */
	public function testAssignPassesTheNameAndRole(): void {
		$this->request->method('getParam')->willReturnMap([
			['name', '', ' Barlow '],
			['role', '', 'heading'],
		]);
		$this->request->expects($this->never())->method('getUploadedFile');
		$this->service->expects($this->once())->method('assignLibraryFont')->with('Barlow', 'heading');

		$response = $this->controller->assignLibrary();

		$this->assertSame(200, $response->getStatus());
		$this->assertSame(['status' => 'ok'], $response->getData());
	}//end testAssignPassesTheNameAndRole()

	/**
	 * A refusal from the service keeps its status and message.
	 *
	 * @return void
	 */
	public function testAssignRefusalsKeepTheirStatus(): void {
		$this->service->method('assignLibraryFont')->willThrowException(new RuntimeException('The font library has no font named "X".', 404));

		$response = $this->controller->assignLibrary();

		$this->assertSame(404, $response->getStatus());
		$this->assertSame(['error' => 'The font library has no font named "X".'], $response->getData());
	}//end testAssignRefusalsKeepTheirStatus()

	/**
	 * Unassigning answers ok, 404 when nothing was assigned, or the refusal's status.
	 *
	 * @return void
	 */
	public function testUnassignAnswers(): void {
		$this->service->method('unassignLibraryFont')->willReturnCallback(
			static function (string $role): bool {
				if ($role === 'footer') {
					throw new RuntimeException('Font role must be "body" or "heading".', 422);
				}

				return $role === 'body';
			}
		);

		$this->assertSame(['status' => 'ok'], $this->controller->unassignLibrary(role: 'body')->getData());

		$none = $this->controller->unassignLibrary(role: 'heading');
		$this->assertSame(404, $none->getStatus());
		$this->assertSame(['error' => 'No library font is assigned to this role.'], $none->getData());

		$this->assertSame(422, $this->controller->unassignLibrary(role: 'footer')->getStatus());
	}//end testUnassignAnswers()

	/**
	 * The library endpoints are for administrators only.
	 *
	 * @return void
	 */
	public function testTheLibraryEndpointsCarryAuthorizedAdminSetting(): void {
		foreach (['library', 'assignLibrary', 'unassignLibrary'] as $method) {
			$this->assertNotEmpty(
				(new ReflectionMethod(FontController::class, $method))->getAttributes(AuthorizedAdminSetting::class),
				$method . '() must carry #[AuthorizedAdminSetting]'
			);
		}
	}//end testTheLibraryEndpointsCarryAuthorizedAdminSetting()
}//end class
