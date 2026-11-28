<?php

/**
 * Tests for the setup contract's controller.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-setup-contract-is-for-administrators-only-req-tqfs-005
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use InvalidArgumentException;
use OCA\Thematiq\Controller\SetupController;
use OCA\Thematiq\Service\Exception\RestrictedSetNotConfirmedException;
use OCA\Thematiq\Service\SetupActions;
use OCA\Thematiq\Service\SetupContract;
use OCA\Thematiq\Service\SetupStatusService;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The routes answer the contract's codes; the controller carries no attribute that lets a non-admin in.
 */
class SetupControllerTest extends TestCase {

	/**
	 * The actions double.
	 *
	 * @var SetupActions&MockObject
	 */
	private SetupActions&MockObject $actions;

	/**
	 * Build the controller with a request body.
	 *
	 * @param array<string, mixed> $params The request parameters.
	 *
	 * @return SetupController The controller.
	 */
	private function make(array $params = []): SetupController {
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParams')->willReturn($params);
		$status = $this->createMock(originalClassName: SetupStatusService::class);
		$status->method('status')->willReturn(['completed' => false]);
		$this->actions = $this->createMock(originalClassName: SetupActions::class);
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('admin');
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);

		return new SetupController(
			appName: 'thematiq',
			request: $request,
			setup: new SetupContract(status: $status, actions: $this->actions),
			userSession: $session
		);
	}//end make()

	/**
	 * An unknown action is not found and runs nothing.
	 *
	 * @return void
	 */
	public function testAnUnknownActionIsNotFound(): void {
		$controller = $this->make();
		$this->actions->expects($this->never())->method('apply');
		$this->actions->expects($this->never())->method('skip');

		$this->assertSame(expected: 404, actual: $controller->action(actionId: 'seed')->getStatus());
	}//end testAnUnknownActionIsNotFound()

	/**
	 * A wrong value refuses the request with 400 naming the key.
	 *
	 * @return void
	 */
	public function testAWrongValueRefusesTheRequest(): void {
		$controller = $this->make(params: ['actionId' => 'apply', 'tokenSet' => 'zuiddrecht', 'menu' => ['columns' => 9]]);
		$this->actions->expects($this->once())->method('apply')
			->with(['tokenSet' => 'zuiddrecht', 'menu' => ['columns' => 9]], 'admin')
			->willThrowException(new InvalidArgumentException('menu.columns'));

		$response = $controller->action(actionId: 'apply');

		$this->assertSame(expected: 400, actual: $response->getStatus());
		$this->assertSame(expected: 'menu.columns', actual: $response->getData()['key']);
	}//end testAWrongValueRefusesTheRequest()

	/**
	 * An unconfirmed restricted set answers 403.
	 *
	 * @return void
	 */
	public function testAnUnconfirmedRestrictedSetIsForbidden(): void {
		$controller = $this->make(params: ['tokenSet' => 'zuiddrecht']);
		$this->actions->method('apply')->willThrowException(new RestrictedSetNotConfirmedException(tokenSetId: 'zuiddrecht'));

		$response = $controller->action(actionId: 'apply');

		$this->assertSame(expected: 403, actual: $response->getStatus());
		$this->assertSame(expected: 'restricted_set_not_confirmed', actual: $response->getData()['error']);
	}//end testAnUnconfirmedRestrictedSetIsForbidden()

	/**
	 * The menu switch reaches the actions as sent; a failed part answers 500 with the parts.
	 *
	 * @return void
	 */
	public function testTheMenuSwitchStoresTheStyle(): void {
		$controller = $this->make(params: ['tokenSet' => 'nextcloud', 'menu' => ['enabled' => false]]);
		$this->actions->expects($this->once())->method('apply')
			->with(['tokenSet' => 'nextcloud', 'menu' => ['enabled' => false]], 'admin')
			->willReturn(['applied' => ['house-style', 'login-page'], 'failed' => ['apps-menu'], 'completed' => false]);

		$response = $controller->action(actionId: 'apply');

		$this->assertSame(expected: 500, actual: $response->getStatus());
		$this->assertSame(expected: ['apps-menu'], actual: $response->getData()['failed']);
	}//end testTheMenuSwitchStoresTheStyle()

	/**
	 * Skip runs skip and answers the new status.
	 *
	 * @return void
	 */
	public function testSkipRunsSkip(): void {
		$controller = $this->make();
		$this->actions->expects($this->once())->method('skip')->with('admin');

		$response = $controller->action(actionId: 'skip');

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertArrayHasKey(key: 'status', array: $response->getData());
	}//end testSkipRunsSkip()

	/**
	 * Neither route lets a non-admin or a page without CSRF in.
	 *
	 * @return void
	 */
	public function testTheRoutesAreForAdministratorsOnly(): void {
		foreach (['status', 'action'] as $method) {
			$attributes = array_map(
				static fn ($attribute) => $attribute->getName(),
				(new ReflectionMethod(SetupController::class, $method))->getAttributes()
			);
			$this->assertSame(expected: [], actual: $attributes, message: $method);
		}
	}//end testTheRoutesAreForAdministratorsOnly()
}//end class
