<?php

/**
 * NL Design Application Bootstrap.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Application
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-1
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-2
 * @spec openspec/changes/render-event-injection/tasks.md#task-3.1
 */

declare(strict_types=1);

namespace OCA\Thematiq\AppInfo;

use OCA\Thematiq\Capabilities;
use OCA\Thematiq\Listener\ThemeInjectionListener;
use OCA\Thematiq\Middleware\ConfigSourceLockMiddleware;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeLoginTemplateRenderedEvent;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;

/**
 * Main application class for NL Design.
 *
 * Bootstraps the NL Design theme system and injects design tokens.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-1
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-2
 */
class Application extends App implements IBootstrap {
	public const APP_ID = 'thematiq';

	/**
	 * The config.php key that declares the server's OTAP environment
	 * (openspec/specs/environment-marker/spec.md).
	 */
	public const ENVIRONMENT_CONFIG_KEY = 'thematiq.environment';

	/**
	 * The command an administrator runs to declare the environment.
	 */
	public const ENVIRONMENT_OCC_COMMAND = 'occ config:system:set thematiq.environment --value=<environment>';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(appName: self::APP_ID);
	}//end __construct()

	/**
	 * Register services and providers.
	 *
	 * No bootstrap-time service registration is required for health: the
	 * `/api/health` endpoint is served by `Controller\HealthController`, which
	 * adopts the OpenRegister AppHost observability engine by COMPOSITION
	 * (ADR-040) — it resolves the engine out of the DI container by FQCN
	 * string at dispatch time and never names an OpenRegister class in a
	 * position the autoloader must resolve. OpenRegister is therefore a
	 * SOFT/optional dependency for health only: Nextcloud still boots,
	 * thematiq still themes, and every thematiq route still resolves when
	 * OpenRegister is absent — /api/health then degrades to
	 * `status: degraded` at HTTP 200 rather than 500ing the app. (It must NOT
	 * go back to `extends GenericHealthController`: NC's router
	 * ReflectionClass()es every file in lib/Controller/ while MATCHING any
	 * route, so an unresolvable parent 500s EVERY route — decidesk#377.)
	 * The declarative checks live in
	 * `src/manifest.json` and use only the OR-independent primitives
	 * (database, filesystem, appEnabled) — never orAvailable, and no OR-object
	 * metrics. The `Capabilities` class IS registered here — it is the app's
	 * first real `register()`-time registration — so the huisstijl is exposed
	 * on every capabilities document without any request-time cost.
	 *
	 * `ThemeInjectionListener` is registered for both `BeforeTemplateRenderedEvent`
	 * and `BeforeLoginTemplateRenderedEvent` — style injection is event-driven,
	 * not boot-driven (see `openspec/changes/render-event-injection`).
	 * `registerEventListener()` registers a lazy service: the listener (and its
	 * whole service graph — config, design system, custom overrides, fonts) is
	 * only instantiated when one of these two events actually fires, so
	 * requests that render no template (WebDAV, OCS/API, cron) never pay for
	 * it.
	 *
	 * @param IRegistrationContext $context The registration context.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/adopt-apphost-2026-06-16/tasks.md#task-2
	 * @spec openspec/changes/render-event-injection/tasks.md#task-3.1
	 * @spec openspec/specs/theming-capability/spec.md
	 */
	public function register(IRegistrationContext $context): void {
		// Load the app's composer autoloader so this app's classes are resolvable
		// process-wide, not only inside this app's own container — required so a
		// cross-app event listener (e.g. the OpenRegister federated-config type)
		// can be constructed by another app's dispatcher. Mirrors hermiq.
		//
		// Guarded, because an install without `vendor/` (a git checkout nobody
		// ran composer in) is legitimate: the app has no runtime composer
		// dependency and Nextcloud autoloads `lib/` itself. Unguarded, the
		// include_once logged two warnings on every request that booted the
		// app (thematiq#266).
		$autoloader = $this->composerAutoloader();
		if (is_file($autoloader) === true) {
			include_once $autoloader;
		}

		// Health endpoint served by Controller\HealthController, which drives
		// the AppHost engine by composition — no explicit registration needed.
		// Public huisstijl capability — see lib/Capabilities.php.
		$context->registerCapability(Capabilities::class);

		// Theme as code (openspec/specs/theme-as-code/spec.md): while
		// thematiq.config_source_lock is on, configuration setters answer 423.
		$context->registerMiddleware(ConfigSourceLockMiddleware::class);

		// Event-driven CSS injection — see lib/Listener/ThemeInjectionListener.php.
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, ThemeInjectionListener::class);
		$context->registerEventListener(BeforeLoginTemplateRenderedEvent::class, ThemeInjectionListener::class);

		// Federated configuration sharing (openregister): contribute the NL Design
		// theme as a shareable config type so a theme can be published to and
		// installed from GitHub over OpenRegister's one fleet mechanism.
		//
		// THE PRELUDE IS KEPT ON PURPOSE (ADR-040).
		//
		// `Coordinator::registerApps()` walks the SORTED app list, calling
		// `OC_App::registerAutoloading()` and then `register()` for one app at
		// a time. Under the app's OLD id, `nldesign` sorted BEFORE
		// `openregister`, so this method ran while the `OCA\OpenRegister\`
		// PSR-4 prefix did not yet exist — on a completely healthy instance
		// with OpenRegister installed and enabled — and the prelude was
		// strictly required.
		//
		// Without the prelude below, the `class_exists()` on the next line
		// answered FALSE every time, the listener was never registered, and
		// the app contributed NO shareable config type at all. Nothing
		// reported it: the app stayed enabled, every route resolved, the
		// theme still applied — the only symptom was that federated config
		// sharing (openspec/specs/federated-config-sharing/spec.md) silently
		// did nothing.
		//
		// Under the new id, `thematiq` sorts AFTER `openregister`, so the
		// `class_exists()` guard would usually answer TRUE on its own. The
		// prelude stays anyway: app sort order is not a contract to rely on
		// (it moves with the app id, and moved once already), and the call is
		// idempotent and cheap.
		//
		// See lib/AppInfo/OpenRegisterAutoloader.php: idempotent, swallows a
		// missing/disabled OpenRegister, and returns false in that case so the
		// guard below answers FALSE truthfully.
		(new OpenRegisterAutoloader())->ensure();

		if (class_exists(\OCA\OpenRegister\Service\Config\RegisterShareableConfigTypesEvent::class) === true) {
			$context->registerEventListener(
				\OCA\OpenRegister\Service\Config\RegisterShareableConfigTypesEvent::class,
				\OCA\Thematiq\Listener\ShareableConfigTypeListener::class
			);
		}
	}//end register()

	/**
	 * The path of this app's composer autoloader, which may not exist.
	 *
	 * Its own method so a test can point register() at a missing file.
	 *
	 * @return string The absolute path to `vendor/autoload.php`.
	 *
	 * @spec openspec/changes/adopt-apphost-2026-06-16/tasks.md#task-2
	 */
	protected function composerAutoloader(): string {
		return dirname(__DIR__, 2) . '/vendor/autoload.php';
	}//end composerAutoloader()

	/**
	 * Boot the application.
	 *
	 * Intentionally a no-op: `IBootstrap` requires the method, but style
	 * injection is entirely event-driven since
	 * `openspec/changes/render-event-injection` — see
	 * `lib/Listener/ThemeInjectionListener.php` and
	 * `lib/Service/CssInjectionService.php`.
	 *
	 * @param IBootContext $context The boot context (unused — kept only to satisfy the IBootstrap signature).
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) - IBootstrap::boot() mandates this exact signature
	 *
	 * @spec openspec/changes/render-event-injection/tasks.md#task-3.2
	 */
	public function boot(IBootContext $context): void {
	}//end boot()
}//end class
