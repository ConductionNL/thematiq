/**
 * @vitest-environment jsdom
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A `.tile-widget` keeps the text and link colour its app paints
 * (thematiq#939, css-architecture "App-specific exclusions").
 *
 * The nldesign layer forces the body text colour onto every span, div and
 * link (element-overrides.css, TEXT COLOR FIXES) and the link colour onto
 * every `a` (theme.css, LINKS), both `!important`. The spec says a tile and
 * everything inside it is excluded from both. The live run found two holes:
 * the tile's own root element matched `div:not(.tile-widget div)`, which only
 * excludes descendants, and theme.css's `a` rule had no exclusion at all.
 *
 * Read from the stylesheets themselves: every rule of the two files that
 * declares `color: … !important` is matched against a fixture with jsdom.
 * A control outside the tile proves the same rules do match there.
 *
 * @spec openspec/specs/css-architecture/spec.md#scenario-app-specific-exclusions
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import postcss from 'postcss'
import { beforeAll, describe, it, expect } from 'vitest'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const FILES = [
	'css/systems/nldesign/theme.css',
	'css/systems/nldesign/element-overrides.css',
]

/**
 * Every selector that forces a colour with `!important`, per file.
 *
 * @return {Array<{file: string, selector: string}>} The selectors.
 */
function forcingSelectors() {
	const out = []
	for (const file of FILES) {
		const css = fs.readFileSync(path.join(root, file), 'utf8')
		postcss.parse(css).walkRules((rule) => {
			if (
				rule.parent.type === 'atrule'
				&& /keyframes/.test(rule.parent.name)
			) {
				return
			}
			let forces = false
			rule.each((node) => {
				if (
					node.type === 'decl'
					&& node.prop === 'color'
					&& node.important
				) {
					forces = true
				}
			})
			if (forces) {
				for (const selector of rule.selectors) {
					out.push({ file, selector: selector.trim() })
				}
			}
		})
	}
	return out
}

/**
 * The forcing selectors an element matches. A selector jsdom cannot parse
 * (pseudo-elements, vendor pseudo-classes) is skipped; the control tests
 * below prove the selectors this spec is about parse.
 *
 * @param {Element} element The element.
 * @return {string[]} `file: selector` for each match.
 */
function matching(element) {
	return forcingSelectors()
		.filter(({ selector }) => {
			try {
				return element.matches(selector)
			} catch {
				return false
			}
		})
		.map(({ file, selector }) => `${file}: ${selector}`)
}

const $ = (id) => document.getElementById(id)

beforeAll(() => {
	document.body.innerHTML = `
		<div id="app"><div id="content"><div class="app-content">
			<div class="tile-widget" id="tile">
				<span id="tile-span">label</span>
				<div id="tile-div">text</div>
				<a id="tile-link" href="#">link</a>
				<p><a id="tile-deep-link" href="#">deep link</a></p>
			</div>
			<a class="tile-widget" id="tile-as-link" href="#">a tile that is a link</a>
			<span class="tile-widget" id="tile-as-span">a tile that is a span</span>
			<div id="plain-div">text</div>
			<span id="plain-span">label</span>
			<a id="plain-link" href="#">link</a>
		</div></div></div>`
})

describe('the nldesign layer leaves a .tile-widget its own colours', () => {
	it.each([
		['the tile itself', 'tile'],
		['a span inside it', 'tile-span'],
		['a div inside it', 'tile-div'],
		['a link inside it', 'tile-link'],
		['a link deeper inside it', 'tile-deep-link'],
		['a tile that is a link', 'tile-as-link'],
		['a tile that is a span', 'tile-as-span'],
	])('forces no colour onto %s', (name, id) => {
		expect(matching($(id))).toEqual([])
	})

	it.each([
		['a div', 'plain-div'],
		['a span', 'plain-span'],
		['a link', 'plain-link'],
	])('still forces the colour onto %s outside a tile', (name, id) => {
		expect(matching($(id)).length).toBeGreaterThan(0)
	})

	it('still forces the link colour from theme.css onto a link outside a tile', () => {
		expect(
			matching($('plain-link')).some((m) =>
				m.startsWith('css/systems/nldesign/theme.css'),
			),
		).toBe(true)
	})
})
