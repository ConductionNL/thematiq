/**
 * @vitest-environment jsdom
 *
 * Hover to open, click to pin: js/app-menu.js on a Nextcloud that opens the
 * apps menu on a click only, and on one that opens it on hover itself.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/apps-menu-opening/spec.md
 */

import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

let coreClicks

async function loadScript() {
	vi.resetModules()
	delete window.thematiqAppMenuOpening
	await import('../../js/app-menu.js?t=' + Math.random())
}

/** A header like Nextcloud's: the waffle toggles the menu on a click. */
function header({ coreHover = false } = {}) {
	document.body.innerHTML =
		(coreHover ? '<div class="app-menu__trigger">' : '<div>')
		+ '<button class="app-menu__waffle" aria-expanded="false"></button>'
		+ '<button class="app-menu__current-app" aria-expanded="false"></button></div>'
		+ '<div class="app-menu__popover"><div class="app-menu__grid"></div></div>'
		+ '<main id="elsewhere"></main>'
	const waffle = document.querySelector('.app-menu__waffle')
	waffle.addEventListener('click', () => {
		coreClicks++
		const open = waffle.getAttribute('aria-expanded') !== 'true'
		waffle.setAttribute('aria-expanded', open ? 'true' : 'false')
	})
	return waffle
}

const isOpen = () =>
	document.querySelector('.app-menu__waffle').getAttribute('aria-expanded')
	=== 'true'

function over(el, from = document.getElementById('elsewhere')) {
	el.dispatchEvent(
		new MouseEvent('pointerover', { bubbles: true, relatedTarget: from }),
	)
}

function out(el, to = document.getElementById('elsewhere')) {
	el.dispatchEvent(
		new MouseEvent('pointerout', { bubbles: true, relatedTarget: to }),
	)
}

