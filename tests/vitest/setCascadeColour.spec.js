/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The colour reader of tests/vitest/support/setCascade.js: hex and opaque
 * hsl(), as the token sets write them (a mark background in hsl() left the
 * mark colour tests unable to measure).
 *
 * @spec openspec/changes/archive/2026-11-22-token-set-coverage-next-waves/tasks.md
 */

import { describe, expect, it } from 'vitest'
import { ratio, rgb } from './support/setCascade.js'

describe('the set cascade colour reader', () => {
	it('reads hex colours', () => {
		expect(rgb('#3669A5')).toEqual([54, 105, 165])
		expect(rgb('#fff')).toEqual([255, 255, 255])
	})

	it('reads opaque hsl() in both syntaxes', () => {
		expect(rgb('hsl(48 100% 60%)')).toEqual([255, 214, 51])
		expect(rgb('hsl(48, 100%, 60%)')).toEqual([255, 214, 51])
		expect(rgb('hsl(210deg 50% 43% / 1)')).toEqual([55, 110, 164])
		expect(ratio('hsl(0 0% 0%)', 'hsl(0 0% 100%)')).toBeCloseTo(21, 5)
	})

	it('refuses a colour it cannot measure', () => {
		expect(() => rgb('hsl(48 100% 60% / 0.5)')).toThrow(/not an opaque/)
		expect(() => rgb('red')).toThrow(/not an opaque/)
	})
})
