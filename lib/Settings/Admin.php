<?php

/**
 * NL Design Admin Settings.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Settings
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-55
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-56
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-57
 * @spec openspec/specs/admin-settings/spec.md#requirement-session-preview-controls
 * @spec openspec/specs/icon-packs/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Settings;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\EmailThemingService;
use OCA\Thematiq\Service\ThemePreviewService;
use OCA\Thematiq\Service\TokenSetService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Settings\IDelegatedSettings;

/**
 * Admin settings form for NL Design.
 *
 * Provides the configuration interface for selecting design token sets.
 * Implements IDelegatedSettings so AuthorizedAdminSetting can reference this class.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-55
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-56
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-57
 */
class Admin implements IDelegatedSettings {

	/**
	 * The application configuration service.
	 *
	 * @var IConfig
	 */
	private IConfig $config;

	/**
	 * The localization service (kept for future i18n use).
	 *
	 * @var IL10N
	 */
	private IL10N $l;

	/**
	 * The token set service.
	 *
	 * @var TokenSetService
	 */
	private TokenSetService $tokenSetService;

	/**
	 * The email theming service.
	 *
	 * @var EmailThemingService
	 */
	private EmailThemingService $emailThemingService;

	/**
	 * The theme preview service (drives the active-preview row).
	 *
	 * @var ThemePreviewService
	 */
	private ThemePreviewService $previewService;

	/**
	 * The user session — resolves the requesting admin's uid for the
	 * active-preview row.
	 *
	 * @var IUserSession
	 */
	private IUserSession $userSession;

	/**
	 * Resolves the active icon pack (icon-packs spec) for the read-only
	 * admin indicator.
	 *
	 * @var DesignSystemService
	 */
	private DesignSystemService $designSystemService;

	/**
	 * Carries server-side state to js/admin.js.
	 *
	 * The panel used to serialise this state into `data-*` attributes on the
	 * `#nldesign-settings` div and have admin.js read it back with
	 * `getAttribute()`. That is the pattern ADR-004 rules out: it breaks on
	 * CSP-hardened instances, and it breaks SILENTLY — any markup change that
	 * moves or renames the carrying element leaves admin.js parsing an empty
	 * string and rendering as though the server had sent nothing at all.
	 *
	 * @var IInitialState
	 */
	private IInitialState $initialState;

	/**
	 * The current request — read only for the `?mock=1` switch that layers the
	 * presentation mock (js/admin-mock.js) over the panel.
	 *
	 * @var IRequest
	 */
	private IRequest $request;

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The config service.
	 * @param IL10N $l The localization service.
	 * @param TokenSetService $tokenSetService The token set service.
	 * @param EmailThemingService $emailThemingService The email theming service.
	 * @param ThemePreviewService $previewService The theme preview service.
	 * @param IUserSession $userSession The user session.
	 * @param DesignSystemService $designSystemService Resolves the active icon pack.
	 * @param IInitialState $initialState Carries server state to admin.js.
	 * @param IRequest $request The current request (presentation-mock switch).
	 */
	public function __construct(
		IConfig $config,
		IL10N $l,
		TokenSetService $tokenSetService,
		EmailThemingService $emailThemingService,
		ThemePreviewService $previewService,
		IUserSession $userSession,
		DesignSystemService $designSystemService,
		IInitialState $initialState,
		IRequest $request,
	) {
		$this->config = $config;
		$this->l = $l;
		$this->tokenSetService = $tokenSetService;
		$this->emailThemingService = $emailThemingService;
		$this->previewService = $previewService;
		$this->userSession = $userSession;
		$this->designSystemService = $designSystemService;
		$this->initialState = $initialState;
		$this->request = $request;
	}//end __construct()

