/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Rendered-surface contrast guard (nldesign#268).
 *
 * The unit tests in tests/Unit/ check the arithmetic of the token values and
 * the presence of the stylesheet rules. Neither can see what the browser
 * actually paints, and this defect lived entirely in that gap: the token values
 * were individually fine, the stylesheets were all present, and the app still
 * added four serious WCAG AA failures that stock Nextcloud does not have,
 * because a blanket `!important` text rule outranked Nextcloud's own
 * light-on-dark pairings and a saturated fill was painted into a token whose
 * NC 34 contract is a pale one.
 *
 * Measured on a Nextcloud 34 instance, changing ONLY whether nldesign was
 * enabled — the numbers this file exists to keep at zero:
 *
 *   /settings/user    .preview-card__header > span   1.75:1
 *   /apps/dashboard/  #app-dashboard > h2            2.91:1
 *   /apps/dashboard/  secondary button label         2.91:1
 *   /settings/admin   .notecard--success             3.94:1
 *   /settings/admin   .notecard--info   (x2)         3.95:1
 *
 * and on the `hoog-contrast` set — whose entire purpose is contrast — 2.44:1
 * and 2.06:1.
 *
 * The contrast maths is inlined rather than pulled from axe-core so this spec
 * adds no dependency and no `enable-axe` decision. It agrees with axe to two
 * decimals on every node above; the WCAG formula is short enough that a copy is
 * cheaper than a runtime that has its own failure modes (.github#351).
 */
import { test, expect, Page } from '@playwright/test'
import {
	requestToken,
	getTokenSet,
	setTokenSet,
	THEMING_URL,
} from '../workflows/_helpers'

/** WCAG 2.2 AA floors. */
const AA_NORMAL = 4.5
const AA_LARGE = 3.0

/**
 * The admin section that renders note cards: Basic settings, with the
 * background-jobs and profile cards. Named explicitly, because Nextcloud 35
 * opens /settings/admin on the overview instead, which has none.
 */
const NOTECARD_ROUTE = '/settings/admin/server'

/**
 * How one surface is found on the page.
 *
 * `backdrop` names the element that paints the fill when it is not an ancestor
 * of the text but a sibling the text is positioned over. Without it the fill is
 * looked for up the text's own ancestors.
 */
type SurfaceTarget = { selector: string; backdrop?: string }

/**
 * Surfaces Nextcloud paints with a saturated colour, each of which regressed.
 *
 * `min` is the floor for that node's own font size — the dashboard heading is
 * large bold text, so 3.0 is the correct floor for it, not 4.5. Using 4.5
 * everywhere would be a stricter test that fails on compliant markup.
 *
 * `targets` lists the markup a surface has had across the Nextcloud versions
 * CI runs, oldest first; the first one present on the page is measured.
 */
const SURFACES: Array<{
	route: string
	targets: SurfaceTarget[]
	min: number
	why: string
}> = [
	{
		route: '/settings/user',
		targets: [
			// Nextcloud 34 and older: a header strip that holds the name.
			{ selector: '.preview-card__header' },
			// Nextcloud 35: the name is positioned over a banner that is its
			// sibling, not its parent.
			{ selector: '.preview-card__name', backdrop: '.preview-card__banner' },
		],
		min: AA_NORMAL,
		why: 'profile preview card name on its primary-tinted fill',
	},
	{
		route: '/apps/dashboard/',
		targets: [{ selector: '#app-dashboard > h2' }],
		min: AA_LARGE,
		why: 'dashboard greeting on the plain background',
	},
	{
		route: NOTECARD_ROUTE,
		targets: [{ selector: '.notecard--success' }],
		min: AA_NORMAL,
		why: 'NcNoteCard success fill',
	},
	{
		route: NOTECARD_ROUTE,
		targets: [{ selector: '.notecard--info' }],
		min: AA_NORMAL,
		why: 'NcNoteCard info fill',
	},
]

/**
 * Compute the effective contrast ratio of an element, resolving a transparent
 * background up the ancestor chain the way a browser composites it.
 *
 * Three distinct outcomes, deliberately not two:
 *   - `null`        the element is absent. NOT a pass; an absent node and a
 *                   compliant one must not look the same.
 *   - `undetermined` a background IMAGE is painted somewhere in the chain, so
 *                   there is no single background colour to measure against.
 *                   Also NOT a pass, and not a failure either — asserting on it
 *                   would turn "we cannot see this" into a red build. axe-core
 *                   classifies the same nodes as `incomplete` for the same
 *                   reason. Found the hard way: on the hoog-contrast set the
 *                   dashboard greeting sits on Nextcloud's illustrated
 *                   background, and a walker blind to images reads it as
 *                   #ffffff on #ffffff — a 1:1 "failure" that is nothing but
 *                   the measurement's own blind spot.
 *   - a ratio       the real thing.
 */
type ContrastResult =
	{ ratio: number; fg: string; bg: string } | { undetermined: string } | null

async function contrastOf(
	page: Page,
	target: SurfaceTarget,
): Promise<ContrastResult> {
	const args = { sel: target.selector, bd: target.backdrop ?? null }
	return await page.evaluate(({ sel, bd }) => {
		const container = document.querySelector(sel) as HTMLElement | null
		if (!container) return null
		// Where the fill is looked for from: the named backdrop, else the text.
		const backdrop =
			bd === null ? null : (document.querySelector(bd) as HTMLElement | null)
		if (bd !== null && !backdrop) return null

		// Measure the node that actually HOLDS the text, not the container that
		// happens to paint the fill. A container's own `color` is inert when its
		// text lives in a child, and reading it produces confident nonsense: on
		// the hoog-contrast set `.preview-card__header` computes #000000 on a
		// #000000 fill — a "1:1 failure" on an element that renders no text,
		// while the child span it delegates to is white and perfectly legible.
		const hasOwnText = (e: Element) =>
			[...e.childNodes].some(
				(n) =>
					n.nodeType === Node.TEXT_NODE
					&& (n.textContent || '').trim().length > 0,
			)
		const el =
			(hasOwnText(container)
				? container
				: ([...container.querySelectorAll('*')].find(hasOwnText) as
						HTMLElement | undefined)) ?? container

		for (
			let probe: HTMLElement | null = backdrop ?? el;
			probe;
			probe = probe.parentElement
		) {
			const cs = getComputedStyle(probe)
			if (cs.backgroundImage && cs.backgroundImage !== 'none') {
				return {
					undetermined: `background-image on ${probe.tagName.toLowerCase()}${probe.id ? '#' + probe.id : ''}`,
				}
			}
			if (
				cs.backgroundColor
				&& !/rgba\(\d+, \d+, \d+, 0\)/.test(cs.backgroundColor)
			)
				break
		}

		const parse = (c: string): [number, number, number, number] => {
			const m = c.match(
				/rgba?\(([\d.]+),\s*([\d.]+),\s*([\d.]+)(?:,\s*([\d.]+))?\)/,
			)
			if (!m) return [255, 255, 255, 1]
			return [
				Number(m[1]),
				Number(m[2]),
				Number(m[3]),
				m[4] === undefined ? 1 : Number(m[4]),
			]
		}
		const lum = ([r, g, b]: number[]): number => {
			const f = (v: number) => {
				const s = v / 255
				return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4)
			}
			return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b)
		}

		// Walk up until an opaque background is found, compositing alpha layers.
		let node: HTMLElement | null = backdrop ?? el
		let bg: [number, number, number] = [255, 255, 255]
		const stack: Array<[number, number, number, number]> = []
		while (node) {
			const c = parse(getComputedStyle(node).backgroundColor)
			if (c[3] > 0) stack.push(c)
			if (c[3] === 1) {
				bg = [c[0], c[1], c[2]]
				break
			}
			node = node.parentElement
		}
		for (let i = stack.length - 2; i >= 0; i--) {
			const [r, g, b, a] = stack[i]
			bg = [
				r * a + bg[0] * (1 - a),
				g * a + bg[1] * (1 - a),
				b * a + bg[2] * (1 - a),
			]
		}

		const fg = parse(getComputedStyle(el).color)
		const composedFg: [number, number, number] = [
			fg[0] * fg[3] + bg[0] * (1 - fg[3]),
			fg[1] * fg[3] + bg[1] * (1 - fg[3]),
			fg[2] * fg[3] + bg[2] * (1 - fg[3]),
		]

		const l1 = lum(composedFg)
		const l2 = lum(bg)
		const ratio = (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05)
		const hex = (c: number[]) =>
			'#' + c.map((v) => Math.round(v).toString(16).padStart(2, '0')).join('')
		return {
			ratio: Math.round(ratio * 100) / 100,
			fg: hex(composedFg),
			bg: hex(bg),
		}
	}, args)
}

