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

	it('keeps the defining variable when two overrides read one token', () => {
		// The map is many-to-one: `overrides.css` points both --color-primary
		// and --color-primary-element at --nldesign-color-primary, and
		// TokenRegistry makes both editable — so an admin can override both and
		// the file has one line to carry them. The token's own name decides,
		// the same rule StockTokensService::canonical() applies to the same map
		// in the other direction.
		const result = playground.exportCss(
			tokens,
			{
				'--color-primary-element': '#00ff00',
				'--color-primary': '#ff0000',
			},
			sources,
		)

		expect(parseTokens(result.css)['--nldesign-color-primary']).toBe('#ff0000')
	})

	it('reports the override the other one took the token from', () => {
		// The bug this guards: the loser used to vanish from the file AND from
		// the report, so an admin re-imported a set that had silently lost a
		// change they had made and saved.
		const result = playground.exportCss(
			tokens,
			{
				'--color-primary': '#ff0000',
				'--color-primary-element': '#00ff00',
			},
			sources,
		)

		expect(result.overruled).toEqual([
			{
				name: '--color-primary-element',
				token: '--nldesign-color-primary',
				winner: '--color-primary',
			},
		])
		expect(result.unexpressed).toEqual([])
	})

	it('does not depend on the order the overrides arrive in', () => {
		const forwards = playground.exportCss(
			tokens,
			{ '--color-primary': '#ff0000', '--color-primary-element': '#00ff00' },
			sources,
		)
		const backwards = playground.exportCss(
			tokens,
			{ '--color-primary-element': '#00ff00', '--color-primary': '#ff0000' },
			sources,
		)

		expect(forwards.css).toBe(backwards.css)
		expect(forwards.overruled).toEqual(backwards.overruled)
	})

	it('falls back to sorted order when no variable carries the token name', () => {
		// --nldesign-color-main-background is read by variables none of which is
		// called --color-main-background, so the candidates are interchangeable
		// and the file must still not depend on iteration order.
		const competing = Object.keys(sources).filter(
			(name) =>
				sources[name] === '--nldesign-color-primary'
				&& name !== '--color-primary',
		)

		const result = playground.exportCss(
			tokens,
			Object.fromEntries(competing.map((name, i) => [name, '#00000' + i])),
			sources,
		)

		const winner = [...competing].sort()[0]
		expect(parseTokens(result.css)['--nldesign-color-primary']).toBe(
			'#00000' + competing.indexOf(winner),
		)
		expect(result.overruled).toHaveLength(competing.length - 1)
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

describe('component instrument: the values the export carries', () => {
	// The server publishes what it parsed out of a file. The browser knows what
	// the page is actually wearing. These differ for the `nextcloud` set by
	// construction — it is resolved from the running instance and has no file
	// values to publish — and they can differ for any set whose file drifts
	// from the cascade.
	it('prefers the live value over the one the server parsed', () => {
		const live = playground.liveTokens(
			{ '--nldesign-color-primary': '#0082c9' },
			() => '#00679e',
		)

		expect(live).toEqual({ '--nldesign-color-primary': '#00679e' })
	})

	it('falls back to the server value when the cascade has none', () => {
		const live = playground.liveTokens(
			{ '--nldesign-color-primary': '#00679e' },
			(name, fallback) => fallback,
		)

		expect(live).toEqual({ '--nldesign-color-primary': '#00679e' })
	})

	it('keeps the server map as the key set, so no token is dropped', () => {
		// A token the cascade cannot answer for still belongs in an exported
		// set: the file defines what the set is MADE of, and a set that
		// silently loses a token is a set that cannot be re-imported whole.
		const live = playground.liveTokens(
			{
				'--nldesign-color-primary': '#00679e',
				'--nldesign-color-error': '#FFE7E7',
			},
			(name, fallback) => fallback,
		)

		expect(Object.keys(live).sort()).toEqual([
			'--nldesign-color-error',
			'--nldesign-color-primary',
		])
	})

	it('invents no token the server did not publish', () => {
		const live = playground.liveTokens({}, () => '#ffffff')

		expect(live).toEqual({})
	})
})

describe('component instrument: the specimens can be used', () => {
	// The content area once rendered as a wall of drawings that ignored every
	// click, because the rule then in force froze any element documenting a
	// state — and that is the selected row in almost every component, so the row
	// an admin reaches for was exactly the row that refused. These assert the
	// hooks are in the markup, so a rewritten specimen cannot quietly go inert
	// again.

	// Read-only indicators. An avatar and a progress bar report state; there is
	// nothing a click could mean on either, and inventing something would be
	// worse than leaving them alone.
	const STATIC = ['avatar', 'progress']

	/**
	 * Match a selector against markup the way a browser would, near enough.
	 * `.foo` has to appear in a class attribute as a whole word and `tr` as a
	 * real tag — matching either as a bare substring makes `<strong>` answer
	 * for `tr`, which is how the first draft of this test passed everything.
	 */
	const matches = (selector) => {
		const last = selector.split(' ').pop()
		if (last.startsWith('.')) {
			const name = last.slice(1)
			return (markup) =>
				[...markup.matchAll(/class="([^"]*)"/g)].some((attribute) =>
					attribute[1].split(/\s+/).includes(name),
				)
		}
		return (markup) => markup.includes(`<${last}`)
	}

	const HOOKS = [
		...playground.PICKABLE.map((group) => group.row),
		'.nldesign-pg-choice',
		'.nldesign-pg-option',
		'.nldesign-pg-action',
		'.action-item__menutoggle',
		// The text field is a real `<input>` now, rendered in NcInputField's own
		// DOM so the shipped stylesheet reaches it; the drawn one is what is
		// left behind for the select and the textarea.
		'.input-field__input',
		'.nldesign-pg-input',
		'.nldesign-pg-textarea',
		'.nldesign-pg-btn',
	].map(matches)

	const render = (component) => {
		const build = playground.STAGES[component.id]
		const states = component.layout === 'wide' ? [null] : component.states
		return states
			.map((state) => build(state === null ? null : state.id, component))
			.join('')
	}

	it('gives every content-area component something to interact with', () => {
		const inert = inventory.components
			.filter((entry) => entry.tab === 'content')
			.filter((entry) => !STATIC.includes(entry.id))
			.filter((entry) => {
				const markup = render(entry)
				return !HOOKS.some((hook) => hook(markup))
			})
			.map((entry) => entry.id)

		expect(inert).toEqual([])
	})

	it('puts every pickable row inside the container its group names', () => {
		// pick() finds the siblings with closest(group), so a row whose named
		// container is not actually an ancestor would silently never move its
		// selection — the failure this whole change was about.
		const orphaned = []
		playground.PICKABLE.forEach((group) => {
			const hasRow = matches(group.row)
			const hasGroup = matches(group.group.split(' ')[0])
			inventory.components.forEach((entry) => {
				const markup = render(entry)
				if (hasRow(markup) && !hasGroup(markup)) {
					orphaned.push(`${entry.id}: ${group.row}`)
				}
			})
		})

		expect(orphaned).toEqual([])
	})

	it('leaves no specimen drawn from the removed placeholder bars', () => {
		const placeheld = inventory.components
			.filter((entry) => render(entry).includes('nldesign-pg-line'))
			.map((entry) => entry.id)

		expect(placeheld).toEqual([])
	})
})

describe('the shipped stylesheets, reached', () => {
	// The specimens are drawn in Nextcloud's own component DOM so that the CSS
	// this page ALREADY loads paints them. Every one of those rules is
	// Vue-scoped, so the whole arrangement hangs on finding the right attribute
	// in the loaded sheets and putting it on. When that stops working the
	// specimens do not look subtly wrong — the components lose their styling
	// entirely, and an admin reads that as a broken theme.

	/**
	 * A style rule, shaped the way a real one is.
	 *
	 * The `cssRules` is the point. Since CSS Nesting shipped, CSSStyleRule
	 * inherits it from CSSGroupingRule, so a real style rule carries an empty
	 * list — and an empty CSSRuleList is an object, so it is TRUTHY. A helper
	 * that left it out modelled a CSSOM no browser has produced for years, and
	 * it is what let the walker ship testing `cssRules` before
	 * `selectorText`: every real style rule took the grouping branch and no
	 * specimen was stamped, while these tests stayed green.
	 */
	const styleRule = (selectorText, nested = []) => ({
		selectorText,
		cssRules: nested,
	})

	/** A stylesheet, shaped the way the CSSOM hands one over. */
	const sheet = (...selectors) => ({
		cssRules: selectors.map((s) => (typeof s === 'string' ? styleRule(s) : s)),
	})

	/** A grouping rule — @media, @supports — which has no selector of its own. */
	const group = (...children) => ({ cssRules: children })

	/** An element, shaped the way applyScopes uses one. */
	const element = (...names) => {
		const attributes = {}
		return {
			classList: names,
			attributes,
			setAttribute(name, value) {
				attributes[name] = value
			},
		}
	}

	const tree = (...nodes) => ({ querySelectorAll: () => nodes })

	it('finds the scope attribute each class is styled under', () => {
		const scopes = playground.componentScopes({
			styleSheets: [
				sheet(
					'.button-vue[data-v-00a99684]',
					'.button-vue--wide[data-v-00a99684]',
					'.input-field__label[data-v-8e16cbb5]',
					'.plain-old-class',
				),
			],
		})

		expect(scopes['button-vue']).toEqual(['data-v-00a99684'])
		expect(scopes['button-vue--wide']).toEqual(['data-v-00a99684'])
		expect(scopes['input-field__label']).toEqual(['data-v-8e16cbb5'])
		expect(scopes['plain-old-class']).toBeUndefined()
	})

	it('reads a style rule that carries an empty cssRules of its own', () => {
		// The regression guard for the walker's rule order. Every rule here is
		// shaped like a real CSSStyleRule, so a walker that descends before it
		// reads the selector finds nothing at all.
		const scopes = playground.componentScopes({
			styleSheets: [sheet('.notecard[data-v-11112222]')],
		})

		expect(scopes.notecard).toEqual(['data-v-11112222'])
	})

	it('reads a nested style rule as well as the one holding it', () => {
		// CSS Nesting again, from the other side: a style rule can be BOTH a
		// selector and a container, so finding the parent must not stop the
		// descent and descending must not skip the parent.
		const scopes = playground.componentScopes({
			styleSheets: [
				sheet(
					styleRule('.list-item[data-v-aaaabbbb]', [
						styleRule('.list-item__name[data-v-aaaabbbb]'),
					]),
				),
			],
		})

		expect(scopes['list-item']).toEqual(['data-v-aaaabbbb'])
		expect(scopes['list-item__name']).toEqual(['data-v-aaaabbbb'])
	})

	it('still descends into a grouping rule, which has no selector', () => {
		const scopes = playground.componentScopes({
			styleSheets: [
				{ cssRules: [group(styleRule('.avatardiv[data-v-ccccdddd]'))] },
			],
		})

		expect(scopes.avatardiv).toEqual(['data-v-ccccdddd'])
	})

	it('follows an @import, which is how these sheets actually arrive', () => {
		// Not a corner case, and the reason the first version of this stamped
		// nothing at all: `dist/theming-settings-admin.css` is one <link> whose
		// entire body is a list of @imports, one per component chunk. In the
		// CSSOM each of those is a CSSImportRule and the imported sheet hangs
		// off `styleSheet` — a property a walker looking for `cssRules` steps
		// straight past, finding an empty map and leaving every specimen bare.
		const scopes = playground.componentScopes({
			styleSheets: [
				{
					cssRules: [
						{ styleSheet: sheet('.button-vue[data-v-00a99684]') },
						{
							styleSheet: sheet(
								'.input-field__input[data-v-8e16cbb5]',
							),
						},
					],
				},
			],
		})

		expect(scopes['button-vue']).toEqual(['data-v-00a99684'])
		expect(scopes['input-field__input']).toEqual(['data-v-8e16cbb5'])
	})

	it('descends into media and supports blocks', () => {
		// The dark-mode halves of these components live inside one, and a
		// component styled only in the light half would go unstamped and lose
		// its dark rules with it.
		const scopes = playground.componentScopes({
			styleSheets: [
				{
					cssRules: [
						{
							cssRules: sheet('.input-field__input[data-v-8e16cbb5]')
								.cssRules,
						},
					],
				},
			],
		})

		expect(scopes['input-field__input']).toEqual(['data-v-8e16cbb5'])
	})

	it('keeps every scope a class is styled under, not just the first', () => {
		// `material-design-icon` is scoped separately in every component that
		// draws an icon, and one element may legitimately carry several — which
		// is what Vue itself does at the root of a child component.
		const scopes = playground.componentScopes({
			styleSheets: [
				sheet(
					'.material-design-icon[data-v-00a99684]',
					'.material-design-icon[data-v-5ca1e30f]',
				),
			],
		})

		expect(scopes['material-design-icon']).toEqual([
			'data-v-00a99684',
			'data-v-5ca1e30f',
		])
	})

	it('survives a stylesheet it is not allowed to read', () => {
		// A sheet from another origin throws on `cssRules`. Nextcloud serves all
		// of its own from this one, so the answer is to skip it rather than to
		// give up and leave every specimen unstamped.
		const scopes = playground.componentScopes({
			styleSheets: [
				{
					get cssRules() {
						throw new Error('SecurityError')
					},
				},
				sheet('.button-vue[data-v-00a99684]'),
			],
		})

		expect(scopes['button-vue']).toEqual(['data-v-00a99684'])
	})

	it('stamps every scope a specimen carries a styled class for', () => {
		const node = element('button-vue', 'button-vue--wide', 'nldesign-pg-btn')
		playground.applyScopes(tree(node), {
			'button-vue': ['data-v-00a99684'],
			'button-vue--wide': ['data-v-00a99684'],
			'input-field': ['data-v-8e16cbb5'],
		})

		expect(node.attributes).toEqual({ 'data-v-00a99684': '' })
	})
})

describe('the login card, against the page it stands for', () => {
	// Transcribed from the rendered DOM of a real Nextcloud 34 login page. The
	// class names are the contract: they are what core's stylesheet, the
	// component stylesheets and Thematiq's own overrides all match on, and a
	// specimen missing one of them is a specimen one of those three stops
	// reaching.
	const markup = playground.STAGES['login-card'](
		null,
		inventory.components.find((entry) => entry.id === 'login-card'),
	)

	it('keeps the guest layout nesting core styles against', () => {
		// `.wrapper` is what separates the card from the footer, and
		// `.v-align` and `.guest-content` are what core centres it with.
		expect(markup).toContain('class="wrapper"')
		expect(markup).toContain('class="v-align"')
		expect(markup).toContain('class="guest-content"')
		expect(markup.indexOf('<footer')).toBeGreaterThan(
			markup.indexOf('class="guest-content"'),
		)
	})

	it('renders the log-in button as NcButton renders it', () => {
		// Including the `vue-` infix: that is what this Nextcloud's login page
		// emits, and it is the only name Thematiq's element-overrides.css knows.
		expect(markup).toContain('button-vue--vue-primary')
		expect(markup).toContain('button-vue--icon-and-text')
		expect(markup).toContain('button-vue--wide')
		expect(markup).toContain('class="button-vue__icon"')
	})

	it('gives a text button no icon span and an icon button no text span', () => {
		// NcButton's own `:empty` and `:has()` rules are what turn an icon-only
		// button square and close up a text-only one; emitting both spans
		// regardless would defeat them.
		const tertiary = markup.slice(markup.indexOf('button-vue--text-only'))
		expect(tertiary.slice(0, tertiary.indexOf('</button>'))).not.toContain(
			'button-vue__icon',
		)

		const reveal = markup.slice(markup.indexOf('button-vue--icon-only'))
		expect(reveal.slice(0, reveal.indexOf('</button>'))).not.toContain(
			'button-vue__text',
		)
	})

	it('renders real controls rather than pictures of them', () => {
		expect(markup).toContain('class="input-field__input"')
		expect(markup).toContain('type="password"')
		expect(markup).toContain('class="checkbox-radio-switch__input"')
	})

	it('gives the checkbox the sizes the component v-binds onto itself', () => {
		// Those two custom properties are declared under build-hash names no
		// specimen can carry; the properties they feed resolve to nothing
		// without this, and the control collapses.
		expect(markup).toContain('--icon-size:24px')
		expect(markup).toContain('--icon-height:24px')
	})

	it('ties every label to the input it names, under an id of its own', () => {
		const ids = [...markup.matchAll(/<input id="([^"]+)"/g)].map((m) => m[1])
		const fors = [...markup.matchAll(/<label for="([^"]+)"/g)].map((m) => m[1])

		expect(ids.length).toBeGreaterThan(0)
		expect(new Set(ids).size).toBe(ids.length)
		fors.forEach((name) => expect(ids).toContain(name))
	})
})
