/**
 * SPDX-FileCopyrightText: 2026 Conduction / NL Design System Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The La Suite unified-search label reaches WCAG 2.2 AA in light mode
 * (thematiq#271).
 *
 * `#header .unified-search-input__label` is 14px / 500, normal text, so SC
 * 1.4.3 asks 4.5:1. It reads `var(--lasuite-content-muted, <fallback>)`, and
 * that token is declared only inside the dark-mode blocks, so in light mode
 * the FALLBACK is what renders, on the gray-025 field. The fallback was
 * gray-500, the magnifier icon's step, which clears the icon's 3:1 non-text bar
 * and lands at 4.22:1 as text.
 *
 * Resolved here from the stylesheets themselves, on both ramps a La Suite page
 * can carry: `defaults.css` alone (cunningham) and `defaults.css` with
 * `brand-override.css` on top (lasuite). axe cannot be relied on for this: it
 * is not enabled in this repo's E2E job.
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import postcss from 'postcss'
import { describe, it, expect } from 'vitest'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const lasuite = (file) => path.join(root, 'css/systems/lasuite', file)

/**
 * Whether a rule only applies in dark mode.
 *
 * @param {import('postcss').Rule} rule The rule.
 * @return {boolean} True inside a dark media query or under a dark selector.
 */
function isDark(rule) {
	if (/dark/.test(rule.selector)) {
		return true
	}
	for (let node = rule.parent; node; node = node.parent) {
		if (node.type === 'atrule' && /dark/.test(node.params)) {
			return true
		}
	}
	return false
}

/**
 * The custom properties a light-mode page declares, from the given sheets in order.
 *
 * @param {string[]} files Stylesheet paths, later ones winning.
 * @return {Map<string, string>} Property name to raw value.
 */
function lightProperties(files) {
	const properties = new Map()
	for (const file of files) {
		postcss.parse(fs.readFileSync(file, 'utf8')).walkDecls(/^--/, (decl) => {
			if (decl.parent.type === 'rule' && isDark(decl.parent) === false) {
				properties.set(decl.prop, decl.value.trim())
			}
		})
	}
	return properties
}

/**
 * Resolve a value's `var()` chain, taking a fallback when a name is undeclared.
 *
 * @param {string} value The raw value.
 * @param {Map<string, string>} properties The declared properties.
 * @return {string} The literal it resolves to.
 */
function resolve(value, properties) {
	const match = value.match(/^var\(\s*(--[\w-]+)\s*(?:,\s*(.+))?\)$/)
	if (match === null) {
		return value.replace(/\s*!important$/, '').trim()
	}
	if (properties.has(match[1])) {
		return resolve(properties.get(match[1]), properties)
	}
	return resolve(match[2] ?? '', properties)
}

/**
 * WCAG contrast ratio of two #rrggbb colours.
 *
 * @param {string} first A hex colour.
 * @param {string} second A hex colour.
 * @return {number} The ratio, 1 to 21.
 */
function contrast(first, second) {
	const luminance = (hex) => {
		const [r, g, b] = [1, 3, 5].map((index) => {
			const channel = parseInt(hex.slice(index, index + 2), 16) / 255
			return channel <= 0.03928
				? channel / 12.92
				: ((channel + 0.055) / 1.055) ** 2.4
		})
		return 0.2126 * r + 0.7152 * g + 0.0722 * b
	}
	const [light, dark] = [luminance(first), luminance(second)].sort((a, b) => b - a)
	return (light + 0.05) / (dark + 0.05)
}

/**
 * The colour value the label rule declares.
 *
 * @return {string} The raw `color` value.
 */
function labelColour() {
	let value = null
	postcss
		.parse(fs.readFileSync(lasuite('element-overrides.css'), 'utf8'))
		.walkRules((rule) => {
			if (
				rule.selectors.includes('#header .unified-search-input__label')
				&& isDark(rule) === false
			) {
				rule.walkDecls('color', (decl) => {
					value = decl.value
				})
			}
		})
	return value
}

const RAMPS = {
	cunningham: [lasuite('defaults.css'), lasuite('element-overrides.css')],
	lasuite: [
		lasuite('defaults.css'),
		lasuite('brand-override.css'),
		lasuite('element-overrides.css'),
	],
}

describe('unified-search label contrast, light mode', () => {
	it('the contrast helper can fail', () => {
		expect(contrast('#74777c', '#f7f8f8')).toBeLessThan(4.5)
		expect(contrast('#000000', '#ffffff')).toBeCloseTo(21, 5)
	})

	it('finds the label rule', () => {
		expect(labelColour()).toMatch(/^var\(/)
	})

	for (const [ramp, files] of Object.entries(RAMPS)) {
		it(`reaches 4.5:1 on the gray-025 field on the ${ramp} ramp`, () => {
			const properties = lightProperties(files)
			const foreground = resolve(labelColour(), properties)
			const background = resolve('var(--lasuite-color-gray-025)', properties)

			expect(foreground).toMatch(/^#[0-9a-f]{6}$/i)
			expect(background).toMatch(/^#[0-9a-f]{6}$/i)
			expect(contrast(foreground, background)).toBeGreaterThanOrEqual(4.5)
		})
	}
})
