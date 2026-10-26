/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The set keeps a counter's label at 400, but not on the tile with its icon
 * in a circle at the end, which the boards set at 600 (DqDashboard,
 * DqMijnTeam) and the library styles so.
 *
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/zuiddrecht-token-set/spec.md
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { describe, expect, it } from 'vitest'

const css = fs.readFileSync(path.resolve(__dirname, '../../css/token-overrides/zuiddrecht.css'), 'utf8')

describe('zuiddrecht counter label weight', () => {
	it('sets 400 only on tiles without the end badge', () => {
		const weighted = []
		postcss.parse(css).walkRules((rule) => {
			if (rule.selector.includes('.cn-kpi-card__title')) {
				rule.walkDecls('font-weight', (d) => weighted.push(rule.selector))
			}
		})
		expect(weighted.length).toBeGreaterThan(0)
		for (const selector of weighted) {
			expect(selector).toContain(':not(:has(.cn-kpi-card__title--badge-end))')
		}
	})
})
