/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * No darker strips inside the La Suite card (thematiq#1052).
 *
 * La Suite floats the app content as one card painted with its surface token,
 * `--lasuite--contextuals--background--surface--primary` (gray-000 in light,
 * gray-800 in dark). Inside it, the Files table header and footer and the
 * README editor paint `--color-main-background`, which REQ-CSS-007 leaves to
 * Nextcloud: #171717 in dark. Measured on Nextcloud 35 in La Suite dark: the
 * card was rgb(47, 48, 61), and `.files-list__thead`, `.files-list__tfoot` and
 * `#rich-workspace .text-editor` were rgb(23, 23, 23).
 *
 * The fix stays inside REQ-CSS-007: no reserved variable is touched. The
 * elements themselves take the card's own surface token, at a specificity
 * above the scoped rules that paint them (`.files-list[data-v-…]
 * .files-list__thead`, 0,3,0).
 *
 * @spec openspec/specs/dark-mode/spec.md
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import postcss from 'postcss'
import { describe, it, expect } from 'vitest'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const css = postcss.parse(
	fs.readFileSync(
		path.join(root, 'css/systems/lasuite/element-overrides.css'),
		'utf8',
	),
)

/** What the card is painted with: the rule that floats #app-content-vue. */
const CARD =
	'var(--lasuite--contextuals--background--surface--primary, var(--lasuite-color-gray-000))'

/** The elements measured as strips, by the class that paints them. */
const STRIPS = [
	'.files-list__thead',
	'.files-list__tfoot',
	'#rich-workspace .text-editor',
	'#rich-workspace .text-editor__main',
	'#rich-workspace .editor',
	'#rich-workspace .text-menubar',
	'#rich-workspace .ProseMirror',
]

/** The reserved variables REQ-CSS-007 leaves to Nextcloud. */
const RESERVED = [
	'--color-main-background',
	'--color-main-background-rgb',
	'--color-main-background-translucent',
	'--color-background-plain',
	'--background-invert-if-dark',
	'--background-invert-if-bright',
]

/**
 * Specificity of one selector.
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

const norm = (v) =>
	v.replace(/\s+/g, ' ').replace(/\( /g, '(').replace(/ \)/g, ')').trim()

describe('the La Suite card has one surface (thematiq#1052)', () => {
	it('paints the card with the surface token', () => {
		let card = null
		css.walkRules((rule) => {
			if (rule.selectors.includes('#app-content-vue')) {
				rule.walkDecls('background', (d) => {
					card = norm(d.value)
				})
			}
		})
		expect(card).toBe(norm(CARD))
	})

	for (const strip of STRIPS) {
		it(`paints ${strip} with the card surface, above the scoped rule`, () => {
			const found = []
			css.walkRules((rule) => {
				if (rule.parent.type !== 'root') {
					return
				}
				const selector = rule.selectors.find(
					(s) =>
						s.trim().endsWith(strip)
						&& s.includes('#app-content-vue')
						&& beats(specificity(s), [0, 3, 0]),
				)
				if (!selector) {
					return
				}
				rule.walkDecls('background-color', (d) => {
					found.push({ value: norm(d.value), important: d.important })
				})
			})
			expect(found).toContainEqual({ value: norm(CARD), important: true })
		})
	}

	for (const set of ['lasuite', 'frankendesk']) {
		it(`gives ${set}'s dark header cells no fill the light ones lack`, () => {
			// The lasuite bundle loads no nldesign defaults.css, so in light a
			// `th` takes component-scopes.css's `transparent`. The dark variant
			// must not invent a fill (it derived #23232f from defaults.css).
			const dark = postcss.parse(
				fs.readFileSync(
					path.join(root, `css/tokens/dark/${set}.css`),
					'utf8',
				),
			)
			const values = []
			dark.walkDecls(
				'--nldesign-component-table-header-background-color',
				(d) => {
					values.push(d.value.trim())
				},
			)
			expect(values.filter((v) => v !== 'transparent')).toEqual([])
		})
	}

	it('writes none of the variables REQ-CSS-007 leaves to Nextcloud', () => {
		const written = []
		css.walkDecls((d) => {
			if (RESERVED.includes(d.prop)) {
				written.push(`${d.parent.selector} ${d.prop}`)
			}
		})
		expect(written).toEqual([])
	})
})
