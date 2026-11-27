<?php

/**
 * Tests for the listener that opens the first-time setup.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-wizard-opens-for-an-administrator-on-the-first-nextcloud-page-while-setup-is-due-req-tqfs-001
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Listener;

use OCA\Thematiq\Listener\SetupWizardListener;
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
	private array $states = [];

	/**
	 * How many times the loader was added.
	 *
	 * @var int
	 */
	private int $loaders = 0;

	/**
	 * An app root with or without a built bundle.
	 *
	 * @var string
	 */
	private string $root = '';

	/**
	 * Make a temporary app root holding the bundle.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->root = sys_get_temp_dir() . '/thematiq-setup-' . bin2hex(random_bytes(4));
		mkdir($this->root . '/js/vendor', 0777, true);
		file_put_contents($this->root . '/' . SetupWizardListener::BUNDLE, '');
	}//end setUp()

	/**
	 * Remove the temporary app root.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		@unlink($this->root . '/' . SetupWizardListener::BUNDLE);
		@rmdir($this->root . '/js/vendor');
		@rmdir($this->root . '/js');
		@rmdir($this->root);
	}//end tearDown()

	/**
	 * Run the listener once.
	 *
	 * @param string $renderAs How the page renders.
	 * @param bool $isAdmin Whether the user is in the admin group.
	 * @param bool $due Whether setup is due.
	 * @param bool $loggedIn Whether someone is signed in.
	 *
	 * @return void
	 */
	private function run(string $renderAs, bool $isAdmin, bool $due, bool $loggedIn = true): void {
		$status = $this->createMock(originalClassName: SetupStatusService::class);
		$status->method('isDue')->willReturn($due);
		$status->method('status')->willReturn(['completed' => false]);
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('someone');
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($loggedIn === true ? $user : null);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isAdmin')->willReturn($isAdmin);
		$state = $this->createMock(originalClassName: IInitialState::class);
		$state->method('provideInitialState')->willReturnCallback(
			function (string $key, $data): void {
				$this->states[$key] = $data;
			}
		);

		$listener = new class($status, $session, $groups, $state, $this->createMock(originalClassName: LoggerInterface::class), $this->root, $this) extends SetupWizardListener {
			/**
			 * The test, which counts the loaders.
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
			 * Count instead of adding the script.
			 *
			 * @return void
			 */
			protected function addLoader(): void {
				$this->test->countLoader();
			}//end addLoader()
		};

		$listener->handle(new BeforeTemplateRenderedEvent($loggedIn, new TemplateResponse('dashboard', 'index', [], $renderAs)));
	}//end run()

	/**
	 * Count one added loader.
	 *
	 * @return void
	 */
	public function countLoader(): void {
		$this->loaders++;
	}//end countLoader()

	/**
	 * An administrator's user page gets the state and the loader while setup is due.
	 *
	 * @return void
	 */
	public function testAnAdminsUserPageGetsTheWizard(): void {
		$this->run(renderAs: TemplateResponse::RENDER_AS_USER, isAdmin: true, due: true);

		$this->assertSame(expected: ['setup_status' => ['completed' => false]], actual: $this->states);
		$this->assertSame(expected: 1, actual: $this->loaders);
	}//end testAnAdminsUserPageGetsTheWizard()

	/**
	 * Nothing once setup is done or skipped.
	 *
	 * @return void
	 */
	public function testNothingOnceSetUp(): void {
		$this->run(renderAs: TemplateResponse::RENDER_AS_USER, isAdmin: true, due: false);

		$this->assertSame(expected: [], actual: $this->states);
		$this->assertSame(expected: 0, actual: $this->loaders);
	}//end testNothingOnceSetUp()

	/**
	 * Nothing for a non-admin, a delegated theming administrator (not in the admin group) or a guest.
	 *
	 * @return void
	 */
	public function testNothingForANonAdminOrAGuest(): void {
		$this->run(renderAs: TemplateResponse::RENDER_AS_USER, isAdmin: false, due: true);
		$this->run(renderAs: TemplateResponse::RENDER_AS_USER, isAdmin: true, due: true, loggedIn: false);

		$this->assertSame(expected: [], actual: $this->states);
		$this->assertSame(expected: 0, actual: $this->loaders);
	}//end testNothingForANonAdminOrAGuest()

	/**
	 * Nothing on a guest, public or error page, also for an administrator.
	 *
	 * @return void
	 */
	public function testNothingOnAPageThatIsNoUserPage(): void {
		foreach ([TemplateResponse::RENDER_AS_GUEST, TemplateResponse::RENDER_AS_PUBLIC, TemplateResponse::RENDER_AS_ERROR, TemplateResponse::RENDER_AS_BLANK] as $renderAs) {
			$this->run(renderAs: $renderAs, isAdmin: true, due: true);
		}

		$this->assertSame(expected: 0, actual: $this->loaders);
	}//end testNothingOnAPageThatIsNoUserPage()

	/**
	 * A checkout without a built bundle adds nothing.
	 *
	 * @return void
	 */
	public function testNothingWithoutABuiltBundle(): void {
		unlink($this->root . '/' . SetupWizardListener::BUNDLE);
		$this->run(renderAs: TemplateResponse::RENDER_AS_USER, isAdmin: true, due: true);

		$this->assertSame(expected: 0, actual: $this->loaders);
	}//end testNothingWithoutABuiltBundle()
}//end class
