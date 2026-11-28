/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The differences the same-shell-every-page 6.3 compare found against the
 * boards (MlInbox, MlThread, TbBoard, TbCard, DqKop), each held to the board.
 *
 * @spec openspec/changes/same-shell-every-page/tasks.md#6-verification
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { describe, expect, it } from 'vitest'

const ROOT = path.resolve(__dirname, '../..')

/**
 * The declarations of the top-level rules whose selector, whitespace folded, is `selector`.
 *
 * @param {string} sheet The stylesheet, relative to the app.
 * @param {string} selector The selector, as written in the sheet.
 * @return {Record<string, string>} The merged declarations.
 */
function declsOf(sheet, selector) {
	const decls = {}
	const css = fs.readFileSync(path.join(ROOT, sheet), 'utf8')
	postcss.parse(css).walkRules((rule) => {
		if (rule.parent?.type !== 'root') {
			return
		}
		if (rule.selector.replace(/\s+/g, ' ').trim() !== selector) {
			return
		}
		rule.walkDecls((d) => {
			if (d.parent === rule) {
				decls[d.prop] = d.value + (d.important ? ' !important' : '')
			}
		})
	})
	return decls
}

describe('same-shell-every-page 6.3: the app pages as the boards draw them', () => {
	it("Mail's New message is 34px, like the shared new-item button (MlInbox)", () => {
		const decls = declsOf(
			'css/workplace-apps/mail.css',
			':is(#content, #content-vue).app-mail .mail-navigation__new-message-button .button-vue--vue-secondary',
		)
		expect(decls).toMatchObject({
			'min-height': '34px !important',
			height: '34px !important',
		})
	})

	it("Mail's selected conversation keeps the tint and the 3px edge over Mail's white rows (MlInbox, MlThread)", () => {
		const decls = declsOf(
			'css/workplace-apps/mail.css',
			':is(#content, #content-vue).app-mail .envelope-list :is(.list-item__wrapper--active .list-item, .list-item--active)',
		)
		expect(decls).toEqual({
			'box-shadow': 'inset 3px 0 0 var(--color-primary-element)',
			'background-color': 'var(--color-primary-element-light) !important',
		})
		// It comes after the white row, which it has to beat.
		const css = fs.readFileSync(
			path.join(ROOT, 'css/workplace-apps/mail.css'),
			'utf8',
		)
		expect(css.indexOf('.list-item--active) {')).toBeGreaterThan(
			css.indexOf('.envelope-list .list-item {'),
		)
	})

	it("Deck's board title is 20px with the open-card count under it (TbBoard)", () => {
		expect(
			declsOf(
				'css/workplace-apps/deck.css',
				':is(#content, #content-vue).app-deck .controls .board-title[data-thematiq-open] h2',
			)['font-size'],
		).toBe('20px !important')
	})

	it("a sidebar in a modal, Deck's bigger view, keeps the shared 20px title (TbCard)", () => {
		expect(
			declsOf(
				'css/workplace-layout.css',
				':where(body:not(#body-login)) .modal-container #app-sidebar-vue .app-sidebar-header__mainname',
			),
		).toEqual({
			'font-size': '20px !important',
			'font-weight': '700 !important',
			'line-height': '26px !important',
		})
	})

	it('Quick add rests in the primary light tint, the plus in its text colour (DqKop)', () => {
		expect(
			declsOf(
				'css/header-workplace.css',
				'body:not(#body-login) #header .thematiq-quick-add__button',
			),
		).toMatchObject({
			background: 'var(--color-primary-element-light)',
			color: 'var(--color-primary-element-light-text) !important',
		})
	})

	it('the search field is 352px, still centred, while the pins carry their names (DqKop)', () => {
		expect(
			declsOf(
				'css/header-workplace.css',
				'body:not(#body-login) #header:has(.thematiq-app-pins__pin[data-thematiq-titled]) .unified-search-menu',
			),
		).toEqual({
			'inset-inline-start': 'calc(50% - 179px)',
			width: '352px',
		})
	})
})
