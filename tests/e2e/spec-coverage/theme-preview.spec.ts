/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Theme preview ("proefdraaien") — the per-user preview lifecycle, its
 * isolation from every other user, and the banner that announces it.
 *
 * WHY THESE ARE BROWSER TESTS AND NOT UNIT TESTS
 * ----------------------------------------------
 * The whole point of this feature is that ONE user's page renders differently
 * from every other user's, decided per request at render time. That is a claim
 * about what a browser receives, and the only honest way to check it is to
 * render pages in two different authenticated sessions and compare. A unit test
 * on ThemePreviewService can show the config values are written; it cannot show
 * the stylesheet that actually reaches the previewer's <head> changed while the
 * other user's did not.
 *
 * STATE DISCIPLINE
 * ----------------
 * A preview is a per-USER value, so these tests are naturally isolated from
 * anything instance-wide — except `publish`, which is instance-wide by design.
 * That one test captures the active set first and restores it in a finally
 * block. afterEach clears any preview the test left behind, so an assertion
 * failure part-way through cannot leak a preview into the next test and make it
 * fail for the wrong reason.
 */
import { test, expect, type Page } from '@playwright/test'

import {
	ensureNonAdminUser,
	loginAs,
	api,
	activeTokenSet,
	adminContext,
	NONADMIN_USER,
	NONADMIN_PASS,
} from './_fixtures'

const APP = '/index.php/apps/thematiq'
const THEMING_URL = '/settings/admin/theming'

/** The set previewed throughout. Its display name is asserted in the banner. */
const PREVIEW_SET = 'amsterdam'
const PREVIEW_NAME = 'Gemeente Amsterdam'

/** Every nldesign stylesheet href in the current page's head. */
async function nldesignStyles(page: Page): Promise<string[]> {
	return page.evaluate(() =>
		[...document.querySelectorAll('link[rel=stylesheet]')]
			.map((l) => (l as HTMLLinkElement).href)
			.filter((h) => h.includes('/thematiq/')),
	)
}

/**
 * Is this stylesheet href the TOKEN stylesheet for `setId`?
 *
 * Matches `css/tokens/<set>.css` and `css/tokens/dark/<set>.css` specifically,
 * rather than a bare substring test. A substring test is wrong in both
 * directions here: `nextcloud` (a real token set id) appears in almost every
 * Nextcloud URL, and a set whose id is a prefix of another would cross-match.
 */
function tokenStylesheetFor(href: string, setId: string): boolean {
	return new RegExp(`/css/tokens/(dark/)?${setId}\\.css`).test(href)
}

/** Every nldesign script src in the current page. */
async function nldesignScripts(page: Page): Promise<string[]> {
	return page.evaluate(() =>
		[...document.querySelectorAll('script[src]')]
			.map((s) => (s as HTMLScriptElement).src)
			.filter((h) => h.includes('/thematiq/')),
	)
}

