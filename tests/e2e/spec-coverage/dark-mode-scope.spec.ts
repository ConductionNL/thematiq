/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/dark-mode/spec.md
 *
 * Behavioural coverage for the dark-mode SCOPE SELECTOR requirement.
 *
 * The sibling `dark-mode.spec.ts` asserts that the generated stylesheet
 * CONTAINS the three selector fragments. That is a text assertion about a file:
 * it stays green if the selectors are present but never match, if the light
 * layer out-specifies them, or if something later in the cascade overrides the
 * result. What the spec requires is that the RENDERED palette changes — so
 * these tests resolve the generated file's OWN tokens through the real cascade,
 * with Playwright emulating the OS `prefers-color-scheme`.
 *
 * NOTHING IS HARDCODED. The probe tokens are parsed out of whichever
 * `tokens/dark/<activeSet>.css` the instance actually loaded, then narrowed to
 * those that also resolve under the light baseline. So the suite follows a
 * token-set change instead of pinning one instance's palette, and it cannot
 * pass by probing a token that does not exist (measured: `lasuite` declares
 * `--nldesign-color-background` in its DARK file only, so a naive hardcoded
 * probe reads `""` in both states and compares nothing).
 *
 * WHY THE BODY ATTRIBUTE IS SET IN-PAGE (and not through the theme UI):
 * switching the Nextcloud theme mutates a preference on the SHARED dev
 * instance, which is why this suite's own toggle test is excluded. Nextcloud
 * sets `data-theme-*` on `<body>` server-side from that preference; the
 * contract under test is which CSS block wins for a given attribute, so setting
 * the attribute directly exercises exactly the selector the spec specifies
 * while mutating nothing. The starting `data-theme-default` state is asserted
 * first, so a Nextcloud change to the attribute vocabulary surfaces as a
 * failure rather than as a silently-vacuous pass.
 *
 * EVERY test starts from a POSITIVE CONTROL: a dark stylesheet must be loaded
 * and the probe set must be non-empty. Without that, a page that failed to load
 * any nldesign CSS would report "light stayed light" and pass for the wrong
 * reason.
 */
import { test, expect, Page } from '@playwright/test'

import { withThemeState } from './_theme-state'

const PROBE_URL = '/settings/user'

/** The nldesign stylesheet hrefs present in the page head, in document order. */
async function nldesignStyleHrefs(page: Page): Promise<string[]> {
	return page.evaluate(() =>
		[...document.querySelectorAll('link[rel=stylesheet]')]
			.map((l) => (l as HTMLLinkElement).href)
			.filter((h) => h.includes('/thematiq/')),
	)
}

/**
 * Parse the `--nldesign-*: <hex>` declarations out of the MEDIA-SCOPED block of
 * the loaded dark variant — the block the auto-theme scenarios are about.
 */
