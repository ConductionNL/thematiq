/**
 * @vitest-environment jsdom
 *
 * The Menu settings dialog (js/dialogs/menu-settings-dialog.js), the parts
 * menuSettingsDialog.spec.js leaves out: the external apps rows (move, show,
 * edit, delete, icon upload), dragging an app, the keyboard on the scope and
 * the icon size, the steppers, and what the dialog does when a request fails.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/apps-menu-settings/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const TEMPLATES = { moved: '{app} moved to place {place} of {total}.' }
const LABELS = new Proxy({}, { get: (target, key) => TEMPLATES[key] || String(key) })

const DEFAULTS = {
	style: 'workplace',
	columns: 4,
	rows: 6,
	rowsAboveFold: 2,
	showCreateActions: true,
	showExternalApps: true,
	shape: 'round',
	tiles: false,
	payoff: true,
	iconSize: 'large',
	iconColour: 'brand',
	order: [],
	pinned: [],
	hidden: [],
	externalApps: [],
}

const ENTRIES = ['launchpad', 'dossiq', 'files', 'calendar'].map((key, rank) => ({
	key,
	name: key.charAt(0).toUpperCase() + key.slice(1),
	icon: '/apps/' + key + '/img/app.svg',
	rank,
	external: false,
}))

/**
 * The answer of GET /app-menu/settings for an administrator with two external apps.
 *
 * @return {object} The answer.
 */
function adminAnswer() {
	const externalApps = [
		{ id: 'wiki', title: 'Wiki', payoff: 'Knowledge', url: 'https://wiki.example.org', icon: { library: 'book' }, visible: true, order: 0, own: false },
		{ id: 'news', title: 'News', payoff: '', url: 'not a url', icon: { upload: 'abc' }, visible: true, order: 1, own: false },
	]
	return {
		labels: LABELS,
		library: { link: 'M0 0', book: 'M1 1', news: 'M2 2' },
		stroke: ['book'],
		iconUrl: '/apps/thematiq/app-menu/icons/__id__',
		admin: true,
		effective: { ...DEFAULTS, externalApps },
		defaults: { ...DEFAULTS, externalApps },
		overrides: {},
		manageUrl: '/settings/apps',
	}
}

let requests
let reload
let failing

/**
 * Answer the dialog's requests: GET with the data, the rest with {} or a failure.
 *
 * @param {object} data The settings answer.
 */
function installFetch(data) {
	requests = []
	failing = new Set()
	global.fetch = vi.fn((url, options = {}) => {
		requests.push({ url, method: options.method, body: typeof options.body === 'string' ? JSON.parse(options.body) : options.body })
		const ok = !failing.has(options.method + ' ' + url)
		const json = options.method === 'GET' ? data : (url.endsWith('/icons') ? { id: 'up1' } : {})
		return Promise.resolve({ ok, status: ok ? 200 : 500, json: () => Promise.resolve(json) })
	})
}

async function flush() {
	for (let i = 0; i < 10; i++) {
		await Promise.resolve()
	}
}

async function openDialog(data = adminAnswer()) {
	installFetch(data)
	vi.resetModules()
	await import('../../js/dialogs/menu-settings-dialog.js?t=' + Math.random())
	await window.ThematiqMenuSettingsDialog.open({ menu: { labels: LABELS }, entries: ENTRIES })
	await flush()
	return document.querySelector('dialog.thematiq-menu-settings')
}

const external = (dialog, id) => dialog.querySelector('[data-external="' + id + '"]')
const externalOrder = (dialog) => Array.from(dialog.querySelectorAll('[data-external]')).map((li) => li.getAttribute('data-external'))
const appOrder = (dialog) => Array.from(dialog.querySelectorAll('[data-key]')).map((li) => li.getAttribute('data-key'))
const key = (target, name, extra = {}) => target.dispatchEvent(new KeyboardEvent('keydown', { key: name, bubbles: true, ...extra }))

async function save(dialog) {
	dialog.querySelector('[data-part="save"]').click()
	await flush()
	return requests.find((r) => r.method === 'POST' && r.url.endsWith('/defaults'))
}

