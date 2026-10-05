/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Files table header, footer and README sit on the content surface (thematiq#1061).
 *
 * component-scopes.css redirects `--color-main-background` on the app content
 * to `--nldesign-component-content-surface-background-color` and hands the
 * captured global back to its children, so cards stand out from the surface.
 * The Files table header and footer are not cards: Nextcloud paints them with
 * `--color-main-background` only so the sticky header hides the rows under
 * it. On a set with its own surface they showed as white strips on
 * Zuiddrecht's #F5F6F8 in light, and as #171717 on its #121315 in dark.
 *
 * This resolves every nldesign set's cascade in light and in both dark scopes
 * and checks that the header and footer paint exactly what the content
 * surface paints. A set with no surface token keeps Nextcloud's own value.
 *
 * @spec openspec/specs/component-tokens/spec.md
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

const PARSED = new Map()
/**
 * A stylesheet parsed once.
 *
 * @param {string} file App-relative path.
 * @return {import('postcss').Root} The tree.
 */
const parsed = (file) => {
	if (!PARSED.has(file)) {
		PARSED.set(file, postcss.parse(read(file)))
	}
	return PARSED.get(file)
}

const CONTENT = '#app-content-vue'
const STRIPS = [
	'.files-list__thead',
	'.files-list__tfoot',
	'#rich-workspace .text-editor',
	'#rich-workspace .text-editor__main',
	'#rich-workspace .editor',
	'#rich-workspace .text-menubar',
	'#rich-workspace .ProseMirror',
	'#rich-workspace .heading-anchor',
	"#rich-workspace div[contenteditable='false']",
]

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
	const s = selector
		.replace(/\s+/g, '')
		.replace(/['"]/g, '')
		// `:root body…` (token-overrides) reaches the same body.
		.replace(/^:root(?=body)/, '')
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
		parsed(file).walkRules((rule) => {
			if (!conditionsHold(rule, env)) {
				return
			}
			const hit = rule.selectors.filter(matches)
			if (hit.length === 0) {
				return
			}
			const weight = Math.max(...hit.map(specificity))
			rule.each((d) => {
				if (d.type !== 'decl') {
					return
				}
				const prior = won[d.prop]
				if (prior && prior.important && !d.important) {
					return
				}
				// Equal importance: the more specific selector wins, then order.
				if (
					prior
					&& prior.important === d.important
					&& prior.weight > weight
				) {
					return
				}
				won[d.prop] = {
					value: d.value.trim(),
					important: d.important,
					weight,
				}
			})
		})
	}
	return Object.fromEntries(Object.entries(won).map(([k, v]) => [k, v.value]))
}

/**
 * Specificity of one selector, folded into one comparable number.
 *
 * @param {string} selector The selector.
 * @return {number} ids * 10000 + classes * 100 + types.
 */
function specificity(selector) {
	const bare = selector.replace(/:where\([^)]*\)/g, '')
	const ids = (bare.match(/#[\w-]+/g) ?? []).length
	const classes = (bare.match(/\.[\w-]+|\[[^\]]+\]|:(?!:)[\w-]+/g) ?? []).length
	const types = (
		bare
			.replace(/[#.][\w-]+|\[[^\]]+\]|:[\w-]+(\([^)]*\))?/g, ' ')
			.match(/\b[a-z][\w-]*/gi) ?? []
	).length
	return ids * 10000 + classes * 100 + types
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
 * The stylesheets a set loads, in order (CssInjectionService::layers).
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
	// CssInjectionService appends these after the set, in this order.
	files.push(
		'css/icon-contrast.css',
		'css/error-contrast.css',
		'css/theme-scopes.css',
		'css/component-scopes.css',
	)
	return files
}

/**
 * The custom properties one level down: a rule's declarations, each resolved
 * against the level above (the scoped `--thematiq-global-*` fallbacks are
 * computed at :root, so resolving against the parent keeps them finite).
 *
 * @param {Record<string, string>} parent The level above.
 * @param {Record<string, string>} declared The level's own declarations.
 * @return {Record<string, string>} The level's variables.
 */
function level(parent, declared) {
	const own = Object.entries(declared).filter(([name]) => name.startsWith('--'))
	// First against the level above, then once more so a declaration that
	// reads another one redeclared on the same element sees that one, as
	// var() does. A self-reference keeps the level above's value.
	const first = { ...parent }
	for (const [name, value] of own) {
		first[name] = resolve(value, parent)
	}
	const out = { ...first }
	for (const [name, value] of own) {
		out[name] = resolve(value, { ...first, [name]: parent[name] })
	}
	return out
}

/**
 * What a strip paints, against what the content surface paints, in one environment.
 *
 * @param {object} set The token-sets.json entry.
 * @param {object} env The environment.
 * @return {string[]} The mismatches.
 */
function mismatches(set, env) {
	const files = bundle(set)
	// Nextcloud declares the main background on body; nldesign never does.
	const declared = {
		'--color-main-background': env.page,
		...cascade(files, env, (s) => reachesBody(s, env)),
	}
	// Custom properties are computed where they are declared: the captured
	// `--thematiq-global-*` values hold the body's value, not the content's.
	const body = {}
	for (const [name, value] of Object.entries(declared)) {
		try {
			body[name] = resolve(value, declared)
		} catch {
			body[name] = value
		}
	}
	const content = level(
		body,
		cascade(files, env, (s) => s.trim() === CONTENT),
	)
	const surface = resolve('var(--color-main-background)', content)
	// The content's children get the captured global back.
	const child = level(
		content,
		cascade(files, env, (s) =>
			/^:where\(.*#app-content-vue.*\) > \*$/.test(s.trim()),
		),
	)
	const out = []
	// The collapsed README fades into the surface, not into white.
	const fade = cascade(
		files,
		env,
		(s) =>
			s.trim() === CONTENT + ' #rich-workspace:not(.focus):not(.empty)::after',
	)['background-image']
	const fadeTo = fade === undefined ? 'var(--color-main-background)' : fade
	if (!resolve(fadeTo, child).toLowerCase().includes(surface.toLowerCase())) {
		out.push(
			`#rich-workspace fade: ${resolve(fadeTo, child)} on the surface ${surface}`,
		)
	}
	for (const strip of STRIPS) {
		const own = cascade(files, env, (s) => s.trim() === CONTENT + ' ' + strip)
		// Without a rule of ours, Nextcloud paints the strip with the main background.
		const paint = resolve(
			own['background-color'] ?? 'var(--color-main-background)',
			child,
		)
		if (paint.toLowerCase() !== surface.toLowerCase()) {
			out.push(`${strip}: ${paint} on the surface ${surface}`)
		}
	}
	return out
}

describe('the Files table header and footer sit on the content surface (thematiq#1061)', () => {
	it('covers the nldesign sets, Zuiddrecht among them', () => {
		expect(SETS.length).toBeGreaterThan(40)
		expect(SETS.map((s) => s.id)).toContain('zuiddrecht')
	})

	for (const [envName, env] of Object.entries(ENVIRONMENTS)) {
		it(`paints them with the surface for every set in the ${envName}`, () => {
			const all = {}
			for (const set of SETS) {
				if (
					env.themes !== 'light'
					&& !exists(`css/tokens/dark/${set.id}.css`)
				) {
					continue
				}
				const m = mismatches(set, env)
				if (m.length > 0) {
					all[set.id] = m
				}
			}
			expect(all).toEqual({})
		})
	}
})
