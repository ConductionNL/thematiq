/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/hide-slogan/spec.md
 *
 * Hide slogan, proven where it acts: on the real login page, opened in an
 * anonymous context, after the setting was toggled through the app's own API.
 *
 * Every test that touches `hide_slogan` (or the token set) goes through
 * withThemeState(), which puts the previous value back in a `finally`.
 * The stored value is read back through the public capability, which reports
 * `hide_slogan === '1'` (lib/Capabilities.php): a strict read of what IConfig
 * holds, not of what a page happens to render.
 */
import { test, expect, type Page } from '@playwright/test'

import {
	api,
	ensureNonAdminUser,
	loginAs,
	NONADMIN_PASS,
	NONADMIN_USER,
} from './_fixtures'
import {
	anonymousPage,
	openLoginPage,
	PROBE_URL,
	readToggles,
	thematiqLayers,
	tokenSetManifest,
	withThemeState,
} from './_theme-state'
import { openTheming } from '../workflows/_helpers'

const SLOGAN_ENDPOINT = '/index.php/apps/thematiq/settings/slogan'
const FOOTER = 'footer.guest-box'

/** Computed display/visibility of the login footer. */
async function footerStyle(
	page: Page,
): Promise<{ display: string; visibility: string; text: string }> {
	return page
		.locator(FOOTER)
		.first()
		.evaluate((el) => ({
			display: getComputedStyle(el).display,
			visibility: getComputedStyle(el).visibility,
			text: (el.textContent ?? '').trim(),
		}))
}

/** Open the login page anonymously, run `fn` on it, close it. */
async function onLoginPage(
	browser: import('@playwright/test').Browser,
	fn: (page: Page) => Promise<void>,
): Promise<void> {
	const anon = await anonymousPage(browser)
	try {
		await openLoginPage(anon.page)
		await fn(anon.page)
	} finally {
		await anon.close()
	}
}

/** The slogan as a reader meets it: the footer's computed style, and whether its text is on screen. */
async function sloganState(
	page: Page,
): Promise<{ display: string; visibility: string; onScreen: boolean }> {
	const footer = await footerStyle(page)
	// innerText leaves out display:none content, so this is what is on screen.
	const screen = await page.evaluate(() => document.body.innerText)
	return {
		display: footer.display,
		visibility: footer.visibility,
		onScreen: footer.text !== '' && screen.includes(footer.text),
	}
}

/** What sloganState() reads when the slogan is hidden. */
const HIDDEN = { display: 'none', visibility: 'hidden', onScreen: false }

test.use({ colorScheme: 'light' })

