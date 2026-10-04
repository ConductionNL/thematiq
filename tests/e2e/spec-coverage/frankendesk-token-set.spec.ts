/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/frankendesk-token-set/spec.md
 *
 * La Frankendesk on a real instance (thematiq#1021): which stylesheets a page
 * loads with the set active, what its tokens resolve to, whether the logo
 * reaches the header, and the contrast of every foreground the set names in
 * light mode and in both dark scopes.
 *
 * The unit spec (tests/vitest/frankendeskTokenSet.spec.js) resolves the
 * shipped files in load order. This one asks the browser, so it also catches
 * a layer the server does not emit, a url that 404s, or a rule a later layer
 * beats.
 *
 * IT CHANGES THE ACTIVE TOKEN SET. `applyThemeState()` records the set it
 * found and `afterAll` puts it back. Run it on a disposable instance, never on
 * the shared dev instance (tests/e2e/base-url.ts refuses that unless named).
 *
 * Dark mode is entered without touching the user's theme preference: the OS
 * scope through `emulateMedia`, the explicit scope by setting the attributes
 * Nextcloud sets on <body> for a chosen dark theme. Each dark test first
 * asserts the starting `data-theme-default` state, so a change in Nextcloud's
 * attribute vocabulary fails here instead of passing vacuously.
 */
import { test, expect, type Page } from '@playwright/test'

import { adminContext } from './_fixtures'
import {
	PROBE_URL,
	applyThemeState,
	bodyVar,
	contrastRatio,
	designSystem,
	parseRgb,
	repoFile,
	resolveColor,
	restoreThemeState,
	rootDeclarations,
	rootVar,
	thematiqLayers,
	tokenSetManifest,
	type ThemeSnapshot,
} from './_theme-state'

const SET_ID = 'frankendesk'

/**
 * The page under the set in light mode. Neither the bundle nor the set
 * declares `--nldesign-color-background` in light (Nextcloud paints the page),
 * so it is the manifest's background, the same fallback the dark generator
 * uses. The dark variant declares it.
 */
const PAGE_BACKGROUND = (
	tokenSetManifest().find((s) => s.id === SET_ID) as unknown as {
		theming: { background_color: string }
	}
).theming.background_color

/** Every foreground the set names, on the surface it sits on (the spec's list). */
const TEXT_PAIRS: Array<[string, string]> = [
	['--nldesign-color-text', '--nldesign-color-background'],
	['--nldesign-color-link', '--nldesign-color-background'],
	['--nldesign-color-link-hover', '--nldesign-color-background'],
	['--nldesign-color-primary-text', '--nldesign-color-primary'],
	['--nldesign-color-header-text', '--nldesign-color-header-background'],
	['--nldesign-nav-link-color', '--nldesign-color-nav-background'],
	['--nldesign-hero-title-color', '--nldesign-color-primary'],
	['--nldesign-hero-body-color', '--nldesign-color-primary'],
	['--frankendesk-card-heading-color', '--frankendesk-card-background'],
	['--frankendesk-card-body-color', '--frankendesk-card-background'],
	['--nldesign-color-footer-text', '--nldesign-color-footer-background'],
	['--frankendesk-footer-wordmark-color', '--nldesign-color-footer-background'],
	['--frankendesk-footer-brand-color', '--nldesign-color-footer-background'],
	['--frankendesk-footer-heading-color', '--nldesign-color-footer-background'],
	['--frankendesk-footer-link-color', '--nldesign-color-footer-background'],
	['--frankendesk-footer-legal-color', '--nldesign-color-footer-background'],
	['--frankendesk-footer-legal-link-color', '--nldesign-color-footer-background'],
	['--frankendesk-footer-social-color', '--nldesign-color-footer-background'],
]

/** WCAG relative luminance of an rgb() string. */
function luminance(rgb: string): number {
	const [r, g, b] = parseRgb(rgb).map((c, i) => {
		if (i === 3) return c
		const s = c / 255
		return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
	})
	return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

/** The computed style of the header logo. */
async function headerLogo(page: Page) {
	const logo = page.locator('#header .logo').first()
	await logo.waitFor({ state: 'attached', timeout: 20_000 })
	return logo.evaluate((el) => {
		const s = getComputedStyle(el)
		return {
			image: s.backgroundImage,
			mask: s.maskImage || (s as any).webkitMaskImage,
			fill: s.backgroundColor,
		}
	})
}

/** Each pair resolved on <body> and measured, as `fg on bg = ratio`. */
async function failingPairs(page: Page): Promise<string[]> {
	const failures: string[] = []
	for (const [fg, bg] of TEXT_PAIRS) {
		const f = await bodyVar(page, fg)
		let b = await bodyVar(page, bg)
		if (b === '' && bg === '--nldesign-color-background') {
			b = PAGE_BACKGROUND
		}
		expect(f, `${fg} must resolve on body`).not.toBe('')
		expect(b, `${bg} must resolve on body`).not.toBe('')
		const ratio = contrastRatio(
			await resolveColor(page, f),
			await resolveColor(page, b),
		)
		if (ratio < 4.5) {
			failures.push(`${fg} ${f} on ${bg} ${b} = ${ratio.toFixed(2)}:1`)
		}
	}
	return failures
}

/** Start from the auto theme: no explicit choice on <body>. */
async function expectAutoTheme(page: Page): Promise<void> {
	const attrs = await page.evaluate(() => ({
		dark: document.body.hasAttribute('data-theme-dark'),
		light: document.body.hasAttribute('data-theme-light'),
		themes: document.body.getAttribute('data-themes') ?? '',
	}))
	expect(attrs.dark, 'precondition: no explicit dark theme').toBe(false)
	expect(attrs.light, 'precondition: no explicit light theme').toBe(false)
	expect(attrs.themes).not.toContain('dark')
}

/** Enter the explicit dark scope the way Nextcloud marks a chosen dark theme. */
async function chooseDarkTheme(page: Page): Promise<void> {
	await page.evaluate(() => {
		document.body.removeAttribute('data-theme-default')
		document.body.setAttribute('data-theme-dark', '')
		document.body.setAttribute('data-themes', 'dark')
	})
}

/** The two dark scopes, each entered from a freshly loaded auto-theme page. */
const DARK_SCOPES: Array<[string, (page: Page) => Promise<void>]> = [
	[
		'the OS preference',
		async (page) => {
			await page.emulateMedia({ colorScheme: 'dark' })
			await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
			await expectAutoTheme(page)
		},
	],
	[
		'an explicit dark theme',
		async (page) => {
			await page.emulateMedia({ colorScheme: 'light' })
			await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
			await expectAutoTheme(page)
			await chooseDarkTheme(page)
		},
	],
]

test.describe('La Frankendesk token set', () => {
	test.describe.configure({ mode: 'serial' })

	let snapshot: ThemeSnapshot | null = null

	test.beforeAll(async ({ browser }) => {
		const ctx = await adminContext(browser)
		const page = await ctx.newPage()
		snapshot = await applyThemeState(page, { tokenSet: SET_ID })
		await ctx.close()
	})

	test.afterAll(async ({ browser }) => {
		if (snapshot === null) return
		const ctx = await adminContext(browser)
		const page = await ctx.newPage()
		await restoreThemeState(page, snapshot)
		await ctx.close()
	})

	test.beforeEach(async ({ page }) => {
		await page.emulateMedia({ colorScheme: 'light' })
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
	})

	// @e2e frankendesk-token-set::selecting-frankendesk-loads-the-lasuite-bundle-and-one-token-file
	test('selecting frankendesk loads the lasuite bundle and one token file', async ({
		page,
	}) => {
		const layers = await thematiqLayers(page)
		const expected = [
			...designSystem('lasuite').stylesheets,
			`tokens/${SET_ID}`,
			`token-overrides/${SET_ID}`,
			`tokens/dark/${SET_ID}`,
		]
		const positions = expected.map((layer) => layers.indexOf(layer))
		for (const [i, layer] of expected.entries()) {
			expect(positions[i], `${layer} must load`).toBeGreaterThanOrEqual(0)
		}
		expect([...positions].sort((a, b) => a - b)).toEqual(positions)
		expect(layers).not.toContain('tokens/lasuite')
	})

	// @e2e frankendesk-token-set::the-set-carries-every-lasuite-token
	test('the set carries every lasuite token', async ({ page }) => {
		const parent = rootDeclarations(repoFile('css/tokens/lasuite.css'))
		expect(
			parent.size,
			'precondition: lasuite.css declares tokens',
		).toBeGreaterThan(10)
		const drift: string[] = []
		for (const [name, value] of parent) {
			if (name === '--nldesign-font-family') continue
			const live = await rootVar(page, name)
			const same = value.startsWith('#')
				? (await resolveColor(page, live))
					=== (await resolveColor(page, value))
				: live.replace(/\s+/g, ' ') === value
			if (!same) drift.push(`${name}: lasuite ${value}, page ${live}`)
		}
		expect(drift).toEqual([])
	})

	// @e2e frankendesk-token-set::the-font-stack-is-the-one-departure
	test('the font stack is the one departure', async ({ page }) => {
		for (const name of [
			'--nldesign-font-family',
			'--nldesign-body-font-family',
		]) {
			const stack = await rootVar(page, name)
			expect(stack.split(',')[0].trim().replace(/['"]/g, '')).toBe('Inter')
			expect(stack).not.toMatch(/marianne/i)
		}
	})

	// @e2e frankendesk-token-set::the-delta-declares-the-portal-surfaces
	test('the delta declares the portal surfaces', async ({ page }) => {
		const footer = await resolveColor(
			page,
			await rootVar(page, '--nldesign-color-footer-background'),
		)
		expect(luminance(footer)).toBeLessThan(0.05)
		expect(await rootVar(page, '--frankendesk-card-radius')).toBe('8px')
		expect(
			await resolveColor(
				page,
				await rootVar(page, '--frankendesk-card-body-color'),
			),
		).toBe(await resolveColor(page, '#6b6b80'))
	})

	// @e2e frankendesk-token-set::the-header-shows-the-la-frankendesk-mark-unmasked
	test('the header shows the La Frankendesk mark unmasked', async ({ page }) => {
		const logo = await headerLogo(page)
		const url = /url\("?([^")]+)"?\)/.exec(logo.image)?.[1]
		expect(url, `background-image ${logo.image}`).toMatch(
			new RegExp(`img/logos/${SET_ID}\\.svg$`),
		)
		expect(logo.mask).toBe('none')
		expect(parseRgb(logo.fill)[3]).toBe(0)

		const res = await page.request.get(url as string)
		expect(res.status()).toBe(200)
		expect(res.headers()['content-type']).toContain('image/svg+xml')
	})

	// @e2e frankendesk-token-set::light-mode-text-reaches-aa
	test('light mode text reaches AA', async ({ page }) => {
		await expectAutoTheme(page)
		expect(await failingPairs(page)).toEqual([])
	})

	for (const [scope, enter] of DARK_SCOPES) {
		// @e2e frankendesk-token-set::the-logo-stays-in-dark-mode
		test(`the logo stays in dark mode (${scope})`, async ({ page }) => {
			await enter(page)
			const logo = await headerLogo(page)
			expect(logo.image).toMatch(new RegExp(`img/logos/${SET_ID}\\.svg`))
		})

		// @e2e frankendesk-token-set::dark-mode-text-reaches-aa-in-both-dark-scopes
		test(`dark mode text reaches AA (${scope})`, async ({ page }) => {
			await enter(page)
			// Positive control: dark mode actually applied, or every pair below
			// would just be the light ones again.
			const background = await resolveColor(
				page,
				await bodyVar(page, '--nldesign-color-background'),
			)
			expect(luminance(background), 'dark page background').toBeLessThan(0.05)
			expect(await failingPairs(page)).toEqual([])
		})

		// @e2e frankendesk-token-set::the-footer-stays-a-dark-band-in-dark-mode
		test(`the footer stays a dark band in dark mode (${scope})`, async ({
			page,
		}) => {
			await enter(page)
			const footer = await resolveColor(
				page,
				await bodyVar(page, '--nldesign-color-footer-background'),
			)
			const text = await resolveColor(
				page,
				await bodyVar(page, '--nldesign-color-footer-text'),
			)
			expect(luminance(footer)).toBeLessThan(0.05)
			expect(contrastRatio(text, footer)).toBeGreaterThanOrEqual(4.5)
		})
	}
})