test.describe('theme preview', () => {
	// The non-admin session is built ONCE. A full form login costs ~20s on a
	// cold instance, and doing it inside the isolation test pushed that single
	// test past the suite's 30s budget. Hoisting it removes work rather than
	// buying time with a longer timeout; the session is read-only in the one
	// test that uses it, so sharing it cannot leak state between tests.
	let nonAdmin: { page: Page; close: () => Promise<void> } | undefined

	/**
	 * The instance-wide active token set these tests run against.
	 *
	 * ESTABLISHED by the fixture, not assumed and not merely read.
	 *
	 * The first version hard-coded `rijkshuisstijl` — what the machine they were
	 * written on happened to have configured — and three assertions failed in CI
	 * for reasons unrelated to the behaviour under test. Reading the value
	 * instead is not enough either: a fresh instance defaults to `nextcloud`,
	 * which is the STOCK appearance (`design_system: none`) and by design loads
	 * NO nldesign stylesheets at all, so "the active set is loaded" would be
	 * false on a perfectly healthy instance.
	 *
	 * So the fixture sets a known non-stock set and restores whatever was there
	 * before. That makes "the previewed set SUBSTITUTED the active one" a claim
	 * with two observable sides on every instance.
	 */
	const ACTIVE_SET = 'rijkshuisstijl'
	let activeSet: string
	let originalActiveSet: string | undefined

	test.beforeAll(async ({ browser }) => {
		// SETUP budget, not an assertion budget — see the same note in
		// admin-only-enforcement.spec.ts. This hook provisions an account,
		// switches the instance-wide token set, and performs a full form login.
		test.setTimeout(180_000)

		const adminCtx = await adminContext(browser)
		const adminPage = await adminCtx.newPage()
		await adminPage.goto(THEMING_URL, { waitUntil: 'domcontentloaded' })
		await ensureNonAdminUser(adminPage)

		originalActiveSet = (await activeTokenSet(adminPage))?.tokenSet
		expect(
			originalActiveSet,
			'the instance-wide active token set must be readable',
		).toBeTruthy()

		const set = await api(adminPage, 'POST', `${APP}/settings/tokenset`, {
			tokenSet: ACTIVE_SET,
		})
		expect(
			set.status,
			`the fixture must be able to activate ${ACTIVE_SET}`,
		).toBe(200)
		activeSet = ACTIVE_SET

		// The previewed set must DIFFER from the active one, or "the previewed
		// set replaced the active one" is unfalsifiable.
		expect(
			activeSet,
			`the active set must not be the previewed set (${PREVIEW_SET})`,
		).not.toBe(PREVIEW_SET)

		await adminCtx.close()

		nonAdmin = await loginAs(browser, NONADMIN_USER, NONADMIN_PASS)
	})

	test.afterAll(async ({ browser }) => {
		await nonAdmin?.close()

		// Restore the instance-wide set this fixture changed, so the suite
		// leaves the instance as it found it for whatever runs next.
		if (originalActiveSet !== undefined && originalActiveSet !== ACTIVE_SET) {
			const ctx = await adminContext(browser)
			const p = await ctx.newPage()
			await p.goto(THEMING_URL, { waitUntil: 'domcontentloaded' })
			await api(p, 'POST', `${APP}/settings/tokenset`, {
				tokenSet: originalActiveSet,
			})
			await ctx.close()
		}
	})

	test.afterEach(async ({ page }) => {
		// Clear any preview this test left behind. Uses the admin session; a
		// discard with no active preview is a no-op, so this is safe to run
		// unconditionally.
		try {
			await page.goto(THEMING_URL)
			await api(page, 'DELETE', `${APP}/settings/preview`)
		} catch {
			// The page may be closed already; nothing to clean up in that case.
		}
	})

	// @e2e openspec/specs/theme-preview/spec.md#starting-a-preview-writes-only-user-values
	test('starting a preview writes user values and leaves the instance-wide set alone', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		const activeBefore = await activeTokenSet(page)

		const start = await api(page, 'POST', `${APP}/settings/preview`, {
			tokenSet: PREVIEW_SET,
		})
		expect(start.status, 'starting a preview must succeed for an admin').toBe(
			200,
		)
		expect(start.json.tokenSet).toBe(PREVIEW_SET)

		// expiresAt ≈ now + 86400 (the spec's 24-hour window). Allow a generous
		// window so a slow instance cannot make this flake, but tight enough
		// that a wrong unit (ms vs s) or a missing offset still fails.
		const nowSec = Math.floor(Date.now() / 1000)
		expect(start.json.expiresAt).toBeGreaterThan(nowSec + 86_000)
		expect(start.json.expiresAt).toBeLessThan(nowSec + 86_800)

		// The instance-wide value must be untouched — this is the whole claim.
		const activeAfter = await activeTokenSet(page)
		expect(
			activeAfter,
			'a preview must not change the instance-wide active set',
		).toEqual(activeBefore)
	})

	// @e2e openspec/specs/theme-preview/spec.md#invalid-token-set-id-is-rejected
	test('an invalid token set id is rejected and starts no preview', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		const res = await api(page, 'POST', `${APP}/settings/preview`, {
			tokenSet: 'does-not-exist',
		})
		expect(res.status, 'an unknown token set id must be a 400').toBe(400)

		// "and no user values MUST be written" — reload and confirm no banner,
		// which is the browser-visible consequence of a preview existing.
		await page.goto('/settings/user')
		await expect(page.locator('#nldesign-preview-banner')).toHaveCount(0)
	})

	// @e2e openspec/specs/theme-preview/spec.md#previewing-admin-sees-the-previewed-set-on-real-pages
	test('the previewing admin gets the previewed token set on a real page', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)

		await page.goto('/settings/user', { waitUntil: 'domcontentloaded' })
		const before = await nldesignStyles(page)
		expect(
			before.some((h) => tokenStylesheetFor(h, activeSet)),
			`the active set ${activeSet} must be loaded before previewing`,
		).toBe(true)

		await page.goto(THEMING_URL)
		expect(
			(
				await api(page, 'POST', `${APP}/settings/preview`, {
					tokenSet: PREVIEW_SET,
				})
			).status,
		).toBe(200)

		await page.goto('/settings/user', { waitUntil: 'domcontentloaded' })
		const during = await nldesignStyles(page)
		expect(
			during.some((h) => tokenStylesheetFor(h, PREVIEW_SET)),
			`the previewed set ${PREVIEW_SET} must be loaded on a normal page`,
		).toBe(true)
		expect(
			during.some((h) => tokenStylesheetFor(h, activeSet)),
			'the previewed set must SUBSTITUTE the active one, not stack on top of it',
		).toBe(false)

		// "custom overrides MUST still load last, unchanged".
		//
		// Asserted as "after the token layer", NOT "last overall". The cascade
		// documented in css-architecture puts custom overrides at layer 4 and
		// then loads custom fonts (4.5) and the conditional hide-slogan /
		// menu-labels stylesheets (5) AFTER them, so "last overall" is false on
		// any instance that has those enabled — it failed in CI with the
		// override at index 10 of 12 while passing locally where the later
		// layers happened to be absent.
		//
		// The invariant that actually matters, and that the spec is getting at,
		// is that an admin's overrides win over the token set they override.
		// A naive `includes('override')` would also match the design system's
		// own element-overrides.css, so match the custom file by name.
		const customOverride = during.findIndex((h) =>
			/custom-overrides\.css/.test(h),
		)
		if (customOverride >= 0) {
			const tokenLayer = during.findIndex((h) =>
				tokenStylesheetFor(h, PREVIEW_SET),
			)
			expect(
				customOverride,
				'custom overrides must load AFTER the token set they override',
			).toBeGreaterThan(tokenLayer)
		}
	})

	// @e2e openspec/specs/theme-preview/spec.md#banner-appears-on-all-themed-pages-for-the-previewer
	test('the banner appears on every themed page with keyboard-operable controls', async ({
		page,
	}) => {
		// This one test navigates THREE full app pages (Files, Dashboard,
		// Settings), because "on all themed pages" is the claim and a banner
		// wired to a single page would satisfy anything less. Three cold
		// Nextcloud app loads do not fit the suite's default 30s envelope.
		//
		// This is a WALL-CLOCK envelope, not a widened assertion. Every
		// assertion inside keeps its own tight deadline — the banner must appear
		// within 15s per page, each control must take focus within 5s — so a
		// banner that never renders, or a control that cannot be focused, still
		// fails fast and fails red. What this buys is only the time to visit
		// three pages instead of being cut off during the third.
		test.setTimeout(120_000)

		await page.goto(THEMING_URL)
		expect(
			(
				await api(page, 'POST', `${APP}/settings/preview`, {
					tokenSet: PREVIEW_SET,
				})
			).status,
		).toBe(200)

		// "on all themed pages" — assert on more than one, or a banner wired to
		// the settings page alone would pass.
		for (const url of ['/apps/files/', '/apps/dashboard/', '/settings/user']) {
			// `domcontentloaded`, not the default `load`. Dashboard widgets keep
			// long-lived requests open, so the load event never fires on a page
			// that is already interactive and already carries the banner — the
			// goto timed out here while the banner was sitting in the DOM.
			// Same reasoning, and the same fix, as tests/e2e/global-setup.ts.
			// This is NOT a widened timeout: the wait is SHORTER and the
			// assertions below are unchanged.
			await page.goto(url, { waitUntil: 'domcontentloaded' })
			const banner = page.locator('#nldesign-preview-banner')
			await banner.waitFor({ state: 'visible', timeout: 15_000 })

			await expect(
				banner,
				`banner must name the previewed set on ${url}`,
			).toContainText(PREVIEW_NAME)
			await expect(
				banner,
				`banner must say the preview is private on ${url}`,
			).toContainText(/only visible to you/i)
			// role="status" so a screen reader announces it (WCAG 4.1.3).
			await expect(banner).toHaveAttribute('role', 'status')

			// Publish and Discard must be present on every page.
			await expect(
				banner.locator('.nldesign-preview-banner-publish'),
			).toBeVisible()
			await expect(
				banner.locator('.nldesign-preview-banner-discard'),
			).toBeVisible()
		}

		// Keyboard operability is asserted ONCE, on the page that has just been
		// loaded, rather than on all three.
		//
		// Not a concession to flakiness: the banner is built by one function in
		// js/preview-banner.js and is the same DOM on every page, so focusing
		// the same two elements three times re-tests one fact. Doing it per page
		// pushed this single-scenario test past the suite's 30s budget (three
		// full navigations plus up to 10s of focus polling per control), and the
		// honest fix for "the test does redundant work" is to stop doing the
		// redundant work — not to raise the timeout until it fits.
		//
		// Discard's keyboard operability is additionally proven end-to-end by
		// the discard test below, which drives it with a real Enter keypress.
		const banner = page.locator('#nldesign-preview-banner')
		for (const cls of [
			'.nldesign-preview-banner-publish',
			'.nldesign-preview-banner-discard',
		]) {
			const control = banner.locator(cls)
			// RE-FOCUS on each poll rather than `focus()` once then
			// `expect(...).toBeFocused()`. The host app mounts after the banner
			// is appended and moves focus once while settling — measured on
			// /apps/files/, where a single focus() lands and is then taken away.
			// `toBeFocused()` retries the ASSERTION but never re-issues focus(),
			// so it burns its whole timeout re-reading a state that can no
			// longer change and reports a focusable control as unfocusable.
			//
			// Still fails for a control that genuinely cannot take focus — a
			// tabindex="-1" plant exhausts the poll and reddens the test — so
			// this measures focusability, not patience.
			await expect
				.poll(
					async () => {
						await control.focus()
						return await control.evaluate(
							(el) => el === document.activeElement,
						)
					},
					{
						message: `${cls} must be keyboard-focusable`,
						timeout: 5_000,
					},
				)
				.toBe(true)
		}
	})

	// @e2e openspec/specs/theme-preview/spec.md#no-banner-payload-without-a-preview
	test('no banner asset or payload is delivered without an active preview', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		await api(page, 'DELETE', `${APP}/settings/preview`)

		await page.goto('/settings/user')
		const scripts = await nldesignScripts(page)
		const styles = await nldesignStyles(page)

		expect(
			scripts.some((s) => s.includes('preview-banner')),
			'preview-banner.js must not be served without a preview',
		).toBe(false)
		expect(
			styles.some((s) => s.includes('preview-banner')),
			'preview-banner.css must not be served without a preview',
		).toBe(false)

		// ...nor the initial-state payload the banner reads.
		const payload = await page.evaluate(() => {
			const el = document.querySelector('#initial-state-nldesign-preview')
			return el ? el.textContent : null
		})
		expect(
			payload,
			'the preview initial-state payload must be absent',
		).toBeNull()
	})

	// @e2e openspec/specs/theme-preview/spec.md#non-admin-users-are-never-affected
	test('a non-admin sees the instance-wide set and no banner while an admin previews', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		expect(
			(
				await api(page, 'POST', `${APP}/settings/preview`, {
					tokenSet: PREVIEW_SET,
				})
			).status,
		).toBe(200)

		// Confirm the preview really is live for the admin, so a "non-admin is
		// unaffected" pass cannot come from the preview never having started.
		await page.goto('/settings/user', { waitUntil: 'domcontentloaded' })
		expect(
			(await nldesignStyles(page)).some((h) =>
				tokenStylesheetFor(h, PREVIEW_SET),
			),
		).toBe(true)

		const other = nonAdmin!.page
		await other.goto('/settings/user', { waitUntil: 'domcontentloaded' })
		const styles = await nldesignStyles(other)
		expect(
			styles.some((h) => tokenStylesheetFor(h, activeSet)),
			'the non-admin must still get the instance-wide set',
		).toBe(true)
		expect(
			styles.some((h) => tokenStylesheetFor(h, PREVIEW_SET)),
			"the non-admin must NOT receive the admin's previewed set",
		).toBe(false)
		await expect(
			other.locator('#nldesign-preview-banner'),
			'the non-admin must see no preview banner',
		).toHaveCount(0)
	})

	// @e2e openspec/specs/theme-preview/spec.md#discard-from-the-banner
	test('discarding from the banner restores the active set and removes the banner', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		expect(
			(
				await api(page, 'POST', `${APP}/settings/preview`, {
					tokenSet: PREVIEW_SET,
				})
			).status,
		).toBe(200)

		await page.goto('/settings/user')
		const banner = page.locator('#nldesign-preview-banner')
		await banner.waitFor({ state: 'visible', timeout: 15_000 })

		// Activate Discard the way a keyboard user would, not with a raw API
		// call — the scenario is about the BANNER's control doing the work.
		const discard = banner.locator('.nldesign-preview-banner-discard')
		await discard.focus()
		await page.keyboard.press('Enter')

		// The handler calls DELETE and reloads; wait for the banner to go.
		await expect(banner, 'the banner must disappear after Discard').toHaveCount(
			0,
			{ timeout: 20_000 },
		)

		await page.goto('/settings/user', { waitUntil: 'domcontentloaded' })
		const styles = await nldesignStyles(page)
		expect(
			styles.some((h) => tokenStylesheetFor(h, activeSet)),
			'the instance-wide set must render again after Discard',
		).toBe(true)
		expect(
			styles.some((h) => tokenStylesheetFor(h, PREVIEW_SET)),
			'the previewed set must be gone after Discard',
		).toBe(false)
	})

	// @e2e openspec/specs/theme-preview/spec.md#publish-without-an-active-preview
	test('publishing with no active preview is refused and changes nothing', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		await api(page, 'DELETE', `${APP}/settings/preview`)

		const before = await activeTokenSet(page)
		const res = await api(page, 'POST', `${APP}/settings/preview/publish`)
		expect(res.status, 'publish without a preview must be a 400').toBe(400)

		const after = await activeTokenSet(page)
		expect(after, 'a refused publish must not change the active set').toEqual(
			before,
		)
	})

	// @e2e openspec/specs/theme-preview/spec.md#publish-promotes-preview-to-instance-wide-active
	test('publishing promotes the preview to the instance-wide active set', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		// activeTokenSet() returns {tokenSet} from the capability, and setTokenSet()
		// takes {tokenSet}, verified against lib/Capabilities.php and
		// lib/Controller/SettingsController.php rather than guessed, because this value is what the finally block
		// restores. Asserting it is present means a shape change breaks this
		// test loudly instead of silently restoring `undefined`.
		const before = await activeTokenSet(page)
		expect(
			before?.tokenSet,
			'the active token set must be readable before publishing',
		).toBeTruthy()
		const originalSet: string = before.tokenSet

		try {
			expect(
				(
					await api(page, 'POST', `${APP}/settings/preview`, {
						tokenSet: PREVIEW_SET,
					})
				).status,
			).toBe(200)

			const pub = await api(page, 'POST', `${APP}/settings/preview/publish`)
			expect(pub.status, 'publish must succeed with an active preview').toBe(
				200,
			)
			expect(pub.json.tokenSet).toBe(PREVIEW_SET)

			// Instance-wide now, not just for this session.
			const after = await activeTokenSet(page)
			expect(JSON.stringify(after)).toContain(PREVIEW_SET)

			// ...and the caller's preview values are cleared, so no banner.
			await page.goto('/settings/user')
			await expect(
				page.locator('#nldesign-preview-banner'),
				'publishing must clear the preview, removing the banner',
			).toHaveCount(0)
		} finally {
			// Restore the instance-wide set — this is the one test here that
			// mutates state every other test depends on.
			await page.goto(THEMING_URL)
			await api(page, 'POST', `${APP}/settings/tokenset`, {
				tokenSet: originalSet,
			})
		}
	})
})
