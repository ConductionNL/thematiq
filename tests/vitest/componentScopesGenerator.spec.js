/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workplace component tokens, read from the generated stylesheet.
 *
 * css/component-scopes.css is loaded for every token set, so a rule added to
 * it reaches every instance. The promise that makes that safe is that a rule
 * whose token is unset resolves to the value the element had before the rule
 * existed. These tests hold the new rules to it by reading the generated
 * declarations: each must end in the captured global, or in the broader token
 * the element inherited before, and never in a literal.
 *
 * They also hold the three mapping fields the tokens need (`selfOnly`,
 * `fallback`, `alsoGlobals`) to what they are documented to emit.
 *
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/component-tokens/spec.md
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { describe, expect, it } from 'vitest'

const ROOT = path.resolve(__dirname, '../..')
const read = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8')

const MAPPING = JSON.parse(read('scripts/mapping/component-tokens.json'))
const SCOPES = postcss.parse(read('css/component-scopes.css'))

/** One line, single spaces: a generated value without its line breaks. */
const flat = (value) =>
	value.replace(/\s+/g, ' ').replace(/\( /g, '(').replace(/ \)/g, ')')

/**
 * The declarations of the rule whose selector list contains `selector`.
 *
 * @param {string} selector One selector of the list, as generated.
 * @return {Record<string, string>|null} Property to flattened value, or null when no rule has it.
 */
function ruleFor(selector) {
	let found = null
	SCOPES.walkRules((rule) => {
		if (
			found === null
			&& rule.selectors.map((s) => s.trim()).includes(selector)
		) {
			found = {}
			rule.walkDecls((d) => {
				found[d.prop] = flat(d.value)
			})
		}
	})
	return found
}

/**
 * A component's own redirect rule. Two components can share a selector (the
 * content card and the content surface both name `#app-content`), so the rule
 * is found by the playground specimen selector the generator appends, which
 * carries the component id and is unique.
 *
 * @param {string} id The component id in the mapping.
 * @return {Record<string, string>|null} Property to flattened value.
 */
function componentRule(id) {
	return ruleFor("[data-thematiq-component='" + id + "']")
}

/** The capture block: what `body` copies each global to. */
const CAPTURES = ruleFor('body')

/** The tokens this change adds, with the component that carries each. */
const NEW_TOKENS = {
	'--nldesign-component-navigation-active-color': 'navigation-active-entry',
	'--nldesign-component-navigation-badge-background-color': 'navigation-badge',
	'--nldesign-component-navigation-badge-color': 'navigation-badge',
	'--nldesign-component-content-card-shadow-color': 'content-card',
	'--nldesign-component-content-surface-background-color': 'content-surface',
	...Object.fromEntries(
		['info', 'success', 'warning', 'error'].flatMap((kind) => [
			[
				'--nldesign-component-status-badge-' + kind + '-background-color',
				'status-badge',
			],
			['--nldesign-component-status-badge-' + kind + '-color', 'status-badge'],
		]),
	),
}

describe('workplace component tokens: the mapping', () => {
	it('carries every new token under its component', () => {
		for (const [token, component] of Object.entries(NEW_TOKENS)) {
			expect(MAPPING.components[component], component).toBeDefined()
			expect(MAPPING.components[component].tokens[token], token).toBeDefined()
		}
	})

	it('declares none of them in a design-system stylesheet', () => {
		// A token that a stylesheet declared by default would stop being a
		// no-op for every set on that design system.
		const declared = []
		const dir = path.join(ROOT, 'css/systems')
		for (const system of fs.readdirSync(dir)) {
			for (const file of fs.readdirSync(path.join(dir, system))) {
				if (file.endsWith('.css') === false) {
					continue
				}
				const css = fs.readFileSync(path.join(dir, system, file), 'utf8')
				for (const token of Object.keys(NEW_TOKENS)) {
					if (new RegExp(token.replace(/-/g, '\\-') + '\\s*:').test(css)) {
						declared.push(system + '/' + file + ': ' + token)
					}
				}
			}
		}
		expect(declared).toEqual([])
	})
})

