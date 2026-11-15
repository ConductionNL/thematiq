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
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-55
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-56
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-57
 * @spec openspec/specs/admin-settings/spec.md#requirement-session-preview-controls
 * @spec openspec/specs/icon-packs/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Settings;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\EmailThemingService;
use OCA\Thematiq\Service\TokenSetSelectionPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\Settings\IDelegatedSettings;

/**
 * Admin settings form for NL Design.
 *
 * Provides the configuration interface for selecting design token sets.
 * Implements IDelegatedSettings so AuthorizedAdminSetting can reference this class.
 *
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-55
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-56
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-57
 */
class Admin implements IDelegatedSettings {

	/**
	 * Where the Documentation link goes for a design system without docs of its own.
	 *
	 * @var string
	 */
	public const DEFAULT_DOCUMENTATION_URL = 'https://thematiq.conduction.nl';

	/**
	 * The form state the template receives, in this order.
	 *
	 * @var array<int, string>
	 */
	private const TEMPLATE_STATE_KEYS = [
		'tokenSets', 'currentTokenSet', 'currentDesignSystem', 'hideSlogan', 'showMenuLabels', 'primaryDrivesComponents',
		'darkVariantsEnabled', 'marianneEnabled', 'emailThemingState', 'emailFooterConfig', 'occEnableCommand',
		'occDisableCommand', 'activePreview', 'activeIconPacks', 'iconPackSource',
	];

	/**
	 * The application configuration service.
	 *
	 * @var IConfig
	 */
	private IConfig $config;

	/**
	 * The services the form reads its state from.
	 *
	 * @var AdminFormServices
	 */
	private AdminFormServices $services;

	/**
	 * The initial state, the request and the playground boot state.
	 *
	 * @var AdminPageState
	 */
	private AdminPageState $page;

	/**
	 * The user session — resolves the requesting admin's uid for the
	 * active-preview row.
	 *
	 * @var IUserSession
	 */
	private IUserSession $userSession;

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The config service.
	 * @param IUserSession $userSession The user session.
	 * @param AdminFormServices $services The services the form reads its state from.
	 * @param AdminPageState $page The initial state, the request and the playground boot state.
	 */
	public function __construct(
		IConfig $config,
		IUserSession $userSession,
		AdminFormServices $services,
		AdminPageState $page,
	) {
		$this->config = $config;
		$this->userSession = $userSession;
		$this->services = $services;
		$this->page = $page;
	}//end __construct()

	/**
	 * Get the settings form.
	 *
	 * @return TemplateResponse The settings form template.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-55
	 * @spec openspec/specs/dark-mode/spec.md
	 * @spec openspec/specs/marianne-font/spec.md
	 */
	public function getForm(): TemplateResponse {
		$state = $this->formState();
		$this->publishBootState(state: $state);

		return new TemplateResponse(Application::APP_ID, 'settings/admin', $this->templateParams(state: $state));
	}//end getForm()

	/**
	 * Everything the form shows: the selectable sets, the current one and its design system, the
	 * flags, the e-mail theming, the signed-in admin's preview and the active icon packs.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/specs/admin-settings/spec.md
	 */
	private function formState(): array {
		// The PICKER, not the catalogue. See TokenSetSelectionPolicy.
		$tokenSets = $this->selectableTokenSets();
		$currentTokenSet = $this->config->getAppValue(Application::APP_ID, 'token_set', 'nextcloud');
		$state = ['tokenSets' => $tokenSets, 'currentTokenSet' => $currentTokenSet] + $this->flagState();
		$state['currentDesignSystem'] = $this->designSystemOf(tokenSets: $tokenSets, tokenSetId: $currentTokenSet);
		$state['emailThemingState'] = $this->services->emailTheming->getState();
		$state['emailFooterConfig'] = $this->services->emailTheming->getFooterConfig();
		$state['occEnableCommand'] = EmailThemingService::OCC_ENABLE_COMMAND;
		$state['occDisableCommand'] = EmailThemingService::OCC_DISABLE_COMMAND;
		$state['activePreview'] = $this->getActivePreviewForCurrentUser();
		[$state['activeIconPacks'], $state['iconPackSource']] = $this->getActiveIconPackIndicator(tokenSetId: $currentTokenSet);

		return $state;
	}//end formState()

