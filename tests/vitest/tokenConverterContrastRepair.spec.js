/**
 * The converter repairs the contrast of a converted set (thematiq#993).
 *
 * The nightly sync left 24 upstream sets on their raw dump because their
 * conversion failed the token-set gate. Two causes, both pinned here:
 *   - colours written as hsl() were copied verbatim; nothing downstream can
 *     measure or invert them (dark input text stayed dark on a dark field);
 *   - brand and text colours that miss WCAG AA against what they are drawn on
 *     (the primary as a link on the page, the header title on the header, body
 *     text on the neutral badge) were kept as they came.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
 */

import { describe, expect, it } from 'vitest'
import { readFileSync } from 'fs'
import { join, resolve } from 'path'
import converter from '../../js/lib/tokenConverter.js'

const ROOT = resolve(__dirname, '../..')
const table = JSON.parse(
	readFileSync(join(ROOT, 'scripts/mapping/nlds-to-nextcloud.json'), 'utf8'),
)
const vocabulary = converter.vocabularyFrom([
	readFileSync(join(ROOT, 'css/systems/nldesign/defaults.css'), 'utf8'),
	readFileSync(join(ROOT, 'css/systems/nldesign/utrecht-bridge.css'), 'utf8'),
])

/**
 * Convert one built theme block and return the declarations by name.
 *
 * @param {Object<string,string>} tokens Custom properties of the theme.
 * @return {Object} `{decl, result}`.
 */
