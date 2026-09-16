/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/lasuite-parity/spec.md
 *
 * SELECTOR LIVENESS — does every rule this theme ships actually match anything?
 *
 * Every visual defect found in the 2026-07-28/29 parity rounds had the same
 * root cause: a selector that reads plausibly, passes review, lints clean, and
 * matches NOTHING in the live DOM.
 *
 *   - `#app-navigation …`     Nextcloud 34 renders `#app-navigation-vue`.
 *                             12 of 13 rules were inert; the one live rule lost
 *                             on specificity to nc-vue's scoped `!important`,
 *                             so the active row kept its brand tint.
 *   - `#content.content`      Only the INNER shell carries the `content` class.
 *                             The outer shell kept Nextcloud's 8px inset and
 *                             16px radius — the body-colour frame — while the
 *                             inner one got a second 50px top margin, opening
 *                             the band under the header.
 *   - `.button-vue--vue-tertiary`
 *                             NC34 emits BOTH that and `button-vue--tertiary`.
 *                             Measured on one page: 60 plain vs 1 `--vue-`.
 *                             The block listed only `--vue-`, so it styled one
 *                             button and missed sixty.
 *
 * None of these fail loudly. Nothing in the unit suite can see them, because
 * they are only wrong RELATIVE TO A RENDERED PAGE. This spec is the check that
 * would have caught all three on the day they were written.
 *
 * UNION SEMANTICS. A selector is judged against the union of every surface
 * below, not each one individually — a rule for the Files grid is legitimately
 * absent on Calendar. It fails only when it matches nothing ANYWHERE.
 *
 * FAIL-CLOSED ALLOWLIST. Selectors that genuinely cannot match on any surveyed
 * surface (transient overlays, deliberate cross-version fallbacks) must be
 * listed in ALLOWED below WITH A REASON, in the same spirit as the `@spec
 * exclude` gates. Adding a bare pattern with no reason is not permitted: the
 * point is that dead selectors become a decision someone made on purpose,
 * rather than something nobody noticed.
 *
 * TWO DEFERRALS THAT EXPIRE, KEPT SEPARATE FROM THAT ALLOWLIST. An allowance
 * never lapses, and these must, so they are not folded into it:
 *
 *   - SINCE       markup only a NEWER Nextcloud renders. Deferred while the
 *                 surveyed major is below `since`, and refused outright once
 *                 the survey reaches the app's declared max-version, so
 *                 raising `nextcloud-test-refs` cannot carry it forward.
 *   - REQUIRES_APP  markup owned by an app this fixture does not install.
 *                 Checked against `OC.appswebroots`, the instance's own list
 *                 of enabled apps, so installing the app puts the selector
 *                 straight back under the main assertion.
 *
 * Both are REPORTED with counts on every run. A deferral nobody can see is an
 * allowance with better manners.
 *
 * GLOBAL STATE. Requires the `lasuite` set to be active — the spec skips
 * (rather than silently passing) when element-overrides.css is absent, because
 * a guard that quietly measures nothing is the exact failure mode it exists to
 * prevent. Beyond that it WRITES three pieces of instance state, each
 * snapshotted and restored: the active token set, one public link share (minted
 * in beforeAll, deleted in afterAll, because `body#body-public` is reachable no
 * other way), and Nextcloud's dark theme (on immediately before the dark
 * surface, off immediately after, never left set for a later spec to inherit).
 */

import { expect, test, type Page } from '@playwright/test'
import {
	THEMING_URL,
	getTokenSet,
	requestToken,
	setTokenSet,
} from '../workflows/_helpers'

/**
 * A node that only exists once Nextcloud's Vue chrome has mounted.
 *
 * Both halves are Vue-rendered and absent from the server document, so the
 * presence of either is proof that the bundles have run and the header
 * subtree the theme targets is really there to be probed.
 */
const CHROME_READY = '#header .app-menu, #header .unified-search-input'

/**
 * THE HEADER IS NOT THE WHOLE CHROME — three bundles, one anchor.
 *
 * `CHROME_READY` proves the HEADER app mounted, and nothing more. The left
 * navigation (`#app-navigation-vue`) ships in the per-app bundle and the
 * notifications bell in the notifications app's own; both mount independently
 * of, and later than, the header. The survey used to wait for CHROME_READY,
 * then `waitForSelector('#app-navigation-vue', …).catch(() => {})` — a
 * container check whose failure was swallowed — and probe immediately.
 *
 * That is why the 2026-08-10 run reported eleven selectors dead that are not.
 * Measured against a real Nextcloud with `lasuite` active (32.0.12 and 34.0.2,
 * both), on /apps/files/ at rest:
 *
 *     #app-navigation-vue .app-navigation-entry.active            -> 1
 *     … .app-navigation-entry-link / __name / a / ::before        -> 1 each
 *     .app-navigation-entry.app-navigation-entry.active.active…   -> 1 each
 *     #header .notifications-button__icon svg                     -> 1
 *
 * Waiting for the CONTAINER and asserting on its CONTENTS is the same mistake
 * the rest of this suite exists to catch, one level up: `#app-navigation-vue`
 * is attached long before the app registers its views and the router marks one
 * `.active`. So wait for the ITEM.
 */
const NAV_READY = '#app-navigation-vue .app-navigation-entry.active'

/** The notifications bell — a third bundle again, and its own mount clock. */
const BELL_READY = '#header .notifications-button__icon'

