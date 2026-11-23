/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workplace app sheets (css/workplace-apps/) draw Mail, Calendar and Deck
 * as the boards. They load in the light and in the dark, so they take every
 * colour from a variable; they are scoped to the app's content or the header,
 * so no other page pays for them; and they use no `:has()`, which makes the
 * browser re-check a rule on every change below it.
 *
 * @spec openspec/specs/workplace-layout/spec.md#requirement-the-light-layout-draws-mail-calendar-and-deck-as-the-boards
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { describe, expect, it } from 'vitest'

const DIR = path.resolve(__dirname, '../../css/workplace-apps')
const SHEETS = ['brand', 'common', 'mail', 'calendar', 'deck']
const ROOTS = [':is(#content, #content-vue)', 'body:not(#body-login) #header', '#openregister-mail-sidebar']
const COLOUR = /#[0-9a-f]{3,8}\b|\brgba?\(|\bhsla?\(/i

describe('workplace app sheets', () => {
	it('ships one sheet per app and the shared ones', () => {
		expect(fs.readdirSync(DIR).sort()).toEqual(SHEETS.map((s) => s + '.css').sort())
	})

	for (const sheet of SHEETS) {
		const root = postcss.parse(fs.readFileSync(path.join(DIR, sheet + '.css'), 'utf8'))

		it(`${sheet}: every rule starts at the content or the header`, () => {
			root.walkRules((rule) => {
				for (const selector of rule.selectors) {
					expect(ROOTS.some((r) => selector.trim().startsWith(r)), selector).toBe(true)
				}
			})
		})

		it(`${sheet}: no colour literal and no :has()`, () => {
			root.walkRules((rule) => {
				expect(rule.selector).not.toContain(':has(')
			})
			root.walkDecls((decl) => {
				expect(COLOUR.test(decl.value), `${decl.prop}: ${decl.value}`).toBe(false)
			})
		})
	}
})
