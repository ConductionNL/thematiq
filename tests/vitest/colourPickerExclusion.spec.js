/**
 * SPDX-FileCopyrightText: 2026 Conduction / NL Design System Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * No theme layer paints Nextcloud's own colour pickers (thematiq#611).
 *
 * Settings › Theming renders each colour field as a primary NcButton whose
 * background IS the colour being chosen: `ColorPickerField.vue` sets it inline
 * as the button's own `--color-primary-element`, with a text colour core
 * computes for legibility. Nextcloud 32 marked that button with
 * `data-admin-theming-setting-color-picker`, and the theme layers excluded it
 * by that attribute. Since the Vue 3 rewrite of the theming settings (in
 * Nextcloud 33, still so in 35.0.1) the button carries only a CSS-module class,
 * `_colorPickerField__button_<hash>`, so every exclusion matched nothing and
 * the primary-button fill painted over the swatch.
 *
 * The fixture is the button as Nextcloud 35.0.1 renders it (the class names
 * come from `dist/theming-settings-admin.mjs`). Every rule in every design
 * system stylesheet that sets a colour is matched against it and its
 * descendants, with interaction pseudo-classes stripped so hover and focus
 * fills are checked too.
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import postcss from 'postcss'
import { JSDOM } from 'jsdom'
import { describe, it, expect } from 'vitest'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')

const PICKER = `
<body id="body-settings">
	<div id="content">
		<div class="_colorPickerField_o0yey_1">
			<button type="button" class="button-vue button-vue--size-large button-vue--icon-and-text button-vue--primary _colorPickerField__button_o0yey_14">
				<span class="button-vue__wrapper">
					<span class="button-vue__icon"><span class="icon-vue"><svg><path d="M0 0"/></svg></span></span>
					<span class="button-vue__text">Primary color</span>
				</span>
			</button>
		</div>
	</div>
</body>`

const COLOUR_PROPERTIES = /^(background|background-color|color|fill|border-color)$/

/**
 * Every stylesheet a design system can load, plus the shared contrast layers.
 *
 * @return {string[]} Paths relative to the app root.
 */
function stylesheets() {
	const systems = JSON.parse(
		fs.readFileSync(path.join(root, 'design-systems.json'), 'utf8'),
	)
	const files = new Set(
		systems
			.flatMap((system) => system.stylesheets)
			.map((sheet) => `css/${sheet}.css`),
	)
	files.add('css/icon-contrast.css')
	files.add('css/error-contrast.css')

	return [...files].filter((file) => fs.existsSync(path.join(root, file)))
}

/**
 * The rules that would paint the picker or one of its children.
 *
 * A declaration that hands the colour back to the picker (`inherit`,
 * `currentcolor`) is not painting it, so it does not count.
 *
 * @return {string[]} `file:line selector` for each offending selector.
 */
function rulesPaintingThePicker() {
	const document = new JSDOM(PICKER).window.document
	const button = document.querySelector('button')
	const elements = [button, ...button.querySelectorAll('*')]
	const offenders = []

	for (const file of stylesheets()) {
		postcss
			.parse(fs.readFileSync(path.join(root, file), 'utf8'))
			.walkRules((rule) => {
				const paints = rule.nodes.some(
					(node) =>
						node.type === 'decl'
						&& COLOUR_PROPERTIES.test(node.prop)
						&& /^(inherit|currentcolor)$/i.test(node.value.trim())
							=== false,
				)
				if (paints === false) {
					return
				}

				for (const selector of rule.selectors) {
					if (selector.includes('::')) {
						continue
					}

					const anyState = selector.replace(
						/:(hover|focus-visible|focus-within|focus|active)\b/g,
						'',
					)
					if (elements.some((element) => element.matches(anyState))) {
						offenders.push(
							`${file}:${rule.source.start.line} ${selector}`,
						)
					}
				}
			})
	}

	return offenders
}

describe('colour picker exclusion (Nextcloud 33 and later)', () => {
	it('reads the stylesheets of every design system', () => {
		expect(stylesheets()).toContain('css/systems/nldesign/theme.css')
		expect(stylesheets()).toContain('css/systems/lasuite/element-overrides.css')
	})

	it('the fixture is a primary button, so the primary rules would reach it unexcluded', () => {
		const button = new JSDOM(PICKER).window.document.querySelector('button')
		expect(button.matches("button[class*='primary']")).toBe(true)
		expect(button.matches('.button-vue--primary')).toBe(true)
	})

	it('no theme rule paints the picker button or its children', () => {
		expect(rulesPaintingThePicker()).toEqual([])
	})

	// The exclusions are chained `:not(a):not(:where(b))` rather than one
	// `:not(a, b)` list. Same specificity, and no comma inside the parentheses:
	// tests/e2e/spec-coverage/selector-liveness.spec.ts splits selectorText on
	// every comma, so a list inside `:not()` would reach it as two broken
	// fragments and be reported dead.
	it('keeps commas out of the parentheses of La Suite element-overrides selectors', () => {
		const css = fs.readFileSync(
			path.join(root, 'css/systems/lasuite/element-overrides.css'),
			'utf8',
		)
		const nested = []
		postcss.parse(css).walkRules((rule) => {
			for (const selector of rule.selectors) {
				let depth = 0
				for (const character of selector) {
					depth += character === '(' ? 1 : character === ')' ? -1 : 0
					if (character === ',' && depth > 0) {
						nested.push(selector)
						break
					}
				}
			}
		})
		expect(nested).toEqual([])
	})
})
