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
	// The navigation panel's own surface. Its Nextcloud variable is
	// `--color-main-background`, which overrides.css deliberately leaves alone
	// because overriding it breaks dark mode — so a row here needs a token the
	// nav rule reads directly, not a re-scope.
	'app-navigation: background-color <- --nldesign-color-nav-background',
	// Text colour inside a field. The chips offer border, placeholder, corner,
	// focus and invalid, but not the typed text itself.
	'text-input: color <- --nldesign-component-textbox-color',
	'textarea: color <- --nldesign-component-textbox-color',
	// The primary button's border. It tracks the background in every shipped
	// set, so it reads as one edge — but it is a separate token with no row.
	'primary-button: border-color <- --nldesign-component-button-primary-action-border-color',
])

/**
 * Rules whose selector names one component but whose subject is another,
 * nested inside it — a button under `#content`, an avatar under `#header`.
 * The marker match cannot tell those apart; the owning component covers them.
 */
const NESTED = new Set([
	'header-bar: background-color <- --nldesign-color-primary',
	'avatar: color <- --nldesign-component-header-color',
	'content-card: color <- --nldesign-component-button-primary-action-color',
	'link: color <- --nldesign-component-header-color',
	'link: color <- --nldesign-color-primary',
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
