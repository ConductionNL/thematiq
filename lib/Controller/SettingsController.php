<?php

/**
 * NL Design Settings Controller.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Controller
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-14
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-15
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-16
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-17
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-18
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-19
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-20
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-21
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-22
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-23
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-24
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-25
 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\ComplianceReportService;
use OCA\Thematiq\Service\CoreThemingState;
use OCA\Thematiq\Service\Exception\GroupThemingValidationException;
use OCA\Thematiq\Service\Exception\RestrictedSetNotConfirmedException;
use OCA\Thematiq\Service\SettingsConfigServices;
use OCA\Thematiq\Service\SettingsReportServices;
use OCA\Thematiq\Service\SettingsTokenSetServices;
use OCA\Thematiq\Service\TokenSetSelectionPolicy;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;

/**
 * Settings controller for NL Design app.
 *
 * Handles API requests for managing token sets, theming, and display settings.
 * Override-related endpoints are handled by OverridesController.
 *
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-14
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-15
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-16
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-17
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-18
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-19
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-20
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-21
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-22
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-23
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-24
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-25
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) - This controller aggregates every settings
 * endpoint of the app (token set, toggles, theming sync, per-app theming, audit trail, email
 * theming); each dependency is one endpoint's service. Splitting it is tracked implicitly by
 * the per-feature OpenSpec changes, not worth a synthetic split today.
 */
class SettingsController extends Controller {

	/**
	 * Appconfig key prefix recording which image file the theming sync last
	 * applied to a core image slot (`synced_logo`, `synced_background`).
	 *
	 * Core stores only THAT a custom image exists (`{key}Mime`), never which
	 * file it came from, so this is the only way the sync dialog can tell an
	 * already-applied logo from one that would change.
	 *
	 * @var string
	 */
	public const SYNCED_IMAGE_PREFIX = 'synced_';

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request object.
	 * @param SettingsConfigServices $settings The config, the audit trail, per-app theming and group theming.
	 * @param SettingsTokenSetServices $tokenSets The token sets, the preview, the active set and core theming.
	 * @param SettingsReportServices $reports The compliance report, the e-mail theming settings and the freshness check.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly SettingsConfigServices $settings,
		private readonly SettingsTokenSetServices $tokenSets,
		private readonly SettingsReportServices $reports,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * Set the active design token set.
	 *
	 * @param string $tokenSet The token set name.
	 *
	 * @return JSONResponse The response with status and selected token set.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-14
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 * @spec openspec/changes/archive/2026-09-30-apply-scheduled-theme-switch/tasks.md#task-1.1
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function setTokenSet(string $tokenSet): JSONResponse {
		try {
			$this->tokenSets->activeTokenSet->switchTo(tokenSet: $tokenSet);
		} catch (RestrictedSetNotConfirmedException $e) {
			return new JSONResponse(['error' => RestrictedSetNotConfirmedException::ERROR, 'tokenSet' => $e->getTokenSetId()], 403);
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(['error' => 'Invalid token set'], 400);
		}

		return new JSONResponse(['status' => 'ok', 'tokenSet' => $tokenSet]);
	}//end setTokenSet()

	/**
	 * Get all available token sets.
	 *
	 * @return JSONResponse The list of available token sets.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-16
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function getAvailableTokenSets(): JSONResponse {
		// Feeds the admin dropdown, so it is the SELECTABLE list, not the full
		// catalogue — see TokenSetSelectionPolicy::selectable(), which
		// offers every named shipped set the vocabulary audit passes. The public
		// catalogue (CatalogController) still answers with everything shipped,
		// including the shared role layer that has no manifest entry.
		$tokenSets = (new TokenSetSelectionPolicy())->selectable(
			tokenSets: $this->tokenSets->tokenSetService,
			config: $this->settings->config,
			appPath: $this->tokenSets->tokenSetService->getAppPath()
		);

		return new JSONResponse(['tokenSets' => $tokenSets]);
	}//end getAvailableTokenSets()

	/**
	 * Store a boolean app setting as '0' or '1'.
	 *
	 * @param string $key The app config key.
	 * @param bool $value The boolean value.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-17
	 */
	private function saveBooleanSetting(string $key, bool $value): void {
		$stored = '0';
		if ($value === true) {
			$stored = '1';
		}

		$this->settings->config->setAppValue(Application::APP_ID, $key, $stored);
	}//end saveBooleanSetting()