/** The token sets driven here: the default, and the one whose name promises contrast. */
const TOKEN_SETS = ['rijkshuisstijl', 'hoog-contrast']

test.describe('rendered-surface contrast', () => {
	let baselineTokenSet = ''

	test.beforeAll(async ({ browser }) => {
		// `browser.newContext()` does NOT inherit the project's storageState —
		// without this the context is anonymous and the POST below is rejected
		// in a way that only surfaces as a later, unrelated failure.
		const ctx = await browser.newContext({
			storageState: 'tests/e2e/.auth/admin.json',
		})
		const page = await ctx.newPage()
		await page.goto(THEMING_URL)
		baselineTokenSet = await getTokenSet(page, await requestToken(page))
		await ctx.close()
	})

	test.afterAll(async ({ browser }) => {
		if (!baselineTokenSet) return
		const ctx = await browser.newContext({
			storageState: 'tests/e2e/.auth/admin.json',
		})
		const page = await ctx.newPage()
		await page.goto(THEMING_URL)
		await setTokenSet(page, await requestToken(page), baselineTokenSet)
		await ctx.close()
	})

	for (const tokenSet of TOKEN_SETS) {
		test(`no painted surface falls below WCAG AA on the ${tokenSet} token set`, async ({
			page,
		}) => {
			// Four page loads, each given time to mount, plus the token-set
			// switch: more than the default 30s on a slow runner.
			test.setTimeout(90_000)
			await page.goto(THEMING_URL)
			await setTokenSet(page, await requestToken(page), tokenSet)

			const failures: string[] = []
			const measured: string[] = []
			const undetermined: string[] = []
			const absent: string[] = []

			for (const surface of SURFACES) {
				await page.goto(surface.route)
				await page.waitForLoadState('domcontentloaded')
				await page.waitForTimeout(1500)

				// The first of the surface's markups that this version renders.
				let target = surface.targets[0]
				let result: ContrastResult = null
				for (const candidate of surface.targets) {
					target = candidate
					result = await contrastOf(page, candidate)
					if (result !== null) break
				}
				if (result === null) {
					// Absent is NOT a pass, but it is also not this app's
					// defect — which of these components renders depends on
					// the instance. CI's seed shows no info note card and
					// this list was written against a rig that had one, so
					// failing here failed on the FIXTURE. Recorded, and the
					// coverage floor below is what stops that being silent.
					absent.push(
						`${surface.route} ${surface.targets.map((t) => t.selector).join(' / ')} (${surface.why})`,
					)
					continue
				}
				if ('undetermined' in result) {
					undetermined.push(`${target.selector} (${result.undetermined})`)
					continue
				}

				measured.push(
					`${target.selector} ${result.ratio}:1 (${result.fg} on ${result.bg})`,
				)
				if (result.ratio < surface.min) {
					failures.push(
						`${surface.route} ${target.selector} — ${result.ratio}:1 `
							+ `(${result.fg} on ${result.bg}), needs ${surface.min}:1 — ${surface.why}`,
					)
				}
			}

			// A COVERAGE FLOOR, because absent and undetermined surfaces are
			// tolerated above and a run that skipped everything would
			// otherwise print green over nothing. Half the list, rounded up:
			// enough headroom for an instance that renders a different set of
			// note cards, not enough for the selectors going stale wholesale.
			const floor = Math.ceil(SURFACES.length / 2)
			expect(
				measured.length,
				`only ${measured.length} of ${SURFACES.length} surfaces were measurable on ${tokenSet}, `
					+ `below the floor of ${floor} — the selectors have probably gone stale.\n`
					+ `  absent: ${absent.join(', ') || 'none'}\n`
					+ `  undetermined: ${undetermined.join(', ') || 'none'}\n`
					+ `  measured: ${measured.join(' | ') || 'none'}`,
			).toBeGreaterThanOrEqual(floor)
			expect(
				failures,
				`nldesign paints text below WCAG AA on the ${tokenSet} token set:\n  ${failures.join('\n  ')}\n\n`
					+ `Measured: ${measured.join(' | ')}`,
			).toEqual([])
		})
	}

	test('the on-surface opt-out does not repaint links in body text', async ({
		page,
	}) => {
		// `--nldesign-color-on-surface` is read by two rules that want
		// DIFFERENT foregrounds: body text and link text. Resetting it to a
		// colour inside the dashboard's white widgets — rather than to
		// `initial`, which restores each rule's own fallback — silently
		// repaints every link there in body text. That regression was
		// introduced by the contrast fix itself and is INVISIBLE to every
		// assertion above, because body text on white passes AA comfortably.
		//
		// The token set is set explicitly, and to one using the nldesign
		// design system: the rules under test do not exist in the others, so
		// inheriting whatever set the previous test left behind makes this
		// pass alone and fail in suite order. It did exactly that.
		await page.goto(THEMING_URL)
		await setTokenSet(page, await requestToken(page), 'rijkshuisstijl')

		await page.goto('/apps/dashboard/')
		await page.waitForLoadState('domcontentloaded')
		await page.waitForTimeout(2000)

		// Two arms, because whether the dashboard shows a widget CONTAINING a
		// link depends on the instance's seed: the rig this was written on
		// had Recommended Files, CI does not. The rendered arm is the better
		// evidence, so it is preferred; the property arm is exact, always
		// available, and fails on the same regression — the reset resolves to
		// a colour instead of to nothing.
		const seen = await page.evaluate(() => {
			const root = getComputedStyle(document.documentElement)
			const hex = (v: string) => {
				const m = v.match(/rgba?\((\d+), (\d+), (\d+)/)
				return m
					? '#'
							+ [m[1], m[2], m[3]]
								.map((n) => Number(n).toString(16).padStart(2, '0'))
								.join('')
					: v
			}
			const panel = document.querySelector(
				'#app-dashboard [class*="panel"], #app-dashboard [class*="widget"]',
			)
			const link = panel ? panel.querySelector('a') : null
			return {
				panelFound: panel !== null,
				// `initial` computes to the empty string; a colour-named
				// reset computes to that colour. That is the whole test.
				onSurface: panel
					? getComputedStyle(panel)
							.getPropertyValue('--nldesign-color-on-surface')
							.trim()
					: null,
				linkColor: link ? hex(getComputedStyle(link).color) : null,
				linkToken: root
					.getPropertyValue('--nldesign-color-link')
					.trim()
					.toLowerCase(),
				textToken: root
					.getPropertyValue('--nldesign-color-text')
					.trim()
					.toLowerCase(),
			}
		})

		// No panel at all means the guard is inert — that is not a pass.
		expect(
			seen.panelFound,
			'no panel or widget inside #app-dashboard — this guard measured nothing',
		).toBe(true)

		expect(
			seen.onSurface,
			`the dashboard panel reset --nldesign-color-on-surface to "${seen.onSurface}" instead of `
				+ '`initial`. A colour there is read by BOTH the text rule and the link rule, so it repaints '
				+ `every link in the widget as body text (${seen.textToken}).`,
		).toBe('')

		if (seen.linkColor !== null) {
			expect(
				seen.linkColor,
				`a link inside a dashboard widget renders ${seen.linkColor}, not the link token `
					+ `${seen.linkToken}. If it equals the body-text token ${seen.textToken}, an on-surface `
					+ 'opt-out named a colour where it should have used `initial`.',
			).toBe(seen.linkToken)
		}
	})

	test('every status note card carries its body text at AA, whatever variants ship', async ({
		page,
	}) => {
		await page.goto(NOTECARD_ROUTE)
		await page.waitForLoadState('domcontentloaded')
		await page.waitForTimeout(1500)

		const variants = await page.evaluate(() => [
			...new Set(
				[...document.querySelectorAll('[class*="notecard--"]')].flatMap(
					(e) =>
						[...e.classList].filter((c) => c.startsWith('notecard--')),
				),
			),
		])

		expect(
			variants.length,
			`no note cards on ${NOTECARD_ROUTE} — the sweep measured nothing`,
		).toBeGreaterThan(0)

		const failures: string[] = []
		let measured = 0
		for (const variant of variants) {
			const result = await contrastOf(page, { selector: `.${variant}` })
			if (result === null || 'undetermined' in result) continue
			measured++
			if (result.ratio < AA_NORMAL) {
				failures.push(
					`.${variant} — ${result.ratio}:1 (${result.fg} on ${result.bg})`,
				)
			}
		}

		expect(
			measured,
			'note card variants were found but none was measurable',
		).toBeGreaterThan(0)

		expect(
			failures,
			`note card fills below WCAG AA:\n  ${failures.join('\n  ')}`,
		).toEqual([])
	})
})
