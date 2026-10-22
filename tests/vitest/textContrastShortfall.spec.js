/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The token editor warns when a page text colour falls under 4.5:1 on the
 * page background (WCAG 1.4.3). #75758a on white measures 4.49:1: the value
 * that shipped as dossiq's muted text.
 */

import { describe, it, expect } from 'vitest'
import tokenTransforms from '../../js/lib/tokenTransforms.js'

const { textContrastShortfall, TEXT_CONTRAST_TOKENS } = tokenTransforms

describe('textContrastShortfall', () => {
	it('names the page text tokens it measures', () => {
		expect(TEXT_CONTRAST_TOKENS).toEqual(['--color-main-text', '--color-text-maxcontrast', '--color-text-lighter'])
	})

	it('reports the ratio of muted text under 4.5:1 on white', () => {
		expect(textContrastShortfall('--color-text-maxcontrast', '#75758a', '#ffffff')).toBe(4.49)
	})

	it('reports nothing for readable text', () => {
		expect(textContrastShortfall('--color-text-maxcontrast', '#5E6168', '#ffffff')).toBeNull()
	})

	it('measures against the given background', () => {
		expect(textContrastShortfall('--color-text-lighter', '#6b6b80', '#c5c6d5')).not.toBeNull()
	})

	it('falls back to white when the background is not a colour', () => {
		expect(textContrastShortfall('--color-main-text', '#777777', 'var(--x)')).toBe(4.47)
	})

	it('ignores other tokens and unreadable values', () => {
		expect(textContrastShortfall('--color-primary', '#75758a', '#ffffff')).toBeNull()
		expect(textContrastShortfall('--color-text-maxcontrast', 'var(--grey)', '#ffffff')).toBeNull()
	})
})
