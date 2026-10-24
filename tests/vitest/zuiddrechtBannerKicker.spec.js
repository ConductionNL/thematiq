/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The attention banner's kicker is untracked: the boards draw "Vandaag eerst"
 * at 13px bold in sentence case (DqMijnWerk, DqDashboard). The set sets no
 * letter spacing on it, so the board look's own kicker rule holds.
 *
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/zuiddrecht-token-set/spec.md
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { describe, expect, it } from 'vitest'

const css = fs.readFileSync(path.resolve(__dirname, '../../css/token-overrides/zuiddrecht.css'), 'utf8')

describe('zuiddrecht banner kicker', () => {
	it('sets no letter spacing on the kicker', () => {
		const spacing = []
		postcss.parse(css).walkRules((rule) => {
			if (rule.selector.includes('.cn-banner-widget__kicker')) {
				rule.walkDecls('letter-spacing', (d) => spacing.push(d.value))
			}
		})
		expect(spacing).toEqual([])
	})
})
