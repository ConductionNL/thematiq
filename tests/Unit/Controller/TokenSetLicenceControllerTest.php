<?php

/**
 * Unit tests for TokenSetLicenceController.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use InvalidArgumentException;
use OCA\Thematiq\Controller\TokenSetLicenceController;
use OCA\Thematiq\Service\Exception\RestrictedSetInUseException;
use OCA\Thematiq\Service\TokenSetLicenceService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The licence endpoints answer with real error statuses and are for administrators only.
 */
#[CoversClass(TokenSetLicenceController::class)]
#[CoversClass(RestrictedSetInUseException::class)]
class TokenSetLicenceControllerTest extends TestCase {

	/**
	 * The mocked licence service.
	 *
	 * @var TokenSetLicenceService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private TokenSetLicenceService $licences;

	/**
	 * Build the controller for an acting user.
	 *
	 * @param string|null $uid The acting uid, or null for no session.
	 *
	 * @return TokenSetLicenceController The controller.
	 */
	private function controller(?string $uid = 'ruben'): TokenSetLicenceController {
		$this->licences = $this->createMock(originalClassName: TokenSetLicenceService::class);
		$session = $this->createMock(originalClassName: IUserSession::class);
		if ($uid !== null) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($uid);
			$session->method('getUser')->willReturn($user);
		}

		return new TokenSetLicenceController(appName: 'thematiq', request: $this->createMock(originalClassName: IRequest::class), licences: $this->licences, userSession: $session);
	}//end controller()

	public function testEveryEndpointIsForAdministratorsOnAPlainController(): void {
		$controller = $this->controller();
		$this->assertInstanceOf(expected: Controller::class, actual: $controller);
		$this->assertNotInstanceOf(expected: OCSController::class, actual: $controller, message: 'an OCS controller would deliver a 403 as 200');

		foreach (['index', 'show', 'confirm', 'withdraw'] as $method) {
			$attributes = (new ReflectionMethod(TokenSetLicenceController::class, $method))->getAttributes(AuthorizedAdminSetting::class);
			$this->assertCount(expectedCount: 1, haystack: $attributes, message: $method);
			$this->assertSame(expected: [Admin::class], actual: array_values($attributes[0]->getArguments()), message: $method);
		}
	}//end testEveryEndpointIsForAdministratorsOnAPlainController()

	public function testTheRoutesExist(): void {
		$routes = (require __DIR__ . '/../../../appinfo/routes.php')['routes'];
		$named = array_column($routes, null, 'name');

		$this->assertSame(expected: ['GET', '/settings/tokensets/licences'], actual: [$named['tokenSetLicence#index']['verb'], $named['tokenSetLicence#index']['url']]);
		$this->assertSame(expected: ['GET', '/settings/tokensets/{id}/licence'], actual: [$named['tokenSetLicence#show']['verb'], $named['tokenSetLicence#show']['url']]);
		$this->assertSame(expected: 'POST', actual: $named['tokenSetLicence#confirm']['verb']);
		$this->assertSame(expected: 'DELETE', actual: $named['tokenSetLicence#withdraw']['verb']);
		$this->assertSame(expected: $named['tokenSetLicence#confirm']['url'], actual: $named['tokenSetLicence#withdraw']['url']);
	}//end testTheRoutesExist()

	public function testTheOverviewListsTheSetsAndTheOnesAwaitingConfirmation(): void {
		$controller = $this->controller();
		$this->licences->method('restrictedSets')->willReturn([['id' => 'owned']]);
		$this->licences->method('awaitingConfirmation')->willReturn([['tokenSet' => 'owned', 'appliedIn' => []]]);

		$this->assertSame(
			expected: ['sets' => [['id' => 'owned']], 'awaiting' => [['tokenSet' => 'owned', 'appliedIn' => []]]],
			actual: $controller->index()->getData()
		);
	}//end testTheOverviewListsTheSetsAndTheOnesAwaitingConfirmation()

	public function testASetWithoutALicenceAnswers404(): void {
		$controller = $this->controller();
		$this->licences->method('describe')->willReturnCallback(fn (string $tokenSetId) => (['owned' => ['text' => 'x']][$tokenSetId] ?? null));

		$this->assertSame(expected: 404, actual: $controller->show('nextcloud')->getStatus());
		$this->assertSame(expected: 200, actual: $controller->show('owned')->getStatus());
		$this->assertSame(expected: ['text' => 'x'], actual: $controller->show('owned')->getData());
	}//end testASetWithoutALicenceAnswers404()

	public function testWithoutTheStatementNothingIsConfirmed(): void {
		$controller = $this->controller();
		$this->licences->expects($this->never())->method('confirm');

		$this->assertSame(expected: 400, actual: $controller->confirm('owned')->getStatus());
		$this->assertSame(expected: 400, actual: $controller->confirm('owned', false)->getStatus());
	}//end testWithoutTheStatementNothingIsConfirmed()

	public function testWithoutASessionNothingIsConfirmed(): void {
		$controller = $this->controller(uid: null);
		$this->licences->expects($this->never())->method('confirm');

		$this->assertSame(expected: 400, actual: $controller->confirm('owned', true)->getStatus());
	}//end testWithoutASessionNothingIsConfirmed()

	public function testTheActingAdministratorConfirms(): void {
		$controller = $this->controller(uid: 'noor');
		$this->licences->expects($this->once())->method('confirm')->with('owned', 'noor')
			->willReturn(['uid' => 'noor', 'at' => '2026-10-12T20:15:00+00:00']);

		$response = $controller->confirm('owned', true);

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: 'noor', actual: $response->getData()['confirmation']['uid']);
	}//end testTheActingAdministratorConfirms()

	public function testASetThatIsNotRestrictedAnswers404(): void {
		$controller = $this->controller();
		$this->licences->method('confirm')->willThrowException(new InvalidArgumentException('no'));

		$this->assertSame(expected: 404, actual: $controller->confirm('open', true)->getStatus());
	}//end testASetThatIsNotRestrictedAnswers404()

	public function testAWithdrawalWhileAppliedAnswers409WithThePlaces(): void {
		$controller = $this->controller();
		$this->licences->method('withdraw')->willThrowException(new RestrictedSetInUseException(tokenSetId: 'owned', appliedIn: [['kind' => 'instance', 'id' => '']]));

		$response = $controller->withdraw('owned');

		$this->assertSame(expected: 409, actual: $response->getStatus());
		$this->assertSame(expected: 'restricted_set_in_use', actual: $response->getData()['error']);
		$this->assertSame(expected: [['kind' => 'instance', 'id' => '']], actual: $response->getData()['appliedIn']);
	}//end testAWithdrawalWhileAppliedAnswers409WithThePlaces()

	public function testAWithdrawalAnswers200(): void {
		$controller = $this->controller(uid: 'noor');
		$this->licences->expects($this->once())->method('withdraw')->with('owned', 'noor');

		$this->assertSame(expected: 200, actual: $controller->withdraw('owned')->getStatus());
	}//end testAWithdrawalAnswers200()
}//end class
