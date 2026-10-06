/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The nldesign solid-background rule paints a widget, not the parts inside it.
 *
 * `[class*='widget']` also matches a BEM element such as
 * `.cn-stages-widget__bar`, and with the rule's ID-level specificity it
 * painted the stages bars the page background on dossiq's case page, so the
 * bars were drawn but invisible. This file reads the stylesheet and asks a
 * real DOM which elements the rule's selectors match, so it runs without a
 * server.
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import postcss from 'postcss'
import { JSDOM } from 'jsdom'
import { describe, it, expect } from 'vitest'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const FILE = 'css/systems/nldesign/element-overrides.css'

/**
 * The selectors of every rule in the sheet that sets a background colour.
 *
 * @return {string[]} The selectors.
 */
function backgroundSelectors() {
	const css = postcss.parse(fs.readFileSync(path.join(root, FILE), 'utf8'))
	const out = []
	css.walkRules((rule) => {
		let paints = false
		rule.walkDecls(/^background(-color)?$/, (d) => {
			if (d.value.includes('--color-main-background')) {
				paints = true
			}
		})
		if (paints) {
			out.push(...rule.selectors.map((s) => s.trim()))
		}
	})
	return out
}

const dom = new JSDOM(`<body>
	<div id="header"><div class="header-widget"></div></div>
	<div id="content">
		<section id="container" class="cn-stages-widget">
			<ol><li id="bar" class="cn-stages-widget__bar cn-stages-widget__bar--done"></li></ol>
		</section>
		<div id="wrapper" class="cn-widget-wrapper"></div>
		<div id="panel" class="app-sidebar-panel"></div>
	</div>
</body>`)
const doc = dom.window.document

/**
 * Whether any background selector of the sheet matches the element.
 *
 * @param {string} id The element id.
 * @return {boolean} Matched.
 */
function painted(id) {
	const el = doc.getElementById(id)
	return backgroundSelectors().some((sel) => {
		try {
			return el.matches(sel)
		} catch {
			return false
		}
	})
}

describe('the nldesign solid-background rule', () => {
	it('still paints a widget container and a panel', () => {
		expect(painted('container')).toBe(true)
		expect(painted('wrapper')).toBe(true)
		expect(painted('panel')).toBe(true)
	})

	it('leaves a widget part, such as a stages bar, its own colour', () => {
		expect(painted('bar')).toBe(false)
	})
})
