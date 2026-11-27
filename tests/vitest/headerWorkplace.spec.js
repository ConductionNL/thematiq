/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * css/header-workplace.css lays the bar out as the DqKop board: the search
 * field centred, the bell and the contacts menu as 44px round buttons, a
 * divider before the person.
 *
 * @spec openspec/specs/workplace-layout/spec.md
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
 * @param {string} [sheet] The stylesheet, relative to the app.
 * @return {Record<string, string>} The merged declarations.
 */
function declsOf(selector, media = '', sheet = 'css/header-workplace.css') {
	const decls = {}
	const css = fs.readFileSync(path.join(ROOT, sheet), 'utf8')
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
			decls[d.prop] =
				d.value.replace(/\s+/g, ' ') + (d.important ? ' !important' : '')
		})
	})
	return decls
}

const BAR = 'body:not(#body-login) #header'

describe('the DqKop bar', () => {
	it('pads the bar 14px at the start and 20px at the end, without gaps between its parts', () => {
		expect(declsOf(BAR)).toMatchObject({
			gap: '0',
			'padding-inline': '14px 20px',
		})
	})

	it('centres a 560px search field in the bar, and lets it follow the grid on a narrow screen', () => {
		expect(declsOf(`${BAR} .unified-search-menu`)).toMatchObject({
			position: 'absolute',
			'inset-inline-start': 'calc(50% - 283px)',
			width: '560px',
		})
		expect(
			declsOf(`${BAR} .unified-search-menu`, '(max-width: 1240px)'),
		).toMatchObject({
			position: 'static',
			'max-width': '560px',
		})
	})

	it('shows the contacts menu and draws the bell and the contacts as 44px round buttons', () => {
		expect(declsOf(`${BAR} .header-end > #contactsmenu`).display).toBe('flex')
		const button = declsOf(`${BAR} #contactsmenu .header-menu__trigger`)
		expect(button).toMatchObject({
			width: '44px !important',
			height: '44px !important',
			'border-radius': 'var(--thematiq-icon-radius, 22px) !important',
		})
		expect(declsOf(`${BAR} #notifications .header-menu__trigger`)).toEqual(
			button,
		)
	})

	// @spec openspec/specs/apps-menu-settings/spec.md#requirement-the-shape-applies-to-every-icon-of-the-apps-menu-and-the-top-bar-req-tqms-001
	it('gives the bell and the contacts menu the hover, focus, active and open background of the bar', () => {
		;['#notifications', '#contactsmenu'].forEach((id) => {
			;[
				`${BAR} ${id} .header-menu__trigger:hover`,
				`${BAR} ${id} .header-menu__trigger:focus-visible`,
				`${BAR} ${id} .header-menu__trigger:active`,
				`${BAR} ${id} .header-menu--opened .header-menu__trigger`,
				`${BAR} ${id}.header-menu--opened .header-menu__trigger`,
			].forEach((selector) => {
				expect(declsOf(selector)['background-color'], selector).toBe(
					'var(--color-background-hover) !important',
				)
			})
			expect(
				declsOf(`${BAR} ${id} .header-menu__trigger:focus-visible`).outline,
			).toMatch(/^2px solid var\(--nldesign-color-primary/)
		})
		;[
			`${BAR} .thematiq-quick-add__button:hover`,
			`${BAR} .thematiq-quick-add__button:focus-visible`,
			`${BAR} .thematiq-quick-add__button:active`,
		].forEach((selector) => {
			expect(declsOf(selector).background, selector).toBe(
				'var(--color-background-hover)',
			)
		})
	})

	it('draws the divider before the person, after whichever control comes last', () => {
		expect(
			declsOf(`${BAR} .header-end > :has(+ #user-menu)::after`),
		).toMatchObject({
			width: '1px',
			height: '28px',
			'margin-inline': '12px 6px',
			'background-color': 'var(--color-border)',
		})
	})

	it("reads the brand block in the bar in the light bar's muted text and border colours", () => {
		// Important: the nldesign theme forces the header colour on `#header *`.
		expect(declsOf(`${BAR} .cn-brand-bar__caption`).color).toBe(
			'var(--color-text-maxcontrast) !important',
		)
		expect(declsOf(`${BAR} .cn-brand-bar__divider`).background).toBe(
			'var(--color-border)',
		)
	})

	it('keeps the bar inside a phone screen (LpMobiel)', () => {
		expect(
			declsOf(
				'body:not(#body-login) #header a.thematiq-brand',
				'(max-width: 512px)',
			).width,
		).toBe('auto')
		expect(
			declsOf(
				'body:not(#body-login) #header .thematiq-brand__caption',
				'(max-width: 512px)',
			).display,
		).toBe('none')
		expect(
			declsOf(
				'body:not(#body-login) #header .header-end > #user-menu',
				'(max-width: 512px)',
			).flex,
		).toBe('none')
		expect(
			declsOf(
				'body:not(#body-login) #header .thematiq-brand__text',
				'(max-width: 420px)',
			).display,
		).toBe('none')
	})
})

// @spec openspec/specs/apps-menu-settings/spec.md#requirement-the-shape-applies-to-every-icon-of-the-apps-menu-and-the-top-bar-req-tqms-001
describe('the menu shape on every icon of the bar and the menu', () => {
	const MENU = 'css/app-menu.css'
	const SQUARE = ":root[data-thematiq-shape='square']"
	const POPOVER = 'body:not(#body-login) .app-menu__popover-base'

	it('sets the radius variable under the square root only, to the menu icons\' radius', () => {
		expect(declsOf(SQUARE, '', MENU)).toEqual({
			'--thematiq-icon-radius': 'var(--border-radius-element, 8px)',
		})
		const css = fs.readFileSync(path.join(ROOT, MENU), 'utf8')
		const setters = []
		postcss.parse(css).walkDecls('--thematiq-icon-radius', (d) => {
			setters.push(d.parent.selector)
		})
		expect(setters).toEqual([SQUARE])
	})

	it('gives the app icons in the menu the square under the square root', () => {
		expect(
			declsOf(`${SQUARE} ${POPOVER} .app-item__circle`, '', MENU),
		).toEqual({ 'border-radius': 'var(--thematiq-icon-radius)' })
	})

	it('keeps every other icon round without the variable', () => {
		const round = [
			[MENU, `${POPOVER} .thematiq-app-menu__create-icon`, '50%'],
			[MENU, `${POPOVER} .thematiq-app-menu__external .app-item__circle`, '50%'],
			[
				MENU,
				'body:not(#body-login) .thematiq-app-pins__ghost > .thematiq-app-pins__icon',
				'50%',
			],
			[undefined, `${BAR} #notifications .header-menu__trigger`, '22px !important'],
			[undefined, `${BAR} #contactsmenu .header-menu__trigger`, '22px !important'],
			[undefined, `${BAR} .thematiq-quick-add__button`, '22px'],
			[undefined, `${BAR} .thematiq-quick-add__mark`, '50%'],
			[
				undefined,
				`${BAR}[data-thematiq-user-chip] #user-menu > .header-menu__trigger`,
				'24px !important',
			],
		]
		round.forEach(([sheet, selector, radius]) => {
			const value = radius.replace(' !important', '')
			const important = radius.endsWith('!important') ? ' !important' : ''
			expect(declsOf(selector, '', sheet)['border-radius'], selector).toBe(
				`var(--thematiq-icon-radius, ${value})${important}`,
			)
		})
	})

	it('squares the other icon buttons at the end of the bar, the search field excepted', () => {
		const decls = declsOf(
			`${SQUARE} ${BAR} .header-end .header-menu__trigger:not(.unified-search-menu *)`,
			'',
			MENU,
		)
		expect(decls).toEqual({
			'border-radius': 'var(--thematiq-icon-radius) !important',
		})
		expect(
			declsOf(
				`${SQUARE} ${BAR} .header-end .button-vue--icon-only:not(.unified-search-menu *)`,
				'',
				MENU,
			),
		).toEqual(decls)
	})
})
