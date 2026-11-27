/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/header-brand.js: the brand block in the top bar is drawn
 * for an app without its own brand, taken out when the app's brand appears,
 * and a change elsewhere on the page (a clock ticking) does not re-run the
 * style check or rewrite the bar.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/workplace-layout/spec.md
 * @spec openspec/changes/same-shell-every-page/specs/workplace-header-brand/spec.md#requirement-every-app-gets-the-brand-block-in-the-workplace-bar-req-tqwh-001
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const BAR =
	'<header id="header"><nav class="apps"></nav></header><main><span id="clock">10:00</span></main>'

const MAIL_APPS = {
	files: { active: false, name: 'Files' },
	mail: { active: true, name: ' Mail ', href: '/apps/mail/' },
}
const CAPABILITIES = {
	theming: { name: ' Zuiddrecht ' },
	nldesign: { logos: { emblem: '/emblem.svg' } },
}

let styleReads
let blockValue

/**
 * Give the page Nextcloud's initial state for the open apps and the capabilities.
 *
 * @param {object|null} apps The `core/apps` state.
 * @param {object|null} capabilities The `core/capabilities` state.
 * @param {object|Array|null} settings The `core/settingsNavEntries` state.
 */
function installState(apps, capabilities, settings = null) {
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) => {
				if (app === 'core' && key === 'apps') {
					return apps
				}
				if (app === 'core' && key === 'capabilities') {
					return capabilities
				}
				if (app === 'core' && key === 'settingsNavEntries') {
					return settings
				}
				return fallback
			},
		},
	}
}

/**
 * Run js/header-brand.js on the current page.
 *
 * @return {Promise<void>}
 */
async function loadScript() {
	vi.resetModules()
	await import('../../js/header-brand.js?t=' + Math.random())
}

/**
 * Let the MutationObserver deliver its records.
 *
 * @return {Promise<void>}
 */
const flush = () => new Promise((resolveFlush) => setTimeout(resolveFlush, 0))

beforeEach(() => {
	// A fresh body per test: an earlier test's observer watches the old one.
	document.documentElement.replaceChild(
		document.createElement('body'),
		document.body,
	)
	document.body.innerHTML = BAR
	installState(MAIL_APPS, CAPABILITIES)
	styleReads = 0
	blockValue = ' 1 '
	vi.spyOn(window, 'getComputedStyle').mockImplementation(() => {
		styleReads++
		return {
			getPropertyValue: (name) =>
				name === '--thematiq-brand-block' ? blockValue : '',
		}
	})
	vi.spyOn(window, 'requestAnimationFrame').mockImplementation((callback) => {
		callback(0)
		return 0
	})
})

afterEach(() => {
	vi.restoreAllMocks()
	delete global.OCP
})