	/**
	 * Get the settings form.
	 *
	 * @return TemplateResponse The settings form template.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-55
	 * @spec openspec/specs/dark-mode/spec.md
	 * @spec openspec/specs/marianne-font/spec.md
	 */
	public function getForm(): TemplateResponse {
		// The PICKER, not the catalogue: only stock plus the admin's own
		// imports. See TokenSetService::SELECTABLE_SHIPPED_SETS.
		$tokenSets = $this->tokenSetService->getSelectableTokenSets();

		$currentTokenSet = $this->config->getAppValue(
			Application::APP_ID,
			'token_set',
			'nextcloud'
		);

		$hideSlogan = $this->config->getAppValue(
			Application::APP_ID,
			'hide_slogan',
			'0'
		) === '1';

		$showMenuLabels = $this->config->getAppValue(
			Application::APP_ID,
			'show_menu_labels',
			'0'
		) === '1';

		$darkVariantsEnabled = $this->config->getAppValue(
			Application::APP_ID,
			'dark_variants',
			'1'
		) === '1';

		$marianneEnabled = $this->config->getAppValue(
			Application::APP_ID,
			'marianne_enabled',
			'0'
		) === '1';

		// The design system backing the current token set — resolved from the
		// already-fetched $tokenSets inventory (TokenSetService surfaces
		// `design_system` per entry), so no new service dependency is needed
		// just to gate the Marianne notice's initial server-rendered
		// visibility (js/admin.js re-derives it client-side on selection
		// change from the same `data-design-system` option attribute).
		$currentDesignSystem = 'nldesign';
		foreach ($tokenSets as $tokenSet) {
			if ($tokenSet['id'] === $currentTokenSet) {
				$currentDesignSystem = $tokenSet['design_system'];
				break;
			}
		}

		$emailThemingState = $this->emailThemingService->getState();
		$emailFooterConfig = $this->emailThemingService->getFooterConfig();

		$activePreview = $this->getActivePreviewForCurrentUser();

		[$activeIconPacks, $iconPackSource] = $this->getActiveIconPackIndicator(tokenSetId: $currentTokenSet);

		// Server state that js/admin.js needs at boot, carried over the
		// canonical channel (ADR-004) rather than `data-*` attributes on the
		// rendered markup. The template still receives these values for the
		// parts it renders server-side; what changed is where the SCRIPT
		// reads them from.
		$this->initialState->provideInitialState('tokenSets', $tokenSets);
		$this->initialState->provideInitialState('currentTokenSet', $currentTokenSet);
		// `?? []` and not `$activePreview` on its own. Nextcloud's
		// InitialStateService accepts a scalar, an array, or a
		// JsonSerializable — and NULL IS NONE OF THOSE. It does not throw on
		// one: it writes `Invalid activePreview data provided to
		// provideInitialState by nldesign` to the log and provides nothing at
		// all. The key would then simply be absent, `loadState` would return
		// its fallback, and because that fallback is also null the panel
		// would look correct while the server logged a warning on every
		// admin page load. An empty array is the same "no preview" fact in a
		// shape the service actually carries.
		$this->initialState->provideInitialState('activePreview', ($activePreview ?? []));
		$this->initialState->provideInitialState('iconPackSource', $iconPackSource);

		return new TemplateResponse(
			Application::APP_ID,
			'settings/admin',
			[
				'tokenSets' => $tokenSets,
				'currentTokenSet' => $currentTokenSet,
				'currentDesignSystem' => $currentDesignSystem,
				'hideSlogan' => $hideSlogan,
				'showMenuLabels' => $showMenuLabels,
				'darkVariantsEnabled' => $darkVariantsEnabled,
				'marianneEnabled' => $marianneEnabled,
				'emailThemingState' => $emailThemingState,
				'emailFooterConfig' => $emailFooterConfig,
				'occEnableCommand' => EmailThemingService::OCC_ENABLE_COMMAND,
				'occDisableCommand' => EmailThemingService::OCC_DISABLE_COMMAND,
				'activePreview' => $activePreview,
				'activeIconPacks' => $activeIconPacks,
				'iconPackSource' => $iconPackSource,
				// Presentation mock: `?mock=1` layers the theming-makeover visual shells
				// (js/admin-mock.js, css/admin-mock.css) over the real panel so they
				// can be screenshotted from a running instance. Nothing else changes.
				'mockUi' => ($this->request->getParam('mock') === '1'),
			]
		);
	}//end getForm()

