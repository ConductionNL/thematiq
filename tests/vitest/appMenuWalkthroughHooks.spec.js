/**
 * @vitest-environment jsdom
 *
 * js/app-menu.js and the apps menu walkthrough: the `data-walkthrough-id`
 * hooks, when the tour is due and starts (on the first open of the menu,
 * never on page load, never with the setup wizard open), the menu held open
 * while it runs, Escape closing the tour before the menu, and replay.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/apps-menu-walkthrough/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

function installState(state) {
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) =>
				app === 'thematiq' && key === 'app-menu' && state !== undefined
					? state
					: fallback,
		},
	}
}

async function tick() {
	for (let i = 0; i < 8; i++) {
		await Promise.resolve()
	}
}

async function loadScript() {
	vi.resetModules()
	delete window.thematiqAppMenuOpening
	delete window.thematiqAppMenuHold
	delete window.thematiqAppMenuReplay
	await import('../../js/app-menu.js?t=' + Math.random())
	await tick()
}

function tile(id) {
	return (
		`<a class="app-item" role="menuitem" tabindex="-1" href="/index.php/apps/${id}/">`
		+ `<span class="app-item__circle"><img class="app-item__icon" src="/apps/${id}/img/app.svg"></span>`
		+ `<span class="app-item__label">${id}</span></a>`
	)
}

const APPS = ['files', 'dossiq', 'calendar', 'pipelinq', 'mail', 'contacts', 'deck', 'photos', 'activity', 'talk', 'notes', 'tasks']

function header(open) {
	document.body.innerHTML =
		'<header id="header"><div class="header-start"><div class="app-menu">'
		+ `<button class="app-menu__waffle" aria-expanded="${open ? 'true' : 'false'}"></button>`
		+ '</div></div></header>'
		+ '<div class="v-popper__popper"><div class="app-menu__popover"></div></div>'
		+ '<main id="elsewhere"></main>'
	const waffle = document.querySelector('.app-menu__waffle')
	waffle.addEventListener('click', () => {
		const now = waffle.getAttribute('aria-expanded') !== 'true'
		waffle.setAttribute('aria-expanded', now ? 'true' : 'false')
		if (now) {
			drawGrid()
		} else {
			document.querySelector('.app-menu__popover').innerHTML = ''
		}
	})
	if (open) {
		drawGrid()
	}
	return waffle
}

function drawGrid() {
	document.querySelector('.app-menu__popover').innerHTML =
		'<div class="app-menu__grid">' + APPS.map(tile).join('') + '</div>'
}

const LABELS = {
	allApps: 'More',
	hideApps: 'Fewer',
	dragHint: 'Drag an app to reorder it.',
	moveHint: 'Alt with an arrow key moves.',
	moved: '{app} moved to place {place} of {total}.',
	menuSettings: 'Menu settings',
}

function menu(walkthrough, settings = {}) {
	return {
		labels: LABELS,
		order: [],
		pins: [],
		settings: { style: 'workplace', columns: 4, rows: 6, rowsAboveFold: 2, showCreateActions: false, ...settings },
		walkthrough,
	}
}

const DUE = { seen: '', progress: '', appVersion: '9.9.9', replayLabel: 'Replay walkthrough' }
const SEEN = { seen: '9.9.9', progress: '', appVersion: '9.9.9', replayLabel: 'Replay walkthrough' }

/** A stand-in for the bundle's global. */
function fakeTour() {
	const tour = {
		running: false,
		starts: [],
		start: vi.fn((request) => {
			tour.starts.push(request)
			tour.running = true
			return true
		}),
		close: vi.fn(() => {
			tour.running = false
			return true
		}),
		isRunning: () => tour.running,
	}
	window.thematiqMenuWalkthrough = tour
	return tour
}

