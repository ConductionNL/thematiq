/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The primary button's ground, label, weight and the button padding are not
 * forced: no `!important` paints them in the nldesign layer, the label's spans
 * follow the button through --nldesign-color-on-surface, and core's
 * `input.primary` and `a.primary` take the primary scope like the others.
 *
 * @spec openspec/changes/primary-buttons-not-forced/specs/primary-buttons-not-forced/spec.md
 */
import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'

const ROOT = path.resolve(__dirname, '..', '..')
const read = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8')

describe('primary buttons are not forced', () => {
	const theme = postcss.parse(read('css/systems/nldesign/theme.css'))

	it('forces no ground, label, border, weight or corner on an enabled primary button', () => {
		const forced = []
		theme.walkRules((rule) => {
			// The login page is core's own page, styled by core's login sheet; its
			// primary button stays the set's, forced there on purpose.
			if (!/primary/.test(rule.selector) || /disabled/.test(rule.selector) || /#body-login|\.login-form/.test(rule.selector)) {
				return
			}
			rule.walkDecls((decl) => {
				if (decl.important && ['background-color', 'color', 'border-color', 'font-weight', 'border-radius'].includes(decl.prop)) {
					forced.push(rule.selector.replace(/\s+/g, ' ') + ' ' + decl.prop)
				}
			})
		})
		expect(forced).toEqual([])
	})

	it('forces no button padding', () => {
		const forced = []
		theme.walkDecls('padding', (decl) => {
			if (decl.important && /button/.test(decl.parent.selector)) {
				forced.push(decl.parent.selector.replace(/\s+/g, ' '))
			}
		})
		expect(forced).toEqual([])
	})

	it('hands the primary label colour to the spans through the on-surface colour', () => {
		expect(read('css/systems/nldesign/theme.css')).toMatch(/:where\(\.button-vue--vue-primary, \.button-vue--primary\)[^{]*\{\s*--nldesign-color-on-surface: var\(--color-primary-element-text\);/)
	})

	it('scopes core\'s input.primary and a.primary with the primary button', () => {
		const scopes = read('css/component-scopes.css')
		expect(scopes).toMatch(/\/\* primary-button \*\/[\s\S]*?input\.primary,\s*a\.primary,/)
	})
})
