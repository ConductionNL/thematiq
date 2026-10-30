/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The paragraph token is forced outside the nextcloud-vue board look and a
 * weightless default inside it, so a board component's own <p> size (a 13px
 * note, a 14px subtitle) wins there and a plain paragraph still takes the
 * token.
 *
 * @spec openspec/specs/paragraph-token/spec.md
 */
import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'

const ROOT = path.resolve(__dirname, '..', '..')
const theme = postcss.parse(fs.readFileSync(path.join(ROOT, 'css/systems/nldesign/theme.css'), 'utf8'))
const PROPS = ['font-size', 'line-height', 'color', 'font-family', 'font-weight']

/**
 * The declarations of the rule with exactly this selector.
 *
 * @param {string} selector The selector.
 * @return {Array<import('postcss').Declaration>} The declarations.
 */
function decls(selector) {
	const out = []
	theme.walkRules((rule) => {
		if (rule.selector.replace(/\s+/g, ' ').trim() === selector) {
			rule.walkDecls((d) => out.push(d))
		}
	})
	return out
}

describe('the paragraph token and the board look', () => {
	it('forces no paragraph size on every <p>, only outside the board look', () => {
		expect(decls('p')).toEqual([])
		const outside = decls('p:not(.cn-look-board *)')
		expect(outside.map((d) => d.prop)).toEqual(PROPS)
		expect(outside.every((d) => d.important && d.value.startsWith('var(--nldesign-component-paragraph-'))).toBe(true)
	})

	it('gives a board-look paragraph the token at no weight and without !important', () => {
		const inside = decls(':where(.cn-look-board) :where(p)')
		expect(inside.map((d) => d.prop)).toEqual(PROPS)
		expect(inside.some((d) => d.important)).toBe(false)
		expect(inside.every((d) => d.value.startsWith('var(--nldesign-component-paragraph-'))).toBe(true)
	})
})
