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
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-14
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-15
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-16
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-17
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-18
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-19
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-20
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-21
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-22
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-23
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-24
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-25
 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\AppThemingService;
use OCA\Thematiq\Service\ComplianceReportService;
use OCA\Thematiq\Service\EmailThemingService;
use OCA\Thematiq\Service\Exception\ConfigReadOnlyException;
use OCA\Thematiq\Service\Exception\FooterValidationException;
use OCA\Thematiq\Service\Exception\ForeignMailTemplateClassException;
use OCA\Thematiq\Service\Exception\GroupThemingValidationException;
use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Service\TokenSetPreviewService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\UpstreamFreshnessService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IConfig;
use OCP\IRequest;

/**
 * Settings controller for NL Design app.
 *
 * Handles API requests for managing token sets, theming, and display settings.
 * Override-related endpoints are handled by OverridesController.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-14
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-15
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-16
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-17
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-18
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-19
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-20
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-21
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-22
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-23
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-24
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-25
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
	 * The application configuration service.
	 *
	 * @var IConfig
	 */
	private IConfig $config;

	/**
	 * The token set service.
	 *
	 * @var TokenSetService
	 */
	private TokenSetService $tokenSetService;

	/**
	 * The theming service.
	 *
	 * @var ThemingService
	 */
	private ThemingService $themingService;

	/**
	 * The token set preview service.
	 *
	 * @var TokenSetPreviewService
	 */
	private TokenSetPreviewService $previewService;

	/**
	 * The per-app theming service.
	 *
	 * @var AppThemingService
	 */
	private AppThemingService $appThemingService;

	/**
	 * The compliance evidence report service.
	 *
	 * @var ComplianceReportService
	 */
	private ComplianceReportService $complianceService;

	/**
	 * The theming audit trail service.
	 *
	 * @var ThemingAuditService
	 */
	private ThemingAuditService $auditService;

	/**
	 * The email theming service.
	 *
	 * @var EmailThemingService
	 */
	private EmailThemingService $emailThemingService;

	/**
	 * The upstream token freshness service.
	 *
	 * @var UpstreamFreshnessService
	 */
	private UpstreamFreshnessService $freshnessService;

	/**
	 * The group theming mapping/resolution service.
	 *
	 * @var GroupThemingService
	 */
	private GroupThemingService $groupThemingService;

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request object.
	 * @param IConfig $config The config service.
	 * @param TokenSetService $tokenSetService The token set service.
	 * @param ThemingService $themingService The theming service.
	 * @param TokenSetPreviewService $previewService The token set preview service.
	 * @param AppThemingService $appThemingService The per-app theming service.
	 * @param ComplianceReportService $complianceService The compliance evidence report service.
	 * @param ThemingAuditService $auditService The theming audit trail service.
	 * @param EmailThemingService $emailThemingService The email theming service.
	 * @param UpstreamFreshnessService $freshnessService The upstream token freshness service.
	 * @param GroupThemingService $groupThemingService The group theming mapping/resolution service.
	 *
	 * @SuppressWarnings(PHPMD.ExcessiveParameterList) - This is the app's aggregating settings
	 * controller; each dependency backs one settings endpoint family (token set, theming sync,
	 * per-app theming, compliance report, audit trail, email theming, upstream freshness, group
	 * theming). NC's DI container supplies them; a synthetic parameter-object split would not
	 * reduce the real coupling.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		IConfig $config,
		TokenSetService $tokenSetService,
		ThemingService $themingService,
		TokenSetPreviewService $previewService,
		AppThemingService $appThemingService,
		ComplianceReportService $complianceService,
		ThemingAuditService $auditService,
		EmailThemingService $emailThemingService,
		UpstreamFreshnessService $freshnessService,
		GroupThemingService $groupThemingService,
	) {
		parent::__construct(appName: $appName, request: $request);
		$this->config = $config;
		$this->tokenSetService = $tokenSetService;
		$this->themingService = $themingService;
		$this->previewService = $previewService;
		$this->appThemingService = $appThemingService;
		$this->complianceService = $complianceService;
		$this->auditService = $auditService;
		$this->emailThemingService = $emailThemingService;
		$this->freshnessService = $freshnessService;
		$this->groupThemingService = $groupThemingService;
	}//end __construct()

	/**
	 * Set the active design token set.
	 *
	 * @param string $tokenSet The token set name.
	 *
	 * @return JSONResponse The response with status and selected token set.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-14
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function setTokenSet(string $tokenSet): JSONResponse {
		if ($this->tokenSetService->isValidTokenSet(tokenSetId: $tokenSet) === false) {
			return new JSONResponse(['error' => 'Invalid token set'], 400);
		}

		$previous = $this->config->getAppValue(Application::APP_ID, 'token_set', 'nextcloud');
		$this->config->setAppValue(Application::APP_ID, 'token_set', $tokenSet);

		$this->auditService->log(
			action: 'token_set_changed',
			context: [
				'old' => $previous,
				'new' => $tokenSet,
			]
		);

		return new JSONResponse(['status' => 'ok', 'tokenSet' => $tokenSet]);
	}//end setTokenSet()

	/**
	 * Get the currently active design token set.
	 *
	 * @return JSONResponse The response with the current token set.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-15
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function getTokenSet(): JSONResponse {
		$tokenSet = $this->config->getAppValue(
			Application::APP_ID,
			'token_set',
			'nextcloud'
		);

		return new JSONResponse(['tokenSet' => $tokenSet]);
	}//end getTokenSet()

	/**
	 * Get all available token sets.
	 *
	 * @return JSONResponse The list of available token sets.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-16
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function getAvailableTokenSets(): JSONResponse {
		// Feeds the admin dropdown, so it is the SELECTABLE list, not the full
		// catalogue — see TokenSetService::SELECTABLE_SHIPPED_SETS. The public
		// catalogue (CatalogController) still answers with everything shipped.
		$tokenSets = $this->tokenSetService->getSelectableTokenSets();

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
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-17
	 */
	private function saveBooleanSetting(string $key, bool $value): void {
		$stored = '0';
		if ($value === true) {
			$stored = '1';
		}

		$this->config->setAppValue(Application::APP_ID, $key, $stored);
	}//end saveBooleanSetting()

	/**
	 * Set the hide slogan setting.
	 *
	 * @param bool $hideSlogan Whether to hide the slogan on login page.
	 *
	 * @return JSONResponse The response with the status.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-18
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function setSloganSetting(bool $hideSlogan): JSONResponse {
		$previous = ($this->config->getAppValue(Application::APP_ID, 'hide_slogan', '0') === '1');
		$this->saveBooleanSetting(key: 'hide_slogan', value: $hideSlogan);

		$this->auditService->log(
			action: 'toggle_changed',
			context: [
				'key' => 'hide_slogan',
				'old' => $previous,
				'new' => $hideSlogan,
			]
		);

		return new JSONResponse(['status' => 'ok', 'hideSlogan' => $hideSlogan]);
	}//end setSloganSetting()

	/**
	 * Set the show menu labels setting.
	 *
	 * @param bool $showMenuLabels Whether to show text labels in app menu.
	 *
	 * @return JSONResponse The response with the status.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-19
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function setMenuLabelsSetting(bool $showMenuLabels): JSONResponse {
		$previous = ($this->config->getAppValue(Application::APP_ID, 'show_menu_labels', '0') === '1');
		$this->saveBooleanSetting(key: 'show_menu_labels', value: $showMenuLabels);

		$this->auditService->log(
			action: 'toggle_changed',
			context: [
				'key' => 'show_menu_labels',
				'old' => $previous,
				'new' => $showMenuLabels,
			]
		);

		return new JSONResponse(['status' => 'ok', 'showMenuLabels' => $showMenuLabels]);
	}//end setMenuLabelsSetting()

	/**
	 * Update Nextcloud theming values from NL Design tokens.
	 *
	 * @return JSONResponse The response with updated fields.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-22
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

		$colorError = $this->themingService->validateColors(params: $params);
		if ($colorError !== null) {
			return new JSONResponse(['error' => $colorError], 400);
		}

		$imageError = $this->themingService->validateImagePaths(params: $params);
		if ($imageError !== null) {
			return new JSONResponse(['error' => $imageError], 400);
		}

		$before = $this->buildThemingSnapshot();

		$updatedColors = $this->themingService->applyColors(params: $params);
		$updatedImages = $this->themingService->applyImages(params: $params);
		$updated = array_merge($updatedColors, $updatedImages);

		// Remember WHICH image was synced. Core stores only that a custom
		// image exists (`{key}Mime`), never which file it came from, so
		// without this the sync dialog cannot tell "this set's logo is
		// already the one in place" from "this set has a logo" — and it
		// offered the same unchanged logo on every single apply.
		foreach ($updatedImages as $imageKey) {
			$this->config->setAppValue(
				Application::APP_ID,
				self::SYNCED_IMAGE_PREFIX . $imageKey,
				(string)$params[$imageKey]
			);
		}

		// Increment the theming sync counter exposed by MetricsController as
		// nldesign_theming_syncs_total. Only counted on success (after both
		// apply* calls completed without throwing).
		$current = (int)$this->config->getAppValue(Application::APP_ID, 'theming_syncs_total', '0');
		$this->config->setAppValue(Application::APP_ID, 'theming_syncs_total', (string)($current + 1));

		$this->auditService->log(
			action: 'theming_sync_applied',
			context: [
				'old' => $before,
				'new' => $updated,
			]
		);

		return new JSONResponse(['status' => 'ok', 'updated' => $updated]);
	}//end updateThemingValues()

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
		$before = $this->buildThemingSnapshot();
		$reset = $this->themingService->resetToDefaults();

		// Nothing is synced any more, so nothing is remembered as synced.
		foreach (['logo', 'background'] as $imageKey) {
			$this->config->deleteAppValue(Application::APP_ID, self::SYNCED_IMAGE_PREFIX . $imageKey);
		}

		$this->auditService->log(
			action: 'theming_sync_reset',
			context: [
				'old' => $before,
				'new' => $reset,
			]
		);

		return new JSONResponse(['status' => 'ok', 'reset' => $reset]);
	}//end resetThemingValues()

	/**
	 * Get current Nextcloud theming values for comparison.
	 *
	 * @return JSONResponse The current theming values.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-23
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function getThemingValues(): JSONResponse {
		$values = $this->buildThemingSnapshot();

		return new JSONResponse($values);
	}//end getThemingValues()

	/**
	 * Build a snapshot of the current theming state.
	 *
	 * @return array<string, mixed> The theming snapshot.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-24
	 */
	private function buildThemingSnapshot(): array {
		$imgManager = $this->themingService->getImageManager();

		$defaults = $this->themingService->getDefaultColors();

		return [
			'primary_color' => $this->config->getAppValue('theming', 'primary_color', ''),
			'background_color' => $this->config->getAppValue('theming', 'background_color', ''),
			'logo_url' => $imgManager->getImageUrl('logo'),
			'background_url' => $imgManager->getImageUrl('background'),
			'has_custom_logo' => $imgManager->hasImage('logo'),
			'has_custom_background' => $imgManager->hasImage('background'),
			// What a reset lands on, so the stock set's dialog can show it
			// before it is applied rather than guess.
			'default_primary_color' => $defaults['primary_color'],
			'default_background_color' => $defaults['background_color'],
			// Which file the sync last put in each image slot, so the dialog
			// can leave out a logo that is already the one in place.
			'synced_logo' => $this->config->getAppValue(
				Application::APP_ID,
				self::SYNCED_IMAGE_PREFIX . 'logo',
				''
			),
			'synced_background' => $this->config->getAppValue(
				Application::APP_ID,
				self::SYNCED_IMAGE_PREFIX . 'background',
				''
			),
		];
	}//end buildThemingSnapshot()

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
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-25
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function getTokenSetPreview(string $tokenSetId): JSONResponse {
		if ($this->tokenSetService->isValidTokenSet(tokenSetId: $tokenSetId) === false) {
			return new JSONResponse(['error' => 'Token set not found'], 404);
		}

		$resolved = $this->previewService->getResolvedColors(tokenSetId: $tokenSetId);

		return new JSONResponse(['tokenSetId' => $tokenSetId, 'resolved' => $resolved]);
	}//end getTokenSetPreview()

	/**
	 * List enabled apps with their per-app theming state.
	 *
	 * @return JSONResponse The list of { id, name, themed } entries.
	 *
	 * @spec openspec/changes/per-app-theming-toggle/tasks.md#task-3.1
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function getAppTheming(): JSONResponse {
		return new JSONResponse(['apps' => $this->appThemingService->getThemableApps()]);
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
	 * @spec openspec/changes/per-app-theming-toggle/tasks.md#task-3.1
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function setAppTheming(array $disabledApps = []): JSONResponse {
		$before = $this->appThemingService->getDisabledApps();
		$this->appThemingService->setDisabledApps(appIds: $disabledApps);
		$after = $this->appThemingService->getDisabledApps();

		$this->auditService->log(
			action: 'app_exclusions_changed',
			context: [
				'old' => $before,
				'new' => $after,
			]
		);

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
		return new JSONResponse($this->freshnessService->getStatus());
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
		$this->freshnessService->setEnabled(enabled: $enabled);

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
		$this->freshnessService->dismiss(setId: $setId, versionOrSha: $version);

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

		$tokenSetId = $this->config->getAppValue(Application::APP_ID, 'token_set', 'nextcloud');
		$instanceId = $this->config->getSystemValue('instanceid', 'unknown');
		$date = gmdate('Ymd');

		$extension = 'json';
		$contentType = 'application/json';
		$content = $this->complianceService->renderJson();
		if ($format === 'markdown') {
			$extension = 'md';
			$contentType = 'text/markdown';
			$content = $this->complianceService->renderMarkdown();
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
		return new JSONResponse(
			[
				'state' => $this->emailThemingService->getState(),
				'footer' => $this->emailThemingService->getFooterConfig(),
				'occEnable' => EmailThemingService::OCC_ENABLE_COMMAND,
				'occDisable' => EmailThemingService::OCC_DISABLE_COMMAND,
			]
		);
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
		try {
			$this->emailThemingService->setFooterConfig($orgName, $accessibilityUrl, $privacyUrl);
		} catch (FooterValidationException $e) {
			return new JSONResponse(
				[
					'error' => 'invalid_footer',
					'field' => $e->getField(),
					'message' => $e->getMessage(),
				],
				422
			);
		}

		try {
			if ($enabled === true) {
				$this->emailThemingService->enable();
			}

			if ($enabled === false) {
				$this->emailThemingService->disable();
			}
		} catch (ConfigReadOnlyException $e) {
			return new JSONResponse(
				[
					'error' => 'config_read_only',
					'occEnable' => $e->getOccEnableCommand(),
					'occDisable' => $e->getOccDisableCommand(),
					'footer' => $this->emailThemingService->getFooterConfig(),
				],
				409
			);
		} catch (ForeignMailTemplateClassException $e) {
			return new JSONResponse(
				[
					'error' => 'foreign_mail_template_class',
					'class' => $e->getForeignClass(),
					'footer' => $this->emailThemingService->getFooterConfig(),
				],
				409
			);
		}//end try

		return new JSONResponse(
			[
				'status' => 'ok',
				'state' => $this->emailThemingService->getState(),
				'footer' => $this->emailThemingService->getFooterConfig(),
			]
		);
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
		$enabled = ($this->config->getAppValue(Application::APP_ID, 'dark_variants', '1') === '1');

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
		$previous = ($this->config->getAppValue(Application::APP_ID, 'dark_variants', '1') === '1');
		$this->saveBooleanSetting(key: 'dark_variants', value: $enabled);

		$this->auditService->log(
			action: 'toggle_changed',
			context: [
				'key' => 'dark_variants',
				'old' => $previous,
				'new' => $enabled,
			]
		);

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
		$enabled = ($this->config->getAppValue(Application::APP_ID, 'marianne_enabled', '0') === '1');

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
		$previous = ($this->config->getAppValue(Application::APP_ID, 'marianne_enabled', '0') === '1');
		$this->saveBooleanSetting(key: 'marianne_enabled', value: $enabled);

		$this->auditService->log(
			action: 'toggle_changed',
			context: [
				'key' => 'marianne_enabled',
				'old' => $previous,
				'new' => $enabled,
			]
		);

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
				'mapping' => $this->groupThemingService->getMapping(),
				'groups' => $this->groupThemingService->getAvailableGroups(),
				// Also a picker. `getSelectableTokenSets()` keeps any set an
				// existing mapping already points at, so narrowing this list
				// can never hide a group's current theme.
				'tokenSets' => $this->tokenSetService->getSelectableTokenSets(),
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
		$before = $this->groupThemingService->getMapping();

		try {
			$after = $this->groupThemingService->setMapping(entries: $mapping);
		} catch (GroupThemingValidationException $e) {
			return new JSONResponse(
				[
					'error' => 'invalid_mapping',
					'entry' => $e->getEntry(),
					'reason' => $e->getReason(),
				],
				422
			);
		}

		$this->auditService->log(
			action: 'group_theming_changed',
			context: [
				'old' => $before,
				'new' => $after,
			]
		);

		return new JSONResponse(
			[
				'status' => 'ok',
				'mapping' => $after,
			]
		);
	}//end setGroupTheming()
}//end class
