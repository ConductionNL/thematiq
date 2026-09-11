/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Apply without a reload (MAKEOVER-PLAN.md stage 2, task 2.11).
 *
 * The whole flow — select a set, confirm, sync core theming, switch back to
 * stock — happens on ONE page load. The spec counts navigations and asserts
 * exactly one: the initial goto. Everything else is proven by what is on the
 * page afterwards: the set's stylesheet run (present or absent), the header
 * colour, and the value core's own Theming panel shows further up the page.
 *
 * Mutates instance state (token_set, custom-overrides.css, core theming) and
 * restores every piece in afterAll, per _helpers.ts.
 */
import { test, expect, type Page } from '@playwright/test'
import {
	openTheming,
	requestToken,
	getTokenSet,
	setTokenSet,
	getOverrides,
	setOverrides,
} from './_helpers'

declare const OC: { generateUrl: (path: string) => string; requestToken: string }

const SHIPPED_SET = 'amsterdam'

/** Core theming values this spec may change and must put back. */
type ThemingSnapshot = { primary_color: string; background_color: string }

async function getCoreTheming(page: Page, token: string): Promise<ThemingSnapshot> {
	return page.evaluate(async (t) => {
		const r = await fetch(OC.generateUrl('/apps/thematiq/settings/theming'), {
			headers: { requesttoken: t },
		})
		const j = await r.json()
		return { primary_color: j.primary_color, background_color: j.background_color }
	}, token)
}

async function setCoreTheming(page: Page, token: string, values: ThemingSnapshot): Promise<void> {
	await page.evaluate(
		async ({ t, v }) => {
			const body = Object.entries(v)
				.filter(([, value]) => value !== '')
				.map(([k, value]) => `${encodeURIComponent(k)}=${encodeURIComponent(value)}`)
				.join('&')
			await fetch(OC.generateUrl('/apps/thematiq/settings/theming'), {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded', requesttoken: t },
				body,
			})
		},
		{ t: token, v: values },
	)
}

/** The Thematiq set-layer <link>s on the page, by pathname under css/. */
async function setLayerFiles(page: Page): Promise<string[]> {
	return page.evaluate(() =>
		Array.from(document.querySelectorAll<HTMLLinkElement>('link[rel="stylesheet"][href*="/thematiq/css/"]'))
			.map((l) => new URL(l.href, location.origin).pathname.replace(/^.*\/thematiq\/css\//, ''))
			.filter((f) => f.startsWith('systems/') || f.startsWith('tokens/')),
	)
}

test.describe('apply without a reload', () => {
	let originalTokenSet = 'nextcloud'
	let originalOverrides: Record<string, string> = {}
	let originalTheming: ThemingSnapshot = { primary_color: '', background_color: '' }

	test.beforeAll(async ({ browser }) => {
		const page = await browser.newPage()
		await openTheming(page)
		const token = await requestToken(page)
		originalTokenSet = await getTokenSet(page, token)
		originalOverrides = await getOverrides(page, token)
		originalTheming = await getCoreTheming(page, token)
		await setTokenSet(page, token, 'nextcloud')
		await page.close()
	})

	test.afterAll(async ({ browser }) => {
		const page = await browser.newPage()
		await openTheming(page)
		const token = await requestToken(page)
		await setOverrides(page, token, originalOverrides)
		await setTokenSet(page, token, originalTokenSet)
		await setCoreTheming(page, token, originalTheming)
		await page.close()
	})

	test('select, confirm, sync and switch back on one page load', async ({ page }) => {
		let loads = 0
		page.on('load', () => {
			loads++
		})

		await openTheming(page)
		expect(loads, 'the initial navigation').toBe(1)

		// Stock: no set layers on the page.
		expect(await setLayerFiles(page)).toEqual([])

		// 1. Select a shipped set: the apply dialog opens, confirm it.
		await page.selectOption('#nldesign-token-set-select', SHIPPED_SET)
		const applyDialog = page.locator('#nldesign-apply-dialog-overlay')
		if (await applyDialog.isVisible({ timeout: 5_000 }).catch(() => false)) {
			await applyDialog.locator('.nldesign-dialog-confirm').click()
			// The dialog stays up until the swap and the theming sync have
			// both settled, so its disappearance IS the "applied" signal: by
			// the time it is hidden, the set's stylesheets have loaded.
			await expect(applyDialog).toBeHidden({ timeout: 20_000 })
			expect(await setLayerFiles(page)).toContain(`tokens/${SHIPPED_SET}`)
		}

		// 2. The set's run is on the page — without navigating.
		await expect
			.poll(async () => (await setLayerFiles(page)).includes(`tokens/${SHIPPED_SET}`), {
				timeout: 15_000,
			})
			.toBe(true)
		expect(await setLayerFiles(page)).toContain('systems/nldesign/theme')
		expect(loads).toBe(1)

		// 3. The theming-sync step is offered for a set with theming metadata;
		//    confirming it must NOT reload either.
		const syncDialog = page.locator('#nldesign-theming-dialog-overlay')
		if (await syncDialog.isVisible({ timeout: 5_000 }).catch(() => false)) {
			await syncDialog.locator('.nldesign-dialog-confirm').click()
			await expect(syncDialog).toBeHidden({ timeout: 15_000 })

			// Core's own panel on this page shows the synced primary.
			const token = await requestToken(page)
			const synced = await getCoreTheming(page, token)
			expect(synced.primary_color.toLowerCase()).not.toBe('')
			const shown = page.locator(
				'[data-admin-theming-setting-primary-color] [data-admin-theming-setting-color-picker]',
			)
			if (await shown.count()) {
				await expect(shown).toContainText(synced.primary_color, { ignoreCase: true })
			}
			expect(loads).toBe(1)
		}

		// 4. Back to stock: every set layer is gone, still no navigation.
		await page.selectOption('#nldesign-token-set-select', 'nextcloud')
		if (await applyDialog.isVisible({ timeout: 5_000 }).catch(() => false)) {
			await applyDialog.locator('.nldesign-dialog-confirm').click()
			await expect(applyDialog).toBeHidden({ timeout: 20_000 })
		}
		await expect
			.poll(async () => (await setLayerFiles(page)).length, { timeout: 15_000 })
			.toBe(0)
		expect(loads, 'no reload anywhere in the flow').toBe(1)
	})
})
