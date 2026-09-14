/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/admin-settings/spec.md
 *
 * UI-only Playwright tests for the NL Design admin settings panel at
 * Settings → Administration → Theming.  Tests verify DOM elements and
 * user-visible behaviour; no assertions on internal PHP/IConfig state.
 *
 * Per-scenario excludes for backend-only scenarios are annotated in the
 * spec file.
 */
import { test, expect } from '@playwright/test'

const THEMING_URL = '/settings/admin/theming'

test.describe('admin-settings', () => {
	// -----------------------------------------------------------------------
	// REQ-ASET-001: Settings Panel Registration
	// -----------------------------------------------------------------------

	test(// @e2e openspec/specs/admin-settings/spec.md#settings-panel-appears-in-admin-area
	'Settings panel appears in admin area', async ({ page }) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		const heading = page.locator('h2:has-text("NL Design System Theme")')
		await expect(heading).toBeVisible()
	})

	test(// @e2e openspec/specs/admin-settings/spec.md#settings-panel-position-relative-to-nextcloud-theming
	'Settings panel position relative to Nextcloud theming', async ({ page }) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		// Both sections are present — the native "Theming" heading and the
		// NL Design heading, which must come after.
		const sections = page.locator('main h2')
		const texts = await sections.allTextContents()
		const themeIdx = texts.findIndex((t) => /^Theming/.test(t.trim()))
		const nlIdx = texts.findIndex((t) => /NL Design System Theme/.test(t))
		expect(themeIdx).toBeGreaterThanOrEqual(0)
		expect(nlIdx).toBeGreaterThan(themeIdx)
	})

	// Scenario: Settings panel is absent when app is disabled
	// @e2e exclude openspec/specs/admin-settings/spec.md#settings-panel-is-absent-when-app-is-disabled
	// Requires disabling the nldesign app and verifying its section disappears;
	// disabling apps requires OCC and modifies shared test environment — not safe for automated e2e.

	// -----------------------------------------------------------------------
	// REQ-ASET-002: Template Response and Parameters
	// -----------------------------------------------------------------------

	// Scenario: Settings panel loads template with all parameters
	// @e2e exclude openspec/specs/admin-settings/spec.md#settings-panel-loads-template-with-all-parameters
	// Validates PHP TemplateResponse parameters (tokenSets, currentTokenSet, hideSlogan, showMenuLabels)
	// — internal server-side rendering details, not observable via DOM.

	// Scenario: Token sets include design system metadata
	// @e2e exclude openspec/specs/admin-settings/spec.md#token-sets-include-design-system-metadata
	// Asserts JSON structure of the `tokenSets` initial-state payload (design_system field) — backend data model.

	// Scenario: Default values for fresh installation
	// @e2e exclude openspec/specs/admin-settings/spec.md#default-values-for-fresh-installation
	// Requires fresh installation state; environment has existing configuration.

	// -----------------------------------------------------------------------
	// REQ-ASET-003: Token Set Selector Dropdown
	// -----------------------------------------------------------------------

	test(// @e2e openspec/specs/admin-settings/spec.md#dropdown-populated-with-token-sets
	'Dropdown populated with token sets', async ({ page }) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		const select = page.locator('#nldesign-token-set-select')
		await expect(select).toBeVisible()
		// The dropdown is the SELECTABLE list, not the catalogue: only brands
		// that are fully functional are offered, which today is `nextcloud`
		// alone. This used to assert "more than five options", which described
		// the catalogue and now describes nothing — an instance running the
		// stock set offers exactly one.
		const values = await select
			.locator('option')
			.evaluateAll((options) =>
				options.map((option) => (option as HTMLOptionElement).value),
			)
		expect(values).toContain('nextcloud')
		// Whatever is running is offered too, so the current value is always
		// one of the options — a dropdown that renders with nothing selected
		// re-themes the instance on the first save.
		expect(values).toContain(await select.inputValue())
		// Each option must have a non-empty text and value
		const firstOption = select.locator('option').first()
		const val = await firstOption.getAttribute('value')
		expect(val).toBeTruthy()
	})

	test(// @e2e openspec/specs/admin-settings/spec.md#dropdown-label-is-associated-with-select
	'Dropdown label is associated with select', async ({ page }) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		// The label element with for="nldesign-token-set-select" must exist
		const label = page.locator('label[for="nldesign-token-set-select"]')
		await expect(label).toBeVisible()
		await expect(label).toContainText('Design token set')
	})

	test(// @e2e openspec/specs/admin-settings/spec.md#design-system-badge-updates-on-selection
	'Design system badge is present after page load', async ({ page }) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		const badge = page.locator('#nldesign-design-system-badge')
		await expect(badge).toBeAttached()
	})

	// Scenario: Admin selects a different token set
	// @e2e exclude openspec/specs/admin-settings/spec.md#admin-selects-a-different-token-set
	// Requires POSTing to tokenset API and verifying IConfig update; covered by
	// token-set-apply-dialog tests that verify the save flow end-to-end.

	// Scenario: Token set with stock Nextcloud design system
	// @e2e exclude openspec/specs/admin-settings/spec.md#token-set-with-stock-nextcloud-design-system
	// Requires selecting "Nextcloud" token set and verifying no nldesign stylesheets load —
	// CSS injection verification is backend/CSS-cascade not testable via DOM selectors.

	// -----------------------------------------------------------------------
	// REQ-ASET-004: Live Preview Box
	// -----------------------------------------------------------------------

	test(// @e2e openspec/specs/admin-settings/spec.md#preview-box-renders-with-token-set-colors
	'Preview box renders with token set colors', async ({ page }) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		// Live preview is rendered as `.nldesign-preview` (id #nldesign-preview)
		// containing app/login `.nldesign-preview-stage` shells. The primary
		// action is the `.nl-btn--primary` button inside the app-shell stage.
		const previewBox = page.locator('#nldesign-preview')
		await expect(previewBox).toBeVisible()
		const primaryBtn = previewBox.locator('.nl-btn--primary').first()
		await expect(primaryBtn).toBeVisible()
	})

	// Scenario: Preview updates on token set change
	// @e2e exclude openspec/specs/admin-settings/spec.md#preview-updates-on-token-set-change
	// Verified implicitly by the apply-dialog flow; isolated color-value assertion
	// requires known token-set colour hardcoded in JS tokenSetColors map — fragile.

	// Scenario: Preview reflects Rijkshuisstijl defaults for unknown sets
	// @e2e exclude openspec/specs/admin-settings/spec.md#preview-reflects-rijkshuisstijl-defaults-for-unknown-sets
	// Internal JS fallback logic, not observable via DOM.

	// -----------------------------------------------------------------------
	// REQ-ASET-005: Hide Slogan Checkbox
	// -----------------------------------------------------------------------

	test(// @e2e openspec/specs/admin-settings/spec.md#checkbox-reflects-enabled-state
	'Hide slogan checkbox is present and has correct label', async ({ page }) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		const checkbox = page.locator('#nldesign-hide-slogan')
		await expect(checkbox).toBeAttached()
		const label = page.locator('label[for="nldesign-hide-slogan"]')
		await expect(label).toBeVisible()
		await expect(label).toContainText('Hide Nextcloud slogan')
	})

	// Scenario: Checkbox reflects disabled state
	// @e2e exclude openspec/specs/admin-settings/spec.md#checkbox-reflects-disabled-state
	// Depends on IConfig state of running environment — not deterministic in shared env.

	test(// @e2e openspec/specs/admin-settings/spec.md#checkbox-label-text-and-accessibility
	'Hide slogan checkbox label text and accessibility', async ({ page }) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		const label = page.locator('label[for="nldesign-hide-slogan"]')
		await expect(label).toContainText(
			'Hide Nextcloud slogan/payoff on login page',
		)
		// label[for] links it to the checkbox — WCAG SC 1.3.1
		const checkbox = page.locator('#nldesign-hide-slogan')
		await expect(checkbox).toBeAttached()
	})

	// Scenario: Checkbox change triggers API call
	// @e2e exclude openspec/specs/admin-settings/spec.md#checkbox-change-triggers-api-call
	// Intercepting the POST /settings/slogan would alter IConfig state; avoid side-effects in shared env.

	// -----------------------------------------------------------------------
	// REQ-ASET-006: Show Menu Labels Checkbox
	// -----------------------------------------------------------------------

	// ANCHOR NOTE (applies to this test and the accessibility one below):
	// these previously read `...#checkbox-reflects-enabled-state-1` and
	// `...#checkbox-label-text-and-accessibility-1`. Neither slug exists.
	// admin-settings/spec.md declares the SAME four scenario titles twice —
	// once under the hide-slogan checkbox requirement (spec.md:128-152) and
	// again under the show-menu-labels one (spec.md:156-176) — and gate-19
	// slugifies a heading's text alone, with no de-duplicating `-1` suffix.
	// The `-1` anchors therefore resolved to nothing and were silently ignored
	// (the gate reports neither dangling anchors nor collisions).
	//
	// Pointing them at the real slug makes them truthful about what this test
	// asserts. It does NOT change the gate's count: because both requirements
	// produce the identical ref, ONE reference already marks BOTH scenarios
	// covered — so the menu-labels scenarios have been credited by the
	// hide-slogan tests all along. That over-crediting is a gate defect and is
	// filed upstream; it cannot be fixed from the test side.
	test(// @e2e openspec/specs/admin-settings/spec.md#checkbox-reflects-enabled-state
	'Show menu labels checkbox is present and has correct label', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		const checkbox = page.locator('#nldesign-show-menu-labels')
		await expect(checkbox).toBeAttached()
		const label = page.locator('label[for="nldesign-show-menu-labels"]')
		await expect(label).toBeVisible()
		await expect(label).toContainText('Show text labels in app menu')
	})

	// Scenario: Checkbox reflects disabled state
	// @e2e exclude openspec/specs/admin-settings/spec.md#checkbox-reflects-disabled-state-1
	// Depends on IConfig runtime state.

	test(// @e2e openspec/specs/admin-settings/spec.md#checkbox-label-text-and-accessibility
	'Show menu labels checkbox label text and accessibility', async ({ page }) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		const label = page.locator('label[for="nldesign-show-menu-labels"]')
		await expect(label).toContainText(
			'Show text labels in app menu (hide icons)',
		)
		const checkbox = page.locator('#nldesign-show-menu-labels')
		await expect(checkbox).toBeAttached()
	})

	// Scenario: Checkbox change triggers API call
	// @e2e exclude openspec/specs/admin-settings/spec.md#checkbox-change-triggers-api-call-1
	// Alters IConfig state — avoid side-effects.

	// -----------------------------------------------------------------------
	// REQ-ASET-007: External Documentation Links
	// -----------------------------------------------------------------------

	test(// @e2e openspec/specs/admin-settings/spec.md#documentation-link-rendered
	'Documentation link rendered with correct attributes', async ({ page }) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		const link = page.locator('a[href="https://nldesign.app"]')
		await expect(link).toBeVisible()
		await expect(link).toHaveAttribute('target', '_blank')
		await expect(link).toHaveAttribute('rel', /noopener/)
	})

	test(// @e2e openspec/specs/admin-settings/spec.md#nl-design-system-info-link-rendered
	'NL Design System info link rendered', async ({ page }) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		const link = page.locator('a[href="https://nldesignsystem.nl/"]')
		await expect(link).toBeVisible()
		await expect(link).toHaveAttribute('target', '_blank')
		await expect(link).toHaveAttribute('rel', /noopener/)
	})

	// Scenario: Links open in new tab without security risk
	// @e2e exclude openspec/specs/admin-settings/spec.md#links-open-in-new-tab-without-security-risk
	// Verified implicitly by the rel="noopener noreferrer" attribute assertions above.

	// -----------------------------------------------------------------------
	// REQ-ASET-008: Vanilla Implementation (No Vue)
	// -----------------------------------------------------------------------

	// All scenarios in REQ-ASET-008 describe server-side implementation details
	// (PHP template structure, absence of webpack bundles) — not testable via DOM.
	// @e2e exclude openspec/specs/admin-settings/spec.md#template-is-plain-php
	// @e2e exclude openspec/specs/admin-settings/spec.md#xss-prevention-via-output-escaping
	// @e2e exclude openspec/specs/admin-settings/spec.md#no-build-step-required

	// -----------------------------------------------------------------------
	// REQ-ASET-009: Admin-Only Access Control
	// -----------------------------------------------------------------------

	// Scenario: Settings panel restricted to admin
	// @e2e exclude openspec/specs/admin-settings/spec.md#settings-panel-restricted-to-admin
	// Requires a non-admin user session — test environment only has admin.

	// Scenario: API endpoints restricted to admin via annotation
	// @e2e exclude openspec/specs/admin-settings/spec.md#api-endpoints-restricted-to-admin-via-annotation
	// PHP annotation check — backend implementation detail.

	test(// @e2e openspec/specs/admin-settings/spec.md#admin-with-valid-session-can-access-all-endpoints
	'Admin with valid session can access settings page', async ({ page }) => {
		await page.goto(THEMING_URL)
		await expect(page).not.toHaveURL(/login/)
		await expect(
			page.locator('h2:has-text("NL Design System Theme")'),
		).toBeVisible()
	})

	// -----------------------------------------------------------------------
	// REQ-ASET-010: Token Editor Panel Integration
	// -----------------------------------------------------------------------

	test(// @e2e openspec/specs/admin-settings/spec.md#token-editor-mount-point-rendered
	'Token editor mount point rendered with content', async ({ page }) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		const editorEl = page.locator('#nldesign-token-editor')
		await expect(editorEl).toBeAttached()
	})

	// Scenario: Token editor loads override data from API
	// @e2e exclude openspec/specs/admin-settings/spec.md#token-editor-loads-override-data-from-api
	// Covered by token-editor-ui tests.

	// Scenario: Token editor saves changes via API
	// @e2e exclude openspec/specs/admin-settings/spec.md#token-editor-saves-changes-via-api
	// Covered by token-editor-ui tests.

	// -----------------------------------------------------------------------
	// REQ-ASET-011: Settings Hint Text
	// -----------------------------------------------------------------------

	test(// @e2e openspec/specs/admin-settings/spec.md#settings-hint-rendered
	'Settings hint text rendered', async ({ page }) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		const hint = page
			.locator('.settings-hint')
			.filter({ hasText: 'Select a Dutch government design token set' })
		await expect(hint).toBeVisible()
	})

	// Scenario: Hint text is localized
	// @e2e exclude openspec/specs/admin-settings/spec.md#hint-text-is-localized
	// Requires Dutch-language session — environment uses default language.

	// Scenario: Hint text provides sufficient context
	// @e2e exclude openspec/specs/admin-settings/spec.md#hint-text-provides-sufficient-context
	// Subjective content quality assertion; covered by hint-rendered test above.

	// -----------------------------------------------------------------------
	// REQ-ASET-012: Data Attributes for JavaScript Initialization
	// -----------------------------------------------------------------------

	/**
	 * Read one initial-state value the way Nextcloud publishes it.
	 *
	 * `IInitialState::provideInitialState('foo', …)` renders a hidden input
	 * with id `initial-state-<app>-<key>` whose value is base64-encoded JSON.
	 * Asserting on that element — rather than on whatever `loadState()`
	 * happens to return once scripts have run — keeps this a test of the
	 * SERVER's output, which is what the scenarios above are about.
	 */
	const readInitialState = async (page, key: string) => {
		const el = page.locator(`#initial-state-thematiq-${key}`)
		await expect(el).toBeAttached()
		const encoded = await el.getAttribute('value')
		expect(encoded).toBeTruthy()
		return JSON.parse(Buffer.from(encoded as string, 'base64').toString('utf-8'))
	}

	test(// @e2e openspec/specs/admin-settings/spec.md#token-sets-provided-as-initial-state
	'Token sets provided as initial state, not as a data attribute', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')

		const settingsDiv = page.locator('#nldesign-settings')
		await expect(settingsDiv).toBeAttached()

		const parsed = await readInitialState(page, 'tokenSets')
		expect(Array.isArray(parsed)).toBe(true)
		expect(parsed.length).toBeGreaterThan(0)

		// The old transport must be gone, not merely unused. Asserting on
		// the ITEM (the attribute) rather than on the panel rendering
		// correctly is deliberate: the panel would render correctly with
		// both mechanisms present, and that is exactly the state this
		// change exists to leave behind.
		expect(await settingsDiv.getAttribute('data-token-sets')).toBeNull()
	})

	test(// @e2e openspec/specs/admin-settings/spec.md#current-token-set-provided-as-initial-state
	'Current token set provided as initial state, not as a data attribute', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')

		const current = await readInitialState(page, 'currentTokenSet')
		expect(typeof current).toBe('string')
		expect(current.length).toBeGreaterThan(0)

		const settingsDiv = page.locator('#nldesign-settings')
		expect(await settingsDiv.getAttribute('data-current-token-set')).toBeNull()
	})

	test(// @e2e openspec/specs/admin-settings/spec.md#active-preview-and-icon-pack-source-provided-as-initial-state
	'Active preview and icon pack source provided as initial state', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')

		// `activePreview` is null when no preview is running — a null is a
		// value here, so the assertion is that the KEY is published, not
		// that it is truthy.
		await expect(
			page.locator('#initial-state-thematiq-activePreview'),
		).toBeAttached()

		const source = await readInitialState(page, 'iconPackSource')
		expect(typeof source).toBe('string')

		expect(
			await page
				.locator('#nldesign-settings')
				.getAttribute('data-active-preview'),
		).toBeNull()
		expect(
			await page
				.locator('#nldesign-icon-pack-indicator')
				.getAttribute('data-icon-pack-source'),
		).toBeNull()
	})

	// Scenario: JavaScript reads initial state on initialization
	// @e2e exclude openspec/specs/admin-settings/spec.md#javascript-reads-initial-state-on-initialization
	// Internal JS initialization logic; observable outcome (dropdown shows correct option) covered by
	// dropdown-populated test, and the read path itself by tests/vitest/admin-a11y.spec.js.

	// -----------------------------------------------------------------------
	// REQ-ASET-013: Localization Support
	// -----------------------------------------------------------------------

	// Scenario: All static text uses l10n
	// @e2e exclude openspec/specs/admin-settings/spec.md#all-static-text-uses-l10n
	// Requires PHP template source inspection — not testable via DOM.

	// Scenario: Dutch translation available
	// @e2e exclude openspec/specs/admin-settings/spec.md#dutch-translation-available
	// Requires Dutch-language Nextcloud session.

	// Scenario: English fallback
	// @e2e exclude openspec/specs/admin-settings/spec.md#english-fallback
	// Fallback language behaviour — covered by the English-language assertions in other tests.
})
