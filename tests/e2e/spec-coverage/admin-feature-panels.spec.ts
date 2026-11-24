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
import { adminContext } from './_fixtures'

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

/** Each wave feature: its anchor, the admin page panel (#hash) it lives in, and a human label. */
const PANELS: Array<{ id: string; hash: string; label: string }> = [
	{
		id: '#nldesign-email-theming',
		hash: 'email',
		label: 'email template theming',
	},
	{ id: '#nldesign-custom-fonts', hash: 'fonts', label: 'custom font upload' },
	{ id: '#nldesign-audit-log', hash: 'log', label: 'theming audit log' },
	{
		id: '#nldesign-upstream-freshness',
		hash: 'gallery',
		label: 'upstream token freshness',
	},
	{ id: '#nldesign-group-theming', hash: 'groups', label: 'per-group theming' },
]

/** Open the admin page panel at `#hash` on the shared page. */
async function openPanel(page: Page, hash: string): Promise<void> {
	await page.evaluate((h) => {
		window.location.hash = h
	}, hash)
	await expect(
		page.locator(`section[data-thematiq-panel="${hash}"]`),
	).toBeVisible()
}

test.describe('admin panels for the market-gap wave features', () => {
	// One page load serves every test below. Each only reads the rendered
	// panels or GETs a settings endpoint, and an admin page load is the most
	// expensive step of this suite (about 20 s on CI).
	//
	// `mode: 'default'` keeps the block on one CI shard, so the page loads once
	// per run. Under `fullyParallel` a block with a beforeAll is otherwise cut
	// into groups across shards, each loading the page again. Unlike 'serial',
	// a failure does not skip the tests after it.
	test.describe.configure({ mode: 'default' })

	let page: Page

	test.beforeAll(async ({ browser }) => {
		const context = await adminContext(browser)
		page = await context.newPage()
		await page.goto(THEMING_URL)
		await page
			.locator('#nldesign-settings')
			.waitFor({ state: 'visible', timeout: 20_000 })
	})

	test.afterAll(async () => {
		await page?.context().close()
	})

	// The shared page is not the test's own `page` fixture, so Playwright
	// takes no screenshot of it on a failure; this one does.
	test.afterEach(async ({}, testInfo) => {
		if (page !== undefined && testInfo.status !== testInfo.expectedStatus) {
			await testInfo.attach('shared page', {
				body: await page.screenshot({ fullPage: true }),
				contentType: 'image/png',
			})
		}
	})

	for (const panel of PANELS) {
		test(`the ${panel.label} panel is present and visible`, async () => {
			await openPanel(page, panel.hash)
			const el = page.locator(panel.id)
			await expect(
				el,
				`${panel.label} must render a reachable panel`,
			).toHaveCount(1)
			await expect(el).toBeVisible()
		})
	}

	test('the dark-variants toggle renders and reflects persisted state', async () => {
		const toggle = page.locator('#nldesign-dark-variants')
		await expect(toggle).toHaveCount(1)

		const state = await apiGet(
			page,
			'/index.php/apps/thematiq/settings/dark-variants',
		)
		expect(await toggle.isChecked()).toBe(state.enabled)
	})

	test('the audit log lists entries with the documented columns', async () => {
		await openPanel(page, 'log')
		const table = page.locator('#nldesign-audit-table')
		await expect(table).toBeVisible()
		// The columns of the TqLogboek board.
		for (const header of ['Time', 'Who', 'Action', 'From', 'To', 'Changed']) {
			await expect(table.locator('thead')).toContainText(header)
		}
	})

	test('upstream freshness is opt-in and discloses the contacted host', async () => {
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

	test('the custom-fonts panel states uploader licence responsibility', async () => {
		await expect(page.locator('#nldesign-custom-fonts')).toContainText(
			/licen[cs]e/i,
		)
	})
})
