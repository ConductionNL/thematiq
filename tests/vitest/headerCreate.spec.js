/**
 * @vitest-environment jsdom
 *
 * The Quick add button in the workplace top bar (MlInbox, ClWeek, TbBoard):
 * js/header-create.js draws it before the bell on Mail, Calendar and Deck,
 * and its menu lists the apps menu's own Create actions.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/workplace-layout/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const BAR =
	'<header id="header"><div class="header-end">'
	+ '<div id="notifications"></div><div id="contactsmenu"></div><nav id="user-menu"></nav>'
	+ '</div></header>'

function installState(openApp) {
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) =>
				app === 'core' && key === 'apps'
					? { [openApp]: { id: openApp, name: openApp, active: true } }
					: fallback,
		},
	}
}

async function loadScript() {
	vi.resetModules()
	await import('../../js/header-create.js?t=' + Math.random())
	await new Promise((resolve) => setTimeout(resolve, 0))
}

const flush = () => new Promise((resolve) => setTimeout(resolve, 0))

describe('the Quick add button', () => {
	let ran

	beforeEach(() => {
		document.body.innerHTML = BAR
		global.t = (app, text) => text
		ran = []
		window.thematiqCreateActions = {
			title: 'Create',
			failed: 'The file could not be created.',
			list: vi.fn(() =>
				Promise.resolve([
					{ name: 'New document', icon: 'M0 0', run: () => ran.push('document') },
					{ name: 'New spreadsheet', icon: 'M0 0', run: () => ran.push('spreadsheet') },
				]),
			),
		}
	})

	afterEach(() => {
		document.body.innerHTML = ''
		delete global.OCP
		delete global.t
		delete window.thematiqCreateActions
	})

	it('sits before the bell on Mail, a 44px round button with a menu', async () => {
		installState('mail')
		await loadScript()

		const wrap = document.querySelector('#header .header-end > .thematiq-quick-add')
		expect(wrap.nextElementSibling.id).toBe('notifications')
		const button = wrap.querySelector('button')
		expect(button.getAttribute('aria-label')).toBe('Quick add')
		expect(button.getAttribute('aria-haspopup')).toBe('menu')
		expect(button.getAttribute('aria-expanded')).toBe('false')
	})

	it('is not drawn on an app whose board has no Quick add', async () => {
		installState('dossiq')
		await loadScript()

		expect(document.querySelector('.thematiq-quick-add')).toBeNull()
	})

	it('waits for the apps menu to share its actions', async () => {
		const source = window.thematiqCreateActions
		delete window.thematiqCreateActions
		installState('calendar')
		await loadScript()
		expect(document.querySelector('.thematiq-quick-add')).toBeNull()

		window.thematiqCreateActions = source
		document.dispatchEvent(new window.CustomEvent('thematiq:create-actions'))
		expect(document.querySelector('.thematiq-quick-add')).not.toBeNull()
	})

	it('lists the shared actions on the first open, fetched once, and runs one', async () => {
		installState('deck')
		await loadScript()

		const button = document.querySelector('.thematiq-quick-add__button')
		button.click()
		await flush()
		const menu = document.querySelector('.thematiq-quick-add__menu')
		expect(menu.hidden).toBe(false)
		expect(button.getAttribute('aria-expanded')).toBe('true')
		const items = Array.from(menu.querySelectorAll('[role="menuitem"]'))
		expect(items.map((item) => item.textContent)).toEqual(['New document', 'New spreadsheet'])
		expect(document.activeElement).toBe(items[0])

		items[1].click()
		expect(ran).toEqual(['spreadsheet'])
		expect(menu.hidden).toBe(true)

		button.click()
		await flush()
		expect(window.thematiqCreateActions.list).toHaveBeenCalledTimes(1)
	})

	it('closes on Escape and gives the focus back to the button', async () => {
		installState('mail')
		await loadScript()

		const button = document.querySelector('.thematiq-quick-add__button')
		button.click()
		await flush()
		const menu = document.querySelector('.thematiq-quick-add__menu')
		menu.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
		expect(menu.hidden).toBe(true)
		expect(document.activeElement).toBe(button)
	})

	it('says so when there is nothing to create', async () => {
		window.thematiqCreateActions.list = () => Promise.resolve([])
		installState('mail')
		await loadScript()

		document.querySelector('.thematiq-quick-add__button').click()
		await flush()
		expect(document.querySelector('.thematiq-quick-add__status').textContent).toBe(
			'Nothing to create here.',
		)
	})
})