describe('workplace component tokens: an unset token changes nothing', () => {
	it('falls back to the captured global, or to the token it refines', () => {
		const wrong = []
		for (const [token, id] of Object.entries(NEW_TOKENS)) {
			const component = MAPPING.components[id]
			const entry = component.tokens[token]
			const rule = componentRule(id)
			const globals = [entry.global].concat(entry.alsoGlobals || [])
			for (const global of globals) {
				const capture = '--thematiq-global-' + global.replace(/^--/, '')
				const captured = 'var(' + capture + ')'
				const fallback =
					entry.fallback !== undefined && global === entry.global
						? 'var(' + entry.fallback + ', ' + captured + ')'
						: captured
				const expected = 'var(' + token + ', ' + fallback + ')'
				if (rule === null || rule[global] !== expected) {
					wrong.push(
						id
							+ ': '
							+ global
							+ ' = '
							+ (rule === null ? 'no rule' : rule[global]),
					)
				}
				// The capture exists, and reads the global itself (through its
				// settable token where it has one), not a literal.
				if (
					CAPTURES[capture] === undefined
					|| CAPTURES[capture].endsWith(
						'var('
							+ global
							+ ')'
							+ (CAPTURES[capture].startsWith('var(--nldesign-nc-')
								? ')'
								: ''),
					) === false
				) {
					wrong.push(
						id + ': capture ' + capture + ' = ' + CAPTURES[capture],
					)
				}
			}
		}
		expect(wrong).toEqual([])
	})

	it('writes no colour literal into any new rule', () => {
		const literals = []
		for (const id of new Set(Object.values(NEW_TOKENS))) {
			const rule = componentRule(id)
			for (const [prop, value] of Object.entries(rule)) {
				if (/#[0-9a-f]{3,8}\b|rgba?\(/i.test(value)) {
					literals.push(id + ': ' + prop + ' = ' + value)
				}
			}
		}
		expect(literals).toEqual([])
	})
})

describe('workplace component tokens: the badge is coloured in the navigation only', () => {
	it('scopes the redirect to a counter inside the app navigation', () => {
		const selectors = MAPPING.components['navigation-badge'].selectors
		expect(selectors.length).toBeGreaterThan(0)
		for (const selector of selectors) {
			expect(selector).toMatch(
				/^(#app-navigation|#app-navigation-vue|\.app-navigation) \.counter-bubble__counter$/,
			)
		}
	})

	it('leaves a counter elsewhere on the badge tokens it had', () => {
		const everywhere = ruleFor('.counter-bubble__counter')
		expect(everywhere['--color-primary-element']).toContain(
			'--nldesign-component-badge-background-color',
		)
		expect(everywhere['--color-primary-element-light']).toBeUndefined()
	})
})

describe('workplace component tokens: a surface token does not reach the cards', () => {
	const surface = MAPPING.components['content-surface']
	const token = '--nldesign-component-content-surface-background-color'

	it('redirects the main background on the content itself', () => {
		expect(surface.tokens[token].selfOnly).toBe(true)
		expect(componentRule('content-surface')['--color-main-background']).toBe(
			'var(' + token + ', var(--thematiq-global-color-main-background))',
		)
	})

	it("hands the captured global back to the content's children, with no specificity", () => {
		const restore = ruleFor(':where(' + surface.selectors.join(', ') + ') > *')
		expect(restore).toEqual({
			'--color-main-background':
				'var(--thematiq-global-color-main-background)',
		})
	})

	it('emits a restore rule for a selfOnly token only', () => {
		const restores = []
		SCOPES.walkRules((rule) => {
			if (/^:where\(.*\) > \*$/.test(rule.selector.trim())) {
				restores.push(rule.selector.trim())
			}
		})
		const selfOnly = Object.values(MAPPING.components).filter((component) =>
			Object.values(component.tokens).some((entry) => entry.selfOnly === true),
		)
		expect(restores.length).toBe(selfOnly.length)
		expect(selfOnly.length).toBe(1)
	})
})

describe('workplace component tokens: a refining token falls back to the token it refines', () => {
	const entry = MAPPING.components['navigation-active-entry']
	const token = '--nldesign-component-navigation-active-color'

	it('keeps the navigation label colour on the selected entry while the active colour is unset', () => {
		const rule = componentRule('navigation-active-entry')
		expect(rule['--color-main-text']).toBe(
			'var('
				+ token
				+ ', var(--nldesign-component-navigation-color, var(--thematiq-global-color-main-text)))',
		)
	})

	it('answers for the label wherever it is read', () => {
		expect(entry.tokens[token].alsoGlobals).toEqual([
			'--color-primary-element-text',
		])
		expect(
			componentRule('navigation-active-entry')['--color-primary-element-text'],
		).toBe(
			'var(' + token + ', var(--thematiq-global-color-primary-element-text))',
		)
	})

	it('matches the wash shape only, so the solid fill of Nextcloud 32 and 33 keeps its own label', () => {
		for (const selector of entry.selectors) {
			expect(selector).toContain(':not(.app-navigation-entry--legacy).active')
		}
	})

	it('uses the nested fallback and the extra globals nowhere else', () => {
		const users = Object.entries(MAPPING.components)
			.filter(([, component]) =>
				Object.values(component.tokens).some(
					(t) => t.fallback !== undefined || t.alsoGlobals !== undefined,
				),
			)
			.map(([id]) => id)
		expect(users).toEqual(['navigation-active-entry'])
	})
})
