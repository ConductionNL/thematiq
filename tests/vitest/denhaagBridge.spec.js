/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Den Haag section of css/public-bridge.css
 * (openspec/changes/denhaag-component-tokens).
 *
 * These read the REAL files: the vendored pinned package CSS, the committed
 * bridge, the shipped token sets and the defaults. A stale bridge, an unmapped
 * property or a set losing its own value fails here.
 */
import { describe, expect, it } from 'vitest'
import { readFileSync, readdirSync, existsSync } from 'fs'
import { join } from 'path'
import {
	BEGIN,
	END,
	buildSection,
	declarations,
	placeSection,
	readProperties,
	resolve,
} from '../../scripts/generate-denhaag-bridge.mjs'

const root = join(__dirname, '..', '..')
const read = (rel) => readFileSync(join(root, rel), 'utf8')
const mappingText = read('scripts/mapping/denhaag-component-tokens.json')
const mapping = JSON.parse(mappingText)
const readSource = (file) => read(join(mapping.sources.directory, file))
const defaults = declarations(read('css/systems/nldesign/defaults.css'))
const bridge = read('css/public-bridge.css')

/** The declarations a portal sees: defaults, then the bridge, then the set. */
function cascade(setId) {
	const merged = new Map(defaults)
	for (const [k, v] of declarations(bridge)) merged.set(k, v)
	for (const [k, v] of declarations(read(`css/tokens/${setId}.css`))) merged.set(k, v)
	return merged
}

function value(setId, name) {
	const merged = cascade(setId)
	return resolve(merged.get(name), merged)
}

describe('the Den Haag section of the public bridge', () => {
	it('is what the mapping produces (no hand edits, not stale)', () => {
		const { section } = buildSection(mapping, mappingText, readSource, defaults)
		expect(placeSection(bridge, section)).toBe(bridge)
	})

	it('a hand edit inside the section is caught', () => {
		const edited = bridge.replace(
			/(--denhaag-case-card-title-color: )[^;]*/,
			'$1#ff0000',
		)
		expect(edited).not.toBe(bridge)
		const { section } = buildSection(mapping, mappingText, readSource, defaults)
		expect(placeSection(edited, section)).not.toBe(edited)
	})

	it('declares every property a pinned component reads without a fallback', () => {
		const declared = declarations(bridge.slice(bridge.indexOf(BEGIN), bridge.indexOf(END)))
		const missing = []
		for (const [name, version] of Object.entries(mapping.sources.components)) {
			for (const prop of readProperties(readSource(`${name}@${version}.css`)).bare) {
				if (declared.has(prop) === false) missing.push(`${name}: ${prop}`)
			}
		}
		expect(missing).toEqual([])
	})

	it('names no Den Haag colour literal: every colour follows the set', () => {
		const section = bridge.slice(bridge.indexOf(BEGIN), bridge.indexOf(END))
		const leaked = [...declarations(section)]
			.filter(([, v]) => /\bhsla?\(/i.test(v))
			.map(([k]) => k)
		expect(leaked).toEqual([])
	})

	it('refuses a Den Haag colour the mapping does not name', () => {
		const partial = structuredClone(mapping)
		delete partial.colours['--denhaag-case-card-title-color']
		expect(() => buildSection(partial, mappingText, readSource, defaults)).toThrow(
			/--denhaag-case-card-title-color/,
		)
	})

	it('vendors every pinned source it names', () => {
		const files = new Set(readdirSync(join(root, mapping.sources.directory)))
		for (const [name, version] of Object.entries({ ...mapping.sources.components, ...mapping.sources.tokens })) {
			expect(files.has(`${name}@${version}.css`), `${name}@${version}`).toBe(true)
		}
	})
})

describe('the cascade a portal sees', () => {
	it('a set with only the semantic layer paints a case card in its own colours', () => {
		const merged = cascade('denhaag')
		const own = (t) => resolve(merged.get(t), merged)
		expect(value('denhaag', '--denhaag-case-card-title-color')).toBe(own('--nldesign-color-text'))
		expect(value('denhaag', '--denhaag-case-card-subtitle-color')).toBe(own('--nldesign-color-text-muted'))
		expect(value('denhaag', '--denhaag-case-card-border-color')).toBe(own('--nldesign-color-border'))
	})

	it('the primary colour reaches the current step of a set without Den Haag values', () => {
		const own = declarations(read('css/tokens/tilburg.css'))
		expect([...own.keys()].some((k) => k.startsWith('--denhaag-'))).toBe(false)
		const merged = cascade('tilburg')
		expect(value('tilburg', '--denhaag-step-marker-current-background-color')).toBe(
			resolve(merged.get('--nldesign-color-primary'), merged),
		)
	})

	it('a set with its own step marker keeps it', () => {
		const own = declarations(read('css/tokens/example-basisschool.css'))
		expect(cascade('example-basisschool').get('--denhaag-step-marker-current-background-color')).toBe(
			own.get('--denhaag-step-marker-current-background-color'),
		)
	})

	it('rotterdam keeps its own process steps', () => {
		const own = declarations(read('css/tokens/rotterdam.css'))
		const merged = cascade('rotterdam')
		const names = [...own.keys()].filter((k) => k.startsWith('--denhaag-process-steps-'))
		expect(names.length).toBeGreaterThan(0)
		for (const name of names) {
			expect(merged.get(name), name).toBe(own.get(name))
		}
	})
})

describe('the documented coverage', () => {
	it('matches the shipped sets', () => {
		const sets = JSON.parse(read('token-sets.json'))
		let roleLayer = 0
		let nldesignOnly = 0
		let denhaagComponents = 0
		for (const set of sets) {
			const file = join(root, 'css', 'tokens', `${set.id}.css`)
			if (existsSync(file) === false) continue
			const css = readFileSync(file, 'utf8')
			const utrecht = (css.match(/--utrecht-[a-z0-9-]+\s*:/g) ?? []).length
			const nldesign = (css.match(/--nldesign-[a-z0-9-]+\s*:/g) ?? []).length
			const denhaag = (css.match(/--denhaag-(?!color-)[a-z0-9-]+\s*:/g) ?? []).length
			if (utrecht >= 100) roleLayer++
			if (utrecht === 0 && nldesign > 0) nldesignOnly++
			if (denhaag > 0) denhaagComponents++
		}
		const measured = `coverage: listed=${sets.length} roleLayer=${roleLayer} nldesignOnly=${nldesignOnly} denhaagComponents=${denhaagComponents}`
		expect(bridge, measured).toContain(measured)
	})
})
