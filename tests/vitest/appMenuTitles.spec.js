/**
 * @vitest-environment jsdom
 *
 * Show titles (apps-menu-show-titles): with `titles` off js/app-menu.js
 * draws every app entry of the menu, the external apps and the Create row's
 * chips as the icon alone, with the name as accessible name and in one shared
 * tooltip; headings and More keep their text; no payoff. With
 * `topBarTitles` on the pins draw their names next to the icon.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/apps-menu-show-titles/specs/apps-menu-settings/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const APPS = ['files', 'dossiq', 'calendar', 'mail', 'deck']

const CORE_APPS = APPS.map((id) => ({
	id,
	name: id.charAt(0).toUpperCase() + id.slice(1),
	href: '/index.php/apps/' + id + '/',
	icon: '/apps/' + id + '/img/app.svg',
	active: id === 'files',
}))

const LABELS = {
	pinned: 'Vastgezette apps',
	pinnedApp: '{app} staat nu vast in de bovenbalk.',
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
	return document.querySelector('.app-menu__grid')
}

function menu(settings = {}, extra = {}) {
	return {
		groups: [{ title: 'Werk', apps: [{ id: 'dossiq', description: 'Zaken en Woo' }] }],
		labels: LABELS,
		order: [],
		pins: [],
		settings,
		external: {
			title: 'Externe apps',
			note: 'opent in een nieuw tabblad',
			apps: [{ key: 'external-code', label: 'Forgejo', line: 'Code', url: 'https://code.example.org', icon: 'M0 0' }],
		},
		...extra,
	}
}

function tip() {
	return document.getElementById('thematiq-app-menu__tooltip')
}

function key(target, name, options = {}) {
	const event = new KeyboardEvent('keydown', { key: name, bubbles: true, cancelable: true, ...options })
	target.dispatchEvent(event)
	return event
}

describe('the apps menu without titles', () => {
	beforeEach(() => {
		vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
		global.OC = { generateUrl: (url) => url, requestToken: 'token' }
		global.fetch = vi.fn(() => Promise.resolve({ ok: true }))
	})

	afterEach(() => {
		vi.useRealTimers()
		if (window.thematiqAppMenuObserver) {
			window.thematiqAppMenuObserver.disconnect()
			delete window.thematiqAppMenuObserver
		}
		if (window.thematiqAppMenuTip && window.thematiqAppMenuTip.off) {
			window.thematiqAppMenuTip.off()
		}
		delete window.thematiqAppMenuTip
		document.body.innerHTML = ''
		delete global.OCP
		delete global.OC
		delete global.fetch
	})

	it('draws titles as before by default', async () => {
		const grid = page()
		installState(menu())
		await loadScript()

		expect(grid.getAttribute('data-titles')).toBe('true')
		const dossiq = grid.querySelector('a[href*="/apps/dossiq/"]')
		expect(dossiq.hasAttribute('aria-label')).toBe(false)
		expect(dossiq.hasAttribute('data-thematiq-tip')).toBe(false)
		expect(dossiq.querySelector('.thematiq-app-menu__description').textContent).toBe('Zaken en Woo')
	})

	it('draws every app entry as its icon with the name as accessible name, and no payoff', async () => {
		const grid = page()
		installState(menu({ titles: false, payoff: true }))
		await loadScript()

		expect(grid.getAttribute('data-titles')).toBe('false')
		const dossiq = grid.querySelector('a[href*="/apps/dossiq/"]')
		expect(dossiq.getAttribute('aria-label')).toBe('dossiq')
		expect(dossiq.getAttribute('data-thematiq-name')).toBe('dossiq')
		expect(dossiq.hasAttribute('data-thematiq-tip')).toBe(true)
		expect(dossiq.querySelector('.thematiq-app-menu__description')).toBeNull()
		// Nextcloud's own label stays Vue's node; CSS takes it out of the layout.
		expect(dossiq.querySelector('.app-item__label')).not.toBeNull()
	})

	it('names an external app with its new tab note, and shows only its title in the tooltip', async () => {
		const grid = page()
		installState(menu({ titles: false }))
		await loadScript()

		const forgejo = grid.querySelector('a[data-thematiq-external="external-code"]')
		expect(forgejo.getAttribute('aria-label')).toBe('Forgejo (opent in een nieuw tabblad)')
		expect(forgejo.getAttribute('data-thematiq-name')).toBe('Forgejo')
		expect(forgejo.querySelector('.thematiq-app-menu__description')).toBeNull()
	})

	it('keeps the headings and the More button as text', async () => {
		const grid = page()
		installState(menu({ titles: false }))
		await loadScript()

		grid.querySelectorAll('button, h2, h3').forEach((el) => {
			expect(el.hasAttribute('data-thematiq-tip')).toBe(false)
		})
	})

	it('shows the tooltip on keyboard focus at once, and Escape hides it before the menu closes', async () => {
		const grid = page()
		installState(menu({ titles: false }))
		await loadScript()

		const mail = grid.querySelector('a[href*="/apps/mail/"]')
		mail.focus()
		expect(tip().hidden).toBe(false)
		expect(tip().getAttribute('role')).toBe('tooltip')
		expect(tip().textContent).toBe('mail')
		expect(mail.hasAttribute('aria-describedby')).toBe(false)

		const closed = vi.fn()
		document.addEventListener('keydown', closed)
		expect(key(mail, 'Escape').defaultPrevented).toBe(true)
		expect(tip().hidden).toBe(true)
		expect(closed).not.toHaveBeenCalled()
		expect(document.activeElement).toBe(mail)

		// With no tooltip showing, Escape does what it did before.
		key(mail, 'Escape')
		expect(closed).toHaveBeenCalledTimes(1)
		document.removeEventListener('keydown', closed)
	})

	it('shows the tooltip after the pointer rests 300 ms, keeps it on the tooltip and hides it on leave', async () => {
		const grid = page()
		installState(menu({ titles: false }))
		await loadScript()

		const deck = grid.querySelector('a[href*="/apps/deck/"]')
		deck.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }))
		vi.advanceTimersByTime(299)
		expect(tip() === null || tip().hidden).toBe(true)
		vi.advanceTimersByTime(1)
		expect(tip().hidden).toBe(false)
		expect(tip().textContent).toBe('deck')

		// The pointer moves onto the tooltip: it stays (WCAG 1.4.13 hoverable).
		deck.dispatchEvent(new MouseEvent('mouseout', { bubbles: true, relatedTarget: tip() }))
		vi.advanceTimersByTime(500)
		expect(tip().hidden).toBe(false)

		tip().dispatchEvent(new MouseEvent('mouseout', { bubbles: true, relatedTarget: document.body }))
		vi.advanceTimersByTime(500)
		expect(tip().hidden).toBe(true)
	})

	it('hides the tooltip when a drag starts', async () => {
		const grid = page()
		installState(menu({ titles: false }))
		await loadScript()

		const mail = grid.querySelector('a[href*="/apps/mail/"]')
		mail.focus()
		expect(tip().hidden).toBe(false)
		mail.dispatchEvent(new Event('dragstart', { bubbles: true }))
		expect(tip().hidden).toBe(true)
	})

	it('still names the app when Alt+P pins an icon-only entry', async () => {
		const grid = page()
		installState(menu({ titles: false }))
		await loadScript()

		const mail = grid.querySelector('a[href*="/apps/mail/"]')
		mail.focus()
		key(mail, 'p', { code: 'KeyP', altKey: true })
		expect(document.getElementById('thematiq-app-pins-live').textContent).toBe(
			'mail staat nu vast in de bovenbalk.',
		)
	})
})

describe('titles in the top bar', () => {
	beforeEach(() => {
		vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
		global.OC = { generateUrl: (url) => url, requestToken: 'token' }
		global.fetch = vi.fn(() => Promise.resolve({ ok: true }))
	})

	afterEach(() => {
		vi.useRealTimers()
		if (window.thematiqAppMenuObserver) {
			window.thematiqAppMenuObserver.disconnect()
			delete window.thematiqAppMenuObserver
		}
		if (window.thematiqAppMenuTip && window.thematiqAppMenuTip.off) {
			window.thematiqAppMenuTip.off()
		}
		delete window.thematiqAppMenuTip
		document.body.innerHTML = ''
		delete global.OCP
		delete global.OC
		delete global.fetch
	})

	it('draws pins icon only with the shared tooltip by default', async () => {
		page()
		installState(menu({}, { pins: ['mail'] }))
		await loadScript()

		const pin = document.querySelector('a[data-thematiq-pin="mail"]')
		expect(pin.getAttribute('aria-label')).toBe('Mail')
		expect(pin.hasAttribute('title')).toBe(false)
		expect(pin.querySelector('.thematiq-app-pins__name')).toBeNull()
		pin.focus()
		expect(tip().hidden).toBe(false)
		expect(tip().textContent).toBe('Mail')
	})

	it('draws each pin with its name next to the icon, and no tooltip while the name fits', async () => {
		page()
		installState(menu({ topBarTitles: true }, { pins: ['mail', 'deck'] }))
		await loadScript()

		const pin = document.querySelector('a[data-thematiq-pin="deck"]')
		const name = pin.querySelector('.thematiq-app-pins__name')
		expect(name.textContent).toBe('Deck')
		expect(name.getAttribute('aria-hidden')).toBe('true')
		expect(pin.hasAttribute('data-thematiq-titled')).toBe(true)
		pin.focus()
		expect(tip() === null || tip().hidden).toBe(true)
	})

	it('shows the tooltip for a pin whose name is cut off', async () => {
		page()
		installState(menu({ topBarTitles: true }, { pins: ['calendar'] }))
		await loadScript()

		const pin = document.querySelector('a[data-thematiq-pin="calendar"]')
		const name = pin.querySelector('.thematiq-app-pins__name')
		Object.defineProperty(name, 'scrollWidth', { value: 180 })
		Object.defineProperty(name, 'clientWidth', { value: 120 })
		pin.focus()
		expect(tip().hidden).toBe(false)
		expect(tip().textContent).toBe('Calendar')
	})
})