function convert(tokens) {
	const body = Object.entries(tokens)
		.map(([name, value]) => `\t${name}: ${value};`)
		.join('\n')
	const result = converter.convert(`.demo-theme {\n${body}\n}\n`, {
		slug: 'demo',
		displayName: 'Demo',
		table,
		vocabulary,
		fonts: [],
		repairContrast: true,
	})
	const decl = {}
	for (const match of result.css
		.replace(/\/\*[\s\S]*?\*\//g, '')
		.matchAll(/(--[\w-]+)\s*:\s*([^;]+);/g)) {
		decl[match[1]] = match[2].trim()
	}
	return { decl, result }
}

const contrast = (a, b) =>
	converter.ratio(converter.parseColor(a), converter.parseColor(b))

describe('colour literals', () => {
	it('writes an hsl() colour as hex, in every layer', () => {
		const { decl } = convert({
			'--utrecht-button-primary-action-background-color': '#154273',
			'--utrecht-textbox-color': 'hsl(206 100% 16%)',
			'--demo-color-grey-11': 'hsl(60, 4%, 11%)',
		})
		expect(decl['--utrecht-textbox-color']).toBe('#002e52')
		expect(decl['--demo-color-grey-11']).toBe('#1d1d1b')
	})

	it('keeps the alpha of hsla() and of an 8-digit hex as rgba()', () => {
		const { decl } = convert({
			'--utrecht-button-primary-action-background-color': '#154273',
			'--demo-color-veil': 'hsla(0deg 0% 0% / 50%)',
			'--demo-color-tint': '#012c9d99',
		})
		expect(decl['--demo-color-veil']).toBe('rgba(0, 0, 0, 0.5)')
		expect(decl['--demo-color-tint']).toBe('rgba(1, 44, 157, 0.6)')
	})

	it('writes a basic named colour as hex, but never transparent', () => {
		expect(converter.normaliseColour('white')).toBe('#ffffff')
		expect(converter.normaliseColour('Grey')).toBe('#808080')
		expect(converter.normaliseColour('transparent')).toBe(null)
	})

	it('leaves a value that is not a single colour alone', () => {
		const { decl } = convert({
			'--utrecht-button-primary-action-background-color': '#154273',
			'--utrecht-button-box-shadow': 'inset 0 -4px hsl(0 0% 0%)',
		})
		expect(decl['--utrecht-button-box-shadow']).toBe('inset 0 -4px hsl(0 0% 0%)')
	})
})

describe('contrast repair', () => {
	it('leaves the colours alone unless the caller asks for the repair (an admin upload)', () => {
		const result = converter.convert(
			'.demo-theme {\n\t--utrecht-button-primary-action-background-color: #f0b800;\n}\n',
			{
				slug: 'demo',
				displayName: 'Demo',
				table,
				vocabulary,
				fonts: [],
			},
		)
		expect(result.css).toContain('--nldesign-color-primary: #f0b800;')
	})

	it('darkens a light primary until it reads as a link on the page, keeping its hue', () => {
		const { decl, result } = convert({
			'--utrecht-button-primary-action-background-color': '#f0b800',
		})
		const primary = decl['--nldesign-color-primary']
		expect(contrast(primary, '#ffffff')).toBeGreaterThanOrEqual(4.5)
		expect(
			contrast(decl['--nldesign-color-primary-text'], primary),
		).toBeGreaterThanOrEqual(4.5)
		// Same hue family: still a yellow-brown, not a grey or a blue.
		const [r, g, b] = converter.parseColor(primary)
		expect(r).toBeGreaterThan(g)
		expect(g).toBeGreaterThan(b)
		expect(result.manifestEntry.theming.primary_color).toBe(primary)
		expect(
			result.report.some(
				(entry) =>
					entry.target === '--nldesign-color-primary'
					&& entry.reason === 'contrast-repaired',
			),
		).toBe(true)
	})

	it('changes a colour that already passes not at all', () => {
		const { decl } = convert({
			'--utrecht-button-primary-action-background-color': '#154273',
			'--utrecht-button-primary-action-color': '#ffffff',
		})
		expect(decl['--nldesign-color-primary']).toBe('#154273')
		expect(decl['--nldesign-color-primary-text']).toBe('#ffffff')
	})

	it('makes the minimal change: just past the threshold, not to black', () => {
		const { decl } = convert({
			'--utrecht-button-primary-action-background-color': '#4a90e2',
		})
		const value = contrast(decl['--nldesign-color-primary'], '#ffffff')
		expect(value).toBeGreaterThanOrEqual(4.5)
		expect(value).toBeLessThan(4.7)
	})

	it('flips a header title that cannot be read on its header', () => {
		const { decl } = convert({
			'--utrecht-button-primary-action-background-color': '#154273',
			'--utrecht-page-header-background-color': '#ffd200',
			'--utrecht-page-header-color': '#ffffff',
		})
		expect(
			contrast(
				decl['--nldesign-color-header-text'],
				decl['--nldesign-color-header-background'],
			),
		).toBeGreaterThanOrEqual(4.5)
	})

	it('measures a translucent header over the page', () => {
		const { decl } = convert({
			'--utrecht-button-primary-action-background-color': '#154273',
			'--utrecht-page-header-background-color': '#012c9d99',
			'--utrecht-page-header-color': '#ffffff',
		})
		// #012c9d at 60% over white is #678bc4: white on it is 3.4:1, so the title must change.
		expect(decl['--nldesign-color-header-text']).not.toBe('#ffffff')
	})

	it('darkens body text that is too light for the neutral badge', () => {
		const { decl } = convert({
			'--utrecht-button-primary-action-background-color': '#154273',
			'--utrecht-document-color': '#8a8a8a',
		})
		expect(
			contrast(decl['--nldesign-color-text'], '#ffffff'),
		).toBeGreaterThanOrEqual(4.5)
		expect(
			contrast(
				decl['--nldesign-color-text'],
				decl['--nldesign-color-background-dark'],
			),
		).toBeGreaterThanOrEqual(4.5)
	})

	it('repairs the link and its hover on the page', () => {
		const { decl } = convert({
			'--utrecht-button-primary-action-background-color': '#154273',
			'--utrecht-link-color': '#5aa0ff',
			'--utrecht-link-hover-color': '#7fb5ff',
		})
		expect(
			contrast(decl['--nldesign-color-link'], '#ffffff'),
		).toBeGreaterThanOrEqual(4.5)
		expect(
			contrast(decl['--nldesign-color-link-hover'], '#ffffff'),
		).toBeGreaterThanOrEqual(4.5)
	})
})

describe('fills and values that are not colours', () => {
	it('lightens a mid-tone neutral fill instead of making body text unreadable on the page', () => {
		// drechterland: the table header (the neutral fill) is #1b7298.
		const { decl } = convert({
			'--utrecht-button-primary-action-background-color': '#154273',
			'--utrecht-document-color': '#222626',
			'--utrecht-table-header-background-color': '#1b7298',
		})
		const text = decl['--nldesign-color-text']
		expect(contrast(text, '#ffffff')).toBeGreaterThanOrEqual(4.5)
		expect(
			contrast(text, decl['--nldesign-color-background-dark']),
		).toBeGreaterThanOrEqual(4.5)
		expect(decl['--nldesign-color-background-dark']).not.toBe('#1b7298')
	})

	it('gives a transparent neutral fill its default', () => {
		const { decl, result } = convert({
			'--utrecht-button-primary-action-background-color': '#154273',
			'--utrecht-table-header-background-color': 'transparent',
		})
		expect(decl['--nldesign-color-background-dark']).toBe('#e0e1e2')
		expect(result.report.some((entry) => entry.reason === 'not-a-colour')).toBe(
			true,
		)
	})

	it('measures a header without its own background on the primary, as the bridge draws it', () => {
		const { decl } = convert({
			'--utrecht-button-primary-action-background-color': '#154273',
			'--utrecht-link-color': '#44ad34',
		})
		// The header text falls back to the link colour; the header to the primary.
		expect(
			contrast(
				decl['--nldesign-color-header-text'],
				decl['--nldesign-color-primary'],
			),
		).toBeGreaterThanOrEqual(4.5)
	})
})

describe('parity fixture', () => {
	const fixture = JSON.parse(
		readFileSync(
			join(ROOT, 'tests/Unit/fixtures/contrast-repair/expected.json'),
			'utf8',
		),
	)

	// TokenSetConverterContrastRepairTest asserts the PHP converter emits the same.
	it.each(Object.keys(fixture))('%s', (name) => {
		const result = converter.convert(fixture[name].input, {
			slug: 'demo',
			displayName: 'Demo',
			table,
			vocabulary,
			fonts: [],
			repairContrast: true,
		})
		const decl = {}
		for (const match of result.css
			.replace(/\/\*[\s\S]*?\*\//g, '')
			.matchAll(/(--[\w-]+)\s*:\s*([^;]+);/g)) {
			decl[match[1]] = match[2].trim()
		}
		for (const [token, value] of Object.entries(fixture[name].expected)) {
			expect(decl[token], token).toBe(value)
		}
		expect(result.manifestEntry.theming.primary_color).toBe(
			fixture[name].primary_color,
		)
	})
})
