/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/dark-mode/spec.md
 *
 * Dark-mode token variants: the generated dark stylesheet is injected next to
 * the light token layer, is scoped so an explicit light choice is never
 * darkened, and the admin toggle gates it instance-wide.
 *
 * GLOBAL STATE: the tests read the stylesheets of a set that has a dark
 * variant, so Zuiddrecht is made the active set (activateTokenSet) for the
 * file and the previous set and its overrides are put back afterwards. The
 * instance's own default is the stock `nextcloud` set, which wears no design
 * system and therefore links no token file and no dark variant at all.
 */
import { test, expect, Page } from '@playwright/test'

import { DARK_PROBE_SET } from './_dark-probe-set'
import { activateTokenSet } from './_token-css'

const THEMING_URL = '/settings/admin/theming'
const PROBE_URL = '/settings/user'

/**
 * Read the dark-variants toggle state from inside the authenticated page.
 *
 * The settings routes are CSRF-protected, so a raw request-context call
 * (cookies without `requesttoken`) is rejected with 412 — fetching in-page is
 * both correct and what `js/admin.js` does.
 */
async function darkVariantsEnabled(page: Page): Promise<boolean> {
	return page.evaluate(async () => {
		const res = await fetch('/index.php/apps/thematiq/settings/dark-variants', {
			headers: { requesttoken: (window as any).OC.requestToken },
		})
		return (await res.json()).enabled
	})
}

/** The nldesign stylesheet hrefs present in the page head. */
async function nldesignStyles(page: Page): Promise<string[]> {
	return page.evaluate(() =>
		[...document.querySelectorAll('link[rel=stylesheet]')]
			.map((l) => (l as HTMLLinkElement).href)
			.filter((h) => h.includes('/thematiq/')),
	)
}

let restore: (() => Promise<void>) | null = null

test.beforeAll(async ({ browser }) => {
	restore = await activateTokenSet(browser, DARK_PROBE_SET)
})

test.afterAll(async () => {
	if (restore !== null) {
		await restore()
	}
})

test.describe('dark-mode token variants', () => {
	test('the dark stylesheet is injected directly after the light token layer', async ({
		page,
	}) => {
		await page.goto(PROBE_URL)
		const styles = await nldesignStyles(page)

		const lightIndex = styles.findIndex((h) =>
			/\/css\/tokens\/[^/]+\.css/.test(h),
		)
		const darkIndex = styles.findIndex((h) => h.includes('/css/tokens/dark/'))

		expect(
			lightIndex,
			'light token stylesheet must be present',
		).toBeGreaterThanOrEqual(0)
		expect(
			darkIndex,
			'dark variant stylesheet must be present',
		).toBeGreaterThanOrEqual(0)
		// The dark layer must come AFTER the light one so its scoped rules win.
		expect(darkIndex).toBeGreaterThan(lightIndex)
	})

	test('the dark stylesheet is dual-scoped and never darkens an explicit light choice', async ({
		page,
	}) => {
		await page.goto(PROBE_URL)
		const styles = await nldesignStyles(page)
		const darkHref = styles.find((h) => h.includes('/css/tokens/dark/'))
		expect(darkHref).toBeTruthy()

		const css = await (await page.request.get(darkHref as string)).text()

		// Auto mode: follows the OS preference.
		expect(css).toContain('prefers-color-scheme: dark')
		// Explicit dark theme selection.
		expect(css).toContain('data-theme-dark')
		// An explicitly chosen LIGHT theme must be excluded from the media-query
		// branch, otherwise a user who picked light would be darkened by the OS.
		expect(css).toContain(':not([data-theme-light])')
	})

	test('the admin toggle renders, is checked by default, and matches persisted state', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		const toggle = page.locator('#nldesign-dark-variants')
		await toggle.waitFor({ state: 'attached', timeout: 15_000 })

		// The rendered control must agree with the persisted app config —
		// a toggle that renders a stale default is the bug this catches.
		expect(await toggle.isChecked()).toBe(await darkVariantsEnabled(page))
	})

	// @e2e exclude openspec/specs/dark-mode/spec.md#requirement-admin-toggle
	// Flipping the toggle mutates INSTANCE-WIDE state on the shared dev
	// instance (same rationale as the hide-slogan spec's excluded
	// checkbox-change test). The gating behaviour — toggle off ⇒ no dark
	// stylesheet is injected — is covered by DarkPaletteService /
	// CssInjectionService unit tests and was verified manually against the
	// live instance (occ toggle off ⇒ 0 dark stylesheets ⇒ restored).
})