/**
 * An EXPLICITLY EMPTY session, for the surfaces that must be anonymous.
 *
 * `storageState: undefined` is not this. Playwright merges context options
 * over the config's `use` block, and an `undefined` value reads as "not
 * specified" — so the root `use.storageState` (tests/e2e/.auth/admin.json)
 * is inherited anyway and the context is quietly logged in as admin. An
 * empty state OBJECT cannot be merged away: there is nothing ambiguous about
 * zero cookies and zero origins.
 *
 * Measured, and the reason this constant exists: with `undefined`, CI's run
 * of this spec reported `body-user` on `/login` — Nextcloud had served the
 * dashboard to a session the "anonymous" context should not have had. It was
 * caught only because the login surface asserts its own body id; the selector
 * verdict itself would have been green, because token gating does not depend
 * on the caller.
 */
const EMPTY_SESSION = { cookies: [], origins: [] }

type Surface = {
	name: string
	path: string
	/**
	 * Probe in a FRESH context with NO stored session.
	 *
	 * `use.storageState` in playwright.config.ts is inherited by every context
	 * created inside a test, so a `browser.newContext()` meant to be anonymous
	 * silently carries the admin cookies — and `/login` then redirects to the
	 * dashboard, quietly surveying the wrong page. `storageState: undefined` is
	 * the only thing that makes the anonymity real, and the surface asserts its
	 * own body id afterwards so a regression here cannot pass unnoticed.
	 */
	anonymous?: boolean
	/** Turn Nextcloud's dark theme on for this surface, and off again after. */
	dark?: boolean
	/**
	 * Late-mounting nodes this surface renders, waited for before probing.
	 * Failure to appear is fatal and named — these are the anchors that stop
	 * the survey measuring an unfinished page. An entry carrying `requiresApp`
	 * is only awaited when that app is actually enabled on the instance.
	 */
	awaitAlso?: Array<{ selector: string; requiresApp?: string }>
	/** Resolved at run time (the public share token is minted in beforeAll). */
	resolvePath?: () => string
}

/**
 * Surfaces surveyed. Each contributes its DOM to the union.
 *
 * The first six are the original authenticated app surfaces. The last three
 * exist because the union was too narrow to judge the stylesheet it was
 * judging: this theme also styles the LOGIN page, PUBLIC share pages and the
 * DARK variant, and none of those is reachable from an authenticated app
 * surface in the default theme. All three were reported "dead" on 2026-08-10
 * and all three are live where they belong — measured, on 32.0.12 and 34.0.2:
 *
 *     /login                     .guest-box.login-box      -> 1
 *     /s/<token>                 body#body-public          -> 1
 *     /apps/files/ (dark on)     body[data-theme-dark]     -> 1
 *                                body[data-themes*="dark"] -> 1
 */
const SURFACES: Array<Surface> = [
	{
		name: 'files',
		path: '/apps/files/',
		awaitAlso: [
			{ selector: NAV_READY },
			{ selector: BELL_READY, requiresApp: 'notifications' },
		],
	},
	{ name: 'contacts', path: '/apps/contacts/' },
	{ name: 'mail', path: '/apps/mail/' },
	{ name: 'calendar', path: '/apps/calendar/' },
	{ name: 'settings', path: '/settings/user' },
	{ name: 'dashboard', path: '/apps/dashboard/' },
	{ name: 'login', path: '/login', anonymous: true },
	{
		name: 'public-share',
		path: '',
		anonymous: true,
		resolvePath: () => publicShareUrl,
	},
	{ name: 'files-dark', path: '/apps/files/', dark: true },
]

/**
 * Selectors permitted to match nothing on every surveyed surface.
 * Each entry is a substring or /regex/ plus the reason it cannot match.
 */
