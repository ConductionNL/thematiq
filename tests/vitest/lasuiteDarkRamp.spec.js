/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The La Suite system paints the shell from its own token ramp with
 * `!important`, so the shared `--nldesign-*` dark layer cannot reach it. These
 * tests resolve the shipped stylesheets the way the browser does for the
 * lasuite load order (defaults, brand-override, element-overrides) and check
 * what the shell resolves to in dark mode, plus the contrast of its text.
 *
 * @spec openspec/specs/dark-mode/spec.md
 */

import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'

const ROOT = path.resolve(__dirname, '../..')
const {
	checkDarkRamp,
	parseRules,
	parseDeclarations,
	primaryVars,
} = require('../css/check-lasuite-dark-ramp.js')

const read = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8')
const strip = (css) => css.replace(/\/\*[\s\S]*?\*\//g, '')

const OVERRIDES = read('css/systems/lasuite/element-overrides.css')

/** Custom properties declared on `:root` in a stylesheet, in source order. */
function rootTokens(css) {
	const tokens = {}
	for (const rule of parseRules(strip(css))) {
		if (rule.context === '' && rule.selector === ':root') {
			for (const d of parseDeclarations(rule.body)) {
				tokens[d.property] = d.value
			}
		}
	}
	return tokens
}

/** Custom properties the explicit dark block of element-overrides declares. */
function darkTokens() {
	const tokens = {}
	for (const rule of parseRules(strip(OVERRIDES))) {
		if (rule.context === '' && rule.selector.includes("data-themes*='dark'")) {
			for (const d of parseDeclarations(rule.body)) {
				if (d.property.startsWith('--')) {
					tokens[d.property] = d.value
				}
			}
		}
	}
	return tokens
}

const LIGHT = {
	...rootTokens(read('css/systems/lasuite/defaults.css')),
	...rootTokens(read('css/systems/lasuite/brand-override.css')),
}
const DARK = { ...LIGHT, ...darkTokens() }

/** Resolve a value's var() chain against a token map, the way the cascade does. */
function resolve(value, tokens, depth = 0) {
	if (depth > 20) {
		throw new Error('var() chain too deep: ' + value)
	}
	const at = value.indexOf('var(')
	if (at === -1) {
		return value.trim()
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
	const fallback = comma === -1 ? undefined : value.slice(comma + 1, k - 1)
	let replacement
	if (tokens[name] !== undefined) {
		replacement = resolve(tokens[name], tokens, depth + 1)
	} else if (fallback !== undefined) {
		replacement = resolve(fallback, tokens, depth + 1)
	} else {
		throw new Error('unresolved token ' + name)
	}
	return resolve(
		value.slice(0, at) + replacement + value.slice(k),
		tokens,
		depth + 1,
	)
}

/** The value a shell rule (outside the dark blocks) declares for a property. */
function shellValue(selectorStart, property) {
	for (const rule of parseRules(strip(OVERRIDES))) {
		if (rule.context === '' && rule.selector.startsWith(selectorStart)) {
			const decl = parseDeclarations(rule.body).find(
				(d) => d.property === property,
			)
			if (decl) {
				return decl.value
			}
		}
	}
	throw new Error(`no ${property} on ${selectorStart}`)
}

function rgb(color) {
	const hex = color.match(/^#([0-9a-f]{6})$/i)
	if (hex) {
		const n = parseInt(hex[1], 16)
		return [(n >> 16) & 255, (n >> 8) & 255, n & 255, 1]
	}
	const fn = color.match(
		/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([\d.]+)\s*)?\)$/,
	)
	if (fn) {
		return [
			Number(fn[1]),
			Number(fn[2]),
			Number(fn[3]),
			fn[4] === undefined ? 1 : Number(fn[4]),
		]
	}
	throw new Error('not a colour: ' + color)
}

/** A translucent colour painted over an opaque ground. */
function over(top, ground) {
	const [r, g, b, a] = rgb(top)
	const [R, G, B] = rgb(ground)
	const mix = (x, y) => Math.round(x * a + y * (1 - a))
	return (
		'#'
		+ [mix(r, R), mix(g, G), mix(b, B)]
			.map((v) => v.toString(16).padStart(2, '0'))
			.join('')
	)
}

function luminance(color) {
	const [r, g, b] = rgb(color).map((v, i) => {
		if (i === 3) {
			return v
		}
		const c = v / 255
		return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4
	})
	return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

function contrast(a, b) {
	const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x)
	return (hi + 0.05) / (lo + 0.05)
}

const SHELL = [
	// The shorthand: #868 dropped the dead background-color longhand before it.
	['#header', 'background'],
	['#content-vue', 'background-color'],
	['#app-content', 'background'],
	['#app-navigation', 'background'],
	['#header .unified-search-input', 'background-color'],
]

describe('the shipped La Suite overrides', () => {
	it('read no raw gray step and no token the dark blocks leave unmapped', () => {
		expect(checkDarkRamp(OVERRIDES).findings).toEqual([])
	})

	it('write none of the variables Nextcloud derives dark mode from', () => {
		const reserved = checkDarkRamp(OVERRIDES).findings.filter((f) =>
			f.includes('REQ-CSS-007'),
		)
		expect(reserved).toEqual([])
	})

	it('remap the same tokens in the media block and the explicit dark block', () => {
		const { media, explicit } = checkDarkRamp(OVERRIDES).remapped
		expect([...media].sort()).toEqual([...explicit].sort())
	})

	it.each(SHELL)(
		'resolve %s to a dark surface in dark mode and a light one in light mode',
		(selector, property) => {
			const value = shellValue(selector, property)
			const dark = resolve(value, DARK)
			const light = resolve(value, LIGHT)
			expect(luminance(dark)).toBeLessThan(0.05)
			expect(luminance(light)).toBeGreaterThan(0.8)
		},
	)

	it('keep the card lighter than the canvas it sits on, as upstream does', () => {
		const card = resolve(shellValue('#app-content', 'background'), DARK)
		const canvas = resolve(shellValue('#content-vue', 'background-color'), DARK)
		expect(luminance(card)).toBeGreaterThan(luminance(canvas))
	})

	it('give headings the dark-mode text colour instead of near-black', () => {
		const heading = resolve(shellValue('h1', 'color'), DARK)
		const card = resolve(shellValue('#app-content', 'background'), DARK)
		expect(contrast(heading, card)).toBeGreaterThanOrEqual(4.5)
	})

	it('keep the selected row visible on the dark navigation', () => {
		const nav = resolve(shellValue('#app-navigation', 'background'), DARK)
		const wash = resolve(
			shellValue(
				'#app-navigation-vue .app-navigation-entry.active',
				'background-color',
			),
			DARK,
		)
		const row = over(wash, nav)
		expect(row).not.toBe(nav)
		expect(contrast(row, nav)).toBeGreaterThan(1.1)
	})

	it.each([
		[
			'--lasuite-content-primary',
			'--lasuite--contextuals--background--surface--primary',
		],
		[
			'--lasuite-content-primary',
			'--lasuite--contextuals--background--surface--tertiary',
		],
		[
			'--lasuite-content-muted',
			'--lasuite--contextuals--background--surface--primary',
		],
		[
			'--lasuite-content-brand',
			'--lasuite--contextuals--background--surface--primary',
		],
		[
			'--lasuite-content-brand',
			'--lasuite--contextuals--background--semantic--neutral--tertiary',
		],
		[
			'--lasuite--contextuals--content--semantic--brand--secondary',
			'--lasuite--contextuals--background--semantic--brand--secondary',
		],
	])('reach WCAG AA (4.5:1) in dark mode for %s on %s', (text, ground) => {
		const ratio = contrast(
			resolve(`var(${text})`, DARK),
			resolve(`var(${ground})`, DARK),
		)
		expect(ratio).toBeGreaterThanOrEqual(4.5)
	})
})

describe('the La Suite primary button in dark mode (#935)', () => {
	// Cunningham's own dark theme keeps the brand fill (brand-550 at rest,
	// brand-650 on hover, `.cunningham-theme--dark` in defaults.css) and the
	// brand-050 on-brand label, so the label has to clear 4.5:1 on both fills,
	// on the cunningham ramp (defaults.css alone) and the lasuite one.
	const CUNNINGHAM_LIGHT = rootTokens(read('css/systems/lasuite/defaults.css'))
	const RAMPS = [
		['cunningham', { ...CUNNINGHAM_LIGHT, ...darkTokens() }],
		['lasuite', DARK],
	]
	const FILL = '.button-vue--vue-primary:not(:where('
	const HOVER = '.button-vue--vue-primary:hover'
	const LABEL =
		'.button-vue--vue-primary:not([data-admin-theming-setting-color-picker])'

	it.each(RAMPS)(
		'keeps the label readable on the fill at rest (%s)',
		(name, tokens) => {
			const label = resolve(shellValue(LABEL, 'color'), tokens)
			const fill = resolve(shellValue(FILL, 'background-color'), tokens)
			expect(
				contrast(label, fill),
				`${label} on ${fill}`,
			).toBeGreaterThanOrEqual(4.5)
		},
	)

	it.each(RAMPS)(
		'keeps the label readable on the hover fill (%s)',
		(name, tokens) => {
			const label = resolve(shellValue(LABEL, 'color'), tokens)
			const fill = resolve(shellValue(HOVER, 'background-color'), tokens)
			expect(
				contrast(label, fill),
				`${label} on ${fill}`,
			).toBeGreaterThanOrEqual(4.5)
		},
	)

	it.each(RAMPS)(
		'fills with the Cunningham dark brand steps (%s)',
		(name, tokens) => {
			expect(resolve(shellValue(FILL, 'background-color'), tokens)).toBe(
				resolve('var(--lasuite-color-brand-550)', tokens),
			)
			expect(resolve(shellValue(HOVER, 'background-color'), tokens)).toBe(
				resolve('var(--lasuite-color-brand-650)', tokens),
			)
		},
	)
})

describe('the dark-ramp check', () => {
	it('fails when a dark variant redefines only the shared layer', () => {
		const css = `
			body[data-themes*='dark'] { --nldesign-color-header-background: #171717; }
			#header { background: var(--lasuite-color-gray-000) !important; }
		`
		const { findings } = checkDarkRamp(css)
		expect(findings).toHaveLength(1)
		expect(findings[0]).toContain('--lasuite-color-gray-000')
	})

	it('fails when only one of the two dark scopes remaps a token a rule reads', () => {
		const css = `
			body[data-themes*='dark'] { --lasuite--contextuals--background--surface--primary: #2f3033; }
			#header { background: var(--lasuite--contextuals--background--surface--primary, var(--lasuite-color-gray-000)); }
		`
		const { findings } = checkDarkRamp(css)
		expect(findings).toHaveLength(1)
		expect(findings[0]).toContain('prefers-color-scheme')
	})

	it('fails on a write to a reserved Nextcloud variable', () => {
		const { findings } = checkDarkRamp('body { --color-main-background: #000; }')
		expect(findings[0]).toContain('REQ-CSS-007')
	})

	it('reads a fallback as a fallback, not as what renders', () => {
		expect(primaryVars('var(--a, var(--b)) var(--c)')).toEqual(['--a', '--c'])
	})
})

/*
 * The Nextcloud background states in dark mode (thematiq#1024 live run).
 *
 * bridge.css maps --color-background-hover, -dark and -darker and
 * --color-border to light gray steps, unconditionally, and its dark blocks
 * flipped only the text variables. In dark mode an NcActions menu item then
 * hovered to #f0f0f3 under #f0f0f3 text: the label vanished, on La Suite and
 * on Cunningham alike. The dark blocks have to remap the grounds as well,
 * with the values of Cunningham's own dark block.
 */
const BRIDGE = strip(read('css/systems/lasuite/bridge.css'))

/** The custom properties one of bridge.css's dark scopes declares. */
function bridgeDark(scope) {
	const out = {}
	for (const rule of parseRules(BRIDGE)) {
		const media = /prefers-color-scheme:\s*dark/.test(rule.context)
		const explicit =
			rule.context === '' && rule.selector.includes("data-themes*='dark'")
		if ((scope === 'media' && media) || (scope === 'explicit' && explicit)) {
			for (const d of parseDeclarations(rule.body)) {
				out[d.property] = d.value.replace(/!important/, '').trim()
			}
		}
	}
	return out
}

describe('the La Suite bridge in dark mode', () => {
	const GROUNDS = [
		'--color-background-hover',
		'--color-background-dark',
		'--color-background-darker',
	]
	for (const scope of ['media', 'explicit']) {
		it(`keeps the text readable on every background state (${scope} dark scope)`, () => {
			const dark = bridgeDark(scope)
			const text = resolve(dark['--color-main-text'], DARK)
			const failures = []
			for (const name of GROUNDS) {
				if (dark[name] === undefined) {
					failures.push(`${name} is not remapped`)
					continue
				}
				const ground = resolve(dark[name], DARK)
				const r = contrast(text, ground)
				if (r < 4.5) {
					failures.push(
						`${name}: ${text} on ${ground} = ${r.toFixed(2)}:1`,
					)
				}
			}
			expect(failures).toEqual([])
		})

		it(`draws borders in the upstream dark border colour (${scope} dark scope)`, () => {
			const dark = bridgeDark(scope)
			expect(
				dark['--color-border'],
				'--color-border is remapped',
			).toBeDefined()
			expect(resolve(dark['--color-border'], DARK)).toBe(
				resolve(
					'var(--lasuite--contextuals--border--surface--primary)',
					DARK,
				),
			)
		})
	}
})
