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

/**
 * The selectors of every rule that makes a ground transparent, with the
 * position of the rule in the sheet.
 *
 * @return {Array<{selector: string, index: number}>} The selectors.
 */
function clearSelectors() {
	const css = postcss.parse(fs.readFileSync(path.join(root, FILE), 'utf8'))
	const out = []
	let index = 0
	css.walkRules((rule) => {
		index++
		let clears = false
		rule.walkDecls(/^background(-color)?$/, (d) => {
			if (d.value === 'transparent') {
				clears = true
			}
		})
		if (clears) {
			out.push(...rule.selectors.map((s) => ({ selector: s.trim(), index })))
		}
	})
	return out
}

const grounds = new JSDOM(`<body><div id="content">
	<div id="ground-wrapper" class="cn-widget-wrapper cn-widget-wrapper--borderless">
		<div id="ground-content" class="cn-widget-wrapper__content">
			<div id="greeting" class="cn-header-widget cn-header-widget--plain cn-header-widget--ground"></div>
		</div>
	</div>
	<div id="banner-wrapper" class="cn-widget-wrapper cn-widget-wrapper--borderless">
		<div id="banner-content" class="cn-widget-wrapper__content">
			<section id="banner" class="cn-banner-widget cn-banner-widget--attention"></section>
		</div>
	</div>
	<div id="tile-wrapper" class="cn-widget-wrapper cn-widget-wrapper--borderless">
		<div id="tile-content" class="cn-widget-wrapper__content">
			<div id="tile" class="cn-stat-widget"></div>
		</div>
	</div>
	<div id="cal-wrapper" class="cn-widget-wrapper cn-widget-wrapper--borderless">
		<div id="cal-content" class="cn-widget-wrapper__content">
			<div id="cal" class="cn-calendar-widget"></div>
			<div id="dash-tile" class="cn-dash-tile-widget"></div>
		</div>
	</div>
	<div id="card-wrapper" class="cn-widget-wrapper">
		<div id="card-content" class="cn-widget-wrapper__content">
			<div id="plain-greeting" class="cn-header-widget cn-header-widget--plain"></div>
		</div>
	</div>
</div></body>`).window.document

/**
 * Whether a transparent rule matches the element.
 *
 * @param {string} id The element id.
 * @return {boolean} Matched.
 */
function cleared(id) {
	const el = grounds.getElementById(id)
	return clearSelectors().some(({ selector }) => el.matches(selector))
}

describe('a widget that asks for no ground', () => {
	it('draws a greeting on the page ground without a white box around it', () => {
		for (const id of ['ground-wrapper', 'ground-content', 'greeting']) {
			expect(cleared(id), id).toBe(true)
		}
	})

	it('draws a borderless attention banner as its own card, with no second card under it', () => {
		expect(cleared('banner-wrapper')).toBe(true)
		expect(cleared('banner-content')).toBe(true)
		// The banner keeps its own card.
		expect(cleared('banner')).toBe(false)
	})

	it('draws any borderless widget on the page ground: its wrapper, content and the widget in it', () => {
		for (const id of [
			'tile-wrapper',
			'tile-content',
			'tile',
			'cal-wrapper',
			'cal-content',
		]) {
			expect(cleared(id), id).toBe(true)
		}
	})

	it('leaves a widget that draws its own card its card', () => {
		for (const id of ['banner', 'cal', 'dash-tile']) {
			expect(cleared(id), id).toBe(false)
		}
	})

	it('leaves a widget in a bordered card the ground it has today', () => {
		for (const id of ['card-wrapper', 'card-content', 'plain-greeting']) {
			expect(cleared(id), id).toBe(false)
		}
	})

	it('comes after the solid rule, which it must beat at the same weight', () => {
		const css = postcss.parse(fs.readFileSync(path.join(root, FILE), 'utf8'))
		let solid = 0
		let index = 0
		css.walkRules((rule) => {
			index++
			if (
				rule.selectors.some((s) => s.startsWith("[class*='widget']:not("))
				&& rule.toString().includes('--color-main-background')
			) {
				solid = index
			}
		})
		expect(solid).toBeGreaterThan(0)
		for (const { index: at } of clearSelectors().filter(({ selector }) =>
			selector.includes('cn-widget-wrapper--borderless'),
		)) {
			expect(at).toBeGreaterThan(solid)
		}
	})
})

/**
 * The specificity of one selector as [ids, classes, elements], with
 * `:not()`, `:is()` and `:has()` counting their most specific argument.
 * Enough for the selectors in this sheet; not a general parser.
 *
 * @param {string} selector The selector.
 * @return {number[]} The three counts.
 */
function specificity(selector) {
	let rest = selector
	const total = [0, 0, 0]
	const add = (t) => t.forEach((v, i) => (total[i] += v))
	const fn = /:(not|is|has)\(/
	let m
	while ((m = fn.exec(rest)) !== null) {
		let depth = 1
		let i = m.index + m[0].length
		const begin = i
		while (depth > 0) {
			if (rest[i] === '(') depth++
			if (rest[i] === ')') depth--
			i++
		}
		const args = rest.slice(begin, i - 1).split(/,(?![^(]*\))/)
		const best = args
			.map((a) => specificity(a.trim()))
			.sort((a, b) => b[0] - a[0] || b[1] - a[1] || b[2] - a[2])[0]
		add(best)
		rest = rest.slice(0, m.index) + ' ' + rest.slice(i)
	}
	rest = rest.replace(/\[[^\]]*\]/g, () => {
		total[1]++
		return ' '
	})
	total[0] += (rest.match(/#[\w-]+/g) || []).length
	total[1] += (rest.match(/\.[\w-]+/g) || []).length
	total[1] += (rest.match(/:(?!:)[\w-]+/g) || []).length
	total[2] += (
		rest.replace(/[#.:][\w-]+/g, ' ').match(/(^|[\s>+~])[a-z][\w-]*/gi) || []
	).length
	return total
}

describe('a transparent ground beats the solid rule', () => {
	it('outweighs it wherever both match', () => {
		const solid = specificity(
			"[class*='widget']:not([class*='widget__']):not(#header *)",
		)
		expect(solid).toEqual([1, 2, 0])
		for (const { selector } of clearSelectors().filter(({ selector }) =>
			selector.includes('cn-'),
		)) {
			const own = specificity(selector)
			const beats =
				own[0] > solid[0] || (own[0] === solid[0] && own[1] >= solid[1])
			expect(beats, `${selector} scores ${own}`).toBe(true)
		}
	})
})
