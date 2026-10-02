<?php

/**
 * Unit tests for ConfigSourceLockMiddleware and the theme-as-code commands and controller.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Middleware
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Middleware;

use OCA\Thematiq\Command\ConfigApply;
use OCA\Thematiq\Controller\ConfigSourceController;
use OCA\Thematiq\Controller\FontController;
use OCA\Thematiq\Controller\PreviewController;
use OCA\Thematiq\Controller\SettingsController;
use OCA\Thematiq\Middleware\ConfigSourceLockMiddleware;
use OCA\Thematiq\Service\ConfigSourceService;
use OCA\Thematiq\Service\Exception\ConfigSourceLockedException;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests for the configuration lock.
 */
class ConfigSourceLockMiddlewareTest extends TestCase {

	/**
	 * The middleware for a request method and lock state.
	 *
	 * @param string $method The HTTP method.
	 * @param bool $locked Whether the lock is on.
	 *
	 * @return ConfigSourceLockMiddleware The middleware.
	 */
	private function middleware(string $method, bool $locked): ConfigSourceLockMiddleware {
		$request = $this->createMock(IRequest::class);
		$request->method('getMethod')->willReturn($method);
		$source = $this->createMock(ConfigSourceService::class);
		$source->method('isLocked')->willReturn($locked);
		$source->method('getSourcePath')->willReturn('/srv/branding');
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(fn (string $text, array $params = []) => vsprintf($text, $params));

		return new ConfigSourceLockMiddleware($request, $source, $l10n);
	}//end middleware()

	/**
	 * Two setters answer 423 naming the source while locked.
	 *
	 * @return void
	 */
	public function testSettersAnswer423WhileLocked(): void {
		$cases = [
			[$this->createMock(SettingsController::class), 'setTokenSet'],
			[$this->createMock(FontController::class), 'upload'],
		];
		foreach ($cases as [$controller, $method]) {
			$middleware = $this->middleware(method: 'POST', locked: true);
			try {
				$middleware->beforeController($controller, $method);
				$this->fail('A locked setter must be refused: ' . $method);
			} catch (ConfigSourceLockedException $e) {
				$response = $middleware->afterException($controller, $method, $e);
				$this->assertSame(423, $response->getStatus());
				$this->assertStringContainsString('/srv/branding', $response->getData()['error']);
			}
		}
	}//end testSettersAnswer423WhileLocked()

	/**
	 * Reads, session-only actions and an unlocked server pass.
	 *
	 * @return void
	 */
	public function testReadsSessionActionsAndUnlockedPass(): void {
		$settings = $this->createMock(SettingsController::class);
		$this->middleware(method: 'GET', locked: true)->beforeController($settings, 'getTokenSetPreview');
		$this->middleware(method: 'POST', locked: true)->beforeController($this->createMock(PreviewController::class), 'start');
		$this->middleware(method: 'POST', locked: false)->beforeController($settings, 'setTokenSet');
		$this->addToAssertionCount(3);
	}//end testReadsSessionActionsAndUnlockedPass()

	/**
	 * Other exceptions pass through unchanged.
	 *
	 * @return void
	 */
	public function testOtherExceptionsPassThrough(): void {
		$this->expectException(RuntimeException::class);
		$this->middleware(method: 'POST', locked: true)->afterException(
			$this->createMock(SettingsController::class),
			'setTokenSet',
			new RuntimeException('boom')
		);
	}//end testOtherExceptionsPassThrough()

	/**
	 * The status endpoint is admin-only and returns the service status.
	 *
	 * @return void
	 */
	public function testStatusEndpointIsAdminOnly(): void {
		$attributes = (new ReflectionMethod(ConfigSourceController::class, 'status'))->getAttributes(AuthorizedAdminSetting::class);
		$this->assertCount(1, $attributes);
		$this->assertSame(Admin::class, $attributes[0]->getArguments()[0]);

		$source = $this->createMock(ConfigSourceService::class);
		$source->method('getStatus')->willReturn(['managed' => true, 'path' => '/srv/branding']);
		$controller = new ConfigSourceController('thematiq', $this->createMock(IRequest::class), $source);
		$this->assertSame('/srv/branding', $controller->status()->getData()['path']);
	}//end testStatusEndpointIsAdminOnly()

	/**
	 * The apply command exits non-zero on an invalid package and lists the errors.
	 *
	 * @return void
	 */
	public function testApplyCommandFailsOnAnInvalidPackage(): void {
		$source = $this->createMock(ConfigSourceService::class);
		$source->method('applyIfChanged')->willReturn(
			['status' => 'failed', 'errors' => [['section' => 'customFonts', 'message' => 'Missing font file fonts/custom-corporate.woff2.']]]
		);
		$command = new ConfigApply($source);
		$application = new Application();
		$application->add($command);
		$tester = new CommandTester($application->find((string)$command->getName()));

		$this->assertSame(1, $tester->execute([]));
		$this->assertStringContainsString('fonts/custom-corporate.woff2', $tester->getDisplay());
		$this->assertSame('thematiq:config:apply', $command->getName());
	}//end testApplyCommandFailsOnAnInvalidPackage()

	/**
	 * The apply command succeeds when applied or unchanged.
	 *
	 * @return void
	 */
	public function testApplyCommandSucceedsWhenApplied(): void {
		$source = $this->createMock(ConfigSourceService::class);
		$source->method('applyIfChanged')->willReturn(['status' => 'applied', 'revision' => '3f2a9c1', 'hash' => 'sha256:x']);
		$command = new ConfigApply($source);
		$application = new Application();
		$application->add($command);
		$tester = new CommandTester($application->find((string)$command->getName()));

		$this->assertSame(0, $tester->execute([]));
		$this->assertStringContainsString('3f2a9c1', $tester->getDisplay());
	}//end testApplyCommandSucceedsWhenApplied()
}//end class
