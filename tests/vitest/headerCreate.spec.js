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

	it('moves through the items with the arrows, wrapping, and closes on Tab', async () => {
		installState('mail')
		await loadScript()
		document.querySelector('.thematiq-quick-add__button').click()
		await flush()
		const menu = document.querySelector('.thematiq-quick-add__menu')
		const items = Array.from(menu.querySelectorAll('[role="menuitem"]'))
		const key = (name) => menu.dispatchEvent(new window.KeyboardEvent('keydown', { key: name, bubbles: true }))

		key('ArrowDown')
		expect(document.activeElement).toBe(items[1])
		key('ArrowDown')
		expect(document.activeElement).toBe(items[0])
		key('ArrowUp')
		expect(document.activeElement).toBe(items[1])
		key('a')
		expect(menu.hidden).toBe(false)
		key('Tab')
		expect(menu.hidden).toBe(true)
	})

	it('closes on a second click of the button and on a click outside it', async () => {
		installState('mail')
		await loadScript()
		const button = document.querySelector('.thematiq-quick-add__button')
		const menu = document.querySelector('.thematiq-quick-add__menu')
		button.click()
		await flush()
		button.click()
		expect(menu.hidden).toBe(true)
		expect(button.getAttribute('aria-expanded')).toBe('false')

		button.click()
		await flush()
		menu.click()
		expect(menu.hidden).toBe(false)
		document.getElementById('notifications').click()
		expect(menu.hidden).toBe(true)
	})

	it('says there is nothing to create when the actions cannot be listed', async () => {
		window.thematiqCreateActions.list = () => Promise.reject(new Error('offline'))
		installState('mail')
		await loadScript()
		document.querySelector('.thematiq-quick-add__button').click()
		await flush()
		await flush()
		expect(document.querySelector('.thematiq-quick-add__status').textContent).toBe('Nothing to create here.')
	})

	it('opens again with the failure when an action reports one', async () => {
		window.thematiqCreateActions.list = () => Promise.resolve([
			{ name: 'New document', icon: 'M0 0', run: (failed) => failed() },
		])
		installState('deck')
		await loadScript()
		const button = document.querySelector('.thematiq-quick-add__button')
		button.click()
		await flush()
		document.querySelector('.thematiq-quick-add__menu [role="menuitem"]').click()

		const menu = document.querySelector('.thematiq-quick-add__menu')
		expect(menu.hidden).toBe(false)
		expect(button.getAttribute('aria-expanded')).toBe('true')
		expect(menu.querySelector('.thematiq-quick-add__status').textContent).toBe('The file could not be created.')
	})

	it('puts the button back when Nextcloud re-renders the end of the bar', async () => {
		installState('calendar')
		await loadScript()
		const wrap = document.querySelector('.thematiq-quick-add')
		const end = document.querySelector('#header .header-end')
		end.remove()
		const fresh = document.createElement('div')
		fresh.className = 'header-end'
		fresh.innerHTML = '<div id="notifications"></div>'
		document.getElementById('header').appendChild(fresh)
		await flush()

		expect(fresh.firstElementChild).toBe(wrap)
		expect(wrap.nextElementSibling.id).toBe('notifications')
	})

	it('is not drawn without translations or when the apps state cannot be read', async () => {
		installState('mail')
		delete global.t
		await loadScript()
		expect(document.querySelector('.thematiq-quick-add')).toBeNull()

		global.t = (app, text) => text
		global.OCP = { InitialState: { loadState: () => { throw new Error('no state') } } }
		await loadScript()
		expect(document.querySelector('.thematiq-quick-add')).toBeNull()
	})

	it('waits for DOMContentLoaded while the page is loading', async () => {
		installState('mail')
		const readyState = vi.spyOn(document, 'readyState', 'get').mockReturnValue('loading')
		await loadScript()
		expect(document.querySelector('.thematiq-quick-add')).toBeNull()

		readyState.mockReturnValue('complete')
		document.dispatchEvent(new Event('DOMContentLoaded'))
		expect(document.querySelector('.thematiq-quick-add')).not.toBeNull()
		readyState.mockRestore()
	})
})
