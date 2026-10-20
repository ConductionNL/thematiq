/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The stat tile's number keeps the library's size: the boards draw it at 28px
 * (DqDashboard, DqTermijnen, DqTeamwachtrij) and the board look sets that.
 * The set sets no font size of its own on it.
 *
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/zuiddrecht-token-set/spec.md
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { describe, expect, it } from 'vitest'

const css = fs.readFileSync(path.resolve(__dirname, '../../css/token-overrides/zuiddrecht.css'), 'utf8')

describe('zuiddrecht stat tile number', () => {
	it('leaves the number\'s size to the library', () => {
		const sizes = []
		postcss.parse(css).walkRules((rule) => {
			if (rule.selector.includes('.cn-stat-widget__value') || rule.selector.includes('.cn-kpi-card__value')) {
				rule.walkDecls(/^font(-size)?$/, (d) => sizes.push(d.value))
			}
		})
		expect(sizes).toEqual([])
	})
})
