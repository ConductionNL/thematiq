/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * La Frankendesk, resolved the way a page loads it (thematiq#1021).
 *
 * `frankendesk` runs on the `lasuite` design system. A page with it active
 * loads the lasuite bundle in the order design-systems.json declares
 * (fonts, defaults, brand-override, bridge, element-overrides), then
 * css/tokens/frankendesk.css, then LogoLayerService's inline logo layer, then
 * css/token-overrides/frankendesk.css, then the generated
 * css/tokens/dark/frankendesk.css. These tests read those real files in that
 * order and resolve every value through the cascade:
 *
 *   - the set keeps every token of its parent (`extends` in token-sets.json)
 *     and agrees with the colours the bundle's own bridge derives from the
 *     Cunningham ramp, with one documented departure (the font stack);
 *   - its logo reaches the header: the file exists, the token file points at
 *     it, and the header rule resolves to it with no mask over it;
 *   - every foreground the set names reaches WCAG AA on the surface it sits on,
 *     in light mode and in both dark scopes (the OS-preference media block and
 *     the explicit `data-themes` block).
 *
 * The dark contrast tests were red on development: the footer had inverted to
 * #cbcbd6 under a #9e9e9e label (1.67:1), the hero title sat on its fill at
 * 1.01:1 and the links on the page at 3.00:1. The rest is a new guard.
 *
 * @spec openspec/specs/frankendesk-token-set/spec.md
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { describe, expect, it } from 'vitest'

const ROOT = path.resolve(__dirname, '../..')
const read = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8')

const SET_ID = 'frankendesk'
const MANIFEST = JSON.parse(read('token-sets.json'))
const ENTRY = MANIFEST.find((e) => e.id === SET_ID)
const PARENT = MANIFEST.find((e) => e.id === ENTRY.extends)
const BUNDLE = JSON.parse(read('design-systems.json')).find(
	(d) => d.id === ENTRY.design_system,
)

/** The one token the set changes on purpose, and why (see the spec). */
const DEPARTURES = {
	'--nldesign-font-family':
		'Inter first and no Marianne: we ship no Marianne @font-face',
}

/**
 * Custom properties declared on a top-level `:root` rule; a later one wins.
 *
 * @param {string} css The stylesheet.
 * @return {Record<string, string>} Property to raw value.
 */
function rootTokens(css) {
	const tokens = {}
	postcss.parse(css).walkRules((rule) => {
		if (rule.parent.type === 'root' && rule.selector.trim() === ':root') {
			rule.walkDecls(/^--/, (d) => {
				tokens[d.prop] = d.value.trim()
			})
		}
	})
	return tokens
}

/**
 * The two scopes of a generated dark file.
 *
 * @param {string} css The generated dark stylesheet.
 * @return {{media: Record<string, string>, explicit: Record<string, string>}} Each scope's declarations.
 */
function darkScopes(css) {
	const media = {}
	const explicit = {}
	postcss.parse(css).walkRules((rule) => {
		const inMedia =
			rule.parent.type === 'atrule'
			&& /prefers-color-scheme:\s*dark/.test(rule.parent.params)
		const isExplicit =
			rule.parent.type === 'root'
			&& rule.selector.includes('data-themes*=dark')
		if (!inMedia && !isExplicit) {
			return
		}
		rule.walkDecls(/^--/, (d) => {
			;(inMedia ? media : explicit)[d.prop] = d.value.trim()
		})
	})
	return { media, explicit }
}

/**
 * The declarations of the first rule in a stylesheet whose selector list
 * contains `selector`, comments removed and `!important` dropped.
 *
 * @param {string} css The stylesheet.
 * @param {string} selector One selector of the list.
 * @return {Record<string, string>} Property to value.
 */
function ruleFor(css, selector) {
	let found = null
	postcss.parse(css).walkRules((rule) => {
		if (
			found === null
			&& rule.selectors.map((s) => s.trim()).includes(selector)
		) {
			found = {}
			rule.walkDecls((d) => {
				found[d.prop] = d.value.trim()
			})
		}
	})
	if (found === null) {
		throw new Error(`no ${selector} rule`)
	}
	return found
}

/**
 * Resolve a value's var() chain against a token map, as the cascade does. An
 * unknown variable takes its fallback; without one the test fails loudly.
 *
 * @param {string} value The declared value.
 * @param {Record<string, string>} tokens The token map.
 * @param {number} depth Recursion guard.
 * @return {string} The resolved value, whitespace collapsed.
 */
function resolve(value, tokens, depth = 0) {
	if (depth > 30) {
		throw new Error('var() chain too deep: ' + value)
	}
	const at = value.indexOf('var(')
	if (at === -1) {
		return value.replace(/\s+/g, ' ').trim()
	}
	let level = 1
	let k = at + 4
	let comma = -1
	while (k < value.length && level > 0) {
		if (value[k] === '(') {
			level++
		} else if (value[k] === ')') {
			level--
		} else if (value[k] === ',' && level === 1 && comma === -1) {
			comma = k
		}
		k++
	}
	const name = value.slice(at + 4, comma === -1 ? k - 1 : comma).trim()
	let replacement
	if (tokens[name] !== undefined) {
		replacement = resolve(tokens[name], tokens, depth + 1)
	} else if (comma !== -1) {
		replacement = resolve(value.slice(comma + 1, k - 1), tokens, depth + 1)
	} else {
		throw new Error('unresolved token ' + name)
	}
	return resolve(
		value.slice(0, at) + replacement + value.slice(k),
		tokens,
		depth + 1,
	)
}

/**
 * Parse a hex (3, 6 or 8 digits), rgb() or rgba() colour.
 *
 * @param {string} value The colour.
 * @return {number[]} [r, g, b, a].
 */
function rgba(value) {
	const v = value.trim()
	const hex = v.match(/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i)
	if (hex) {
		let h = hex[1]
		if (h.length === 3) {
			h = [...h].map((c) => c + c).join('')
		}
		const n = (i) => parseInt(h.slice(i, i + 2), 16)
		return [n(0), n(2), n(4), h.length === 8 ? n(6) / 255 : 1]
	}
	const fn = v.match(/^rgba?\(([^)]*)\)$/i)
	if (fn) {
		const p = fn[1]
			.split(/[\s,/]+/)
			.filter(Boolean)
			.map(Number)
		return [p[0], p[1], p[2], p[3] ?? 1]
	}
	throw new Error(`not a colour: "${value}"`)
}

