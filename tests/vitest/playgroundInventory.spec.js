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

/**
 * The THEMING stylesheets, as one string, for the class-name check.
 *
 * The playground's own sheets are excluded on purpose, and that exclusion is
 * the whole point of the guard. A class name here is meant to prove the theme
 * still reaches the component; counting css/playground.css lets the specimen's
 * fallback floor answer for the theme, and a floor defines every class the
 * specimen draws by construction. That is exactly how three wrong names —
 * .menutoggle, .unified-search__button and .app-content-detail — survived this
 * guard until they were read off Nextcloud's own source instead.
 *
 * So: a name has to appear in a stylesheet that paints the REAL component, not
 * in the one that paints the drawing of it.
 */
const SELF_PAINTED = /playground/

const stylesheets = (function read(dir) {
	return fs.readdirSync(dir, { withFileTypes: true }).reduce((text, entry) => {
		const full = path.join(dir, entry.name)
		if (entry.isDirectory()) {
			return text + read(full)
		}
		if (entry.name.endsWith('.css') === false) {
			return text
		}
		if (SELF_PAINTED.test(entry.name) === true) {
			return text
		}
		return text + fs.readFileSync(full, 'utf8')
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

	it('draws no frozen copy of a state the admin can produce by pointing', () => {
		// A frozen hover beside the live specimen is two components an admin
		// cannot tell apart, one of which does not respond to being hovered.
		// The real pseudo-class is wired on every specimen, so the drawing shows
		// the component once and lets it be hovered.
		const frozen = inventory.components.flatMap((component) => {
			// Only the states the stage actually draws: renderStage skips
			// the pointable ones, so asking a builder for `hover` and then
			// objecting to what comes back tests nothing that ships.
			const states =
				component.layout === 'wide'
					? [null]
					: component.states.filter(
							(state) => !playground.POINTABLE.includes(state.id),
						)
			const markup = states
				.map((state) =>
					String(
						playground.STAGES[component.id](
							state === null ? null : state.id,
							component,
						) || '',
					),
				)
				.join('')
			return playground.POINTABLE.filter((name) =>
				[...markup.matchAll(/class="([^"]*)"/g)].some((attribute) =>
					attribute[1].split(/\s+/).includes(`is-${name}`),
				),
			).map((name) => `${component.id}: is-${name}`)
		})

		expect(frozen).toEqual([])
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

describe('the header specimen across Nextcloud versions', () => {
	// The header is the one component whose MARKUP changes between the versions
	// this app supports. 34 deleted core/src/components/AppMenuEntry.vue — it is
	// present in v32.0.0 and v33.0.0 and a 404 in v34.0.0 — and replaced the
	// entry row with a waffle, a popover grid and a current-app button. An admin
	// on 32 asking "what happens when I upgrade" is the whole point of the
	// switch, so these assert each version draws the shape that version ships.
	const header = (version) => playground.STAGES['header-bar'](null, null, version)

	const classesOf = (markup) =>
		new Set(
			[...markup.matchAll(/class="([^"]*)"/g)].flatMap((attribute) =>
				attribute[1].split(/\s+/),
			),
		)

	it('draws the entry row on 32 and 33, and never the 34 shape', () => {
		for (const version of [32, 33]) {
			const classes = classesOf(header(version))

			expect(classes.has('app-menu-entry')).toBe(true)
			expect(classes.has('app-menu__list')).toBe(true)
			expect(classes.has('app-menu__waffle')).toBe(false)
			expect(classes.has('app-menu__current-app')).toBe(false)
		}
	})

	it('draws the waffle and the current app on 34, and never the entry row', () => {
		const classes = classesOf(header(34))

		expect(classes.has('app-menu__waffle')).toBe(true)
		expect(classes.has('app-menu__current-app')).toBe(true)
		expect(classes.has('app-menu-entry')).toBe(false)
		expect(classes.has('app-menu__list')).toBe(false)
	})

	it('moves the search from a glyph on the right to a field in the middle', () => {
		// 34 did not merely rename the search trigger: it replaced the magnifier
		// among the account glyphs with UnifiedSearchInput, a <search> element
		// carrying the placeholder, between the app menu and the glyphs. A
		// specimen that only renamed the class would put the new search in the
		// old place.
		//
		// The 32/33 side is asserted on the names UnifiedSearch.vue really
		// emits — .unified-search-menu around an NcHeaderButton, whose visible
		// element is .header-menu__trigger. The name this test used to assert,
		// .unified-search__button, is pre-Vue and is emitted by NEITHER
		// release, so it proved only that the specimen still said it.
		expect(classesOf(header(33)).has('unified-search-menu')).toBe(true)
		expect(classesOf(header(33)).has('header-menu__trigger')).toBe(true)
		expect(classesOf(header(33)).has('unified-search__button')).toBe(false)
		expect(header(33)).not.toContain('<search')

		expect(classesOf(header(34)).has('unified-search-input')).toBe(true)
		expect(header(34)).toContain('<search')
		expect(classesOf(header(34)).has('unified-search-menu')).toBe(false)
	})

	it('names the current app and gives it an icon, on 34 only', () => {
		expect(header(34)).toContain('Thematiq')
		expect(classesOf(header(34)).has('app-menu__current-app-icon')).toBe(true)
		expect(header(32)).not.toContain('Thematiq')
	})

	it('draws an app menu in every version it can be drawn as', () => {
		// Whichever shape is showing, the menu itself has to be there: a version
		// branch that returns nothing would leave the bar with a logo and the
		// account glyphs and nothing between them.
		for (const version of [32, 33, 34]) {
			expect(classesOf(header(version)).has('app-menu')).toBe(true)
		}
	})

	it('falls back to the newest header when the version is unknown', () => {
		// playgroundVersion is 0 when the server cannot be asked.
		expect(classesOf(header(0)).has('app-menu__waffle')).toBe(true)
		expect(classesOf(header(undefined)).has('app-menu__waffle')).toBe(true)
	})
})

describe('the header specimen shows the instance, not a mock-up', () => {
	const header = (version) => playground.STAGES['header-bar'](null, null, version)

	it('never hardcodes a stand-in account', () => {
		// The bar an admin is judging is THEIR bar, and a stranger's initials in
		// the corner is the one detail that makes the whole drawing read as
		// somebody else's screenshot. The avatar comes from
		// OC.getCurrentUser(); these initials were literal.
		for (const version of [32, 33, 34]) {
			expect(header(version)).not.toContain('RB')
		}
	})

	it('degrades to a placeholder where there is no session to ask', () => {
		// This file is loaded under Node by these very tests, so `OC` is absent
		// and accountPlate() must not throw — a builder that crashes takes the
		// whole stage down, not just the avatar.
		expect(header(34)).toContain('nldesign-pg-avatarwrap')
		expect(header(34)).toContain('user-status-icon')
	})

	it('draws the logo through core class, not a shape of its own', () => {
		// `.logo` is what core's own header rule paints, so the specimen
		// resolves --image-logoheader / --image-logo the same way the real bar
		// does instead of drawing a disc that shows an admin nothing about
		// their own branding.
		for (const version of [32, 33, 34]) {
			expect(header(version)).toContain('nldesign-pg-header-logo logo')
		}
	})
})

describe('the 34 search field matches the structure core gives it', () => {
	const header34 = () => playground.STAGES['header-bar'](null, null, 34)

	it('nests the button inside the search element, not beside it', () => {
		// UnifiedSearchInput is two elements doing two jobs: <search> is an
		// absolutely centred, click-through TRACK spanning the bar, and
		// .unified-search-input__button inside it is the thing you see and
		// click. Flattening them into one element is what made the specimen a
		// left-aligned pill in the middle of the leftover space rather than a
		// centred field in the middle of the bar.
		const markup = header34()
		const search = markup.indexOf('<search')
		const button = markup.indexOf('unified-search-input__button')
		const close = markup.indexOf('</search>')

		expect(search).toBeGreaterThan(-1)
		expect(button).toBeGreaterThan(search)
		expect(button).toBeLessThan(close)
	})

	it('carries the icon and the label the real field carries', () => {
		expect(header34()).toContain('unified-search-input__icon')
		expect(header34()).toContain('unified-search-input__label')
	})
})