describe('the Menu settings dialog, the rest', () => {
	beforeEach(() => {
		global.OC = { generateUrl: (url) => url, requestToken: 'token' }
		reload = vi.fn()
		vi.spyOn(window, 'location', 'get').mockReturnValue({ reload })
		window.confirm = vi.fn(() => true)
		HTMLDialogElement.prototype.showModal = function () {
			this.setAttribute('open', '')
		}
		HTMLDialogElement.prototype.close = function () {
			this.removeAttribute('open')
			this.dispatchEvent(new Event('close'))
		}
	})

	afterEach(() => {
		document.body.innerHTML = ''
		delete window.ThematiqMenuSettingsDialog
		delete global.OC
		delete global.fetch
		vi.restoreAllMocks()
	})

	it('draws an external app with its payoff and host, a library icon in strokes and an uploaded icon as an image', async () => {
		const dialog = await openDialog()
		expect(externalOrder(dialog)).toEqual(['wiki', 'news'])
		expect(external(dialog, 'wiki').querySelector('.thematiq-menu-settings__sub').textContent).toBe('Knowledge · wiki.example.org')
		expect(external(dialog, 'news').querySelector('.thematiq-menu-settings__sub').textContent).toBe('')

		const drawn = external(dialog, 'wiki').querySelector('.thematiq-menu-settings__app-icon svg')
		expect(drawn.getAttribute('fill')).toBe('none')
		expect(drawn.getAttribute('stroke')).toBe('currentColor')
		expect(external(dialog, 'news').querySelector('.thematiq-menu-settings__app-icon img').getAttribute('src')).toBe('/apps/thematiq/app-menu/icons/abc')
	})

	it('moves an external app with its buttons and with Alt and the arrows, and not past either end', async () => {
		const dialog = await openDialog()
		external(dialog, 'wiki').querySelector('[data-part="up"]').click()
		expect(externalOrder(dialog)).toEqual(['wiki', 'news'])

		external(dialog, 'wiki').querySelector('[data-part="down"]').click()
		expect(externalOrder(dialog)).toEqual(['news', 'wiki'])
		expect(dialog.querySelector('[role="status"]').textContent).toBe('Wiki moved to place 2 of 2.')
		expect(document.activeElement).toBe(external(dialog, 'wiki').querySelector('[data-part="down"]'))

		key(external(dialog, 'wiki').querySelector('[data-part="up"]'), 'ArrowUp', { altKey: true })
		expect(externalOrder(dialog)).toEqual(['wiki', 'news'])
		key(external(dialog, 'wiki'), 'ArrowDown')
		expect(externalOrder(dialog)).toEqual(['wiki', 'news'])

		const saved = await save(dialog)
		expect(saved.body.settings.externalApps.map((app) => [app.id, app.order])).toEqual([['wiki', 0], ['news', 1]])
	})

	it('hides and deletes an external app, and saves what is left', async () => {
		const dialog = await openDialog()
		const show = external(dialog, 'wiki').querySelector('[data-part="show"]')
		expect(show.getAttribute('aria-label')).toBe('show')
		show.click()
		expect(external(dialog, 'wiki').classList.contains('thematiq-menu-settings__row--off')).toBe(true)

		external(dialog, 'news').querySelector('[data-part="delete"]').click()
		expect(externalOrder(dialog)).toEqual(['wiki'])

		const saved = await save(dialog)
		expect(saved.body.settings.externalApps).toEqual([expect.objectContaining({ id: 'wiki', visible: false })])
	})

	it('edits an external app in place, and Cancel or the close button keeps it as it was', async () => {
		const dialog = await openDialog()
		external(dialog, 'wiki').querySelector('[data-part="edit"]').click()
		let form = dialog.querySelector('dialog.thematiq-menu-settings--form')
		expect(form.querySelector('#' + form.getAttribute('aria-labelledby')).textContent).toBe('editExternal')
		expect(form.querySelector('[data-part="submit"]').textContent).toBe('save')
		expect(document.activeElement.id).toBe('thematiq-menu-settings-field-title')
		form.querySelector('.thematiq-menu-settings__close').click()
		expect(dialog.querySelector('dialog.thematiq-menu-settings--form')).toBeNull()
		expect(document.activeElement).toBe(external(dialog, 'wiki').querySelector('[data-part="edit"]'))

		external(dialog, 'wiki').querySelector('[data-part="edit"]').click()
		form = dialog.querySelector('dialog.thematiq-menu-settings--form')
		const title = form.querySelector('#thematiq-menu-settings-field-title')
		title.value = '  Intranet wiki  '
		title.dispatchEvent(new Event('input'))
		form.querySelector('[data-part="submit"]').click()

		expect(external(dialog, 'wiki').textContent).toContain('Intranet wiki')
		const saved = await save(dialog)
		expect(saved.body.settings.externalApps[0]).toMatchObject({ id: 'wiki', title: 'Intranet wiki', url: 'https://wiki.example.org' })
	})

	it('refuses an address that only looks like https', async () => {
		const dialog = await openDialog()
		external(dialog, 'wiki').querySelector('[data-part="edit"]').click()
		const form = dialog.querySelector('dialog.thematiq-menu-settings--form')
		const url = form.querySelector('#thematiq-menu-settings-field-url')
		url.value = 'https://[broken'
		url.dispatchEvent(new Event('input'))
		form.querySelector('[data-part="submit"]').click()

		expect(form.querySelector('#thematiq-menu-settings-field-url').getAttribute('aria-invalid')).toBe('true')
		expect(document.activeElement.id).toBe('thematiq-menu-settings-field-url')
		expect(external(dialog, 'wiki').textContent).toContain('wiki.example.org')
	})

	it('uploads an icon for an external app, and says so when the upload fails', async () => {
		const dialog = await openDialog()
		dialog.querySelector('[data-part="add-external"]').click()
		let form = dialog.querySelector('dialog.thematiq-menu-settings--form')
		const input = form.querySelector('input[type="file"]')
		const click = vi.spyOn(input, 'click').mockImplementation(() => {})
		form.querySelector('[data-part="upload"]').click()
		expect(click).toHaveBeenCalled()

		input.dispatchEvent(new Event('change'))
		await flush()
		expect(requests.some((r) => r.url.endsWith('/icons'))).toBe(false)

		const file = new File(['<svg/>'], 'icon.svg', { type: 'image/svg+xml' })
		Object.defineProperty(input, 'files', { value: [file] })
		input.dispatchEvent(new Event('change'))
		await flush()
		const upload = requests.find((r) => r.url === '/apps/thematiq/app-menu/icons')
		expect(upload.method).toBe('POST')
		expect(upload.body.get('icon')).toBeInstanceOf(File)
		expect(form.querySelector('[data-part="upload"]').textContent).toBe('uploaded')

		form.querySelector('.thematiq-menu-settings__close').click()
		dialog.querySelector('[data-part="add-external"]').click()
		form = dialog.querySelector('dialog.thematiq-menu-settings--form')
		const second = form.querySelector('input[type="file"]')
		Object.defineProperty(second, 'files', { value: [file] })
		failing.add('POST /apps/thematiq/app-menu/icons')
		second.dispatchEvent(new Event('change'))
		await flush()
		expect(form.textContent).toContain('uploadFailed')
		expect(form.querySelector('[data-part="upload"]').textContent).toBe('upload')
	})

	it('moves the icon choice by a row with the up and down arrows', async () => {
		const dialog = await openDialog()
		dialog.querySelector('[data-part="add-external"]').click()
		const form = dialog.querySelector('dialog.thematiq-menu-settings--form')
		form.querySelector('[data-icon="news"]').click()
		expect(form.querySelector('[data-icon="news"]').getAttribute('aria-checked')).toBe('true')
		key(form.querySelector('[data-icon="news"]'), 'ArrowDown')
		// Three icons, a row of eight: down wraps to (2 + 8) % 3.
		expect(form.querySelector('[data-icon="book"]').getAttribute('aria-checked')).toBe('true')
		key(form.querySelector('[data-icon="book"]'), 'Enter')
		expect(form.querySelector('[data-icon="book"]').getAttribute('aria-checked')).toBe('true')
	})

	it('drags an app by its handle to before the app under the pointer', async () => {
		const dialog = await openDialog()
		const list = dialog.querySelector('.thematiq-menu-settings__list--apps')
		Array.from(list.children).forEach((li, i) => {
			li.getBoundingClientRect = () => ({ top: i * 40, height: 40 })
		})
		const handle = list.querySelector('[data-key="calendar"] [data-drag]')
		handle.dispatchEvent(new MouseEvent('pointerdown', { bubbles: true, button: 0, clientY: 130 }))
		document.dispatchEvent(new MouseEvent('pointermove', { clientY: 128 }))
		expect(list.querySelector('[data-drop-before]')).toBeNull()
		document.dispatchEvent(new MouseEvent('pointermove', { clientY: 50 }))
		expect(list.querySelector('[data-key="dossiq"]').hasAttribute('data-drop-before')).toBe(true)
		expect(list.querySelector('[data-key="calendar"]').hasAttribute('data-dragging')).toBe(true)
		document.dispatchEvent(new MouseEvent('pointerup', {}))

		expect(appOrder(dialog)).toEqual(['launchpad', 'calendar', 'dossiq', 'files'])
		const saved = await save(dialog)
		expect(saved.body.settings.order).toEqual(['launchpad', 'calendar', 'dossiq', 'files'])
	})

	it('drops an app dragged below the last row at the end, and ignores a click without a move or off the handle', async () => {
		const dialog = await openDialog()
		let list = dialog.querySelector('.thematiq-menu-settings__list--apps')
		const boxes = () => Array.from(list.children).forEach((li, i) => {
			li.getBoundingClientRect = () => ({ top: i * 40, height: 40 })
		})
		boxes()
		list.querySelector('[data-key="launchpad"] [data-drag]').dispatchEvent(new MouseEvent('pointerdown', { bubbles: true, button: 0, clientY: 10 }))
		document.dispatchEvent(new MouseEvent('pointerup', {}))
		expect(appOrder(dialog)).toEqual(['launchpad', 'dossiq', 'files', 'calendar'])

		list.querySelector('[data-key="launchpad"] .thematiq-menu-settings__name').dispatchEvent(new MouseEvent('pointerdown', { bubbles: true, button: 0, clientY: 10 }))
		document.dispatchEvent(new MouseEvent('pointermove', { clientY: 500 }))
		document.dispatchEvent(new MouseEvent('pointerup', {}))
		expect(appOrder(dialog)).toEqual(['launchpad', 'dossiq', 'files', 'calendar'])

		list = dialog.querySelector('.thematiq-menu-settings__list--apps')
		boxes()
		list.querySelector('[data-key="launchpad"] [data-drag]').dispatchEvent(new MouseEvent('pointerdown', { bubbles: true, button: 0, clientY: 10 }))
		document.dispatchEvent(new MouseEvent('pointermove', { clientY: 500 }))
		document.dispatchEvent(new MouseEvent('pointerup', {}))
		expect(appOrder(dialog)).toEqual(['dossiq', 'files', 'calendar', 'launchpad'])
	})

	it('switches the scope with the arrow keys and ignores other keys', async () => {
		const dialog = await openDialog()
		key(dialog.querySelector('[data-scope="everyone"]'), 'Enter')
		expect(dialog.querySelector('[data-scope="everyone"]').getAttribute('aria-checked')).toBe('true')
		key(dialog.querySelector('[data-scope="everyone"]'), 'ArrowRight')
		expect(dialog.querySelector('[data-scope="me"]').getAttribute('aria-checked')).toBe('true')
		expect(document.activeElement).toBe(dialog.querySelector('[data-scope="me"]'))
		key(dialog.querySelector('[data-scope="me"]'), 'ArrowLeft')
		expect(dialog.querySelector('[data-scope="everyone"]').getAttribute('aria-checked')).toBe('true')
	})

	it('falls back to Menu items when Only for me drops the open Theming tab', async () => {
		const dialog = await openDialog()
		dialog.querySelector('#thematiq-menu-settings-tab-theming').click()
		dialog.querySelector('[data-scope="me"]').click()
		expect(dialog.querySelector('[role="tab"][aria-selected="true"]').id).toBe('thematiq-menu-settings-tab-items')
	})

	it('moves to the last tab with ArrowLeft from the first, to the first with Home, and ignores other keys', async () => {
		const dialog = await openDialog()
		key(dialog.querySelector('#thematiq-menu-settings-tab-items'), 'ArrowLeft')
		expect(document.activeElement.id).toBe('thematiq-menu-settings-tab-theming')
		key(document.activeElement, 'Home')
		expect(document.activeElement.id).toBe('thematiq-menu-settings-tab-items')
		key(document.activeElement, 'a')
		expect(dialog.querySelector('[role="tab"][aria-selected="true"]').id).toBe('thematiq-menu-settings-tab-items')
	})

	it('sets the icon size by click and by arrow key', async () => {
		const dialog = await openDialog()
		dialog.querySelector('#thematiq-menu-settings-tab-theming').click()
		const checked = () => dialog.querySelector('[data-size][aria-checked="true"]').getAttribute('data-size')
		const other = dialog.querySelector('[data-size]:not([aria-checked="true"])').getAttribute('data-size')
		dialog.querySelector('[data-size="' + other + '"]').click()
		expect(checked()).toBe(other)
		key(dialog.querySelector('[data-size="' + other + '"]'), 'Tab')
		expect(checked()).toBe(other)
		key(dialog.querySelector('[data-size="' + other + '"]'), 'ArrowRight')
		expect(checked()).toBe('large')

		const saved = await save(dialog)
		expect(saved.body.settings.iconSize).toBe('large')
	})

	it('clamps a typed column count to its range', async () => {
		const dialog = await openDialog()
		dialog.querySelector('#thematiq-menu-settings-tab-configuration').click()
		const columns = dialog.querySelector('#thematiq-menu-settings-columns')
		const max = columns.getAttribute('max')
		columns.value = '99'
		columns.dispatchEvent(new Event('change'))
		expect(dialog.querySelector('#thematiq-menu-settings-columns').value).toBe(max)
		expect(dialog.querySelector('[aria-label="moreColumns"]').disabled).toBe(true)

		const saved = await save(dialog)
		expect(saved.body.settings.columns).toBe(Number(max))
	})

	it('resets Only for me with its own question and address, and does nothing when the question is declined', async () => {
		const dialog = await openDialog()
		dialog.querySelector('[data-scope="me"]').click()
		window.confirm.mockReturnValueOnce(false)
		dialog.querySelector('[data-part="reset"]').click()
		await flush()
		expect(window.confirm).toHaveBeenCalledWith('resetOnlyMe')
		expect(requests.some((r) => r.method === 'DELETE')).toBe(false)

		dialog.querySelector('[data-part="reset"]').click()
		await flush()
		expect(requests.find((r) => r.method === 'DELETE').url).toBe('/apps/thematiq/app-menu/settings')
		expect(reload).toHaveBeenCalled()
	})

	it('says so when the reset fails', async () => {
		const dialog = await openDialog()
		failing.add('DELETE /apps/thematiq/app-menu/defaults')
		dialog.querySelector('[data-part="reset"]').click()
		await flush()
		const alert = dialog.querySelector('[role="alert"]')
		expect(alert.hidden).toBe(false)
		expect(alert.textContent).toBe('saveFailed')
		expect(reload).not.toHaveBeenCalled()
	})

	it('says in the pins live region that the settings could not be read, and opens nothing', async () => {
		document.body.innerHTML = '<div id="thematiq-app-pins-live"></div>'
		installFetch({})
		failing.add('GET /apps/thematiq/app-menu/settings')
		vi.resetModules()
		await import('../../js/dialogs/menu-settings-dialog.js?t=' + Math.random())
		const result = await window.ThematiqMenuSettingsDialog.open({ menu: { labels: LABELS }, entries: ENTRIES })

		expect(result).toBeNull()
		expect(document.querySelector('dialog.thematiq-menu-settings')).toBeNull()
		expect(document.getElementById('thematiq-app-pins-live').textContent).toBe('settingsFailed')
	})
})
