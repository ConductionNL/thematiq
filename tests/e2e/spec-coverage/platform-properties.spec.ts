/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/platform-properties/spec.md
 *
 * What Nextcloud core owns around thematiq, seen from the browser: the slogan,
 * the legal links, the product name and web address stay core's, and every
 * URL thematiq writes into a page carries the domain the server answers on.
 *
 * Core's Theming fields are set the way core's own admin page sets them
 * (`/apps/theming/ajax/updateStylesheet`) and read back the way any client
 * reads them (the OCS capabilities). Every value a test changes is put back
 * in a `finally`: core fields to the value read before, thematiq settings
 * through withThemeState(), core theming through restoreCoreTheming().
 *
 * Not covered here yet: the logo, background and favicon slot scenarios
 * (they upload images into core; theming-sync.spec.ts holds the capture and
 * restore they would build on), a user's dark choice, admin delegation, and
 * the app store install (a second user with a theme preference, a delegated
 * group, app store access). Each is still listed by gate 19.
 */
import { test, expect, type Page } from '@playwright/test'
import { adminContext } from './_fixtures'
import {
	anonymousPage,
	openLoginPage,
	PROBE_URL,
	withThemeState,
} from './_theme-state'
import { captureCoreTheming, postTheming, restoreCoreTheming } from './_core-theming'

/** The core Theming fields these tests touch, by their core setting name. */
type CoreField = 'name' | 'url' | 'slogan' | 'privacyUrl'

/** Open an admin page that carries `OC`. */
async function openAdminPage(page: Page): Promise<void> {
	await page.goto(PROBE_URL)
	await page.waitForLoadState('domcontentloaded')
	await page.waitForFunction(
		() => typeof (window as unknown as { OC?: unknown }).OC !== 'undefined',
		null,
		{ timeout: 30_000 },
	)
}

/** Core's theming capability: what any client reads as the name, slogan and address. */
async function coreTheming(page: Page): Promise<Record<string, string>> {
	const res = await page.evaluate(async () => {
		const r = await fetch('/ocs/v2.php/cloud/capabilities?format=json', {
			headers: { 'OCS-APIRequest': 'true' },
		})
		return { status: r.status, json: await r.json() }
	})
	expect(res.status, 'the capabilities answer').toBe(200)
	return res.json.ocs.data.capabilities.theming as Record<string, string>
}

/**
 * The privacy statement URL core holds, read from the initial state of core's
 * own Theming page (the capabilities do not carry it). Leaves `page` there.
 */
async function storedPrivacyUrl(page: Page): Promise<string> {
	await page.goto('/settings/admin/theming')
	await page.waitForLoadState('domcontentloaded')
	const value = await page.evaluate(() => {
		const el = document.querySelector<HTMLInputElement>(
			'#initial-state-theming-adminThemingParameters',
		)
		if (el === null) {
			return null
		}
		const state = JSON.parse(atob(el.value)) as { privacyUrl?: string }
		return state.privacyUrl ?? ''
	})
	expect(
		value,
		"core's Theming page carries its parameters, so the old value can be put back",
	).not.toBeNull()
	return value as string
}

/**
 * Set a core Theming field the way core's admin page does, or undo it to
 * core's default when `value` is empty.
 */
async function setCoreField(
	page: Page,
	setting: CoreField,
	value: string,
): Promise<void> {
	const status = await page.evaluate(
		async ({ setting, value }) => {
			const OC = (
				window as unknown as {
					OC: { generateUrl: (p: string) => string; requestToken: string }
				}
			).OC
			const route =
				value === ''
					? '/apps/theming/ajax/undoChanges'
					: '/apps/theming/ajax/updateStylesheet'
			const body = new URLSearchParams(
				value === '' ? { setting } : { setting, value },
			)
			const r = await fetch(OC.generateUrl(route), {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					requesttoken: OC.requestToken,
				},
				body: body.toString(),
			})
			return r.status
		},
		{ setting, value },
	)
	expect(status, `core theming accepts ${setting}`).toBe(200)
}

/**
 * Run `fn` with core Theming fields set to `values`, and put back what was
 * there whatever happens. `before` maps each field to the capability that
 * reports it, so the old value can be read first.
 */
async function withCoreFields(
	page: Page,
	values: Partial<Record<CoreField, string>>,
	before: Partial<Record<CoreField, string>>,
	fn: () => Promise<void>,
): Promise<void> {
	const fields = Object.keys(values) as CoreField[]
	try {
		for (const field of fields) {
			await setCoreField(page, field, values[field] as string)
		}
		await fn()
	} finally {
		for (const field of fields) {
			await setCoreField(page, field, before[field] ?? '')
		}
	}
}

