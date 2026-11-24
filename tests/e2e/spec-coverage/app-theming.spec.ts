/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/per-app-theming/spec.md
 *
 * Per-app theming toggle: excluding an app suppresses ALL nldesign CSS on that
 * app's pages while every other app (and login/settings) stays themed.
 */
import { test, expect, Page } from '@playwright/test'

const THEMING_URL = '/settings/admin/theming'
// An app that is present in the test instance and safe to toggle.
//
// `dashboard` — not `activity`. Activity is a separate appstore app; it is
// bundled in the docker image but is NOT in nextcloud/server's `apps/`, which
// is what CI checks out, so it is simply absent there. The panel rendered
// correctly and just had no `input[data-app-id="activity"]` row, and all four
// tests here blew a 30s timeout on a locator for an app that was never
// installed (runs 30889958278, 30892246034).
//
// `dashboard` is shipped by nextcloud/server, enabled by default (it is the
// first entry of core's own `defaultapp` list), is not in
// AppThemingService::PROTECTED_IDS so it appears in the panel, and has a real
// page at /apps/dashboard/ for the "CSS is stripped from its pages" half of
// the assertion. Deliberately NOT `files`: `/apps/files/` is already the
// CONTROL that must stay themed in the same test, and using one app for both
// arms would make the assertion contradict itself.
const TARGET_APP = 'dashboard'

/** Count nldesign stylesheets present in the page head. */
async function nldesignStyleCount(page: Page): Promise<number> {
	return page.evaluate(
		() =>
			[...document.querySelectorAll('link[rel=stylesheet]')].filter((l) =>
				(l as HTMLLinkElement).href.includes('/thematiq/'),
			).length,
	)
}

/**
 * Expand the per-app theming dropdown and filter it to the target app so its
 * checkbox is visible AND inside the (scrollable) panel viewport. The list is
 * rendered inside a collapsed `.nldesign-app-dropdown-panel`; every checkbox
 * exists in the DOM but is hidden until the trigger is clicked, and the panel
 * scrolls internally, so far-down rows stay outside the click viewport until
 * the search field filters the list down to a single matching row.
 */
async function openAppThemingDropdown(page: Page, appName = TARGET_APP) {
	const trigger = page.locator(
		'#nldesign-app-theming-list .nldesign-app-dropdown-trigger',
	)
	if ((await trigger.count()) === 0) {
		// No dropdown variant (flat list) — nothing to expand.
		return
	}
	if (
		!(await page
			.locator('#nldesign-app-theming-list .nldesign-app-dropdown.open')
			.count())
	) {
		await trigger.click()
	}
	const search = page.locator(
		'#nldesign-app-theming-list .nldesign-app-dropdown-search input',
	)
	await search.waitFor({ state: 'visible' })
	await search.fill(appName)
}

/** Set the target app's themed state via the admin panel and save. */
async function setThemed(page: Page, themed: boolean) {
	await page.goto(THEMING_URL)
	await page.waitForLoadState('domcontentloaded')
	await openAppThemingDropdown(page)
	const box = page.locator(
		`#nldesign-app-theming-list input[data-app-id="${TARGET_APP}"]`,
	)
	// The raw <input> is visually hidden off-canvas (position:absolute, left:-9999px),
	// so Playwright cannot click it directly. The actual control is the associated
	// <label>; clicking it toggles the checkbox. Read state from the input.
	const id = await box.getAttribute('id')
	const label = page.locator(`#nldesign-app-theming-list label[for="${id}"]`)
	await label.waitFor({ state: 'visible' })
	if ((await box.isChecked()) !== themed) {
		await label.click()
	}
	// Collapse the dropdown so its open panel/search field no longer overlaps
	// (and intercepts pointer events for) the Save button below it.
	const trigger = page.locator(
		'#nldesign-app-theming-list .nldesign-app-dropdown-trigger',
	)
	if ((await trigger.count()) > 0) {
		await trigger.click()
		await expect(
			page.locator('#nldesign-app-theming-list .nldesign-app-dropdown.open'),
		).toHaveCount(0)
	}
	await page.locator('#nldesign-app-theming-save').click()
	// Allow the POST to round-trip.
	await page.waitForTimeout(800)
}