	/**
	 * Audit a changed toggle as a `toggle_changed` entry.
	 *
	 * @param string $key The app config key of the toggle.
	 * @param array{0: mixed, 1: mixed} $change The value before and after.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	private function auditToggle(string $key, array $change): void {
		$this->settings->auditService->log(
			action: 'toggle_changed',
			context: [
				'key' => $key,
				'old' => $change[0],
				'new' => $change[1],
			]
		);
	}//end auditToggle()

	/**
	 * Audit a change as an entry with the state before and after.
	 *
	 * @param string $action The audit action.
	 * @param array{0: mixed, 1: mixed} $change The state before and after.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	private function auditChange(string $action, array $change): void {
		$this->settings->auditService->log(
			action: $action,
			context: [
				'old' => $change[0],
				'new' => $change[1],
			]
		);
	}//end auditChange()

	/**
	 * Set the hide slogan setting.
	 *
	 * @param bool $hideSlogan Whether to hide the slogan on login page.
	 *
	 * @return JSONResponse The response with the status.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-18
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 * @spec openspec/specs/platform-properties/spec.md#REQ-TPLAT-006
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function setSloganSetting(bool $hideSlogan): JSONResponse {
		$previous = ($this->settings->config->getAppValue(Application::APP_ID, 'hide_slogan', '0') === '1');
		$this->saveBooleanSetting(key: 'hide_slogan', value: $hideSlogan);

		$this->auditToggle(key: 'hide_slogan', change: [$previous, $hideSlogan]);

		return new JSONResponse(['status' => 'ok', 'hideSlogan' => $hideSlogan]);
	}//end setSloganSetting()

	/**
	 * Set whether the brand primary overrules the component tokens it used to drive.
	 *
	 * Turning this on emits `css/primary-lock.css`, which forces every component
	 * token flagged `primary` in `scripts/mapping/component-tokens.json` back to
	 * the brand value. Stored per-component values are left untouched, so turning
	 * it off restores them.
	 *
	 * @param bool $primaryDrivesComponents Whether the primary overrules component tokens.
	 *
	 * @return JSONResponse The response with the status.
	 *
	 * @spec openspec/specs/component-tokens/spec.md
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 *
	 * @SuppressWarnings(PHPMD.LongVariable) - the parameter name IS the wire contract: Nextcloud binds it from the JSON body key the
	 * admin panel posts, so shortening it to satisfy a length rule would rename the API field.
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function setPrimaryDrivesComponentsSetting(bool $primaryDrivesComponents): JSONResponse {
		$previous = ($this->settings->config->getAppValue(Application::APP_ID, 'primary_drives_components', '0') === '1');
		$this->saveBooleanSetting(key: 'primary_drives_components', value: $primaryDrivesComponents);

		$this->auditToggle(key: 'primary_drives_components', change: [$previous, $primaryDrivesComponents]);

		return new JSONResponse(
			['status' => 'ok', 'primaryDrivesComponents' => $primaryDrivesComponents]
		);
	}//end setPrimaryDrivesComponentsSetting()

	/**
	 * Set whether Save overrides asks for confirmation first.
	 *
	 * Two flags, because the two questions are not the same one. On the stock
	 * `nextcloud` set there is a real choice to make — keep the edits as a new
	 * token set, or write them over the running Nextcloud theme — and on any
	 * other set there is only a confirmation. An admin who has stopped wanting
	 * one has not necessarily stopped wanting the other.
	 *
	 * Both default to ON. The dialogs offer "do not ask again", and the pair of
	 * controls under the token editor turns them back on, so the choice is never
	 * one-way.
	 *
	 * @param bool $confirmSaveStock Whether to ask when the stock set is active.
	 * @param bool $confirmSaveTheme Whether to ask when a token set is active.
	 *
	 * @return JSONResponse The response with the status.
	 *
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function setSaveConfirmSettings(bool $confirmSaveStock, bool $confirmSaveTheme): JSONResponse {
		$this->saveConfirmation(key: 'confirm_save_stock', value: $confirmSaveStock);
		$this->saveConfirmation(key: 'confirm_save_theme', value: $confirmSaveTheme);

		return new JSONResponse(
			[
				'status' => 'ok',
				'confirmSaveStock' => $confirmSaveStock,
				'confirmSaveTheme' => $confirmSaveTheme,
			]
		);
	}//end setSaveConfirmSettings()

	/**
	 * Store one save confirmation flag (default on) and audit it, when it changed.
	 *
	 * @param string $key The app config key of the flag.
	 * @param bool $value Whether to ask.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	private function saveConfirmation(string $key, bool $value): void {
		$previous = ($this->settings->config->getAppValue(Application::APP_ID, $key, '1') === '1');
		if ($previous === $value) {
			return;
		}

		$this->saveBooleanSetting(key: $key, value: $value);
		$this->auditToggle(key: $key, change: [$previous, $value]);
	}//end saveConfirmation()

	/**
	 * Set the show menu labels setting.
	 *
	 * @param bool $showMenuLabels Whether to show text labels in app menu.
	 *
	 * @return JSONResponse The response with the status.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-19
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function setMenuLabelsSetting(bool $showMenuLabels): JSONResponse {
		$previous = ($this->settings->config->getAppValue(Application::APP_ID, 'show_menu_labels', '0') === '1');
		$this->saveBooleanSetting(key: 'show_menu_labels', value: $showMenuLabels);

		$this->auditToggle(key: 'show_menu_labels', change: [$previous, $showMenuLabels]);

		return new JSONResponse(['status' => 'ok', 'showMenuLabels' => $showMenuLabels]);
	}//end setMenuLabelsSetting()

	/**
	 * Update Nextcloud theming values from NL Design tokens.
	 *
	 * @return JSONResponse The response with updated fields.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-22
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function updateThemingValues(): JSONResponse {
		$params = $this->request->getParams();

		// `reset=1` means stock Nextcloud: undo everything the sync ever
		// applied. It shares this endpoint rather than taking a path or verb
		// of its own because Nextcloud caches the route collection per host
		// for an hour — a new route 404s on every warm instance until then.
		if (($params['reset'] ?? '') !== '' && ($params['reset'] ?? '') !== '0') {
			return $this->resetThemingValues();
		}

		$refusal = $this->themingInputRefusal(params: $params);
		if ($refusal !== null) {
			return $refusal;
		}

		$state = $this->coreThemingState();
		$before = $state->snapshot();

		$updated = $this->applyTheming(params: $params, state: $state);

		// A SECOND SNAPSHOT, not $updated. The audit service diffs `old`
		// against `new` to fill the entry's `changed` list, and that only
		// means anything when both sides are the same shape. $updated is a
		// LIST of the field names that were written; $before is a keyed
		// snapshot. Diffing a map's keys against a list's indices reported
		// every one of the snapshot's fields as changed on every sync, plus
		// the integers 0, 1, 2 — so the one field a reader relies on to see
		// what a sync actually did was noise. $updated is still what the
		// response carries; the audit entry gets before-and-after.
		$after = $state->snapshot();

		$this->auditChange(action: 'theming_sync_applied', change: [$before, $after]);

		return new JSONResponse(['status' => 'ok', 'updated' => $updated]);
	}//end updateThemingValues()

	/**
	 * Apply the colours and images, remember which images were synced and count the sync.
	 *
	 * @param array<string, mixed> $params The request parameters.
	 * @param CoreThemingState $state Core theming's state, which records the synced images and the count.
	 *
	 * @return array<int, string> The fields that were written, colours first.
	 *
	 * @spec openspec/specs/theming-sync/spec.md
	 */
	private function applyTheming(array $params, CoreThemingState $state): array {
		$updatedColors = $this->tokenSets->themingService->applyColors(params: $params);
		$updatedImages = $this->tokenSets->themingService->applyImages(params: $params);
		$updated = array_merge($updatedColors, $updatedImages);

		$state->rememberSyncedImages(imageKeys: $updatedImages, params: $params);
		$state->countSync();

		return $updated;
	}//end applyTheming()

