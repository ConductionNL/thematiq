<?php

/**
 * Tests for the listener that opens the first-time setup.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-wizard-opens-for-an-administrator-on-the-first-nextcloud-page-while-setup-is-due-req-tqfs-001
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Listener;

use OCA\Thematiq\Listener\SetupWizardListener;
use OCA\Thematiq\Service\SetupAudience;
use OCA\Thematiq\Service\SetupStatusService;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Only an administrator's user page gets the wizard, and only while setup is due.
 */
class SetupWizardListenerTest extends TestCase {

	/**
	 * The initial states provided.
	 *
	 * @var array<string, mixed>
	 */
	public array $states = [];

	/**
	 * How many times the loader was added.
	 *
	 * @var int
	 */
	public int $loaders = 0;

	/**
	 * Whether the wizard bundle counts as built.
	 *
	 * @var bool
	 */
	public bool $built = true;

	/**
	 * The audience over a user who is or is not signed in and in the admin group.
	 *
	 * @param bool $isAdmin Whether the user is in the admin group.
	 * @param bool $loggedIn Whether someone is signed in.
	 *
	 * @return SetupAudience The audience.
	 */
	private function audience(bool $isAdmin, bool $loggedIn): SetupAudience {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('someone');
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn([null, $user][(int)$loggedIn]);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isAdmin')->willReturn($isAdmin);

		return new SetupAudience(userSession: $session, groupManager: $groups);
	}//end audience()

	/**
	 * Fire the listener once.
	 *
	 * @param string $renderAs How the page renders.
	 * @param bool $isAdmin Whether the user is in the admin group.
	 * @param bool $due Whether setup is due.
	 * @param bool $loggedIn Whether someone is signed in.
	 *
	 * @return void
	 */
	private function fire(string $renderAs, bool $isAdmin, bool $due, bool $loggedIn = true): void {
		$status = $this->createMock(originalClassName: SetupStatusService::class);
		$status->method('isDue')->willReturn($due);
		$status->method('status')->willReturn(['completed' => false]);
		$state = $this->createMock(originalClassName: IInitialState::class);
		$state->method('provideInitialState')->willReturnCallback(
			function (string $key, $data): void {
				$this->states[$key] = $data;
			}
		);

		$listener = new class(
			$status,
			$this->audience(isAdmin: $isAdmin, loggedIn: $loggedIn),
			$state,
			$this->createMock(originalClassName: LoggerInterface::class),
			$this
		) extends SetupWizardListener {
			/**
			 * The test, which records the loaders.
			 *
			 * @var SetupWizardListenerTest
			 */
			private SetupWizardListenerTest $test;

			/**
			 * Constructor.
			 *
			 * @param mixed ...$args The listener's arguments, then the test.
			 */
			public function __construct(...$args) {
				$this->test = array_pop($args);
				parent::__construct(...$args);
			}//end __construct()

			/**
			 * Whether the bundle counts as built in this test.
			 *
			 * @return bool The test's answer.
			 */
			protected function bundleBuilt(): bool {
				return $this->test->built;
			}//end bundleBuilt()

			/**
			 * Count instead of adding the script.
			 *
			 * @return void
			 */
			protected function addLoader(): void {
				$this->test->loaders++;
			}//end addLoader()
		};

		$listener->handle(new BeforeTemplateRenderedEvent($loggedIn, new TemplateResponse('dashboard', 'index', [], $renderAs)));
	}//end fire()

	/**
	 * An administrator's user page gets the state and the loader while setup is due.
	 *
	 * @return void
	 */
	public function testAnAdminsUserPageGetsTheWizard(): void {
		$this->fire(renderAs: TemplateResponse::RENDER_AS_USER, isAdmin: true, due: true);

		$this->assertSame(expected: ['setup_status' => ['completed' => false]], actual: $this->states);
		$this->assertSame(expected: 1, actual: $this->loaders);
	}//end testAnAdminsUserPageGetsTheWizard()

	/**
	 * Nothing once setup is done or skipped.
	 *
	 * @return void
	 */
	public function testNothingOnceSetUp(): void {
		$this->fire(renderAs: TemplateResponse::RENDER_AS_USER, isAdmin: true, due: false);

		$this->assertSame(expected: [], actual: $this->states);
		$this->assertSame(expected: 0, actual: $this->loaders);
	}//end testNothingOnceSetUp()

	/**
	 * Nothing for a non-admin, a delegated theming administrator (not in the admin group) or a guest.
	 *
	 * @return void
	 */
	public function testNothingForANonAdminOrAGuest(): void {
		$this->fire(renderAs: TemplateResponse::RENDER_AS_USER, isAdmin: false, due: true);
		$this->fire(renderAs: TemplateResponse::RENDER_AS_USER, isAdmin: true, due: true, loggedIn: false);

		$this->assertSame(expected: [], actual: $this->states);
		$this->assertSame(expected: 0, actual: $this->loaders);
	}//end testNothingForANonAdminOrAGuest()

	/**
	 * Nothing on a guest, public, error or blank page, also for an administrator.
	 *
	 * @return void
	 */
	public function testNothingOnAPageThatIsNoUserPage(): void {
		$pages = [
			TemplateResponse::RENDER_AS_GUEST,
			TemplateResponse::RENDER_AS_PUBLIC,
			TemplateResponse::RENDER_AS_ERROR,
			TemplateResponse::RENDER_AS_BLANK,
		];
		foreach ($pages as $renderAs) {
			$this->fire(renderAs: $renderAs, isAdmin: true, due: true);
		}

		$this->assertSame(expected: 0, actual: $this->loaders);
	}//end testNothingOnAPageThatIsNoUserPage()

	/**
	 * A checkout without a built bundle adds nothing.
	 *
	 * @return void
	 */
	public function testNothingWithoutABuiltBundle(): void {
		$this->built = false;
		$this->fire(renderAs: TemplateResponse::RENDER_AS_USER, isAdmin: true, due: true);

		$this->assertSame(expected: 0, actual: $this->loaders);
	}//end testNothingWithoutABuiltBundle()
}//end class