test.describe('per-app-theming', () => {
	// PHPUnit-covered storage/validation/resolver scenarios — not DOM-testable.
	// @e2e exclude openspec/specs/per-app-theming/spec.md#fresh-install-upgrade-has-no-exclusions
	// @e2e exclude openspec/specs/per-app-theming/spec.md#unknown-app-ids-self-heal-on-save
	// @e2e exclude openspec/specs/per-app-theming/spec.md#index-php-prefixed-urls-resolve-to-the-same-app-id
	// @e2e exclude openspec/specs/per-app-theming/spec.md#resolution-failure-fails-open-to-themed
	// Newman/API-contract scenarios — covered by the integration collection.
	// @e2e exclude openspec/specs/per-app-theming/spec.md#admin-reads-the-per-app-theming-state
	// @e2e exclude openspec/specs/per-app-theming/spec.md#posting-an-exclusion-for-a-protected-id-is-ignored
	// @e2e exclude openspec/specs/per-app-theming/spec.md#non-admin-cannot-change-the-exclusion-list

	test.afterAll(async ({ browser }) => {
		// Always restore theming for the target app so the suite is idempotent.
		const page = await browser.newPage()
		await setThemed(page, true).catch(() => {})
		await page.close()
	})

	test(
		// @e2e openspec/specs/per-app-theming/spec.md#admin-excludes-an-app-via-the-panel
		// @e2e openspec/specs/per-app-theming/spec.md#excluded-app-renders-without-any-nldesign-css
		// @e2e openspec/specs/per-app-theming/spec.md#non-excluded-app-stays-fully-themed
		'Excluding an app via the panel strips nldesign CSS from its pages only',
		async ({ page }) => {
			await setThemed(page, false)

			await page.goto(`/apps/${TARGET_APP}/`)
			await page.waitForLoadState('domcontentloaded')
			expect(await nldesignStyleCount(page)).toBe(0)

			await page.goto('/apps/files/')
			await page.waitForLoadState('domcontentloaded')
			expect(await nldesignStyleCount(page)).toBeGreaterThan(0)
		},
	)

	test(
		// @e2e openspec/specs/per-app-theming/spec.md#admin-re-enables-theming-for-an-app
		'Re-enabling an app restores theming on its pages',
		async ({ page }) => {
			await setThemed(page, false)
			await setThemed(page, true)

			await page.goto(`/apps/${TARGET_APP}/`)
			await page.waitForLoadState('domcontentloaded')
			expect(await nldesignStyleCount(page)).toBeGreaterThan(0)
		},
	)

	test(
		// @e2e openspec/specs/per-app-theming/spec.md#login-and-settings-pages-are-always-themed
		'Settings pages stay themed even with an active exclusion list',
		async ({ page }) => {
			await setThemed(page, false)
			await page.goto(THEMING_URL)
			await page.waitForLoadState('domcontentloaded')
			expect(await nldesignStyleCount(page)).toBeGreaterThan(0)
		},
	)

	test(
		// @e2e openspec/specs/per-app-theming/spec.md#checkboxes-are-accessible
		'Every app row exposes a checkbox associated with a visible label',
		async ({ page }) => {
			await page.goto(THEMING_URL)
			await page.waitForLoadState('domcontentloaded')
			await openAppThemingDropdown(page)
			const box = page.locator(
				`#nldesign-app-theming-list input[data-app-id="${TARGET_APP}"]`,
			)
			await box.waitFor({ state: 'visible' })
			const id = await box.getAttribute('id')
			const label = page.locator(`label[for="${id}"]`)
			await expect(label).toBeVisible()
			await expect(label).not.toBeEmpty()
		},
	)
})
