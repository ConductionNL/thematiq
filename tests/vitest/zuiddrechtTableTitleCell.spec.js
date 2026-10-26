/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The set's 14px table cell leaves the title cell alone: the boards draw a
 * row's title at 15px (DqZaken, DqZoekresultaten, DqPostvak) and the library's
 * board look sets that, but `#app-content-vue .cn-data-table tbody td` beat it
 * with its id (r30: search titles at 14px).
 *
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/zuiddrecht-token-set/spec.md
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { describe, expect, it } from 'vitest'

const css = fs.readFileSync(path.resolve(__dirname, '../../css/token-overrides/zuiddrecht.css'), 'utf8')

describe('zuiddrecht table cells', () => {
	it('sizes the cells at 14px but not the title cell', () => {
		const selectors = []
		postcss.parse(css).walkRules((rule) => {
			if (rule.selector.includes('.cn-data-table tbody td')) {
				rule.walkDecls('font-size', () => selectors.push(rule.selector))
			}
		})
		expect(selectors).toEqual(['#app-content-vue .cn-data-table tbody td:not(.cn-table-col--title)'])
	})
})
