/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * NcButton draws the design system's focus ring, not Nextcloud's (#1050).
 *
 * Every design system paints its ring on `*:focus-visible` with `!important`.
 * NcButton ships a scoped rule of its own,
 * `.button-vue[data-v-…]:focus-visible { outline: 2px solid
 * var(--color-main-text) !important; box-shadow: 0 0 0 4px
 * var(--color-main-background) !important }`, at specificity 0,3,0. Both are
 * `!important`, so the higher specificity wins and every button kept
 * Nextcloud's 2px ring in the main text colour. In dark high contrast that is
 * the white border colour, which the high-contrast spec forbids (#1023).
 *
 * So every rule that draws a ring on `*:focus-visible` must also list a
 * `.button-vue` selector that beats 0,3,0, with the same declarations. Read
 * from the stylesheets themselves, inside every at-rule branch, so the
 * prefers-contrast and forced-colors rings are held too.
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import postcss from 'postcss'
import { describe, it, expect } from 'vitest'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')

/** NcButton's scoped ring: `.button-vue[data-v-…]:focus-visible`. */
const NC_BUTTON_RING_SPECIFICITY = [0, 3, 0]

/** Each design system's ring stylesheets (cunningham shares lasuite's bridge). */
const RING_STYLESHEETS = {
	nldesign: ['css/systems/nldesign/theme.css'],
	'summer-breeze': ['css/systems/summer-breeze/theme.css'],
	'high-contrast': [
		'css/systems/high-contrast/theme.css',
		'css/systems/high-contrast/element-overrides.css',
	],
	'lasuite and cunningham': ['css/systems/lasuite/bridge.css'],
}

/** The properties that make up a ring. */
const RING_PROPS = ['outline', 'outline-width', 'outline-color', 'box-shadow']

/**
 * Specificity of one simple selector chain.
 *
 * @param {string} selector The selector.
 * @return {number[]} [ids, classes, types].
 */
function specificity(selector) {
	const ids = (selector.match(/#[\w-]+/g) ?? []).length
	const classes = (selector.match(/\.[\w-]+|\[[^\]]+\]|:(?!:)[\w-]+/g) ?? [])
		.length
	const types = (
		selector
			.replace(/[#.][\w-]+|\[[^\]]+\]|:[\w-]+/g, ' ')
			.match(/\b[a-z][\w-]*/gi) ?? []
	).length
	return [ids, classes, types]
}

/**
 * Whether specificity a beats b.
 *
 * @param {number[]} a First.
 * @param {number[]} b Second.
 * @return {boolean} True when a wins.
 */
function beats(a, b) {
	for (let i = 0; i < 3; i++) {
		if (a[i] !== b[i]) {
			return a[i] > b[i]
		}
	}
	return false
}

/**
 * The at-rule chain a rule sits in, for messages.
 *
 * @param {import('postcss').Rule} rule The rule.
 * @return {string} For example `@media (prefers-contrast: more)`.
 */
function context(rule) {
	const parts = []
	for (let p = rule.parent; p && p.type === 'atrule'; p = p.parent) {
		parts.unshift(`@${p.name} ${p.params}`)
	}
	return parts.join(' ') || 'top level'
}

/**
 * Every rule that draws a ring on `*:focus-visible`.
 *
 * @param {string} file The stylesheet.
 * @return {import('postcss').Rule[]} The rules.
 */
function ringRules(file) {
	const out = []
	postcss
		.parse(fs.readFileSync(path.join(root, file), 'utf8'))
		.walkRules((rule) => {
			if (!rule.selectors.some((s) => s.trim() === '*:focus-visible')) {
				return
			}
			let draws = false
			rule.walkDecls((d) => {
				if (RING_PROPS.includes(d.prop) && d.important) {
					draws = true
				}
			})
			if (draws) {
				out.push(rule)
			}
		})
	return out
}

describe('NcButton draws the design system focus ring (#1050)', () => {
	for (const [system, files] of Object.entries(RING_STYLESHEETS)) {
		describe(system, () => {
			const rules = files.flatMap((file) =>
				ringRules(file).map((rule) => ({ file, rule })),
			)

			it('has a ring on *:focus-visible to carry over', () => {
				expect(rules.length).toBeGreaterThan(0)
			})

			for (const { file, rule } of rules) {
				it(`${file} (${context(rule)}) lists a .button-vue selector that beats NcButton's scoped ring`, () => {
					const winners = rule.selectors.filter(
						(s) =>
							/\.button-vue\b/.test(s)
							&& /:focus-visible\b/.test(s)
							&& beats(specificity(s), NC_BUTTON_RING_SPECIFICITY),
					)
					expect(
						winners,
						`selectors: ${rule.selectors.join(', ')}`,
					).not.toEqual([])
				})
			}
		})
	}

	it('the specificity helper ranks NcButton scoped rule at 0,3,0', () => {
		expect(specificity('.button-vue[data-v-47ce59a3]:focus-visible')).toEqual(
			NC_BUTTON_RING_SPECIFICITY,
		)
		expect(beats(specificity('*:focus-visible'), [0, 3, 0])).toBe(false)
	})
})
