/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/nl-design/spec.md#component-uses-color
 *
 * The nldesign system stylesheets an instance SERVES hardcode no colour
 * (thematiq#769).
 *
 * tests/vitest/nldesignNoHardcodedColour.spec.js reads the files in the
 * repository. This spec reads what a browser actually receives: the
 * stylesheets the page links while an nldesign set is active. A colour is
 * hardcoded when a hex value or an rgb()/hsl() function is left in an
 * ordinary declaration after every var() call, fallback included, is removed.
 * Custom property declarations define tokens and are out of scope, and so is
 * defaults.css, the file that defines the token defaults.
 *
 * GLOBAL STATE: the active set is switched to `amsterdam` (an nldesign set)
 * and restored in afterAll.
 */

import { test, expect } from '@playwright/test'
import { THEMING_URL, getTokenSet, requestToken, setTokenSet } from '../workflows/_helpers'

const HARDCODED = /#[0-9a-f]{3,8}\b|\b(rgba?|hsla?)\(/i

/**
 * Remove every var(...) call, fallbacks included, innermost first.
 *
 * @param value A declaration value.
 * @return The value with no var() left.
 */
function stripVars(value: string): string {
	let previous = ''
	let current = value
	while (current !== previous) {
		previous = current
		current = current.replace(/var\([^()]*\)/gi, '')
	}
	return current
}

/**
 * Declarations in a stylesheet that hardcode a colour.
 *
 * @param css Stylesheet text.
 * @return `property: value` per offending declaration.
 */
function hardcoded(css: string): string[] {
	const text = css.replace(/\/\*[\s\S]*?\*\//g, '')
	const out: string[] = []
	for (const match of text.matchAll(/(^|[;{])\s*([a-z-]+)\s*:\s*([^;{}]+)/gi)) {
		const [, , prop, value] = match
		if (prop.startsWith('--') === false && HARDCODED.test(stripVars(value)) === true) {
			out.push(`${prop}: ${value.trim()}`)
		}
	}
	return out
}

test.describe('nl-design: served system stylesheets use tokens for colour', () => {
	let baseline: string | null = null

	test.beforeAll(async ({ browser }) => {
		const page = await browser.newPage()
		await page.goto(THEMING_URL, { waitUntil: 'domcontentloaded' })
		const token = await requestToken(page)
		baseline = await getTokenSet(page, token)
		await setTokenSet(page, token, 'amsterdam')
		await page.close()
	})

	test.afterAll(async ({ browser }) => {
		if (baseline === null) return
		const page = await browser.newPage()
		await page.goto(THEMING_URL, { waitUntil: 'domcontentloaded' })
		await setTokenSet(page, await requestToken(page), baseline)
		await page.close()
	})

	test('Component uses color: no served nldesign stylesheet hardcodes a colour', async ({ page }) => {
		await page.goto('/apps/files/', { waitUntil: 'domcontentloaded' })
		const hrefs = await page.evaluate(() =>
			[...document.querySelectorAll('link[rel="stylesheet"]')]
				.map((link) => (link as HTMLLinkElement).href)
				.filter((href) => href.includes('/css/systems/nldesign/'))
				.filter((href) => href.includes('/defaults.css') === false),
		)

		// A guard that finds no stylesheet has measured nothing: fail, do not pass.
		expect(hrefs.some((href) => href.includes('/theme.css'))).toBe(true)
		expect(hrefs.some((href) => href.includes('/element-overrides.css'))).toBe(true)

		const offenders: string[] = []
		for (const href of hrefs) {
			const response = await page.request.get(href)
			expect(response.ok()).toBe(true)
			for (const finding of hardcoded(await response.text())) {
				offenders.push(`${new URL(href).pathname} ${finding}`)
			}
		}
		expect(offenders).toEqual([])
	})
})
