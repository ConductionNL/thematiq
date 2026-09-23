/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * DEEP, DATA-DEPENDENT workflow: token-SET apply + persistence.
 *
 * Proves the "select a municipality / token set and apply it" feature actually
 * works end-to-end through the UI:
 *   - changing the token-set dropdown opens the apply dialog (when the set would
 *     change resolved values) and confirming it persists the active set, and
 *   - the active set survives a fresh reload (GET /settings/tokenset + the
 *     dropdown's selected <option>), and
 *   - the design-system badge reflects the newly-selected set.
 * Then it RESTORES the prior set.
 *
 * GLOBAL STATE: mutates nldesign.token_set (and, via the apply dialog, possibly
 * custom-overrides.css). Both are snapshotted in beforeAll and RESTORED in
 * afterAll. We deliberately DESELECT all token rows in the apply dialog so the
 * confirm only changes the active set, not the persisted overrides.
 */
import { test, expect } from '@playwright/test'
import {
	openTheming,
	requestToken,
	getOverrides,
	setOverrides,
	getTokenSet,
	setTokenSet,
	offerTokenSets,
	withdrawTokenSetOffer,
	type TokenSetOffer,
} from './_helpers'

// Target set to apply (a real municipality set in token-sets.json). Chosen so it
// differs from the common dev default ("demodam") to force a real change.
const TARGET_SET = 'utrecht'

let baselineSet = ''
let baselineOverrides: Record<string, string> = {}
let offer: TokenSetOffer | null = null

test.describe('workflow: token-set apply persistence', () => {
	test.describe.configure({ mode: 'serial', timeout: 90_000 })

	test.beforeAll(async ({ browser }) => {
		const page = await browser.newPage()
		await openTheming(page)
		const token = await requestToken(page)
		baselineSet = await getTokenSet(page, token)
		baselineOverrides = await getOverrides(page, token)
		// A shipped brand is only in the dropdown once something makes it
		// selectable (token-sets spec, "Only Fully Functional Brands Are
		// Selectable"); a group mapping does so without theming this admin.
		offer = await offerTokenSets(page, token, [TARGET_SET])
		await page.close()
	})

	test.afterAll(async ({ browser }) => {
		// CRITICAL: restore the prior active set AND overrides.
		const page = await browser.newPage()
		await openTheming(page)
		const token = await requestToken(page)
		await setTokenSet(page, token, baselineSet)
		await setOverrides(page, token, baselineOverrides)
		if (offer !== null) {
			await withdrawTokenSetOffer(page, token, offer)
		}
		expect(await getTokenSet(page, token)).toBe(baselineSet)
		expect(await getOverrides(page, token)).toEqual(baselineOverrides)
		await page.close()
	})

	test('selecting + applying a token set PERSISTS the active set and updates the badge', async ({
		page,
	}) => {
		await openTheming(page)
		const token = await requestToken(page)

		// Ensure we start from a set that is NOT the target, so the change is real.
		if ((await getTokenSet(page, token)) === TARGET_SET) {
			await setTokenSet(page, token, 'demodam')
			await openTheming(page)
		}

		const select = page.locator('#nldesign-token-set-select')
		await expect(select).toBeVisible()

		// Drive the real dropdown — this fires the change handler → apply dialog.
		await select.selectOption(TARGET_SET)

		// The apply dialog appears when resolved values would change. If the set
		// produces no visual diff the app saves directly (no dialog). Handle both.
		const dialog = page.locator('#nldesign-apply-dialog-overlay')
		const appeared = await dialog
			.waitFor({ state: 'visible', timeout: 4_000 })
			.then(() => true)
			.catch(() => false)

		if (appeared) {
			// Deselect all token rows so confirming changes ONLY the active set,
			// not the persisted custom overrides.
			await page.locator('#nldesign-apply-deselect-all').click()
			await dialog.locator('.nldesign-dialog-confirm').click()
			await expect(dialog).toBeHidden({ timeout: 10_000 })
		}

		// Active set is persisted in the backend.
		await expect
			.poll(async () => await getTokenSet(page, token), { timeout: 10_000 })
			.toBe(TARGET_SET)

		// The design-system badge reflects the selected set (non-empty system name).
		const badge = page.locator('#nldesign-design-system-badge')
		await expect(badge).not.toHaveText('')
	})

	test('applied token set survives a FRESH RELOAD (dropdown selection + backend)', async ({
		page,
	}) => {
		await openTheming(page)
		const token = await requestToken(page)

		// Backend still reports the applied set.
		expect(await getTokenSet(page, token)).toBe(TARGET_SET)

		// The server-rendered <select> has the applied set marked selected.
		const selected = await page
			.locator('#nldesign-token-set-select')
			.inputValue()
		expect(selected).toBe(TARGET_SET)
	})
})