test.describe('hide-slogan', () => {
	// Not `mode: 'serial'`: every test sets and restores its own state through
	// withThemeState(), so none depends on the one before it. Serial mode would
	// skip the rest of the file after one failure, and keep all 20 tests (over
	// ten minutes) on one CI shard.
	test.describe.configure({ timeout: 90_000 })

	test(// @e2e openspec/specs/hide-slogan/spec.md#setting-stored-as-enabled
	// @e2e openspec/specs/hide-slogan/spec.md#true-boolean-converted-to-string-1
	// @e2e openspec/specs/hide-slogan/spec.md#toggle-slogan-hiding-on
	// @e2e openspec/specs/hide-slogan/spec.md#route-registration
	'POST /settings/slogan with true answers ok and stores the string 1', async ({
		browser,
		page,
	}) => {
		await withThemeState(browser, { hideSlogan: false }, async () => {
			await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
			// The route answers and reaches settings#setSloganSetting, which is the
			// only handler that echoes `hideSlogan`.
			const res = await api(page, 'POST', SLOGAN_ENDPOINT, {
				hideSlogan: true,
			})
			expect(res.status).toBe(200)
			expect(res.json).toEqual({ status: 'ok', hideSlogan: true })
			// The capability is true only for a stored '1'.
			expect((await readToggles(page)).hideSlogan).toBe(true)
		})
	})

	test(// @e2e openspec/specs/hide-slogan/spec.md#setting-stored-as-disabled
	// @e2e openspec/specs/hide-slogan/spec.md#false-boolean-converted-to-string-0
	// @e2e openspec/specs/hide-slogan/spec.md#toggle-slogan-hiding-off
	'POST /settings/slogan with false answers ok and stores the string 0', async ({
		browser,
		page,
	}) => {
		await withThemeState(browser, { hideSlogan: true }, async () => {
			await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
			const res = await api(page, 'POST', SLOGAN_ENDPOINT, {
				hideSlogan: false,
			})
			expect(res.status).toBe(200)
			expect(res.json).toEqual({ status: 'ok', hideSlogan: false })
			expect((await readToggles(page)).hideSlogan).toBe(false)
		})
	})

	test(// @e2e openspec/specs/hide-slogan/spec.md#non-admin-access-denied
	'a non-admin POST is refused and the setting does not move', async ({
		browser,
		page,
	}) => {
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
		await ensureNonAdminUser(page)
		await withThemeState(browser, { hideSlogan: false }, async () => {
			const user = await loginAs(browser, NONADMIN_USER, NONADMIN_PASS)
			try {
				await user.page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
				const res = await api(user.page, 'POST', SLOGAN_ENDPOINT, {
					hideSlogan: true,
				})
				expect(res.status).toBe(403)
			} finally {
				await user.close()
			}
			expect((await readToggles(page)).hideSlogan).toBe(false)
		})
	})

	test(// @e2e openspec/specs/hide-slogan/spec.md#feature-enabled-loads-css
	// @e2e openspec/specs/hide-slogan/spec.md#css-loading-position-in-cascade
	'enabled, the login page loads hide-slogan.css after layer 7 and custom-overrides', async ({
		browser,
	}) => {
		await withThemeState(browser, { hideSlogan: true }, async () => {
			await onLoginPage(browser, async (page) => {
				const layers = await thematiqLayers(page)
				const slogan = layers.indexOf('hide-slogan')
				expect(slogan).toBeGreaterThanOrEqual(0)
				expect(slogan).toBeGreaterThan(
					layers.findIndex((l) => l.startsWith('custom-overrides')),
				)
				for (const [i, l] of layers.entries()) {
					if (l.startsWith('systems/') || l.startsWith('tokens/')) {
						expect(i, l).toBeLessThan(slogan)
					}
				}
				// Every declaration in it is !important, so position is not the
				// only thing it relies on.
				const priorities = await page.evaluate(() => {
					const sheet = [...document.styleSheets].find(
						(s) =>
							s.href !== null
							&& new URL(s.href).pathname.endsWith(
								'/thematiq/css/hide-slogan.css',
							),
					)
					if (sheet === undefined) return null
					return [...sheet.cssRules].flatMap((r) => {
						const style = (r as CSSStyleRule).style
						return [...style].map(
							(p) => `${p}:${style.getPropertyPriority(p)}`,
						)
					})
				})
				expect(priorities).toEqual([
					'display:important',
					'visibility:important',
				])
			})
		})
	})

	test(// @e2e openspec/specs/hide-slogan/spec.md#feature-disabled-skips-css
	// @e2e openspec/specs/hide-slogan/spec.md#slogan-visible-when-feature-disabled
	'disabled, no hide-slogan.css is loaded and the slogan footer shows', async ({
		browser,
	}) => {
		await withThemeState(browser, { hideSlogan: false }, async () => {
			await onLoginPage(browser, async (page) => {
				expect(await thematiqLayers(page)).not.toContain('hide-slogan')
				const footer = await footerStyle(page)
				expect(footer.display).not.toBe('none')
				expect(footer.visibility).toBe('visible')
				expect(footer.text).not.toBe('')
				await expect(page.locator(FOOTER).first()).toContainText(footer.text)
				expect(await page.evaluate(() => document.body.innerText)).toContain(
					footer.text,
				)
			})
		})
	})

	test(// @e2e openspec/specs/hide-slogan/spec.md#footer-element-hidden-with-display-none
	// @e2e openspec/specs/hide-slogan/spec.md#both-hiding-mechanisms-applied
	'enabled, the slogan footer computes to display none and visibility hidden', async ({
		browser,
	}) => {
		await withThemeState(browser, { hideSlogan: true }, async () => {
			await onLoginPage(browser, async (page) => {
				expect(await sloganState(page)).toEqual(HIDDEN)
			})
		})
	})

	test(// @e2e openspec/specs/hide-slogan/spec.md#multiple-selector-coverage-for-robustness
	'the rule names all three footer selectors and the login footer matches them', async ({
		browser,
	}) => {
		await withThemeState(browser, { hideSlogan: true }, async () => {
			await onLoginPage(browser, async (page) => {
				const selectors = await page.evaluate(() => {
					const sheet = [...document.styleSheets].find(
						(s) =>
							s.href !== null
							&& new URL(s.href).pathname.endsWith(
								'/thematiq/css/hide-slogan.css',
							),
					)
					if (sheet === undefined) return null
					return [...sheet.cssRules].flatMap((r) =>
						(r as CSSStyleRule).selectorText
							.split(',')
							.map((s) => s.trim()),
					)
				})
				expect(selectors).toEqual([
					'footer.guest-box',
					'#body-login footer.guest-box',
					'body.body-login-container footer.guest-box',
				])
				// The login footer is reached by the context selector too.
				expect(
					await page.evaluate(
						() =>
							document.querySelector('#body-login footer.guest-box')
							!== null,
					),
				).toBe(true)
				expect(await sloganState(page)).toEqual(HIDDEN)
			})
		})
	})

	test(// @e2e openspec/specs/hide-slogan/spec.md#non-login-page-footers-unaffected
	'enabled, footers on a logged-in page stay visible', async ({
		browser,
		page,
	}) => {
		await withThemeState(browser, { hideSlogan: true }, async () => {
			await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
			expect(await thematiqLayers(page)).toContain('hide-slogan')
			await page.evaluate(() => {
				const footer = document.createElement('footer')
				footer.id = 'e2e-plain-footer'
				footer.textContent = 'a footer that is not a guest box'
				document.body.appendChild(footer)
			})
			const hidden = await page.evaluate(() =>
				[...document.querySelectorAll('footer')]
					.filter((f) => !f.classList.contains('guest-box'))
					.filter((f) => getComputedStyle(f).display === 'none')
					.map((f) => f.id || f.className),
			)
			expect(hidden).toEqual([])
		})
	})

	test(// @e2e openspec/specs/hide-slogan/spec.md#other-guest-box-elements-unaffected
	'enabled, the login form guest box stays visible', async ({ browser }) => {
		await withThemeState(browser, { hideSlogan: true }, async () => {
			await onLoginPage(browser, async (page) => {
				const others = await page.evaluate(() =>
					[...document.querySelectorAll('.guest-box')]
						.filter((el) => el.tagName !== 'FOOTER')
						.map((el) => getComputedStyle(el).display),
				)
				expect(others.length).toBeGreaterThan(0)
				expect(others.filter((d) => d === 'none')).toEqual([])
				await expect(page.locator('input[name="user"]')).toBeVisible()
			})
		})
	})

	test(// @e2e openspec/specs/hide-slogan/spec.md#login-page-layout-preserved
	'enabled, the footer takes no space and the login box stays centred', async ({
		browser,
	}) => {
		await withThemeState(browser, { hideSlogan: true }, async () => {
			await onLoginPage(browser, async (page) => {
				expect(await page.locator(FOOTER).first().boundingBox()).toBeNull()
				const box = await page
					.locator('.guest-box.login-box')
					.first()
					.boundingBox()
				const viewport = page.viewportSize()
				expect(box).not.toBeNull()
				expect(viewport).not.toBeNull()
				const centre = (box?.y ?? 0) + (box?.height ?? 0) / 2
				const height = viewport?.height ?? 0
				// #body-login is a centred flex column; with the footer gone the
				// box centre sits in the middle of the viewport.
				if ((box?.height ?? 0) < height) {
					expect(Math.abs(centre - height / 2)).toBeLessThan(height * 0.15)
				}
			})
		})
	})

	test(// @e2e openspec/specs/hide-slogan/spec.md#accessibility-tree-impact
	'enabled, the slogan is absent from the accessibility tree', async ({
		browser,
	}) => {
		await withThemeState(browser, { hideSlogan: true }, async () => {
			await onLoginPage(browser, async (page) => {
				const text = (await footerStyle(page)).text
				expect(
					text,
					'the footer still carries the slogan in the DOM',
				).not.toBe('')
				const tree = await page.locator('body').ariaSnapshot()
				expect(tree).not.toContain(text.split('\n')[0].trim())
			})
		})
	})

	test(// @e2e openspec/specs/hide-slogan/spec.md#print-stylesheet-compatibility
	'enabled, the slogan stays hidden in print media', async ({ browser }) => {
		await withThemeState(browser, { hideSlogan: true }, async () => {
			await onLoginPage(browser, async (page) => {
				await page.emulateMedia({ media: 'print' })
				expect((await footerStyle(page)).display).toBe('none')
			})
		})
	})

	test(// @e2e openspec/specs/hide-slogan/spec.md#checkbox-reflects-current-state-on-load
	'the admin checkbox renders the stored value, checked and unchecked', async ({
		browser,
		page,
	}) => {
		const box = page.locator('#nldesign-hide-slogan')
		await withThemeState(browser, { hideSlogan: true }, async () => {
			await openTheming(page)
			await expect(box).toBeChecked()
			await expect(box).toHaveClass(/\bcheckbox\b/)
		})
		await withThemeState(browser, { hideSlogan: false }, async () => {
			await openTheming(page)
			await expect(box).not.toBeChecked()
		})
	})

	test(// @e2e openspec/specs/hide-slogan/spec.md#checkbox-change-triggers-save
	'unchecking the box posts hideSlogan false and the stored value follows', async ({
		browser,
		page,
	}) => {
		await withThemeState(browser, { hideSlogan: true }, async () => {
			await openTheming(page)
			await expect(page.locator('#nldesign-hide-slogan')).toBeChecked()
			const [response] = await Promise.all([
				page.waitForResponse(
					(r) =>
						r.url().includes('/settings/slogan')
						&& r.request().method() === 'POST',
				),
				page.locator('label[for="nldesign-hide-slogan"]').click(),
			])
			expect(response.request().postDataJSON()).toEqual({ hideSlogan: false })
			expect(response.status()).toBe(200)
			expect((await readToggles(page)).hideSlogan).toBe(false)
		})
	})

	test(// @e2e openspec/specs/hide-slogan/spec.md#checkbox-label-is-localized
	'the checkbox label reads the translated string and points at the box', async ({
		page,
	}) => {
		await openTheming(page)
		const label = page.locator('label[for="nldesign-hide-slogan"]')
		await expect(label).toHaveText('Hide Nextcloud slogan/payoff on login page')
		await expect(label).toHaveAttribute('for', 'nldesign-hide-slogan')
	})

	test(// @e2e openspec/specs/hide-slogan/spec.md#rijkshuisstijl-login-page-compliance
	'on rijkshuisstijl the login page shows no Nextcloud text below the form', async ({
		browser,
	}) => {
		await withThemeState(
			browser,
			{ tokenSet: 'rijkshuisstijl', hideSlogan: true },
			async () => {
				await onLoginPage(browser, async (page) => {
					expect(await thematiqLayers(page)).toContain(
						'tokens/rijkshuisstijl',
					)
					expect(await sloganState(page)).toEqual(HIDDEN)
				})
			},
		)
	})

	test(// @e2e openspec/specs/hide-slogan/spec.md#municipality-login-page-compliance
	'on amsterdam the login page shows no Nextcloud slogan', async ({ browser }) => {
		await withThemeState(
			browser,
			{ tokenSet: 'amsterdam', hideSlogan: true },
			async () => {
				await onLoginPage(browser, async (page) => {
					expect(await thematiqLayers(page)).toContain('tokens/amsterdam')
					expect(await sloganState(page)).toEqual(HIDDEN)
				})
			},
		)
	})

	test(// @e2e openspec/specs/hide-slogan/spec.md#feature-works-with-all-token-sets
	'the slogan is hidden on one set of every design system, stock included', async ({
		browser,
	}) => {
		test.setTimeout(180_000)
		// One set per design system: the stylesheet does not depend on the set,
		// so the meaningful partition is the bundle loaded before it. These are
		// set ids: the high-contrast design system's set is `hoog-contrast`.
		const sets = [
			'nextcloud',
			'rijkshuisstijl',
			'summer-breeze',
			'hoog-contrast',
			'lasuite',
			'cunningham',
		]
		const shipped = tokenSetManifest()
		for (const set of sets) {
			expect(
				shipped.some((s) => s.id === set),
				`token-sets.json ships ${set}`,
			).toBe(true)
		}
		for (const set of sets) {
			await withThemeState(
				browser,
				{ tokenSet: set, hideSlogan: true },
				async () => {
					await onLoginPage(browser, async (page) => {
						expect(await thematiqLayers(page), set).toContain(
							'hide-slogan',
						)
						expect(await sloganState(page)).toEqual(HIDDEN)
					})
				},
			)
		}
	})

	test(// @e2e openspec/specs/hide-slogan/spec.md#setting-change-not-immediate
	'a login page already open keeps the slogan until it is reloaded', async ({
		browser,
	}) => {
		await withThemeState(browser, { hideSlogan: false }, async () => {
			const anon = await anonymousPage(browser)
			try {
				await openLoginPage(anon.page)
				expect((await footerStyle(anon.page)).display).not.toBe('none')

				await withThemeState(browser, { hideSlogan: true }, async () => {
					// Nothing is pushed to a page that is already rendered.
					expect(await thematiqLayers(anon.page)).not.toContain(
						'hide-slogan',
					)
					expect((await footerStyle(anon.page)).display).not.toBe('none')
					// The next full load picks it up.
					await openLoginPage(anon.page)
					expect(await sloganState(anon.page)).toEqual(HIDDEN)
				})
			} finally {
				await anon.close()
			}
		})
	})

	test(// @e2e openspec/specs/hide-slogan/spec.md#admin-sees-effect-by-navigating-to-login-page
	'after enabling in the admin panel, a fresh private window shows no slogan', async ({
		browser,
		page,
	}) => {
		await withThemeState(browser, { hideSlogan: false }, async () => {
			await openTheming(page)
			await Promise.all([
				page.waitForResponse(
					(r) =>
						r.url().includes('/settings/slogan')
						&& r.request().method() === 'POST',
				),
				page.locator('label[for="nldesign-hide-slogan"]').click(),
			])
			await onLoginPage(browser, async (login) => {
				expect(await sloganState(login)).toEqual(HIDDEN)
			})
		})
	})
})
