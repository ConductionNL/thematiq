<?php

/**
 * Unit tests for ThemeInjectionListener.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/render-event-injection/tasks.md#task-4.1
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Listener;

use OCA\Thematiq\Listener\ThemeInjectionListener;
use OCA\Thematiq\Service\AppThemingService;
use OCA\Thematiq\Service\CssInjectionService;
use OCA\Thematiq\Service\EnvironmentMarkerService;
use OCP\AppFramework\Http\Events\BeforeLoginTemplateRenderedEvent;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\EventDispatcher\Event;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for ThemeInjectionListener.
 */
class ThemeInjectionListenerTest extends TestCase {

	/**
	 * The CSS injection service mock.
	 *
	 * @var CssInjectionService&MockObject
	 */
	private $cssInjectionService;

	/**
	 * The per-app theming guard mock.
	 *
	 * @var AppThemingService&MockObject
	 */
	private $appThemingService;

	/**
	 * The request mock.
	 *
	 * @var IRequest&MockObject
	 */
	private $request;

	/**
	 * The logger mock.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private $logger;

	/**
	 * The environment marker mock.
	 *
	 * @var EnvironmentMarkerService&MockObject
	 */
	private $environmentMarker;

	/**
	 * The listener under test.
	 *
	 * @var ThemeInjectionListener
	 */
	private ThemeInjectionListener $listener;

	/**
	 * Set up mocks before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->cssInjectionService = $this->createMock(CssInjectionService::class);
		$this->appThemingService = $this->createMock(AppThemingService::class);
		$this->request = $this->createMock(IRequest::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->environmentMarker = $this->createMock(EnvironmentMarkerService::class);
		$this->listener = new ThemeInjectionListener(
			$this->cssInjectionService,
			$this->appThemingService,
			$this->request,
			$this->logger,
			$this->environmentMarker
		);

		// No blanket `isThemingDisabledFor()` default here: PHPUnit's
		// InvocationMocker resolves overlapping unconstrained stubs in
		// REGISTRATION order (the first-registered unconstrained stub wins
		// for every call), so a setUp()-level default would silently shadow
		// a test's own `->with($appId)->willReturn(true)`. Each test
		// configures it explicitly instead (an unconfigured bool-returning
		// mock method defaults to `false`, which is what the "not excluded"
		// tests below rely on).
	}//end setUp()

	/**
	 * renderAs `user`/`guest`/`public`/`error` each reach `inject()` with the
	 * matching context.
	 *
	 * @dataProvider renderAsProvider
	 *
	 * @param string $renderAs The response's renderAs value.
	 * @param string $expectedContext The context `inject()` must receive.
	 */
	public function testRenderAsMapsToMatchingContext(string $renderAs, string $expectedContext): void {
		$response = new TemplateResponse('files', 'index', [], $renderAs);

		$this->cssInjectionService->expects($this->once())->method('inject')->with($expectedContext);

		$this->listener->handle(new BeforeTemplateRenderedEvent(true, $response));
	}//end testRenderAsMapsToMatchingContext()

	/**
	 * renderAs => context cases.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function renderAsProvider(): array {
		return [
			'user' => [TemplateResponse::RENDER_AS_USER, 'user'],
			'guest' => [TemplateResponse::RENDER_AS_GUEST, 'guest'],
			'public' => [TemplateResponse::RENDER_AS_PUBLIC, 'public'],
			'error' => [TemplateResponse::RENDER_AS_ERROR, 'error'],
		];
	}//end renderAsProvider()

	/**
	 * An unknown renderAs (e.g. `blank`) still injects — fail-open,
	 * forward-compatibility.
	 */
	public function testUnknownRenderAsStillInjects(): void {
		$response = new TemplateResponse('files', 'index', [], 'blank');

		$this->cssInjectionService->expects($this->once())->method('inject')->with('blank');

		$this->listener->handle(new BeforeTemplateRenderedEvent(true, $response));
	}//end testUnknownRenderAsStillInjects()

