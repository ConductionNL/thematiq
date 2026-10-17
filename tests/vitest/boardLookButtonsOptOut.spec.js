/**
 * @vitest-environment jsdom
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The button rules leave a nextcloud-vue board-look page's controls alone.
 *
 * A page drawn in the board look (`.cn-look-board`) draws its own controls:
 * folder tabs, filter chips, breadcrumb text and neutral header buttons. The
 * broad button rules force a weight, a corner, a border width and the
 * secondary colours with `!important`, which turned the chips into tabs, made
 * every tab bold and painted the header buttons in the primary colour. Read
 * from the stylesheets: every forcing rule is matched against a fixture with
 * jsdom. Controls prove the same rules still reach a button outside the look.
 *
 * @spec openspec/specs/css-architecture/spec.md#scenario-app-specific-exclusions
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import postcss from 'postcss'
import { beforeAll, describe, it, expect } from 'vitest'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const FILES = [
	'css/systems/nldesign/theme.css',
	'css/systems/nldesign/element-overrides.css',
]
const SHAPE = ['font-weight', 'border-radius', 'border-width']
const SECONDARY_INK = [
	'background-color',
	'color',
	'border-color',
	'--nldesign-color-on-surface',
]

/**
 * The selectors of every rule in the two stylesheets that sets one of `props`,
 * with `!important` unless the property is a custom property.
 *
 * @param {string[]} props The properties to look for.
 * @return {string[]} The selectors.
 */
function selectorsSetting(props) {
	const out = []
	for (const file of FILES) {
		postcss
			.parse(fs.readFileSync(path.join(root, file), 'utf8'))
			.walkRules((rule) => {
				if (
					rule.parent.type === 'atrule'
					&& /keyframes|media/.test(rule.parent.name)
				) {
					return
				}
				const sets = rule.nodes.some(
					(node) =>
						node.type === 'decl'
						&& props.includes(node.prop)
						&& (node.important || node.prop.startsWith('--')),
				)
				if (sets) {
					out.push(...rule.selectors.map((s) => s.trim()))
				}
			})
	}
	return out
}

/**
 * The selectors from `list` an element matches. A selector jsdom cannot parse
 * is skipped; the controls prove the button rules parse.
 *
 * @param {Element} el The element.
 * @param {string[]} list The selectors.
 * @return {string[]} The matching selectors.
 */
function matching(el, list) {
	return list.filter((selector) => {
		try {
			return el.matches(selector)
		} catch (e) {
			return false
		}
	})
}

describe('the button rules and the board look', () => {
	beforeAll(() => {
		document.body.innerHTML = `
			<div class="cn-look-board">
				<button id="chip" type="button" class="cn-quick-filter-bar__tab">Alles</button>
				<button id="tab" type="button" class="cn-tabs__nav-item">Overzicht</button>
				<button id="board-secondary" class="button-vue button-vue--secondary"><span id="board-text" class="button-vue__text">Meer</span></button>
			</div>
			<button id="plain" type="button">Opslaan</button>
			<button id="secondary" class="button-vue button-vue--secondary"><span id="text" class="button-vue__text">Meer</span></button>`
	})

	it('forces no weight, corner or border width on a board-look control', () => {
		const shape = selectorsSetting(SHAPE)
		for (const id of ['chip', 'tab', 'board-secondary', 'board-text']) {
			expect(matching(document.getElementById(id), shape), id).toEqual([])
		}
	})

	it('still forces them on a button outside the board look', () => {
		const shape = selectorsSetting(SHAPE)
		for (const id of ['plain', 'secondary', 'text']) {
			expect(
				matching(document.getElementById(id), shape).length,
				id,
			).toBeGreaterThan(0)
		}
	})

	it('paints no secondary-button colours on a board-look secondary button', () => {
		const ink = selectorsSetting(SECONDARY_INK).filter((s) =>
			/secondary/.test(s),
		)
		expect(matching(document.getElementById('board-secondary'), ink)).toEqual([])
		expect(
			matching(document.getElementById('secondary'), ink).length,
		).toBeGreaterThan(0)
	})
})
