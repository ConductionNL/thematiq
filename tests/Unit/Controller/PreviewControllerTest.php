<?php

/**
 * Unit tests for PreviewController.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/archive/2026-07-23-theme-preview-workflow/tasks.md#task-5.2
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\PreviewController;
use OCA\Thematiq\Service\ThemePreviewService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Covers openspec/specs/theme-preview/spec.md#requirement-preview-lifecycle-endpoints:
 * start()/discard()/publish() resolve the acting uid from IUserSession only,
 * start() rejects invalid ids with 400, publish() returns 400 with no active
 * preview, and every method carries #[AuthorizedAdminSetting(Admin::class)].
 */
class PreviewControllerTest extends TestCase {

	/**
	 * The mocked theme preview service.
	 *
	 * @var ThemePreviewService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private ThemePreviewService $previewService;

	/**
	 * The mocked user session.
	 *
	 * @var IUserSession&\PHPUnit\Framework\MockObject\MockObject
	 */
	private IUserSession $userSession;

	/**
	 * The controller under test.
	 *
	 * @var PreviewController
	 */
	private PreviewController $controller;

	/**
	 * Set up each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->previewService = $this->createMock(originalClassName: ThemePreviewService::class);
		$this->userSession = $this->createMock(originalClassName: IUserSession::class);

		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('admin');
		$this->userSession->method('getUser')->willReturn($user);

		$this->controller = new PreviewController(
			appName: 'nldesign',
			request: $this->createMock(originalClassName: IRequest::class),
			previewService: $this->previewService,
			userSession: $this->userSession
		);
	}//end setUp()

	/**
	 * start() resolves the uid from IUserSession, never from the request,
	 * and returns the started preview state.
	 *
	 * @return void
	 */
	public function testStartReturnsOkWithStateForValidId(): void {
		$this->previewService->expects($this->once())
			->method('startPreview')
			->with('admin', 'amsterdam')
			->willReturn(['tokenSet' => 'amsterdam', 'expiresAt' => 1234]);

		$response = $this->controller->start(tokenSet: 'amsterdam');

		$this->assertSame(
			expected: ['status' => 'ok', 'tokenSet' => 'amsterdam', 'expiresAt' => 1234],
			actual: $response->getData()
		);
	}//end testStartReturnsOkWithStateForValidId()

	/**
	 * start() returns 400 for an invalid token set id.
	 *
	 * @return void
	 */
	public function testStartReturns400ForInvalidId(): void {
		$this->previewService->method('startPreview')
			->willThrowException(new \InvalidArgumentException('Invalid token set: does-not-exist'));

		$response = $this->controller->start(tokenSet: 'does-not-exist');

		$this->assertSame(expected: 400, actual: $response->getStatus());
		$this->assertSame(expected: 'Invalid token set', actual: $response->getData()['error']);
	}//end testStartReturns400ForInvalidId()

	/**
	 * discard() clears the caller's own preview (uid from the session) and
	 * returns an ok envelope.
	 *
	 * @return void
	 */
	public function testDiscardClearsOwnPreviewAndReturnsOk(): void {
		$this->previewService->expects($this->once())
			->method('clearPreview')
			->with('admin');

		$response = $this->controller->discard();

		$this->assertSame(expected: ['status' => 'ok'], actual: $response->getData());
	}//end testDiscardClearsOwnPreviewAndReturnsOk()

	/**
	 * publish() promotes the caller's own preview and returns the published set.
	 *
	 * @return void
	 */
	public function testPublishReturnsOkWithPublishedTokenSet(): void {
		$this->previewService->expects($this->once())
			->method('publishPreview')
			->with('admin')
			->willReturn('amsterdam');

		$response = $this->controller->publish();

		$this->assertSame(expected: ['status' => 'ok', 'tokenSet' => 'amsterdam'], actual: $response->getData());
	}//end testPublishReturnsOkWithPublishedTokenSet()

	/**
	 * publish() returns 400 and changes nothing when there is no active preview.
	 *
	 * @return void
	 */
	public function testPublishReturns400WhenNoActivePreview(): void {
		$this->previewService->method('publishPreview')
			->willThrowException(new \RuntimeException('No active preview to publish for uid: admin'));

		$response = $this->controller->publish();

		$this->assertSame(expected: 400, actual: $response->getStatus());
		$this->assertSame(expected: 'No active preview to publish', actual: $response->getData()['error']);
	}//end testPublishReturns400WhenNoActivePreview()

	/**
	 * All three lifecycle endpoints carry #[AuthorizedAdminSetting(Admin::class)].
	 *
	 * @return void
	 */
	public function testAllMethodsAreAdminAnnotated(): void {
		foreach (['start', 'discard', 'publish'] as $method) {
			$reflection = new \ReflectionMethod(PreviewController::class, $method);
			$attributes = $reflection->getAttributes(AuthorizedAdminSetting::class);

			$this->assertNotEmpty(actual: $attributes, message: "PreviewController::{$method}() must carry #[AuthorizedAdminSetting]");
			$this->assertSame(expected: Admin::class, actual: $attributes[0]->getArguments()['settings'] ?? $attributes[0]->getArguments()[0]);
		}
	}//end testAllMethodsAreAdminAnnotated()

	/**
	 * When no user is logged in (defensive path — the route itself is
	 * already admin-only), every endpoint returns 400 rather than resolving
	 * a null uid into the service.
	 *
	 * @return void
	 */
	public function testNoUserSessionReturns400OnEveryEndpoint(): void {
		$anonymousSession = $this->createMock(originalClassName: IUserSession::class);
		$anonymousSession->method('getUser')->willReturn(null);

		$controller = new PreviewController(
			appName: 'nldesign',
			request: $this->createMock(originalClassName: IRequest::class),
			previewService: $this->previewService,
			userSession: $anonymousSession
		);

		$this->previewService->expects($this->never())->method('startPreview');
		$this->previewService->expects($this->never())->method('clearPreview');
		$this->previewService->expects($this->never())->method('publishPreview');

		$this->assertSame(expected: 400, actual: $controller->start(tokenSet: 'amsterdam')->getStatus());
		$this->assertSame(expected: 400, actual: $controller->discard()->getStatus());
		$this->assertSame(expected: 400, actual: $controller->publish()->getStatus());
	}//end testNoUserSessionReturns400OnEveryEndpoint()
}//end class
