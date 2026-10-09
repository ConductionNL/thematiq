/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Does a chip's token actually reach its component?
 *
 * A component token is worth nothing unless something paints from it. There
 * are two ways it can:
 *
 *   1. `css/component-scopes.css` redirects the Nextcloud variable the
 *      component consumes, and a NEXTCLOUD rule paints from that variable; or
 *   2. a thematiq rule paints the property and reads a token that CHAINS to
 *      the component's own — directly, through `defaults.css`, or through an
 *      `aliases` entry in the mapping.
 *
 * It fails when a thematiq rule paints the property with `!important` while
 * reading some OTHER token. The rule wins, the chip's control does nothing,
 * and nothing says so: the row still renders, still saves, still shows a
 * colour. That is exactly how the login button shipped — its background and
 * label came from the PRIMARY button's tokens, so an admin could set a colour,
 * see it stored, and watch the button stay blue.
 *
 * This is the guard for that class of defect. It is deliberately stricter than
 * "the token exists": existence was never the problem.
 *
 * @spec openspec/specs/component-tokens/spec.md
 */

import { describe, it, expect } from 'vitest'
import * as fs from 'fs'
import * as path from 'path'

const ROOT = path.resolve(__dirname, '../..')

const mapping = JSON.parse(
	fs.readFileSync(
		path.join(ROOT, 'scripts/mapping/component-tokens.json'),
		'utf8',
	),
)

const SHEETS = [
	'css/systems/nldesign/theme.css',
	'css/systems/nldesign/element-overrides.css',
]

/**
 * Tokens that are allowed to paint over a component token, with the reason.
 *
 * `--nldesign-color-on-surface` is not a component token and is not meant to
 * be one. It is the WCAG pairing mechanism in element-overrides.css: a
 * container declares which foreground belongs on its surface and every
 * descendant inherits it, so text stays legible on a saturated fill. Making it
 * per-component would defeat the point — the pairing has to travel.
 */
const BY_DESIGN = new Set(['--nldesign-color-on-surface'])

/**
 * Properties a component has no row for, and the token that paints them.
 *
 * Each entry is a real gap: the component simply offers no control for that
 * property, so a rule painting it from elsewhere is not shadowing anything.
 * Adding a row is the fix; until then this list is the record of what an admin
 * cannot reach, and it must not grow silently.
 */
const NO_ROW_YET = new Set([
	// Empty since component-scoped-tokens 8.7 (decision 126): the navigation
	// background, the typed text of a text input and a textarea, the primary
	// button's border and the avatar initials each got a row.
])

/**
 * Rules whose selector names one component but whose subject is another,
 * nested inside it — a button under `#content`, an avatar under `#header`.
 * The marker match cannot tell those apart; the owning component covers them.
 */
const NESTED = new Set([
	'header-bar: background-color <- --nldesign-color-primary',
	'avatar: color <- --nldesign-component-header-color',
	'header-bar: color <- --nldesign-component-avatar-initials-color',
	'content-card: color <- --nldesign-component-button-primary-action-color',
	'link: color <- --nldesign-component-header-color',
	'link: color <- --nldesign-color-primary',
	// The header's app MENU entry (`.app-menu-main .app-menu-entry.active a` in
	// theme.css), not the app NAVIGATION entry this component is. The two share
	// only the `.active` state class, which is all the marker match can see.
	'navigation-active-entry: color <- --nldesign-color-primary',
])

/* ---------------------------------------------------- defaults.css chains */

