/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The cascade a shipped token set paints on <body>: the stylesheets it loads,
 * the winning custom properties in one theme environment, var() resolution
 * and WCAG contrast. Shared by the contrast tests that measure a set's
 * Nextcloud variables.
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { compact } from './cssText.js'

const ROOT = path.resolve(__dirname, '../../..')
export const read = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8')
export const exists = (rel) => fs.existsSync(path.join(ROOT, rel))

export const SYSTEMS = Object.fromEntries(
	JSON.parse(read('design-systems.json')).map((s) => [s.id, s]),
)
export const SETS = JSON.parse(read('token-sets.json')).filter(
	(s) =>
		(s.design_system ?? 'nldesign') === 'nldesign'
		&& exists(`css/tokens/${s.id}.css`),
)

export const ENVIRONMENTS = {
	light: { scheme: 'light', themes: 'light' },
	'system dark preference': { scheme: 'dark', themes: null },
	'explicit dark theme': { scheme: 'light', themes: 'dark' },
}
/**
 * Whether the @media rules around a node hold in an environment.
 *
 * @param {import('postcss').Node} node The rule.
 * @param {object} env The environment.
 * @return {boolean} True when every enclosing condition matches.
 */
export function conditionsHold(node, env) {
	for (let p = node.parent; p && p.type !== 'root'; p = p.parent) {
		if (p.type !== 'atrule') {
			return false
		}
		const q = p.params.replace(/\s+/g, ' ').trim()
		if (p.name !== 'media') {
			// @supports and friends: assume a current browser.
			continue
		}
		if (q === '(prefers-color-scheme: dark)') {
			if (env.scheme !== 'dark') {
				return false
			}
		} else if (q === '(prefers-color-scheme: light)') {
			if (env.scheme === 'dark') {
				return false
			}
		} else {
			// prefers-contrast, forced-colors, widths: not this environment.
			return false
		}
	}
	return true
}

/**
 * Whether one selector selects `<body>` or the root above it.
 *
 * @param {string} selector A single selector.
 * @param {object} env The environment.
 * @return {boolean} True when it matches.
 */
export function reachesBody(selector, env) {
	const s = selector.replace(/\s+/g, '').replace(/['"]/g, '')
	if (['html', ':root', 'body', 'body[data-themes]'].includes(s)) {
		return true
	}
	if (s.startsWith('body:not([data-theme-light])')) {
		return env.themes === null
	}
	if (s === 'body[data-theme-dark]') {
		return env.themes === 'dark'
	}
	if (s === 'body[data-themes*=dark]' || s === '[data-themes*=dark]') {
		return env.themes !== null && env.themes.includes('dark')
	}
	if (s === 'body[data-theme-light]' || s === 'body[data-themes*=light]') {
		return env.themes !== null && env.themes.includes('light')
	}
	return false
}

const PARSED = new Map()

/**
 * A stylesheet parsed once per run: the cascades read the same files for every set and environment.
 *
 * @param {string} file The stylesheet, relative to the app.
 * @return {import('postcss').Root} The parsed stylesheet.
 */
export function parsed(file) {
	if (!PARSED.has(file)) {
		PARSED.set(file, postcss.parse(read(file)))
	}
	return PARSED.get(file)
}

/**
 * The winning declarations for the rules a predicate selects: `!important`
 * beats normal, then source order.
 *
 * @param {string[]} files Stylesheets in cascade order.
 * @param {object} env The environment.
 * @param {(selector: string) => boolean} matches Selects the rules to read.
 * @return {Record<string, string>} Property to value.
 */
export function cascade(files, env, matches) {
	const won = {}
	for (const file of files) {
		parsed(file).walkRules((rule) => {
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
				won[d.prop] = { value: compact(d.value), important: d.important }
			})
		})
	}
	return Object.fromEntries(Object.entries(won).map(([k, v]) => [k, v.value]))
}

/**
 * Replace every var() with its value, fallbacks included.
 *
 * @param {string} value The declared value.
 * @param {Record<string, string>} vars The custom properties.
 * @return {string} The literal.
 */
export function resolve(value, vars) {
	let v = value.replace(/!important/, '')
	for (let i = 0; i < 30 && /var\(/.test(v); i++) {
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
 * Convert an opaque `hsl()`, in the space- or comma-separated syntax, to
 * [r, g, b]; null for anything else or a colour that is not opaque.
 *
 * @param {string} value The colour.
 * @return {number[]|null} [r, g, b], or null.
 */
function hslToRgb(value) {
	const m = /^hsla?\(\s*(-?[\d.]+)(?:deg)?[\s,]+([\d.]+)%[\s,]+([\d.]+)%(?:\s*[,/]\s*([\d.]+)(%?))?\s*\)$/i.exec(value.trim())
	if (m === null) {
		return null
	}
	if (m[4] !== undefined && Number(m[4]) !== (m[5] === '%' ? 100 : 1)) {
		return null
	}
	const hue = ((Number(m[1]) % 360) + 360) % 360
	const sat = Number(m[2]) / 100
	const light = Number(m[3]) / 100
	const chroma = (1 - Math.abs(2 * light - 1)) * sat
	const at = (n) => {
		const k = (n + hue / 30) % 12
		return light - chroma / 2 * Math.max(-1, Math.min(k - 3, 9 - k, 1))
	}
	return [at(0), at(8), at(4)].map((c) => Math.round(c * 255))
}

/**
 * Parse #rgb, #rrggbb or an opaque `hsl()` colour.
 *
 * @param {string} value The colour.
 * @return {number[]} [r, g, b].
 */
export function rgb(value) {
	const fromHsl = hslToRgb(value)
	if (fromHsl !== null) {
		return fromHsl
	}
	let h = value.trim().replace('#', '')
	if (h.length === 8) {
		h = h.slice(0, 6)
	}
	if (!/^[0-9a-f]{3}([0-9a-f]{3})?$/i.test(h)) {
		throw new Error(`not an opaque hex colour: "${value}"`)
	}
	if (h.length === 3) {
		h = [...h].map((c) => c + c).join('')
	}
	return [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16))
}

/**
 * WCAG contrast ratio.
 *
 * @param {string} a First colour.
 * @param {string} b Second colour.
 * @return {number} The ratio.
 */
export function ratio(a, b) {
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
 * The stylesheets a set loads, in order, as CssInjectionService emits them.
 *
 * @param {object} set The token-sets.json entry.
 * @return {string[]} App-relative paths.
 */
export function bundle(set) {
	const files = SYSTEMS[set.design_system ?? 'nldesign'].stylesheets.map(
		(s) => `css/${s}.css`,
	)
	if (set.extends) {
		files.push(`css/tokens/${set.extends}.css`)
	}
	files.push(`css/tokens/${set.id}.css`)
	if (exists(`css/token-overrides/${set.id}.css`)) {
		files.push(`css/token-overrides/${set.id}.css`)
	}
	if (exists(`css/tokens/dark/${set.id}.css`)) {
		files.push(`css/tokens/dark/${set.id}.css`)
	}
	return files
}
