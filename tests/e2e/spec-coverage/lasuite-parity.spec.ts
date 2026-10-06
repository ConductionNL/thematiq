/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/lasuite-parity/spec.md
 *
 * Component-parity check for the `lasuite` and `cunningham` design systems:
 * renders real Nextcloud elements under each active set and asserts their
 * COMPUTED styles equal the Cunningham reference values sourced directly
 * from css/systems/lasuite/{defaults,brand-override,bridge,element-
 * overrides}.css — i.e. "does the CSS this app ships actually apply as
 * authored", not an independently-invented pixel spec.
 *
 * SCOPE HONESTY: nldesign's own admin panel is vanilla JS (no Vue), and its
 * element-overrides.css styles only a handful of real NC selectors
 * concretely (button.primary, input[type=text], #header, .modal-container,
 * plus the shared body font-family rule). For each of the five elements
 * below, the reference table only lists the properties this app's CSS
 * ACTUALLY sets for that selector — asserting an un-sourced property (e.g.
 * a button's padding, which nldesign never declares and NC's own Cn* button
 * component owns) would test Nextcloud/Vue-library internals unrelated to
 * this app's own claims, and would be brittle across NC versions.
 *
 * GLOBAL STATE: activating `lasuite`/`cunningham` mutates the INSTANCE-WIDE
 * active token set (same class of mutation as tests/e2e/workflows/*). The
 * prior token set is snapshotted in beforeAll and RESTORED in afterAll.
 *
 * LOAD-FRAGILE INSTANCE (issue #181): runs `mode: 'serial'`, one `test()`
 * per element, small and batchable — a flake isolates to one element, not
 * the whole suite.
 */
import { test, expect, Page } from '@playwright/test'
import { requestToken, getTokenSet, setTokenSet } from '../workflows/_helpers'

const THEMING_URL = '/settings/admin/theming'

type StyleRef = Partial<{
	backgroundColor: string // hex, e.g. '#4844ad'
	color: string // hex
	borderColor: string // hex
	borderWidth: string // e.g. '1px'
	borderStyle: string // e.g. 'solid'
	borderRadius: string // e.g. '4px'
	fontFamily: string[] // lower-cased family list, e.g. ['marianne', 'inter', 'sans-serif']
	fontWeight: string // e.g. '600'
}>

interface ElementRef {
	/** Human-readable element name for assertion messages. */
	name: string
	/** CSS selector for the element under test (must exist on the chosen surface). */
	selector: string
	/**
	 * Which rendered surface carries this element.
	 *
	 * `admin` (default) is the nldesign admin theming page, which renders through
	 * Nextcloud's AUTHENTICATED layout. `public` is a public link share, which
	 * renders through `core/templates/layout.public.php` — a different chrome
	 * with a different header. One declaration block in element-overrides.css
	 * legitimately spans both, and a row is only honest if it is measured where
	 * its selector exists.
	 */
	surface?: 'admin' | 'public'
	ref: StyleRef
}

// Reference values sourced from css/systems/lasuite/{defaults,brand-override,
// bridge,element-overrides}.css. brand-override.css redeclares La Suite's FULL
// deployed colour system — not just the brand ramp but the violet-TINTED
// gray/white/black neutrals and its vivid semantic palettes (generated from the
// deployed build; see scripts/generate-lasuite-tokens.mjs --override). So the
// grays differ per set: the lasuite bundle loads brand-override.css (violet-
// tinted), the cunningham bundle does not (neutral npm base). White (gray-000)
// is #ffffff in both.
// NOTE on gray-100: it remains the structural hairline the sidebar draws as its
// border-RIGHT. It is not asserted below because readComputedStyle() reads
// border-BOTTOM only, and the header — the one element that used to carry a
// gray-100 bottom rule — is now deliberately rule-less to match La Suite
// Messages' flat top bar.
const GRAY_300: Record<'lasuite' | 'cunningham', string> = {
	lasuite: '#a9a9bf', // brand-override.css — deployed violet-tinted (input borders)
	cunningham: '#a7acb2', // defaults.css neutral npm base
}
const GRAY_000 = '#ffffff' // --lasuite-color-gray-000 (white — identical in both sets)
const BORDER_RADIUS = '4px' // --lasuite-border-radius (literal — no upstream token)
const FONT_STACK = ['marianne', 'inter', 'sans-serif'] // --lasuite-font-family

const BRAND_650: Record<'lasuite' | 'cunningham', string> = {
	lasuite: '#4844ad', // brand-override.css — deployed violet (logo / button hover)
	cunningham: '#1a509f', // defaults.css blue base (brand-override.css not loaded)
}

// La Suite's .c__button--brand--primary fills with brand-550 at REST and
// brand-650 on HOVER (verified 2026-07-26 against the shipped Cunningham CSS via
// a local computed-style comparison). Filling brand-650 at rest previously made
// our button-at-rest match La Suite's HOVER — one step too dark.
const BRAND_550: Record<'lasuite' | 'cunningham', string> = {
	lasuite: '#5e5cd0', // brand-override.css — deployed violet (primary button rest)
	cunningham: '#1167d4', // defaults.css blue base
}
// On-brand button text is brand-050 (a pale violet-white), not pure white.
const BRAND_050: Record<'lasuite' | 'cunningham', string> = {
	lasuite: '#eef1fa', // brand-override.css — deployed violet
	cunningham: '#eaf1fb', // defaults.css blue base
}

function referenceTable(set: 'lasuite' | 'cunningham'): ElementRef[] {
	const brand650 = BRAND_650[set]
	const brand550 = BRAND_550[set]
	const brand050 = BRAND_050[set]
	const gray300 = GRAY_300[set]
	return [
		{
			name: 'primary button',
			// #nldesign-group-theming-save ships class="button primary" in
			// templates/settings/admin.php, matching element-overrides.css's
			// `button[class*="primary"]` / `button.primary` rules. Fill and
			// border are brand-550 at REST (brand-650 on hover); text is brand-050.
			selector: '#nldesign-group-theming-save',
			ref: {
				backgroundColor: brand550,
				color: brand050,
				borderColor: brand550,
				borderRadius: BORDER_RADIUS,
			},
		},
		{
			name: 'text input',
			// #nldesign-upload-name is a real input[type="text"] in
			// templates/settings/admin.php.
			selector: '#nldesign-upload-name',
			ref: {
				borderColor: gray300,
				borderWidth: '1px',
				borderStyle: 'solid',
				borderRadius: BORDER_RADIUS,
				fontFamily: FONT_STACK,
			},
		},
		// Merge note — development DELETED this row, correctly observing that
		// `#header .header-appname` exists only in Nextcloud's PUBLIC layout
		// (core/templates/layout.public.php); the authenticated layout this spec
		// runs against has no such element on 31 or 34, so the row blew a 15s
		// waitForSelector on every run (30889958278, 30892246034).
		//
		// That diagnosis is right about the OLD selector, and this row is kept
		// rather than deleted because it no longer uses it: NC34 renders the
		// current app's name through `.app-menu__current-app-name`, which does
		// exist here and is measured live below. Repairing a check keeps the
		// coverage that deleting it loses — and a parity table that quietly
		// stops asserting is the failure mode this suite exists to prevent.
		// nldesign's `#header .header-appname` rules remain live on public
		// pages; covering the public layout still needs its own spec.
		{
			name: 'header app name',
			// THIRD SELECTOR, THIRD 15s TIMEOUT — and this time the version the
			// suite RUNS ON is the thing that was never checked.
			//
			// The row previously read `#header .app-menu__current-app-name`,
			// chosen because "Nextcloud 34 renders the current app's name" that
			// way. That is true of NC34. It is not true of the Nextcloud this job
			// installs: the shared workflow's `nextcloud-test-refs` defaults to
			// `["stable31","stable32"]` and the Playwright job takes element [0],
			// so every run of this suite has been against **stable31**.
			// `app-menu__current-app-name` appears in NO stable31 or stable32
			// source file — the current-app button is a 34-era addition — so the
			// row could only ever time out. The page snapshot from run 31086399980
			// confirms it directly: stable31's header is an icon-only app list
			// (`nav.app-menu > ul > li > a`) with no app-name text at all.
			//
			// The declaration block under test (element-overrides.css:151-153)
			// covers THREE selectors, and one of them IS live on stable31:
			// `#header .header-start .header-appname`, in
			// `core/templates/layout.public.php`. Same block, same declared
			// values — so measuring there tests exactly the CSS this row has
			// always meant to test, on chrome the server actually renders.
			// `surface: 'public'` navigates to a public link share created by the
			// test itself; `public` is one of CssInjectionService's five valid
			// render contexts, so the theme is injected there.
			//
			// Deleting the row was the alternative, and it is the one this file
			// has already rejected twice: a parity table that quietly stops
			// asserting is the failure mode the suite exists to prevent.
			selector: '#header .header-start .header-appname',
			surface: 'public',
			ref: {
				color: brand650,
				// 700, not 600. Read off La Suite Docs (localhost:3000), which renders
				// its wordmark as TEXT and so exposes a computed value — Messages ships
				// an image and cannot be measured. Live: rgb(72,68,173), 22px, 700.
				// The colour in this reference was already right; the weight was not.
				fontWeight: '700',
			},
		},
		{
			name: 'header bar',
			// La Suite Messages' top bar is white, flat and RULE-LESS: measured on
			// the live app (localhost:8900, 2026-07-27) it carries a 1px bottom
			// border whose colour is transparent, and box-shadow: none. The theme
			// reproduces that, so there is deliberately no visible hairline here —
			// only the border-box metrics are asserted, plus the white surface.
			selector: '#header',
			ref: {
				backgroundColor: GRAY_000,
				borderWidth: '1px', // border-bottom-width
				borderStyle: 'solid', // border-bottom-style
			},
		},
		{
			name: 'app navigation sidebar',
			// La Suite's sidebar is flush and SQUARE (measured x=0, y=0,
			// border-radius 0) on a white surface, with a soft right shadow
			// instead of a rounded card. Radius 0 is the assertable half of that;
			// the shadow and the x=0 flush position are covered by the layout
			// checks below rather than this colour/metric table.
			// Both ids, exactly as css/systems/lasuite/element-overrides.css
			// writes them ("#app-navigation, .app-navigation,
			// #app-navigation-vue { … }") — one declaration block, so whichever
			// the running Nextcloud renders carries the identical treatment.
			// The settings page is server-rendered as `#app-navigation` on 31
			// and Vue-rendered as `#app-navigation-vue` on 34; pinning only the
			// 34 id made this row unsatisfiable on the version CI runs, and it
			// blew a 15s waitForSelector (run 30893391595). Matching the CSS
			// selector under test is the point, not a looser locator.
			selector: '#app-navigation, #app-navigation-vue',
			ref: {
				backgroundColor: GRAY_000,
				borderRadius: '0px',
			},
		},
		{
			name: 'audit-log table',
			// #nldesign-audit-table is a real, always-rendered <table> in
			// templates/settings/admin.php (theming audit log).
			selector: '#nldesign-audit-table',
			ref: {
				// The only property nldesign's CSS actually sources for a bare
				// <table>: font-family, inherited through the shared body/
				// .app-content font-family rule (element-overrides.css). No
				// nldesign rule targets table borders/background/padding, so
				// they are intentionally NOT asserted here.
				fontFamily: FONT_STACK,
			},
		},
	]
}

/** #rrggbb → 'rgb(r, g, b)' — getComputedStyle always reports colours as rgb(). */
function hexToRgb(hex: string): string {
	const h = hex.replace('#', '')
	const r = parseInt(h.slice(0, 2), 16)
	const g = parseInt(h.slice(2, 4), 16)
	const b = parseInt(h.slice(4, 6), 16)
	return `rgb(${r}, ${g}, ${b})`
}

/** Normalise a computed font-family list for comparison: split, trim, lower-case, strip quotes. */
function normaliseFontFamily(computed: string): string[] {
	return computed.split(',').map((f) =>
		f
			.trim()
			.replace(/^['"]|['"]$/g, '')
			.toLowerCase(),
	)
}

interface ComputedSubset {
	backgroundColor: string
	color: string
	borderColor: string
	borderWidth: string
	borderStyle: string
	borderRadius: string
	fontFamily: string
	fontWeight: string
}

/** Read the subset of computed styles this spec ever compares, for one element. */
async function readComputedStyle(
	page: Page,
	selector: string,
): Promise<ComputedSubset> {
	return page.evaluate((sel) => {
		const el = document.querySelector(sel)
		if (!el) throw new Error(`Element not found: ${sel}`)
		const cs = getComputedStyle(el)
		return {
			backgroundColor: cs.backgroundColor,
			color: cs.color,
			// For elements whose reference only cares about ONE border edge
			// (header's border-bottom, input's full border), reading the
			// bottom edge is a superset-safe choice: for elements with a
			// uniform border (input) top/right/bottom/left are identical, so
			// borderBottomColor equals the value a full `border` shorthand
			// check would report too.
			borderColor: cs.borderBottomColor,
			borderWidth: cs.borderBottomWidth,
			borderStyle: cs.borderBottomStyle,
			borderRadius: cs.borderRadius,
			fontFamily: cs.fontFamily,
			fontWeight: cs.fontWeight,
		}
	}, selector)
}

/**
 * Compare computed styles against a reference, asserting only the
 * properties present on `ref`. On mismatch, throws naming the exact
 * property and the expected-vs-actual delta.
 */
function assertMatchesReference(
	elementName: string,
	computed: ComputedSubset,
	ref: StyleRef,
): void {
	if (ref.backgroundColor !== undefined) {
		expect(
			computed.backgroundColor,
			`${elementName}: background-color — expected ${ref.backgroundColor} (${hexToRgb(ref.backgroundColor)}), got ${computed.backgroundColor}`,
		).toBe(hexToRgb(ref.backgroundColor))
	}
	if (ref.color !== undefined) {
		expect(
			computed.color,
			`${elementName}: color — expected ${ref.color} (${hexToRgb(ref.color)}), got ${computed.color}`,
		).toBe(hexToRgb(ref.color))
	}
	if (ref.borderColor !== undefined) {
		expect(
			computed.borderColor,
			`${elementName}: border-color — expected ${ref.borderColor} (${hexToRgb(ref.borderColor)}), got ${computed.borderColor}`,
		).toBe(hexToRgb(ref.borderColor))
	}
	if (ref.borderWidth !== undefined) {
		expect(
			computed.borderWidth,
			`${elementName}: border-width — expected ${ref.borderWidth}, got ${computed.borderWidth}`,
		).toBe(ref.borderWidth)
	}
	if (ref.borderStyle !== undefined) {
		expect(
			computed.borderStyle,
			`${elementName}: border-style — expected ${ref.borderStyle}, got ${computed.borderStyle}`,
		).toBe(ref.borderStyle)
	}
	if (ref.borderRadius !== undefined) {
		expect(
			computed.borderRadius,
			`${elementName}: border-radius — expected ${ref.borderRadius}, got ${computed.borderRadius}`,
		).toBe(ref.borderRadius)
	}
	if (ref.fontFamily !== undefined) {
		const actual = normaliseFontFamily(computed.fontFamily)
		expect(
			actual,
			`${elementName}: font-family — expected [${ref.fontFamily.join(', ')}], got [${actual.join(', ')}]`,
		).toEqual(ref.fontFamily)
	}
	if (ref.fontWeight !== undefined) {
		expect(
			computed.fontWeight,
			`${elementName}: font-weight — expected ${ref.fontWeight}, got ${computed.fontWeight}`,
		).toBe(ref.fontWeight)
	}
}

let baselineTokenSet: string | null = null

/**
 * Create a public link share and return its absolute page path.
 *
 * Needed by the `surface: 'public'` rows, which measure chrome that only
 * `core/templates/layout.public.php` renders. Created per-test rather than in
 * `beforeAll` on purpose: a `beforeAll` that throws fails EVERY test in the
 * describe, so a transient sharing hiccup would have reddened all thirteen rows
 * and told you nothing about twelve of them.
 *
 * Fails loudly on any non-200 — an OCS 200 with an error `meta.statuscode` is
 * still an error, so the OCS status is checked separately from the HTTP status.
 */
async function createPublicShare(page: Page, token: string): Promise<string> {
	const res = await page.evaluate(async (t) => {
		const base = (
			window as unknown as {
				OC: { linkToOCS: (s: string, v: number) => string }
			}
		).OC.linkToOCS('apps/files_sharing/api/v1', 2)
		const r = await fetch(`${base}shares?format=json`, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'OCS-APIRequest': 'true',
				requesttoken: t,
			},
			// welcome.txt is seeded into every fresh Nextcloud's admin home by
			// core's first-login hook; shareType 3 = public link, permission 1 =
			// read.
			body: JSON.stringify({
				path: '/welcome.txt',
				shareType: 3,
				permissions: 1,
			}),
		})
		return { status: r.status, body: await r.text() }
	}, token)

	expect(
		res.status,
		`public share creation: HTTP ${res.status} — ${res.body.slice(0, 400)}`,
	).toBe(200)
	const parsed = JSON.parse(res.body)
	expect(
		parsed?.ocs?.meta?.statuscode,
		`public share creation: OCS statuscode ${parsed?.ocs?.meta?.statuscode} — ${parsed?.ocs?.meta?.message}`,
	).toBe(200)

	const shareToken = parsed?.ocs?.data?.token
	expect(shareToken, 'public share creation returned no token').toBeTruthy()
	return `/index.php/s/${shareToken}`
}

test.describe('lasuite-parity', () => {
	// NO `mode: 'serial'`.
	//
	// It used to be here, with the rationale "a flake isolates to one element,
	// not the whole suite". Serial mode does the exact opposite of that: when one
	// test in a serial group fails, Playwright ABANDONS every later test in the
	// group. On run 31086399980 the `lasuite: header app name` row timed out and
	// took TEN further tests down with it — three remaining lasuite rows, all six
	// cunningham rows, and the unified-search modal check. Those ten reported
	// "did not run": no pass, no fail, no verdict, and nothing in the summary line
	// distinguishing them from tests that were never written.
	//
	// The isolation the comment wanted comes from the one-test-per-element split,
	// which is orthogonal to serial mode and is kept. Nothing here depends on a
	// previous test: every test navigates, reads its own CSRF token, sets its own
	// token set and reloads. And `workers: 1` in playwright.config.ts means
	// dropping serial introduces no concurrency at all — on each CI shard the
	// tests still execute one at a time, in order. The only thing that changes
	// is that a failure stops being contagious.
	//
	// (Under `fullyParallel` sharding the tests may be spread over several
	// shards. Each shard that gets some runs `beforeAll`/`afterAll` for them, so
	// the token-set snapshot/restore still brackets every test.)

	// Every navigation in this suite settles slowly on a loaded instance, and the
	// default 30s budget turns that into a reported PARITY failure — the report
	// names the element ("primary button matches the Cunningham reference") while
	// the actual error is a navigation timeout, which reads as a regression that
	// is not there. Budget set once for the whole describe.
	test.setTimeout(120_000)

	// @e2e exclude openspec/specs/lasuite-parity/spec.md#mismatch-names-the-property-and-delta
	// Proven by manually reverting a reference value and observing the
	// Playwright assertion message during development (see PR description);
	// asserting a DELIBERATE failure is not something a passing CI suite can
	// carry permanently.

	test.beforeAll(async ({ browser }) => {
		// This hook mutates instance-wide state, so it gets a realistic budget:
		// the default 30s is not enough on a loaded instance and the suite then
		// aborts on a "beforeAll hook timeout" that looks like a parity failure
		// in the report but is nothing of the kind.
		test.setTimeout(120_000)

		const page = await browser.newPage()
		// `domcontentloaded`, not `networkidle`: Nextcloud polls in the
		// background (notifications, dashboard widgets), so the network never
		// goes idle and this wait simply burns the hook's whole budget.
		await page.goto(THEMING_URL, { waitUntil: 'domcontentloaded' })
		const token = await requestToken(page)
		baselineTokenSet = await getTokenSet(page, token)
		await page.close()
	})

	test.afterAll(async ({ browser }) => {
		if (baselineTokenSet === null) return
		const page = await browser.newPage()
		await page.goto(THEMING_URL, { waitUntil: 'domcontentloaded' })
		// `domcontentloaded`, not `networkidle`: Nextcloud polls in the background, so
		// the network never goes idle and this wait burns the whole test budget.
		await page.waitForLoadState('domcontentloaded')
		const token = await requestToken(page)
		await setTokenSet(page, token, baselineTokenSet)
		await page.close()
	})

	for (const set of ['lasuite', 'cunningham'] as const) {
		for (const element of referenceTable(set)) {
			test(`${set}: ${element.name} matches the Cunningham reference`, async ({
				page,
			}) => {
				await page.goto(THEMING_URL, { waitUntil: 'domcontentloaded' })
				// `domcontentloaded`, not `networkidle`: Nextcloud polls in the background, so
				// the network never goes idle and this wait burns the whole test budget.
				await page.waitForLoadState('domcontentloaded')
				const token = await requestToken(page)
				await setTokenSet(page, token, set)

				// The token set is applied from the ADMIN page in both cases —
				// it needs `OC.requestToken`, which a public share page does not
				// expose. Only the surface the element is then MEASURED on
				// differs.
				if (element.surface === 'public') {
					await page.goto(await createPublicShare(page, token), {
						waitUntil: 'domcontentloaded',
					})
				} else {
					await page.reload({ waitUntil: 'domcontentloaded' })
				}
				// `domcontentloaded`, not `networkidle`: Nextcloud polls in the background, so
				// the network never goes idle and this wait burns the whole test budget.
				await page.waitForLoadState('domcontentloaded')

				await page.waitForSelector(element.selector, { timeout: 15_000 })
				const computed = await readComputedStyle(page, element.selector)
				assertMatchesReference(
					`${set} ${element.name}`,
					computed,
					element.ref,
				)
			})
		}
	}

	test('unified-search modal, when present, matches the shared radius token', async ({
		page,
	}) => {
		// .modal-container is styled defensively by element-overrides.css
		// (theming NC-core's OWN native modal component) but nldesign's own
		// vanilla-JS admin panel never renders one itself — so this check
		// must trigger a REAL NC-core modal to have anything to assert.
		// Unified search is the most stable, single-click native trigger
		// already referenced by this app's own CSS
		// (#header .unified-search__button in element-overrides.css — a
		// selector that, as the locator note below records, matches nothing on
		// any supported Nextcloud). If the installed NC version does not
		// surface `.modal-container` for it (implementation detail that varies
		// by NC release), skip rather than fail on something unrelated to this
		// app's theming code.
		// Navigate BEFORE reading the CSRF token. `requestToken()` evaluates
		// `window.OC.requestToken` in the page, and a fresh `page` fixture is on
		// about:blank, where `OC` is undefined — so this threw
		// "Cannot read properties of undefined (reading 'requestToken')" before
		// it reached anything it meant to assert. Every other test in this
		// describe goes to THEMING_URL first; this one did not. It had never
		// surfaced because the describe WAS `mode: 'serial'` at the time and an
		// earlier failure always stopped the block before this test ran — the
		// same cascade that is removed at the top of this describe.
		await page.goto(THEMING_URL, { waitUntil: 'domcontentloaded' })
		// `domcontentloaded`, not `networkidle`: Nextcloud polls in the background,
		// so the network never goes idle and this wait burns the whole test budget.
		// This was the last `networkidle` left in the file — every other wait here
		// had already been converted, so it would have been the only one tripping
		// the e2e-networkidle gate.
		await page.waitForLoadState('domcontentloaded')
		const token = await requestToken(page)
		await setTokenSet(page, token, 'lasuite')
		await page.goto(THEMING_URL, { waitUntil: 'domcontentloaded' })
		// `domcontentloaded`, not `networkidle`: Nextcloud polls in the background, so
		// the network never goes idle and this wait burns the whole test budget.
		// (Merge note: development reloaded and waited on `networkidle` here. Same
		// intent — re-render after the theme is applied — but `networkidle` is the
		// wait the e2e-networkidle gate exists to catch, for this exact reason.)
		await page.waitForLoadState('domcontentloaded')

		// THE TRIGGER CLASS DIFFERS ON EVERY SUPPORTED NEXTCLOUD, AND
		// `.unified-search__button` IS NONE OF THEM. Read off core/src/views/
		// UnifiedSearch.vue at each ref:
		//
		//   stable31  <div class="header-menu unified-search-menu">
		//               <NcButton class="header-menu__trigger">
		//   stable32  <div class="unified-search-menu">
		//               <NcHeaderButton id="unified-search">
		//   NC34      identical to stable32
		//
		// So the sole locator this test had matched nothing on any of them, the
		// count was always 0, and the test skipped every time it was reached
		// with a message — "not present in header on this NC version" — that
		// reads like a benign version quirk and was in fact a locator that is
		// wrong everywhere. It never once asserted the modal radius it exists
		// to assert. (It was reached rarely in any case: the serial cascade
		// removed at the top of this describe usually stopped the block first.)
		//
		// Scoped to the search menu rather than a page-wide role query: a bare
		// `getByRole('button').first()` would land on whichever control happens
		// to render first and pass for the wrong reason.
		const searchButton = page.locator(
			'#header .unified-search-menu .header-menu__trigger, #header #unified-search, #header .unified-search__button',
		)
		if ((await searchButton.count()) === 0) {
			test.skip(
				true,
				'unified-search trigger not present in header on this NC version',
			)
			return
		}
		await searchButton.first().click()

		const modal = page.locator('.modal-container')
		try {
			await modal.first().waitFor({ state: 'visible', timeout: 5_000 })
		} catch {
			test.skip(
				true,
				'.modal-container did not appear for unified search on this NC version',
			)
			return
		}

		const borderRadius = await modal
			.first()
			.evaluate((el) => getComputedStyle(el).borderRadius)
		expect(
			borderRadius,
			`modal: border-radius — expected ${BORDER_RADIUS}, got ${borderRadius}`,
		).toBe(BORDER_RADIUS)

		// Best-effort cleanup — close the dialog so it doesn't linger for the
		// next test in this serial run.
		await page.keyboard.press('Escape').catch(() => {})
	})
})