const ALLOWED: Array<{ pattern: RegExp; reason: string }> = [
	{
		pattern: /^#app-navigation(?![-\w])/,
		reason: 'Deliberate pre-NC34 fallback; NC34 renders #app-navigation-vue. Kept so the theme still applies on older servers.',
	},
	{
		pattern: /#content\.content|#app-content(?!-vue)/,
		reason: 'Cross-version shell fallbacks. NC34 uses #content (no class) + #content-vue + #app-content-vue.',
	},
	{
		pattern: /nav\.app-menu/,
		reason: 'NC34 renders the app menu through a different structure; kept for older servers.',
	},
	{
		pattern:
			/\.header-appname|\.header-left|\.header-right|\.menutoggle|\.unified-search__button|\.header-start \.icon-vue/,
		reason: 'Pre-Vue header classes retained as fallbacks for older Nextcloud releases.',
	},
	{
		pattern: /^(button|input)\.(primary|secondary)\b/,
		reason: 'Pre-Vue button classes; NC34 emits .button-vue--* which the adjacent wildcard rules catch.',
	},
	{
		pattern: /--active/,
		reason: 'BEM --active variant; NC34 uses .active. Both spellings are carried deliberately.',
	},
	{
		pattern: /\.modal-container|\.popover|\.dropdown|\.oc-dialog|\.toastify/,
		reason: 'Transient overlays — only in the DOM while open, never on a page at rest.',
	},
	{
		pattern: /\.list-item__wrapper\.active/,
		reason: 'Alternate selection spelling; NC34 uses .list-item__wrapper--active. Both carried.',
	},
	{
		pattern: /app-navigation--close/,
		reason: 'Collapsed-sidebar state; the class only exists while the navigation is collapsed.',
	},
	{
		pattern: /^(textarea|select|\.button)$|^input\[type=|^h[4-6]$/,
		reason:
			'Base-layer rules for plain form controls and minor headings. The surveyed surfaces are '
			+ 'Vue apps that render their own components instead, but these still apply on form-bearing '
			+ 'admin pages and inside dialogs.',
	},
	{
		pattern: /unified-search__input/,
		reason: 'Pre-NC34 unified-search markup, retained as a fallback for older servers.',
	},
	{
		pattern: /\.app-menu-entry__|\.app-menu-icon\b|\.unified-search-menu\b/,
		reason:
			'NC 32/33 header markup — the mirror image of the SINCE list below, and dead here for '
			+ 'exactly the reason its entries were dead before the pin moved. The survey now runs on '
			+ 'stable34 (nextcloud-test-refs[0]), where the header is built from app-menu__waffle and '
			+ 'unified-search-input; NC 32 builds it from app-menu-entry*, app-menu-icon and '
			+ 'unified-search-menu instead. MEASURED both ways: the NC 32.0.12 / NC 34.0.2 table below '
			+ 'records the NC34-only half, and run 31588593102 — the first survey ever taken on '
			+ 'stable34 — recorded these eight matching nothing on any surface. appinfo/info.xml '
			+ 'declares min-version="32" and the PHPUnit matrix still carries a stable32 leg, so this '
			+ 'half of the stylesheet is kept on purpose: it is what themes the header on the oldest '
			+ 'server this app is offered to.',
	},
	{
		pattern: /^\[data-admin-theming-setting-color-(picker|reset)\] \*$/,
		reason:
			'NC 32/33 colour-picker markup. Those releases render the admin colour fields as '
			+ 'NcButtons carrying data-admin-theming-setting-color-picker / -color-reset. NC 34 '
			+ 'rewrote Settings > Theming as a Vue 3 app with CSS-module classes '
			+ '(_colorPickerField__button_<hash>) and no data attribute: MEASURED on run '
			+ '35051300741, whose admin-theming DOM snapshot has neither attribute anywhere. '
			+ 'The rule is still what keeps the picker label legible on the NC 32 floor. The NC 34 '
			+ 'picker is not excluded by any rule yet; that gap is tracked separately rather than '
			+ 'hidden here.',
	},
]

function allowedReason(selector: string): string | null {
	for (const { pattern, reason } of ALLOWED) {
		if (pattern.test(selector)) return reason
	}
	return null
}

/**
 * The newest Nextcloud this app declares support for (appinfo/info.xml
 * `<nextcloud min-version="32" max-version="34"/>`). It is the expiry date on
 * every SINCE entry below: once CI surveys this major, nothing may be deferred.
 */
const MAX_SUPPORTED_NC = 34

/**
 * A ONE-VERSION SURVEY CANNOT JUDGE A CROSS-VERSION STYLESHEET.
 *
 * ALLOWED above carries selectors kept for OLDER servers than the one CI runs
 * ("Deliberate pre-NC34 fallback…"). This list is its missing mirror image:
 * selectors for markup only a NEWER server renders. The theme has to carry
 * both, because the app supports NC 32 through 34 — and on any single instance
 * one of the two halves is necessarily unmatchable.
 *
 * Unlike ALLOWED, an entry here is NOT a permanent excuse. It is deferred only
 * while the surveyed server is older than `since`; on `since` or newer the
 * selector is required live like any other, and the assertion below refuses to
 * defer anything at all once the survey reaches MAX_SUPPORTED_NC. A stale
 * entry therefore fails the moment CI moves up, instead of rotting quietly.
 *
 * MEASURED, not assumed. CI pins
 * `nextcloud-test-refs: ["stable34", "stable32"]` (see
 * .github/workflows/code-quality.yml — openregister cannot install below 32),
 * and Playwright surveys entry [0], so THIS SPEC NOW RUNS ON NC 34. Probing
 * both versions with `lasuite` active, on /apps/files/ at rest:
 *
 *   selector                                    NC 32.0.12   NC 34.0.2
 *   #header .app-menu__waffle                        0            1
 *   #header .app-menu__waffle svg                    0            1
 *   #header .app-menu__current-app-icon              0            1
 *   #header .app-menu__current-app-name              0            1
 *   #header .app-menu__current-app .button-vue__text 0            1
 *   #header .unified-search-input                    0            1
 *   #header .unified-search-input svg                0            1
 *   #header .unified-search-input__button            0            1
 *   #header .unified-search-input__icon              0            1
 *   #header .unified-search-input__label             0            1
 *   #header .header-start .button-vue__icon          0            2
 *   #header .header-start svg                        0            1
 *
 * NC 32 builds its header from a different component set entirely — the class
 * names present there are `app-menu-entry--active`, `app-menu-entry__icon`,
 * `app-menu__list` and `unified-search-menu`. So these twelve are not stale CSS
 * and not a timing artefact; they are markup that server does not have.
 *
 * THE PIN HAS SINCE BEEN RAISED, and the entry below did exactly what it was
 * built to do: on the first survey of NC 34 every selector it defers came back
 * live, so `versionDeferred` is empty and the expiry assertion passes without
 * anyone having to remember it. The entry is kept rather than deleted because
 * it remains a true statement about NC 32 — the app's declared floor, and the
 * second leg of the PHPUnit matrix — and it re-arms by itself if the refs are
 * ever reordered.
 *
 * That same survey exposed the mirror set, which nothing could measure while CI
 * stayed on 32: eight NC 32/33-only header selectors that match nothing on NC
 * 34. They are in ALLOWED above, with the same measurement behind them.
 *
 * STILL OPEN, and deliberately not smuggled in here: the table above also says
 * the La Suite header treatment does not reach NC 32 at all — its own change.
 */
const SINCE: Array<{ pattern: RegExp; since: number; reason: string }> = [
	{
		pattern:
			/^#header \.(app-menu__waffle|app-menu__current-app|unified-search-input|header-start (\.button-vue__icon|svg))/,
		since: 33,
		reason:
			'NC33+ header markup (waffle / current-app / inline unified-search). NC32 renders '
			+ 'app-menu-entry* + unified-search-menu instead, so these cannot match there.',
	},
]

function deferredUntil(selector: string): { since: number; reason: string } | null {
	for (const { pattern, since, reason } of SINCE) {
		if (pattern.test(selector)) return { since, reason }
	}
	return null
}

/**
 * A SELECTOR WHOSE OWNING APP IS NOT INSTALLED IS NOT A DEAD SELECTOR.
 *
 * Same shape as SINCE, different precondition. CI builds its instance from a
 * `nextcloud/server` checkout plus `additional-apps`
 * (.github/workflows/code-quality.yml: openregister and nldesign, nothing
 * else). Several apps that ship inside the released Nextcloud tarball live in
 * their OWN repositories and are therefore absent from that fixture — and any
 * theme rule targeting their markup then matches nothing, on an instance where
 * the markup simply does not exist.
 *
 * MEASURED. `notifications-button__icon` appears nowhere in a Nextcloud
 * release except `apps/notifications/` (grepped the 32-apache image: three
 * files, all under that directory). On an instance that HAS the app the
 * selector is live — probed at rest on /apps/files/, count 1, on 32.0.12 and
 * 34.0.2 alike.
 *
 * Like SINCE and unlike ALLOWED, this expires by itself: the check is against
 * `OC.appswebroots`, the instance's own list of enabled apps, so the day the
 * app joins the fixture the selector is required live again.
 */
const REQUIRES_APP: Array<{ pattern: RegExp; appId: string; reason: string }> = [
	{
		pattern: /notifications-button__icon/,
		appId: 'notifications',
		reason:
			'The bell is rendered by the notifications app, which lives in its own repository and is '
			+ 'not part of the nextcloud/server checkout CI builds its instance from.',
	},
]

function deferredUntilApp(
	selector: string,
): { appId: string; reason: string } | null {
	for (const { pattern, appId, reason } of REQUIRES_APP) {
		if (pattern.test(selector)) return { appId, reason }
	}
	return null
}

/**
 * A SUBTREE THIS FIXTURE NEVER RENDERED IS NOT A DEAD SELECTOR EITHER.
 *
 * Third precondition, same discipline as SINCE and REQUIRES_APP: the check is
 * a measured fact about this run, and it inverts the moment the fact changes.
 *
 * MEASURED, on CI (run 31408716704) against its own `nextcloud/server`
 * stable32 checkout served by `php -S`: the Files left navigation renders its
 * entries — the bare `.app-navigation-entry` and `.app-navigation-entry:hover`
 * rules are live there — but NO entry is ever marked `.active`, on any surface,
 * even after waiting 60s for one. The same page on a released Nextcloud has
 * one: probed at rest on 32.0.12 and 34.0.2, `#app-navigation-vue
 * .app-navigation-entry.active` matches exactly 1 node.
 *
 * So the ten active-row selectors are correct CSS that this fixture cannot
 * exercise. Deferring them is not an excuse for them: `anchor` is a selector
 * the survey actively waits for and records, so the day CI's navigation marks
 * an active entry they are required live again, automatically. If instead the
 * navigation renders nothing at all, `chromeless` and the live-count floor
 * below still fail the run — this defers a missing STATE, never a missing page.
 */
const REQUIRES_RENDERED: Array<{ pattern: RegExp; anchor: string; reason: string }> =
	[
		{
			pattern:
				/app-navigation-entry[\w.-]*\.active|\.active[\w.-]*\s+\.app-navigation-entry/,
			anchor: NAV_READY,
			reason:
				'The active-row selectors need the router to mark one navigation entry active. This '
				+ 'fixture renders the entries but never the active state.',
		},
	]

function deferredUntilRendered(
	selector: string,
): { anchor: string; reason: string } | null {
	for (const { pattern, anchor, reason } of REQUIRES_RENDERED) {
		if (pattern.test(selector)) return { anchor, reason }
	}
	return null
}

/**
 * Turn Nextcloud's own dark theme on or off for the signed-in account.
 *
 * `body[data-themes*="dark"]` and `body[data-theme-dark]` are the hooks the
 * theme hangs its dark variant on, and no default-themed surface carries
 * either — which is why both were reported dead. Measured with this call in
 * place: `data-theme-dark=""` and `data-themes="dark"` appear on <body>, one
 * match each.
 *
 * The route is `PUT /api/v1/theme/{id}/enable`, NOT `PUT /api/v1/theme/{id}`
 * — the latter is the disable route and answers 405 to a PUT. It is called
 * IN THE PAGE because Nextcloud rejects a cookie-only request without
 * `requesttoken`, and the response status is asserted rather than assumed: a
 * theme that never switched leaves the two selectors dead and would be read
 * as a stylesheet fault.
 */
async function setDarkTheme(page: Page, enabled: boolean): Promise<void> {
	const status = await page.evaluate(async (on) => {
		const token = (window as unknown as { OC: { requestToken: string } }).OC
			.requestToken
		const res = await fetch(
			on
				? '/ocs/v2.php/apps/theming/api/v1/theme/dark/enable'
				: '/ocs/v2.php/apps/theming/api/v1/theme/dark',
			{
				method: on ? 'PUT' : 'DELETE',
				headers: { requesttoken: token, 'OCS-APIRequest': 'true' },
			},
		)
		return res.status
	}, enabled)
	expect(
		status,
		`switching Nextcloud's dark theme ${enabled ? 'on' : 'off'} failed`,
	).toBe(200)
}

let baselineTokenSet: string | null = null
let publicShareUrl = ''
let publicShareId: string | null = null

test.describe('lasuite selector liveness', () => {
	// NOBODY WAS ACTIVATING THE THEME THIS SPEC MEASURES.
	//
	// The docblock above says "Requires the `lasuite` set to be active" and then
	// nothing in the file, the config, or the CI seed ever activated it. The seed
	// sets `rijkshuisstijl`; the only spec that switches to `lasuite` is
	// lasuite-parity, which runs earlier (alphabetically) and dutifully RESTORES
	// the baseline in its `afterAll`. So by the time this file ran, the lasuite
	// bundle was not being served at all.
	//
	// The two tests below failed differently on that, and the difference is the
	// whole lesson:
	//
	//   - The selector sweep is fail-closed and skipped, printing "lasuite
	//     element-overrides.css was not served on any surface". That is exactly
	//     one line in a 110-test summary, and it renders as "1 skipped". The
	//     repo's single most valuable guard — the one written because five
	//     defects in a row were dead selectors — had never once executed.
	//   - The geometry lock had no such guard, so it measured STOCK Nextcloud
	//     chrome and asserted La Suite's full-bleed shell against it. It reported
	//     `x` = 8: Nextcloud's own 8px inset, correctly present on a page the
	//     theme was not applied to. The failure named a theming regression that
	//     did not exist. (Confirmed from run 31086399980's own failure
	//     screenshot: stock Nextcloud blue, blue frame down the left edge.)
	//
	// A guard that skips on a fixture nobody built and a guard that fails on one
	// are the same bug wearing two faces. Both are fixed by building the fixture
	// here, where the requirement is stated — snapshot the active set, activate
	// `lasuite`, restore afterwards, same pattern as lasuite-parity's.
	//
	// The fail-closed skip below is deliberately KEPT. It is now unreachable in
	// the normal case, which is the point: if it ever fires again it means this
	// hook stopped working, and that has to be visible rather than silently
	// turning into a green sweep over zero selectors.
	test.beforeAll(async ({ browser }) => {
		test.setTimeout(120_000)
		const page = await browser.newPage()
		await page.goto(THEMING_URL, { waitUntil: 'domcontentloaded' })
		const token = await requestToken(page)
		baselineTokenSet = await getTokenSet(page, token)
		await setTokenSet(page, token, 'lasuite')

		// A PUBLIC SHARE IS THE ONLY WAY TO REACH `body#body-public`.
		//
		// The theme styles public link pages, and no authenticated app surface
		// ever carries that body id, so the survey could not see the rule and
		// called it dead. Mint one link share here and drop it in afterAll, the
		// same snapshot-and-restore discipline the token set gets. The OCS call
		// runs IN THE PAGE because Nextcloud rejects a cookie-only request
		// without `requesttoken`; a `page.request` call would come back 412 and
		// a 412 body parsed for a token yields undefined, which is exactly how a
		// failed read gets mistaken for a value.
		const share = await page.evaluate(async () => {
			const oc = (
				window as unknown as {
					OC: {
						requestToken: string
						getCurrentUser: () => { uid: string }
					}
				}
			).OC
			// A dedicated folder, not `/`: Nextcloud answers 403 to a link share
			// on the root, and not a skeleton folder either — `skeletondirectory`
			// can be empty on a fresh instance, so /Documents is not guaranteed
			// to exist. MKCOL is idempotent enough here (405 = already there).
			const dir = 'nldesign-selector-liveness'
			await fetch(`/remote.php/dav/files/${oc.getCurrentUser().uid}/${dir}`, {
				method: 'MKCOL',
				headers: { requesttoken: oc.requestToken },
			})
			const res = await fetch('/ocs/v2.php/apps/files_sharing/api/v1/shares', {
				method: 'POST',
				headers: {
					requesttoken: oc.requestToken,
					'OCS-APIRequest': 'true',
					'Content-Type': 'application/json',
					Accept: 'application/json',
				},
				body: JSON.stringify({
					path: `/${dir}`,
					shareType: 3,
					permissions: 1,
				}),
			})
			if (!res.ok)
				return {
					status: res.status,
					token: null as string | null,
					id: null as string | null,
				}
			const json = await res.json()
			return {
				status: res.status,
				token: json?.ocs?.data?.token ?? null,
				id: String(json?.ocs?.data?.id ?? ''),
			}
		})
		// Fail loudly rather than surveying eight surfaces and calling the ninth
		// dead: a share that was never created is a measurement failure.
		expect(
			share.token,
			`could not mint the public link share the survey needs (OCS status ${share.status})`,
		).toBeTruthy()
		publicShareUrl = `/s/${share.token}`
		publicShareId = share.id

		await page.close()
	})

	test.afterAll(async ({ browser }) => {
		if (baselineTokenSet === null) return
		const page = await browser.newPage()
		await page.goto(THEMING_URL, { waitUntil: 'domcontentloaded' })
		const token = await requestToken(page)
		await setTokenSet(page, token, baselineTokenSet)
		if (publicShareId !== null && publicShareId !== '') {
			await page.evaluate(async (id) => {
				await fetch(`/ocs/v2.php/apps/files_sharing/api/v1/shares/${id}`, {
					method: 'DELETE',
					headers: {
						requesttoken: (
							window as unknown as { OC: { requestToken: string } }
						).OC.requestToken,
						'OCS-APIRequest': 'true',
					},
				})
			}, publicShareId)
		}
		// And the folder the share hung off, so the spec leaves the instance as
		// it found it rather than accreting one directory per run.
		await page.evaluate(async () => {
			const oc = (
				window as unknown as {
					OC: {
						requestToken: string
						getCurrentUser: () => { uid: string }
					}
				}
			).OC
			await fetch(
				`/remote.php/dav/files/${oc.getCurrentUser().uid}/nldesign-selector-liveness`,
				{ method: 'DELETE', headers: { requesttoken: oc.requestToken } },
			)
		})
		await page.close()
	})

	test('every element-overrides selector matches something on at least one surface', async ({
		browser,
		page,
	}) => {
		// Nine surfaces, most of them a full Vue app boot. test.slow() alone is
		// not enough on a loaded dev instance, so the budget is set explicitly.
		test.setTimeout(420_000)

		const liveEverywhere = new Set<string>()
		let allSelectors: string[] = []
		let sheetSeenOnce = false
		let serverMajor = 0

		const unreachable: string[] = []
		const chromeless: string[] = []
		/** `<surface>:<selector>` for every late-mounting node that never arrived. */
		const unrendered: string[] = []
		/** Anchors deliberately not awaited because their app is not enabled here. */
		const skippedAnchors: string[] = []
		/** Anchors this fixture really did render, on at least one surface. */
		const renderedAnchors = new Set<string>()
		/** Every app id this instance reports as enabled, unioned over surfaces. */
		const installedApps = new Set<string>()
		// Per-surface tallies, printed at the end. A survey that quietly measured
		// nothing and a survey that measured everything reach the same verdict
		// otherwise — and a green over an empty match set is this repo's own
		// signature failure (thirteen a11y gates once passed planted true
		// positives because every one of them globbed `src/**/*.vue`, and
		// nldesign ships zero .vue files).
		const perSurface: Array<{ name: string; selectors: number; live: number }> =
			[]

		for (const surface of SURFACES) {
			const path =
				surface.resolvePath === undefined
					? surface.path
					: surface.resolvePath()

			// An anonymous surface gets its OWN context with the session
			// explicitly cleared. Inheriting `use.storageState` would send admin's
			// cookies to /login, Nextcloud would redirect to the dashboard, and
			// the survey would measure a logged-in app while reporting that it had
			// measured the login screen — green, and about the wrong page.
			const context =
				surface.anonymous === true
					? await browser.newContext({ storageState: EMPTY_SESSION })
					: null
			const probePage = context === null ? page : await context.newPage()

			// Dark is instance-visible state, so it goes on immediately before
			// this surface and off immediately after — never left set for a later
			// spec to inherit.
			if (surface.dark === true) await setDarkTheme(page, true)

			try {
				// A surface that will not load on a loaded dev box must not fail the
				// guard: union semantics mean a missing surface can only make the
				// result MORE conservative (fewer selectors proven live), never
				// produce a false accusation. Unreachable surfaces are reported,
				// not fatal — a guard that breaks when the instance is slow gets
				// switched off, and then it protects nothing.
				try {
					await probePage.goto(path, {
						waitUntil: 'domcontentloaded',
						timeout: 45_000,
					})
				} catch {
					unreachable.push(surface.name)
					continue
				}

				// WAIT FOR WHAT YOU ARE ABOUT TO MEASURE, NOT FOR ITS CONTAINER.
				//
				// Nextcloud's chrome is not one bundle. The header, the per-app
				// left navigation and the notifications bell mount independently
				// and on different clocks; none of them is in the server document
				// (fetched /apps/files/ raw: 33705 bytes, `id="header"` present,
				// `app-menu`, `app-navigation-vue` and `notifications-button__icon`
				// all absent).
				//
				// The previous revision waited for CHROME_READY — which only proves
				// the HEADER mounted — and then did
				// `waitForSelector('#app-navigation-vue').catch(() => {})`: a
				// CONTAINER check whose failure was swallowed. `#app-navigation-vue`
				// is attached long before the app registers its views and the
				// router marks one entry `.active`, so eleven selectors under the
				// navigation and the bell were probed too early and reported dead.
				// They are not: on a real instance at rest they match with counts
				// of 1-2 on NC 32.0.12 AND 34.0.2.
				//
				// So: wait for the ITEM, and record anything that never arrived
				// instead of silently probing an unfinished page.
				//
				// The login and public-share surfaces have no Vue app chrome by
				// design, so the header wait is skipped there rather than counted
				// as a failure.
				if (surface.anonymous !== true) {
					try {
						await probePage.waitForSelector(CHROME_READY, {
							state: 'attached',
							timeout: 60_000,
						})
					} catch {
						chromeless.push(surface.name)
					}
				}
				// `OC.appswebroots` is the instance's own map of ENABLED apps. It
				// is read here, from the page, rather than assumed: whether the
				// bell can render at all is a property of this fixture, and an
				// anchor waited for on an instance that cannot produce it would
				// burn its whole timeout and then fail for the wrong reason.
				if (surface.anonymous !== true) {
					const apps = await probePage.evaluate(() =>
						Object.keys(
							(
								window as unknown as {
									OC?: { appswebroots?: Record<string, string> }
								}
							).OC?.appswebroots ?? {},
						),
					)
					apps.forEach((app) => installedApps.add(app))
				}
				for (const anchor of surface.awaitAlso ?? []) {
					if (
						anchor.requiresApp !== undefined
						&& !installedApps.has(anchor.requiresApp)
					) {
						skippedAnchors.push(
							`${surface.name}: ${anchor.selector} (app '${anchor.requiresApp}' not enabled)`,
						)
						continue
					}
					try {
						await probePage.waitForSelector(anchor.selector, {
							state: 'attached',
							timeout: 60_000,
						})
						renderedAnchors.add(anchor.selector)
					} catch {
						unrendered.push(`${surface.name}: ${anchor.selector}`)
					}
				}

				// POSITIVE CONTROL ON THE ANONYMITY. `/login` served to a logged-in
				// browser is a redirect to the dashboard, and a dashboard surveyed
				// under the name "login" contributes nothing the other surfaces did
				// not already have — while looking exactly like a clean run.
				if (surface.name === 'login') {
					const bodyId = await probePage.evaluate(() => document.body.id)
					expect(
						bodyId,
						'the login surface must really be the login screen — a body id of "body-user" means the '
							+ 'anonymous context inherited the admin session and the survey measured an app page',
					).toBe('body-login')
				}

				const result = await probePage.evaluate(() => {
					const sheet = [...document.styleSheets].find(
						(s) =>
							s.href
							&& /systems\/lasuite\/element-overrides\.css/.test(
								s.href,
							),
					)
					if (!sheet) return null

					let rules: CSSRule[]
					try {
						rules = [...sheet.cssRules]
					} catch {
						return null
					}

					const selectors: string[] = []
					const live: string[] = []

					for (const rule of rules) {
						const styleRule = rule as CSSStyleRule
						if (!styleRule.selectorText) continue
						for (const raw of styleRule.selectorText.split(',')) {
							const sel = raw.trim()
							if (!sel) continue
							selectors.push(sel)
							// Pseudo-elements and interaction states can never match at rest;
							// probe the element they hang off instead.
							const probe = sel
								.replace(/::[a-z-]+(\([^)]*\))?/gi, '')
								.replace(
									/:(hover|focus|focus-within|focus-visible|active|visited|target|placeholder|disabled|checked)\b(\([^)]*\))?/gi,
									'',
								)
								.trim()
							if (!probe) continue
							try {
								if (document.querySelectorAll(probe).length > 0)
									live.push(sel)
							} catch {
								/* invalid probe after stripping — reported as dead */
							}
						}
					}
					// The running server's major version. Which half of a
					// cross-version stylesheet CAN match here depends on it, so it
					// is read from the page rather than assumed.
					const version =
						(
							window as unknown as {
								OC?: { config?: { version?: string } }
							}
						).OC?.config?.version ?? ''
					return {
						selectors,
						live,
						major: Number.parseInt(version.split('.')[0], 10) || 0,
					}
				})

				if (result === null) continue
				sheetSeenOnce = true
				if (result.major > serverMajor) serverMajor = result.major
				allSelectors = [...new Set([...allSelectors, ...result.selectors])]
				result.live.forEach((s) => liveEverywhere.add(s))
				perSurface.push({
					name: surface.name,
					selectors: result.selectors.length,
					live: new Set(result.live).size,
				})
			} finally {
				if (surface.dark === true) await setDarkTheme(page, false)
				if (context !== null) await context.close()
			}
		}

		test.skip(
			!sheetSeenOnce,
			'lasuite element-overrides.css was not served on any surface — activate the lasuite token set to run this guard',
		)

		// PRINT THE COUNTS. Not decoration: the only difference between this
		// guard working and this guard measuring an empty page is a number, and
		// a number nobody prints is a number nobody checks.
		console.warn(
			`[selector-liveness] Nextcloud major ${serverMajor}; `
				+ `${allSelectors.length} distinct selectors in element-overrides.css; `
				+ `${liveEverywhere.size} proven live across ${perSurface.length} surveyed surfaces`,
		)
		for (const surveyed of perSurface) {
			console.warn(
				`[selector-liveness]   ${surveyed.name}: ${surveyed.live} live of ${surveyed.selectors} probed`,
			)
		}
		if (unreachable.length > 0) {
			// Surfaced rather than swallowed: a run that silently surveyed half the
			// surfaces looks identical to a clean one otherwise.
			console.warn(
				`[selector-liveness] surfaces that did not load: ${unreachable.join(', ')}`,
			)
		}
		if (chromeless.length > 0) {
			console.warn(
				`[selector-liveness] surfaces whose Vue chrome never mounted: ${chromeless.join(', ')}`,
			)
		}
		if (unrendered.length > 0) {
			console.warn(
				`[selector-liveness] late-mounting nodes that never arrived: ${unrendered.join('; ')}`,
			)
		}

		// The survey has to have SEEN something. Zero selectors parsed, or almost
		// none proven live, means the sheet was served but unreadable or the pages
		// never rendered — either way the verdict below is meaningless, and
		// "meaningless" must not be spelled PASS.
		expect(
			allSelectors.length,
			'the survey parsed no selectors at all out of element-overrides.css',
		).toBeGreaterThan(100)
		expect(
			liveEverywhere.size,
			'almost nothing in the stylesheet was proven live — the survey measured empty pages',
		).toBeGreaterThan(50)

		// A survey that reached its surfaces but never saw the chrome mount
		// measured a server-rendered skeleton, and EVERY selector under
		// `#header` or `#app-navigation-vue` would be reported dead. That is
		// the exact false accusation this test made before the wait above
		// existed, and it must be impossible to make silently: fail on the
		// measurement, naming it, rather than on the CSS it cannot see.
		expect(
			chromeless.length,
			`The Vue chrome never mounted on ${chromeless.join(', ')} — those surfaces were measured as a `
				+ `server-rendered skeleton. Every #header / #app-navigation-vue selector would read as dead. `
				+ `This is a failure of the MEASUREMENT, not of the stylesheet: do not "fix" the CSS on it.`,
		).toBeLessThan(SURFACES.length)
		// An `awaitAlso` anchor that never arrived is NOT failed here. It is fed
		// into REQUIRES_RENDERED below, which defers exactly the selectors that
		// depend on it and leaves every other selector under the full assertion.
		// Failing outright would be the honest thing only if the missing anchor
		// meant the page was unfinished — but `chromeless` and the live-count
		// floor already cover that case, and this one is narrower: a subtree the
		// fixture renders WITHOUT the state the selectors need.
		expect(
			unreachable.length,
			`Too few surfaces loaded (${unreachable.join(', ')}) — the union would be too narrow to trust`,
		).toBeLessThan(SURFACES.length - 1)

		const dead = allSelectors.filter((sel) => !liveEverywhere.has(sel))

		// Deferred: correct CSS for a Nextcloud newer than the one CI surveys.
		// Split out and REPORTED rather than folded into ALLOWED, because unlike
		// an allowance this one expires — see SINCE.
		const deferred = dead
			.filter((sel) => allowedReason(sel) === null)
			.filter((sel) => {
				const versionGate = deferredUntil(sel)
				if (versionGate !== null && serverMajor < versionGate.since)
					return true
				const appGate = deferredUntilApp(sel)
				if (appGate !== null && !installedApps.has(appGate.appId))
					return true
				const renderGate = deferredUntilRendered(sel)
				return renderGate !== null && !renderedAnchors.has(renderGate.anchor)
			})
			.sort()
		if (deferred.length > 0) {
			console.warn(
				`[selector-liveness] ${deferred.length} selectors deferred — they target markup this `
					+ `fixture cannot render (Nextcloud major ${serverMajor}; `
					+ `${installedApps.size} apps enabled):\n  ${deferred.join('\n  ')}`,
			)
		}
		if (skippedAnchors.length > 0) {
			console.warn(
				`[selector-liveness] readiness anchors not awaited: ${skippedAnchors.join('; ')}`,
			)
		}

		// BOTH DEFERRALS EXPIRE BY THEMSELVES, WHICH IS WHAT SEPARATES THEM FROM
		// AN ALLOWANCE.
		//
		// The app deferral expires above, in the filter: it only applies while
		// `OC.appswebroots` says the app is absent, so installing the app puts
		// the selector straight back under the main assertion.
		//
		// The version deferral needs this explicit expiry, because "a newer
		// Nextcloud" stops existing at the newest one the app supports. Without
		// it, raising `nextcloud-test-refs` would silently carry the exemptions
		// forward and the twelve header selectors would go on never being
		// verified anywhere.
		const versionDeferred = deferred.filter((sel) => deferredUntil(sel) !== null)
		expect(
			serverMajor >= MAX_SUPPORTED_NC ? versionDeferred : [],
			`Surveyed Nextcloud ${serverMajor} is the newest version this app supports `
				+ `(appinfo/info.xml max-version=${MAX_SUPPORTED_NC}), so nothing may be deferred to a newer `
				+ `one. These selectors match nothing here and their SINCE entry has expired — delete the `
				+ `entry and the CSS, or fix the selectors:\n  ${versionDeferred.join('\n  ')}`,
		).toEqual([])

		const deferredSet = new Set(deferred)
		const unexplainedDead = dead
			.filter((sel) => allowedReason(sel) === null)
			.filter((sel) => !deferredSet.has(sel))
			.sort()

		expect(
			unexplainedDead,
			`These selectors matched NOTHING on any surveyed surface. Either fix them against the real DOM, `
				+ `or add them to ALLOWED with the reason they cannot match:\n  ${unexplainedDead.join('\n  ')}`,
		).toEqual([])
	})

	test('the two shells the theme resizes are actually present and full-bleed', async ({
		page,
	}) => {
		// Regression lock for the band-and-frame defect specifically: it is not
		// enough that the selectors match — the boxes have to reach the edges,
		// or the body colour shows through again.
		await page.goto('/apps/files/', { waitUntil: 'domcontentloaded' })
		await page.waitForTimeout(2500)

		const geometry = await page.evaluate(() => {
			const shell = document.querySelector(
				'#content-vue',
			) as HTMLElement | null
			if (!shell) return null
			const r = shell.getBoundingClientRect()
			return {
				x: Math.round(r.x),
				width: Math.round(r.width),
				viewport: window.innerWidth,
			}
		})

		test.skip(
			geometry === null,
			'#content-vue not present on this Nextcloud version',
		)

		expect(
			geometry!.x,
			'the content shell must start at the window edge, not inset',
		).toBe(0)
		expect(
			geometry!.width,
			'the content shell must span the full viewport, or body colour shows down the side',
		).toBe(geometry!.viewport)
	})
})
