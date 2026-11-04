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
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getMethod')->willReturn($method);
		$source = $this->createMock(originalClassName: ConfigSourceService::class);
		$source->method('isLocked')->willReturn($locked);
		$source->method('getSourcePath')->willReturn('/srv/branding');
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnCallback(fn (string $text, array $params = []) => vsprintf($text, $params));

		return new ConfigSourceLockMiddleware(request: $request, source: $source, l10n: $l10n);
	}//end middleware()

	/**
	 * Two setters answer 423 naming the source while locked.
	 *
	 * @return void
	 */
	public function testSettersAnswer423WhileLocked(): void {
		$cases = [
			[$this->createMock(originalClassName: SettingsController::class), 'setTokenSet'],
			[$this->createMock(originalClassName: FontController::class), 'upload'],
		];
		foreach ($cases as [$controller, $method]) {
			$middleware = $this->middleware(method: 'POST', locked: true);
			try {
				$middleware->beforeController($controller, $method);
				$this->fail(message: 'A locked setter must be refused: ' . $method);
			} catch (ConfigSourceLockedException $e) {
				$response = $middleware->afterException($controller, $method, $e);
				$this->assertSame(expected: 423, actual: $response->getStatus());
				$this->assertStringContainsString(needle: '/srv/branding', haystack: $response->getData()['error']);
			}
		}
	}//end testSettersAnswer423WhileLocked()

	/**
	 * Reads, session-only actions and an unlocked server pass.
	 *
	 * @return void
	 */
	public function testReadsSessionActionsAndUnlockedPass(): void {
		$settings = $this->createMock(originalClassName: SettingsController::class);
		$this->middleware(method: 'GET', locked: true)->beforeController($settings, 'getTokenSetPreview');
		$this->middleware(method: 'POST', locked: true)->beforeController($this->createMock(originalClassName: PreviewController::class), 'start');
		$this->middleware(method: 'POST', locked: false)->beforeController($settings, 'setTokenSet');
		$this->addToAssertionCount(count: 3);
	}//end testReadsSessionActionsAndUnlockedPass()

	/**
	 * Other exceptions pass through unchanged.
	 *
	 * @return void
	 */
	public function testOtherExceptionsPassThrough(): void {
		$this->expectException(exception: RuntimeException::class);
		$this->middleware(method: 'POST', locked: true)->afterException(
			$this->createMock(originalClassName: SettingsController::class),
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
		$this->assertCount(expectedCount: 1, haystack: $attributes);
		$this->assertSame(expected: Admin::class, actual: $attributes[0]->getArguments()[0]);

		$source = $this->createMock(originalClassName: ConfigSourceService::class);
		$source->method('getStatus')->willReturn(['managed' => true, 'path' => '/srv/branding']);
		$controller = new ConfigSourceController(
			appName: 'thematiq',
			request: $this->createMock(originalClassName: IRequest::class),
			source: $source
		);
		$this->assertSame(expected: '/srv/branding', actual: $controller->status()->getData()['path']);
	}//end testStatusEndpointIsAdminOnly()

	/**
	 * The apply command exits non-zero on an invalid package and lists the errors.
	 *
	 * @return void
	 */
	public function testApplyCommandFailsOnAnInvalidPackage(): void {
		$source = $this->createMock(originalClassName: ConfigSourceService::class);
		$source->method('applyIfChanged')->willReturn(
			['status' => 'failed', 'errors' => [['section' => 'customFonts', 'message' => 'Missing font file fonts/custom-corporate.woff2.']]]
		);
		$command = new ConfigApply(source: $source);
		$application = new Application();
		$application->add($command);
		$tester = new CommandTester($application->find((string)$command->getName()));

		$this->assertSame(expected: 1, actual: $tester->execute([]));
		$this->assertStringContainsString(needle: 'fonts/custom-corporate.woff2', haystack: $tester->getDisplay());
		$this->assertSame(expected: 'thematiq:config:apply', actual: $command->getName());
	}//end testApplyCommandFailsOnAnInvalidPackage()

	/**
	 * The apply command succeeds when applied or unchanged.
	 *
	 * @return void
	 */
	public function testApplyCommandSucceedsWhenApplied(): void {
		$source = $this->createMock(originalClassName: ConfigSourceService::class);
		$source->method('applyIfChanged')->willReturn(['status' => 'applied', 'revision' => '3f2a9c1', 'hash' => 'sha256:x']);
		$command = new ConfigApply(source: $source);
		$application = new Application();
		$application->add($command);
		$tester = new CommandTester($application->find((string)$command->getName()));

		$this->assertSame(expected: 0, actual: $tester->execute([]));
		$this->assertStringContainsString(needle: '3f2a9c1', haystack: $tester->getDisplay());
	}//end testApplyCommandSucceedsWhenApplied()
}//end class