describe('the apps menu walkthrough in the menu script', () => {
	beforeEach(() => {
		window.ThematiqMenuSettingsDialog = { open: vi.fn() }
		global.OC = { filePath: (app, type, file) => `/apps/${app}/${type}/${file}`, generateUrl: (u) => u }
	})

	afterEach(() => {
		if (window.thematiqAppMenuObserver) {
			window.thematiqAppMenuObserver.disconnect()
		}
		document.body.innerHTML = ''
		document.head.querySelectorAll('script[data-thematiq-menu-walkthrough]').forEach((el) => el.remove())
		delete window.thematiqMenuWalkthrough
		delete global.OCP
		delete global.OC
		delete window.ThematiqMenuSettingsDialog
		vi.useRealTimers()
	})

	it('marks every part the tour spotlights', async () => {
		installState(menu(SEEN))
		header(true)
		await loadScript()
		const grid = document.querySelector('.app-menu__grid')
		const id = (ref) => document.querySelector(`[data-walkthrough-id="${ref}"]`)
		expect(id('apps-menu-button')).toBe(document.querySelector('.app-menu__waffle'))
		expect(id('apps-menu-list')).toBe(grid)
		expect(id('apps-menu-more')).toBe(grid.querySelector('[data-thematiq-row="toggle"]'))
		expect(id('apps-menu-first-entry').style.order).toBe('100')
		// No app is pinned: the menu's corner of the bar.
		expect(id('apps-menu-pins')).toBe(document.querySelector('#header .app-menu'))
		expect(id('apps-menu-settings')).toBe(grid.querySelector('.thematiq-app-menu__settings'))
		// The Create row is off: no hook, so its optional step is skipped.
		expect(id('apps-menu-create')).toBeNull()
		expect(document.querySelectorAll('[data-walkthrough-id="apps-menu-first-entry"]')).toHaveLength(1)
	})

	it('leaves the hooks out without the tour state', async () => {
		installState(menu(undefined))
		header(true)
		await loadScript()
		expect(document.querySelector('[data-walkthrough-id]')).toBeNull()
	})

	it('loads the tour only on the first open of the menu with a tour due, never on page load', async () => {
		installState(menu({ ...DUE }))
		const waffle = header(false)
		await loadScript()
		expect(document.querySelector('script[data-thematiq-menu-walkthrough]')).toBeNull()
		waffle.click()
		await tick()
		const script = document.querySelector('script[data-thematiq-menu-walkthrough]')
		expect(script).not.toBeNull()
		expect(script.getAttribute('src')).toBe('/apps/thematiq/js/vendor/menuWalkthrough.js')
	})

	it('starts the tour inside the menu\'s popover with the state, once per page', async () => {
		const tour = fakeTour()
		installState(menu({ ...DUE }, { showCreateActions: true }))
		const waffle = header(false)
		await loadScript()
		expect(tour.start).not.toHaveBeenCalled()
		waffle.click()
		await tick()
		expect(tour.start).toHaveBeenCalledTimes(1)
		const request = tour.starts[0]
		expect(request.mode).toBe('auto')
		expect(request.state.appVersion).toBe('9.9.9')
		expect(request.container).toBe(document.querySelector('.v-popper__popper'))
		expect(request.parts['apps-menu-create']).toBe(true)
		// Ended: the state is seen and the tour does not come back on this page.
		tour.running = false
		request.onEnd()
		expect(request.state.seen).toBe('9.9.9')
		waffle.click()
		waffle.click()
		await tick()
		expect(tour.start).toHaveBeenCalledTimes(1)
	})

	it('does not start for a person who saw the tour, nor while the setup wizard is open', async () => {
		const tour = fakeTour()
		installState(menu({ ...SEEN }))
		let waffle = header(false)
		await loadScript()
		waffle.click()
		await tick()
		expect(tour.start).not.toHaveBeenCalled()

		installState(menu({ ...DUE }))
		waffle = header(false)
		document.body.insertAdjacentHTML('beforeend', '<div data-thematiq-setup-wizard></div>')
		await loadScript()
		waffle.click()
		await tick()
		expect(tour.start).not.toHaveBeenCalled()
	})

	it('is due again for progress left part-way, and opens the menu by itself for an immediate start', async () => {
		const tour = fakeTour()
		installState(menu({ ...SEEN, progress: '{"tourId":"apps-menu","stepId":"open","index":0,"immediate":true}' }))
		header(false)
		await loadScript()
		await new Promise((r) => setTimeout(r, 0))
		await tick()
		expect(document.querySelector('.app-menu__waffle').getAttribute('aria-expanded')).toBe('true')
		expect(tour.start).toHaveBeenCalledTimes(1)
	})

	it('holds the menu open while the tour runs: the pointer leaving does not close it', async () => {
		vi.useFakeTimers()
		const tour = fakeTour()
		installState(menu({ ...DUE }))
		const waffle = header(false)
		await loadScript()
		window.matchMedia = () => ({ matches: true })
		document.hasFocus = () => true
		// Opened by resting the pointer on the button: a look that closes on leave.
		waffle.dispatchEvent(new MouseEvent('pointerover', { bubbles: true, relatedTarget: document.getElementById('elsewhere') }))
		vi.advanceTimersByTime(200)
		await tick()
		expect(tour.start).toHaveBeenCalledTimes(1)
		waffle.dispatchEvent(new MouseEvent('pointerout', { bubbles: true, relatedTarget: document.getElementById('elsewhere') }))
		vi.advanceTimersByTime(500)
		expect(waffle.getAttribute('aria-expanded')).toBe('true')
		// A click on the grid button does not close it either.
		waffle.click()
		expect(waffle.getAttribute('aria-expanded')).toBe('true')
	})

	it('Escape closes the tour first and leaves the menu open', async () => {
		const tour = fakeTour()
		installState(menu({ ...DUE }))
		const waffle = header(false)
		await loadScript()
		waffle.click()
		await tick()
		const seen = []
		document.addEventListener('keydown', (e) => seen.push(e.key))
		document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
		document.dispatchEvent(new KeyboardEvent('keyup', { key: 'Escape', bubbles: true }))
		expect(tour.close).toHaveBeenCalledTimes(1)
		expect(seen).toEqual([])
		expect(waffle.getAttribute('aria-expanded')).toBe('true')
		// A second Escape belongs to the menu again.
		document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
		expect(seen).toEqual(['Escape'])
	})

	it('replays from step 1: opens the menu and starts in replay mode', async () => {
		vi.useFakeTimers()
		const tour = fakeTour()
		installState(menu({ ...SEEN }))
		header(false)
		await loadScript()
		expect(typeof window.thematiqAppMenuReplay).toBe('function')
		window.thematiqAppMenuReplay()
		await tick()
		vi.advanceTimersByTime(150)
		await tick()
		expect(document.querySelector('.app-menu__waffle').getAttribute('aria-expanded')).toBe('true')
		expect(tour.start).toHaveBeenCalledTimes(1)
		expect(tour.starts[0].mode).toBe('replay')
	})

	it('has no replay with the classic style', async () => {
		installState(menu(undefined, { style: 'classic' }))
		header(false)
		await loadScript()
		expect(window.thematiqAppMenuReplay).toBeUndefined()
	})
})
