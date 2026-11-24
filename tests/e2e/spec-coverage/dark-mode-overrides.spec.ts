/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * An administrator's dark value reaches every dark user the same way, a
 * light-theme user keeps the light value, and the instance-wide toggle turns
 * the dark variants off without deleting them.
 *
 * A user's theme choice is what Nextcloud writes on <body>
 * (`data-theme-dark`, `data-theme-light`, or none for "System default"), so
 * the tests set that attribute and emulate the operating system's colour
 * scheme, as dark-mode-scope.spec.ts does. The colour is read off an element
 * in the page, where the theme scopes apply.
 *
 * GLOBAL STATE: Zuiddrecht is made the active set with its overrides emptied
 * (activateTokenSet), then given one light and one dark primary colour; the
 * dark-variants toggle is flipped. Everything is put back afterwards.
 */
import { test, expect, type Page } from '@playwright/test'
import { activateTokenSet, openThemedPage } from './_token-css'
import { adminContext } from './_fixtures'
import { requestToken } from '../workflows/_helpers'

const THEMING_URL = '/settings/admin/theming'
const LIGHT = '#154273'
const DARK = '#8fb8e6'

/** `rgb(r, g, b)` as `#rrggbb`. */
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

/** POST /settings/overrides with a light and a dark map. */
async function saveOverrides(
	page: Page,
	overrides: Record<string, string>,
	darkOverrides: Record<string, string>,
): Promise<void> {
	await page.goto(THEMING_URL)
	await page.waitForLoadState('domcontentloaded')
	const token = await requestToken(page)
	const status = await page.evaluate(
		async ({ t, ov, dk }) => {
			const OC = (
				window as unknown as { OC: { generateUrl: (p: string) => string } }
			).OC
			const r = await fetch(
				OC.generateUrl('/apps/thematiq/settings/overrides'),
				{
					method: 'POST',
					headers: { 'Content-Type': 'application/json', requesttoken: t },
					body: JSON.stringify({ overrides: ov, darkOverrides: dk }),
				},
			)
			return r.status
		},
		{ t: token, ov: overrides, dk: darkOverrides },
	)
	expect(status, 'the overrides are saved').toBe(200)
}

/** Put a user's theme choice on <body>, as Nextcloud renders it; '' is System default. */
async function chooseTheme(page: Page, theme: '' | 'dark' | 'light'): Promise<void> {
	await page.evaluate((choice) => {
		const body = document.body
		for (const name of body.getAttributeNames()) {
			if (name.startsWith('data-theme-')) body.removeAttribute(name)
		}
		body.setAttribute('data-themes', choice)
		if (choice !== '') body.setAttribute(`data-theme-${choice}`, '')
	}, theme)
}

/** The primary colour a component in the page paints with. */
async function primaryInPage(page: Page): Promise<string> {
	return page.evaluate(() => {
		const probe = document.createElement('span')
		probe.style.color = 'var(--color-primary)'
		document.body.appendChild(probe)
		const colour = getComputedStyle(probe).color
		probe.remove()
		return colour
	})
}

/** The primary colour after the page has settled on a theme. */
async function seenPrimary(page: Page): Promise<string> {
	await page.evaluate(
		() =>
			new Promise<void>((r) =>
				requestAnimationFrame(() => requestAnimationFrame(() => r())),
			),
	)
	return rgbToHex(await primaryInPage(page))
}

test.describe('dark overrides for every dark user', () => {
	test.describe.configure({ mode: 'serial', timeout: 120_000 })

	let restore: (() => Promise<void>) | null = null

	test.beforeAll(async ({ browser }) => {
		restore = await activateTokenSet(browser, 'zuiddrecht')
		const ctx = await adminContext(browser)
		const page = await ctx.newPage()
		try {
			await saveOverrides(
				page,
				{ '--color-primary': LIGHT },
				{ '--color-primary': DARK },
			)
		} finally {
			await ctx.close()
		}
	})

	test.afterAll(async () => {
		// activateTokenSet's restore puts the admin's own overrides back.
		if (restore !== null) {
			await restore()
		}
	})

	test(
		// @e2e dark-mode::both-kinds-of-dark-user-see-the-same-override
		'a System-default user on a dark OS and a user who chose dark see the same dark value',
		async ({ page }) => {
			await page.emulateMedia({ colorScheme: 'dark' })
			await openThemedPage(page)
			await chooseTheme(page, '')
			expect(await seenPrimary(page), 'System default, dark OS').toBe(DARK)

			await page.emulateMedia({ colorScheme: 'light' })
			await chooseTheme(page, 'dark')
			expect(await seenPrimary(page), 'chose dark, light OS').toBe(DARK)
		},
	)

	test(
		// @e2e dark-mode::a-light-theme-user-is-not-affected-by-dark-values
		'a user who chose light keeps the light value on a dark OS',
		async ({ page }) => {
			await page.emulateMedia({ colorScheme: 'dark' })
			await openThemedPage(page)
			await chooseTheme(page, 'light')
			expect(await seenPrimary(page)).toBe(LIGHT)
		},
	)
})

test.describe('the dark variants toggle', () => {
	test(
		// @e2e dark-mode::toggle-disables-dark-variants
		'off, no page links a dark stylesheet, and the files stay served',
		async ({ page }) => {
			const darkLinks = async (): Promise<string[]> => {
				await openThemedPage(page)
				return page.evaluate(() =>
					[
						...document.querySelectorAll<HTMLLinkElement>(
							'link[rel=stylesheet]',
						),
					]
						.map((l) => l.href)
						.filter((h) => h.includes('/css/tokens/dark/')),
				)
			}
			const setToggle = async (on: boolean): Promise<boolean> => {
				await page.goto(THEMING_URL)
				await page.waitForLoadState('domcontentloaded')
				const token = await requestToken(page)
				return page.evaluate(
					async ({ t, enabled }) => {
						const OC = (
							window as unknown as {
								OC: { generateUrl: (p: string) => string }
							}
						).OC
						const url = OC.generateUrl(
							'/apps/thematiq/settings/dark-variants',
						)
						const before = (await (
							await fetch(url, { headers: { requesttoken: t } })
						).json()) as { enabled: boolean }
						const r = await fetch(url, {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								requesttoken: t,
							},
							body: JSON.stringify({ enabled }),
						})
						if (!r.ok) throw new Error(`dark variants: HTTP ${r.status}`)
						return before.enabled
					},
					{ t: token, enabled: on },
				)
			}

			const was = await setToggle(true)
			try {
				const before = await darkLinks()
				expect(
					before.length,
					'with the toggle on a dark stylesheet loads',
				).toBeGreaterThan(0)

				await setToggle(false)
				expect(await darkLinks()).toEqual([])
				// The generated files are still there.
				for (const href of before) {
					expect((await page.request.get(href)).status(), href).toBe(200)
				}
			} finally {
				await setToggle(was)
			}
		},
	)
})