	/**
	 * The login event injects with context `login` and never consults the
	 * per-app guard.
	 */
	public function testLoginEventInjectsWithLoginContextAndSkipsGuard(): void {
		$response = new TemplateResponse('core', 'login', [], TemplateResponse::RENDER_AS_GUEST);

		$this->appThemingService->expects($this->never())->method('isThemingDisabledFor');
		$this->appThemingService->expects($this->never())->method('resolveAppIdFromPath');
		$this->cssInjectionService->expects($this->once())->method('inject')->with('login');

		$this->listener->handle(new BeforeLoginTemplateRenderedEvent($response));
	}//end testLoginEventInjectsWithLoginContextAndSkipsGuard()

	/**
	 * An excluded app (identified via the response's own app id) skips injection.
	 */
	public function testExcludedResponseAppSkipsInjection(): void {
		$response = new TemplateResponse('calendar', 'index', [], TemplateResponse::RENDER_AS_USER);

		$this->appThemingService->method('isThemingDisabledFor')->with('calendar')->willReturn(true);
		$this->request->expects($this->never())->method('getPathInfo');
		$this->cssInjectionService->expects($this->never())->method('inject');

		$this->listener->handle(new BeforeTemplateRenderedEvent(true, $response));
	}//end testExcludedResponseAppSkipsInjection()

	/**
	 * A non-excluded response app stays fully themed.
	 */
	public function testNonExcludedResponseAppInjectsNormally(): void {
		$response = new TemplateResponse('files', 'index', [], TemplateResponse::RENDER_AS_USER);

		$this->appThemingService->method('isThemingDisabledFor')->with('files')->willReturn(false);
		$this->cssInjectionService->expects($this->once())->method('inject')->with('user');

		$this->listener->handle(new BeforeTemplateRenderedEvent(true, $response));
	}//end testNonExcludedResponseAppInjectsNormally()

	/**
	 * An empty response app id falls back to the request path resolver.
	 */
	public function testEmptyResponseAppFallsBackToPath(): void {
		$response = new TemplateResponse('', 'index', [], TemplateResponse::RENDER_AS_USER);

		$this->request->method('getPathInfo')->willReturn('/apps/calendar/');
		$this->appThemingService->method('resolveAppIdFromPath')->with('/apps/calendar/')->willReturn('calendar');
		$this->appThemingService->method('isThemingDisabledFor')->with('calendar')->willReturn(true);
		$this->cssInjectionService->expects($this->never())->method('inject');

		$this->listener->handle(new BeforeTemplateRenderedEvent(true, $response));
	}//end testEmptyResponseAppFallsBackToPath()

	/**
	 * A `core`-attributed response app also falls back to the request path resolver.
	 */
	public function testCoreResponseAppFallsBackToPath(): void {
		$response = new TemplateResponse('core', 'index', [], TemplateResponse::RENDER_AS_USER);

		$this->request->method('getPathInfo')->willReturn('/index.php/apps/calendar/');
		$this->appThemingService->method('resolveAppIdFromPath')
			->with('/index.php/apps/calendar/')->willReturn('calendar');
		$this->appThemingService->method('isThemingDisabledFor')->with('calendar')->willReturn(true);
		$this->cssInjectionService->expects($this->never())->method('inject');

		$this->listener->handle(new BeforeTemplateRenderedEvent(true, $response));
	}//end testCoreResponseAppFallsBackToPath()

	/**
	 * A throwing app-id resolver fails open to themed rather than propagating.
	 */
	public function testResolverThrowingFailsOpenToThemed(): void {
		$response = new TemplateResponse('files', 'index', [], TemplateResponse::RENDER_AS_USER);

		$this->appThemingService->method('isThemingDisabledFor')->willThrowException(new \RuntimeException('boom'));
		$this->cssInjectionService->expects($this->once())->method('inject')->with('user');

		$this->listener->handle(new BeforeTemplateRenderedEvent(true, $response));
	}//end testResolverThrowingFailsOpenToThemed()

