/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The error chip and error button labels read on the error fill (thematiq#1027).
 *
 * NcChip variant="error" and NcButton type="error" paint
 * `background: var(--color-error)`, and css/error-contrast.css pins their
 * label. A literal white label lands on the LIGHTER red every dark variant
 * derives, and fails there. This resolves each shipped set's real cascade
 * (its design system's stylesheets in design-systems.json order, its token
 * file, its dark variant, then error-contrast.css) in the light theme and in
 * both dark scopes, and measures the label against the fill it sits on.
 *
 * @spec openspec/specs/dark-mode/spec.md
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { describe, expect, it } from 'vitest'

const ROOT = path.resolve(__dirname, '../..')
const read = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8')
const exists = (rel) => fs.existsSync(path.join(ROOT, rel))

const SYSTEMS = Object.fromEntries(
	JSON.parse(read('design-systems.json')).map((s) => [s.id, s]),
)
const SETS = JSON.parse(read('token-sets.json')).filter(
	(s) =>
		(s.design_system ?? 'nldesign') !== 'none'
		&& exists(`css/tokens/${s.id}.css`),
)

/** The selectors error-contrast.css pins a label on, and what paints them. */
const LABELS = {
	'error chip': '.nc-chip--error',
	'error button': '.button-vue--error',
}

const ENVIRONMENTS = {
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
function conditionsHold(node, env) {
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
function reachesBody(selector, env) {
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

/**
 * The winning declarations for the rules a predicate selects: `!important`
 * beats normal, then source order.
 *
 * @param {string[]} files Stylesheets in cascade order.
 * @param {object} env The environment.
 * @param {(selector: string) => boolean} matches Selects the rules to read.
 * @return {Record<string, string>} Property to value.
 */
function cascade(files, env, matches) {
	const won = {}
	for (const file of files) {
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
 * @param {Record<string, string>} vars The custom properties.
 * @return {string} The literal.
 */
function resolve(value, vars) {
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
 * Parse #rgb, #rrggbb or a `#fff`-style keyword-free colour.
 *
 * @param {string} value The colour.
 * @return {number[]} [r, g, b].
 */
function rgb(value) {
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
 * The stylesheets a set loads, in order, as CssInjectionService emits them.
 *
 * @param {object} set The token-sets.json entry.
 * @return {string[]} App-relative paths.
 */
function bundle(set) {
	const system = SYSTEMS[set.design_system ?? 'nldesign']
	const files = system.stylesheets.map((s) => `css/${s}.css`)
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
	files.push('css/error-contrast.css')
	return files
}

/**
 * Every label below 4.5:1 on its fill, in one environment.
 *
 * @param {object} set The token-sets.json entry.
 * @param {object} env The environment.
 * @return {string[]} The failures.
 */
function failures(set, env) {
	const files = bundle(set)
	const vars = cascade(files, env, (s) => reachesBody(s, env))
	const fill = resolve('var(--color-error)', vars)
	const out = []
	for (const [name, selector] of Object.entries(LABELS)) {
		const own = cascade(files, env, (s) => s.trim() === selector)
		const label = resolve(own.color, vars)
		const r = ratio(label, fill)
		if (r < 4.5) {
			out.push(`${name}: ${label} on ${fill} = ${r.toFixed(2)}:1`)
		}
	}
	return out
}

describe('error labels on the error fill (thematiq#1027)', () => {
	it('covers the shipped sets and their dark variants', () => {
		expect(SETS.length).toBeGreaterThan(40)
		expect(
			SETS.filter((s) => exists(`css/tokens/dark/${s.id}.css`)).length,
		).toBeGreaterThan(40)
	})

	for (const [envName, env] of Object.entries(ENVIRONMENTS)) {
		it(`reaches 4.5:1 for every shipped set in the ${envName}`, () => {
			const all = {}
			for (const set of SETS) {
				if (env.scheme === 'dark' || env.themes !== 'light') {
					if (!exists(`css/tokens/dark/${set.id}.css`)) {
						continue
					}
				}
				const f = failures(set, env)
				if (f.length > 0) {
					all[set.id] = f
				}
			}
			expect(all).toEqual({})
		})

		// Found in the live run (Utrecht, Nextcloud 35, the trash bin's "Empty
		// deleted files" button): the label is a <span> inside the button, and
		// nldesign's element-overrides.css paints every span with
		// `var(--nldesign-color-on-surface, var(--nldesign-color-text))
		// !important`. The button's own colour never reached it: the label
		// read rgb(235, 235, 235) on the dark red and black on the light red.
		// The documented opt-out is to set --nldesign-color-on-surface on the
		// fill, so it inherits into every descendant.
		it(`hands the same label to the text inside the fill in the ${envName}`, () => {
			const all = {}
			for (const set of SETS) {
				if (env.scheme === 'dark' || env.themes !== 'light') {
					if (!exists(`css/tokens/dark/${set.id}.css`)) {
						continue
					}
				}
				const files = bundle(set)
				const vars = cascade(files, env, (s) => reachesBody(s, env))
				for (const [name, selector] of Object.entries(LABELS)) {
					const own = cascade(files, env, (s) => s.trim() === selector)
					const inner = own['--nldesign-color-on-surface']
					if (inner === undefined) {
						all[`${set.id} ${name}`] =
							'sets no --nldesign-color-on-surface'
					} else if (resolve(inner, vars) !== resolve(own.color, vars)) {
						all[`${set.id} ${name}`] =
							`inner ${resolve(inner, vars)} vs own ${resolve(own.color, vars)}`
					}
				}
			}
			expect(all).toEqual({})
		})
	}
})
