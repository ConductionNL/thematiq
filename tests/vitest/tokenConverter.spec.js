/**
 * The theme converter's contract, on the JavaScript runtime (nlds-theme-converter task 3.9).
 *
 * Local fixtures only (design decision 3): tests/Unit/fixtures/converter/ holds a built theme
 * stylesheet, a raw upstream dump and a Style Dictionary tree, plus the expectation each one
 * converts to. TokenSetConverterParityTest holds the PHP runtime to the same expectations, so
 * this suite checking them here is what makes a drift fail both suites.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
 */

import { describe, expect, it } from 'vitest'
import { readFileSync } from 'fs'
import { join } from 'path'
import {
	FIXTURE_DIR,
	convertFixture,
	converter,
	converterFixtures,
	loadConverterContext,
	repoRoot,
	tableHash,
} from '../../scripts/lib/converter-context.mjs'

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

/**
 * A built theme block from a map of declarations.
 *
 * @param {Object<string,string>} tokens The custom properties.
 * @return {string} The stylesheet.
 */
function theme(tokens) {
	const body = Object.entries(tokens).map(([name, value]) => `\t${name}: ${value};`).join('\n')

	return `.demo-theme {\n${body}\n}\n`
}

const entry = (result, source) => result.report.find((item) => item.source === source)

describe('the parity fixtures', () => {
	it.each(converterFixtures().map((fixture) => [fixture.name, fixture]))(
		'%s converts to its committed expectation',
		(name, fixture) => {
			const expected = JSON.parse(readFileSync(join(FIXTURE_DIR, `${name}.expected.json`), 'utf8'))
			expect(convertFixture(fixture, context)).toEqual(expected)
		},
	)
})

