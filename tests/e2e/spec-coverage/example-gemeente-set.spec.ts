/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The fictional "(EXAMPLE) Gemeente" set in a real browser: it is offered in
 * the token set dropdown, applying it paints its primary colour and its logo
 * is served by the app, and its font, Source Sans 3, loads from the app's
 * own files and from nowhere else.
 *
 * GLOBAL STATE: the set is offered through a group mapping (offerTokenSets)
 * where the selection policy does not already list it, and made the active
 * set with its overrides emptied (activateTokenSet). Both are put back.
 */
import { readFileSync } from 'fs'
import { join } from 'path'
import { test, expect } from '@playwright/test'
import { activateTokenSet, openThemedPage } from './_token-css'
import {
	appAssetUrl,
	offerTokenSets,
	requestToken,
	withdrawTokenSetOffer,
} from '../workflows/_helpers'

const SET = 'example-gemeente'
const ROOT = join(__dirname, '..', '..', '..')
const MANIFEST = JSON.parse(readFileSync(join(ROOT, 'token-sets.json'), 'utf8')) as {
	id: string
	name: string
	theming?: Record<string, string>
}[]
const ENTRY = MANIFEST.find((e) => e.id === SET)

test.describe('the example municipality set', () => {
	test(
		// @e2e token-sets::the-set-is-listed-and-selectable
		'it is offered in the dropdown, and applying it paints its primary and serves its logo',
		async ({ page, browser }) => {
			test.setTimeout(120_000)
			expect(ENTRY, 'token-sets.json lists the set').toBeDefined()
			expect(ENTRY?.name).toBe('(EXAMPLE) Gemeente')
			expect(ENTRY?.theming?.primary_color).toBe('#12506B')
			expect(ENTRY?.theming?.logo).toBe('img/logos/example-gemeente.svg')

			await page.goto('/settings/admin/theming')
			await page.waitForLoadState('domcontentloaded')
			const token = await requestToken(page)
			const offer = await offerTokenSets(page, token, [SET])
			try {
				await page.goto('/settings/admin/theming')
				await page.waitForLoadState('domcontentloaded')
				const option = page.locator(
					`#nldesign-token-set-select option[value="${SET}"]`,
				)
				await expect(option).toBeAttached()
				await expect(option).toHaveText(/\(EXAMPLE\) Gemeente/)

				const restore = await activateTokenSet(browser, SET)
				try {
					await openThemedPage(page)
					const primary = await page.evaluate(() => {
						const probe = document.createElement('span')
						probe.style.color = 'var(--color-primary)'
						document.body.appendChild(probe)
						const colour = getComputedStyle(probe).color
						probe.remove()
						return colour
					})
					expect(primary).toBe('rgb(18, 80, 107)')

					const logo = await appAssetUrl(
						page,
						'img',
						'logos/example-gemeente.svg',
					)
					const res = await page.request.get(logo)
					expect(res.status(), logo).toBe(200)
				} finally {
					await restore()
				}
			} finally {
				await page.goto('/settings/admin/theming')
				await page.waitForLoadState('domcontentloaded')
				await withdrawTokenSetOffer(page, await requestToken(page), offer)
			}
		},
	)

	test.describe('with the set active', () => {
		test.describe.configure({ mode: 'serial', timeout: 120_000 })

		let restore: (() => Promise<void>) | null = null

		test.beforeAll(async ({ browser }) => {
			restore = await activateTokenSet(browser, SET)
		})

		test.afterAll(async () => {
			if (restore !== null) {
				await restore()
			}
		})

		test(
			// @e2e token-sets::the-font-loads-from-the-app
			'body text renders in Source Sans 3 from the app, and no font comes from elsewhere',
			async ({ page }) => {
				const fonts: string[] = []
				page.on('response', (r) => {
					if (r.request().resourceType() === 'font') fonts.push(r.url())
				})
				await openThemedPage(page)
				await page.evaluate(async () => {
					await document.fonts.ready
					const family = getComputedStyle(document.body).fontFamily
					await document.fonts.load(`16px ${family}`, 'Esdoornstad')
				})

				const family = await page.evaluate(
					() => getComputedStyle(document.body).fontFamily,
				)
				expect(family.split(',')[0].trim().replace(/["']/g, '')).toBe(
					'Source Sans 3',
				)
				const loaded = await page.evaluate(() =>
					[...document.fonts]
						.filter((f) => f.status === 'loaded')
						.map((f) => f.family.replace(/["']/g, '')),
				)
				expect(loaded).toContain('Source Sans 3')

				const origin = new URL(page.url()).origin
				const sourceSans = fonts.filter((u) => /source-sans-3/.test(u))
				expect(
					sourceSans.length,
					'a Source Sans 3 file is fetched',
				).toBeGreaterThan(0)
				for (const url of sourceSans) {
					expect(new URL(url).pathname, url).toContain(
						'/thematiq/css/fonts/',
					)
				}
				for (const url of fonts) {
					expect(
						new URL(url).origin,
						`${url} is not a third-party font host`,
					).toBe(origin)
				}
			},
		)
	})
})
