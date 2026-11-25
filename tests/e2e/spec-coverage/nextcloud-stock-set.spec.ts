/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The `nextcloud` ("stock") token set in a real browser: its tokens are what
 * this instance's own theme computes, delivered as the inline
 * `<style id="nldesign-stock-tokens">` block, never the shipped
 * css/tokens/nextcloud.css, and they follow the primary colour an
 * administrator sets in Nextcloud's own theming.
 *
 * GLOBAL STATE: `nextcloud` is made the active set with its overrides emptied
 * (activateTokenSet), and core's primary colour is changed through core's own
 * endpoint; core theming is captured before and restored after
 * (captureCoreTheming / restoreCoreTheming).
 */
import { test, expect, type Page } from '@playwright/test'
import {
	activateTokenSet,
	indexOfCss,
	openThemedPage,
	stylesheetPaths,
} from './_token-css'
import { captureCoreTheming, restoreCoreTheming } from './_core-theming'

const STOCK_STYLE = '#nldesign-stock-tokens'

/** The value the inline stock block declares for `name`, lower-cased. */
async function stockToken(page: Page, name: string): Promise<string> {
	const css = await page.locator(STOCK_STYLE).textContent()
	expect(css, 'the stock block is on the page').not.toBeNull()
	const match = new RegExp(`${name}\\s*:\\s*([^;]+);`).exec(css as string)
	expect(match, `${name} is declared in the stock block`).not.toBeNull()
	return (match as RegExpExecArray)[1].trim().toLowerCase()
}

/** Core's primary colour, as any client reads it. */
async function corePrimary(page: Page): Promise<string> {
	return page.evaluate(async () => {
		const r = await fetch('/ocs/v2.php/cloud/capabilities?format=json', {
			headers: { 'OCS-APIRequest': 'true' },
		})
		const json = await r.json()
		return String(json.ocs.data.capabilities.theming.color).toLowerCase()
	})
}

/** Set core's primary colour the way core's own admin page does. */
async function setCorePrimary(page: Page, colour: string): Promise<void> {
	const status = await page.evaluate(async (value) => {
		const OC = (
			window as unknown as {
				OC: { generateUrl: (p: string) => string; requestToken: string }
			}
		).OC
		const r = await fetch(
			OC.generateUrl('/apps/theming/ajax/updateStylesheet'),
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					requesttoken: OC.requestToken,
				},
				body: new URLSearchParams({
					setting: 'primary_color',
					value,
				}).toString(),
			},
		)
		return r.status
	}, colour)
	expect(status, 'core accepts the primary colour').toBe(200)
}

test.describe('the stock nextcloud set', () => {
	test.describe.configure({ mode: 'serial', timeout: 120_000 })

	let restore: (() => Promise<void>) | null = null

	test.beforeAll(async ({ browser }) => {
		restore = await activateTokenSet(browser, 'nextcloud')
	})

	test.afterAll(async () => {
		if (restore !== null) {
			await restore()
		}
	})

	test(
		// @e2e nextcloud-variable-mapping::the-set-follows-the-installed-version
		'its tokens come from this instance as an inline block, not from the shipped file',
		async ({ page }) => {
			await openThemedPage(page)
			await expect(page.locator(STOCK_STYLE)).toBeAttached()
			expect(indexOfCss(await stylesheetPaths(page), 'tokens/nextcloud')).toBe(
				-1,
			)
			// The value is the one this instance computes: core's own primary.
			expect(await stockToken(page, '--nldesign-color-primary')).toBe(
				await corePrimary(page),
			)
		},
	)

	test(
		// @e2e nextcloud-variable-mapping::the-set-follows-core-theming
		'a primary colour set in core theming is the primary the set carries',
		async ({ page }) => {
			await openThemedPage(page)
			const capture = await captureCoreTheming(page)
			try {
				const before = await stockToken(page, '--nldesign-color-primary')
				const colour = before === '#3a5f0b' ? '#5b2a86' : '#3a5f0b'
				await setCorePrimary(page, colour)
				await openThemedPage(page)
				expect(await stockToken(page, '--nldesign-color-primary')).toBe(
					colour,
				)
			} finally {
				await restoreCoreTheming(page, capture)
			}
		},
	)
})
