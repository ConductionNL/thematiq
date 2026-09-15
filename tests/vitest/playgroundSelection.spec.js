/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The component instrument's selection logic and its token-set export.
 *
 * Everything the instrument does to the DOM is judged by looking at it — that
 * is what it is for. These are the parts where looking would not tell you
 * whether they are right: which chips a tab offers, what a shared link
 * reopens, and what leaves the panel as a file.
 *
 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
 */

import { describe, it, expect } from 'vitest'
import * as fs from 'fs'
import * as path from 'path'
import playground from '../../js/playground.js'

const ROOT = path.resolve(__dirname, '../..')

const inventory = JSON.parse(
	fs.readFileSync(path.join(ROOT, 'js/playground/components.json'), 'utf8'),
)

/**
 * The required semantic vocabulary, read from the audit CLI rather than
 * restated here, so this test cannot disagree with the gate it appeals to.
 */
const REQUIRED_TOKENS = [
	...fs
		.readFileSync(path.join(ROOT, 'scripts/audit-token-sets.mjs'), 'utf8')
		.split('const REQUIRED_TOKENS = [')[1]
		.split(']')[0]
		.matchAll(/'(--nldesign-[a-z0-9-]+)'/g),
].map((match) => match[1])

/** The --nldesign-* declarations of a CSS file, the way the server parses them. */
function parseTokens(css) {
	const tokens = {}
	for (const match of css.matchAll(/^\s*(--nldesign-[\w-]+)\s*:\s*([^;]+);/gm)) {
		tokens[match[1]] = match[2].trim()
	}
	return tokens
}

/** The merged --nldesign-* layer of a shipped set: the defaults, then the set. */
function resolvedTokens(setId) {
	return {
		...parseTokens(
			fs.readFileSync(
				path.join(ROOT, 'css/systems/nldesign/defaults.css'),
				'utf8',
			),
		),
		...parseTokens(
			fs.readFileSync(path.join(ROOT, `css/tokens/${setId}.css`), 'utf8'),
		),
	}
}

/** The --color-* to --nldesign-* map, the way the server parses overrides.css. */
function tokenSources() {
	const sources = {}
	const css = fs.readFileSync(
		path.join(ROOT, 'css/systems/nldesign/overrides.css'),
		'utf8',
	)
	for (const match of css.matchAll(
		/^\s*(--[\w-]+)\s*:\s*var\((--[\w-]+)\)\s*(?:!important)?\s*;/gm,
	)) {
		sources[match[1]] = match[2]
	}
	return sources
}

/** Whether the vocabulary audit would rate a token map complete. */
function ratesComplete(tokens) {
	return REQUIRED_TOKENS.every((token) =>
		Object.prototype.hasOwnProperty.call(tokens, token),
	)
}

describe('component instrument: the chips', () => {
	it('offers a tab only the components filed under it', () => {
		const status = playground.componentsFor(inventory, 'status')

		expect(status.length).toBeGreaterThan(0)
		expect(status.every((component) => component.tab === 'status')).toBe(true)
	})

	it('offers nothing for a tab the editor does not have', () => {
		expect(playground.componentsFor(inventory, 'nonsense')).toEqual([])
	})

	it('finds a component by id', () => {
		expect(playground.componentById(inventory, 'primary-button').title).toBe(
			'Primary button',
		)
		expect(playground.componentById(inventory, 'no-such-thing')).toBe(null)
	})
})

describe('component instrument: the rows under a component', () => {
	const component = playground.componentById(inventory, 'primary-button')

	it('groups the rows under the state each one paints, in callout order', () => {
		const groups = playground.rowsByState(component)

		expect(groups.map((group) => group.state.n)).toEqual([1, 2, 3, 4])
		expect(groups[0].tokens.map((token) => token.name)).toContain(
			'--color-primary-element',
		)
	})

	it('puts a token-less fact under its own state and nowhere else', () => {
		const groups = playground.rowsByState(component)
		const disabled = groups.find((group) => group.state.id === 'disabled')

		expect(disabled.fixed.map((entry) => entry.code)).toEqual([
			'derived-by-nextcloud',
		])
		expect(disabled.tokens).toEqual([])
	})

	it('lists every token of the component exactly once across the states', () => {
		const listed = playground
			.rowsByState(component)
			.flatMap((group) => group.tokens.map((token) => token.name))

		expect(listed.sort()).toEqual(
			component.tokens.map((token) => token.name).sort(),
		)
	})
})

describe('component instrument: the URL hash', () => {
	it('round-trips a selection', () => {
		const hash = playground.hashFor('status', 'primary-button')

		expect(hash).toBe('#preview=status/primary-button')
		expect(playground.parseHash(hash, inventory)).toEqual({
			tab: 'status',
			component: 'primary-button',
		})
	})

	it('round-trips the full view of a tab', () => {
		const hash = playground.hashFor('content', playground.FULL_VIEW)

		expect(playground.parseHash(hash, inventory)).toEqual({
			tab: 'content',
			component: playground.FULL_VIEW,
		})
	})

	it('refuses a component that is not under the tab the link names', () => {
		// A link is shared and then the inventory moves a chip. Opening the tab
		// and ignoring the stale component is recoverable; opening a component
		// under a tab whose token rows it cannot filter is not.
		expect(
			playground.parseHash('#preview=typography/primary-button', inventory),
		).toBe(null)
	})

	it('refuses a tab or a component this build does not have', () => {
		expect(
			playground.parseHash('#preview=nonsense/primary-button', inventory),
		).toBe(null)
		expect(
			playground.parseHash('#preview=status/no-such-thing', inventory),
		).toBe(null)
		expect(playground.parseHash('', inventory)).toBe(null)
		expect(playground.parseHash('#something-else', inventory)).toBe(null)
	})
})

describe('component instrument: the token set export', () => {
	const tokens = resolvedTokens('rijkshuisstijl')
	const sources = tokenSources()

	it('round-trips the active set when nothing is overridden', () => {
		const result = playground.exportCss(tokens, {}, sources)

		expect(parseTokens(result.css)).toEqual(tokens)
		expect(result.unexpressed).toEqual([])
	})

	it('is rated by the vocabulary audit exactly as the set it came from', () => {
		const exported = parseTokens(playground.exportCss(tokens, {}, sources).css)

		expect(ratesComplete(exported)).toBe(ratesComplete(tokens))
		expect(ratesComplete(exported)).toBe(true)
	})

	it('writes an override back to the token the variable reads', () => {
		const exported = parseTokens(
			playground.exportCss(tokens, { '--color-primary': '#a90061' }, sources)
				.css,
		)

		expect(exported['--nldesign-color-primary']).toBe('#a90061')
	})

	it('reports an override no token in the vocabulary can carry', () => {
		// `--color-scrollbar` is editable and `overrides.css` maps it to no
		// --nldesign-* token, so a token set file cannot express it. Saying so is
		// the difference between an export the admin can trust and one that
		// quietly lost a change.
		const result = playground.exportCss(
			tokens,
			{ '--color-scrollbar': '#111111' },
			sources,
		)

		expect(result.unexpressed).toEqual(['--color-scrollbar'])
		expect(parseTokens(result.css)).toEqual(tokens)
	})

	it('emits one flat :root block, sorted, in the shape the upload accepts', () => {
		const lines = playground.exportCss(tokens, {}, sources).css.split('\n')
		const names = lines
			.filter((line) => line.startsWith('  --'))
			.map((line) => line.split(':')[0].trim())

		expect(lines[1]).toBe(':root {')
		expect(lines[lines.length - 2]).toBe('}')
		expect(names).toEqual([...names].sort())
	})
})
