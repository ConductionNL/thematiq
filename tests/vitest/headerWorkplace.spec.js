/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * css/header-workplace.css lays the bar out as the DqKop board: the search
 * field centred, the bell and the contacts menu as 44px round buttons, a
 * divider before the person.
 *
 * @spec openspec/changes/workplace-header-dqkop/specs/workplace-layout/spec.md
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { describe, expect, it } from 'vitest'

const ROOT = path.resolve(__dirname, '../..')

/**
 * The declarations of every rule whose selector list holds `selector`,
 * outside media queries unless `media` names one.
 *
 * @param {string} selector The selector, as written in the sheet.
 * @param {string} [media] The media query params, when the rule sits in one.
 * @return {Record<string, string>} The merged declarations.
 */
function declsOf(selector, media = '') {
	const decls = {}
	const css = fs.readFileSync(path.join(ROOT, 'css/header-workplace.css'), 'utf8')
	postcss.parse(css).walkRules((rule) => {
		const inMedia = rule.parent?.type === 'atrule' ? rule.parent.params : ''
		if (inMedia !== media) {
			return
		}
		const selectors = rule.selectors.map((s) => s.replace(/\s+/g, ' ').trim())
		if (!selectors.includes(selector)) {
			return
		}
		rule.walkDecls((d) => {
			decls[d.prop] = d.value.replace(/\s+/g, ' ') + (d.important ? ' !important' : '')
		})
	})
	return decls
}

const BAR = 'body:not(#body-login) #header'

describe('the DqKop bar', () => {
	it('pads the bar 14px at the start and 20px at the end, without gaps between its parts', () => {
		expect(declsOf(BAR)).toMatchObject({ gap: '0', 'padding-inline': '14px 20px' })
	})

	it('centres a 560px search field in the bar, and lets it follow the grid on a narrow screen', () => {
		expect(declsOf(`${BAR} .unified-search-menu`)).toMatchObject({
			position: 'absolute',
			'inset-inline-start': 'calc(50% - 283px)',
			width: '560px',
		})
		expect(declsOf(`${BAR} .unified-search-menu`, '(max-width: 1240px)')).toMatchObject({
			position: 'static',
			'max-width': '560px',
		})
	})

	it('shows the contacts menu and draws the bell and the contacts as 44px round buttons', () => {
		expect(declsOf(`${BAR} .header-end > #contactsmenu`).display).toBe('flex')
		const button = declsOf(`${BAR} #contactsmenu .header-menu__trigger`)
		expect(button).toMatchObject({ width: '44px !important', height: '44px !important', 'border-radius': '22px !important' })
		expect(declsOf(`${BAR} #notifications .header-menu__trigger`)).toEqual(button)
	})

	it('draws the divider before the person, after whichever control comes last', () => {
		expect(declsOf(`${BAR} .header-end > :has(+ #user-menu)::after`)).toMatchObject({
			width: '1px',
			height: '28px',
			'margin-inline': '12px 6px',
			'background-color': 'var(--color-border)',
		})
	})

	it('reads the brand block in the bar in the light bar\'s muted text and border colours', () => {
		expect(declsOf(`${BAR} .cn-brand-bar__caption`).color).toBe('var(--color-text-maxcontrast)')
		expect(declsOf(`${BAR} .cn-brand-bar__divider`).background).toBe('var(--color-border)')
	})
})
