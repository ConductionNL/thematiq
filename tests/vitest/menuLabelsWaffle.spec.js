/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Show menu labels reaches the Nextcloud 33+ waffle menu (#895).
 *
 * css/show-menu-labels.css only targeted the Nextcloud 32 header
 * (`nav.app-menu .app-menu-entry*`). From 33 the header is a waffle button, a
 * current-app button and a popover grid of `.app-item` tiles (core
 * src/components/AppMenu.vue and AppMenuItem.vue, read on stable35), so the
 * setting loaded a stylesheet that changed nothing.
 *
 * Core's own rules this file has to beat, copied from stable35:
 *   AppMenu.vue  `.app-menu__current-app { @media (max-width: 1024px) {
 *                display: none !important } }` (scoped, so one class plus one
 *                data attribute: specificity 0,2,0)
 *   AppMenuItem.vue `.app-item__label` (always shown, 12px, under the icon)
 *
 * The browser half lives in tests/e2e/spec-coverage/menu-labels.spec.ts; this
 * file reads the stylesheet, so it runs without a server.
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import postcss from 'postcss'
import { describe, it, expect } from 'vitest'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const css = postcss.parse(
	fs.readFileSync(path.join(root, 'css/show-menu-labels.css'), 'utf8'),
)

/** Core's scoped hide rule: `.app-menu__current-app[data-v-…]`. */
const CORE_HIDE_SPECIFICITY = [0, 2, 0]

/**
 * The declarations of every rule whose selector list contains `selector`.
 *
 * @param {string} selector The exact selector.
 * @return {Array<{rule: import('postcss').Rule, decls: Record<string, import('postcss').Declaration>}>} Matches.
 */
function rulesFor(selector) {
	const out = []
	css.walkRules((rule) => {
		if (rule.selectors.map((s) => s.trim()).includes(selector)) {
			const decls = {}
			rule.walkDecls((d) => {
				decls[d.prop] = d
			})
			out.push({ rule, decls })
		}
	})
	return out
}

/**
 * Specificity (ids, classes, types) of a selector without pseudo-elements.
 *
 * @param {string} selector The selector.
 * @return {number[]} [a, b, c].
 */
function specificity(selector) {
	const ids = (selector.match(/#[\w-]+/g) ?? []).length
	const classes = (selector.match(/\.[\w-]+|\[[^\]]+\]|:(?!:)[\w-]+/g) ?? [])
		.length
	const types = (
		selector
			.replace(/[#.][\w-]+|\[[^\]]+\]|:[\w-]+/g, ' ')
			.match(/\b[a-z][\w-]*/gi) ?? []
	).length
	return [ids, classes, types]
}

/**
 * Whether specificity a beats b.
 *
 * @param {number[]} a First.
 * @param {number[]} b Second.
 * @return {boolean} True when a wins.
 */
function beats(a, b) {
	for (let i = 0; i < 3; i++) {
		if (a[i] !== b[i]) {
			return a[i] > b[i]
		}
	}
	return false
}

/**
 * Assert one declaration exists with the value and `!important`.
 *
 * @param {Record<string, import('postcss').Declaration>} decls The rule's declarations.
 * @param {string} prop The property.
 * @param {string} value The expected value.
 */
function expectImportant(decls, prop, value) {
	expect(decls[prop], `${prop} is declared`).toBeDefined()
	expect(decls[prop].value).toBe(value)
	expect(decls[prop].important).toBe(true)
}

describe('show menu labels on the Nextcloud 33+ waffle menu (#895)', () => {
	it("keeps the current-app button shown at every width, over core's narrow-screen hide", () => {
		const selector = '#header nav.app-menu .app-menu__current-app'
		const found = rulesFor(selector)
		expect(found.length).toBe(1)
		const { rule, decls } = found[0]
		// Not inside a media query: it must hold below 1024px, where core hides it.
		expect(rule.parent.type).toBe('root')
		expectImportant(decls, 'display', 'flex')
		expect(beats(specificity(selector), CORE_HIDE_SPECIFICITY)).toBe(true)
	})

	it('shows the current app name next to the waffle', () => {
		const found = rulesFor('#header nav.app-menu .app-menu__current-app-name')
		expect(found.length).toBe(1)
		expectImportant(found[0].decls, 'display', 'inline-block')
		expectImportant(found[0].decls, 'visibility', 'visible')
		expectImportant(found[0].decls, 'opacity', '1')
	})

	it('keeps every grid tile label visible, matched outside #header', () => {
		const found = rulesFor('.app-menu__popover .app-item__label')
		expect(found.length).toBe(1)
		expectImportant(found[0].decls, 'display', 'block')
		expectImportant(found[0].decls, 'visibility', 'visible')
		expectImportant(found[0].decls, 'opacity', '1')
	})

	it('marks the active app tile by weight, as on Nextcloud 32', () => {
		const found = rulesFor(
			'.app-menu__popover .app-item--active .app-item__label',
		)
		expect(found.length).toBe(1)
		expectImportant(found[0].decls, 'font-weight', '600')
	})

	it('keeps the Nextcloud 32 rules', () => {
		expect(rulesFor('#header nav.app-menu .app-menu-entry__label').length).toBe(
			1,
		)
		expect(rulesFor('#header nav.app-menu .app-menu-icon').length).toBe(1)
	})
})
