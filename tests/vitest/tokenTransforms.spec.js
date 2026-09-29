/**
 * SPDX-FileCopyrightText: 2026 Conduction / NL Design System Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for js/lib/tokenTransforms.js — the pure design-token / colour
 * transforms the admin settings page uses to derive preview palettes and
 * normalise colour-picker input. Exact-output assertions on every branch.
 */

import { describe, it, expect } from 'vitest'
import tokenTransforms from '../../js/lib/tokenTransforms.js'

const {
	darkenHex,
	getPreviewColors,
	normaliseColorForPicker,
	hexToRgbTriplet,
	designSystemLabel,
	groupDiagnosticsByReason,
} = tokenTransforms

describe('darkenHex', () => {
	it('darkens a #RRGGBB colour by the given fraction', () => {
		// 0xec = 236 → round(236 * 0.9) = 212 = 0xd4
		expect(darkenHex('#ec0000', 0.1)).toBe('#d40000')
	})

	it('returns black at fraction 1 and the same colour at fraction 0', () => {
		expect(darkenHex('#ffffff', 1)).toBe('#000000')
		expect(darkenHex('#1a2b3c', 0)).toBe('#1a2b3c')
	})

	it('clamps an out-of-range fraction into [0, 1]', () => {
		expect(darkenHex('#808080', 2)).toBe('#000000')
		expect(darkenHex('#808080', -1)).toBe('#808080')
	})

	it('returns the input unchanged for a non-#RRGGBB value', () => {
		expect(darkenHex('red', 0.1)).toBe('red')
		expect(darkenHex('#abc', 0.1)).toBe('#abc')
		expect(darkenHex('', 0.1)).toBe('')
	})
})

describe('getPreviewColors', () => {
	it('derives the palette from a token set primary_color', () => {
		const palette = getPreviewColors({ theming: { primary_color: '#0082c9' } })
		expect(palette.primary).toBe('#0082c9')
		expect(palette.primaryText).toBe('#ffffff')
		// hover is 10% darker
		expect(palette.primaryHover).toBe(darkenHex('#0082c9', 0.1))
	})

	it('falls back to a neutral dark when there is no theming metadata', () => {
		expect(getPreviewColors(null)).toEqual({
			primary: '#333333',
			primaryHover: darkenHex('#333333', 0.1),
			primaryText: '#ffffff',
		})
		expect(getPreviewColors({}).primary).toBe('#333333')
		expect(getPreviewColors({ theming: {} }).primary).toBe('#333333')
	})
})

describe('normaliseColorForPicker', () => {
	it('passes through a lower-cased #RRGGBB value', () => {
		expect(normaliseColorForPicker('#AABBCC')).toBe('#aabbcc')
	})

	it('expands a #RGB shorthand to #RRGGBB', () => {
		expect(normaliseColorForPicker('#abc')).toBe('#aabbcc')
		expect(normaliseColorForPicker('#F00')).toBe('#ff0000')
	})

	it('returns #000000 for empty / null / undefined', () => {
		expect(normaliseColorForPicker('')).toBe('#000000')
		expect(normaliseColorForPicker(null)).toBe('#000000')
		expect(normaliseColorForPicker(undefined)).toBe('#000000')
	})

	it('returns null for values needing browser resolution (named/rgb)', () => {
		// admin.js resolves these via a canvas; the pure helper signals "not pure".
		expect(normaliseColorForPicker('red')).toBeNull()
		expect(normaliseColorForPicker('rgb(1,2,3)')).toBeNull()
	})

	it('turns an r, g, b triplet into #RRGGBB, so an -rgb token has a swatch', () => {
		expect(normaliseColorForPicker('255, 243, 205')).toBe('#fff3cd')
		expect(normaliseColorForPicker('0,0,0')).toBe('#000000')
		expect(normaliseColorForPicker('300, 0, 0')).toBe('#ff0000')
	})
})

describe('hexToRgbTriplet', () => {
	it('writes a colour as the bare r, g, b triplet', () => {
		expect(hexToRgbTriplet('#fff3cd')).toBe('255, 243, 205')
		expect(hexToRgbTriplet('#abc')).toBe('170, 187, 204')
	})

	it('passes a triplet through in canonical form', () => {
		expect(hexToRgbTriplet('255,243,205')).toBe('255, 243, 205')
	})

	it('returns null for an empty value or one it cannot read', () => {
		expect(hexToRgbTriplet('')).toBeNull()
		expect(hexToRgbTriplet(null)).toBeNull()
		expect(hexToRgbTriplet('red')).toBeNull()
	})
})

describe('designSystemLabel', () => {
	it('maps known design-system ids', () => {
		expect(designSystemLabel('none')).toBe('Stock Nextcloud')
		expect(designSystemLabel('nldesign')).toBe('NL Design System')
	})

	it('returns the id itself for an unknown design system', () => {
		expect(designSystemLabel('amsterdam')).toBe('amsterdam')
	})
})

describe('groupDiagnosticsByReason', () => {
	it('groups entries by reason, alphabetically ordered', () => {
		const entries = [
			{ path: 'shadow.elevation-1', reason: 'unmapped-path' },
			{
				path: 'color.accent',
				reason: 'unsupported-color-space',
				detail: 'display-p3',
			},
			{ path: 'color.other', reason: 'unmapped-path' },
			{ path: 'a.x', reason: 'alias-cycle', detail: 'a.x -> b.y -> a.x' },
		]

		const groups = groupDiagnosticsByReason(entries)

		expect(groups.map((g) => g.reason)).toEqual([
			'alias-cycle',
			'unmapped-path',
			'unsupported-color-space',
		])
		expect(groups.find((g) => g.reason === 'unmapped-path').items).toHaveLength(
			2,
		)
		expect(
			groups
				.find((g) => g.reason === 'unmapped-path')
				.items.map((i) => i.path),
		).toEqual(['shadow.elevation-1', 'color.other'])
	})

	it('returns an empty array for an empty or missing list (no rendering when arrays are absent)', () => {
		expect(groupDiagnosticsByReason([])).toEqual([])
		expect(groupDiagnosticsByReason(undefined)).toEqual([])
		expect(groupDiagnosticsByReason(null)).toEqual([])
	})

	it('falls back to an "unknown" reason bucket for a malformed entry', () => {
		const groups = groupDiagnosticsByReason([{ path: 'x.y' }])
		expect(groups).toEqual([{ reason: 'unknown', items: [{ path: 'x.y' }] }])
	})
})
