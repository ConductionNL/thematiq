<?php

/**
 * Unit tests for ScheduledSwitchController.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/scheduled-switch/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\ScheduledSwitchController;
use OCA\Thematiq\Service\Exception\ScheduledSwitchException;
use OCA\Thematiq\Service\Exception\ScheduledSwitchNotFoundException;
use OCA\Thematiq\Service\ScheduledSwitchService;
use OCA\Thematiq\Service\SwitchPlan;
use OCA\Thematiq\Service\SwitchWindow;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * List, plan and cancel; refusals answer 400 with the reason, an unknown id
 * 404, and every endpoint is admin-only (a non-admin gets 403 from
 * Nextcloud's middleware, which reads the attribute asserted here).
 */
class ScheduledSwitchControllerTest extends TestCase {

	/**
	 * The service double.
	 *
	 * @var ScheduledSwitchService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $service;

	/**
	 * The controller under test.
	 *
	 * @var ScheduledSwitchController
	 */
	private ScheduledSwitchController $controller;

	/**
	 * Build the controller.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->service = $this->createMock(originalClassName: ScheduledSwitchService::class);
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('admin');
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);

		$this->controller = new ScheduledSwitchController(
			appName: 'thematiq',
			request: $this->createMock(originalClassName: IRequest::class),
			service: $this->service,
			userSession: $session
		);
	}//end setUp()

	/**
	 * GET lists the switches with the status block.
	 *
	 * @return void
	 */
	public function testIndexListsSwitchesAndStatus(): void {
		$this->service->method('list')->willReturn([['id' => 'a1', 'tokenSet' => 'koningsdag-oranje']]);
		$this->service->method('getStatus')->willReturn(['activeTokenSet' => 'rijkshuisstijl', 'cronWarning' => false]);

		$data = $this->controller->index()->getData();

		$this->assertSame(expected: 'a1', actual: $data['switches'][0]['id']);
		$this->assertSame(expected: 'rijkshuisstijl', actual: $data['status']['activeTokenSet']);
	}//end testIndexListsSwitchesAndStatus()

	/**
	 * POST plans a switch as the signed-in administrator.
	 *
	 * @return void
	 */
	public function testCreatePlansTheSwitchAsTheCurrentUser(): void {
		$this->service->expects($this->once())->method('create')->with(
			new SwitchPlan(tokenSet: 'koningsdag-oranje', window: new SwitchWindow(startAt: '2027-04-26T16:00:00Z', endAt: '2027-04-28T06:00:00Z')),
			'admin'
		)->willReturn(['id' => 'a1']);

		$response = $this->controller->create(
			tokenSet: 'koningsdag-oranje',
			startAt: '2027-04-26T16:00:00Z',
			endAt: '2027-04-28T06:00:00Z',
		);

		$this->assertSame(expected: 201, actual: $response->getStatus());
		$this->assertSame(expected: 'a1', actual: $response->getData()['switch']['id']);
	}//end testCreatePlansTheSwitchAsTheCurrentUser()

	/**
	 * An empty end means no end.
	 *
	 * @return void
	 */
	public function testAnEmptyEndMeansNoEnd(): void {
		$this->service->expects($this->once())->method('create')->with(
			new SwitchPlan(tokenSet: 'koningsdag-oranje', window: new SwitchWindow(startAt: '2027-04-26T16:00:00Z', endAt: null)),
			'admin'
		)->willReturn(['id' => 'a1']);

		$this->controller->create(tokenSet: 'koningsdag-oranje', startAt: '2027-04-26T16:00:00Z', endAt: '');
	}//end testAnEmptyEndMeansNoEnd()

	/**
	 * Scenario "Overlapping plans are refused": 400 with the reason.
	 *
	 * @return void
	 */
	public function testARefusedPlanAnswers400WithTheReason(): void {
		$this->service->method('create')->willThrowException(new ScheduledSwitchException(message: 'The window overlaps a planned switch.'));

		$response = $this->controller->create(tokenSet: 'x', startAt: '2027-05-05T00:00:00Z', endAt: '2027-05-10T00:00:00Z');

		$this->assertSame(expected: 400, actual: $response->getStatus());
		$this->assertSame(expected: 'The window overlaps a planned switch.', actual: $response->getData()['error']);
	}//end testARefusedPlanAnswers400WithTheReason()

	/**
	 * DELETE cancels; an unknown id is 404.
	 *
	 * @return void
	 */
	public function testCancelAndUnknownCancel(): void {
		$this->service->expects($this->exactly(count: 2))->method('cancel')->willReturnCallback(
			function (string $id): void {
				if ($id === 'nope') {
					throw new ScheduledSwitchNotFoundException(message: 'No planned switch nope.');
				}
			}
		);

		$this->assertSame(expected: 200, actual: $this->controller->cancel(id: 'a1')->getStatus());
		$this->assertSame(expected: 404, actual: $this->controller->cancel(id: 'nope')->getStatus());
	}//end testCancelAndUnknownCancel()

	/**
	 * Scenario "A non-admin cannot plan a switch": every endpoint carries
	 * the admin setting attribute.
	 *
	 * @return void
	 */
	public function testEveryEndpointIsAdminOnly(): void {
		foreach (['index', 'create', 'cancel'] as $method) {
			$attributes = (new \ReflectionMethod(ScheduledSwitchController::class, $method))->getAttributes(AuthorizedAdminSetting::class);
			$this->assertNotEmpty(actual: $attributes, message: "ScheduledSwitchController::{$method}() must carry #[AuthorizedAdminSetting]");
			$this->assertSame(expected: Admin::class, actual: $attributes[0]->getArguments()[0]);
		}
	}//end testEveryEndpointIsAdminOnly()

	/**
	 * The three routes exist.
	 *
	 * @return void
	 */
	public function testTheRoutesAreRegistered(): void {
		$routes = require __DIR__ . '/../../../appinfo/routes.php';
		$found = [];
		foreach ($routes['routes'] as $route) {
			if (str_starts_with($route['name'], 'scheduledSwitch#') === true) {
				$found[$route['name']] = $route['verb'] . ' ' . $route['url'];
			}
		}

		$this->assertSame(
			expected: [
				'scheduledSwitch#index' => 'GET /settings/scheduled-switches',
				'scheduledSwitch#create' => 'POST /settings/scheduled-switches',
				'scheduledSwitch#cancel' => 'DELETE /settings/scheduled-switches/{id}',
			],
			actual: $found
		);
	}//end testTheRoutesAreRegistered()
}//end class
