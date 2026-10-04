/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The high-contrast system stays AAA in Nextcloud's dark themes (thematiq#1023).
 *
 * Resolves the real bundle the way the page loads it for `hoog-contrast`: the
 * design system's stylesheets in design-systems.json order, then
 * tokens/hoog-contrast.css, then the dark variant tokens/dark/hoog-contrast.css.
 * Only rules that reach `<body>` in a given environment count (a dark scope,
 * a prefers-contrast branch), and Nextcloud's own `--color-*` variables are
 * resolved through them, so what is measured is what core and every component
 * paint, not the token file alone.
 *
 * @spec openspec/specs/high-contrast-token-set/spec.md
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { describe, expect, it } from 'vitest'

const ROOT = path.resolve(__dirname, '../..')
const read = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8')

const SYSTEM = JSON.parse(read('design-systems.json')).find(
	(s) => s.id === 'high-contrast',
)
const DARK_VARIANT = 'css/tokens/dark/hoog-contrast.css'
// The dark layer joins the cascade only when its file exists, exactly as
// CssInjectionService::hasDarkVariantLayer() decides.
const BUNDLE = [
	...SYSTEM.stylesheets.map((s) => `css/${s}.css`),
	'css/tokens/hoog-contrast.css',
	...(fs.existsSync(path.join(ROOT, DARK_VARIANT)) ? [DARK_VARIANT] : []),
]

const AAA_TEXT = 7
const AAA_UI = 4.5

/**
 * The environments a page can be in. `themes` is body's `data-themes`
 * (null: the user chose no explicit theme, so the system preference decides).
 */
const DARK_ENVIRONMENTS = {
	'system dark preference': { scheme: 'dark', themes: null, more: false },
	'explicit dark theme': { scheme: 'light', themes: 'dark', more: false },
	'dark-highcontrast theme': {
		scheme: 'light',
		themes: 'dark-highcontrast',
		more: false,
	},
	'dark-highcontrast theme with prefers-contrast: more': {
		scheme: 'dark',
		themes: 'dark-highcontrast',
		more: true,
	},
	'system dark preference with prefers-contrast: more': {
		scheme: 'dark',
		themes: null,
		more: true,
	},
}
const LIGHT = { scheme: 'light', themes: 'light', more: false }

/**
 * Whether the @media rules around a node all hold in an environment.
 *
 * @param {import('postcss').Node} node The rule.
 * @param {object} env The environment.
 * @return {boolean} True when every enclosing condition matches.
 */
function conditionsHold(node, env) {
	for (let p = node.parent; p && p.type !== 'root'; p = p.parent) {
		if (p.type !== 'atrule' || p.name !== 'media') {
			return false
		}
		const q = p.params.replace(/\s+/g, ' ').trim()
		if (q === '(prefers-color-scheme: dark)' && env.scheme !== 'dark') {
			return false
		}
		if (q === '(prefers-contrast: more)' && !env.more) {
			return false
		}
		if (q === '(forced-colors: active)') {
			return false
		}
	}
	return true
}

/**
 * Whether one selector selects `<body>` (or the root above it) in an environment.
 *
 * @param {string} selector A single selector.
 * @param {object} env The environment.
 * @return {boolean} True when it matches.
 */