	/**
	 * The admin's on/off choices.
	 *
	 * `primaryDrivesComponents`: whether the brand primary overrules every component token it used
	 * to drive. OFF by default, which costs nothing visually: with no per-component value stored,
	 * the component tokens already resolve to the brand primary. Turning it ON gives that back up.
	 * `confirmSaveStock`/`confirmSaveTheme`: whether Save overrides asks first. Both default ON: the
	 * first save is the one most worth a question, and the dialogs are the only place the "keep
	 * this as a new token set" choice is offered.
	 *
	 * @return array<string, bool>
	 *
	 * @spec openspec/specs/admin-settings/spec.md
	 */
	private function flagState(): array {
		return [
			'hideSlogan' => $this->isFlagOn(key: 'hide_slogan'),
			'showMenuLabels' => $this->isFlagOn(key: 'show_menu_labels'),
			'primaryDrivesComponents' => $this->isFlagOn(key: 'primary_drives_components'),
			'confirmSaveStock' => $this->isFlagOn(key: 'confirm_save_stock', fallback: '1'),
			'confirmSaveTheme' => $this->isFlagOn(key: 'confirm_save_theme', fallback: '1'),
			'darkVariantsEnabled' => $this->isFlagOn(key: 'dark_variants', fallback: '1'),
			'marianneEnabled' => $this->isFlagOn(key: 'marianne_enabled'),
		];
	}//end flagState()

	/**
	 * The design system behind a token set, from the already-fetched inventory.
	 *
	 * It gates the Marianne notice's server-rendered visibility; js/admin.js re-derives it
	 * client-side on selection change from the same `data-design-system` option attribute.
	 *
	 * @param array<int, array<string, mixed>> $tokenSets The selectable sets.
	 * @param string $tokenSetId The current set.
	 *
	 * @return string `nldesign` when the set is not among them.
	 *
	 * @spec openspec/specs/admin-settings/spec.md
	 */
	private function designSystemOf(array $tokenSets, string $tokenSetId): string {
		foreach ($tokenSets as $tokenSet) {
			if ($tokenSet['id'] === $tokenSetId) {
				return $tokenSet['design_system'];
			}
		}

		return 'nldesign';
	}//end designSystemOf()

	/**
	 * Publish the server state js/admin.js needs at boot over the canonical channel (ADR-004).
	 *
	 * The save-confirmation flags go through initial state rather than the template because the two
	 * controls they drive are rendered by js/admin.js inside the token editor.
	 *
	 * @param array<string, mixed> $state The form state.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/admin-settings/spec.md
	 */
	private function publishBootState(array $state): void {
		foreach (['tokenSets', 'currentTokenSet', 'confirmSaveStock', 'confirmSaveTheme'] as $key) {
			$this->page->initialState->provideInitialState($key, $state[$key]);
		}

		$this->publishPreviewState(activePreview: $state['activePreview'], iconPackSource: $state['iconPackSource']);
		$this->publishPlaygroundState(currentTokenSet: $state['currentTokenSet'], activePreview: $state['activePreview']);
	}//end publishBootState()

	/**
	 * The template parameters: the form state, the mock switch, the environment marker, the
	 * layout choices and the documentation link.
	 *
	 * `?mock=1` layers the theming-makeover visual shells (js/admin-mock.js, css/admin-mock.css)
	 * over the real panel so they can be screenshotted from a running instance.
	 *
	 * @param array<string, mixed> $state The form state.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/specs/admin-settings/spec.md
	 */
	private function templateParams(array $state): array {
		$params = [];
		foreach (self::TEMPLATE_STATE_KEYS as $key) {
			$params[$key] = $state[$key];
		}

		$params['mockUi'] = ($this->page->request->getParam('mock') === '1');

		return $params + $this->environmentParams() + $this->layoutParams() + $this->documentationParams(designSystem: $state['currentDesignSystem']);
	}//end templateParams()