async function darkMediaDeclarations(page: Page): Promise<Record<string, string>> {
	const hrefs = await nldesignStyleHrefs(page)
	const darkHref = hrefs.find((h) => h.includes('/css/tokens/dark/'))
	expect(
		darkHref,
		'precondition: a tokens/dark/* stylesheet must be loaded, otherwise these '
			+ 'assertions cannot distinguish "scoped correctly" from "never loaded"',
	).toBeTruthy()

	const css = await (await page.request.get(darkHref as string)).text()
	const mediaBlock = css.slice(
		css.indexOf('@media (prefers-color-scheme: dark)'),
		css.indexOf('body[data-theme-dark]'),
	)

	const out: Record<string, string> = {}
	for (const m of mediaBlock.matchAll(
		/(--nldesign-[a-z0-9-]+)\s*:\s*(#[0-9a-f]{3,8})\s*;/gi,
	)) {
		out[m[1]] = m[2].toLowerCase()
	}
	expect(
		Object.keys(out).length,
		'precondition: the dark variant must declare colour tokens to probe',
	).toBeGreaterThan(5)
	return out
}

/** Resolve custom properties on <body> through the real cascade. */
async function resolveTokens(
	page: Page,
	tokens: string[],
): Promise<Record<string, string>> {
	return page.evaluate((list) => {
		const cs = getComputedStyle(document.body)
		const out: Record<string, string> = {}
		for (const t of list) {
			out[t] = cs.getPropertyValue(t).trim().toLowerCase()
		}
		return out
	}, tokens)
}

/**
 * Wait until a style change has actually landed before reading the palette.
 *
 * `emulateMedia()` and an attribute write both resolve as soon as the command
 * is delivered — the style recalculation that follows is asynchronous. Reading
 * `getComputedStyle` on the next line therefore races the recalc, and the race
 * is usually won: the same code passed 5/5, then 2/5, then 5/5 on this
 * instance with no edit in between. That is the shape of a flaky test, and a
 * flaky test in a coverage suite is worse than none — it trains the next
 * reader to re-run until green.
 *
 * Polling on the SENTINEL token's expected value is the fix. It is not a
 * `waitForTimeout` (no fixed delay) and not `networkidle` (banned by gate-58,
 * and this switch issues no request at all — which is itself asserted below):
 * it waits for the exact condition under test and fails on its own timeout if
 * the condition never arrives.
 */
async function settleTo(page: Page, token: string, expected: string): Promise<void> {
	await expect
		.poll(async () => (await resolveTokens(page, [token]))[token], {
			message: `waiting for ${token} to settle to ${expected}`,
			timeout: 10_000,
		})
		.toBe(expected)
}

/**
 * Value-agnostic settle, for the reads whose expected value is what we are
 * about to measure. Two animation frames guarantee a style recalculation has
 * run (recalc precedes paint, and CDP commands are delivered in order), so
 * this is a barrier rather than a delay — it adds no fixed wait.
 */
async function settle(page: Page): Promise<void> {
	await page.evaluate(
		() =>
			new Promise<void>((r) =>
				requestAnimationFrame(() => requestAnimationFrame(() => r())),
			),
	)
}

/** Set an explicit theme attribute on <body>, as Nextcloud does server-side. */
async function setBodyTheme(page: Page, attr: string): Promise<void> {
	await page.evaluate((a) => {
		const body = document.body
		for (const name of [...body.getAttributeNames()]) {
			if (name.startsWith('data-theme-')) {
				body.removeAttribute(name)
			}
		}
		body.setAttribute('data-themes', a === 'data-theme-dark' ? 'dark' : '')
		body.setAttribute(a, '')
	}, attr)
}

/** Assert the body is on the auto ("System default") theme, not an explicit one. */
async function expectAutoTheme(page: Page): Promise<void> {
	const explicit = await page.evaluate(() =>
		document.body
			.getAttributeNames()
			.filter(
				(n) => n.startsWith('data-theme-') && n !== 'data-theme-default',
			),
	)
	expect(
		explicit,
		'these scenarios are about the auto theme — an explicit choice on the '
			+ 'probe page would make the media-query assertions vacuous',
	).toEqual([])
}

/**
 * The probe set: dark-declared tokens that ALSO resolve to a different value in
 * the current (light) state. Tokens the light layer never declares are dropped,
 * because "empty becomes set" is a weaker signal than "light colour becomes
 * dark colour" and would let a half-broken cascade through.
 */
async function buildProbeSet(
	page: Page,
): Promise<{ dark: Record<string, string>; light: Record<string, string> }> {
	const dark = await darkMediaDeclarations(page)
	await settle(page)
	const resolved = await resolveTokens(page, Object.keys(dark))

	const light: Record<string, string> = {}
	const darkNarrowed: Record<string, string> = {}
	for (const [token, darkValue] of Object.entries(dark)) {
		const current = resolved[token]
		if (current !== '' && current !== darkValue) {
			light[token] = current
			darkNarrowed[token] = darkValue
		}
	}

	expect(
		Object.keys(light).length,
		'precondition: at least a handful of tokens must differ between the light '
			+ 'layer and the dark variant, otherwise there is nothing to observe',
	).toBeGreaterThan(5)

	return { dark: darkNarrowed, light }
}

test.describe('dark-mode scope selectors — light OS', () => {
	test.use({ colorScheme: 'light' })

	// @e2e dark-mode::explicit-dark-choice-on-a-light-os-renders-dark
	test('an explicit dark choice on a LIGHT OS renders the dark palette', async ({
		page,
	}) => {
		await page.goto(PROBE_URL)
		await expectAutoTheme(page)

		const { dark, light } = await buildProbeSet(page)

		// Baseline: a light OS with no explicit choice resolves the LIGHT layer.
		const before = await resolveTokens(page, Object.keys(dark))
		expect(before).toEqual(light)

		// body[data-theme-dark] — the UNCONDITIONAL block, which must win even
		// though the OS reports light.
		const sentinel = Object.keys(dark)[0]
		await setBodyTheme(page, 'data-theme-dark')
		await settleTo(page, sentinel, dark[sentinel])
		const after = await resolveTokens(page, Object.keys(dark))

		expect(
			after,
			'an explicit dark choice must resolve every token to its generated '
				+ 'dark value regardless of the OS preference',
		).toEqual(dark)
	})
})

test.describe('dark-mode scope selectors — dark OS', () => {
	test.use({ colorScheme: 'dark' })

	// @e2e dark-mode::auto-theme-follows-a-dark-os
	// @e2e dark-mode::os-switch-flips-theme-live-without-app-involvement
	test('the auto theme follows a dark OS, via the media query alone', async ({
		page,
	}) => {
		await page.goto(PROBE_URL)
		await expectAutoTheme(page)

		// Read the declarations, then the light values under an emulated light
		// OS, so both halves of the comparison come from this same page.
		const dark = await darkMediaDeclarations(page)
		await page.emulateMedia({ colorScheme: 'light' })
		await settle(page)
		const lightResolved = await resolveTokens(page, Object.keys(dark))

		const probes = Object.keys(dark).filter(
			(t) => lightResolved[t] !== '' && lightResolved[t] !== dark[t],
		)
		expect(
			probes.length,
			'precondition: tokens must differ light vs dark',
		).toBeGreaterThan(5)
		const sentinel = probes[0]

		// "…via the CSS media query alone — no nldesign PHP or JS runs to effect
		// the switch." Flip the emulated OS preference with NO navigation, and
		// the palette must follow. A JS- or server-driven implementation could
		// not react to this at all.
		const requests: string[] = []
		page.on('request', (r) => {
			if (r.url().includes('/thematiq/')) {
				requests.push(r.url())
			}
		})

		await page.emulateMedia({ colorScheme: 'dark' })
		await settleTo(page, sentinel, dark[sentinel])
		const darkResolved = await resolveTokens(page, probes)
		for (const t of probes) {
			expect(darkResolved[t], `${t} under a dark OS`).toBe(dark[t])
		}

		await page.emulateMedia({ colorScheme: 'light' })
		await settleTo(page, sentinel, lightResolved[sentinel])
		const backToLight = await resolveTokens(page, probes)
		for (const t of probes) {
			expect(backToLight[t], `${t} back under a light OS`).toBe(
				lightResolved[t],
			)
		}

		expect(
			requests,
			'the OS switch must be pure CSS — no nldesign request may be issued',
		).toEqual([])
	})

	// @e2e dark-mode::explicit-light-choice-on-a-dark-os-stays-light
	test('an explicit light choice on a DARK OS stays light', async ({ page }) => {
		await page.goto(PROBE_URL)
		await expectAutoTheme(page)

		const dark = await darkMediaDeclarations(page)

		// Capture the light values from THIS page, then narrow to the tokens
		// that actually change. Derivation legitimately maps some tokens to the
		// value they already had (measured: `--nldesign-color-primary-hover` is
		// #36347d in both palettes of `lasuite`), and those tokens can never
		// evidence anything about scoping — asserting "not the dark value" over
		// them fails on a correct implementation.
		await page.emulateMedia({ colorScheme: 'light' })
		await settle(page)
		const lightResolved = await resolveTokens(page, Object.keys(dark))
		const probes = Object.keys(dark).filter(
			(t) => lightResolved[t] !== '' && lightResolved[t] !== dark[t],
		)
		expect(
			probes.length,
			'precondition: tokens must differ light vs dark',
		).toBeGreaterThan(5)
		const sentinel = probes[0]

		// Prove the page IS in the darkening condition before excluding it,
		// otherwise "stayed light" is satisfied by a page that never went dark.
		await page.emulateMedia({ colorScheme: 'dark' })
		await settleTo(page, sentinel, dark[sentinel])
		const auto = await resolveTokens(page, probes)
		for (const t of probes) {
			expect(
				auto[t],
				`precondition: ${t} must be dark under a dark OS with no explicit `
					+ 'choice, or this test cannot show the :not() exclusions do anything',
			).toBe(dark[t])
		}

		// Now the exclusion: an explicit light choice must return every probe to
		// its exact light value — not merely to "something other than dark".
		await setBodyTheme(page, 'data-theme-light')
		await settleTo(page, sentinel, lightResolved[sentinel])
		const explicitLight = await resolveTokens(page, probes)
		for (const t of probes) {
			expect(
				explicitLight[t],
				`${t}: an explicit light choice must not be darkened`,
			).toBe(lightResolved[t])
		}
	})

	// @e2e dark-mode::dark-stylesheet-loads-in-order
	test('the dark variant loads after its light layer and before custom overrides', async ({
		browser,
		page,
	}) => {
		// The custom-overrides layer is only linked while the active set has a
		// saved overrides file, so save one (empty) for the duration of the test
		// rather than rely on an earlier spec having left one behind.
		await withThemeState(browser, { overrides: {} }, async () => {
			await page.goto(PROBE_URL)
			const order = await nldesignStyleHrefs(page)

			const light = order.findIndex((h) => /\/css\/tokens\/[^/]+\.css/.test(h))
			const dark = order.findIndex((h) => h.includes('/css/tokens/dark/'))
			const overrides = order.findIndex((h) => h.includes('custom-overrides'))

			expect(light, 'a light token layer must load').toBeGreaterThanOrEqual(0)
			expect(dark, 'a dark variant must load').toBeGreaterThanOrEqual(0)
			expect(
				dark,
				'the dark variant must load AFTER its light layer',
			).toBeGreaterThan(light)
			expect(
				overrides,
				'the custom-overrides layer must load',
			).toBeGreaterThanOrEqual(0)
			expect(
				dark,
				'the dark variant must load BEFORE custom-overrides so a site override still wins',
			).toBeLessThan(overrides)
		})
	})
})

test.describe('dark-mode scope selectors — anonymous', () => {
	// The login page carries no data-theme-* attributes and an empty
	// data-themes, so all four :not() exclusions match and the media-scoped
	// block must apply.
	test.use({ colorScheme: 'dark', storageState: { cookies: [], origins: [] } })

	// @e2e dark-mode::anonymous-login-page-follows-the-os
	test('the anonymous login page follows a dark OS', async ({ page }) => {
		await page.goto('/login')

		// Confirm we really are anonymous — a leaked session would land this on
		// an authenticated page whose body DOES carry a theme attribute.
		await expect(page.locator('form[name="login"]')).toBeVisible()
		await expectAutoTheme(page)

		const dark = await darkMediaDeclarations(page)
		await settle(page)
		const resolved = await resolveTokens(page, Object.keys(dark))

		const applied = Object.keys(dark).filter((t) => resolved[t] === dark[t])
		expect(
			applied.length,
			'the anonymous login page must follow the dark OS — the media-scoped '
				+ 'block applies because every :not() exclusion matches',
		).toBeGreaterThan(5)
	})
})