	/**
	 * The 400 answer to an invalid colour or image path, checked before anything is applied.
	 *
	 * @param array<string, mixed> $params The request parameters.
	 *
	 * @return JSONResponse|null The refusal, or null when both are valid.
	 *
	 * @spec openspec/specs/theming-sync/spec.md
	 */
	private function themingInputRefusal(array $params): ?JSONResponse {
		$colorError = $this->tokenSets->themingService->validateColors(params: $params);
		if ($colorError !== null) {
			return new JSONResponse(['error' => $colorError], 400);
		}

		$imageError = $this->tokenSets->themingService->validateImagePaths(params: $params);
		if ($imageError !== null) {
			return new JSONResponse(['error' => $imageError], 400);
		}

		return null;
	}//end themingInputRefusal()

	/**
	 * Reset Nextcloud theming to its defaults — what selecting the stock
	 * `nextcloud` set means for core's colours and logo.
	 *
	 * The sync used to "match" the stock set's manifest values (a stale
	 * primary and no logo), which kept the previous set's logo in place after
	 * switching back to stock. Stock is the ABSENCE of a synced theme, so this
	 * undoes every setting the sync can write, through the same
	 * `ThemingDefaults::undo()` core's own panel uses.
	 *
	 * Reached through `POST /settings/theming` with `reset=1` rather than a
	 * route of its own — see the caller for why — so it is admin-gated by that
	 * endpoint's own `AuthorizedAdminSetting` and needs no second attribute.
	 *
	 * @return JSONResponse `{status: "ok", reset: string[]}`.
	 *
	 * @spec openspec/changes/apply-without-reload/specs/theming-sync/spec.md
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	private function resetThemingValues(): JSONResponse {
		$state = $this->coreThemingState();
		$before = $state->snapshot();
		$reset = $this->tokenSets->themingService->resetToDefaults();

		// Nothing is synced any more, so nothing is remembered as synced.
		$state->forgetSyncedImages();

		$this->auditChange(action: 'theming_sync_reset', change: [$before, $reset]);

		return new JSONResponse(['status' => 'ok', 'reset' => $reset]);
	}//end resetThemingValues()

	/**
	 * Get current Nextcloud theming values for comparison.
	 *
	 * @return JSONResponse The current theming values.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-23
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function getThemingValues(): JSONResponse {
		$values = $this->coreThemingState()->snapshot();

		return new JSONResponse($values);
	}//end getThemingValues()

	/**
	 * Core theming's current state and the images the sync put in place.
	 *
	 * @return CoreThemingState The state, read through this controller's config and theming service.
	 */
	private function coreThemingState(): CoreThemingState {
		return new CoreThemingState(config: $this->settings->config, theming: $this->tokenSets->themingService);
	}//end coreThemingState()