	/**
	 * An event that is neither of the two handled types is a no-op — no
	 * exception escapes, and injection never runs.
	 */
	public function testUnrelatedEventIsNoOp(): void {
		$unrelated = $this->createMock(Event::class);

		$this->cssInjectionService->expects($this->never())->method('inject');

		$this->listener->handle($unrelated);
		$this->addToAssertionCount(1);
	}//end testUnrelatedEventIsNoOp()

	/**
	 * A throwing CssInjectionService never lets the exception escape `handle()`
	 * — and, since nldesign#264, never does so SILENTLY.
	 *
	 * Failing open is correct (theming is presentation, never a hard
	 * dependency), but this catch used to have an empty body, so an aborted
	 * cascade reached no log at any level and presented as "one theming
	 * feature is broken". The `assertNotEmpty` below is the half that was
	 * missing: without it this test passes over a swallow that tells nobody.
	 */
	public function testInjectionServiceThrowingNeverEscapesButIsLogged(): void {
		$response = new TemplateResponse('files', 'index', [], TemplateResponse::RENDER_AS_USER);

		$this->cssInjectionService->method('inject')->willThrowException(new \RuntimeException('boom'));

		$warnings = [];
		$this->logger->method('warning')->willReturnCallback(
			function (string $message) use (&$warnings) {
				$warnings[] = $message;
			}
		);

		// Not throwing IS the assertion for the fail-open half; if `handle()`
		// let it escape, PHPUnit would report the RuntimeException here.
		$this->listener->handle(new BeforeTemplateRenderedEvent(true, $response));

		$this->assertCount(1, $warnings);
		$this->assertStringContainsString('served unthemed', $warnings[0]);
	}//end testInjectionServiceThrowingNeverEscapesButIsLogged()

	/**
	 * Double dispatch of the same event yields exactly two `inject()` calls
	 * with the same arguments each time (idempotency lives in
	 * `\OCP\Util::addStyle()`, not the listener) — no duplicated/skewed state.
	 */
	public function testDoubleDispatchYieldsNoDuplicatedSideEffectsBeyondInject(): void {
		$response = new TemplateResponse('files', 'index', [], TemplateResponse::RENDER_AS_USER);

		$this->cssInjectionService->expects($this->exactly(2))->method('inject')->with('user');

		$event = new BeforeTemplateRenderedEvent(true, $response);
		$this->listener->handle($event);
		$this->listener->handle($event);
	}//end testDoubleDispatchYieldsNoDuplicatedSideEffectsBeyondInject()

	/**
	 * The environment marker is a safety signal, not a theme: an app
	 * excluded from theming still gets it.
	 *
	 * @spec openspec/specs/environment-marker/spec.md
	 */
	public function testExcludedAppStillGetsTheEnvironmentMarker(): void {
		$response = new TemplateResponse('calendar', 'index', [], TemplateResponse::RENDER_AS_USER);

		$this->appThemingService->method('isThemingDisabledFor')->with('calendar')->willReturn(true);
		$this->environmentMarker->expects($this->once())->method('inject');
		$this->cssInjectionService->expects($this->never())->method('inject');

		$this->listener->handle(new BeforeTemplateRenderedEvent(true, $response));
	}//end testExcludedAppStillGetsTheEnvironmentMarker()

	/**
	 * The login page gets the environment marker.
	 *
	 * @spec openspec/specs/environment-marker/spec.md
	 */
	public function testLoginPageGetsTheEnvironmentMarker(): void {
		$response = new TemplateResponse('core', 'login', [], TemplateResponse::RENDER_AS_GUEST);

		$this->environmentMarker->expects($this->once())->method('inject');

		$this->listener->handle(new BeforeLoginTemplateRenderedEvent($response));
	}//end testLoginPageGetsTheEnvironmentMarker()

	/**
	 * An unrelated event gets no marker.
	 */
	public function testUnrelatedEventGetsNoEnvironmentMarker(): void {
		$this->environmentMarker->expects($this->never())->method('inject');

		$this->listener->handle(new Event());
	}//end testUnrelatedEventGetsNoEnvironmentMarker()
}//end class
