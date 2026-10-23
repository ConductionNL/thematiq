/**
 * @vitest-environment jsdom
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A tertiary button in the app navigation keeps the navigation's text colour.
 *
 * The tertiary-button scope in component-scopes.css points `--color-main-text`
 * at `--nldesign-component-button-color`, which most sets resolve to their
 * brand primary. NcAppNavigationSettings draws its fold-out toggle
 * ("Geavanceerd", "Instellingen") as a tertiary NcButton, so it turned blue
 * next to footer entries in the main text colour. element-overrides.css hands
 * a tertiary button inside the navigation the navigation's own value back.
 *
 * @spec openspec/specs/css-architecture/spec.md#scenario-app-specific-exclusions
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import postcss from 'postcss'
import { beforeAll, describe, it, expect } from 'vitest'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')

/**
 * The selectors in a stylesheet whose rule sets `--color-main-text`, with the
 * value they set.
 *
 * @param {string} file Path relative to the repository root.
 * @return {{selector: string, value: string}[]} The selectors and values.
 */
function mainTextRules(file) {
	const out = []
	postcss.parse(fs.readFileSync(path.join(root, file), 'utf8')).walkDecls('--color-main-text', (decl) => {
		if (decl.parent.type !== 'rule') {
			return
		}
		for (const selector of decl.parent.selectors) {
			out.push({ selector: selector.trim(), value: decl.value.trim() })
		}
	})
	return out
}

/**
 * Whether an element matches a selector; a selector jsdom cannot parse does not.
 *
 * @param {Element} element The element.
 * @param {string} selector The selector.
 * @return {boolean} Whether it matches.
 */
function matches(element, selector) {
	try {
		return element.matches(selector)
	} catch {
		return false
	}
}

const $ = (id) => document.getElementById(id)

beforeAll(() => {
	document.body.innerHTML = `
		<div id="content">
			<div id="app-navigation-vue" class="app-navigation">
				<div class="_container_x"><div class="_header_x">
					<button id="nav-settings" class="button-vue button-vue--tertiary button-vue--wide">Geavanceerd</button>
				</div></div>
			</div>
			<div class="app-content">
				<button id="content-tertiary" class="button-vue button-vue--tertiary">Annuleren</button>
			</div>
		</div>`
})

describe('a tertiary button in the app navigation', () => {
	it('gets the tertiary button colour from the component scope (the cause)', () => {
		const scoped = mainTextRules('css/component-scopes.css')
			.filter((rule) => matches($('nav-settings'), rule.selector))
		expect(scoped.some((rule) => rule.value.includes('--nldesign-component-button-color'))).toBe(true)
	})

	it('inherits the navigation text colour instead', () => {
		const handBack = mainTextRules('css/systems/nldesign/element-overrides.css')
			.filter((rule) => matches($('nav-settings'), rule.selector) && rule.value === 'inherit')
		expect(handBack.length).toBeGreaterThan(0)
	})

	it('leaves a tertiary button outside the navigation alone', () => {
		const handBack = mainTextRules('css/systems/nldesign/element-overrides.css')
			.filter((rule) => matches($('content-tertiary'), rule.selector))
		expect(handBack).toEqual([])
	})
})
