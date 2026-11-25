/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/marianne-font/spec.md
 *
 * The Marianne acknowledgement gate in a real browser: off, the page links no
 * Marianne stylesheet, asks for no Marianne file and renders Inter; on, it
 * links the gated stylesheet after the fonts layer and loads Marianne from
 * the app itself; off again, Inter is back. The admin page shows the
 * restriction notice beside the control.
 *
 * GLOBAL STATE: `lasuite` is made the active set and its overrides emptied
 * (activateTokenSet), and the gate is set; both are put back afterwards.
 */
import { test, expect, type Page } from '@playwright/test'
import {
	activateTokenSet,
	indexOfCss,
	openThemedPage,
	stylesheetPaths,
} from './_token-css'
import { requestToken } from '../workflows/_helpers'

const THEMING_URL = '/settings/admin/theming'

/** Set the Marianne acknowledgement gate; returns what it was. */
async function setMarianne(page: Page, enabled: boolean): Promise<boolean> {
	await page.goto(THEMING_URL)
	await page.waitForLoadState('domcontentloaded')
	const token = await requestToken(page)
	return page.evaluate(
		async ({ t, on }) => {
			const OC = (
				window as unknown as { OC: { generateUrl: (p: string) => string } }
			).OC
			const url = OC.generateUrl('/apps/thematiq/settings/marianne')
			const before = (await (
				await fetch(url, { headers: { requesttoken: t } })
			).json()) as { enabled: boolean }
			const r = await fetch(url, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', requesttoken: t },
				body: JSON.stringify({ enabled: on }),
			})
			if (!r.ok) throw new Error(`marianne gate: HTTP ${r.status}`)
			return before.enabled
		},
		{ t: token, on: enabled },
	)
}

/** What a themed page loads and renders for the fonts. */
async function fontState(page: Page): Promise<{
	requests: { url: string; status: number }[]
	paths: string[]
	loaded: string[]
}> {
	const requests: { url: string; status: number }[] = []
	const listener = (r: import('@playwright/test').Response): void => {
		requests.push({ url: r.url(), status: r.status() })
	}
	page.on('response', listener)
	try {
		await openThemedPage(page)
		await page.evaluate(() => document.fonts.ready)
		// Body text is drawn, so the faces it uses have been asked for.
		await page.evaluate(async () => {
			const family = getComputedStyle(document.body).fontFamily
			await document.fonts.load(`16px ${family}`, 'Bonjour')
		})
		const loaded = await page.evaluate(() =>
			[...document.fonts]
				.filter((f) => f.status === 'loaded')
				.map((f) => f.family.replace(/["']/g, '')),
		)
		return { requests, paths: await stylesheetPaths(page), loaded }
	} finally {
		page.off('response', listener)
	}
}

const isMarianneFile = (url: string): boolean => /marianne[^/]*\.woff2/i.test(url)

test.describe('marianne-font', () => {
	test.describe.configure({ mode: 'serial', timeout: 120_000 })

	let restoreSet: (() => Promise<void>) | null = null
	let gateBefore: boolean | null = null

	test.beforeAll(async ({ browser }) => {
		restoreSet = await activateTokenSet(browser, 'lasuite')
	})

	test.afterAll(async ({ browser }) => {
		if (gateBefore !== null) {
			const page = await browser.newPage()
			try {
				await setMarianne(page, gateBefore)
			} finally {
				await page.close()
			}
		}
		if (restoreSet !== null) {
			await restoreSet()
		}
	})

	test(
		// @e2e marianne-font::notice-shows-beside-the-acknowledgement-control
		'the admin page shows the restriction notice beside the control',
		async ({ page }) => {
			await page.goto(THEMING_URL)
			await page.waitForLoadState('domcontentloaded')
			const gate = page.locator('#nldesign-marianne-gate')
			await expect(gate).toBeVisible({ timeout: 30_000 })
			await expect(gate.locator('#nldesign-marianne-enabled')).toBeVisible()
			const notice = gate.locator('#nldesign-marianne-notice')
			await expect(notice).toBeVisible()
			await expect(notice).toContainText('French State')
			await expect(notice).toContainText('Inter')
		},
	)

	test(
		// @e2e marianne-font::default-off-marianne-never-loads
		'off, no Marianne stylesheet or file, and the text renders in Inter',
		async ({ page }) => {
			gateBefore = await setMarianne(page, false)
			const state = await fontState(page)
			expect(indexOfCss(state.paths, 'systems/lasuite/marianne')).toBe(-1)
			expect(state.requests.filter((r) => isMarianneFile(r.url))).toEqual([])
			expect(state.loaded).toContain('Inter')
			expect(state.loaded).not.toContain('Marianne')
		},
	)

	test(
		// @e2e marianne-font::enabled-marianne-self-hosts-and-renders
		'on, the gated stylesheet follows the fonts layer and Marianne loads from the app',
		async ({ page }) => {
			await setMarianne(page, true)
			const state = await fontState(page)
			const fonts = indexOfCss(state.paths, 'systems/lasuite/fonts')
			const marianne = indexOfCss(state.paths, 'systems/lasuite/marianne')
			expect(marianne, 'the gated stylesheet is linked').toBeGreaterThan(fonts)
			expect(fonts).toBeGreaterThanOrEqual(0)

			const files = state.requests.filter((r) => isMarianneFile(r.url))
			expect(files.length, 'a Marianne file is loaded').toBeGreaterThan(0)
			const origin = new URL(page.url()).origin
			for (const file of files) {
				expect(file.status, file.url).toBe(200)
				expect(new URL(file.url).origin, file.url).toBe(origin)
				expect(new URL(file.url).pathname, file.url).toContain('/thematiq/')
			}
			expect(state.loaded).toContain('Marianne')
		},
	)

	test(
		// @e2e marianne-font::reverting-the-gate-returns-to-inter
		'off again, the gated stylesheet is gone and Inter renders',
		async ({ page }) => {
			await setMarianne(page, false)
			const state = await fontState(page)
			expect(indexOfCss(state.paths, 'systems/lasuite/marianne')).toBe(-1)
			expect(state.requests.filter((r) => isMarianneFile(r.url))).toEqual([])
			expect(state.loaded).toContain('Inter')
			expect(state.loaded).not.toContain('Marianne')
		},
	)
})