function reachesBody(selector, env) {
	const s = selector.replace(/\s+/g, '').replace(/'/g, '')
	if (s === ':root' || s === 'body' || s === 'body[data-themes]') {
		return true
	}
	if (s.startsWith('body:not([data-theme-light])')) {
		return env.themes === null
	}
	if (s === 'body[data-theme-dark]') {
		return env.themes === 'dark'
	}
	if (s === 'body[data-themes*=dark]') {
		return env.themes !== null && env.themes.includes('dark')
	}
	return false
}

/**
 * Walk the bundle and collect, for every declaration whose rule matches,
 * the winning value: `!important` beats normal, then source order.
 *
 * @param {object} env The environment.
 * @param {(selector: string) => boolean} matches Selects the rules to read.
 * @return {Record<string, string>} Property to value.
 */
function cascade(env, matches) {
	const won = {}
	for (const file of BUNDLE) {
		postcss.parse(read(file)).walkRules((rule) => {
			if (!conditionsHold(rule, env) || !rule.selectors.some(matches)) {
				return
			}
			rule.each((d) => {
				if (d.type !== 'decl') {
					return
				}
				const prior = won[d.prop]
				if (prior && prior.important && !d.important) {
					return
				}
				won[d.prop] = { value: d.value.trim(), important: d.important }
			})
		})
	}
	return Object.fromEntries(Object.entries(won).map(([k, v]) => [k, v.value]))
}

/**
 * Replace every var() with its value, fallbacks included.
 *
 * @param {string} value The declared value.
 * @param {Record<string, string>} vars The custom properties on body.
 * @return {string} The literal.
 */
function resolve(value, vars) {
	let v = value
	for (let i = 0; i < 20 && /var\(/.test(v); i++) {
		v = v.replace(
			/var\(\s*(--[\w-]+)\s*(?:,\s*([^()]*))?\)/g,
			(m, name, fallback) => {
				if (vars[name] === undefined && fallback === undefined) {
					throw new Error(`${name} is not declared`)
				}
				return vars[name] ?? fallback
			},
		)
	}
	return v.trim()
}

/**
 * Parse #rgb or #rrggbb.
 *
 * @param {string} value The colour.
 * @return {number[]} [r, g, b].
 */
function rgb(value) {
	let h = value.replace('#', '').trim()
	if (!/^[0-9a-f]{3}([0-9a-f]{3})?$/i.test(h)) {
		throw new Error(`not an opaque hex colour: "${value}"`)
	}
	if (h.length === 3) {
		h = [...h].map((c) => c + c).join('')
	}
	return [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16))
}

/**
 * WCAG contrast ratio of two hex colours.
 *
 * @param {string} a First colour.
 * @param {string} b Second colour.
 * @return {number} The ratio.
 */
function ratio(a, b) {
	const lum = (c) => {
		const [r, g, bl] = rgb(c).map((x) => {
			const s = x / 255
			return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
		})
		return 0.2126 * r + 0.7152 * g + 0.0722 * bl
	}
	const [hi, lo] = [lum(a), lum(b)].sort((x, y) => y - x)
	return (hi + 0.05) / (lo + 0.05)
}

/**
 * What a page looks like in an environment: the variables on body, and the
 * resolved paint of the elements the system styles directly.
 *
 * @param {object} env The environment.
 * @return {object} The resolved page.
 */
function page(env) {
	const vars = cascade(env, (s) => reachesBody(s, env))
	const element = (selector) => cascade(env, (s) => s.trim() === selector)
	const focus = element('*:focus-visible')
	const outline = resolve(focus.outline.replace(/!important/, ''), vars)
	const [, width, colour] = outline.match(/^(\d+)px\s+solid\s+(\S+)$/) ?? []
	return {
		v: (name) => resolve(vars[name], vars),
		header: resolve(element('#header').background, vars),
		headerText: resolve(element('#header *').color, vars),
		link: resolve(element('a').color, vars),
		placeholder: resolve(element('::placeholder').color, vars),
		focusColour: colour,
		focusWidth: Number(focus['outline-width']?.match(/\d+/)?.[0] ?? width),
	}
}

/**
 * The outline width a selector ends up with across the whole bundle.
 *
 * `cascade()` keeps `outline` and `outline-width` apart, but the shorthand
 * resets the width: a later `outline: 3px ...` beats an earlier
 * `outline-width: 4px`. This follows both, `!important` first, then order.
 *
 * @param {object} env The environment.
 * @param {string} selector One selector, as written in the stylesheets.
 * @return {number|null} The width in px, or null when nothing sets it.
 */
function outlineWidth(env, selector) {
	let won = null
	for (const file of BUNDLE) {
		postcss.parse(read(file)).walkRules((rule) => {
			if (!conditionsHold(rule, env) || !rule.selectors.includes(selector)) {
				return
			}
			rule.walkDecls(/^outline(-width)?$/, (d) => {
				const px = d.value.match(/(\d+)px/)
				if (px === null || (won && won.important && !d.important)) {
					return
				}
				won = { px: Number(px[1]), important: d.important }
			})
		})
	}
	return won === null ? null : won.px
}

/** Nextcloud's own variables: foreground, background, threshold. */
const PAIRS = [
	['--color-main-text', '--color-main-background', AAA_TEXT],
	['--color-text-maxcontrast', '--color-main-background', AAA_TEXT],
	['--color-text-light', '--color-main-background', AAA_TEXT],
	['--color-main-text', '--color-background-hover', AAA_TEXT],
	['--color-main-text', '--color-background-dark', AAA_TEXT],
	['--color-main-text', '--color-background-darker', AAA_TEXT],
	['--color-primary-element-text', '--color-primary-element', AAA_TEXT],
	['--color-primary-element-text', '--color-primary-element-hover', AAA_TEXT],
	[
		'--color-primary-element-light-text',
		'--color-primary-element-light',
		AAA_TEXT,
	],
	[
		'--color-primary-element-light-text',
		'--color-primary-element-light-hover',
		AAA_TEXT,
	],
	['--color-error', '--color-main-background', AAA_TEXT],
	['--color-warning', '--color-main-background', AAA_TEXT],
	['--color-success', '--color-main-background', AAA_TEXT],
	['--color-info', '--color-main-background', AAA_TEXT],
	['--color-border', '--color-main-background', AAA_UI],
	['--color-border-dark', '--color-main-background', AAA_UI],
	['--color-border-maxcontrast', '--color-main-background', AAA_UI],
	['--color-primary-element', '--color-main-background', AAA_UI],
]

/**
 * Every pair below its threshold, as readable lines.
 *
 * @param {object} p The resolved page.
 * @return {string[]} The failures.
 */
function failures(p) {
	const out = []
	const check = (name, fg, bg, min) => {
		const r = ratio(fg, bg)
		if (r < min) {
			out.push(`${name}: ${fg} on ${bg} = ${r.toFixed(2)}:1, needs ${min}:1`)
		}
	}
	for (const [fg, bg, min] of PAIRS) {
		check(`${fg} on ${bg}`, p.v(fg), p.v(bg), min)
	}
	const bg = p.v('--color-main-background')
	check('header text', p.headerText, p.header, AAA_TEXT)
	check('link', p.link, bg, AAA_TEXT)
	check('placeholder', p.placeholder, bg, AAA_TEXT)
	check('focus ring', p.focusColour, bg, AAA_UI)
	return out
}

describe('high-contrast dark variant (thematiq#1023)', () => {
	it('loads the real bundle, dark variant included', () => {
		expect(BUNDLE).toContain('css/systems/high-contrast/theme.css')
		expect(BUNDLE).toContain(DARK_VARIANT)
	})

	it('stays AAA in the light theme', () => {
		expect(failures(page(LIGHT))).toEqual([])
		expect(page(LIGHT).v('--color-main-background')).toBe('#ffffff')
		expect(page(LIGHT).v('--color-main-background-blur')).toBe('#ffffff')
	})

	for (const [name, env] of Object.entries(DARK_ENVIRONMENTS)) {
		describe(name, () => {
			it('paints a black page with near-white text', () => {
				const p = page(env)
				expect(p.v('--color-main-background')).toBe('#000000')
				expect(ratio(p.v('--color-main-text'), '#ffffff')).toBeLessThan(1.25)
				expect(p.header).toBe('#000000')
			})

			it('holds every pair to AAA: 7:1 text, 4.5:1 borders, fills and focus', () => {
				expect(failures(page(env))).toEqual([])
			})

			// Found in the live run: the app shell behind the navigation paints
			// --color-main-background-blur through a backdrop blur. Left unmapped
			// it keeps Nextcloud's translucent #171717 over the background image,
			// so the navigation sat on a tinted picture instead of black.
			it('paints the app shell black, with no translucent blur over the background image', () => {
				const p = page(env)
				expect(p.v('--color-main-background-blur')).toBe('#000000')
				expect(p.v('--filter-background-blur')).toBe('none')
			})

			it('keeps a thick focus ring that is not the border colour', () => {
				const p = page(env)
				expect(p.focusWidth).toBeGreaterThanOrEqual(3)
				// White borders are on every control in dark, so a white ring
				// would read as one more border.
				expect(p.focusColour).not.toBe(p.v('--color-border'))
			})
		})
	}

	// Found in the live run: element-overrides.css loads after theme.css and
	// re-declared `outline: 3px` for these selectors, so the 4px ring of the
	// prefers-contrast branch never reached the page.
	describe('prefers-contrast: more widens the focus ring to 4px', () => {
		const FOCUS_SELECTORS = [
			'*:focus-visible',
			'a:focus',
			'button:focus',
			'input:focus',
		]
		const MORE = {
			'light theme': { ...LIGHT, more: true },
			'system dark preference':
				DARK_ENVIRONMENTS[
					'system dark preference with prefers-contrast: more'
				],
			'dark-highcontrast theme':
				DARK_ENVIRONMENTS[
					'dark-highcontrast theme with prefers-contrast: more'
				],
		}
		for (const [name, env] of Object.entries(MORE)) {
			it(`in the ${name}`, () => {
				for (const selector of FOCUS_SELECTORS) {
					expect(outlineWidth(env, selector), selector).toBe(4)
				}
			})
		}
		it('and stays 3px without it', () => {
			for (const selector of FOCUS_SELECTORS) {
				expect(outlineWidth(LIGHT, selector), selector).toBe(3)
				expect(
					outlineWidth(
						DARK_ENVIRONMENTS['system dark preference'],
						selector,
					),
					selector,
				).toBe(3)
			}
		})
	})

	it('leaves an explicit light-highcontrast choice light under a dark system preference', () => {
		const p = page({ scheme: 'dark', themes: 'light-highcontrast', more: false })
		expect(p.v('--color-main-background')).toBe('#ffffff')
		expect(failures(p)).toEqual([])
	})
})