	/**
	 * The administrator's own layout choices, as stored. An empty value means
	 * the option follows the active token set.
	 *
	 * Read straight from the app config, under the two keys
	 * `LayoutOptionsService` owns, rather than through that service: this
	 * class is at PHPMD's coupling limit, and the template needs only the raw
	 * strings. It selects "Follow the theme" for any value it does not know,
	 * which is how the service reads one too.
	 *
	 * @return array{
	 *     workplaceLayout: string,
	 *     brandStripe: string,
	 *     navigationWidth: string,
	 *     navigationActiveStyle: string,
	 *     brandStripePlacement: string,
	 *     loginWatermark: string,
	 *     headerStyle: string
	 * } The template parameters.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-the-workplace-layout-is-an-admin-option
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/brand-stripe/spec.md#requirement-the-brand-stripe-is-an-admin-option
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md#requirement-the-navigation-width-is-an-admin-option
	 */
	private function layoutParams(): array {
		return [
			'workplaceLayout' => $this->config->getAppValue(Application::APP_ID, 'workplace_layout', ''),
			'brandStripe' => $this->config->getAppValue(Application::APP_ID, 'brand_stripe', ''),
			'navigationWidth' => $this->config->getAppValue(Application::APP_ID, 'navigation_width', ''),
			'navigationActiveStyle' => $this->config->getAppValue(Application::APP_ID, 'navigation_active_style', ''),
			'brandStripePlacement' => $this->config->getAppValue(Application::APP_ID, 'brand_stripe_placement', ''),
			'loginWatermark' => $this->config->getAppValue(Application::APP_ID, 'login_watermark', ''),
			'headerStyle' => $this->config->getAppValue(Application::APP_ID, 'header_style', ''),
		];
	}//end layoutParams()

	/**
	 * The environment config.php declares, shown read-only, and the command
	 * that sets it.
	 *
	 * @return array{environment: string, environmentCommand: string} The template parameters.
	 *
	 * @spec openspec/specs/environment-marker/spec.md
	 */
	private function environmentParams(): array {
		return [
			'environment' => strtolower(trim($this->config->getSystemValueString(Application::ENVIRONMENT_CONFIG_KEY, ''))),
			'environmentCommand' => Application::ENVIRONMENT_OCC_COMMAND,
		];
	}//end environmentParams()

	/**
	 * The Documentation link for the current design system, as a template
	 * parameter, and every design system's link for js/admin.js.
	 *
	 * A design system names its docs in `documentation_url` in
	 * design-systems.json; one that names none gets this app's docs. The link
	 * follows the design system of the set picked in the dropdown, so the
	 * script needs every design system's link, not only the current one's (#662).
	 *
	 * @param string $designSystem The current token set's design system.
	 *
	 * @return array{documentationUrl: string} The template parameter.
	 *
	 * @spec openspec/specs/admin-settings/spec.md#requirement-documentation-link-follows-the-design-system
	 */
	private function documentationParams(string $designSystem): array {
		$urls = [];
		foreach ($this->services->designSystems->getDesignSystems() as $id => $entry) {
			$url = ($entry['documentation_url'] ?? '');
			// Only an https link: the manifest must not put a javascript: URL in the header.
			if (is_string($url) === false || str_starts_with($url, 'https://') === false) {
				$url = self::DEFAULT_DOCUMENTATION_URL;
			}

			$urls[(string)$id] = $url;
		}

		$this->page->initialState->provideInitialState('designSystemDocs', $urls);

		return ['documentationUrl' => ($urls[$designSystem] ?? self::DEFAULT_DOCUMENTATION_URL)];
	}//end documentationParams()

	/**
	 * Reads one on/off appconfig flag.
	 *
	 * Every toggle on this panel is stored the same way — the string '1' or
	 * '0' under the app id — and getForm() read five of them as five identical
	 * five-line blocks. One name for that shape keeps the method at the length
	 * phpmd allows and keeps the default visible at the call site, which is the
	 * part that differs between them.
	 *
	 * @param string $key The appconfig key.
	 * @param string $fallback The value to assume when the key was never set.
	 *
	 * @return bool Whether the flag is on.
	 */
	private function isFlagOn(string $key, string $fallback = '0'): bool {
		return ($this->config->getAppValue(Application::APP_ID, $key, $fallback) === '1');
	}//end isFlagOn()

