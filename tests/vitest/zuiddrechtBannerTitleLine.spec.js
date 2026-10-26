/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The attention banner's case title is one 20px line at the font's normal
 * line height (24px), as DqMijnWerk draws "Parkeervergunningen binnenstad".
 *
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/zuiddrecht-token-set/spec.md
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { describe, expect, it } from 'vitest'

const css = fs.readFileSync(path.resolve(__dirname, '../../css/token-overrides/zuiddrecht.css'), 'utf8')

describe('zuiddrecht banner title', () => {
	it('sets the title at the normal line height', () => {
		const heights = []
		postcss.parse(css).walkRules((rule) => {
			if (rule.selector.includes('.cn-banner-widget__title')) {
				rule.walkDecls('line-height', (d) => heights.push(d.value))
			}
		})
		expect(heights).toEqual(['normal'])
	})
})
