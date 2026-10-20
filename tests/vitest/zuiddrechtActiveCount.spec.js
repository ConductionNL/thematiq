/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The count on the selected quick-filter chip is the library's: an opaque
 * soft-ink bubble on the dark chip (DqZaken, DqWerkbord). The set paints no
 * ground of its own there, which drew a 22% white wash over the board's
 * #3d4047.
 *
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/zuiddrecht-token-set/spec.md
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { describe, expect, it } from 'vitest'

const css = fs.readFileSync(path.resolve(__dirname, '../../css/token-overrides/zuiddrecht.css'), 'utf8')

describe('zuiddrecht selected quick-filter count', () => {
	it('leaves the count ground to the library', () => {
		const grounds = []
		postcss.parse(css).walkRules((rule) => {
			if (rule.selector.includes('.cn-quick-filter-bar__tab--active') && rule.selector.includes('.cn-quick-filter-bar__count')) {
				rule.walkDecls(/^background/, (d) => grounds.push(d.value))
			}
		})
		expect(grounds).toEqual([])
	})

	it('keeps the light ink on the selected chip', () => {
		expect(css).toContain('.cn-quick-filter-bar__tab--active')
		expect(css).toContain('color: var(--color-main-background) !important;')
	})
})
