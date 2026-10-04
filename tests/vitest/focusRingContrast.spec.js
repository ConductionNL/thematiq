/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The nldesign focus ring reaches 3:1 (#896, WCAG 2.2 SC 1.4.11).
 *
 * Every set declares `--nldesign-color-focus` translucent on purpose
 * (ADR-CSS-003): a halo that composites against whatever it sits on. On white
 * the default rgba(0, 123, 199, 0.5) paints rgb(128, 189, 227), about 2.0:1.
 * So the ring is two-tone: the translucent token stays as the outer halo, and
 * the 2px outline itself is the SAME colour made opaque.
 *
 * Read from the stylesheets themselves: every `outline` / `outline-color`
 * declaration in a `*:focus-visible` rule of each bundle's ring stylesheet
 * (css/systems/nldesign/theme.css, and css/systems/lasuite/bridge.css for
 * lasuite and cunningham, #970) is
 * resolved against the default set's tokens (defaults.css, then
 * tokens/rijkshuisstijl.css) on white, and against the generated dark variant
 * on Nextcloud's dark main background. A translucent colour is composited over
 * the background first, which is exactly how it renders.
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import postcss from 'postcss'
import { describe, it, expect } from 'vitest'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8')

/** Nextcloud's own main background in the light and the dark theme. */
const WHITE = [255, 255, 255]
const NC_DARK = [23, 23, 23]

/**
 * Every custom property a stylesheet declares; a later declaration wins.
 *
 * @param {string[]} files Stylesheets in cascade order.
 * @return {Record<string, string>} Property name to raw value.
 */
function tokens(files) {
	const out = {}
	for (const file of files) {
		postcss.parse(read(file)).walkDecls(/^--/, (d) => {
			out[d.prop] = d.value.trim()
		})
	}
	return out
}

/**
 * Parse a hex, rgb() or rgba() colour.
 *
 * @param {string} value The colour.
 * @return {number[]} [r, g, b, a].
 */
function parseColour(value) {
	const v = value.trim()
	const hex = v.match(/^#([0-9a-f]{3,8})$/i)
	if (hex) {
		let h = hex[1]
		if (h.length <= 4) {
			h = [...h].map((c) => c + c).join('')
		}
		const n = (i) => parseInt(h.slice(i, i + 2), 16)
		return [n(0), n(2), n(4), h.length === 8 ? n(6) / 255 : 1]
	}
	const fn = v.match(/^rgba?\(([^)]*)\)$/i)
	if (fn) {
		const parts = fn[1]
			.split(/[\s,/]+/)
			.filter(Boolean)
			.map(Number)
		return [parts[0], parts[1], parts[2], parts[3] ?? 1]
	}
	throw new Error(`cannot parse colour "${value}"`)
}

/**
 * Resolve `var(--x)` references, one level at a time.
 *
 * @param {string} value The declared value.
 * @param {Record<string, string>} vars The tokens.
 * @return {string} The value with every var() replaced.
 */
function substitute(value, vars) {
	let v = value
	for (let i = 0; i < 10 && /var\(/.test(v); i++) {
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
	return v
}

/**
 * The colour an outline declaration paints over a background.
 *
 * @param {string} value The outline or outline-color value.
 * @param {Record<string, string>} vars The tokens.
 * @param {number[]} bg The background [r, g, b].
 * @return {number[]} The painted [r, g, b].
 */
function painted(value, vars, bg) {
	const v = substitute(value.replace(/!important/, ''), vars)
		.replace(/^\s*\d+px\s+solid\s+/, '')
		.trim()
	// Relative colour syntax keeps the origin's channels AND its alpha unless
	// an explicit `/ <alpha>` replaces it (CSS Color 5): `rgb(from X r g b)`
	// on a translucent X is just as translucent (#931).
	const relative = v.match(
		/^rgb\(\s*from\s+(.+?)\s+r\s+g\s+b\s*(?:\/\s*([\d.]+%?)\s*)?\)$/i,
	)
	let colour
	if (relative) {
		const origin = parseColour(relative[1])
		let alpha = origin[3]
		if (relative[2] !== undefined) {
			alpha = relative[2].endsWith('%')
				? parseFloat(relative[2]) / 100
				: Number(relative[2])
		}
		colour = [...origin.slice(0, 3), alpha]
	} else {
		colour = parseColour(v)
	}
	const [r, g, b, a] = colour
	return [r, g, b].map((c, i) => c * a + bg[i] * (1 - a))
}

/**
 * WCAG relative luminance.
 *
 * @param {number[]} rgb The colour.
 * @return {number} The luminance.
 */
function luminance(rgb) {
	const [r, g, b] = rgb.map((c) => {
		const s = c / 255
		return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
	})
	return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

/**
 * WCAG contrast ratio.
 *
 * @param {number[]} a First colour.
 * @param {number[]} b Second colour.
 * @return {number} The ratio.
 */
function contrast(a, b) {
	const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x)
	return (hi + 0.05) / (lo + 0.05)
}

/**
 * The *:focus-visible rules of a bundle's stylesheet, in source order.
 *
 * @param {string} sheet The stylesheet that draws the ring.
 * @return {import('postcss').Rule[]} The rules, @supports variants included.
 */
function focusRules(sheet) {
	const rules = []
	postcss.parse(read(sheet)).walkRules((rule) => {
		if (/(^|,)\s*\*:focus-visible\s*(,|$)/.test(rule.selector)) {
			rules.push(rule)
		}
	})
	return rules
}

/**
 * The custom properties a rule declares on the focused element.
 *
 * @param {import('postcss').Rule} rule The rule.
 * @return {Record<string, string>} Property name to raw value.
 */
function ownProps(rule) {
	const out = {}
	rule.walkDecls(/^--/, (d) => {
		out[d.prop] = d.value.trim()
	})
	return out
}

/**
 * Every outline colour a browser can paint, one per rule variant: the plain
 * rule alone, and the plain rule with each @supports rule applied on top.
 *
 * @param {string} sheet The stylesheet that draws the ring.
 * @param {Record<string, string>} tokens The set's tokens.
 * @return {Array<{value: string, vars: Record<string, string>}>} Each outline with the variables it resolves against.
 */
function outlineVariants(sheet, tokens) {
	const rules = focusRules(sheet)
	const outlines = []
	const base = {}
	for (const rule of rules) {
		rule.walkDecls(/^outline(-color)?$/, (d) => outlines.push(d.value))
		if (rule.parent.type !== 'atrule') {
			Object.assign(base, ownProps(rule))
		}
	}
	const variants = [{ ...tokens, ...base }]
	for (const rule of rules) {
		if (rule.parent.type === 'atrule') {
			variants.push({ ...tokens, ...base, ...ownProps(rule) })
		}
	}
	return outlines.flatMap((value) => variants.map((vars) => ({ value, vars })))
}

/**
 * Each bundle's ring, resolved against its default set: the stylesheet that
 * draws it, and the token files in cascade order for light and for dark. The
 * La Suite bundle (lasuite and cunningham share its bridge) drew the
 * translucent token as the outline itself (#970).
 */
const BUNDLES = [
	{
		name: 'nldesign (rijkshuisstijl)',
		sheet: 'css/systems/nldesign/theme.css',
		light: [
			'css/systems/nldesign/defaults.css',
			'css/tokens/rijkshuisstijl.css',
		],
		dark: ['css/tokens/dark/rijkshuisstijl.css'],
	},
	{
		name: 'lasuite',
		sheet: 'css/systems/lasuite/bridge.css',
		light: [
			'css/systems/lasuite/defaults.css',
			'css/systems/lasuite/brand-override.css',
			'css/systems/lasuite/bridge.css',
			'css/tokens/lasuite.css',
		],
		dark: ['css/tokens/dark/lasuite.css'],
	},
	{
		name: 'cunningham',
		sheet: 'css/systems/lasuite/bridge.css',
		light: [
			'css/systems/lasuite/defaults.css',
			'css/systems/lasuite/bridge.css',
			'css/tokens/cunningham.css',
		],
		dark: ['css/tokens/dark/cunningham.css'],
	},
]

for (const bundle of BUNDLES) {
	const LIGHT = tokens(bundle.light)
	const DARK = { ...LIGHT, ...tokens(bundle.dark) }

	describe(`the ${bundle.name} focus ring reaches 3:1`, () => {
		it('has an outline colour declared on *:focus-visible', () => {
			expect(outlineVariants(bundle.sheet, LIGHT).length).toBeGreaterThan(0)
		})

		for (const [name, vars, bg] of [
			['on white', LIGHT, WHITE],
			['on the dark surface', DARK, NC_DARK],
		]) {
			it(`every outline colour clears 3:1 ${name}`, () => {
				for (const { value, vars: scoped } of outlineVariants(
					bundle.sheet,
					vars,
				)) {
					const ratio = contrast(painted(value, scoped, bg), bg)
					expect(
						ratio,
						`${value} paints ${ratio.toFixed(2)}:1 ${name}`,
					).toBeGreaterThanOrEqual(3)
				}
			})
		}

		it('keeps the translucent token as the halo', () => {
			const shadows = []
			for (const rule of focusRules(bundle.sheet)) {
				rule.walkDecls('box-shadow', (d) => shadows.push(d.value))
			}
			expect(
				shadows.some((s) => s.includes('var(--nldesign-color-focus)')),
			).toBe(true)
		})
	})
}
