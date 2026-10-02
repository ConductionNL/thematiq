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
		$this->service = $this->createMock(ScheduledSwitchService::class);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		$this->controller = new ScheduledSwitchController('thematiq', $this->createMock(IRequest::class), $this->service, $session);
	}//end setUp()

	/**
	 * GET lists the switches with the status block.
	 */
	public function testIndexListsSwitchesAndStatus(): void {
		$this->service->method('list')->willReturn([['id' => 'a1', 'tokenSet' => 'koningsdag-oranje']]);
		$this->service->method('getStatus')->willReturn(['activeTokenSet' => 'rijkshuisstijl', 'cronWarning' => false]);

		$data = $this->controller->index()->getData();

		$this->assertSame('a1', $data['switches'][0]['id']);
		$this->assertSame('rijkshuisstijl', $data['status']['activeTokenSet']);
	}//end testIndexListsSwitchesAndStatus()

	/**
	 * POST plans a switch as the signed-in administrator.
	 */
	public function testCreatePlansTheSwitchAsTheCurrentUser(): void {
		$this->service->expects($this->once())->method('create')->with(
			'koningsdag-oranje',
			'2027-04-26T16:00:00Z',
			'2027-04-28T06:00:00Z',
			'admin'
		)->willReturn(['id' => 'a1']);

		$response = $this->controller->create(
			tokenSet: 'koningsdag-oranje',
			startAt: '2027-04-26T16:00:00Z',
			endAt: '2027-04-28T06:00:00Z',
		);

		$this->assertSame(201, $response->getStatus());
		$this->assertSame('a1', $response->getData()['switch']['id']);
	}//end testCreatePlansTheSwitchAsTheCurrentUser()

	/**
	 * An empty end means no end.
	 */
	public function testAnEmptyEndMeansNoEnd(): void {
		$this->service->expects($this->once())->method('create')->with(
			'koningsdag-oranje',
			'2027-04-26T16:00:00Z',
			null,
			'admin'
		)->willReturn(['id' => 'a1']);

		$this->controller->create(tokenSet: 'koningsdag-oranje', startAt: '2027-04-26T16:00:00Z', endAt: '');
	}//end testAnEmptyEndMeansNoEnd()

	/**
	 * Scenario "Overlapping plans are refused": 400 with the reason.
	 */
	public function testARefusedPlanAnswers400WithTheReason(): void {
		$this->service->method('create')->willThrowException(new ScheduledSwitchException('The window overlaps a planned switch.'));

		$response = $this->controller->create(tokenSet: 'x', startAt: '2027-05-05T00:00:00Z', endAt: '2027-05-10T00:00:00Z');

		$this->assertSame(400, $response->getStatus());
		$this->assertSame('The window overlaps a planned switch.', $response->getData()['error']);
	}//end testARefusedPlanAnswers400WithTheReason()

	/**
	 * DELETE cancels; an unknown id is 404.
	 */
	public function testCancelAndUnknownCancel(): void {
		$this->service->expects($this->exactly(2))->method('cancel')->willReturnCallback(
			function (string $id): void {
				if ($id === 'nope') {
					throw new ScheduledSwitchNotFoundException('No planned switch nope.');
				}
			}
		);

		$this->assertSame(200, $this->controller->cancel(id: 'a1')->getStatus());
		$this->assertSame(404, $this->controller->cancel(id: 'nope')->getStatus());
	}//end testCancelAndUnknownCancel()

	/**
	 * Scenario "A non-admin cannot plan a switch": every endpoint carries
	 * the admin setting attribute.
	 */
	public function testEveryEndpointIsAdminOnly(): void {
		foreach (['index', 'create', 'cancel'] as $method) {
			$attributes = (new \ReflectionMethod(ScheduledSwitchController::class, $method))->getAttributes(AuthorizedAdminSetting::class);
			$this->assertNotEmpty($attributes, "ScheduledSwitchController::{$method}() must carry #[AuthorizedAdminSetting]");
			$this->assertSame(Admin::class, $attributes[0]->getArguments()[0]);
		}
	}//end testEveryEndpointIsAdminOnly()

	/**
	 * The three routes exist.
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
			[
				'scheduledSwitch#index' => 'GET /settings/scheduled-switches',
				'scheduledSwitch#create' => 'POST /settings/scheduled-switches',
				'scheduledSwitch#cancel' => 'DELETE /settings/scheduled-switches/{id}',
			],
			$found
		);
	}//end testTheRoutesAreRegistered()
}//end class
