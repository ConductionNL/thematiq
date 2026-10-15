/**
 * The converter writes oklch() and oklab() as hex, and gives a theme without a
 * primary colour the set's own (Q-thematiq-10).
 *
 * Rijkshuisstijl 18 writes every colour as oklch(); the converter passed those
 * through, so the set's theming primary became an oklch() string and five
 * token-set tests failed. Zwolle's theme names no primary-action colour, so its
 * converted set had no primary, header or link colour.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/converter-oklch-and-manifest-primary/specs/token-set-converter/spec.md
 */

import { describe, expect, it } from 'vitest'
import { readFileSync } from 'fs'
import { join, resolve } from 'path'
import converter from '../../js/lib/tokenConverter.js'
import { vocabularyStylesheets } from '../../scripts/lib/converter-context.mjs'
import { withPrimary } from '../../scripts/convert-nlds-theme.mjs'

const ROOT = resolve(__dirname, '../..')
const table = JSON.parse(
	readFileSync(join(ROOT, 'scripts/mapping/nlds-to-nextcloud.json'), 'utf8'),
)
const vocabulary = converter.vocabularyFrom(vocabularyStylesheets())

describe('oklch() and oklab() become hex', () => {
	it.each([
		['oklch(62.8% 0.2577 29.23)', '#ff0000'],
		['oklch(45.2% 0.313 264.05)', '#0000ff'],
		['oklch(100% 0 0)', '#ffffff'],
		['oklch(0.5 0.5 30)', '#ff0000'],
		['oklch(50% 25% 120deg)', '#5c6b21'],
		['oklab(0.628 0.2249 0.1258)', '#ff0000'],
		['oklch(0% 0 none / 0.102)', 'rgba(0, 0, 0, 0.1)'],
	])('%s is %s', (value, expected) => {
		expect(converter.normaliseColour(value)).toBe(expected)
	})

	it('leaves a value it cannot read alone', () => {
		expect(converter.normaliseColour('oklch(bad 0 0)')).toBeNull()
		expect(converter.normaliseColour('oklch(from red l c h)')).toBeNull()
	})

	it('gives the set and its manifest a hex primary, and reports the original', () => {
		const result = converter.convert(
			'.demo-theme {\n'
				+ '\t--utrecht-button-primary-action-background-color: oklch(38.5% 0.1 250);\n'
				+ '\t--utrecht-button-primary-action-color: oklch(100% 0 0);\n'
				+ '}\n',
			{
				slug: 'demo',
				displayName: 'Demo',
				sourceName: 'demo.css',
				table,
				vocabulary,
				fonts: [],
			},
		)

		expect(result.manifestEntry.theming.primary_color).toBe('#0c4576')
		expect(result.css).toContain('--nldesign-color-primary: #0c4576;')
		expect(result.css).not.toMatch(/:\s*okl(ch|ab)\(/)
		expect(result.report).toContainEqual(
			expect.objectContaining({
				reason: 'colour-normalised',
				original: 'oklch(38.5% 0.1 250)',
				value: '#0c4576',
			}),
		)
	})
})

describe("a theme without a primary colour takes the set's own", () => {
	const css = '.zwolle-theme {\n\t--utrecht-document-color: #1a1a1a;\n}\n'
	const quiet = { slug: 'zwolle', primary: null, quiet: true }

	it('reads the committed manifest primary', () => {
		const manifest = JSON.parse(
			readFileSync(join(ROOT, 'token-sets.json'), 'utf8'),
		)
		const committed = manifest
			.find((entry) => entry.id === 'zwolle')
			.theming.primary_color.toLowerCase()

		expect(withPrimary(css, 'theme.css', quiet)).toContain(
			`--zwolle-color-primary: ${committed};`,
		)
	})

	it('takes --primary over the manifest', () => {
		expect(
			withPrimary(css, 'theme.css', { ...quiet, primary: '#123456' }),
		).toContain('--zwolle-color-primary: #123456;')
	})

	it('leaves a theme that has a primary-action colour alone', () => {
		const own =
			'.zwolle-theme {\n\t--utrecht-button-primary-action-background-color: #000000;\n}\n'
		expect(withPrimary(own, 'theme.css', quiet)).toBe(own)
	})

	it('leaves a tokens.json input alone', () => {
		expect(withPrimary('{}', 'tokens.json', quiet)).toBe('{}')
	})

	it('gives the converted set a primary, header and link colour', () => {
		const result = converter.convert(withPrimary(css, 'theme.css', quiet), {
			slug: 'zwolle',
			displayName: 'Gemeente Zwolle',
			sourceName: 'theme.css',
			table,
			vocabulary,
			fonts: [],
		})

		expect(result.css).toMatch(/--nldesign-color-primary: #[0-9a-f]{6};/)
		expect(result.css).toMatch(/--nldesign-color-link: #[0-9a-f]{6};/)
		expect(result.manifestEntry.theming.primary_color).toMatch(/^#[0-9a-f]{6}$/)
	})
})
