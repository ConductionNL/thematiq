/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The set holds the greeting heading at 32px (DqDashboard), but a heading with
 * the greeting on a line under it keeps its own 26px (LpVandaag).
 *
 * @spec openspec/specs/zuiddrecht-token-set/spec.md
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { describe, expect, it } from 'vitest'

const css = fs.readFileSync(
	path.resolve(__dirname, '../../css/token-overrides/zuiddrecht.css'),
	'utf8',
)

describe('zuiddrecht greeting heading', () => {
	it('sizes the heading at 32px everywhere but in greeting-line mode', () => {
		const selectors = []
		postcss.parse(css).walkRules((rule) => {
			if (rule.selector.includes('.cn-header-widget__title')) {
				rule.walkDecls('font-size', (d) => selectors.push(`${rule.selector} ${d.value}`))
			}
		})
		expect(selectors).toEqual([
			'#app-content-vue .cn-header-widget:not(.cn-header-widget--greeting-line) .cn-header-widget__title 32px',
		])
	})
})
