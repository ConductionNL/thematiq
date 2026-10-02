<?php

declare(strict_types=1);

return [
	'routes' => [
		// Metrics — served by nldesign's own MetricsController (domain theme
		// metrics: token sets, custom overrides, theming syncs). Admin-only:
		// no #[PublicPage]/#[NoAdminRequired], so the SecurityMiddleware
		// default (admin session required) applies. A Prometheus scraper
		// must authenticate as an admin (e.g. an app password).
		['name' => 'metrics#index', 'url' => '/api/metrics', 'verb' => 'GET'],
		// Health — the `health#index` route name and `/api/health` URL are
		// unchanged, but the leaf HealthController service name is aliased to
		// the OpenRegister AppHost engine's GenericHealthController in
		// Application::register() (ADR-040). The engine reads the
		// observability.health block of src/manifest.json and owns the auth
		// posture (#[PublicPage] + #[NoCSRFRequired]) and the
		// {status, app, version, checks} contract.
		['name' => 'health#index', 'url' => '/api/health', 'verb' => 'GET'],

		// Non-admin, read-only token-set catalogue + shared contrast
		// evaluation (app-token-set-selection) — #[NoAdminRequired] on both
		// controller methods (authenticated non-admin user, not
		// #[PublicPage]); deliberately outside the /settings/* prefix this
		// app reserves for admin-gated routes, alongside metrics/health.
		['name' => 'catalog#tokenSets', 'url' => '/api/token-sets', 'verb' => 'GET'],
		['name' => 'assistantMark#show', 'url' => '/api/assistant-mark', 'verb' => 'GET'],
		// Token reference of one set, for signed-in users (openspec/specs/token-reference/spec.md).
		['name' => 'tokenReference#show', 'url' => '/api/token-sets/{id}/reference', 'verb' => 'GET'],
		['name' => 'contrast#evaluate', 'url' => '/api/contrast/evaluate', 'verb' => 'POST'],

		['name' => 'settings#getAvailableTokenSets', 'url' => '/settings/tokensets', 'verb' => 'GET'],
		['name' => 'settings#setTokenSet', 'url' => '/settings/tokenset', 'verb' => 'POST'],
		['name' => 'settings#getTokenSet', 'url' => '/settings/tokenset', 'verb' => 'GET'],
		// Planned token set switches (openspec/specs/scheduled-switch).
		['name' => 'scheduledSwitch#index', 'url' => '/settings/scheduled-switches', 'verb' => 'GET'],
		['name' => 'scheduledSwitch#create', 'url' => '/settings/scheduled-switches', 'verb' => 'POST'],
		['name' => 'scheduledSwitch#cancel', 'url' => '/settings/scheduled-switches/{id}', 'verb' => 'DELETE'],
		['name' => 'settings#setSloganSetting', 'url' => '/settings/slogan', 'verb' => 'POST'],
		['name' => 'settings#setMenuLabelsSetting', 'url' => '/settings/menulabels', 'verb' => 'POST'],
		['name' => 'settings#setPrimaryDrivesComponentsSetting', 'url' => '/settings/primary-drives-components', 'verb' => 'POST'],
		['name' => 'settings#setSaveConfirmSettings', 'url' => '/settings/save-confirmations', 'verb' => 'POST'],
		['name' => 'settings#getThemingValues', 'url' => '/settings/theming', 'verb' => 'GET'],
		['name' => 'settings#updateThemingValues', 'url' => '/settings/theming', 'verb' => 'POST'],
		// Resetting core theming to stock deliberately has NO route of its own:
		// it is `POST /settings/theming` with `reset=1`. Nextcloud caches the
		// route collection per host for an hour, so a brand-new path or verb
		// 404s/405s on every already-warm instance until that expires — measured
		// here as a "failed to apply" toast on a switch that had in fact
		// succeeded. Riding the URL that has existed for releases means the
		// reset works the moment the code lands.
		// Per-app theming exclusion list.
		['name' => 'settings#getAppTheming', 'url' => '/settings/app-theming', 'verb' => 'GET'],
		['name' => 'settings#setAppTheming', 'url' => '/settings/app-theming', 'verb' => 'POST'],
		// Upstream token freshness (opt-in daily background job status + dismissal).
		['name' => 'settings#getUpstreamFreshness', 'url' => '/settings/upstream-freshness', 'verb' => 'GET'],
		['name' => 'settings#setUpstreamFreshness', 'url' => '/settings/upstream-freshness', 'verb' => 'POST'],
		['name' => 'settings#dismissUpstreamNotice', 'url' => '/settings/upstream-freshness/dismiss', 'verb' => 'POST'],
		// Theme gallery (opt-in index of house styles; openspec/specs/theme-gallery/spec.md).
		['name' => 'gallery#index', 'url' => '/settings/gallery', 'verb' => 'GET'],
		['name' => 'gallery#setEnabled', 'url' => '/settings/gallery', 'verb' => 'POST'],
		['name' => 'gallery#install', 'url' => '/settings/gallery/{id}/install', 'verb' => 'POST'],
		// The playground's own components (authoring-own-markup-preview), admin-only.
		['name' => 'ownComponent#list', 'url' => '/settings/playground/components', 'verb' => 'GET'],
		['name' => 'ownComponent#save', 'url' => '/settings/playground/components', 'verb' => 'POST'],
		['name' => 'ownComponent#delete', 'url' => '/settings/playground/components/{slug}', 'verb' => 'DELETE'],
		['name' => 'overrides#getOverrides', 'url' => '/settings/overrides', 'verb' => 'GET'],
		['name' => 'overrides#setOverrides', 'url' => '/settings/overrides', 'verb' => 'POST'],
		// Import/export.
		// Freeform custom CSS — admin-authored arbitrary rules, sanitised
		// server-side before persisting. Separate from the token overrides
		// above: different trust profile, different audit actions.
		['name' => 'customCss#getCustomCss', 'url' => '/settings/custom-css', 'verb' => 'GET'],
		['name' => 'customCss#setCustomCss', 'url' => '/settings/custom-css', 'verb' => 'POST'],
		['name' => 'overrides#exportOverrides', 'url' => '/settings/overrides/export', 'verb' => 'GET'],
		['name' => 'overrides#importOverrides', 'url' => '/settings/overrides/import', 'verb' => 'POST'],
		// Token set preview for apply dialog.
		['name' => 'settings#getTokenSetPreview', 'url' => '/settings/tokenset-preview/{tokenSetId}', 'verb' => 'GET'],
		// Stylesheet layer manifest — which <link>/<style> elements a set puts
		// on a page, in cascade order, so the admin panel can swap sets on the
		// page it is on without a reload (apply-without-reload).
		['name' => 'layer#getStylesheets', 'url' => '/settings/tokenset-stylesheets/{tokenSetId}', 'verb' => 'GET'],
		// Custom token set upload lifecycle.
		['name' => 'customTokenSet#upload', 'url' => '/settings/tokensets/upload', 'verb' => 'POST'],
		['name' => 'brandForm#inputs', 'url' => '/settings/tokensets/from-colours', 'verb' => 'GET'],
		['name' => 'brandForm#create', 'url' => '/settings/tokensets/from-colours', 'verb' => 'POST'],
		['name' => 'customTokenSet#list', 'url' => '/settings/tokensets/custom', 'verb' => 'GET'],
		['name' => 'customTokenSet#export', 'url' => '/settings/tokensets/custom/{id}/export', 'verb' => 'GET'],
		['name' => 'customTokenSet#delete', 'url' => '/settings/tokensets/custom/{id}', 'verb' => 'DELETE'],
		// Active-configuration WCAG contrast compliance evidence report (download).
		['name' => 'settings#complianceReport', 'url' => '/settings/compliance-report', 'verb' => 'GET'],
		// Custom font upload lifecycle (admin-only).
		['name' => 'font#upload', 'url' => '/settings/fonts/upload', 'verb' => 'POST'],
		['name' => 'font#list', 'url' => '/settings/fonts', 'verb' => 'GET'],
		['name' => 'font#delete', 'url' => '/settings/fonts/{id}', 'verb' => 'DELETE'],
		// Font serving — deliberately public (#[PublicPage] + #[NoCSRFRequired]
		// on FontController::serve()/css()): CSS `url()` font loads carry no
		// CSRF token and must also work on the pre-login page before any
		// session exists.
		['name' => 'font#serve', 'url' => '/fonts/{id}.woff2', 'verb' => 'GET'],
		['name' => 'font#css', 'url' => '/fonts/css', 'verb' => 'GET'],
		// Runtime files (overrides, custom CSS, uploaded sets, captured images),
		// kept in app data so the signed app directory stays as shipped.
		// Public for the same reason as the font routes; the name must pass
		// RuntimeFileNames::isAllowed() (RuntimeFileController::serve()).
		['name' => 'runtimeFile#serve', 'url' => '/runtime/{name}', 'verb' => 'GET', 'requirements' => ['name' => '.+']],
		// Theming audit trail — admin-only (AuthorizedAdminSetting), no
		// #[PublicPage]/#[NoAdminRequired].
		['name' => 'audit#list', 'url' => '/settings/audit', 'verb' => 'GET'],
		['name' => 'audit#export', 'url' => '/settings/audit/export', 'verb' => 'GET'],
		// Kept configuration versions (openspec/specs/theme-versions/spec.md), admin-only.
		['name' => 'audit#versions', 'url' => '/settings/versions', 'verb' => 'GET'],
		['name' => 'audit#previewVersion', 'url' => '/settings/versions/{id}/preview', 'verb' => 'POST'],
		['name' => 'audit#restoreVersion', 'url' => '/settings/versions/{id}/restore', 'verb' => 'POST'],
		// Email template theming — admin toggle + compliance footer config.
		['name' => 'settings#getEmailTheming', 'url' => '/settings/email-theming', 'verb' => 'GET'],
		['name' => 'settings#setEmailTheming', 'url' => '/settings/email-theming', 'verb' => 'POST'],
		// Dark-mode variants — instance-wide admin toggle (openspec/specs/dark-mode/spec.md).
		['name' => 'settings#getDarkVariants', 'url' => '/settings/dark-variants', 'verb' => 'GET'],
		['name' => 'settings#setDarkVariants', 'url' => '/settings/dark-variants', 'verb' => 'POST'],
		// Marianne (French State typeface) admin acknowledgement gate — default
		// off; enabling it is the operator's affirmation of French-state
		// eligibility (openspec/specs/marianne-font/spec.md).
		['name' => 'settings#getMarianneEnabled', 'url' => '/settings/marianne', 'verb' => 'GET'],
		['name' => 'settings#setMarianneEnabled', 'url' => '/settings/marianne', 'verb' => 'POST'],
		// Complete configuration bundle (config-portability) — OTAP promotion
		// download/upload, admin-only (AuthorizedAdminSetting).
		['name' => 'configBundle#export', 'url' => '/settings/config/export', 'verb' => 'GET'],
		['name' => 'configBundle#import', 'url' => '/settings/config/import', 'verb' => 'POST'],
		['name' => 'configSource#status', 'url' => '/settings/config-source', 'verb' => 'GET'],
		// Theme preview ("proefdraaien") — per-session token set trial before
		// instance-wide publish. Admin-only (AuthorizedAdminSetting), no
		// #[PublicPage]/#[NoAdminRequired].
		['name' => 'preview#start', 'url' => '/settings/preview', 'verb' => 'POST'],
		['name' => 'preview#discard', 'url' => '/settings/preview', 'verb' => 'DELETE'],
		['name' => 'preview#publish', 'url' => '/settings/preview/publish', 'verb' => 'POST'],
		// Group theming — group-to-token-set mapping (multi-tenant huisstijl).
		['name' => 'settings#getGroupTheming', 'url' => '/settings/group-theming', 'verb' => 'GET'],
		['name' => 'settings#setGroupTheming', 'url' => '/settings/group-theming', 'verb' => 'POST'],
		['name' => 'assistantMark#settings', 'url' => '/settings/assistant-mark', 'verb' => 'GET'],
		['name' => 'assistantMark#save', 'url' => '/settings/assistant-mark', 'verb' => 'POST'],
		['name' => 'myGroups#index', 'url' => '/api/my-groups/house-style', 'verb' => 'GET'],
		['name' => 'myGroups#update', 'url' => '/api/my-groups/{group}/house-style', 'verb' => 'POST'],
	],
];
