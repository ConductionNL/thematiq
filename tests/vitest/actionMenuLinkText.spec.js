/**
 * @vitest-environment jsdom
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * An action menu link item (NcActionLink, `a.action-link`) reads as a menu
 * entry: the id-level link rule in element-overrides.css paints it through
 * `--nldesign-color-on-surface`, which the item sets to the main text colour,
 * and a class-level rule takes away theme.css's `!important` underline.
 *
 * @spec openspec/changes/action-menu-links-keep-text-colour/specs/css-architecture/spec.md
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import postcss from 'postcss'
import { beforeAll, describe, it, expect } from 'vitest'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const read = (file) => postcss.parse(fs.readFileSync(path.join(root, file), 'utf8'))
const overrides = read('css/systems/nldesign/element-overrides.css')

/**
 * The declarations of `prop` in element-overrides.css whose rule matches the element.
 *
 * @param {Element} element The element.
 * @param {string} prop The property.
 * @return {Array<{selector: string, value: string, important: boolean}>} The matches.
 */
function declarations(element, prop) {
	const out = []
	overrides.walkDecls(prop, (decl) => {
		const rule = decl.parent
		if (rule.type !== 'rule' || (rule.parent.type === 'atrule' && rule.parent.name !== 'media')) {
			return
		}
		const hit = rule.selectors.find((selector) => {
			try {
				return element.matches(selector.trim())
			} catch {
				return false
			}
		})
		if (hit) {
			out.push({ selector: hit.trim(), value: decl.value, important: decl.important })
		}
	})
	return out
}

const $ = (id) => document.getElementById(id)

beforeAll(() => {
	document.body.innerHTML = `
		<div id="app"><div id="content"><div class="app-content">
			<ul role="menu">
				<li class="action"><a id="menu-link" class="action-link focusable" href="#"><span class="action-link__text">Een bug melden</span></a></li>
			</ul>
			<p><a id="prose-link" href="#">Documentatie</a></p>
		</div></div></div>`
})

describe('an action menu link item reads as a menu entry', () => {
	it('points the ink the link rule reads at the main text colour', () => {
		const ink = declarations($('menu-link'), '--nldesign-color-on-surface')
		expect(ink.map((entry) => entry.value)).toContain('var(--color-main-text)')
	})

	it('is still painted only through that ink by the id-level link rule', () => {
		const forced = declarations($('menu-link'), 'color').filter((entry) => entry.important && entry.selector.includes('#'))
		expect(forced.length).toBeGreaterThan(0)
		forced.forEach((entry) => expect(entry.value).toMatch(/^var\(--nldesign-color-on-surface/))
	})

	it('takes away the underline with a class-level rule', () => {
		const decoration = declarations($('menu-link'), 'text-decoration')
		expect(decoration).toContainEqual({ selector: '.action-link', value: 'none', important: true })
	})

	it('leaves a link in prose alone', () => {
		expect(declarations($('prose-link'), '--nldesign-color-on-surface')).toEqual([])
		expect(declarations($('prose-link'), 'text-decoration').filter((entry) => entry.value === 'none')).toEqual([])
	})
})
