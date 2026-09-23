/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/email-theming/spec.md
 * @e2e openspec/specs/custom-fonts/spec.md
 * @e2e openspec/specs/theming-audit/spec.md
 * @e2e openspec/specs/upstream-freshness/spec.md
 * @e2e openspec/specs/per-group-theming/spec.md
 * @e2e openspec/specs/config-portability/spec.md
 * @e2e openspec/specs/theme-preview/spec.md
 *
 * Every admin panel added by the market-gap wave must actually render in the
 * Theming settings page — a controller + route with no reachable UI is the
 * "orphaned capability" defect class this fleet keeps hitting.
 */
import { test, expect, Page } from '@playwright/test'

const THEMING_URL = '/settings/admin/theming'

/**
 * GET an nldesign settings endpoint from inside the authenticated page.
 *
 * These routes are session-authenticated and therefore CSRF-protected: a raw
 * request-context call (cookies only, no `requesttoken`) is rejected with 412
 * by Nextcloud's middleware. Issuing the fetch in-page is both correct and
 * closer to what `js/admin.js` actually does.
 */
async function apiGet(page: Page, path: string): Promise<Record<string, unknown>> {
	return page.evaluate(async (p) => {
		const res = await fetch(p, {
			headers: { requesttoken: (window as any).OC.requestToken },
		})
		return res.json()
	}, path)
}

/** Each wave feature: its settings-panel anchor and a human label. */
const PANELS: Array<{ id: string; label: string }> = [
	{ id: '#nldesign-email-theming', label: 'email template theming' },
	{ id: '#nldesign-custom-fonts', label: 'custom font upload' },
	{ id: '#nldesign-audit-log', label: 'theming audit log' },
	{ id: '#nldesign-upstream-freshness', label: 'upstream token freshness' },
	{ id: '#nldesign-group-theming', label: 'per-group theming' },
]

test.describe('admin panels for the market-gap wave features', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto(THEMING_URL)
		await page
			.locator('#nldesign-settings')
			.waitFor({ state: 'visible', timeout: 20_000 })
	})

	for (const panel of PANELS) {
		test(`the ${panel.label} panel is present and visible`, async ({ page }) => {
			const el = page.locator(panel.id)
			await expect(
				el,
				`${panel.label} must render a reachable panel`,
			).toHaveCount(1)
			await expect(el).toBeVisible()
		})
	}

	test('the dark-variants toggle renders and reflects persisted state', async ({
		page,
	}) => {
		const toggle = page.locator('#nldesign-dark-variants')
		await expect(toggle).toHaveCount(1)

		const state = await apiGet(
			page,
			'/index.php/apps/thematiq/settings/dark-variants',
		)
		expect(await toggle.isChecked()).toBe(state.enabled)
	})

	test('the audit log lists entries with the documented columns', async ({
		page,
	}) => {
		const table = page.locator('#nldesign-audit-table')
		await expect(table).toBeVisible()
		for (const header of [
			'Timestamp',
			'User',
			'Action',
			'From',
			'To',
			'Changed',
		]) {
			await expect(table.locator('thead')).toContainText(header)
		}
	})

	test('upstream freshness is opt-in and discloses the contacted host', async ({
		page,
	}) => {
		const toggle = page.locator('#nldesign-upstream-freshness-toggle')
		await expect(toggle).toHaveCount(1)

		// Default OFF: the app must make no outbound request unless asked.
		const status = await apiGet(
			page,
			'/index.php/apps/thematiq/settings/upstream-freshness',
		)
		expect(status.enabled).toBe(false)

		// The egress target must be named in the UI, not hidden in docs.
		await expect(page.locator('#nldesign-upstream-freshness')).toContainText(
			'api.github.com',
		)
	})

	test('the custom-fonts panel states uploader licence responsibility', async ({
		page,
	}) => {
		await expect(page.locator('#nldesign-custom-fonts')).toContainText(
			/licen[cs]e/i,
		)
	})
})
