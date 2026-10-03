<?php

/**
 * NL Design Theme Injection Listener.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Listener
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/css-architecture/spec.md
 * @spec openspec/specs/per-app-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Listener;

use OCA\Thematiq\Service\AppThemingService;
use OCA\Thematiq\Service\CssInjectionService;
use OCA\Thematiq\Service\EnvironmentMarkerService;
use OCP\AppFramework\Http\Events\BeforeLoginTemplateRenderedEvent;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Injects nldesign CSS on render events instead of at `Application::boot()`.
 *
 * Registered in `Application::register()` for both
 * `BeforeTemplateRenderedEvent` and `BeforeLoginTemplateRenderedEvent` — the
 * same two events Nextcloud core's own `ThemeInjectionService` uses. For a
 * `BeforeTemplateRenderedEvent` the response's `renderAs` is mapped to a
 * render context, the per-app exclusion guard runs (resolved from
 * `TemplateResponse::getApp()`, falling back to the request path), and
 * `CssInjectionService::inject()` is called for that context. For
 * `BeforeLoginTemplateRenderedEvent` the context is always `login` and the
 * per-app guard never runs — login is never an app page. Any failure inside
 * `handle()` is caught so a broken listener can never break page rendering
 * (theming is presentation, never a hard dependency).
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/specs/css-architecture/spec.md
 * @spec openspec/specs/per-app-theming/spec.md
 */
class ThemeInjectionListener implements IEventListener {

	/**
	 * The CSS injection service.
	 *
	 * @var CssInjectionService
	 */
	private CssInjectionService $cssInjectionService;

	/**
	 * Resolves the per-app theming exclusion guard.
	 *
	 * @var AppThemingService
	 */
	private AppThemingService $appThemingService;

	/**
	 * The current request, used only as the per-app guard's path fallback.
	 *
	 * @var IRequest
	 */
	private IRequest $request;

	/**
	 * Records a swallowed listener failure, so failing open is never silent.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Marks every page of a non-production server, excluded apps included.
	 *
	 * @var EnvironmentMarkerService
	 */
	private EnvironmentMarkerService $environmentMarker;

	/**
	 * Constructor.
	 *
	 * @param CssInjectionService $cssInjectionService The CSS injection service.
	 * @param AppThemingService $appThemingService The per-app theming guard resolver.
	 * @param IRequest $request The current request.
	 * @param LoggerInterface $logger The logger for swallowed failures.
	 * @param EnvironmentMarkerService $environmentMarker The environment marker.
	 */
	public function __construct(
		CssInjectionService $cssInjectionService,
		AppThemingService $appThemingService,
		IRequest $request,
		LoggerInterface $logger,
		EnvironmentMarkerService $environmentMarker,
	) {
		$this->cssInjectionService = $cssInjectionService;
		$this->appThemingService = $appThemingService;
		$this->request = $request;
		$this->logger = $logger;
		$this->environmentMarker = $environmentMarker;
	}//end __construct()

	/**
	 * Handle a render event by injecting nldesign CSS for its context.
	 *
	 * @param Event $event The dispatched event.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 * @spec openspec/specs/per-app-theming/spec.md
	 * @spec openspec/specs/environment-marker/spec.md
	 */
	public function handle(Event $event): void {
		// The environment marker is a safety signal, not a theme: it runs
		// before the per-app guard, on the login page and on every template
		// render, and fails open inside its own service.
		if ($event instanceof BeforeLoginTemplateRenderedEvent || $event instanceof BeforeTemplateRenderedEvent) {
			$this->environmentMarker->inject();
		}

		try {
			if ($event instanceof BeforeLoginTemplateRenderedEvent) {
				// Login is never an app page — the per-app guard never runs.
				$this->cssInjectionService->inject(context: 'login');
				return;
			}

			if ($event instanceof BeforeTemplateRenderedEvent) {
				$response = $event->getResponse();

				if ($this->isThemingDisabledForResponse(response: $response) === true) {
					return;
				}

				$this->cssInjectionService->inject(
					context: $this->resolveContext(response: $response),
					appId: $this->resolveAppId(response: $response)
				);
			}
		} catch (Throwable $e) {
			// Fail open: a listener failure must never break page rendering
			// (theming is presentation, never a hard dependency).
			//
			// It must not be INVISIBLE either. This catch used to have an empty
			// body, so an aborted cascade reached no log at any level and
			// presented as "one theming feature is broken" (nldesign#264).
			// Individual layers are isolated in CssInjectionService::runLayer()
			// and never arrive here; anything that does is a real listener bug.
			$this->logger->warning(
				'nldesign: theme injection failed for this render; the page was served unthemed.',
				['app' => 'thematiq', 'exception' => $e]
			);
		}//end try

	}//end handle()

	/**
	 * Map a `TemplateResponse`'s `renderAs` to a render context.
	 *
	 * Any value outside the four mapped `renderAs` constants (e.g. `blank`,
	 * `base`, or a future value) is passed through unchanged — it is not one
	 * of `CssInjectionService::VALID_CONTEXTS`, so injection always proceeds
	 * as themed for it regardless of the `themed_contexts` configuration
	 * (forward-compatibility fail-open, see design.md §2).
	 *
	 * @param TemplateResponse $response The response being rendered.
	 *
	 * @return string The resolved render context.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 */
	private function resolveContext(TemplateResponse $response): string {
		$renderAs = $response->getRenderAs();

		return match ($renderAs) {
			TemplateResponse::RENDER_AS_USER => 'user',
			TemplateResponse::RENDER_AS_GUEST => 'guest',
			TemplateResponse::RENDER_AS_PUBLIC => 'public',
			TemplateResponse::RENDER_AS_ERROR => 'error',
			default => (string)$renderAs,
		};
	}//end resolveContext()

	/**
	 * Resolve whether nldesign styling must be skipped for the app being rendered.
	 *
	 * The app id is resolved primarily from the response's own `getApp()`;
	 * when that is empty or `core` the request path is consulted as a
	 * fallback. Any resolution failure fails open to themed — theming is
	 * presentation, not security.
	 *
	 * @param TemplateResponse $response The response being rendered.
	 *
	 * @return bool True when nldesign style injection must be skipped.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	private function isThemingDisabledForResponse(TemplateResponse $response): bool {
		try {
			return $this->appThemingService->isThemingDisabledFor(appId: $this->resolveAppId(response: $response));
		} catch (Throwable $e) {
			// Fail open: presentation, not security — a broken resolve must
			// not strip theming everywhere, nor crash the render path.
			return false;
		}
	}//end isThemingDisabledForResponse()

	/**
	 * The app being rendered: the response's own `getApp()`, or the request
	 * path when that is empty or `core`. Null when neither names an app or the
	 * resolution fails (fail open: no brand, default theming).
	 *
	 * @param TemplateResponse $response The response being rendered.
	 *
	 * @return string|null The app id.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	private function resolveAppId(TemplateResponse $response): ?string {
		try {
			$appId = $response->getApp();
			if ($appId === '' || $appId === 'core') {
				$appId = $this->appThemingService->resolveAppIdFromPath(pathInfo: $this->request->getPathInfo());
			}

			return $appId;
		} catch (Throwable $e) {
			return null;
		}
	}//end resolveAppId()
}//end class
