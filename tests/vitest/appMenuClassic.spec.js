/**
 * @vitest-environment jsdom
 *
 * The classic menu style (js/app-menu.js): Nextcloud's apps menu stays as it
 * ships, and an administrator gets one row with the gear "Menu settings" at
 * its end, the way back to the Menu settings dialog. Also the script's
 * guards: no OCP, a state that cannot be read, and a state that is not a menu.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-the-classic-style-leaves-nextclouds-apps-menu-alone
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const APPS = ['files', 'dossiq', 'calendar']

function installState(state) {
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) => (app === 'thematiq' && key === 'app-menu' ? state : fallback),
		},
	}
}

async function loadScript() {
	vi.resetModules()
	delete window.thematiqAppMenuOpening
	await import('../../js/app-menu.js?t=' + Math.random())
	for (let i = 0; i < 5; i++) {
		await Promise.resolve()
	}
}

const flush = () => new Promise((resolve) => setTimeout(resolve, 0))

function grid() {
	return (
		'<div class="app-menu__grid">'
		+ APPS.map(
			(id) =>
				`<a class="app-item" role="menuitem" href="/index.php/apps/${id}/">`
				+ `<img class="app-item__icon" src="/apps/${id}/img/app.svg"><span class="app-item__label">${id}</span></a>`,
		).join('')
		+ '</div>'
	)
}

function classicMenu(extra = {}) {
	return {
		admin: true,
		groups: [{ title: 'Werk', apps: [{ id: 'dossiq', description: 'Zaken en Woo' }] }],
		labels: { menuSettings: 'Menu settings' },
		order: [],
		settings: { style: 'classic' },
		...extra,
	}
}

describe('the classic apps menu', () => {
	let open

	beforeEach(() => {
		// A fresh body per test: an earlier test's observer watches the old one.
		document.documentElement.replaceChild(document.createElement('body'), document.body)
		document.body.innerHTML = grid()
		global.OC = { generateUrl: (url) => url, requestToken: 'token' }
		global.fetch = vi.fn(() => Promise.resolve({ ok: true }))
		open = vi.fn()
		window.ThematiqMenuSettingsDialog = { open }
	})

	afterEach(() => {
		if (window.thematiqAppMenuObserver) {
			window.thematiqAppMenuObserver.disconnect()
			delete window.thematiqAppMenuObserver
		}
		delete window.ThematiqMenuSettingsDialog
		delete window.thematiqCreateActions
		delete global.OCP
		delete global.OC
		delete global.fetch
		vi.restoreAllMocks()
	})

	it('leaves the grid as Nextcloud draws it and adds one gear row at its end for an administrator', async () => {
		installState(classicMenu())
		await loadScript()
		const menuGrid = document.querySelector('.app-menu__grid')

		expect(Array.from(menuGrid.querySelectorAll(':scope > a.app-item')).map((a) => a.textContent)).toEqual(APPS)
		expect(menuGrid.querySelector('[data-thematiq-folded]')).toBeNull()
		const rows = menuGrid.querySelectorAll(':scope > .thematiq-app-menu__classic')
		expect(rows).toHaveLength(1)
		expect(menuGrid.lastElementChild).toBe(rows[0])
		const gear = rows[0].querySelector('button.thematiq-app-menu__settings')
		expect(gear.type).toBe('button')
		expect(gear.getAttribute('aria-haspopup')).toBe('dialog')
		expect(gear.textContent).toBe('Menu settings')
		expect(gear.querySelector('svg')).not.toBeNull()
		expect(window.thematiqAppMenuObserver).toBeUndefined()
	})

	it('opens the Menu settings dialog with the grid entries and the gear as opener', async () => {
		const menu = classicMenu()
		installState(menu)
		await loadScript()
		const gear = document.querySelector('.thematiq-app-menu__settings')
		gear.click()

		expect(open).toHaveBeenCalledTimes(1)
		const args = open.mock.calls[0][0]
		expect(args.opener).toBe(gear)
		expect(args.menu).toBe(menu)
		// The apps of the menu's groups come first, then the rest in grid order.
		expect(args.entries.map((entry) => entry.key)).toEqual(['dossiq', 'files', 'calendar'])
		const dossiq = args.entries.find((entry) => entry.key === 'dossiq')
		expect(dossiq).toMatchObject({ name: 'dossiq', icon: '/apps/dossiq/img/app.svg', description: 'Zaken en Woo', external: false, work: true })
	})

	it('adds the gear to a grid that opens later, once', async () => {
		document.body.innerHTML = ''
		installState(classicMenu())
		await loadScript()
		document.body.innerHTML = grid()
		await flush()
		document.body.appendChild(document.createElement('span'))
		await flush()

		expect(document.querySelectorAll('.thematiq-app-menu__classic')).toHaveLength(1)
	})

	it.each([
		['a person who is not an administrator', { admin: false }],
		['no Menu settings label', { labels: {} }],
	])('adds no gear for %s', async (label, extra) => {
		installState(classicMenu(extra))
		await loadScript()
		expect(document.querySelector('.thematiq-app-menu__classic')).toBeNull()
	})

	it('adds no gear when the dialog is not loaded', async () => {
		delete window.ThematiqMenuSettingsDialog
		installState(classicMenu())
		await loadScript()
		expect(document.querySelector('.thematiq-app-menu__classic')).toBeNull()

		window.ThematiqMenuSettingsDialog = { open: 'not a function' }
		document.body.appendChild(document.createElement('span'))
		await flush()
		expect(document.querySelector('.thematiq-app-menu__classic')).toBeNull()
	})

	it.each([
		['no OCP', () => delete global.OCP],
		['no initial state loader', () => { global.OCP = { InitialState: {} } }],
		['a state that throws', () => { global.OCP = { InitialState: { loadState: () => { throw new Error('no') } } } }],
		['no menu', () => installState(null)],
		['a menu that is not an object', () => installState('classic')],
	])('leaves the page alone with %s', async (label, arrange) => {
		arrange()
		await loadScript()
		expect(document.querySelector('.thematiq-app-menu__classic')).toBeNull()
		expect(document.querySelector('.app-menu__grid').children).toHaveLength(APPS.length)
		expect(window.thematiqAppMenuObserver).toBeUndefined()
	})
})
