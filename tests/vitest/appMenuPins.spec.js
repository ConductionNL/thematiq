/**
 * @vitest-environment jsdom
 *
 * Pinned apps in the workplace top bar: js/app-menu.js draws the person's
 * pins after the grid button, pins an app dragged onto the bar or with Alt+P,
 * unpins a pin dragged off the bar or with Alt+P, and saves the pins.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-pins/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const APPS = ['files', 'dossiq', 'calendar', 'pipelinq', 'mail', 'contacts', 'deck']

const CORE_APPS = APPS.map((id) => ({
	id,
	name: id.charAt(0).toUpperCase() + id.slice(1),
	href: '/index.php/apps/' + id + '/',
	icon: '/apps/' + id + '/img/app.svg',
	active: id === 'files',
}))

const LABELS = {
	pinned: 'Vastgezette apps',
	dropToPin: 'Laat los om vast te zetten',
	releaseToRemove: 'Laat los om te verwijderen',
	pinnedApp: '{app} staat nu vast in de bovenbalk.',
	unpinnedApp: '{app} is uit de bovenbalk gehaald.',
	pinFull: 'Je kunt maximaal {max} apps vastzetten.',
	pinSaveFailed: 'De vastgezette apps konden niet worden opgeslagen.',
	moved: '{app} staat nu op plaats {place} van {total}.',
}

function installState(state) {
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) => {
				if (app === 'thematiq' && key === 'app-menu') {
					return state
				}
				if (app === 'core' && key === 'apps') {
					return CORE_APPS
				}
				return fallback
			},
		},
	}
}

async function tick() {
	for (let i = 0; i < 5; i++) {
		await Promise.resolve()
	}
}

async function loadScript() {
	vi.resetModules()
	delete window.thematiqAppMenuOpening
	await import('../../js/app-menu.js?t=' + Math.random())
	await tick()
}

function page() {
	document.body.innerHTML =
		'<header id="header"><div class="header-start"><a id="nextcloud"></a>'
		+ '<nav class="app-menu"><button class="app-menu__waffle" aria-expanded="true"></button></nav>'
		+ '</div><div class="header-end"></div></header>'
		+ '<div class="app-menu__popover-base"><div class="app-menu__popover"><div class="app-menu__grid">'
		+ APPS.map(
			(id) =>
				`<a class="app-item" role="menuitem" tabindex="-1" href="/index.php/apps/${id}/">`
				+ `<span class="app-item__circle"></span><span class="app-item__label">${id}</span></a>`,
		).join('')
		+ '</div></div></div>'
	document.getElementById('header').getBoundingClientRect = () => ({
		left: 0,
		right: 1440,
		top: 0,
		bottom: 68,
		width: 1440,
		height: 68,
	})
	return document.querySelector('.app-menu__grid')
}

function bar() {
	return document.querySelector('nav.thematiq-app-pins')
}

function pinned() {
	return Array.from(bar().querySelectorAll('a[data-thematiq-pin]')).map((a) =>
		a.getAttribute('data-thematiq-pin'),
	)
}

function live() {
	return document.getElementById('thematiq-app-pins-live').textContent
}

function altP(target) {
	const event = new KeyboardEvent('keydown', {
		key: 'p',
		code: 'KeyP',
		altKey: true,
		bubbles: true,
		cancelable: true,
	})
	target.dispatchEvent(event)
	return event
}

function pointer(target, type, x, y) {
	target.dispatchEvent(
		new MouseEvent(type, { bubbles: true, button: 0, clientX: x, clientY: y }),
	)
}

describe('pinned apps in the top bar', () => {
	let posted

	beforeEach(() => {
		vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
		posted = []
		global.OC = { generateUrl: (url) => url, requestToken: 'token' }
		global.fetch = vi.fn((url, options) => {
			posted.push({ url, body: JSON.parse(options.body) })
			return Promise.resolve({ ok: true })
		})
	})

	afterEach(() => {
		vi.useRealTimers()
		if (window.thematiqAppMenuObserver) {
			window.thematiqAppMenuObserver.disconnect()
			delete window.thematiqAppMenuObserver
		}
		document.body.innerHTML = ''
		delete document.elementsFromPoint
		delete global.OCP
		delete global.OC
		delete global.fetch
	})

	it('draws the stored pins after the grid button and leaves out what it cannot link', async () => {
		page()
		installState({ labels: LABELS, order: [], pins: ['deck', 'gone', 'files'] })
		await loadScript()

		expect(bar().previousElementSibling.classList.contains('app-menu')).toBe(true)
		expect(bar().getAttribute('aria-label')).toBe('Vastgezette apps')
		expect(pinned()).toEqual(['deck', 'files'])
		const deck = bar().querySelector('[data-thematiq-pin="deck"]')
		expect(deck.getAttribute('aria-label')).toBe('Deck')
		expect(deck.getAttribute('href')).toBe('/index.php/apps/deck/')
		expect(bar().querySelector('[data-thematiq-pin="files"]').getAttribute('aria-current')).toBe('page')
	})

	it('hides the bar without pins', async () => {
		page()
		installState({ labels: LABELS, order: [], pins: [] })
		await loadScript()

		expect(bar().hidden).toBe(true)
	})

	it('pins and unpins the focused app with Alt+P, says so and saves', async () => {
		const grid = page()
		installState({ labels: LABELS, order: [], pins: [] })
		await loadScript()

		const mail = grid.querySelector('a[href*="/apps/mail/"]')
		mail.focus()
		expect(altP(mail).defaultPrevented).toBe(true)
		expect(pinned()).toEqual(['mail'])
		expect(live()).toBe('mail staat nu vast in de bovenbalk.')
		vi.advanceTimersByTime(500)
		expect(posted).toEqual([{ url: '/apps/thematiq/app-menu/pins', body: { pins: ['mail'] } }])

		altP(mail)
		expect(pinned()).toEqual([])
		expect(live()).toBe('mail is uit de bovenbalk gehaald.')
		vi.advanceTimersByTime(500)
		expect(posted[1].body.pins).toEqual([])
	})

	it('pins at most five apps', async () => {
		const grid = page()
		installState({ labels: LABELS, order: [], pins: ['files', 'dossiq', 'calendar', 'pipelinq', 'mail'] })
		await loadScript()

		const deck = grid.querySelector('a[href*="/apps/deck/"]')
		deck.focus()
		altP(deck)
		expect(pinned()).toHaveLength(5)
		expect(live()).toBe('Je kunt maximaal 5 apps vastzetten.')
	})

	it('pins an app dragged onto the bar, with a drop slot and its hint', async () => {
		const grid = page()
		installState({ labels: LABELS, order: [], pins: ['files'] })
		await loadScript()
		document.elementsFromPoint = () => []

		const deck = grid.querySelector('a[href*="/apps/deck/"]')
		pointer(deck, 'pointerdown', 300, 300)
		pointer(document, 'pointermove', 300, 200)
		expect(deck.hasAttribute('data-thematiq-dragging')).toBe(true)
		expect(bar().querySelector('.thematiq-app-pins__drop')).toBeNull()

		pointer(document, 'pointermove', 300, 30)
		expect(bar().querySelector('.thematiq-app-pins__drop')).not.toBeNull()
		expect(document.querySelector('.thematiq-app-pins__drop-hint[role="status"]').textContent).toBe('Laat los om vast te zetten')
		expect(document.querySelector('.thematiq-app-pins__ghost').textContent).toContain('Deck')

		pointer(document, 'pointerup', 300, 30)
		expect(bar().querySelector('.thematiq-app-pins__drop')).toBeNull()
		expect(document.querySelector('.thematiq-app-pins__ghost')).toBeNull()
		expect(document.querySelector('.thematiq-app-pins__drop-hint')).toBeNull()
		expect(pinned()).toEqual(['files', 'deck'])
		vi.advanceTimersByTime(500)
		expect(posted.filter((p) => p.url.endsWith('/pins'))[0].body.pins).toEqual(['files', 'deck'])
	})

	it('unpins a pin dragged off the bar, with its hint, and the drag does not open the app', async () => {
		page()
		installState({ labels: LABELS, order: [], pins: ['files', 'deck'] })
		await loadScript()
		bar().getBoundingClientRect = () => ({ left: 100, right: 200, top: 14, bottom: 54 })

		const deck = bar().querySelector('[data-thematiq-pin="deck"]')
		pointer(deck, 'pointerdown', 170, 30)
		pointer(document, 'pointermove', 170, 300)
		expect(deck.hasAttribute('data-thematiq-dragging')).toBe(true)
		const ghost = document.querySelector('.thematiq-app-pins__ghost')
		expect(ghost.querySelector('.thematiq-app-pins__ghost-hint').textContent).toBe('Laat los om te verwijderen')

		pointer(document, 'pointerup', 170, 300)
		expect(pinned()).toEqual(['files'])
		expect(live()).toBe('Deck is uit de bovenbalk gehaald.')
	})

	it('keeps a pin released back on the bar', async () => {
		page()
		installState({ labels: LABELS, order: [], pins: ['files', 'deck'] })
		await loadScript()
		bar().getBoundingClientRect = () => ({ left: 100, right: 200, top: 14, bottom: 54 })

		const deck = bar().querySelector('[data-thematiq-pin="deck"]')
		pointer(deck, 'pointerdown', 170, 30)
		pointer(document, 'pointermove', 170, 300)
		pointer(document, 'pointermove', 150, 30)
		expect(document.querySelector('.thematiq-app-pins__ghost-hint').hidden).toBe(true)
		pointer(document, 'pointerup', 150, 30)
		expect(pinned()).toEqual(['files', 'deck'])

		const opened = vi.fn()
		deck.addEventListener('click', opened)
		const click = new MouseEvent('click', { bubbles: true, cancelable: true })
		deck.dispatchEvent(click)
		expect(click.defaultPrevented).toBe(true)
	})

	it('moves a pin with Alt and an arrow key and unpins it with Alt+P', async () => {
		page()
		installState({ labels: LABELS, order: [], pins: ['files', 'deck', 'mail'] })
		await loadScript()

		const files = bar().querySelector('[data-thematiq-pin="files"]')
		files.focus()
		files.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', altKey: true, bubbles: true, cancelable: true }))
		expect(pinned()).toEqual(['deck', 'files', 'mail'])
		expect(live()).toBe('Files staat nu op plaats 2 van 3.')
		const moved = bar().querySelector('[data-thematiq-pin="files"]')
		expect(document.activeElement).toBe(moved)

		altP(moved)
		expect(pinned()).toEqual(['deck', 'mail'])
		expect(document.activeElement.getAttribute('data-thematiq-pin')).toBe('mail')
	})
})