test.describe('platform-properties', () => {
	test.describe.configure({ mode: 'serial', timeout: 120_000 })

	test(
		// @e2e platform-properties::the-slogan-is-hidden
		"hiding the slogan hides it on the login page and leaves core's slogan stored",
		async ({ browser }) => {
			const ctx = await adminContext(browser)
			const admin = await ctx.newPage()
			try {
				await openAdminPage(admin)
				const before = await coreTheming(admin)
				const slogan = 'Samen werken'
				await withCoreFields(
					admin,
					{ slogan },
					{ slogan: before.slogan ?? '' },
					async () => {
						await withThemeState(
							browser,
							{ hideSlogan: true },
							async () => {
								const anon = await anonymousPage(browser)
								try {
									await openLoginPage(anon.page)
									// innerText leaves out what is not rendered.
									const onScreen = await anon.page.evaluate(
										() => document.body.innerText,
									)
									expect(onScreen).not.toContain(slogan)
								} finally {
									await anon.close()
								}
								// Core still holds the slogan.
								expect((await coreTheming(admin)).slogan).toBe(
									slogan,
								)
							},
						)
					},
				)
			} finally {
				await ctx.close()
			}
		},
	)

	test(
		// @e2e platform-properties::a-privacy-link-is-set-in-core
		'a privacy statement set in core is a visible link on the login page',
		async ({ browser }) => {
			const ctx = await adminContext(browser)
			const admin = await ctx.newPage()
			try {
				await openAdminPage(admin)
				const before = await storedPrivacyUrl(admin)
				const privacyUrl = 'https://werkplek.example.nl/privacy'
				await withCoreFields(
					admin,
					{ privacyUrl },
					{ privacyUrl: before },
					async () => {
						const anon = await anonymousPage(browser)
						try {
							await openLoginPage(anon.page)
							await expect(
								anon.page.locator(`a[href="${privacyUrl}"]`).first(),
							).toBeVisible()
						} finally {
							await anon.close()
						}
					},
				)
			} finally {
				await ctx.close()
			}
		},
	)

	test(
		// @e2e platform-properties::the-workspace-moves-to-the-organisations-domain
		'every URL thematiq writes into a page is on the domain the page was served from',
		async ({ page }) => {
			await openAdminPage(page)
			const urls = await page.evaluate(() => {
				const found: string[] = []
				const take = (value: string | null): void => {
					if (value !== null && value.includes('thematiq')) {
						found.push(new URL(value, location.href).href)
					}
				}
				document
					.querySelectorAll('link[href]')
					.forEach((el) => take(el.getAttribute('href')))
				document
					.querySelectorAll('script[src]')
					.forEach((el) => take(el.getAttribute('src')))
				document
					.querySelectorAll('img[src]')
					.forEach((el) => take(el.getAttribute('src')))
				return found
			})
			expect(
				urls.length,
				'thematiq writes URLs into the page',
			).toBeGreaterThan(0)
			const origin = new URL(page.url()).origin
			for (const url of urls) {
				expect(new URL(url).origin, url).toBe(origin)
			}

			// An absolute URL inside a served thematiq stylesheet is on that domain too.
			const absolute = await page.evaluate(async () => {
				const out: string[] = []
				const sheets = [
					...document.querySelectorAll<HTMLLinkElement>(
						'link[rel="stylesheet"][href*="thematiq"]',
					),
				]
				for (const link of sheets) {
					const css = await (await fetch(link.href)).text()
					for (const m of css.matchAll(
						/url\(\s*['"]?(https?:\/\/[^'")\s]+)/g,
					)) {
						out.push(m[1])
					}
				}
				return out
			})
			const host = new URL(origin).host
			for (const url of absolute) {
				const target = new URL(url)
				// Only a URL to this server must carry its domain; a font or icon
				// CDN named by a set's licence notes is someone else's.
				if (target.pathname.includes('/apps/thematiq/')) {
					expect(target.host, url).toBe(host)
				}
			}
		},
	)

	test(
		// @e2e platform-properties::applying-a-set-leaves-the-name-and-web-address-alone
		"a theming sync leaves core's name and web address alone",
		async ({ page }) => {
			await openAdminPage(page)
			const before = await coreTheming(page)
			const name = 'Werkplek Voorbeeld'
			const url = 'https://werkplek.example.nl'
			await withCoreFields(
				page,
				{ name, url },
				{ name: before.name ?? '', url: before.url ?? '' },
				async () => {
					const capture = await captureCoreTheming(page)
					try {
						// What the sync dialog posts when an administrator confirms it.
						const res = await postTheming(page, {
							primary_color: '#3669a5',
						})
						expect(res.status).toBe(200)

						await openAdminPage(page)
						const after = await coreTheming(page)
						expect(after.name).toBe(name)
						expect(after.url).toBe(url)
						await expect(page).toHaveTitle(new RegExp(name))
					} finally {
						await restoreCoreTheming(page, capture)
					}
				},
			)
		},
	)
})
