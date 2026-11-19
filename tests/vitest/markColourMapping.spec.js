/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * `--color-mark` follows a set's mark colour only where it stays legible
 * (token-set-coverage-next-waves 2.2.2, Q-thematiq-12).
 *
 * Nextcloud paints `<mark>` (search hits) with `--color-mark` behind the
 * inherited body text, `--color-main-text`, not behind the set's
 * `--utrecht-mark-color`. A set's `--utrecht-mark-background-color` may
 * become the mark colour only when that pair keeps 4.5:1 in the light theme
 * and in both dark scopes. Every other set keeps Nextcloud's own tint
 * (#fff0c7, dark #4d3800). The measured ratios per set are recorded in
 * docs/reference/token-set-mark-colour.md.
 *
 * @spec openspec/changes/token-set-coverage-next-waves/tasks.md
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { compact } from './support/cssText.js'
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

const PARSED = new Map()

/**
 * A stylesheet parsed once per run: the cascades read the same files for every set and environment.
 *
 * @param {string} file The stylesheet, relative to the app.
 * @return {import('postcss').Root} The parsed stylesheet.
 */
function parsed(file) {
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
function cascade(files, env, matches) {
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

// The sets whose mark colour is Nextcloud's mark colour (Q-thematiq-12, 19 Nov 2026).
const MAPPED = ['hoeksche-waard']

const MARK_SETS = SETS.filter((s) => /--utrecht-mark-background-color\s*:/.test(read(`css/tokens/${s.id}.css`)))

/**
 * What a set gives `<mark>` in one environment: its own mark background, the
 * mapped token, and the body text above it.
 *
 * @param {object} set The token-sets.json entry.
 * @param {object} env The environment.
 * @return {{mark: string|null, mapped: string|null, text: string, ratio: number|null}} The resolved colours.
 */
function paint(set, env) {
	const vars = cascade(bundle(set), env, (s) => reachesBody(s, env))
	const text = resolve('var(--color-main-text)', vars)
	const mark = vars['--utrecht-mark-background-color'] === undefined ? null : resolve('var(--utrecht-mark-background-color)', vars)
	const mapped = vars['--nldesign-nc-color-mark'] === undefined ? null : resolve('var(--nldesign-nc-color-mark)', vars)
	return { mark, mapped, text, ratio: mark === null ? null : ratio(text, mark) }
}

/**
 * Whether a set ships the environment.
 *
 * @param {object} set The token-sets.json entry.
 * @param {object} env The environment.
 * @return {boolean} True when it does.
 */
function ships(set, env) {
	return env.themes === 'light' || exists(`css/tokens/dark/${set.id}.css`)
}

describe('--color-mark follows the set only where it stays legible (next-waves 2.2.2)', () => {
	it('stays unmapped in overrides.css, for every set', () => {
		expect(read('css/systems/nldesign/overrides.css')).not.toMatch(/^\s*--color-mark\s*:/m)
	})

	it('measures the sets that declare a mark background', () => {
		expect(MARK_SETS.length).toBeGreaterThanOrEqual(7)
		for (const id of MAPPED) {
			expect(MARK_SETS.map((s) => s.id)).toContain(id)
		}
	})

	for (const [envName, env] of Object.entries(ENVIRONMENTS)) {
		it(`maps exactly the sets whose mark keeps body text at 4.5:1 in every mode, in the ${envName}`, () => {
			const mappedHere = []
			const failures = {}
			for (const set of MARK_SETS.filter((s) => ships(s, env))) {
				const p = paint(set, env)
				if (p.mapped !== null) {
					mappedHere.push(set.id)
					if (p.mapped !== p.mark) {
						failures[set.id] = `maps ${p.mapped}, its mark is ${p.mark}`
					} else if (p.ratio < 4.5) {
						failures[set.id] = `${p.text} on ${p.mark} = ${p.ratio.toFixed(2)}:1`
					}
				}
			}
			expect(failures).toEqual({})
			expect(mappedHere.sort()).toEqual(MAPPED.filter((id) => ships(SETS.find((s) => s.id === id), env)))
		})
	}

	it('leaves Nextcloud\'s tint on every set where some mode would fall under 4.5:1', () => {
		for (const set of MARK_SETS.filter((s) => !MAPPED.includes(s.id))) {
			const below = Object.values(ENVIRONMENTS).filter((env) => ships(set, env)).some((env) => {
				const p = paint(set, env)
				return p.ratio === null || p.ratio < 4.5
			})
			expect([set.id, below]).toEqual([set.id, true])
		}
	})
})
