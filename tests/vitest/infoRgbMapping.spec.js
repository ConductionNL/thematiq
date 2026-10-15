/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * `--color-info-rgb` follows the set's info colour (token-set-coverage-next-waves 2.2).
 *
 * overrides.css maps Nextcloud's `--color-info-rgb` to
 * `--nldesign-color-info-rgb`. The triple must name the same colour as
 * `--color-info`, and a wash of it behind body text must keep that text
 * legible: Nextcloud draws its warning box as
 * `rgba(var(--color-warning-rgb), 0.2)` under `--color-main-text`, and an info
 * wash is measured the same way, on the set's page background (Nextcloud's own where the set sets none), in the light
 * theme and in both dark scopes.
 *
 * @spec openspec/changes/token-set-coverage-next-waves/tasks.md
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
		(s.design_system ?? 'nldesign') === 'nldesign'
		&& exists(`css/tokens/${s.id}.css`),
)

const WASH_ALPHA = 0.2

// Nextcloud's own --color-main-background (DefaultTheme, DarkTheme), for a set that sets no page colour.
const NC_LIGHT_PAGE = '#ffffff'
const NC_DARK_PAGE = '#171717'

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

/**
 * A hex colour for an `r, g, b` triple.
 *
 * @param {string} triple The triple.
 * @return {string} #rrggbb.
 */
function hexOf(triple) {
	const parts = triple.split(',').map((x) => Number(x.trim()))
	if (
		parts.length !== 3
		|| parts.some((x) => !Number.isInteger(x) || x < 0 || x > 255)
	) {
		throw new Error(`not an r, g, b triple: "${triple}"`)
	}
	return '#' + parts.map((x) => x.toString(16).padStart(2, '0')).join('')
}

/**
 * The opaque channels of a colour as #rrggbb, its alpha dropped: the triple
 * carries no alpha, and a translucent info colour (xxllnc) keeps its own.
 *
 * @param {string} value #rgb, #rrggbb, #rrggbbaa or rgb()/rgba().
 * @return {string} #rrggbb.
 */
function channels(value) {
	const m = value.match(/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i)
	if (m) {
		return hexOf(m.slice(1, 4).join(','))
	}
	return (
		'#'
		+ rgb(value)
			.map((x) => x.toString(16).padStart(2, '0'))
			.join('')
	)
}

/**
 * The colour a wash of `top` at `alpha` gives over `base`.
 *
 * @param {string} top The washed colour.
 * @param {string} base The opaque colour below.
 * @param {number} alpha The wash opacity.
 * @return {string} #rrggbb.
 */
function over(top, base, alpha) {
	const t = rgb(top)
	const b = rgb(base)
	return (
		'#'
		+ t
			.map((x, i) =>
				Math.round(alpha * x + (1 - alpha) * b[i])
					.toString(16)
					.padStart(2, '0'),
			)
			.join('')
	)
}

/**
 * What one set paints, in one environment.
 *
 * @param {object} set The token-sets.json entry.
 * @param {object} env The environment.
 * @return {object} The resolved colours.
 */
function paint(set, env) {
	const vars = cascade(bundle(set), env, (s) => reachesBody(s, env))
	const info = resolve('var(--color-info)', vars)
	const infoRgb = hexOf(resolve('var(--color-info-rgb)', vars))
	const dark = env.scheme === 'dark' || env.themes === 'dark'
	const page = resolve(
		`var(--nldesign-color-background, ${dark ? NC_DARK_PAGE : NC_LIGHT_PAGE})`,
		vars,
	)
	const text = resolve('var(--color-main-text)', vars)
	return { info, infoRgb, page, text, wash: over(infoRgb, page, WASH_ALPHA) }
}

/**
 * Run a check on every set that ships the environment.
 *
 * @param {object} env The environment.
 * @param {(set: object, p: object) => string|null} check A failure, or null.
 * @return {Record<string, string>} Failures by set id.
 */
function everySet(env, check) {
	const all = {}
	for (const set of SETS) {
		if (
			(env.scheme === 'dark' || env.themes !== 'light')
			&& !exists(`css/tokens/dark/${set.id}.css`)
		) {
			continue
		}
		const failure = check(set, paint(set, env))
		if (failure !== null) {
			all[set.id] = failure
		}
	}
	return all
}

describe('--color-info-rgb follows the set (token-set-coverage-next-waves 2.2)', () => {
	it('is mapped in overrides.css, not left to Nextcloud', () => {
		expect(read('css/systems/nldesign/overrides.css')).toMatch(
			/^\s*--color-info-rgb:\s*var\(--nldesign-color-info-rgb\)\s*!important;/m,
		)
	})

	it('covers the shipped sets and their dark variants', () => {
		expect(SETS.length).toBeGreaterThan(40)
		expect(
			SETS.filter((s) => exists(`css/tokens/dark/${s.id}.css`)).length,
		).toBeGreaterThan(40)
	})

	for (const [envName, env] of Object.entries(ENVIRONMENTS)) {
		it(`names the same colour as --color-info in the ${envName}`, () => {
			expect(
				everySet(env, (set, p) =>
					p.infoRgb === channels(p.info)
						? null
						: `${p.infoRgb} vs ${p.info}`,
				),
			).toEqual({})
		})

		it(`keeps body text at 4.5:1 on a ${WASH_ALPHA * 100}% info wash in the ${envName}`, () => {
			expect(
				everySet(env, (set, p) => {
					const r = ratio(p.text, p.wash)
					return r >= 4.5
						? null
						: `${p.text} on ${p.wash} (${p.infoRgb} over ${p.page}) = ${r.toFixed(2)}:1`
				}),
			).toEqual({})
		})
	}
})