/**
 * A colour painted over an opaque ground.
 *
 * @param {string} top The (possibly translucent) colour.
 * @param {string} ground The opaque colour under it.
 * @return {number[]} The painted [r, g, b].
 */
function over(top, ground) {
	const [r, g, b, a] = rgba(top)
	const under = rgba(ground)
	return [r, g, b].map((c, i) => c * a + under[i] * (1 - a))
}

/**
 * WCAG relative luminance.
 *
 * @param {number[]} rgb The colour.
 * @return {number} The luminance.
 */
function luminance(rgb) {
	const [r, g, b] = rgb.slice(0, 3).map((c) => {
		const s = c / 255
		return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
	})
	return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

/**
 * WCAG contrast of a foreground painted on a background.
 *
 * @param {string} fg The foreground (may be translucent).
 * @param {string} bg The background.
 * @return {number} The ratio.
 */
function contrast(fg, bg) {
	const [hi, lo] = [luminance(over(fg, bg)), luminance(rgba(bg))].sort(
		(x, y) => y - x,
	)
	return (hi + 0.05) / (lo + 0.05)
}

const SET_CSS = read(`css/tokens/${SET_ID}.css`)
const SET = rootTokens(SET_CSS)
const PARENT_TOKENS = rootTokens(read(`css/tokens/${PARENT.id}.css`))

/** The bundle's own :root layers, in the declared load order. */
const BUNDLE_TOKENS = Object.assign(
	{},
	...BUNDLE.stylesheets.map((s) => rootTokens(read(`css/${s}.css`))),
)

/**
 * LogoLayerService's inline layer for a set that ships `img/logos/<id>.svg`:
 * the absolute logo url plus the three header-logo variables, read from the
 * service's own source so a change there reaches this test.
 */
const LOGO_SERVICE = read('lib/Service/LogoLayerService.php')
const HEADER_LOGO_VARIABLES = (() => {
	const m = LOGO_SERVICE.match(
		/HEADER_LOGO_VARIABLES\s*=\s*((?:'[^']*'\s*\.?\s*)+);/,
	)
	if (!m) {
		throw new Error('HEADER_LOGO_VARIABLES not found in LogoLayerService')
	}
	const css = [...m[1].matchAll(/'([^']*)'/g)].map((x) => x[1]).join('')
	const vars = {}
	for (const decl of css.split(';')) {
		const [name, ...rest] = decl.split(':')
		if (name.trim().startsWith('--')) {
			vars[name.trim()] = rest.join(':').trim()
		}
	}
	return vars
})()
const LOGO_URL = `url(/custom_apps/thematiq/img/logos/${SET_ID}.svg)`
const LOGO_LAYER = { '--nldesign-logo-url': LOGO_URL, ...HEADER_LOGO_VARIABLES }

/** What a page with the set active resolves on `:root` in light mode. */
const LIGHT = {
	// The page itself: the manifest's background, which is also what the dark
	// generator falls back to when a set declares none.
	'--nldesign-color-background': ENTRY.theming.background_color,
	...BUNDLE_TOKENS,
	...SET,
	...LOGO_LAYER,
}
const DARK_FILE = darkScopes(read(`css/tokens/dark/${SET_ID}.css`))
const SCOPES = [
	['light', LIGHT],
	['dark (OS preference)', { ...LIGHT, ...DARK_FILE.media }],
	['dark (explicit choice)', { ...LIGHT, ...DARK_FILE.explicit }],
]

/**
 * Every foreground the set names, on the surface it sits on. All are text, so
 * all take the 4.5:1 of normal-size text; the footer's 13px headings and legal
 * line leave no room for the large-text 3:1.
 */
const TEXT_PAIRS = [
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

describe('La Frankendesk is the lasuite set plus a delta', () => {
	it('names lasuite as its parent and runs on the same design system', () => {
		expect(ENTRY.extends).toBe('lasuite')
		expect(ENTRY.design_system).toBe('lasuite')
		expect(PARENT.design_system).toBe(ENTRY.design_system)
		expect(BUNDLE, 'design-systems.json ships lasuite').toBeTruthy()
	})

	it('carries every token of its parent with the same value', () => {
		// The renderer loads ONE token file per set, so `extends` does not pull
		// lasuite.css in first. A parent token missing here is a token the set
		// silently lacks; a changed one is a drift nobody chose.
		const drift = Object.entries(PARENT_TOKENS)
			.filter(([name]) => DEPARTURES[name] === undefined)
			.filter(
				([name, value]) => SET[name]?.toLowerCase() !== value.toLowerCase(),
			)
			.map(([name, value]) => `${name}: parent ${value}, set ${SET[name]}`)
		expect(drift).toEqual([])
	})

	it('departs from its parent only where the spec says so', () => {
		for (const name of Object.keys(DEPARTURES)) {
			expect(PARENT_TOKENS[name], `${name} is a parent token`).toBeDefined()
			expect(SET[name]).not.toBe(PARENT_TOKENS[name])
		}
	})

	it('agrees with the bundle bridge on brand, text, surface and border colours', () => {
		// bridge.css maps --nldesign-* onto the Cunningham ramp for the plain
		// lasuite pick; the set restates them as literals, which must be the
		// same colours. Status colours are left out on purpose: they follow the
		// parent's literals (#1006), which no longer match the ramp's status
		// steps. That drift belongs to lasuite and is recorded in the spec.
		const disagree = Object.keys(PARENT_TOKENS)
			.filter((name) =>
				/^--nldesign-color-(primary|text|background|header|nav|border|link)/.test(
					name,
				),
			)
			.filter((name) => BUNDLE_TOKENS[name] !== undefined)
			.map((name) => [name, resolve(BUNDLE_TOKENS[name], BUNDLE_TOKENS)])
			.filter(
				([name, bridge]) => bridge.toLowerCase() !== SET[name].toLowerCase(),
			)
			.map(([name, bridge]) => `${name}: bridge ${bridge}, set ${SET[name]}`)
		expect(disagree).toEqual([])
	})

	it('uses La Suite violet brand-650 as its primary, as the manifest says', () => {
		const brand650 = resolve('var(--lasuite-color-brand-650)', BUNDLE_TOKENS)
		expect(SET['--nldesign-color-primary'].toLowerCase()).toBe(
			brand650.toLowerCase(),
		)
		expect(ENTRY.theming.primary_color.toLowerCase()).toBe(
			brand650.toLowerCase(),
		)
		expect(SET['--nldesign-color-link']).toBe(SET['--nldesign-color-primary'])
	})

	it('sets body copy in self-hosted Inter and never names Marianne', () => {
		for (const name of [
			'--nldesign-font-family',
			'--nldesign-body-font-family',
		]) {
			expect(SET[name].split(',')[0].trim()).toBe('Inter')
			expect(SET[name]).not.toMatch(/marianne/i)
		}
	})

	it('paints a white header and a dark footer in light mode', () => {
		expect(
			luminance(rgba(SET['--nldesign-color-header-background'])),
		).toBeGreaterThan(0.9)
		expect(
			luminance(rgba(SET['--nldesign-color-footer-background'])),
		).toBeLessThan(0.05)
	})
})

describe('the La Frankendesk logo reaches the header', () => {
	it('ships the file the manifest names, listed in img/ICONS.md', () => {
		expect(ENTRY.theming.logo).toBe(`img/logos/${SET_ID}.svg`)
		const svg = read(ENTRY.theming.logo)
		expect(svg).toMatch(/^<svg[\s>]/)
		expect(read('img/ICONS.md')).toMatch(new RegExp(`^- ${SET_ID}$`, 'm'))
	})

	it('points --nldesign-logo-url at that file, relative to css/tokens/', () => {
		const m = SET['--nldesign-logo-url'].match(/^url\(['"]?([^'")]+)['"]?\)$/)
		expect(m).toBeTruthy()
		const target = path.normalize(path.join('css/tokens', m[1]))
		expect(target).toBe(path.normalize(ENTRY.theming.logo))
	})

	it('is the file LogoLayerService looks for first', () => {
		expect(LOGO_SERVICE).toContain("'img/logos/' . $tokenSet . '.' . $extension")
		expect(LOGO_SERVICE).toMatch(/\['svg',/)
	})

	it.each([
		[
			'the shared lasuite element overrides',
			'css/systems/lasuite/element-overrides.css',
		],
		[
			'its own token override, which loads after them',
			`css/token-overrides/${SET_ID}.css`,
		],
	])('shows the logo unmasked through %s', (_label, file) => {
		const rule = ruleFor(read(file), '#header .logo')
		const strip = (v) => v.replace(/\s*!important\s*$/, '')
		expect(resolve(strip(rule['background-image']), LIGHT)).toBe(LOGO_URL)
		expect(resolve(strip(rule.mask), LIGHT)).toBe('none')
		expect(resolve(strip(rule['-webkit-mask']), LIGHT)).toBe('none')
		expect(resolve(strip(rule['background-color']), LIGHT)).toBe('transparent')
	})

	it('keeps the same logo in dark mode (no logo_dark)', () => {
		expect(ENTRY.theming.logo_dark).toBeUndefined()
		expect(DARK_FILE.media['--nldesign-logo-url']).toBeUndefined()
		expect(DARK_FILE.explicit['--nldesign-logo-url']).toBeUndefined()
	})
})

describe('La Frankendesk reaches WCAG AA', () => {
	it('declares the same dark values in both dark scopes', () => {
		expect(DARK_FILE.media).toEqual(DARK_FILE.explicit)
		expect(Object.keys(DARK_FILE.media).length).toBeGreaterThan(0)
	})

	for (const [scope, tokens] of SCOPES) {
		it.each(TEXT_PAIRS)(`${scope}: %s on %s clears 4.5:1`, (fg, bg) => {
			const f = resolve(`var(${fg})`, tokens)
			const b = resolve(`var(${bg})`, tokens)
			const ratio = contrast(f, b)
			expect(
				ratio,
				`${f} on ${b} = ${ratio.toFixed(2)}:1`,
			).toBeGreaterThanOrEqual(4.5)
		})
	}

	it('keeps the footer a dark band in dark mode instead of inverting it', () => {
		for (const [, tokens] of SCOPES.slice(1)) {
			const footer = resolve('var(--nldesign-color-footer-background)', tokens)
			expect(luminance(rgba(footer))).toBeLessThan(0.05)
		}
	})
})
