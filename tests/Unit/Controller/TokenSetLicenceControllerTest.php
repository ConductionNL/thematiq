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
		$this->licences = $this->createMock(TokenSetLicenceService::class);
		$session = $this->createMock(IUserSession::class);
		if ($uid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
			$session->method('getUser')->willReturn($user);
		}

		return new TokenSetLicenceController('thematiq', $this->createMock(IRequest::class), $this->licences, $session);
	}//end controller()

	public function testEveryEndpointIsForAdministratorsOnAPlainController(): void {
		$controller = $this->controller();
		$this->assertInstanceOf(Controller::class, $controller);
		$this->assertNotInstanceOf(OCSController::class, $controller, 'an OCS controller would deliver a 403 as 200');

		foreach (['index', 'show', 'confirm', 'withdraw'] as $method) {
			$attributes = (new ReflectionMethod(TokenSetLicenceController::class, $method))->getAttributes(AuthorizedAdminSetting::class);
			$this->assertCount(1, $attributes, $method);
			$this->assertSame([Admin::class], array_values($attributes[0]->getArguments()), $method);
		}
	}//end testEveryEndpointIsForAdministratorsOnAPlainController()

	public function testTheRoutesExist(): void {
		$routes = (require __DIR__ . '/../../../appinfo/routes.php')['routes'];
		$named = array_column($routes, null, 'name');

		$this->assertSame(['GET', '/settings/tokensets/licences'], [$named['tokenSetLicence#index']['verb'], $named['tokenSetLicence#index']['url']]);
		$this->assertSame(['GET', '/settings/tokensets/{id}/licence'], [$named['tokenSetLicence#show']['verb'], $named['tokenSetLicence#show']['url']]);
		$this->assertSame('POST', $named['tokenSetLicence#confirm']['verb']);
		$this->assertSame('DELETE', $named['tokenSetLicence#withdraw']['verb']);
		$this->assertSame($named['tokenSetLicence#confirm']['url'], $named['tokenSetLicence#withdraw']['url']);
	}//end testTheRoutesExist()

	public function testTheOverviewListsTheSetsAndTheOnesAwaitingConfirmation(): void {
		$controller = $this->controller();
		$this->licences->method('restrictedSets')->willReturn([['id' => 'owned']]);
		$this->licences->method('awaitingConfirmation')->willReturn([['tokenSet' => 'owned', 'appliedIn' => []]]);

		$this->assertSame(
			['sets' => [['id' => 'owned']], 'awaiting' => [['tokenSet' => 'owned', 'appliedIn' => []]]],
			$controller->index()->getData()
		);
	}//end testTheOverviewListsTheSetsAndTheOnesAwaitingConfirmation()

	public function testASetWithoutALicenceAnswers404(): void {
		$controller = $this->controller();
		$this->licences->method('describe')->willReturnCallback(fn (string $tokenSetId) => (['owned' => ['text' => 'x']][$tokenSetId] ?? null));

		$this->assertSame(404, $controller->show('nextcloud')->getStatus());
		$this->assertSame(200, $controller->show('owned')->getStatus());
		$this->assertSame(['text' => 'x'], $controller->show('owned')->getData());
	}//end testASetWithoutALicenceAnswers404()

	public function testWithoutTheStatementNothingIsConfirmed(): void {
		$controller = $this->controller();
		$this->licences->expects($this->never())->method('confirm');

		$this->assertSame(400, $controller->confirm('owned')->getStatus());
		$this->assertSame(400, $controller->confirm('owned', false)->getStatus());
	}//end testWithoutTheStatementNothingIsConfirmed()

	public function testWithoutASessionNothingIsConfirmed(): void {
		$controller = $this->controller(null);
		$this->licences->expects($this->never())->method('confirm');

		$this->assertSame(400, $controller->confirm('owned', true)->getStatus());
	}//end testWithoutASessionNothingIsConfirmed()

	public function testTheActingAdministratorConfirms(): void {
		$controller = $this->controller('noor');
		$this->licences->expects($this->once())->method('confirm')->with('owned', 'noor')
			->willReturn(['uid' => 'noor', 'at' => '2026-10-12T20:15:00+00:00']);

		$response = $controller->confirm('owned', true);

		$this->assertSame(200, $response->getStatus());
		$this->assertSame('noor', $response->getData()['confirmation']['uid']);
	}//end testTheActingAdministratorConfirms()

	public function testASetThatIsNotRestrictedAnswers404(): void {
		$controller = $this->controller();
		$this->licences->method('confirm')->willThrowException(new InvalidArgumentException('no'));

		$this->assertSame(404, $controller->confirm('open', true)->getStatus());
	}//end testASetThatIsNotRestrictedAnswers404()

	public function testAWithdrawalWhileAppliedAnswers409WithThePlaces(): void {
		$controller = $this->controller();
		$this->licences->method('withdraw')->willThrowException(new RestrictedSetInUseException('owned', [['kind' => 'instance', 'id' => '']]));

		$response = $controller->withdraw('owned');

		$this->assertSame(409, $response->getStatus());
		$this->assertSame('restricted_set_in_use', $response->getData()['error']);
		$this->assertSame([['kind' => 'instance', 'id' => '']], $response->getData()['appliedIn']);
	}//end testAWithdrawalWhileAppliedAnswers409WithThePlaces()

	public function testAWithdrawalAnswers200(): void {
		$controller = $this->controller('noor');
		$this->licences->expects($this->once())->method('withdraw')->with('owned', 'noor');

		$this->assertSame(200, $controller->withdraw('owned')->getStatus());
	}//end testAWithdrawalAnswers200()
}//end class
