/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The nldesign solid-background rule paints Nextcloud's own panels and
 * widgets, and nothing an app or nextcloud-vue draws.
 *
 * A `[class*='widget']` catch-all with ID-level specificity painted every
 * element with "widget" in its class: the stages bars on dossiq's case page,
 * a borderless widget's ground, `.cn-reports-page__widgets`. This file reads
 * the stylesheet and asks a real DOM which elements the rule's selectors
 * match, so it runs without a server.
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
	<div id="header"><div id="header-widget" class="header-widget widget"></div></div>
	<div id="content">
		<div id="panel" class="panel"></div>
		<div id="nc-widget" class="dashboard-widget"></div>
		<div id="reference" class="widget-default"></div>
		<div id="reference-custom" class="widget-custom"></div>
		<div id="reference-access" class="widget-access"></div>
		<section id="stages" class="cn-stages-widget">
			<ol><li id="bar" class="cn-stages-widget__bar cn-stages-widget__bar--done"></li></ol>
		</section>
		<div id="wrapper" class="cn-widget-wrapper cn-widget-wrapper--borderless"></div>
		<div id="greeting" class="cn-header-widget cn-header-widget--ground"></div>
		<div id="report-widgets" class="cn-reports-page__widgets"></div>
		<div id="app-widget" class="dq-case-widget"></div>
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
	return backgroundSelectors().some((sel) => el.matches(sel))
}

describe('the nldesign solid-background rule', () => {
	it("paints Nextcloud's own panels and widgets", () => {
		for (const id of [
			'panel',
			'nc-widget',
			'reference',
			'reference-custom',
			'reference-access',
		]) {
			expect(painted(id), id).toBe(true)
		}
	})

	it('leaves the header alone', () => {
		expect(painted('header-widget')).toBe(false)
	})

	it('leaves every app and nextcloud-vue widget, and the parts inside one, the ground it draws', () => {
		for (const id of [
			'stages',
			'bar',
			'wrapper',
			'greeting',
			'report-widgets',
			'app-widget',
		]) {
			expect(painted(id), id).toBe(false)
		}
	})

	it('selects no element by a class-name substring of "widget"', () => {
		for (const sel of backgroundSelectors()) {
			expect(sel).not.toMatch(/\[class\*=['"]?[^\]]*widget/)
		}
	})
})