	/**
	 * Resolve the read-only "active icon pack" indicator: the resolved
	 * ordered pack list for the currently persisted token set, and whether
	 * that resolution came from the design system default or an admin
	 * override (`openspec/specs/icon-packs/spec.md`).
	 *
	 * @param string $tokenSetId The currently persisted (instance-wide) token set id.
	 *
	 * @return array{0: string[], 1: 'design-system'|'override'} `[activeIconPacks, iconPackSource]`.
	 *
	 * @spec openspec/specs/icon-packs/spec.md
	 */
	private function getActiveIconPackIndicator(string $tokenSetId): array {
		$activeIconPacks = $this->designSystemService->resolveActiveIconPacks(tokenSetId: $tokenSetId);

		$override = trim($this->config->getAppValue(Application::APP_ID, 'icon_pack', ''));

		$iconPackSource = 'design-system';
		if ($override !== '' && $activeIconPacks === [$override]) {
			$iconPackSource = 'override';
		}

		return [$activeIconPacks, $iconPackSource];
	}//end getActiveIconPackIndicator()

	/**
	 * Resolve the requesting admin's active preview (name + token set id), or
	 * null when they have none — drives the settings panel's active-preview
	 * row (admin-settings spec: Session Preview Controls).
	 *
	 * @return array{tokenSet: string, name: string}|null The active preview, or null.
	 *
	 * @spec openspec/specs/admin-settings/spec.md#requirement-session-preview-controls
	 */
	private function getActivePreviewForCurrentUser(): ?array {
		$uid = $this->userSession->getUser()?->getUID();
		if ($uid === null) {
			return null;
		}

		$preview = $this->previewService->getActivePreview(uid: $uid);
		if ($preview === null) {
			return null;
		}

		$meta = $this->tokenSetService->getAvailableTokenSets();
		$name = $preview['tokenSet'];
		foreach ($meta as $tokenSet) {
			if ($tokenSet['id'] === $preview['tokenSet']) {
				$name = $tokenSet['name'];
				break;
			}
		}

		return [
			'tokenSet' => $preview['tokenSet'],
			'name' => $name,
		];
	}//end getActivePreviewForCurrentUser()

	/**
	 * Get the settings section identifier.
	 *
	 * @return string The section identifier (theming).
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-56
	 */
	public function getSection(): string {
		return 'theming';
	}//end getSection()

	/**
	 * Get the priority for ordering in the settings menu.
	 *
	 * @return int The priority value (lower = higher priority).
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-57
	 */
	public function getPriority(): int {
		return 50;
	}//end getPriority()

	/**
	 * Get the display name for this delegated settings section.
	 *
	 * Returns null so only the section name is displayed (no sub-name).
	 *
	 * @return string|null The settings display name, or null.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-55
	 */
	public function getName(): ?string {
		return null;
	}//end getName()

	/**
	 * Get the list of authorized app config keys this setting may modify.
	 *
	 * Returns the nldesign app config keys that admin-delegated users
	 * are allowed to modify through this settings panel.
	 *
	 * @return array<string, string[]> The authorized app config map.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-55
	 * @spec openspec/specs/upstream-freshness/spec.md
	 * @spec openspec/specs/per-group-theming/spec.md
	 * @spec openspec/specs/marianne-font/spec.md
	 */
	public function getAuthorizedAppConfig(): array {
		return [
			Application::APP_ID => [
				'/token_set/',
				'/hide_slogan/',
				'/show_menu_labels/',
				'/dark_variants/',
				'/marianne_enabled/',
				'/disabled_apps/',
				'/theming_syncs_total/',
				// Per-group theming mapping (openspec/specs/per-group-theming/spec.md):
				// the ordered group->token-set mapping and its cache-invalidation
				// generation counter.
				'/group_token_sets/',
				'/group_token_sets_generation/',
				// Upstream token freshness — only the two admin-initiated
				// config keys (the opt-in toggle and dismissal state); the
				// job-internal ETag/head-SHA/checked-at/notices keys are never
				// written through this delegated-admin surface.
				'/upstream_freshness_enabled/',
				'/upstream_freshness_dismissed/',
				'/email_footer_org_name/',
				'/email_footer_accessibility_url/',
				'/email_footer_privacy_url/',
			],
		];
	}//end getAuthorizedAppConfig()
}//end class
