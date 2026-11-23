/**
 * The theme converter's logo step and its input guards, on the JavaScript
 * runtime: an inline logo becomes a file under img/logos/, a logo already in
 * that directory is pointed at, every other shape is reported and kept, and
 * a table without a rule list, a DTCG document or an input without custom
 * properties is refused.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/token-set-converter/spec.md
 */

import { describe, expect, it } from 'vitest'
import converter from '../../js/lib/tokenConverter.js'
import { loadConverterContext } from '../../scripts/lib/converter-context.mjs'

// The converter is imported here, not taken from converter-context.mjs: that one is loaded
// through Node's require, which coverage does not see.
const context = loadConverterContext()

/**
 * Convert content with the app's own table.
 *
 * @param {string} content The input.
 * @param {Object} [extra] Extra options.
 * @return {Object} The converter result plus `decl`, the emitted declarations by name.
 */
function run(content, extra = {}) {
	const result = converter.convert(content, {
		slug: 'demo',
		displayName: 'Demo',
		table: context.table,
		tableHash: context.tableHash,
		vocabulary: context.vocabulary,
		fonts: [],
		...extra,
	})
	const decl = {}
	for (const match of result.css.replace(/\/\*[\s\S]*?\*\//g, '').matchAll(/(--[\w-]+)\s*:\s*([^;]+);/g)) {
		decl[match[1]] = match[2].trim()
	}
	return { ...result, decl }
}

const SVG = '<svg xmlns="http://www.w3.org/2000/svg"/>'
const theme = (logo, extra = '') => `.demo-theme {\n\t--demo-color-primary: #00689e;\n\t--demo-logo-url: ${logo};\n${extra}}\n`
const logoReport = (result) => result.report.filter((item) => item.target === '--nldesign-logo-url')

describe('the logo step', () => {
	it('writes a base64 logo as a file, points the logo token at it, and rewrites the copies of the same artwork', () => {
		const uri = `url("data:image/svg+xml;base64,${Buffer.from(SVG).toString('base64')}")`
		const result = run(
			'.demo-theme {\n\t--demo-color-primary: #00689e;\n'
			+ `\t--conduction-logo-header-background-image: ${uri};\n`
			+ `\t--conduction-logo-footer-background-image: ${uri};\n}\n`,
		)

		expect(result.logoAsset).toEqual({ path: 'img/logos/demo.svg', contents: SVG })
		expect(result.decl['--nldesign-logo-url']).toBe("url('../../img/logos/demo.svg')")
		expect(result.decl['--conduction-logo-footer-background-image']).toBe('var(--nldesign-logo-url)')
		expect(result.css).not.toContain('base64')
		expect(result.manifestEntry.theming.logo).toBe('img/logos/demo.svg')
		expect(logoReport(result)).toEqual([
			{
				source: '--conduction-logo-header-background-image',
				target: '--nldesign-logo-url',
				action: 'applied',
				reason: 'logo-extracted',
				value: 'img/logos/demo.svg',
			},
		])
	})

	it('decodes a percent-encoded logo and names the file after the asset name it is given', () => {
		const result = run(theme('url("data:image/svg+xml,%3Csvg%2F%3E")'), { assetName: 'custom-demo' })
		expect(result.logoAsset).toEqual({ path: 'img/logos/custom-demo.svg', contents: '<svg/>' })
		expect(result.decl['--nldesign-logo-url']).toBe("url('../../img/logos/custom-demo.svg')")
	})

	it('keeps the bytes of a binary logo unchanged', () => {
		const bytes = String.fromCharCode(0x89, 0x50, 0x4e, 0x47, 0xff, 0x00)
		const result = run(theme(`url("data:image/png;base64,${Buffer.from(bytes, 'latin1').toString('base64')}")`))
		expect(result.logoAsset.path).toBe('img/logos/demo.png')
		expect(result.logoAsset.contents).toBe(bytes)
	})

	it('points at a logo that is already a file under img/logos/, without writing one', () => {
		const result = run(theme("url('../img/logos/zuiddrecht.svg')"))
		expect(result.logoAsset).toBeNull()
		expect(result.decl['--nldesign-logo-url']).toBe("url('../../img/logos/zuiddrecht.svg')")
	})

	it.each([
		['an image type the table does not take', 'url("data:image/bmp;base64,AAAA")'],
		['a payload that is not base64', 'url("data:image/png;base64,@@@")'],
		['an empty payload', 'url("data:image/svg+xml;base64,")'],
		['a file outside img/logos/', "url('../img/other/logo.svg')"],
		['a path that climbs out of img/logos/', "url('img/logos/../../secret.svg')"],
	])('reports %s as unsupported and keeps the declaration', (label, value) => {
		const result = run(theme(value))
		expect(result.logoAsset).toBeNull()
		expect(logoReport(result)).toEqual([
			expect.objectContaining({ source: '--demo-logo-url', action: 'skipped', reason: 'logo-format-unsupported', value }),
		])
		expect(result.decl['--nldesign-logo-url']).not.toBe(`url('../../${value}')`)
	})

	it('shortens a long unsupported value in the report', () => {
		const value = `url("data:image/bmp;base64,${'A'.repeat(200)}")`
		const entry = logoReport(run(theme(value)))[0]
		expect(entry.value).toHaveLength(118)
		expect(entry.value.endsWith('…')).toBe(true)
	})

	it.each([
		['none', 'none'],
		['url(none)', 'url(none)'],
	])('passes over a logo of %s without a report', (label, value) => {
		const result = run(theme(value))
		expect(result.logoAsset).toBeNull()
		expect(logoReport(result)).toEqual([])
	})

	it('refuses a logo over the size limit, and does nothing without a logo block in the table', () => {
		const uri = 'url("data:image/svg+xml,%3Csvg%2F%3E")'
		const small = { ...context.table, logo: { ...context.table.logo, maxBytes: 3 } }
		expect(run(theme(uri), { table: small }).logoAsset).toBeNull()

		const withoutLogo = { ...context.table }
		delete withoutLogo.logo
		const result = run(theme(uri), { table: withoutLogo })
		expect(result.logoAsset).toBeNull()
		expect(logoReport(result)).toEqual([])
	})

	it('takes the first source the table lists before a role match', () => {
		const result = run(
			'.demo-theme {\n\t--demo-color-primary: #00689e;\n'
			+ '\t--acme-logo-src: url("data:image/svg+xml,%3Cb%2F%3E");\n'
			+ '\t--utrecht-logo-image: url("data:image/svg+xml,%3Ca%2F%3E");\n}\n',
		)
		expect(result.logoAsset.contents).toBe('<a/>')
		expect(logoReport(result)[0].source).toBe('--utrecht-logo-image')
	})

	it('falls back to a declaration whose role is a logo when no listed source is there', () => {
		const result = run('.demo-theme {\n\t--demo-color-primary: #00689e;\n\t--acme-logo-src: url("data:image/svg+xml,%3Cb%2F%3E");\n}\n')
		expect(result.logoAsset.contents).toBe('<b/>')
		expect(logoReport(result)[0].source).toBe('--acme-logo-src')
	})
})

describe('the input guards', () => {
	it('needs a mapping table with a rule list', () => {
		expect(() => run('.demo-theme { --demo-color-primary: #00689e; }', { table: {} })).toThrow(
			expect.objectContaining({ code: 500, message: 'A decoded mapping table with a rule list is required.' }),
		)
	})

	it('sends a W3C Design Tokens document to the server', () => {
		const dtcg = JSON.stringify({ color: { primary: { $value: '#00689e', $type: 'color' } } })
		expect(() => run(dtcg)).toThrow(expect.objectContaining({ code: 422, message: expect.stringContaining('DesignTokensMapper') }))
	})

	it.each([
		['a block whose only property has no value', '.demo-theme { --x: ; }'],
		['a Style Dictionary tree without a scalar value', JSON.stringify({ color: { primary: { value: { a: 1 } } } })],
	])('refuses %s: no custom properties', (label, content) => {
		expect(() => run(content)).toThrow(expect.objectContaining({ code: 422, message: expect.stringContaining('No custom properties') }))
	})
})
