/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The component inventory, held to the code it describes.
 *
 * `js/playground/components.json` is data, and data is exactly the kind of
 * thing that goes stale quietly: a token renamed in the registry, a class name
 * Nextcloud stopped using, a component whose stage markup was never written.
 * Each of those leaves an instrument that still renders and no longer tells the
 * truth — a chip that silently shows an unstyled specimen looks like a broken
 * theme rather than a stale data file. That is the failure this file exists to
 * make loud, and it is the drift guard a Vue build would have given for free.
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

/** Every variable the token editor can write, read out of the PHP registry. */
const registry = new Set(
	[
		...fs
			.readFileSync(path.join(ROOT, 'lib/Service/TokenRegistry.php'), 'utf8')
			.matchAll(/'(--[a-z0-9-]+)'\s*=>\s*\[/g),
	].map((match) => match[1]),
)

/** The tab each registry token is filed under, which is where its row renders. */
const registryTabs = new Map(
	[
		...fs
			.readFileSync(path.join(ROOT, 'lib/Service/TokenRegistry.php'), 'utf8')
			.matchAll(/'(--[a-z0-9-]+)'\s*=>\s*\['tab'\s*=>\s*'([a-z]+)'/g),
	].map((match) => [match[1], match[2]]),
)

/** Every stylesheet this app ships, as one string, for the class-name check. */
const stylesheets = (function read(dir) {
	return fs.readdirSync(dir, { withFileTypes: true }).reduce((text, entry) => {
		const full = path.join(dir, entry.name)
		if (entry.isDirectory()) {
			return text + read(full)
		}
		return entry.name.endsWith('.css')
			? text + fs.readFileSync(full, 'utf8')
			: text
	}, '')
})(path.join(ROOT, 'css'))

/** Every token of every component, flattened. */
const tokens = inventory.components.flatMap((component) =>
	component.tokens.map((token) => ({ component: component.id, ...token })),
)

describe('component inventory: the tokens', () => {
	it('names only tokens the editor actually renders a row for', () => {
		// The instrument CLONES the editor's own row for each token. A name the
		// registry does not carry has no row to clone, so the component would
		// list a control that does nothing.
		const unknown = tokens
			.filter((token) => registry.has(token.name) === false)
			.map((token) => `${token.component}: ${token.name}`)

		expect(unknown).toEqual([])
	})

	it('reaches every token the editor can write', () => {
		// The chips are the visual way into the same file the four-tab list
		// edits. A token no component reads is one an admin can only find by
		// scrolling the full list, which is the thing this instrument exists to
		// make unnecessary.
		const covered = new Set(tokens.map((token) => token.name))
		const unreachable = [...registry].filter(
			(name) => covered.has(name) === false,
		)

		expect(unreachable).toEqual([])
	})

	it('points every token at a state the stage actually draws', () => {
		const dangling = inventory.components.flatMap((component) => {
			const states = new Set(component.states.map((state) => state.n))
			return component.tokens
				.concat(component.fixed)
				.filter((entry) => states.has(entry.callout) === false)
				.map(
					(entry) =>
						`${component.id}: ${entry.name || entry.what} → ${entry.callout}`,
				)
		})

		expect(dangling).toEqual([])
	})

	it('says what every token paints', () => {
		const undescribed = tokens
			.filter((token) => !token.paints)
			.map((token) => `${token.component}: ${token.name}`)

		expect(undescribed).toEqual([])
	})

	it('gives every token-less row a reason code', () => {
		const unexplained = inventory.components.flatMap((component) =>
			component.fixed
				.filter((entry) => !entry.code || !entry.why)
				.map((entry) => `${component.id}: ${entry.what}`),
		)

		expect(unexplained).toEqual([])
	})

	it('uses reason codes the converter defines', () => {
		// Same vocabulary as an import report, so the same fact is never
		// explained two different ways.
		const known = new Set(
			Object.keys(
				JSON.parse(
					fs.readFileSync(
						path.join(ROOT, 'scripts/mapping/nlds-to-nextcloud.json'),
						'utf8',
					),
				).reasons,
			),
		)
		const invented = inventory.components.flatMap((component) =>
			component.fixed
				.filter((entry) => known.has(entry.code) === false)
				.map((entry) => `${component.id}: ${entry.code}`),
		)

		expect(invented).toEqual([])
	})
})

describe('component inventory: the components', () => {
	it('has stage markup for every component and a component for every stage', () => {
		const declared = inventory.components.map((component) => component.id).sort()
		const built = Object.keys(playground.STAGES).sort()

		expect(built).toEqual(declared)
	})

	it('draws every state its stage markup is asked for', () => {
		// A cell-per-state builder is called once per state id; one that returns
		// nothing would leave an empty cell under a numbered marker.
		const empty = inventory.components
			.filter((component) => component.layout !== 'wide')
			.flatMap((component) =>
				component.states
					.filter(
						(state) =>
							String(
								playground.STAGES[component.id](state.id, component)
									|| '',
							) === '',
					)
					.map((state) => `${component.id}: ${state.id}`),
			)

		expect(empty).toEqual([])
	})

	it('marks every state on the drawing of a wide component', () => {
		// A wide specimen is drawn once and places its own markers, so the legend
		// and the drawing can drift apart in a way the cell layout cannot: a
		// state listed below with no number on the component above it is a
		// promise the picture does not keep.
		const unmarked = inventory.components
			.filter((component) => component.layout === 'wide')
			.flatMap((component) => {
				const markup = String(
					playground.STAGES[component.id](null, component) || '',
				)
				return component.states
					.filter(
						(state) => markup.includes(`data-co="${state.n}"`) === false,
					)
					.map((state) => `${component.id}: ${state.n} (${state.id})`)
			})

		expect(unmarked).toEqual([])
	})

	it('draws a wide component at a size worth judging', () => {
		// The reason a component is wide at all: a table you can only see three
		// cells of, or a list with one row, tells an admin nothing about rhythm
		// — the rules between rows, the zebra against the hover, the spacing.
		// A token count is a poor proxy for "big enough", but an empty or
		// near-empty specimen is unambiguously not it.
		const thin = inventory.components
			.filter((component) => component.layout === 'wide')
			.map((component) => ({
				id: component.id,
				length: String(
					playground.STAGES[component.id](null, component) || '',
				).length,
			}))
			.filter((entry) => entry.length < 200)

		expect(thin).toEqual([])
	})

	it('says which surface every component is drawn on', () => {
		// "Will this look good" is a question about a component AND its
		// background; neither answers it alone. A component with no ground would
		// be judged against whatever the stage happens to be, which is the one
		// surface it never actually lands on.
		const grounds = ['content', 'plain', 'login', 'header', 'overlay']
		const unplaced = inventory.components
			.filter((component) => grounds.includes(component.ground) === false)
			.map((component) => `${component.id}: ${component.ground}`)

		expect(unplaced).toEqual([])
	})

	it('draws every ground it declares', () => {
		// A ground named in the data with no rule in the stylesheet is a
		// transparent box: the specimen would look like it sits on the stage.
		const stylesheet = fs.readFileSync(
			path.join(ROOT, 'css/playground.css'),
			'utf8',
		)
		const undrawn = [
			...new Set(inventory.components.map((component) => component.ground)),
		].filter(
			(ground) =>
				stylesheet.includes(`.nldesign-pg-ground--${ground}`) === false,
		)

		expect(undrawn).toEqual([])
	})

	it('names only class names a stylesheet in this app styles', () => {
		// The drift guard: when Nextcloud renames a class out from under a
		// copied fragment, the specimen stops being styled and looks like a
		// broken theme rather than a stale copy.
		const unstyled = inventory.components.flatMap((component) =>
			component.classes
				.filter(
					(className) =>
						new RegExp(
							'\\.'
								+ className.replace(/[-_]/g, (char) => '\\' + char)
								+ '(?![A-Za-z0-9_])',
						).test(stylesheets) === false,
				)
				.map((className) => `${component.id}: .${className}`),
		)

		expect(unstyled).toEqual([])
	})

	it('gives every component a unique id, a title and a subtitle', () => {
		const ids = inventory.components.map((component) => component.id)
		const incomplete = inventory.components
			.filter((component) => !component.title || !component.subtitle)
			.map((component) => component.id)

		expect(ids.length).toBe(new Set(ids).size)
		expect(incomplete).toEqual([])
	})

	it('names a radius token the registry carries, where it names one', () => {
		const unknown = inventory.components
			.filter((component) => component.radiusToken)
			.filter((component) => registry.has(component.radiusToken) === false)
			.map((component) => `${component.id}: ${component.radiusToken}`)

		expect(unknown).toEqual([])
	})

	it('fills every tab with at least one component', () => {
		const empty = inventory.tabs
			.filter(
				(tab) => playground.componentsFor(inventory, tab.id).length === 0,
			)
			.map((tab) => tab.id)

		expect(empty).toEqual([])
	})

	it('may read a token filed under another tab, and does', () => {
		// Not a defect, and worth pinning: a primary button lives under Buttons
		// & Status while the colour it is painted with is filed under Login page
		// & Branding. The instrument clones from the whole editor rather than
		// from the open panel precisely so this works.
		const crossTab = tokens.filter((token) => {
			const component = playground.componentById(inventory, token.component)
			return registryTabs.get(token.name) !== component.tab
		})

		expect(crossTab.length).toBeGreaterThan(0)
	})
})
