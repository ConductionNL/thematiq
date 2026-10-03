<?php

/**
 * Unit tests for Application::register().
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/archive/2026-06-16-adopt-apphost/tasks.md#task-2
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\AppInfo;

use ErrorException;
use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Capabilities;
use OCA\Thematiq\Listener\ThemeInjectionListener;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeLoginTemplateRenderedEvent;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use PHPUnit\Framework\TestCase;

/**
 * register() on an install without `vendor/` (thematiq#266).
 */
class ApplicationTest extends TestCase {

	/**
	 * An Application whose composer autoloader is the given path.
	 *
	 * The constructor is skipped: App::__construct() asks the running server
	 * for its config, and register() needs nothing it sets up.
	 *
	 * @param string $autoloader The path register() should try.
	 *
	 * @return Application The application under test.
	 */
	private function application(string $autoloader): Application {
		return new class($autoloader) extends Application {
			/**
			 * Store the path instead of booting an app container.
			 *
			 * @param string $autoloader The autoloader path.
			 */
			public function __construct(private string $autoloader) {
			}

			/**
			 * @return string The stand-in path.
			 */
			protected function composerAutoloader(): string {
				return $this->autoloader;
			}
		};
	}//end application()

	/**
	 * A missing autoloader raises no warning and registration still happens.
	 *
	 * Every PHP warning is turned into an exception for the call, which is how
	 * the two include_once warnings per request in the issue fail this test.
	 *
	 * @return void
	 */
	public function testRegisterWithoutVendorRaisesNoWarning(): void {
		$listeners = [];
		$context = $this->createMock(IRegistrationContext::class);
		$context->expects($this->once())->method('registerCapability')->with(Capabilities::class);
		$context->method('registerEventListener')->willReturnCallback(
			static function (string $event, string $listener) use (&$listeners): void {
				$listeners[] = [$event, $listener];
			}
		);

		set_error_handler(
			static function (int $severity, string $message, string $file, int $line): bool {
				throw new ErrorException($message, 0, $severity, $file, $line);
			}
		);
		try {
			$this->application(autoloader: sys_get_temp_dir() . '/thematiq-no-vendor-' . uniqid() . '/autoload.php')
				->register($context);
		} finally {
			restore_error_handler();
		}

		$this->assertContains([BeforeTemplateRenderedEvent::class, ThemeInjectionListener::class], $listeners);
		$this->assertContains([BeforeLoginTemplateRenderedEvent::class, ThemeInjectionListener::class], $listeners);
	}//end testRegisterWithoutVendorRaisesNoWarning()

	/**
	 * The real path points at this app's own `vendor/autoload.php`.
	 *
	 * @return void
	 */
	public function testTheAutoloaderIsTheAppsOwn(): void {
		$application = new class extends Application {
			/**
			 * Skip the app container, as above.
			 */
			public function __construct() {
			}

			/**
			 * @return string The parent's path, made public for the assertion.
			 */
			public function path(): string {
				return $this->composerAutoloader();
			}
		};

		$this->assertSame(dirname(__DIR__, 3) . '/vendor/autoload.php', $application->path());
	}//end testTheAutoloaderIsTheAppsOwn()
}//end class