	/**
	 * Publish the two initial-state keys the preview banner and the icon-pack
	 * indicator read.
	 *
	 * `activePreview` is normalised to an empty array rather than passed as
	 * null: `provideInitialState(null)` writes "Failed to provideInitialState"
	 * to the log and provides nothing at all, so the key would be absent,
	 * `loadState` would return its fallback, and the panel would look correct
	 * while the server logged a warning on every admin page load. An empty
	 * array is the same "no preview" fact in a shape the service carries.
	 *
	 * @param array<string, mixed>|null $activePreview The active session preview, or null when there is none.
	 * @param string $iconPackSource Whether the pack list came from the design system or an override.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theme-preview/spec.md
	 * @spec openspec/specs/icon-packs/spec.md
	 */
	private function publishPreviewState(?array $activePreview, string $iconPackSource): void {
		$this->page->initialState->provideInitialState('activePreview', ($activePreview ?? []));
		$this->page->initialState->provideInitialState('iconPackSource', $iconPackSource);
	}//end publishPreviewState()

	/**
	 * Publish what the component playground reads at boot.
	 *
	 * The instrument (js/playground.js) rebuilds the token editor below into a
	 * selector / stage / tokens view. Everything it shows is data published
	 * here — the script decides none of it.
	 *
	 * The set it describes is the one the page is WEARING, so a session
	 * preview wins over the persisted set: an admin previewing a set and
	 * opening a component must be shown the set in front of them.
	 *
	 * Keys are forwarded rather than named here so the service stays the one
	 * place that decides what the instrument is given.
	 *
	 * @param string $currentTokenSet The persisted token set id.
	 * @param array<string, mixed>|null $activePreview The active session preview, or null when there is none.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
	 */
	private function publishPlaygroundState(string $currentTokenSet, ?array $activePreview): void {
		$playgroundSet = $currentTokenSet;
		if ($activePreview !== null) {
			$playgroundSet = $activePreview['tokenSet'];
		}

		foreach ($this->page->playground->getInitialState(tokenSetId: $playgroundSet) as $key => $value) {
			$this->page->initialState->provideInitialState($key, $value);
		}
	}//end publishPlaygroundState()

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
		$activeIconPacks = $this->services->designSystems->resolveActiveIconPacks(tokenSetId: $tokenSetId);

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

		$preview = $this->services->previews->getActivePreview(uid: $uid);
		if ($preview === null) {
			return null;
		}

		$meta = $this->services->tokenSets->getAvailableTokenSets();
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
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-56
	 */
	public function getSection(): string {
		return 'theming';
	}//end getSection()

	/**
	 * Get the priority for ordering in the settings menu.
	 *
	 * @return int The priority value (lower = higher priority).
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-57
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
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-55
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
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-55
	 * @spec openspec/specs/upstream-freshness/spec.md
	 * @spec openspec/specs/per-group-theming/spec.md
	 * @spec openspec/specs/marianne-font/spec.md
	 * @spec openspec/specs/platform-properties/spec.md#REQ-TPLAT-008
	 */
	public function getAuthorizedAppConfig(): array {
		return [
			Application::APP_ID => [
				'/token_set/',
				'/hide_slogan/',
				'/show_menu_labels/',
				'/workplace_layout/',
				'/brand_stripe/',
				'/navigation_width/',
				'/navigation_active_style/',
				'/brand_stripe_placement/',
				'/login_watermark/',
				'/header_style/',
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
	/**
	 * The sets the picker offers: every named shipped set the vocabulary audit
	 * passes, plus the admin's own imports. See {@see TokenSetSelectionPolicy}.
	 *
	 * @return array<int, array<string, mixed>> The selectable token sets.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	private function selectableTokenSets(): array {
		return (new TokenSetSelectionPolicy())->selectable(
			tokenSets: $this->services->tokenSets,
			config: $this->config,
			appPath: $this->services->tokenSets->getAppPath()
		);
	}//end selectableTokenSets()

}//end class
