<?php

/**
 * Unit tests for the ADR-040 OpenRegister autoload prelude.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/federated-config-sharing/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\AppInfo;

use OCA\Thematiq\AppInfo\OpenRegisterAutoloader;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * `Application::register()` runs before OpenRegister's PSR-4 prefix exists,
 * because the app coordinator walks a SORTED list and `nldesign` sorts before
 * `openregister`. Everything downstream of that — the `class_exists()` guard,
 * the federated-config listener, whether `nldesign.theme` reaches the
 * catalogue at all — depends on this prelude running and, crucially, on it
 * NEVER THROWING when OpenRegister is absent.
 *
 * That second half is what these tests pin. A prelude that throws would abort
 * `register()`, and the coordinator catches the Throwable, logs an emergency
 * and continues — leaving the app enabled with half its wiring missing and
 * nothing in the UI to say so.
 */
class OpenRegisterAutoloaderTest extends TestCase {

	/**
	 * Temporary fake openregister app directory.
	 *
	 * @var string
	 */
	private string $appPath;

	protected function setUp(): void {
		parent::setUp();
		$this->appPath = sys_get_temp_dir() . '/thematiq-or-' . bin2hex(random_bytes(4));
		mkdir($this->appPath . '/lib/Fake', 0777, true);
		file_put_contents(
			$this->appPath . '/lib/Fake/Probe.php',
			"<?php\nnamespace OCA\\OpenRegister\\Fake;\nfinal class Probe {}\n"
		);
	}//end setUp()

	protected function tearDown(): void {
		OpenRegisterAutoloader::unregister();
		@unlink($this->appPath . '/lib/Fake/Probe.php');
		@rmdir($this->appPath . '/lib/Fake');
		@rmdir($this->appPath . '/lib');
		@rmdir($this->appPath);
		parent::tearDown();
	}//end tearDown()

	/**
	 * Nextcloud 35 removed `OC_App::registerAutoloading()`, which this prelude
	 * used to call inside its catch-all — so on 35 it silently returned false.
	 * Only public API and plain PHP may be used.
	 */
	public function testSourceUsesNoPrivateOcAppApi(): void {
		$source = (string) file_get_contents(__DIR__ . '/../../../lib/AppInfo/OpenRegisterAutoloader.php');
		$code   = (string) preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $source);
		$this->assertStringNotContainsString('OC_App', $code);
	}//end testSourceUsesNoPrivateOcAppApi()

	/**
	 * Enabled OpenRegister: the PSR-4 prefix is registered, idempotently, and
	 * an OpenRegister class actually becomes loadable.
	 */
	public function testRegistersPsr4PrefixWhenEnabled(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForAnyone')->with('openregister')->willReturn(true);
		$appManager->method('getAppPath')->with('openregister')->willReturn($this->appPath . '/');

		$this->assertTrue((new OpenRegisterAutoloader())->ensure($appManager));
		$this->assertTrue((new OpenRegisterAutoloader())->ensure($appManager));
		$this->assertTrue(class_exists('OCA\\OpenRegister\\Fake\\Probe'));
	}//end testRegistersPsr4PrefixWhenEnabled()

	/**
	 * Disabled OpenRegister: false, and its path is never asked for.
	 */
	public function testDisabledOpenRegisterDegradesToFalse(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForAnyone')->willReturn(false);
		$appManager->expects($this->never())->method('getAppPath');

		$this->assertFalse((new OpenRegisterAutoloader())->ensure($appManager));
	}//end testDisabledOpenRegisterDegradesToFalse()

	/**
	 * The loader only ever answers for OpenRegister's namespace.
	 */
	public function testClassFileOnlyAnswersForOpenRegister(): void {
		$this->assertSame(
			'/x/lib/Db/Schema.php',
			OpenRegisterAutoloader::classFile(appPath: '/x', class: 'OCA\\OpenRegister\\Db\\Schema')
		);
		$this->assertNull(OpenRegisterAutoloader::classFile(appPath: '/x', class: 'OCA\\Thematiq\\Foo'));
		$this->assertNull(OpenRegisterAutoloader::classFile(appPath: '/x', class: 'OCA\\OpenRegister\\'));
	}//end testClassFileOnlyAnswersForOpenRegister()

	/**
	 * The app id must be the real one, not a near-miss. `getAppPath()` throws
	 * for an unknown id, which this class swallows — so a typo here would
	 * degrade silently and forever, which is the exact failure mode the class
	 * exists to prevent.
	 */
	public function testAppIdIsOpenregister(): void {
		$this->assertSame('openregister', OpenRegisterAutoloader::OPENREGISTER_APP_ID);
	}//end testAppIdIsOpenregister()

	/**
	 * An absent or disabled OpenRegister must produce `false`, not an
	 * exception. `getAppPath()` throwing is the normal, supported signal that
	 * the app is not installed.
	 */
	public function testAbsentOpenRegisterDegradesToFalseWithoutThrowing(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForAnyone')->willReturn(true);
		$appManager->method('getAppPath')
			->willThrowException(new RuntimeException('App openregister not found'));

		$this->assertFalse((new OpenRegisterAutoloader())->ensure($appManager));
	}//end testAbsentOpenRegisterDegradesToFalseWithoutThrowing()

	/**
	 * The prelude asks for the path of `openregister` specifically — the whole
	 * point is which app's autoloader gets pulled in.
	 */
	public function testAsksTheAppManagerForOpenregistersPath(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForAnyone')->with('openregister')->willReturn(true);
		$appManager->expects($this->once())
			->method('getAppPath')
			->with('openregister')
			->willThrowException(new RuntimeException('not installed here'));

		(new OpenRegisterAutoloader())->ensure($appManager);
	}//end testAsksTheAppManagerForOpenregistersPath()

	/**
	 * With no argument the prelude resolves the app manager from the server
	 * container. Under PHPUnit there is no container, so this exercises the
	 * same swallow-and-degrade path a real absent OpenRegister takes — and
	 * proves the no-argument call used by `Application::register()` cannot
	 * throw either.
	 */
	public function testNoArgumentCallNeverThrows(): void {
		$this->assertIsBool((new OpenRegisterAutoloader())->ensure());
	}//end testNoArgumentCallNeverThrows()
}//end class