const defaults = new Map()
{
	const css = fs
		.readFileSync(path.join(ROOT, 'css/systems/nldesign/defaults.css'), 'utf8')
		.replace(/\/\*[\s\S]*?\*\//g, '')
	for (const m of css.matchAll(/(--nldesign-[a-z0-9-]+)\s*:\s*([^;]+);/g)) {
		defaults.set(m[1], m[2].trim())
	}
}

/**
 * Every token `name` resolves through, itself included.
 *
 * @param {string} name A `--nldesign-*` token name.
 * @param {Set<string>} [seen] Accumulator for the recursion.
 *
 * @return {Set<string>} The chain.
 */
function chainOf(name, seen) {
	const out = seen || new Set()
	if (out.has(name)) {
		return out
	}
	out.add(name)
	const value = defaults.get(name)
	if (value === undefined) {
		return out
	}
	for (const m of value.matchAll(/var\(\s*(--nldesign-[a-z0-9-]+)/g)) {
		chainOf(m[1], out)
	}
	return out
}

/* ------------------------------------------------------- the shipped rules */

const rules = []
for (const file of SHEETS) {
	const css = fs
		.readFileSync(path.join(ROOT, file), 'utf8')
		.replace(/\/\*[\s\S]*?\*\//g, '')
	for (const m of css.matchAll(/([^{}]+)\{([^{}]*)\}/g)) {
		const selectorList = m[1].trim()
		if (selectorList.startsWith('@') || selectorList.includes(':root')) {
			continue
		}
		const decls = []
		for (const declaration of m[2].split(';')) {
			const at = declaration.indexOf(':')
			if (at < 0) {
				continue
			}
			const prop = declaration.slice(0, at).trim()
			if (prop.startsWith('--')) {
				continue
			}
			const tokens = [
				...declaration
					.slice(at + 1)
					.matchAll(/var\(\s*(--nldesign-[a-z0-9-]+)/g),
			].map((x) => x[1])
			if (tokens.length === 0) {
				continue
			}
			// The FIRST is the primary; the rest are var() fallbacks, which are
			// what a chain is supposed to end in and never shadow anything.
			decls.push({ prop, token: tokens[0] })
		}
		if (decls.length > 0) {
			rules.push({
				selectors: selectorList
					.split(',')
					.map((s) => s.trim().replace(/\s+/g, ' ')),
				decls,
			})
		}
	}
}

/* ------------------------------------------- attributing a rule to a chip */

/** Only the LAST compound of a selector identifies the component. */
function markersFor(component) {
	const marks = new Set()
	for (const selector of component.selectors) {
		const last = selector.trim().split(/\s+/).pop()
		const classes = last.match(/\.[\w-]+/g) || []
		const ids = last.match(/#[\w-]+/g) || []
		classes.forEach((c) => marks.add(c))
		ids.forEach((i) => marks.add(i))
		if (classes.length > 0 || ids.length > 0) {
			continue
		}
		// An element compound, attribute included: `input[type='submit']` must
		// stay whole, or it matches every typed field in the sheet.
		if (/^[a-z][\w-]*(\[[^\]]*\])?$/.test(last)) {
			marks.add(last)
		}
	}
	return [...marks]
}

function hasMarker(selector, marker) {
	const escaped = marker.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
	if (/^[.#]/.test(marker)) {
		// A class or id may sit anywhere in a compound: `.a.b` does carry `.a`.
		return new RegExp(escaped + '(?![\\w-])').test(selector)
	}
	// An element compound must be the whole compound, or `button` claims
	// `button.secondary`.
	return new RegExp('(?:^|[\\s>+~])' + escaped + '(?![\\w.#[-])').test(selector)
}

/** The properties a chip's rows are understood to control. */
function claimedProps(component) {
	const props = new Set()
	for (const name of Object.keys(component.tokens)) {
		if (/-background-color$/.test(name)) {
			props.add('background-color')
			props.add('background')
		}
		if (/(^|-)color$/.test(name)) {
			props.add('color')
		}
		if (/-border-color$/.test(name)) {
			props.add('border-color')
		}
		if (/-border-radius$/.test(name)) {
			props.add('border-radius')
		}
		if (/-font-family$/.test(name)) {
			props.add('font-family')
		}
		if (/-font-weight$/.test(name)) {
			props.add('font-weight')
		}
	}
	return props
}

/** States a chip has a row for; a `:disabled` rule is not its business. */
function statesOf(component) {
	const states = new Set([''])
	for (const name of Object.keys(component.tokens)) {
		if (/-hover(-|$)/.test(name)) {
			states.add('hover')
		}
		if (/-active-/.test(name)) {
			states.add('active')
		}
		if (/-focus-/.test(name)) {
			states.add('focus')
		}
		if (/-disabled-/.test(name)) {
			states.add('disabled')
		}
	}
	return states
}

function stateOf(selector) {
	const m = selector.match(
		/:(hover|active|focus-visible|focus|disabled|checked|visited|invalid)/,
	)
	if (m === null) {
		return ''
	}
	return m[1] === 'focus-visible' ? 'focus' : m[1]
}

/* ------------------------------------------------------------------ the test */

function findInert() {
	const found = []
	for (const [id, component] of Object.entries(mapping.components)) {
		const own = new Set(Object.keys(component.tokens))
		const aliased = new Set(Object.keys(component.aliases ?? {}))
		const marks = markersFor(component)
		const claims = claimedProps(component)
		const states = statesOf(component)
		if (marks.length === 0 || claims.size === 0) {
			continue
		}

		for (const rule of rules) {
			const matches = rule.selectors.some(
				(s) =>
					marks.some((mk) => hasMarker(s, mk)) && states.has(stateOf(s)),
			)
			if (matches === false) {
				continue
			}
			for (const decl of rule.decls) {
				if (claims.has(decl.prop) === false) {
					continue
				}
				if (aliased.has(decl.token) || BY_DESIGN.has(decl.token)) {
					continue
				}
				if ([...chainOf(decl.token)].some((t) => own.has(t))) {
					continue
				}
				const key = id + ': ' + decl.prop + ' <- ' + decl.token
				if (found.includes(key) === false) {
					found.push(key)
				}
			}
		}
	}
	return found
}

describe('component tokens: does the chip reach its component', () => {
	it('has no chip row a shipped rule paints over', () => {
		// Anything here is a control that renders, saves, and does nothing. If
		// this fails after adding a rule, chain that rule to the component's own
		// token — `var(--nldesign-component-x-y, var(--what-it-read-before))` —
		// or add an `aliases` entry in the mapping when the rule legitimately
		// reads another component's token.
		const unreachable = findInert().filter(
			(key) => NO_ROW_YET.has(key) === false && NESTED.has(key) === false,
		)

		expect(unreachable).toEqual([])
	})

	it('keeps the known-gap list honest', () => {
		// A gap that has been closed must leave this list, or the list stops
		// describing the code and starts excusing it.
		const current = findInert()
		const stale = [...NO_ROW_YET, ...NESTED].filter(
			(key) => current.includes(key) === false,
		)

		expect(stale).toEqual([])
	})
})

/*
 * The capture block's ELEMENT, which decides which palette the fallbacks see.
 *
 * `--thematiq-global-*` copies a Nextcloud global so a scope rule can fall back
 * to it without referring to itself. Which element that copy is taken on is the
 * whole behaviour: every design system declares the globals on `body`
 * (css/systems/nldesign/theme.css and the high-contrast, summer-breeze and
 * lasuite sheets), while Nextcloud core declares its own on `:root`, because
 * ThemeInjectionService serves the default theme with `plain=true`.
 *
 * Taken on `:root`, the copies held CORE's palette, so every component whose
 * token nobody had set — 91 of the 123 in the mapping — was painted Nextcloud
 * blue instead of the brand colour, and `primary-lock.css` drove every locked
 * component to core's primary rather than the set's.
 *
 * `body` sees both: what it declares itself, and what `:root` declares, by
 * inheritance. So this is not a style preference, and a regenerate that moves
 * it back to `:root` has to fail here.
 */
describe('component tokens: the capture block is taken on body', () => {
	const GENERATED = ['css/component-scopes.css', 'css/primary-lock.css']

	it.each(GENERATED)('%s declares --thematiq-global-* on body', (file) => {
		const css = fs.readFileSync(path.join(ROOT, file), 'utf8')

		// The selector list of the first block that mentions a capture name —
		// read off the raw text back to the end of the previous rule or
		// comment, so a list spanning several lines is read whole.
		const open = css.search(/\{[^}]*--thematiq-global-/)
		expect(open, 'no block declaring or reading a capture').toBeGreaterThan(-1)

		const head = css.slice(0, open)
		const start = Math.max(head.lastIndexOf('}') + 1, head.lastIndexOf('*/') + 2)
		const selectors = head
			.slice(start)
			.split(',')
			.map((s) => s.trim())

		expect(selectors[0]).toBe('body')
		// The token editor's preview is captured too: an unsaved edit is
		// declared there and nowhere else. Only the scopes read captures
		// inside it; the lock is about the live page.
		if (file === 'css/component-scopes.css') {
			expect(selectors).toEqual(['body', '#nldesign-preview'])
		} else {
			expect(selectors).toEqual(['body'])
		}
	})

	it('leaves no capture stranded on :root', () => {
		for (const file of GENERATED) {
			const css = fs.readFileSync(path.join(ROOT, file), 'utf8')
			const rootBlocks = [...css.matchAll(/(^|\n):root\s*\{([^}]*)\}/g)]
			const stranded = rootBlocks.filter((m) =>
				m[2].includes('--thematiq-global-'),
			)

			expect(stranded.map(() => file)).toEqual([])
		}
	})
})

/**
 * Core's guest layout wraps the login logo in its own `<div id="header">`, so
 * a bare `#header` in the header-bar scope painted the top bar's colour behind
 * the login logo — an area that belongs to `logo-slogan`, `login-card` and the
 * login background. The exclusion sits in `:where()` so the selector keeps the
 * specificity of a plain `#header`.
 */
describe('component tokens: header-bar leaves the login page alone', () => {
	const LOGGED_IN = ':where(body:not(#body-login)) #header'

	it('maps header-bar onto #header outside the login page only', () => {
		const selectors = mapping.components['header-bar'].selectors
		const headers = selectors.filter((s) => s.includes('#header'))

		expect(headers).toEqual([LOGGED_IN])
	})

	it('generates no bare #header rule', () => {
		const css = fs.readFileSync(
			path.join(ROOT, 'css/component-scopes.css'),
			'utf8',
		)
		// `#header` at the start of a selector, i.e. not under the exclusion.
		const bare = css.match(/(^|[,{}]|\*\/)\s*#header\b/g) || []

		expect(bare).toEqual([])
		expect(css).toContain(LOGGED_IN + ' {')
	})
})

/**
 * The navigation column's own background (component-scoped-tokens 8.7,
 * decision 126). Its Nextcloud variable is `--color-main-background`, which
 * nothing in this layer may redeclare: dark mode depends on it. So the row
 * re-scopes the blur variable the legacy column reads, and the nldesign rule
 * reads the token directly.
 */
describe('component tokens: the navigation background leaves the main background alone', () => {
	const NAV = '--nldesign-component-navigation-background-color'
	const scopes = fs.readFileSync(
		path.join(ROOT, 'css/component-scopes.css'),
		'utf8',
	)

	it('maps the row onto a variable other than --color-main-background', () => {
		const entry = mapping.components['app-navigation'].tokens[NAV]

		expect(entry).toBeDefined()
		expect(entry.global).not.toBe('--color-main-background')
		expect(entry.alsoGlobals).toBeUndefined()
	})

	it('redeclares no --color-main-background in the navigation scope', () => {
		const open = scopes.indexOf("[data-thematiq-component='app-navigation'] {")
		expect(open).toBeGreaterThan(-1)
		const body = scopes.slice(open, scopes.indexOf('}', open))

		expect(body).toContain(NAV)
		expect(body).not.toMatch(/--color-main-background\s*:/)
	})

	it("reads the row first in the nldesign column rule, the set's nav colour second", () => {
		const theme = fs
			.readFileSync(path.join(ROOT, 'css/systems/nldesign/theme.css'), 'utf8')
			.replace(/\/\*[\s\S]*?\*\//g, '')
		const rule = theme.match(/#app-navigation,\s*\.app-navigation\s*\{([^}]*)\}/)

		expect(rule).not.toBeNull()
		expect(rule[1].replace(/\s+/g, ' ')).toContain(
			'background-color: var( '
				+ NAV
				+ ', var(--nldesign-color-nav-background) ) !important',
		)
	})
})

/**
 * Live check 2026-10-09 (component-scoped-tokens 8.7): a stored navigation
 * background painted nothing, in the Files app or on the playground's column.
 * The test above read theme.css's rule, but element-overrides.css loads after
 * it and forces the same column to `--color-main-background` with
 * `!important`, and on Nextcloud's `#app-navigation-vue` its id selector
 * outranks theme.css anyway. Every rule that paints the column opaque must
 * read the row first, or the row is a control that does nothing.
 */
describe('component tokens: every rule that paints the navigation column reads its row', () => {
	const NAV = '--nldesign-component-navigation-background-color'
	const DIR = path.join(ROOT, 'css/systems/nldesign')

	const columnRules = fs
		.readdirSync(DIR)
		.filter((file) => file.endsWith('.css'))
		.flatMap((file) => {
			const css = fs
				.readFileSync(path.join(DIR, file), 'utf8')
				.replace(/\/\*[\s\S]*?\*\//g, '')
			return [...css.matchAll(/([^{}]+)\{([^}]*)\}/g)]
				.filter((m) =>
					m[1]
						.split(',')
						.map((s) => s.trim())
						.some((s) => ['#app-navigation', '.app-navigation', '#app-navigation-vue'].includes(s)),
				)
				.flatMap((m) =>
					[...m[2].matchAll(/(background(?:-color)?)\s*:\s*([^;]+!important)/g)].map(
						(d) => ({ file, property: d[1], value: d[2].replace(/\s+/g, ' ') }),
					),
				)
		})

	it('finds the column rules', () => {
		expect(columnRules.length).toBeGreaterThan(0)
	})

	it.each(columnRules.map((r) => [r.file + ' ' + r.property, r]))(
		'%s reads the row first',
		(_label, rule) => {
			expect(rule.value).toMatch(new RegExp('^var\\( ?' + NAV + ','))
		},
	)
})

/**
 * The same live check: the playground drew the avatar on a header ground but
 * outside `#header`, so the initials row moved nothing on its own stage.
 */
describe('component tokens: the avatar specimen reads the initials row', () => {
	it('colours the header avatar specimen from the row', () => {
		const css = fs
			.readFileSync(path.join(ROOT, 'css/playground.css'), 'utf8')
			.replace(/\/\*[\s\S]*?\*\//g, '')
		const rule = css.match(
			/#body-settings #nldesign-settings #nldesign-preview \.nldesign-pg-ground--header \.nldesign-pg-avatar\s*\{([^}]*)\}/,
		)

		expect(rule).not.toBeNull()
		const body = rule[1].replace(/\s+/g, ' ')
		expect(body).toContain('color: var( --nldesign-component-avatar-initials-color,')
		// element-overrides.css paints every span outside #header with two ids, six classes
		// and !important; anything less loses to it.
		expect(body).toContain('!important')
	})
})
