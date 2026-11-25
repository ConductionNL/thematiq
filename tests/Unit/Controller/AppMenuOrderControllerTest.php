<?php

/**
 * Tests for AppMenuOrderController.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Tests
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/apps-menu-order/spec.md#requirement-the-order-is-stored-for-the-person-on-the-server
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\AppMenuOrderController;
use OCA\Thematiq\Service\AppMenuOrder;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Over an in-memory user config.
 *
 * @spec openspec/specs/apps-menu-order/spec.md#requirement-the-order-is-stored-for-the-person-on-the-server
 */
final class AppMenuOrderControllerTest extends TestCase {

	/**
	 * The stored values, by user.
	 *
	 * @var array<string, string>
	 */
	private array $stored = [];

	/**
	 * The controller with a signed-in user, or none.
	 *
	 * @param string|null $uid The signed-in user's id.
	 *
	 * @return AppMenuOrderController
	 */
	private function controller(?string $uid): AppMenuOrderController {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('setUserValue')->willReturnCallback(function (string $user, string $app, string $key, $value): void {
			$this->stored[$user] = (string)$value;
		});
		$session = $this->createMock(originalClassName: IUserSession::class);
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);

		return new AppMenuOrderController(
			appName: 'thematiq',
			request: $this->createMock(originalClassName: IRequest::class),
			order: new AppMenuOrder(config: $config),
			userSession: $session
		);
	}//end controller()

	/**
	 * Scenario: one person's order is not another's. The route takes no user id.
	 *
	 * @return void
	 */
	public function testItWritesOnlyTheSignedInUser(): void {
		$response = $this->controller(uid: 'anna')->save(order: ['deck', 'files']);
		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: ['order' => ['deck', 'files']], actual: $response->getData());
		$this->assertSame(expected: ['anna' => '["deck","files"]'], actual: $this->stored);

		$params = array_map(
			static fn ($param): string => $param->getName(),
			(new ReflectionMethod(AppMenuOrderController::class, 'save'))->getParameters()
		);
		$this->assertSame(expected: ['order'], actual: $params);
	}//end testItWritesOnlyTheSignedInUser()

	/**
	 * Without a session nothing is written.
	 *
	 * @return void
	 */
	public function testWithoutASessionNothingIsWritten(): void {
		$this->assertSame(expected: 401, actual: $this->controller(uid: null)->save(order: ['files'])->getStatus());
		$this->assertSame(expected: [], actual: $this->stored);
	}//end testWithoutASessionNothingIsWritten()

	/**
	 * Any signed-in user may call it, never an anonymous one, and the CSRF check stays on.
	 *
	 * @return void
	 */
	public function testSignedInUsersOnlyWithTheCsrfCheck(): void {
		$method = new ReflectionMethod(AppMenuOrderController::class, 'save');
		$this->assertCount(expectedCount: 1, haystack: $method->getAttributes(NoAdminRequired::class));
		$this->assertCount(expectedCount: 0, haystack: $method->getAttributes(PublicPage::class));
		$this->assertCount(expectedCount: 0, haystack: $method->getAttributes(NoCSRFRequired::class));
	}//end testSignedInUsersOnlyWithTheCsrfCheck()
}//end class
