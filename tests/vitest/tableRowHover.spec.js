/**
 * @vitest-environment jsdom
 *
 * The nldesign row hover greys only a data table's rows: a table with a head
 * row, no nested tables and no presentation role. A layout grid drawn as a
 * table (Calendar's week, where one row holds the whole week) keeps its own
 * colours.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/css-architecture/spec.md
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { describe, expect, it } from 'vitest'

const ROOT = path.resolve(__dirname, '../..')
const sheet = postcss.parse(
	fs.readFileSync(path.join(ROOT, 'css/systems/nldesign/theme.css'), 'utf8'),
)

const hoverRules = []
sheet.walkRules((rule) => {
	if (/tr:hover/.test(rule.selector)) {
		rule.walkDecls('background-color', (decl) => {
			if (/table-row-hover/.test(decl.value)) {
				hoverRules.push(rule.selector.replace(/\s+/g, ' '))
			}
		})
	}
})

describe('the table row hover', () => {
	it('is one rule, scoped to data tables', () => {
		expect(hoverRules).toEqual([
			":where(table:has(> thead):not([role='presentation']):not(:has(table))) > tbody > tr:hover > td",
		])
	})

	it('never reaches a bare `tr:hover td` again', () => {
		expect(hoverRules.some((selector) => /^tr:hover td$/.test(selector))).toBe(
			false,
		)
	})
})