describe('header brand', () => {
	it('draws the block first in the bar and marks the bar', async () => {
		await loadScript()
		const header = document.getElementById('header')
		expect(header.firstChild.className).toBe('thematiq-brand-host')
		expect(header.querySelector('.thematiq-brand__name').textContent).toBe(
			'Mail',
		)
		expect(header.hasAttribute('data-thematiq-brand')).toBe(true)
	})

	it('links to the open app and shows the emblem, the organisation and the divider', async () => {
		await loadScript()
		const host = document.querySelector('.thematiq-brand-host')
		const link = host.querySelector('a.thematiq-brand')
		expect(link.getAttribute('href')).toBe('/apps/mail/')

		const emblem = link.querySelector('img.thematiq-brand__emblem')
		expect(emblem.getAttribute('src')).toBe('/emblem.svg')
		expect(emblem.getAttribute('alt')).toBe('')

		expect(link.querySelector('.thematiq-brand__caption').textContent).toBe(
			'Zuiddrecht',
		)
		const divider = host.querySelector('.thematiq-brand__divider')
		expect(divider.getAttribute('aria-hidden')).toBe('true')
		expect(host.lastChild).toBe(divider)
	})

	it('leaves out the emblem and the caption the capabilities do not give, and links home without an href', async () => {
		installState({ deck: { active: true, name: 'Deck' } }, { theming: {} })
		await loadScript()
		const link = document.querySelector('a.thematiq-brand')
		expect(link.getAttribute('href')).toBe('/')
		expect(link.querySelector('.thematiq-brand__emblem')).toBeNull()
		expect(link.querySelector('.thematiq-brand__caption')).toBeNull()
		expect(link.querySelector('.thematiq-brand__name').textContent).toBe('Deck')
	})

	it('draws the block without capabilities at all', async () => {
		installState(MAIL_APPS, null)
		await loadScript()
		expect(document.querySelector('.thematiq-brand__name').textContent).toBe(
			'Mail',
		)
		expect(document.querySelector('.thematiq-brand__emblem')).toBeNull()
	})

	it('names the active settings section on a page without an open app', async () => {
		installState({ files: { active: false, name: 'Files' } }, CAPABILITIES, {
			settings: {
				active: true,
				name: 'Persoonlijke instellingen',
				href: '/settings/user',
			},
			admin_settings: { active: false, name: 'Beheerdersinstellingen' },
		})
		await loadScript()
		const link = document.querySelector('a.thematiq-brand')
		expect(link.querySelector('.thematiq-brand__name').textContent).toBe(
			'Persoonlijke instellingen',
		)
		expect(link.getAttribute('href')).toBe('/settings/user')
		expect(
			document.getElementById('header').hasAttribute('data-thematiq-brand'),
		).toBe(true)
	})

	describe('when Nextcloud marks no settings entry active (Nextcloud 34 on Personal and Administration settings)', () => {
		const NC34_SETTINGS = {
			accessibility_settings: { active: false, name: 'Appearance and accessibility', href: '/index.php/settings/user/theming' },
			settings: { active: false, name: 'Personal settings', href: '/index.php/settings/user' },
			admin_settings: { active: false, name: 'Administration settings', href: '/index.php/settings/admin/overview' },
			core_users: { active: false, name: 'Accounts', href: '/index.php/settings/users' },
			logout: { active: false, name: 'Log out', href: '/index.php/logout?requesttoken=x' },
		}
		const nameOn = async (path) => {
			window.history.pushState({}, '', path)
			installState({ files: { active: false, name: 'Files' } }, CAPABILITIES, NC34_SETTINGS)
			await loadScript()
			const name = document.querySelector('a.thematiq-brand .thematiq-brand__name')
			return name === null ? null : name.textContent
		}

		afterEach(() => window.history.pushState({}, '', '/'))

		// One page per test: every load of the script observes the bar, and the suite resets the page per test.
		it.each([
			['/index.php/settings/user', 'Personal settings'],
			['/index.php/settings/user/personal-info', 'Personal settings'],
			['/index.php/settings/user/theming', 'Appearance and accessibility'],
			['/index.php/settings/admin', 'Administration settings'],
			['/index.php/settings/admin/theming', 'Administration settings'],
		])('on %s names %s: the longest link prefix, else the entry sharing the first two path parts', async (path, name) => {
			expect(await nameOn(path)).toBe(name)
		})

		it.each([['/index.php/settings/userx'], ['/index.php/apps/files/']])('names no section on %s, which no settings entry covers', async (path) => {
			expect(await nameOn(path)).toBe(null)
		})
	})

	it('reads a settings navigation given as a list', async () => {
		installState(null, CAPABILITIES, [
			{ active: false, name: 'Persoonlijk' },
			{ active: true, name: 'Beheer', href: '/settings/admin' },
		])
		await loadScript()
		expect(document.querySelector('.thematiq-brand__name').textContent).toBe(
			'Beheer',
		)
	})

	it('prefers the open app over a settings entry', async () => {
		installState(MAIL_APPS, CAPABILITIES, {
			settings: { active: true, name: 'Persoonlijk' },
		})
		await loadScript()
		expect(document.querySelector('.thematiq-brand__name').textContent).toBe(
			'Mail',
		)
	})

	it.each([
		['no apps state', null],
		['no open app', { files: { active: false, name: 'Files' } }],
		['an open app without a name', { mail: { active: true, name: '   ' } }],
		['an open app whose name is not text', { mail: { active: true, name: 7 } }],
	])(
		'shows the emblem and the organisation alone, no name element, with %s',
		async (label, apps) => {
			installState(apps, CAPABILITIES)
			window.OC = { webroot: '/nc' }
			await loadScript()
			delete window.OC
			const header = document.getElementById('header')
			const link = header.querySelector('a.thematiq-brand')
			expect(link.querySelector('.thematiq-brand__emblem')).not.toBeNull()
			expect(link.querySelector('.thematiq-brand__caption').textContent).toBe(
				'Zuiddrecht',
			)
			expect(link.querySelector('.thematiq-brand__name')).toBeNull()
			expect(link.getAttribute('href')).toBe('/nc/')
			expect(header.hasAttribute('data-thematiq-brand')).toBe(true)
		},
	)

	it('draws nothing with nothing to show', async () => {
		installState({ files: { active: false, name: 'Files' } }, null)
		await loadScript()
		const header = document.getElementById('header')
		expect(header.querySelector('.thematiq-brand-host')).toBeNull()
		expect(header.hasAttribute('data-thematiq-brand')).toBe(false)
	})

	it('draws nothing when the initial state cannot be read', async () => {
		global.OCP = {
			InitialState: {
				loadState: () => {
					throw new Error('no state')
				},
			},
		}
		await loadScript()
		expect(document.querySelector('.thematiq-brand-host')).toBeNull()
	})

	it('draws nothing without OCP or without a top bar', async () => {
		delete global.OCP
		await loadScript()
		expect(document.querySelector('.thematiq-brand-host')).toBeNull()

		installState(MAIL_APPS, CAPABILITIES)
		document.body.innerHTML = '<main></main>'
		await loadScript()
		expect(document.querySelector('.thematiq-brand-host')).toBeNull()
	})

	it('waits for DOMContentLoaded while the page is still loading', async () => {
		const readyState = vi
			.spyOn(document, 'readyState', 'get')
			.mockReturnValue('loading')
		await loadScript()
		expect(document.querySelector('.thematiq-brand-host')).toBeNull()

		readyState.mockReturnValue('interactive')
		document.dispatchEvent(new Event('DOMContentLoaded'))
		expect(document.getElementById('header').firstChild.className).toBe(
			'thematiq-brand-host',
		)
	})

	it('takes the block out again when the sheet does not style it', async () => {
		blockValue = ''
		await loadScript()
		const header = document.getElementById('header')
		expect(header.querySelector('.thematiq-brand-host')).toBeNull()
		expect(header.hasAttribute('data-thematiq-brand')).toBe(false)
	})

	it('does not draw the block when the app already draws its own brand', async () => {
		document.body.innerHTML =
			BAR + '<nav><div class="cn-app-nav__brand"></div></nav>'
		await loadScript()
		expect(document.querySelector('.thematiq-brand-host')).toBeNull()
		expect(
			document.getElementById('header').hasAttribute('data-thematiq-brand'),
		).toBe(false)
	})

	it('does not re-check the style or rewrite the bar when the page changes elsewhere', async () => {
		await loadScript()
		const header = document.getElementById('header')
		const setAttribute = vi.spyOn(header, 'setAttribute')
		const before = styleReads

		for (let tick = 1; tick <= 5; tick++) {
			document.getElementById('clock').textContent = `10:0${tick}`
			document
				.querySelector('main')
				.appendChild(document.createElement('span'))
			await flush()
		}

		expect(styleReads).toBe(before)
		expect(setAttribute).not.toHaveBeenCalled()
		expect(header.firstChild.className).toBe('thematiq-brand-host')
	})

	it('reads the style once, however often the bar changes', async () => {
		await loadScript()
		const header = document.getElementById('header')
		header.appendChild(document.createElement('div'))
		await flush()
		header.insertBefore(document.createElement('div'), header.firstChild)
		await flush()

		expect(styleReads).toBe(1)
		expect(header.firstChild.className).toBe('thematiq-brand-host')
	})

	it('takes the block out when the app draws its own brand', async () => {
		await loadScript()
		const header = document.getElementById('header')
		const own = document.createElement('div')
		own.className = 'cn-brand-bar-host'
		header.appendChild(own)
		await flush()

		expect(header.querySelector('.thematiq-brand-host')).toBeNull()
		expect(header.hasAttribute('data-thematiq-brand')).toBe(false)
	})

	it('takes the block out when the app navigation mounts its brand inside a wrapper, and back when it goes', async () => {
		await loadScript()
		const header = document.getElementById('header')
		const wrapper = document.createElement('section')
		wrapper.innerHTML = '<div class="cn-app-nav__brand"></div>'
		document.querySelector('main').appendChild(wrapper)
		await flush()
		expect(header.querySelector('.thematiq-brand-host')).toBeNull()

		wrapper.remove()
		await flush()
		expect(header.firstChild.className).toBe('thematiq-brand-host')
		expect(header.hasAttribute('data-thematiq-brand')).toBe(true)
	})

	it('puts the block back first when a re-render of the bar moves it', async () => {
		await loadScript()
		const header = document.getElementById('header')
		header.insertBefore(document.createElement('div'), header.firstChild)
		await flush()

		expect(header.firstChild.className).toBe('thematiq-brand-host')
	})

	it('puts the block back when a re-render drops it', async () => {
		await loadScript()
		const header = document.getElementById('header')
		header.removeChild(header.querySelector('.thematiq-brand-host'))
		await flush()

		expect(header.firstChild.className).toBe('thematiq-brand-host')
	})

	it('batches the records of one frame into one sync', async () => {
		let pending = null
		window.requestAnimationFrame.mockImplementation((callback) => {
			pending = callback
			return 1
		})
		await loadScript()
		const header = document.getElementById('header')
		header.appendChild(document.createElement('div'))
		await flush()
		header.appendChild(document.createElement('div'))
		await flush()

		expect(window.requestAnimationFrame).toHaveBeenCalledTimes(1)
		pending(0)
		expect(header.firstChild.className).toBe('thematiq-brand-host')
	})
})
