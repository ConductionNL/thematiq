/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Zuiddrecht's workplace type scale and keyboard ring, as the DqTokens board
 * draws them: page title 28px, card title 17px, Nextcloud's own text and small
 * text, and a 2px ring in the opaque action blue that the light workplace
 * draws from a token of the set, with the ring of any other set as fallback.
 *
 * @spec openspec/changes/zuiddrecht-workplace-type-and-focus/specs/zuiddrecht-token-set/spec.md#requirement-the-set-declares-the-dqtokens-type-scale-req-tqzd-001
 * @spec openspec/changes/zuiddrecht-workplace-type-and-focus/specs/zuiddrecht-token-set/spec.md#requirement-keyboard-focus-shows-a-2-px-blue-ring-req-tqzd-002
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { describe, expect, it } from 'vitest'

const ROOT = path.resolve(__dirname, '../..')
const read = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8')

/**
 * Every custom property the set declares, wherever it declares it.
 *
 * @param {string} css The stylesheet.
 * @return {Record<string, string>} Property to raw value.
 */
function declarations(css) {
	const out = {}
	postcss.parse(css).walkDecls(/^--/, (decl) => {
		out[decl.prop] = decl.value.trim()
	})
	return out
}

/**
 * WCAG relative luminance of a #RRGGBB colour.
 *
 * @param {string} hex The colour.
 * @return {number} The luminance.
 */
function luminance(hex) {
	const channels = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255)
	const [r, g, b] = channels.map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4))
	return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

/**
 * WCAG contrast ratio of two #RRGGBB colours.
 *
 * @param {string} a One colour.
 * @param {string} b The other.
 * @return {number} The ratio.
 */
function contrast(a, b) {
	const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x)
	return (hi + 0.05) / (lo + 0.05)
}

const SET = declarations(read('css/tokens/zuiddrecht.css'))

describe('Zuiddrecht declares the DqTokens type scale', () => {
	it('sets the page title at 28px and the card title at 17px', () => {
		expect(SET['--nldesign-component-heading-1-font-size']).toBe('28px')
		expect(SET['--nldesign-component-heading-3-font-size']).toBe('17px')
	})

	it('leaves Nextcloud\'s text and small text sizes alone', () => {
		expect(SET).not.toHaveProperty('--default-font-size')
		expect(SET).not.toHaveProperty('--font-size-small')
	})

	it('keeps meta text in the muted #5E6168', () => {
		expect(SET['--nldesign-color-text-muted']).toBe('#5E6168')
	})
})

describe('the light workplace draws Zuiddrecht\'s keyboard ring', () => {
	const ring = SET['--nldesign-color-focus-ring']

	const ringRules = () => {
		const rules = []
		postcss.parse(read('css/workplace-layout.css')).walkDecls('outline', (decl) => {
			if (decl.parent.selector.includes(':focus-visible')) {
				rules.push(decl)
			}
		})
		return rules
	}

	it('declares the ring as the opaque action blue', () => {
		expect(ring).toBe('#3669A5')
		expect(SET['--nldesign-color-primary']).toBe(ring)
	})

	it('reaches 3:1 on white and on the workspace', () => {
		expect(contrast(ring, '#FFFFFF')).toBeGreaterThanOrEqual(3)
		expect(contrast(ring, '#F5F6F8')).toBeGreaterThanOrEqual(3)
	})

	it('draws a 2px solid ring from the token, with the ring of the system and then Nextcloud\'s as fallback', () => {
		const rules = ringRules()
		expect(rules).toHaveLength(1)
		const value = rules[0].value.replace(/\s+/g, ' ')
		expect(value).toMatch(/^2px solid var\( ?--nldesign-color-focus-ring, ?var\( ?--nldesign-focus-ring-color, ?var\( ?--color-main-text ?\) ?\) ?\)$/)
		expect(rules[0].important).toBe(true)
	})

	it('reaches the same elements as the design system\'s ring, NcButton included, and not the login page', () => {
		const selector = ringRules()[0].parent.selector.replace(/\s+/g, ' ')
		expect(selector).toContain(':where(body:not(#body-login)) *:focus-visible')
		expect(selector).toContain('body .button-vue.button-vue:focus-visible')
	})

	it('writes no colour literal in the layout sheet\'s ring rule', () => {
		expect(ringRules()[0].value).not.toMatch(/#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(/i)
	})
})
