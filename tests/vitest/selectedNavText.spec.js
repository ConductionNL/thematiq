/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The selected app-navigation entry keeps a readable label (thematiq#1051).
 *
 * Since Nextcloud 34, NcAppNavigationItem draws the selected entry as a WASH:
 * `color-mix(in srgb, var(--color-primary-element) 16%, transparent)` over the
 * navigation (22% while hovered), with the label in `--color-main-text`. Only
 * entries marked `.app-navigation-entry--legacy` keep the old solid
 * `--color-primary-element` fill with `--color-primary-element-text` on it.
 *
 * nldesign's element-overrides.css handed EVERY selected entry the solid fill's
 * label colour through `--nldesign-color-on-surface`. On the wash that label
 * has nothing to stand on: Utrecht dark painted #111111 on a dark blue wash,
 * and light painted white on a pale one.
 *
 * This resolves each shipped set's cascade (its design system's stylesheets,
 * its token file, its dark variant) in the light theme and in both dark
 * scopes, and measures the label a selected entry gets against the wash it
 * sits on, at rest and hovered. Summer Breeze, La Suite and Cunningham paint
 * their own selection and are held by their own tests.
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

/** The systems that leave the selected entry to Nextcloud's wash. */
const WASH_SYSTEMS = ['nldesign', 'high-contrast']

const SYSTEMS = Object.fromEntries(
	JSON.parse(read('design-systems.json')).map((s) => [s.id, s]),
)
const SETS = JSON.parse(read('token-sets.json')).filter(
	(s) =>
		WASH_SYSTEMS.includes(s.design_system ?? 'nldesign')
		&& exists(`css/tokens/${s.id}.css`),
)

/** The selected entry as Nextcloud 34 and 35 render it. */
const ENTRY = '#app-navigation-vue .app-navigation-entry.active'
const NAVIGATION = '#app-navigation-vue'

/** The wash shares: at rest and hovered. */
const WASHES = { 'at rest': 0.16, hovered: 0.22 }

/** Nextcloud's own main background, which nldesign leaves alone. */
const ENVIRONMENTS = {
	light: { scheme: 'light', themes: 'light', page: '#ffffff' },
	'system dark preference': { scheme: 'dark', themes: null, page: '#171717' },
	'explicit dark theme': { scheme: 'light', themes: 'dark', page: '#171717' },
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
 * The winning declarations for the rules a predicate selects.
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
 * Parse an opaque #rgb or #rrggbb colour (an alpha channel is dropped).
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
		throw new Error(`not a hex colour: "${value}"`)
	}
	if (h.length === 3) {
		h = [...h].map((c) => c + c).join('')
	}
	return [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16))
}

/**
 * The colour a share of `top` over `under` paints.
 *
 * @param {string} top The wash colour.
 * @param {number} share Its share, 0 to 1.
 * @param {string} under The surface below.
 * @return {string} The composited hex.
 */
function wash(top, share, under) {
	const a = rgb(top)
	const b = rgb(under)
	return (
		'#'
		+ a
			.map((c, i) => Math.round(c * share + b[i] * (1 - share)))
			.map((c) => c.toString(16).padStart(2, '0'))
			.join('')
	)
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
 * The stylesheets a set loads, in order.
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
	return files
}

/**
 * The label colour of the selected entry: what the entry hands its text
 * through `--nldesign-color-on-surface`, else what the navigation hands it,
 * else Nextcloud's own `--color-main-text`.
 *
 * @param {string[]} files The bundle.
 * @param {object} env The environment.
 * @param {Record<string, string>} vars The body-level variables.
 * @return {string} The label colour.
 */
function label(files, env, vars) {
	const entry = cascade(files, env, (s) => s.trim() === ENTRY)
	const nav = cascade(files, env, (s) => s.trim() === NAVIGATION)
	const value =
		entry['--nldesign-color-on-surface']
		?? nav['--nldesign-color-on-surface']
		?? 'var(--color-main-text)'
	return resolve(value, vars)
}

/**
 * Every failing label in one environment.
 *
 * @param {object} set The token-sets.json entry.
 * @param {object} env The environment.
 * @return {string[]} The failures.
 */
function failures(set, env) {
	const files = bundle(set)
	const vars = cascade(files, env, (s) => reachesBody(s, env))
	const text = label(files, env, vars)
	const page = vars['--color-main-background']
		? resolve(vars['--color-main-background'], vars)
		: env.page
	const element = resolve('var(--color-primary-element)', vars)
	const out = []
	for (const [state, share] of Object.entries(WASHES)) {
		const ground = wash(element, share, page)
		const r = ratio(text, ground)
		if (r < 4.5) {
			out.push(`${state}: ${text} on ${ground} = ${r.toFixed(2)}:1`)
		}
	}
	return out
}

describe('the selected navigation entry label (thematiq#1051)', () => {
	it('covers the shipped sets, Utrecht among them', () => {
		expect(SETS.length).toBeGreaterThan(40)
		expect(SETS.map((s) => s.id)).toContain('utrecht')
	})

	for (const [envName, env] of Object.entries(ENVIRONMENTS)) {
		it(`reaches 4.5:1 on the wash for every set in the ${envName}`, () => {
			const all = {}
			for (const set of SETS) {
				if (
					env.themes !== 'light'
					&& !exists(`css/tokens/dark/${set.id}.css`)
				) {
					continue
				}
				const f = failures(set, env)
				if (f.length > 0) {
					all[set.id] = f
				}
			}
			expect(all).toEqual({})
		})
	}

	it('keeps the solid fill label on legacy entries', () => {
		const files = bundle({ id: 'utrecht', design_system: 'nldesign' })
		const legacy = cascade(
			files,
			ENVIRONMENTS.light,
			(s) =>
				s.trim()
				=== '#app-navigation-vue .app-navigation-entry--legacy.active',
		)
		expect(legacy['--nldesign-color-on-surface']).toBe(
			'var(--color-primary-element-text)',
		)
	})
})
