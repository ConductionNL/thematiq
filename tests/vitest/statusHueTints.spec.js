/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workplace sets paint a status colour on the boards' tints.
 *
 * dossiq draws a status pill as the NL Design System hue's light tint
 * (`--nl-color-<hue>-light`) with the hue mixed 65% into the ink as its text.
 * Without the tint tokens the pill fell back to the NL Design System's own
 * tints, which differ from the boards' (purple #F2EAF5 against #E8E6F6). The
 * five workplace sets declare the boards' tints, and the text reaches AA on
 * every one, in the light and in both dark scopes.
 *
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/zuiddrecht-token-set/spec.md
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { describe, expect, it } from 'vitest'

const ROOT = path.resolve(__dirname, '../..')
const read = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8')

const SETS = ['zuiddrecht', 'vaartveld', 'wilgenboom', 'esdoornveen', 'warmtepompacademie']

/** The boards' tints; blue is the set's own received tint for the four school sets. */
const TINTS = { purple: '#E8E6F6', green: '#E7F3EA', orange: '#FFF4DE', red: '#FCEDEC', teal: '#E3F1F3', grey: '#F0F1F3' }
const BLUE = { zuiddrecht: '#EAF0F7', vaartveld: '#E8EEFC', wilgenboom: '#E8F1EB', esdoornveen: '#F0EAF7', warmtepompacademie: '#E3F1F3' }

/** The hue a status badge mixes into the ink: dossiq's NL Design System fallbacks. */
const HUES = { blue: '#0b71ab', purple: '#762f8e', green: '#39870c', orange: '#e17000', red: '#d52b1e', teal: '#1d5e66', grey: '#696969' }

/**
 * Declarations per rule selector, later rules winning.
 *
 * @param {string} css The stylesheet.
 * @param {(selector: string, parent: object) => boolean} pick Which rules count.
 * @return {Record<string, string>} Property to value.
 */
function decls(css, pick) {
	const out = {}
	postcss.parse(css).walkRules((rule) => {
		if (pick(rule.selector, rule.parent)) {
			rule.walkDecls(/^--/, (d) => {
				out[d.prop] = d.value.trim()
			})
		}
	})
	return out
}

const rgb = (hex) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16))
const lum = (c) => c.map((v) => v / 255).map((v) => (v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4)).reduce((s, v, i) => s + v * [0.2126, 0.7152, 0.0722][i], 0)
const ratio = (a, b) => {
	const [hi, lo] = [lum(a), lum(b)].sort((x, y) => y - x)
	return (hi + 0.05) / (lo + 0.05)
}
const badgeText = (hue, ink) => rgb(HUES[hue]).map((v, i) => Math.round(v * 0.65 + rgb(ink)[i] * 0.35))

describe.each(SETS)('%s status tints', (set) => {
	const light = decls(read(`css/tokens/${set}.css`), (selector) => selector.trim() === ':root')

	it('declares the boards\' tint for every hue', () => {
		expect(light['--nl-color-blue-light']).toBe(BLUE[set])
		for (const [hue, tint] of Object.entries(TINTS)) {
			expect(light[`--nl-color-${hue}-light`], hue).toBe(tint)
		}
	})

	it('keeps the badge text at AA on every tint in the light', () => {
		const ink = light['--nldesign-color-text']
		for (const hue of Object.keys(HUES)) {
			expect(ratio(badgeText(hue, ink), rgb(light[`--nl-color-${hue}-light`])), hue).toBeGreaterThanOrEqual(4.5)
		}
	})

	it.each([
		['the system preference', (selector, parent) => parent.type === 'atrule' && selector.includes(':not([data-theme-dark])')],
		['the explicit dark theme', (selector, parent) => parent.type !== 'atrule' && selector.includes('[data-theme-dark]')],
	])('keeps the badge text at AA on every tint under %s', (_scope, pick) => {
		const dark = { ...decls(read(`css/tokens/dark/${set}.css`), pick), ...decls(read(`css/token-overrides/${set}.css`), pick) }
		const ink = dark['--nldesign-color-text']
		expect(ink, 'the dark scope declares its ink').toMatch(/^#[0-9a-f]{6}$/i)
		for (const hue of Object.keys(HUES)) {
			const tint = dark[`--nl-color-${hue}-light`]
			expect(tint, hue).toMatch(/^#[0-9a-f]{6}$/i)
			expect(ratio(badgeText(hue, ink), rgb(tint)), hue).toBeGreaterThanOrEqual(4.5)
		}
	})
})
