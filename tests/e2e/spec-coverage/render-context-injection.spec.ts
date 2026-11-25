/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/css-architecture/spec.md
 * @e2e openspec/specs/theming-capability/spec.md
 *
 * Render-event injection: every render context (logged-in, login/guest,
 * error) receives the full nldesign cascade in the documented order, and the
 * huisstijl is published through the public capability.
 */
import { test, expect, Page } from '@playwright/test'

import { activateTokenSet } from './_token-css'

async function nldesignStyles(page: Page): Promise<string[]> {
	return page.evaluate(() =>
		[...document.querySelectorAll('link[rel=stylesheet]')]
			.map((l) => (l as HTMLLinkElement).href)
			.filter((h) => h.includes('/thematiq/')),
	)
}

test.describe('render-context CSS injection', () => {
	test('a logged-in page receives the themed cascade', async ({
		page,
		browser,
	}) => {
		// The instance default is the stock `nextcloud` set, which wears no
		// design system and links no system or token layer; a set that does
		// is made active for the measurement: Zuiddrecht, unrestricted.
		const restore = await activateTokenSet(browser, 'zuiddrecht')
		try {
			await page.goto('/settings/user')
			const styles = await nldesignStyles(page)
			expect(styles.length).toBeGreaterThan(0)
			// Design-system layer precedes the token layer, which precedes overrides.
			const sys = styles.findIndex((h) => h.includes('/css/systems/'))
			const tok = styles.findIndex((h) => /\/css\/tokens\/[^/]+\.css/.test(h))
			const ovr = styles.findIndex((h) => h.includes('custom-overrides'))
			expect(sys).toBeGreaterThanOrEqual(0)
			expect(tok).toBeGreaterThan(sys)
			expect(ovr).toBeGreaterThan(tok)
		} finally {
			await restore()
		}
	})

	test('the login page (guest render) is themed too', async ({ browser }) => {
		// A fresh context with no stored session lands on the real login page.
		const ctx = await browser.newContext({
			storageState: { cookies: [], origins: [] },
		})
		const page = await ctx.newPage()
		await page.goto('/index.php/login')
		await page
			.locator('input[name="user"]')
			.waitFor({ state: 'visible', timeout: 30_000 })

		expect((await nldesignStyles(page)).length).toBeGreaterThan(0)
		await ctx.close()
	})

	test('an error page keeps the huisstijl (previously unthemed)', async ({
		page,
	}) => {
		await page.goto('/index.php/apps/definitely-not-an-app/', {
			waitUntil: 'domcontentloaded',
		})
		expect(
			(await nldesignStyles(page)).length,
			'error/404 renders must stay branded',
		).toBeGreaterThan(0)
	})

	test('the active huisstijl is published pre-login via the OCS capability', async ({
		request,
	}) => {
		const res = await request.get('/ocs/v2.php/cloud/capabilities?format=json', {
			headers: { 'OCS-APIRequest': 'true' },
		})
		expect(res.status()).toBe(200)

		const cap = (await res.json()).ocs.data.capabilities.nldesign
		expect(cap, 'nldesign capability must be present').toBeTruthy()
		expect(cap.tokenSet).toHaveProperty('id')
		expect(cap.designSystem).toBeTruthy()
		// Strict allowlist — private config must never leak into a public doc.
		//
		// `iconPacks` is the EIGHTH key and belongs here: it is a specified
		// public capability, not a leak. openspec/specs/icon-packs/spec.md
		// requires "capabilities.nldesign.iconPacks MUST equal [\"dsfr\"]" for a
		// dsfr override and "MUST include iconPacks as an empty array" when no
		// pack resolves, and lib/Capabilities.php documents an "eight-key
		// payload". This list was written at seven and never updated, because
		// this spec had never been executed — the first CI run of the suite
		// (30889958278) is what surfaced the drift. Adding it is correcting a
		// stale fixture against the canonical spec, not widening the gate: the
		// assertion stays an exact set comparison, so a NINTH key still fails.
		expect(Object.keys(cap).sort()).toEqual(
			[
				'designSystem',
				'hideSlogan',
				'iconPacks',
				'logos',
				'showMenuLabels',
				'tokenSet',
				'version',
				'wcagLevel',
			].sort(),
		)
	})
})
