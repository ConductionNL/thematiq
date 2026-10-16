/**
 * @vitest-environment jsdom
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A board-look card title keeps the main text colour nextcloud-vue paints.
 *
 * nextcloud-vue's CnBoardView draws each card title as a link styled as a
 * heading: `.cn-board-view--board .cn-board-view__card-title` with
 * `color: var(--color-main-text) !important` (nextcloud-vue#1636). The
 * nldesign link rules in element-overrides.css score at id level through
 * `:not(#header a)`, so with their own `!important` they won and the titles
 * read as links. Those rules now leave the title out.
 *
 * Read from the stylesheets: every rule that declares `color: … !important`
 * is matched against a fixture with jsdom. The theme.css link rule stays at
 * (0,0,1) and loses to the title's class rule, so only the id-level rules of
 * element-overrides.css must skip the title. Controls prove the same rules
 * still reach a plain link and a card title outside the board look.
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
		<div id="app"><div id="content"><div class="app-content">
			<div class="cn-board-view cn-board-view--board">
				<div class="cn-board-view__card">
					<a id="board-title" class="cn-board-view__card-title" href="#">Case 12</a>
				</div>
			</div>
			<div class="cn-board-view">
				<a id="plain-board-title" class="cn-board-view__card-title" href="#">Case 13</a>
			</div>
			<a id="plain-link" href="#">link</a>
		</div></div></div>`
})

describe('the nldesign link rules leave a board-look card title its colour', () => {
	it('forces no id-level colour onto a card title under the board look', () => {
		expect(idLevelMatches($('board-title'))).toEqual([])
	})

	it('still forces the link colour onto a plain link', () => {
		expect(idLevelMatches($('plain-link')).length).toBeGreaterThan(0)
	})

	it('still forces the link colour onto a card title without the board look', () => {
		expect(idLevelMatches($('plain-board-title')).length).toBeGreaterThan(0)
	})
})
