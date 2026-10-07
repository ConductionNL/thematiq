<?php

/**
 * Unit tests for the name and the role in the workplace top bar.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/header-style-workplace/specs/workplace-layout/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\HeaderUserService;
use OCA\Thematiq\Service\LayoutOptionsService;
use OCP\Accounts\IAccount;
use OCP\Accounts\IAccountManager;
use OCP\Accounts\IAccountProperty;
use OCP\AppFramework\Services\IInitialState;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The service hands the signed-in person's display name and profile role to
 * js/header-user.js, does nothing with nobody signed in, and fails open.
 */
class HeaderUserServiceTest extends TestCase {

	/**
	 * The user session mock.
	 *
	 * @var IUserSession&MockObject
	 */
	private $userSession;

	/**
	 * The account manager mock.
	 *
	 * @var IAccountManager&MockObject
	 */
	private $accountManager;

	/**
	 * The initial state mock.
	 *
	 * @var IInitialState&MockObject
	 */
	private $initialState;

	/**
	 * The logger mock.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private $logger;

	/**
	 * How often the script was emitted.
	 *
	 * @var int
	 */
	private int $scripts = 0;

	/**
	 * Set up the mocks.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->userSession = $this->createMock(IUserSession::class);
		$this->accountManager = $this->createMock(IAccountManager::class);
		$this->initialState = $this->createMock(IInitialState::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->scripts = 0;
	}//end setUp()

	/**
	 * The service, with the script seam counted instead of emitted.
	 *
	 * @return HeaderUserService
	 */
	private function service(): HeaderUserService {
		$service = $this->getMockBuilder(HeaderUserService::class)
			->setConstructorArgs([$this->userSession, $this->accountManager, $this->initialState, $this->logger])
			->onlyMethods(['emitScript'])
			->getMock();
		$service->method('emitScript')->willReturnCallback(
			function (): void {
				$this->scripts++;
			}
		);

		return $service;
	}//end service()

	/**
	 * Sign a person in with a display name and a profile role.
	 *
	 * @param string $name The display name.
	 * @param string $role The role property's value.
	 *
	 * @return void
	 */
	private function signIn(string $name, string $role): void {
		$user = $this->createMock(IUser::class);
		$user->method('getDisplayName')->willReturn($name);
		$this->userSession->method('getUser')->willReturn($user);

		$property = $this->createMock(IAccountProperty::class);
		$property->method('getValue')->willReturn($role);
		$account = $this->createMock(IAccount::class);
		$account->method('getProperty')->with(IAccountManager::PROPERTY_ROLE)->willReturn($property);
		$this->accountManager->method('getAccount')->with($user)->willReturn($account);
	}//end signIn()

	/**
	 * Layout options that resolve the page's stylesheets to the given list.
	 *
	 * @param array<int, string> $sheets The stylesheets.
	 *
	 * @return LayoutOptionsService
	 */
	private function layout(array $sheets = ['workplace-layout', 'header-workplace']): LayoutOptionsService {
		$layout = $this->createMock(LayoutOptionsService::class);
		$layout->method('stylesheets')->willReturn($sheets);

		return $layout;
	}//end layout()

	/**
	 * The board's person: the name and the role, handed over once with the script.
	 *
	 * @spec openspec/changes/header-style-workplace/specs/workplace-layout/spec.md#requirement-the-workplace-header-shows-the-name-and-the-role
	 */
	public function testTheNameAndTheRoleAreHandedToTheScript(): void {
		$this->signIn(name: 'Pieter Jansen', role: ' Woo-coördinator ');
		$this->initialState->expects($this->once())->method('provideInitialState')
			->with('header-user', ['name' => 'Pieter Jansen', 'role' => 'Woo-coördinator']);

		$this->service()->inject(tokenSet: 'zuiddrecht', layoutOptions: $this->layout());

		$this->assertSame(1, $this->scripts);
	}//end testTheNameAndTheRoleAreHandedToTheScript()

	/**
	 * A profile without a role hands over the name and an empty role, so the
	 * bar shows the name only.
	 *
	 * @spec openspec/changes/header-style-workplace/specs/workplace-layout/spec.md#requirement-the-workplace-header-shows-the-name-and-the-role
	 */
	public function testWithoutARoleTheNameStandsAlone(): void {
		$this->signIn(name: 'admin', role: '');

		$this->assertSame(['name' => 'admin', 'role' => ''], $this->service()->person());
	}//end testWithoutARoleTheNameStandsAlone()

	/**
	 * With nobody signed in (the login page, a public share) nothing is
	 * handed over and no script loads.
	 *
	 * @spec openspec/changes/header-style-workplace/specs/workplace-layout/spec.md#requirement-the-workplace-header-shows-the-name-and-the-role
	 */
	public function testNobodySignedInLoadsNothing(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->initialState->expects($this->never())->method('provideInitialState');

		$this->service()->inject(tokenSet: 'zuiddrecht', layoutOptions: $this->layout());

		$this->assertSame(0, $this->scripts);
	}//end testNobodySignedInLoadsNothing()

	/**
	 * A page without the workplace bar (another header style, or the default
	 * layout) gets no state and no script, even with somebody signed in.
	 *
	 * @spec openspec/changes/header-style-workplace/specs/workplace-layout/spec.md#requirement-the-workplace-header-shows-the-name-and-the-role
	 */
	public function testWithoutTheWorkplaceBarNothingLoads(): void {
		$this->signIn(name: 'Pieter Jansen', role: 'Woo-coördinator');
		$this->initialState->expects($this->never())->method('provideInitialState');

		$this->service()->inject(tokenSet: 'utrecht', layoutOptions: $this->layout(sheets: ['workplace-layout']));

		$this->assertSame(0, $this->scripts);
	}//end testWithoutTheWorkplaceBarNothingLoads()

	/**
	 * A failure is logged and leaves the page with Nextcloud's own avatar:
	 * no state, no script, no exception.
	 *
	 * @spec openspec/changes/header-style-workplace/specs/workplace-layout/spec.md#requirement-the-workplace-header-shows-the-name-and-the-role
	 */
	public function testAFailureIsLoggedAndLeavesTheAvatar(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getDisplayName')->willReturn('Pieter Jansen');
		$this->userSession->method('getUser')->willReturn($user);
		$this->accountManager->method('getAccount')->willThrowException(new RuntimeException('accounts table gone'));
		$this->initialState->expects($this->never())->method('provideInitialState');
		$this->logger->expects($this->once())->method('warning');

		$this->service()->inject(tokenSet: 'zuiddrecht', layoutOptions: $this->layout());

		$this->assertSame(0, $this->scripts);
	}//end testAFailureIsLoggedAndLeavesTheAvatar()
}//end class
