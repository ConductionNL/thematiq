/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Zuiddrecht's table header row is the workplace boards' row: near-white,
 * muted labels at 600 (DqZaken). component-scopes.css hands the header colour
 * to --color-text-maxcontrast inside every table, so the header colour is
 * also the muted text of every table cell; it must be the set's muted grey,
 * not the body ink, and reach AA on the header, a white row and the hover.
 *
 * @spec openspec/changes/zuiddrecht-table-header-board-palette/specs/zuiddrecht-table-header-board-palette/spec.md
 */
import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const ROOT = path.resolve(__dirname, '..', '..')
const read = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '')

/**
 * The value a stylesheet gives a custom property, the first declaration.
 *
 * @param {string} css Stylesheet text without comments.
 * @param {string} prop The property.
 * @return {string|null} The value, or null.
 */
const valueOf = (css, prop) => {
	const m = css.match(new RegExp(`${prop}:\\s*([^;]+);`))
	return m ? m[1].trim() : null
}

/**
 * Relative luminance of a #rrggbb colour (WCAG 2.2).
 *
 * @param {string} hex The colour.
 * @return {number} The luminance.
 */
const luminance = (hex) => {
	const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255)
		.map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4))
	return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

/**
 * Contrast ratio of two #rrggbb colours.
 *
 * @param {string} a One colour.
 * @param {string} b The other.
 * @return {number} The ratio.
 */
const contrast = (a, b) => {
	const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x)
	return (hi + 0.05) / (lo + 0.05)
}

const light = read('css/tokens/zuiddrecht.css')
const dark = read('css/tokens/dark/zuiddrecht.css')

describe('Zuiddrecht table header palette', () => {
	it('draws the boards\' header row: near-white, muted, 600', () => {
		expect(valueOf(light, '--nldesign-component-table-header-background-color')).toBe('#FAFBFC')
		expect(valueOf(light, '--nldesign-component-table-header-color')).toBe(valueOf(light, '--nldesign-color-text-muted'))
		expect(valueOf(light, '--nldesign-component-table-header-font-weight')).toBe('600')
	})

	it('keeps the muted header colour at AA on the header, a white row and the hover', () => {
		const muted = valueOf(light, '--nldesign-component-table-header-color')
		for (const ground of ['#FAFBFC', '#FFFFFF', valueOf(light, '--nldesign-color-background-hover')]) {
			expect(contrast(muted, ground)).toBeGreaterThanOrEqual(4.5)
		}
	})

	it('keeps the generated dark header at AA', () => {
		const fg = valueOf(dark, '--nldesign-component-table-header-color')
		const bg = valueOf(dark, '--nldesign-component-table-header-background-color')
		expect(contrast(fg, bg)).toBeGreaterThanOrEqual(4.5)
	})
})
