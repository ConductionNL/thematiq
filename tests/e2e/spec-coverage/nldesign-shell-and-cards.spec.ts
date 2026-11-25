/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The solid app shell, the dashboard card rule and the text on the light
 * primary, proved in a real browser.
 *
 * Each block makes one set active, opens a themed page and either reads what
 * the cascade resolved or adds the elements a rule is written for and reads
 * their computed style. A nextcloud-vue screen named by a scenario (an app's
 * shell, a dashboard card, the board's save-view button) is built here from
 * the class names and the variables the library uses, so the test needs no
 * other app installed. Expected values come from css/tokens/zuiddrecht.css.
 *
 * GLOBAL STATE: each block switches the active set and empties its admin
 * overrides; both are restored in afterAll (see activateTokenSet).
 */
import { test, expect, type Page } from '@playwright/test'
import { activateTokenSet, contrastRatio, openThemedPage } from './_token-css'

/** Turn `rgb(r, g, b)` into `#rrggbb`, the notation the token file uses. */
function rgbToHex(rgb: string): string {
	const parts = rgb.match(/\d+(\.\d+)?/g) ?? []
	return (
		'#'
		+ parts
			.slice(0, 3)
			.map((n) => Math.round(Number(n)).toString(16).padStart(2, '0'))
			.join('')
	)
}

/**
 * Add `html` and an optional stylesheet to the page, and return the computed
 * style properties `props` of the element matching `selector`.
 *
 * The stylesheet stands in for the library's own rule, so it is written at
 * the library's specificity (class selectors only): a theme rule that is
 * meant to win over the library must win over it too.
 */
async function computedOf(
	page: Page,
	html: string,
	selector: string,
	props: string[],
	css = '',
): Promise<Record<string, string>> {
	return page.evaluate(
		({ html, selector, props, css }) => {
			const host = document.createElement('div')
			host.id = 'thematiq-e2e-host'
			host.innerHTML = html
			document.body.appendChild(host)
			const style = document.createElement('style')
			style.textContent = css
			document.head.prepend(style)
			const el = host.querySelector(selector)
			if (el === null) {
				throw new Error(`no element matches ${selector}`)
			}
			const cs = getComputedStyle(el)
			const out: Record<string, string> = {}
			for (const p of props) {
				out[p] = cs.getPropertyValue(p).trim()
			}
			host.remove()
			style.remove()
			return out
		},
		{ html, selector, props, css },
	)
}

/** A nextcloud-vue dashboard with one widget card. */
const DASHBOARD_CARD =
	'<div class="cn-dashboard-page"><div class="cn-widget-wrapper">'
	+ '<div class="cn-widget-wrapper__header">Registers</div>'
	+ '<div class="cn-widget-wrapper__content">12</div></div></div>'

/** The rule nextcloud-vue draws under a widget card's title. */
const CARD_HEADER_RULE =
	'.cn-widget-wrapper__header { border-bottom: 1px solid rgb(200, 200, 200); }'

test.describe('under the Zuiddrecht set', () => {
	test.describe.configure({ mode: 'serial', timeout: 90_000 })

	let restore: (() => Promise<void>) | null = null

	test.beforeAll(async ({ browser }) => {
		restore = await activateTokenSet(browser, 'zuiddrecht')
	})

	test.afterAll(async () => {
		if (restore !== null) {
			await restore()
		}
	})

	test.beforeEach(async ({ page }) => {
		await openThemedPage(page)
	})

	test(
		// @e2e nldesign-solid-app-shell::the-ai-chat-button-sits-on-a-white-shell
		'the app shell is the solid main background',
		async ({ page }) => {
			// NcContent (#content-vue) paints itself with the frosted variable.
			const shell = await computedOf(
				page,
				'<div class="e2e-content-vue">Werkplek</div>',
				'.e2e-content-vue',
				['background-color'],
				'.e2e-content-vue { background-color: var(--color-main-background-blur); }',
			)
			expect(shell['background-color']).toBe('rgb(255, 255, 255)')
		},
	)

	test(
		// @e2e cunningham-cards-token::openregisters-dashboard-under-zuiddrecht
		'a dashboard card draws no rule under its title',
		async ({ page }) => {
			const header = await computedOf(
				page,
				DASHBOARD_CARD,
				'.cn-widget-wrapper__header',
				['border-bottom-style'],
				CARD_HEADER_RULE,
			)
			expect(header['border-bottom-style']).toBe('none')
		},
	)

	test(
		// @e2e primary-light-text-step::the-boards-save-view-button-under-zuiddrecht
		'text on the light primary hover is the light-text step, 4.5:1 or more',
		async ({ page }) => {
			const button = await computedOf(
				page,
				'<button class="e2e-save-view">Weergave opslaan</button>',
				'.e2e-save-view',
				['color', 'background-color'],
				'.e2e-save-view { color: var(--color-primary-element-light-text); background-color: var(--color-primary-element-light-hover); }',
			)
			const text = rgbToHex(button.color)
			expect(text).toBe('#234a78')
			expect(contrastRatio(text, '#dce6f2')).toBeGreaterThanOrEqual(4.5)
		},
	)

	test(
		// @e2e nldesign-solid-app-shell::the-login-card-stays-frosted
		'the login card keeps the frosted background',
		async ({ browser }) => {
			// Logged out: the login page is only shown to a visitor.
			const context = await browser.newContext({
				storageState: { cookies: [], origins: [] },
			})
			try {
				const page = await context.newPage()
				await page.goto('/index.php/login', {
					waitUntil: 'domcontentloaded',
				})
				await page.locator('#body-login').waitFor({ state: 'attached' })
				const vars = await page.evaluate(() => {
					const style = getComputedStyle(document.body)
					return {
						blur: style
							.getPropertyValue('--color-main-background-blur')
							.trim(),
						main: style
							.getPropertyValue('--color-main-background')
							.trim(),
					}
				})
				// Nextcloud's own value: the main background, translucent.
				expect(vars.blur).not.toBe('')
				expect(vars.blur).not.toBe(vars.main)
			} finally {
				await context.close()
			}
		},
	)
})

test.describe('under the Cunningham set', () => {
	test.describe.configure({ mode: 'serial', timeout: 90_000 })

	let restore: (() => Promise<void>) | null = null

	test.beforeAll(async ({ browser }) => {
		restore = await activateTokenSet(browser, 'cunningham')
	})

	test.afterAll(async () => {
		if (restore !== null) {
			await restore()
		}
	})

	test(
		// @e2e cunningham-dashboard-card-header-rule::a-dashboard-under-the-cunningham-set
		'no dashboard card draws a line between its title and its content',
		async ({ page }) => {
			await openThemedPage(page)
			const header = await computedOf(
				page,
				DASHBOARD_CARD,
				'.cn-widget-wrapper__header',
				['border-bottom-style'],
				CARD_HEADER_RULE,
			)
			expect(header['border-bottom-style']).toBe('none')
			// A chromeless card is left alone and keeps the library's rule.
			const chromeless = await computedOf(
				page,
				DASHBOARD_CARD.replace(
					'cn-widget-wrapper"',
					'cn-widget-wrapper cn-widget-wrapper--chromeless"',
				),
				'.cn-widget-wrapper__header',
				['border-bottom-style'],
				CARD_HEADER_RULE,
			)
			expect(chromeless['border-bottom-style']).toBe('solid')
		},
	)
})