	/**
	 * Get resolved --color-* values for a given token set.
	 *
	 * Used by the apply dialog to compare current resolved values against what
	 * a token set would produce, without applying anything to the CSS stack.
	 *
	 * @param string $tokenSetId The token set identifier.
	 *
	 * @return JSONResponse The resolved color map.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-25
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function getTokenSetPreview(string $tokenSetId): JSONResponse {
		if ($this->tokenSets->tokenSetService->isValidTokenSet(tokenSetId: $tokenSetId) === false) {
			return new JSONResponse(['error' => 'Token set not found'], 404);
		}

		$resolved = $this->tokenSets->previewService->getResolvedColors(tokenSetId: $tokenSetId);

		return new JSONResponse(['tokenSetId' => $tokenSetId, 'resolved' => $resolved]);
	}//end getTokenSetPreview()

	/**
	 * List enabled apps with their per-app theming state.
	 *
	 * @return JSONResponse The list of { id, name, themed } entries.
	 *
	 * @spec openspec/changes/archive/2026-06-14-per-app-theming-toggle/tasks.md#task-3.1
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function getAppTheming(): JSONResponse {
		return new JSONResponse(['apps' => $this->settings->appThemingService->getThemableApps()]);
	}//end getAppTheming()

	/**
	 * Replace the per-app theming exclusion list.
	 *
	 * Accepts { disabledApps: string[] }. Unknown and protected ids are dropped
	 * by the service before persisting.
	 *
	 * @param array $disabledApps The app ids to exclude from theming.
	 *
	 * @return JSONResponse The persisted state after validation.
	 *
	 * @spec openspec/changes/archive/2026-06-14-per-app-theming-toggle/tasks.md#task-3.1
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function setAppTheming(array $disabledApps = []): JSONResponse {
		$before = $this->settings->appThemingService->getDisabledApps();
		$this->settings->appThemingService->setDisabledApps(appIds: $disabledApps);
		$after = $this->settings->appThemingService->getDisabledApps();

		$this->auditChange(action: 'app_exclusions_changed', change: [$before, $after]);

		return new JSONResponse(
			[
				'status' => 'ok',
				'disabledApps' => $after,
			]
		);
	}//end setAppTheming()

	/**
	 * Get the upstream token freshness status: whether the opt-in check is
	 * enabled, the last-checked timestamp, and any notices still visible
	 * after dismissal filtering.
	 *
	 * @return JSONResponse The status payload.
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function getUpstreamFreshness(): JSONResponse {
		return new JSONResponse($this->reports->freshnessService->getStatus());
	}//end getUpstreamFreshness()

	/**
	 * Enable or disable the upstream token freshness check. This is the
	 * app's first outbound network egress and defaults to disabled — enabling
	 * it takes effect on the next daily background job run.
	 *
	 * @param bool $enabled Whether the check should be enabled.
	 *
	 * @return JSONResponse The response with the persisted state.
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function setUpstreamFreshness(bool $enabled): JSONResponse {
		$this->reports->freshnessService->setEnabled(enabled: $enabled);

		return new JSONResponse(['status' => 'ok', 'enabled' => $enabled]);
	}//end setUpstreamFreshness()

	/**
	 * Dismiss an upstream freshness notice for a set at a specific
	 * version/SHA marker. A later detection carrying a different marker for
	 * the same set re-surfaces regardless of this dismissal.
	 *
	 * @param string $setId The token set id, or the generic notice key.
	 * @param string $version The version (or SHA) marker being dismissed.
	 *
	 * @return JSONResponse The response with the status.
	 *
	 * @spec openspec/specs/upstream-freshness/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function dismissUpstreamNotice(string $setId, string $version): JSONResponse {
		$this->reports->freshnessService->dismiss(setId: $setId, versionOrSha: $version);

		return new JSONResponse(['status' => 'ok']);
	}//end dismissUpstreamNotice()

	/**
	 * Export the active-configuration WCAG contrast compliance evidence report.
	 *
	 * Color-contrast evidence for theme tokens only — NOT a WCAG-EM audit and
	 * NOT a full WCAG evaluation (see ComplianceReportService::SCOPE_STATEMENT).
	 * Served as a download so it can be attached directly to a
	 * toegankelijkheidsverklaring evidence package.
	 *
	 * @param string $format The requested format: "json" (default) or "markdown".
	 *
	 * @return Response The report download, or a 400 JSON error for an unknown format.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function complianceReport(string $format = 'json'): Response {
		if ($format !== 'json' && $format !== 'markdown') {
			return new JSONResponse(['error' => 'Unknown format. Use "json" or "markdown".'], 400);
		}

		$tokenSetId = $this->settings->config->getAppValue(Application::APP_ID, 'token_set', 'nextcloud');
		$instanceId = $this->settings->config->getSystemValue('instanceid', 'unknown');
		$date = gmdate('Ymd');

		$extension = 'json';
		$contentType = 'application/json';
		$content = $this->reports->complianceService->renderJson();
		if ($format === 'markdown') {
			$extension = 'md';
			$contentType = 'text/markdown';
			$content = $this->reports->complianceService->renderMarkdown();
		}

		$filename = sprintf('nldesign-compliance-%s-%s-%s.%s', $instanceId, $tokenSetId, $date, $extension);

		return new DataDownloadResponse(data: $content, filename: $filename, contentType: $contentType);
	}//end complianceReport()

	/**
	 * Get the email template toggle state and compliance footer config.
	 *
	 * @return JSONResponse The state, footer config, and manual occ commands.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function getEmailTheming(): JSONResponse {
		return new JSONResponse($this->reports->emailTheming->read());
	}//end getEmailTheming()

	/**
	 * Save the compliance footer config and toggle the email template.
	 *
	 * The footer config is always applied first (app config, always
	 * writable) and independently reported, so it saves successfully even
	 * when the system-config toggle write fails (read-only config.php or a
	 * foreign `mail_template_class`).
	 *
	 * @param bool $enabled Whether the branded template should be enabled.
	 * @param string $orgName The organization name.
	 * @param string $accessibilityUrl The toegankelijkheidsverklaring URL.
	 * @param string $privacyUrl The privacy statement URL.
	 *
	 * @return JSONResponse The result, or a structured 409/422 error.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function setEmailTheming(
		bool $enabled,
		string $orgName = '',
		string $accessibilityUrl = '',
		string $privacyUrl = '',
	): JSONResponse {
		$answer = $this->reports->emailTheming->save(
			enabled: $enabled,
			footer: ['orgName' => $orgName, 'accessibilityUrl' => $accessibilityUrl, 'privacyUrl' => $privacyUrl]
		);

		return new JSONResponse($answer['body'], $answer['status']);
	}//end setEmailTheming()

	/**
	 * Get the instance-wide dark-mode variants toggle state.
	 *
	 * @return JSONResponse The `{ enabled }` state (default enabled).
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function getDarkVariants(): JSONResponse {
		$enabled = ($this->settings->config->getAppValue(Application::APP_ID, 'dark_variants', '1') === '1');

		return new JSONResponse(['enabled' => $enabled]);
	}//end getDarkVariants()

	/**
	 * Set the instance-wide dark-mode variants toggle. Disabling stops the
	 * generated dark stylesheet from loading (see
	 * `Application::injectThemeCSS()`) without deleting the generated files.
	 *
	 * @param bool $enabled Whether dark-mode variants should be active.
	 *
	 * @return JSONResponse The response with the persisted state.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function setDarkVariants(bool $enabled): JSONResponse {
		$previous = ($this->settings->config->getAppValue(Application::APP_ID, 'dark_variants', '1') === '1');
		$this->saveBooleanSetting(key: 'dark_variants', value: $enabled);

		$this->auditToggle(key: 'dark_variants', change: [$previous, $enabled]);

		return new JSONResponse(['status' => 'ok', 'enabled' => $enabled]);
	}//end setDarkVariants()

	/**
	 * Get the Marianne (French State typeface) acknowledgement gate state.
	 *
	 * @return JSONResponse The `{ enabled }` state (default disabled).
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function getMarianneEnabled(): JSONResponse {
		$enabled = ($this->settings->config->getAppValue(Application::APP_ID, 'marianne_enabled', '0') === '1');

		return new JSONResponse(['enabled' => $enabled]);
	}//end getMarianneEnabled()

	/**
	 * Set the Marianne acknowledgement gate. Enabling it is the operator's
	 * affirmation that their organisation is a French State agency
	 * (administration de l'État) entitled to use the Marianne typeface (see
	 * AGREEMENT-MARIANNE.md) — `CssInjectionService` only emits the
	 * self-hosted `@font-face Marianne` layer while this flag is `'1'` AND
	 * the active design system is `lasuite`. Disabling it reverts every
	 * subsequent render to Inter without deleting the bundled font files.
	 *
	 * @param bool $enabled Whether the Marianne gate should be active.
	 *
	 * @return JSONResponse The response with the persisted state.
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function setMarianneEnabled(bool $enabled): JSONResponse {
		$previous = ($this->settings->config->getAppValue(Application::APP_ID, 'marianne_enabled', '0') === '1');
		$this->saveBooleanSetting(key: 'marianne_enabled', value: $enabled);

		$this->auditToggle(key: 'marianne_enabled', change: [$previous, $enabled]);

		return new JSONResponse(['status' => 'ok', 'enabled' => $enabled]);
	}//end setMarianneEnabled()

	/**
	 * Get the group theming mapping plus the picker option lists.
	 *
	 * @return JSONResponse The ordered mapping, available groups, and available token sets.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function getGroupTheming(): JSONResponse {
		return new JSONResponse(
			[
				'mapping' => $this->settings->groupThemingService->getMapping(),
				'groups' => $this->settings->groupThemingService->getAvailableGroups(),
				// Also a picker. The policy keeps any set an
				// existing mapping already points at, so narrowing this list
				// can never hide a group's current theme.
				'tokenSets' => (new TokenSetSelectionPolicy())->selectable(
					tokenSets: $this->tokenSets->tokenSetService,
					config: $this->settings->config,
					appPath: $this->tokenSets->tokenSetService->getAppPath()
				),
			]
		);
	}//end getGroupTheming()

	/**
	 * Replace the full ordered group theming mapping.
	 *
	 * Accepts `{ mapping: {group, tokenSet}[] }` in priority order. On
	 * validation failure the save is rejected wholesale (HTTP 422 naming the
	 * offending entry and reason) and nothing is persisted.
	 *
	 * @param array $mapping The desired ordered mapping.
	 *
	 * @return JSONResponse The persisted mapping, or a 422 validation error.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function setGroupTheming(array $mapping = []): JSONResponse {
		$before = $this->settings->groupThemingService->getMapping();

		try {
			$after = $this->settings->groupThemingService->setMapping(entries: $mapping);
		} catch (GroupThemingValidationException $e) {
			return $this->mappingRefusal(e: $e);
		}

		$this->auditChange(action: 'group_theming_changed', change: [$before, $after]);

		return new JSONResponse(
			[
				'status' => 'ok',
				'mapping' => $after,
			]
		);
	}//end setGroupTheming()

	/**
	 * The answer to a refused group mapping: 403 for a restricted set nobody
	 * confirmed, 422 naming the entry and the reason for anything else.
	 *
	 * @param GroupThemingValidationException $e The refusal.
	 *
	 * @return JSONResponse The error.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	private function mappingRefusal(GroupThemingValidationException $e): JSONResponse {
		if ($e->getRestrictedSet() !== null) {
			return new JSONResponse(
				[
					'error' => RestrictedSetNotConfirmedException::ERROR,
					'tokenSet' => $e->getRestrictedSet(),
					'entry' => $e->getEntry(),
				],
				403
			);
		}

		return new JSONResponse(
			[
				'error' => 'invalid_mapping',
				'entry' => $e->getEntry(),
				'reason' => $e->getReason(),
			],
			422
		);
	}//end mappingRefusal()
}//end class
