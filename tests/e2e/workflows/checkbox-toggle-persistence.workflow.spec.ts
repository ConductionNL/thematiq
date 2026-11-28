/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * DEEP, DATA-DEPENDENT workflow: checkbox toggle persistence.
 *
 * The hide-slogan and show-menu-labels checkboxes write boolean app config that
 * conditionally injects css/hide-slogan.css / show-menu-labels.css instance-wide
 * (see Application::injectThemeCSS). This workflow proves the toggles actually
 * PERSIST:
 *   - clicking a checkbox saves the boolean to the backend, and
 *   - after a FRESH RELOAD the server-rendered checkbox reflects the persisted
 *     state (the template reads hide_slogan / show_menu_labels app config), and
 *   - toggling back removes/clears it.
 *
 * GLOBAL STATE: mutates nldesign.hide_slogan / show_menu_labels. Both are
 * snapshotted in beforeAll and RESTORED in afterAll.
 */
import { test, expect, type Page } from '@playwright/test'
import { openTheming, requestToken, setSlogan, setMenuLabels } from './_helpers'

async function checkboxState(page: Page, id: string): Promise<boolean> {
	// The native checkbox is visually hidden by Nextcloud styling; read the
	// underlying checked property directly rather than relying on visibility.
	return await page
		.locator(`#${id}`)
		.evaluate((el) => (el as HTMLInputElement).checked)
}

/**
 * Toggle a Nextcloud-styled checkbox to a target state by clicking its <label>
 * (the native input is positioned off-screen and not directly clickable). The
 * admin.js 'change' handler POSTs the new value asynchronously; we WAIT for that
 * POST to resolve before returning so the caller can safely reload and assert
 * the persisted state (otherwise the save races the reload).
 */
async function setCheckbox(
	page: Page,
	id: string,
	target: boolean,
	endpoint: string,
): Promise<void> {
	const current = await checkboxState(page, id)
	if (current === target) {
		return
	}
	// Click the visible label, which natively toggles the associated input and
	// fires the 'change' event the admin.js handler is bound to. Await the
	// resulting persistence POST so it completes before any reload.
	const [resp] = await Promise.all([
		page.waitForResponse(
			(r) => r.url().includes(endpoint) && r.request().method() === 'POST',
			{ timeout: 10_000 },
		),
		page.locator(`label[for="${id}"]`).click(),
	])
	expect(resp.status(), `${endpoint} save should 200`).toBe(200)
	if (endpoint.includes('app-menu')) {
		// The menu switches (js/admin-layout-panel.js) never read the answer, and
		// Chromium does not finish a fetch body the page leaves unread, so
		// `resp.json()` would wait for ever. The switch's own live region says
		// Saved once the answer is ok; the reload in the caller proves the store.
		await expect(
			page.locator(`#${id}`).locator('xpath=..').locator('.thematiq-create-switches__live'),
		).toHaveText(/\S/, { timeout: 5_000 })
	} else {
		const body = await resp.json()
		expect(body.status).toBe('ok')
	}
	await expect
		.poll(async () => await checkboxState(page, id), { timeout: 5_000 })
		.toBe(target)
}

let baselineHideSlogan = false
let baselineMenuLabels = false

test.describe('workflow: checkbox toggle persistence', () => {
	test.describe.configure({ mode: 'serial', timeout: 90_000 })

	test.beforeAll(async ({ browser }) => {
		const page = await browser.newPage()
		await openTheming(page)
		baselineHideSlogan = await checkboxState(page, 'nldesign-hide-slogan')
		baselineMenuLabels = await checkboxState(page, 'nldesign-show-menu-labels')
		await page.close()
	})

	test.afterAll(async ({ browser }) => {
		// CRITICAL: restore both booleans to their prior persisted state.
		const page = await browser.newPage()
		await openTheming(page)
		const token = await requestToken(page)
		await setSlogan(page, token, baselineHideSlogan)
		await setMenuLabels(page, token, baselineMenuLabels)
		// Verify the reload reflects the restored state.
		await openTheming(page)
		expect(await checkboxState(page, 'nldesign-hide-slogan')).toBe(
			baselineHideSlogan,
		)
		expect(await checkboxState(page, 'nldesign-show-menu-labels')).toBe(
			baselineMenuLabels,
		)
		await page.close()
	})

	test('toggling hide-slogan PERSISTS across a fresh reload', async ({ page }) => {
		await openTheming(page)
		const before = await checkboxState(page, 'nldesign-hide-slogan')
		const target = !before

		// Drive the real checkbox — fires the change handler → POST /settings/slogan.
		await setCheckbox(page, 'nldesign-hide-slogan', target, '/settings/slogan')

		// Reload fresh: the server-rendered checkbox must reflect the new persisted value.
		await openTheming(page)
		expect(
			await checkboxState(page, 'nldesign-hide-slogan'),
			'hide-slogan should persist toggled state',
		).toBe(target)

		// Toggle back and confirm the reverse also persists.
		await setCheckbox(page, 'nldesign-hide-slogan', before, '/settings/slogan')
		await openTheming(page)
		expect(
			await checkboxState(page, 'nldesign-hide-slogan'),
			'hide-slogan should persist reverted state',
		).toBe(before)
	})

	test('toggling show-menu-labels PERSISTS across a fresh reload', async ({
		page,
	}) => {
		await openTheming(page)
		const before = await checkboxState(page, 'nldesign-show-menu-labels')
		const target = !before

		await setCheckbox(
			page,
			'nldesign-show-menu-labels',
			target,
			'/app-menu/defaults',
		)

		await openTheming(page)
		expect(
			await checkboxState(page, 'nldesign-show-menu-labels'),
			'menu-labels should persist toggled state',
		).toBe(target)

		await setCheckbox(
			page,
			'nldesign-show-menu-labels',
			before,
			'/app-menu/defaults',
		)
		await openTheming(page)
		expect(
			await checkboxState(page, 'nldesign-show-menu-labels'),
			'menu-labels should persist reverted state',
		).toBe(before)
	})
})