describe('accepted inputs', () => {
	it('detects a class-scoped block as input A and resolves its var() chain', () => {
		const result = run(
			'.openwoo-theme { --utrecht-button-primary-action-background-color: var(--openwoo-color-primary); --openwoo-color-primary: #23845c; }',
			{ slug: 'openwoo' },
		)
		expect(result.inputKind).toBe('A')
		expect(result.decl['--nldesign-color-primary']).toBe('#23845c')
		expect(result.css).not.toContain('var(--openwoo-color-primary)')
	})

	it('refuses a DTCG document here and points at the server, which owns DesignTokensMapper', () => {
		expect(() => run('{"color": {"primary": {"$value": "#123456", "$type": "color"}}}')).toThrow(/server/)
	})

	it('detects a :root set of --nldesign-* names as input D and runs add-only', () => {
		const result = run(':root { --nldesign-color-primary: #1b3d6b; --nldesign-color-primary-text: #ffffff; }')
		expect(result.inputKind).toBe('D')
		expect(entry(result, '--nldesign-color-primary')).toMatchObject({ action: 'kept', reason: 'kept-existing-value' })
	})

	it('refuses unrecognised content with a 422 naming the accepted shapes', () => {
		let error = null
		try {
			run('this is not a theme at all')
		} catch (caught) {
			error = caught
		}
		expect(error).not.toBeNull()
		expect(error.code).toBe(422)
		expect(error.message).toMatch(/Design Tokens/)
		expect(error.message).toMatch(/tokens\.json/)
	})

	it('resolves a Style Dictionary alias instead of emitting its braces', () => {
		const result = run(JSON.stringify({
			brand: { red: { 500: { value: '#c8102e' } } },
			utrecht: { button: { 'primary-action': { 'background-color': { value: '{brand.red.500}' } } } },
		}))
		expect(result.inputKind).toBe('C')
		expect(result.decl['--nldesign-color-primary']).toBe('#c8102e')
		expect(result.manifestEntry.theming.primary_color).toBe('#c8102e')
		expect(result.css).not.toMatch(/\{brand/)
	})

	it('reports an alias to a leaf the document lacks as unresolved-var', () => {
		const result = run(JSON.stringify({
			brand: { red: { value: '#c8102e' } },
			utrecht: { link: { color: { value: '{brand.missing}' } } },
		}))
		expect(entry(result, '--utrecht-link-color')).toMatchObject({ action: 'skipped', reason: 'unresolved-var' })
	})
})

describe('output shape and provenance', () => {
	it('emits one flat :root in four sections with the provenance block', () => {
		const result = run(theme({
			'--demo-color-blue-40': '#1b3d6b',
			'--utrecht-button-primary-action-background-color': 'var(--demo-color-blue-40)',
		}), { slug: 'zwolle', sourceName: 'design-tokens.css' })
		expect(result.css.match(/:root\s*\{/g)).toHaveLength(1)
		expect(result.css).not.toMatch(/@media|@supports|@import/)
		expect(result.css).toContain('input kind:      A')
		expect(result.css).toContain('design-tokens.css')
		expect(result.css).toContain(`sha256:${context.tableHash.slice(0, 16)}`)
		expect(result.css).toMatch(/applied:\s+\d+/)
		expect(result.css).toMatch(/skipped:\s+\d+/)
	})

	it('hashes the table the way the PHP runtime does, over its raw bytes', () => {
		const raw = readFileSync(join(repoRoot, 'scripts/mapping/nlds-to-nextcloud.json'), 'utf8')
		expect(context.tableHash).toBe(tableHash(raw))
		expect(context.tableHash).toMatch(/^[0-9a-f]{64}$/)
	})

	it('re-prefixes a palette step found under the app vocabulary', () => {
		const result = run(':root { --nldesign-color-blue-40: #1b3d6b; --nldesign-color-primary: #1b3d6b; }', { slug: 'nijmegen' })
		expect(result.decl['--nijmegen-color-blue-40']).toBe('#1b3d6b')
		expect(result.decl['--nldesign-color-blue-40']).toBeUndefined()
		expect(entry(result, '--nldesign-color-blue-40')).toMatchObject({ action: 'adapted', reason: 'palette-reprefixed' })
	})

	it('does not emit a var() chain that leaves the input', () => {
		const result = run(theme({
			'--utrecht-button-primary-action-background-color': '#154273',
			'--utrecht-link-color': 'var(--some-foreign-token)',
		}))
		expect(entry(result, '--utrecht-link-color')).toMatchObject({ reason: 'unresolved-var' })
		expect(result.css).not.toContain('--some-foreign-token')
	})
})

describe('table-driven mapping', () => {
	it('takes the first matching source and names it', () => {
		const result = run(theme({
			'--utrecht-button-primary-action-background-color': '#154273',
			'--demo-color-primary': '#990000',
		}))
		expect(result.decl['--nldesign-color-primary']).toBe('#154273')
		expect(result.report.find((item) => item.target === '--nldesign-color-primary').source)
			.toBe('--utrecht-button-primary-action-background-color')
	})

	it('derives a missing hover colour by darken and reports it adapted', () => {
		const result = run(theme({ '--utrecht-button-primary-action-background-color': '#154273' }))
		const hover = result.report.find((item) => item.target === '--nldesign-color-primary-hover')
		expect(hover.action).toBe('adapted')
		expect(result.decl['--nldesign-color-primary-hover']).toBe(converter.darken('#154273', 0.1))
	})

	it('darkens a guarded colour until the pair passes 4.5:1', () => {
		const result = run(theme({
			'--utrecht-button-primary-action-background-color': '#7fb3e0',
			'--utrecht-button-primary-action-color': '#ffffff',
		}))
		const ratio = converter.ratio(
			converter.parseColor(result.decl['--nldesign-color-primary']),
			converter.parseColor(result.decl['--nldesign-color-primary-text']),
		)
		expect(ratio).toBeGreaterThanOrEqual(4.5)
		expect(result.report.some((item) => item.reason === 'contrast-adjusted')).toBe(true)
	})
})

describe('nothing is dropped silently', () => {
	const result = run(theme({
		'--utrecht-button-primary-action-background-color': '#154273',
		'--utrecht-page-max-inline-size': '1200px',
		'--utrecht-document-font-size': '18px',
		'--utrecht-button-padding-block-start': '12px',
		'--utrecht-accordion-button-background-color': '#eeeeee',
		'--utrecht-breadcrumb-nav-link-color': '#154273',
		'--utrecht-skip-link-background-color': '#ffffff',
		'--utrecht-page-background-color': '#f5f5f5',
		'--utrecht-page-header-background-image': 'url("https://example.com/header.png")',
		'--demo-made-up-thing': '3',
	}))

	it('refuses layout, type scale and clickable-area tokens with their reasons', () => {
		expect(entry(result, '--utrecht-page-max-inline-size')).toMatchObject({ action: 'skipped', reason: 'layout-fixed-by-nextcloud' })
		expect(entry(result, '--utrecht-document-font-size')).toMatchObject({ action: 'skipped', reason: 'typography-scale-locked' })
		expect(entry(result, '--utrecht-button-padding-block-start')).toMatchObject({ action: 'skipped', reason: 'clickable-area-locked' })
		expect(result.decl['--utrecht-page-max-inline-size']).toBeUndefined()
	})

	it('keeps component tokens Nextcloud does not have', () => {
		for (const name of ['--utrecht-accordion-button-background-color', '--utrecht-breadcrumb-nav-link-color', '--utrecht-skip-link-background-color']) {
			expect(entry(result, name)).toMatchObject({ action: 'kept', reason: 'kept-for-nlds-components' })
			expect(result.decl[name]).toBeDefined()
		}
	})

	it('routes the page background to core theming', () => {
		expect(result.manifestEntry.theming.background_color).toBe('#f5f5f5')
		expect(result.decl['--color-main-background']).toBeUndefined()
		expect(result.report.some((item) => item.reason === 'routed-to-core-theming')).toBe(true)
	})

	it('drops an external url() before anything sees it', () => {
		expect(entry(result, '--utrecht-page-header-background-image')).toMatchObject({ action: 'skipped', reason: 'external-url-blocked' })
		expect(result.css).not.toContain('example.com')
	})

	it('reports a token no rule knows as unmapped', () => {
		expect(entry(result, '--demo-made-up-thing').reason).toBe('unmapped')
	})

	it('accounts for every source token', () => {
		const counts = result.counts
		expect(counts.applied + counts.adapted + counts.kept + counts.skipped).toBe(result.report.length)
	})
})

describe('the reason codes and the table agree (task 2.4, a permanent gate)', () => {
	const reasons = context.table.reasons

	it('every reason the converter emits over every shipped set and fixture has copy in the table', () => {
		const emitted = new Set()
		const inputs = converterFixtures().map((fixture) => [fixture.slug, readFileSync(join(repoRoot, fixture.input), 'utf8')])
		const manifest = JSON.parse(readFileSync(join(repoRoot, 'token-sets.json'), 'utf8'))
		for (const set of manifest) {
			if (set.design_system !== 'nldesign') {
				continue
			}
			inputs.push([set.id, readFileSync(join(repoRoot, 'css/tokens', `${set.id}.css`), 'utf8')])
		}
		for (const [slug, content] of inputs) {
			for (const item of run(content, { slug, repairContrast: true }).report) {
				if (item.reason) {
					emitted.add(item.reason)
				}
			}
		}
		expect([...emitted].filter((reason) => reasons[reason] === undefined)).toEqual([])
	})

	it('every reason in the table has a producer in the table or in one of the two runtimes', () => {
		const sources = [
			JSON.stringify({ ...context.table, reasons: undefined }),
			readFileSync(join(repoRoot, 'js/lib/tokenConverter.js'), 'utf8'),
			readFileSync(join(repoRoot, 'lib/Service/TokenSetConverterService.php'), 'utf8'),
			readFileSync(join(repoRoot, 'lib/Service/DesignTokensMapper.php'), 'utf8'),
			readFileSync(join(repoRoot, 'lib/Service/ThematiqExportLayers.php'), 'utf8'),
		].join('\n')
		expect(Object.keys(reasons).filter((reason) => sources.includes(`'${reason}'`) === false && sources.includes(`"${reason}"`) === false))
			.toEqual([])
	})
})

describe('re-conversion never overwrites a chosen value', () => {
	it('a hand-authored shipped set keeps every value it declares', () => {
		const original = readFileSync(join(repoRoot, 'css/tokens/vng.css'), 'utf8')
		const before = {}
		for (const match of original.replace(/\/\*[\s\S]*?\*\//g, '').matchAll(/(--nldesign-[\w-]+)\s*:\s*([^;]+);/g)) {
			before[match[1]] = match[2].trim()
		}
		const result = run(original, { slug: 'vng' })
		expect(result.inputKind).toBe('D')
		for (const [name, value] of Object.entries(before)) {
			expect(result.decl[name], name).toBe(value)
			if (name === '--nldesign-logo-url') {
				continue // a logo stored as a file keeps its path through the logo step, not a report entry
			}
			expect(result.report.find((item) => item.source === name && item.target === name)).toMatchObject({ action: 'kept', reason: 'kept-existing-value' })
		}
	})
})