describe('the apps menu opens on hover and pins on click', () => {
	beforeEach(() => {
		vi.useFakeTimers({
			toFake: ['setTimeout', 'clearTimeout', 'requestAnimationFrame'],
		})
		coreClicks = 0
		window.matchMedia = vi.fn(() => ({ matches: true }))
		vi.spyOn(document, 'hasFocus').mockReturnValue(true)
		global.OCP = { InitialState: { loadState: () => ({ hint: 'Hint' }) } }
	})

	afterEach(() => {
		vi.useRealTimers()
		vi.restoreAllMocks()
		if (window.thematiqAppMenuObserver) {
			window.thematiqAppMenuObserver.disconnect()
			delete window.thematiqAppMenuObserver
		}
		document.body.innerHTML = ''
		document.documentElement.removeAttribute('data-thematiq-app-menu-peek')
		delete window.matchMedia
		delete global.OCP
	})

	it('opens after a pause and closes when the pointer leaves', async () => {
		const waffle = header()
		await loadScript()

		over(waffle)
		vi.advanceTimersByTime(149)
		expect(isOpen()).toBe(false)
		vi.advanceTimersByTime(1)
		expect(isOpen()).toBe(true)

		const popover = document.querySelector('.app-menu__popover')
		out(waffle, popover)
		over(popover, waffle)
		vi.advanceTimersByTime(500)
		expect(isOpen(), 'moving into the menu keeps it open').toBe(true)

		out(popover)
		vi.advanceTimersByTime(179)
		expect(isOpen()).toBe(true)
		vi.advanceTimersByTime(1)
		expect(isOpen()).toBe(false)
	})

	it('a hover open marks the root until a key is pressed, so the focused tile draws no ring', async () => {
		const waffle = header()
		await loadScript()
		const marked = () =>
			document.documentElement.hasAttribute('data-thematiq-app-menu-peek')

		expect(marked()).toBe(false)
		over(waffle)
		vi.advanceTimersByTime(150)
		expect(isOpen()).toBe(true)
		expect(marked()).toBe(true)

		document.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight' }))
		expect(marked(), 'a key press brings the ring back').toBe(false)

		const css = readFileSync(
			resolve(__dirname, '../../css/app-menu.css'),
			'utf8',
		)
		expect(css).toMatch(
			/:root\[data-thematiq-app-menu-peek\][^{]*\.app-menu__popover-base\s+:focus-visible[^{]*\{\s*outline: none !important;\s*box-shadow: none !important;/,
		)
	})

	it('a short pass does not open', async () => {
		const waffle = header()
		await loadScript()

		over(waffle)
		vi.advanceTimersByTime(100)
		out(waffle)
		vi.advanceTimersByTime(500)
		expect(isOpen()).toBe(false)
	})

	it('a click during a look pins the menu, and another click closes it', async () => {
		const waffle = header()
		await loadScript()

		over(waffle)
		vi.advanceTimersByTime(150)
		expect(coreClicks).toBe(1)

		const outsidePress = vi.fn()
		document.addEventListener('mousedown', outsidePress, true)
		const press = new MouseEvent('mousedown', {
			bubbles: true,
			cancelable: true,
		})
		waffle.dispatchEvent(press)
		expect(
			outsidePress,
			'the popover never saw the press',
		).not.toHaveBeenCalled()
		expect(press.defaultPrevented, 'focus stays in the menu').toBe(true)
		document.removeEventListener('mousedown', outsidePress, true)
		const pin = new MouseEvent('click', { bubbles: true, cancelable: true })
		waffle.dispatchEvent(pin)
		expect(pin.defaultPrevented).toBe(true)
		expect(coreClicks, 'Nextcloud never saw the pinning click').toBe(1)
		out(waffle)
		vi.advanceTimersByTime(1000)
		expect(isOpen()).toBe(true)

		waffle.click()
		expect(isOpen()).toBe(false)
	})

	it('opens the menu again when the popover library hid it on the pinning click', async () => {
		const waffle = header()
		await loadScript()

		over(waffle)
		vi.advanceTimersByTime(150)
		waffle.dispatchEvent(
			new MouseEvent('click', { bubbles: true, cancelable: true }),
		)
		waffle.setAttribute('aria-expanded', 'false')
		await Promise.resolve()
		expect(isOpen()).toBe(true)
		expect(coreClicks).toBe(2)
	})

	it('a menu opened by a click stays open when the pointer leaves', async () => {
		const waffle = header()
		const nav = document.createElement('nav')
		nav.className = 'app-menu'
		waffle.parentNode.parentNode.insertBefore(nav, waffle.parentNode)
		nav.appendChild(waffle.parentNode)
		const coreLeave = vi.fn()
		nav.addEventListener('mouseleave', coreLeave)
		await loadScript()

		waffle.click()
		expect(isOpen()).toBe(true)
		nav.addEventListener('pointerleave', coreLeave)
		nav.dispatchEvent(new MouseEvent('mouseleave'))
		nav.dispatchEvent(new MouseEvent('pointerleave'))
		expect(
			coreLeave,
			'Nextcloud never hears the pointer leave a pinned menu',
		).not.toHaveBeenCalled()
		out(waffle)
		vi.advanceTimersByTime(1000)
		expect(isOpen()).toBe(true)
	})

	it('a touch screen or an unfocused window does not open on hover', async () => {
		const waffle = header()
		window.matchMedia = vi.fn(() => ({ matches: false }))
		await loadScript()

		over(waffle)
		vi.advanceTimersByTime(1000)
		expect(isOpen()).toBe(false)

		window.matchMedia = vi.fn(() => ({ matches: true }))
		document.hasFocus.mockReturnValue(false)
		over(waffle)
		vi.advanceTimersByTime(1000)
		expect(isOpen()).toBe(false)
	})

	it('leaves hover to a Nextcloud that does it itself, and pins its menu on a click', async () => {
		const waffle = header({ coreHover: true })
		await loadScript()

		over(waffle)
		vi.advanceTimersByTime(1000)
		expect(isOpen(), 'the script does not open it a second time').toBe(false)

		waffle.setAttribute('aria-expanded', 'true')
		const pin = new MouseEvent('click', { bubbles: true, cancelable: true })
		waffle.dispatchEvent(pin)
		expect(pin.defaultPrevented).toBe(true)
		expect(coreClicks).toBe(0)

		const trigger = document.querySelector('.app-menu__trigger')
		const coreLeave = vi.fn()
		trigger.addEventListener('mouseleave', coreLeave)
		trigger.dispatchEvent(new MouseEvent('mouseleave'))
		expect(
			coreLeave,
			'the pointer leaving does not reach Nextcloud',
		).not.toHaveBeenCalled()
	})

	it('the stylesheet hides the outlined tile and keeps the menu inside the window', () => {
		const css = readFileSync(
			resolve(__dirname, '../../css/app-menu.css'),
			'utf8',
		)
		expect(css).toMatch(
			/a\[data-thematiq-outlined\] \{\s*display: none !important;/,
		)
		expect(css).not.toContain('margin-inline-start: -34px')
	})

	it('the stylesheet turns transitions off under reduced motion', () => {
		const css = readFileSync(
			resolve(__dirname, '../../css/app-menu.css'),
			'utf8',
		)
		const block = css.slice(
			css.indexOf('@media (prefers-reduced-motion: reduce)'),
		)
		expect(block).toContain('.app-menu__popover-base')
		expect(block).toContain('transition: none !important')
	})
})
