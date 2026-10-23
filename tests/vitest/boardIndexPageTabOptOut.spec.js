/**
 * @vitest-environment jsdom
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A board-look index page tab keeps the main text colour nextcloud-vue paints.
 *
 * nextcloud-vue's CnIndexPage draws the sibling lists of a page as folder
 * tabs: links with `.cn-index-page__page-tab`. Under the board look they read
 * as tabs in the main text colour, not as links. The id-level nldesign link
 * rules in element-overrides.css (`:not(#header a)` with `!important`) would
 * paint them in the link colour, so those rules leave the tab out, as they
 * do for the board card title.
 *
 * @spec openspec/specs/css-architecture/spec.md#scenario-app-specific-exclusions
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import postcss from 'postcss'
import { beforeAll, describe, it, expect } from 'vitest'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const FILE = 'css/systems/nldesign/element-overrides.css'

/**
 * Every selector in element-overrides.css that forces a colour with
 * `!important`.
 *
 * @return {string[]} The selectors.
 */
function forcingSelectors() {
	const out = []
	const css = fs.readFileSync(path.join(root, FILE), 'utf8')
	postcss.parse(css).walkRules((rule) => {
		if (rule.parent.type === 'atrule' && /keyframes/.test(rule.parent.name)) {
			return
		}
		const forces = rule.nodes.some(
			(node) => node.type === 'decl' && node.prop === 'color' && node.important,
		)
		if (forces) {
			out.push(...rule.selectors.map((s) => s.trim()))
		}
	})
	return out
}

/**
 * The forcing selectors an element matches that carry an id. A selector jsdom
 * cannot parse is skipped; the controls below prove the link rules parse.
 *
 * @param {Element} element The element.
 * @return {string[]} The matching id-level selectors.
 */
function idLevelMatches(element) {
	return forcingSelectors().filter((selector) => {
		if (!selector.includes('#')) {
			return false
		}
		try {
			return element.matches(selector)
		} catch {
			return false
		}
	})
}

const $ = (id) => document.getElementById(id)

beforeAll(() => {
	document.body.innerHTML = `
		<div id="app"><div id="content" class="cn-look-board"><div class="app-content">
			<nav class="cn-index-page__page-tabs">
				<a id="board-tab" class="cn-index-page__page-tab cn-index-page__page-tab--active" href="#">Contracten</a>
			</nav>
		</div></div></div>
		<div id="plain"><nav class="cn-index-page__page-tabs">
			<a id="plain-tab" class="cn-index-page__page-tab" href="#">Contracten</a>
		</nav>
		<a id="plain-link" href="#">link</a></div>`
})

describe('the nldesign link rules leave a board-look index page tab its colour', () => {
	it('forces no id-level colour onto a page tab under the board look', () => {
		expect(idLevelMatches($('board-tab'))).toEqual([])
	})

	it('still forces the link colour onto a plain link', () => {
		expect(idLevelMatches($('plain-link')).length).toBeGreaterThan(0)
	})

	it('still forces the link colour onto a page tab without the board look', () => {
		expect(idLevelMatches($('plain-tab')).length).toBeGreaterThan(0)
	})
})
