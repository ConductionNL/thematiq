/**
 * @vitest-environment jsdom
 *
 * The Menu settings dialog (js/dialogs/menu-settings-dialog.js): the tabs, the
 * scope switch for administrators, the apps with order, pin and show, the
 * external apps form, and what Save, Reset and Cancel send.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/apps-menu-settings/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const TEMPLATES = {
	moved: '{app} moved to place {place} of {total}.',
	previewSentence: '{shape}, {size} icons in {colour}, {payoff} and {tiles}.',
}
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

const ENTRIES = [
	'launchpad',
	'dossiq',
	'files',
	'calendar',
	'deck',
	'notes',
	'mail',
].map((key, rank) => ({
	key,
	name: key.charAt(0).toUpperCase() + key.slice(1),
	icon: '/apps/' + key + '/img/app.svg',
	description: '',
	rank,
	external: false,
}))

function answer(admin, extra = {}) {
	const effective = {
		...DEFAULTS,
		pinned: ['files'],
		externalApps: [
			{
				id: 'adm',
				title: 'Wiki',
				payoff: '',
				url: 'https://wiki.example.org',
				icon: { library: 'book' },
				visible: true,
				order: 0,
				own: false,
			},
		],
	}
	const data = {
		labels: LABELS,
		library: { link: 'M0 0', book: 'M1 1', news: 'M2 2' },
		iconUrl: '/apps/thematiq/app-menu/icons/__id__',
		admin,
		effective,
		overrides: {},
		...extra,
	}
	if (admin) {
		data.defaults = { ...DEFAULTS, externalApps: [effective.externalApps[0]] }
		data.manageUrl = '/settings/apps'
	}
	return data
}

let requests
let reload

function installFetch(data, status = 200) {
	requests = []
	global.fetch = vi.fn((url, options = {}) => {
		requests.push({
			url,
			method: options.method,
			body:
				options.body && typeof options.body === 'string'
					? JSON.parse(options.body)
					: options.body,
		})
		const ok = options.method === 'GET' || status < 300
		return Promise.resolve({
			ok,
			status: ok ? 200 : status,
			json: () => Promise.resolve(options.method === 'GET' ? data : {}),
		})
	})
}

async function flush() {
	for (let i = 0; i < 10; i++) {
		await Promise.resolve()
	}
}

async function openDialog(data, opener) {
	installFetch(data)
	vi.resetModules()
	await import('../../js/dialogs/menu-settings-dialog.js?t=' + Math.random())
	await window.ThematiqMenuSettingsDialog.open({
		menu: { labels: LABELS },
		entries: ENTRIES,
		opener,
	})
	await flush()
	return document.querySelector('dialog.thematiq-menu-settings')
}

function tabNames(dialog) {
	return Array.from(dialog.querySelectorAll('[role="tab"]')).map(
		(tab) => tab.textContent,
	)
}

function row(dialog, key) {
	return dialog.querySelector('[data-key="' + key + '"]')
}

function order(dialog) {
	return Array.from(dialog.querySelectorAll('[data-key]')).map((li) =>
		li.getAttribute('data-key'),
	)
}

describe('the Menu settings dialog', () => {
	beforeEach(() => {
		global.OC = { generateUrl: (url) => url, requestToken: 'token' }
		reload = vi.fn()
		vi.spyOn(window, 'location', 'get').mockReturnValue({ reload })
		window.confirm = vi.fn(() => true)
		if (!HTMLDialogElement.prototype.showModal) {
			HTMLDialogElement.prototype.showModal = function () {
				this.setAttribute('open', '')
			}
		}
		if (!HTMLDialogElement.prototype.close) {
			HTMLDialogElement.prototype.close = function () {
				this.removeAttribute('open')
				this.dispatchEvent(new Event('close'))
			}
		}
	})

	afterEach(() => {
		document.body.innerHTML = ''
		delete window.ThematiqMenuSettingsDialog
		delete global.OC
		delete global.fetch
		vi.restoreAllMocks()
	})

	it('is a titled modal; a person gets Menu items and a locked Configuration, without the scope or Theming', async () => {
		const dialog = await openDialog(answer(false))

		expect(requests[0]).toMatchObject({
			url: '/apps/thematiq/app-menu/settings',
			method: 'GET',
		})
		expect(dialog.hasAttribute('open')).toBe(true)
		expect(
			dialog.querySelector('#' + dialog.getAttribute('aria-labelledby'))
				.textContent,
		).toBe('title')
		expect(
			dialog.querySelector('[role="radiogroup"][aria-label="scope"]'),
		).toBeNull()
		expect(tabNames(dialog)).toEqual(['tabItems', 'tabConfiguration'])
		expect(document.activeElement.getAttribute('role')).toBe('tab')

		const config = dialog.querySelector(
			'#thematiq-menu-settings-tab-configuration',
		)
		config.click()
		const panel = dialog.querySelector('[role="tabpanel"]')
		expect(panel.getAttribute('aria-labelledby')).toBe(
			'thematiq-menu-settings-tab-configuration',
		)
		expect(
			dialog.querySelector('#thematiq-menu-settings-columns').disabled,
		).toBe(true)
		expect(panel.textContent).toContain('setByAdmin')
		expect(panel.querySelector('a[href="/settings/apps"]')).toBeNull()
	})

	it('moves between the tabs with the arrow keys', async () => {
		const dialog = await openDialog(answer(true))

		expect(tabNames(dialog)).toEqual([
			'tabItems',
			'tabConfiguration',
			'tabTheming',
		])
		dialog
			.querySelector('[role="tab"]')
			.dispatchEvent(
				new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }),
			)
		expect(document.activeElement.id).toBe(
			'thematiq-menu-settings-tab-configuration',
		)
		expect(document.activeElement.getAttribute('aria-selected')).toBe('true')
		document.activeElement.dispatchEvent(
			new KeyboardEvent('keydown', { key: 'End', bubbles: true }),
		)
		expect(document.activeElement.id).toBe('thematiq-menu-settings-tab-theming')
	})

	it('lets an administrator switch to Only for me, which has no Theming tab', async () => {
		const dialog = await openDialog(answer(true))
		const scope = dialog.querySelector('[role="radiogroup"][aria-label="scope"]')
		expect(scope.querySelector('[aria-checked="true"]').textContent).toBe(
			'everyone',
		)

		scope.querySelector('[data-scope="me"]').click()
		expect(
			dialog.querySelector('[data-scope="me"]').getAttribute('aria-checked'),
		).toBe('true')
		expect(tabNames(dialog)).toEqual(['tabItems', 'tabConfiguration'])
	})

	it('orders, pins and hides apps, and a person saves only what they changed', async () => {
		const dialog = await openDialog(answer(false))

		expect(order(dialog)).toEqual(ENTRIES.map((entry) => entry.key))
		row(dialog, 'dossiq').querySelector('[data-part="up"]').click()
		expect(order(dialog).slice(0, 2)).toEqual(['dossiq', 'launchpad'])
		expect(
			document.activeElement.closest('[data-key]').getAttribute('data-key'),
		).toBe('dossiq')
		row(dialog, 'deck')
			.querySelector('[data-part="down"]')
			.dispatchEvent(
				new KeyboardEvent('keydown', {
					key: 'ArrowUp',
					altKey: true,
					bubbles: true,
				}),
			)
		expect(order(dialog).indexOf('deck')).toBe(3)

		const pin = row(dialog, 'calendar').querySelector('[data-part="pin"]')
		expect(pin.getAttribute('aria-pressed')).toBe('false')
		pin.click()
		expect(
			row(dialog, 'calendar')
				.querySelector('[data-part="pin"]')
				.getAttribute('aria-pressed'),
		).toBe('true')
		const show = row(dialog, 'notes').querySelector('[data-part="show"]')
		expect(show.getAttribute('role')).toBe('switch')
		expect(show.getAttribute('aria-label')).toBe('show')
		show.click()
		expect(
			row(dialog, 'notes')
				.querySelector('[data-part="show"]')
				.getAttribute('aria-checked'),
		).toBe('false')

		dialog.querySelector('[data-part="save"]').click()
		await flush()
		const saved = requests.find((r) => r.method === 'POST')
		expect(saved.url).toBe('/apps/thematiq/app-menu/settings')
		expect(Object.keys(saved.body.settings).sort()).toEqual([
			'hidden',
			'order',
			'pinned',
		])
		expect(saved.body.settings.pinned).toEqual(['files', 'calendar'])
		expect(saved.body.settings.hidden).toEqual(['notes'])
		expect(saved.body.settings.order.slice(0, 2)).toEqual([
			'dossiq',
			'launchpad',
		])
		expect(reload).toHaveBeenCalled()
	})

	it('pins at most five apps', async () => {
		const data = answer(false)
		data.effective.pinned = ['launchpad', 'dossiq', 'files', 'calendar', 'deck']
		const dialog = await openDialog(data)

		expect(
			row(dialog, 'notes').querySelector('[data-part="pin"]').disabled,
		).toBe(true)
		expect(row(dialog, 'deck').querySelector('[data-part="pin"]').disabled).toBe(
			false,
		)
	})

	it('saves the defaults for everyone with every setting', async () => {
		const dialog = await openDialog(answer(true))
		dialog.querySelector('#thematiq-menu-settings-tab-configuration').click()
		dialog.querySelector('[aria-label="fewerColumns"]').click()
		expect(dialog.querySelector('#thematiq-menu-settings-columns').value).toBe(
			'3',
		)
		dialog.querySelector('#thematiq-menu-settings-tab-theming').click()
		dialog
			.querySelector(
				'input[name="thematiq-menu-settings-shape"][value="square"]',
			)
			.dispatchEvent(new Event('change'))
		dialog.querySelector('#thematiq-menu-settings-switch-tiles').click()

		dialog.querySelector('[data-part="save"]').click()
		await flush()
		const saved = requests.find((r) => r.method === 'POST')
		expect(saved.url).toBe('/apps/thematiq/app-menu/defaults')
		expect(saved.body.settings).toMatchObject({
			columns: 3,
			shape: 'square',
			tiles: true,
			iconSize: 'large',
		})
		expect(saved.body.settings.externalApps).toHaveLength(1)
	})

	it('adds an external app after checking its title and https address', async () => {
		const dialog = await openDialog(answer(false))
		expect(dialog.textContent).toContain('noExternal')

		dialog.querySelector('[data-part="add-external"]').click()
		const form = dialog.querySelector('dialog.thematiq-menu-settings--form')
		expect(
			form.querySelector('#' + form.getAttribute('aria-labelledby'))
				.textContent,
		).toBe('addExternal')
		const url = form.querySelector('#thematiq-menu-settings-field-url')
		url.value = 'http://intranet.example.org'
		url.dispatchEvent(new Event('input'))
		form.querySelector('[data-part="submit"]').click()
		expect(
			form
				.querySelector('#thematiq-menu-settings-field-title')
				.getAttribute('aria-invalid'),
		).toBe('true')
		expect(
			form
				.querySelector('#thematiq-menu-settings-field-url')
				.getAttribute('aria-invalid'),
		).toBe('true')

		const title = form.querySelector('#thematiq-menu-settings-field-title')
		title.value = 'Intranet'
		title.dispatchEvent(new Event('input'))
		const url2 = form.querySelector('#thematiq-menu-settings-field-url')
		url2.value = 'https://intranet.example.org'
		url2.dispatchEvent(new Event('input'))
		const icons = form.querySelector(
			'[role="radiogroup"][aria-label="iconLibrary"]',
		)
		icons
			.querySelector('[data-icon="link"]')
			.dispatchEvent(
				new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }),
			)
		expect(
			form.querySelector('[data-icon="book"]').getAttribute('aria-checked'),
		).toBe('true')
		form.querySelector('[data-part="submit"]').click()

		expect(
			dialog.querySelector('dialog.thematiq-menu-settings--form'),
		).toBeNull()
		const added = dialog.querySelector('[data-external]')
		expect(added.textContent).toContain('Intranet')
		expect(added.textContent).toContain('intranet.example.org')

		dialog.querySelector('[data-part="save"]').click()
		await flush()
		const saved = requests.find((r) => r.method === 'POST')
		expect(saved.body.settings.externalApps).toEqual([
			expect.objectContaining({
				title: 'Intranet',
				url: 'https://intranet.example.org',
				icon: { library: 'book' },
				visible: true,
			}),
		])
	})

	it('resets the scope after a confirmation, and Cancel sends nothing and gives focus back', async () => {
		const opener = document.createElement('button')
		document.body.appendChild(opener)
		let dialog = await openDialog(answer(false), opener)
		dialog.querySelector('[data-part="cancel"]').click()
		expect(document.querySelector('dialog.thematiq-menu-settings')).toBeNull()
		expect(document.activeElement).toBe(opener)
		expect(requests.filter((r) => r.method !== 'GET')).toHaveLength(0)

		dialog = await openDialog(answer(true), opener)
		dialog.querySelector('[data-part="reset"]').click()
		await flush()
		expect(window.confirm).toHaveBeenCalledWith('resetEveryone')
		expect(requests.find((r) => r.method === 'DELETE').url).toBe(
			'/apps/thematiq/app-menu/defaults',
		)
	})

	it('says so when the settings could not be saved', async () => {
		const dialog = await openDialog(answer(false))
		installFetch(answer(false), 400)
		dialog.querySelector('[data-part="save"]').click()
		await flush()
		const alert = dialog.querySelector('[role="alert"]')
		expect(alert.hidden).toBe(false)
		expect(alert.textContent).toBe('saveFailed')
		expect(reload).not.toHaveBeenCalled()
	})

	it('draws Theming as the board: inline colour radios, the size beside its name, and a sentence that follows the settings', async () => {
		const dialog = await openDialog(answer(true))
		dialog.querySelector('#thematiq-menu-settings-tab-theming').click()

		const group = dialog.querySelector(
			'[role="radiogroup"][aria-labelledby="thematiq-menu-settings-icon-size"]',
		)
		expect(
			dialog.querySelector('#thematiq-menu-settings-icon-size').textContent,
		).toBe('iconSize')
		expect(group.querySelector('[aria-checked="true"]').textContent).toBe(
			'large',
		)
		expect(
			dialog.querySelectorAll(
				'.thematiq-menu-settings__inline-radio input[name="thematiq-menu-settings-colour"]',
			),
		).toHaveLength(2)
		const caption = () =>
			dialog.querySelector('.thematiq-menu-settings__figure figcaption')
				.textContent
		expect(caption()).toBe(
			'round, sizeLarge icons in colourBrand, withPayoff and withoutTiles.',
		)

		dialog.querySelector('#thematiq-menu-settings-switch-tiles').click()
		dialog
			.querySelector(
				'input[name="thematiq-menu-settings-colour"][value="neutral"]',
			)
			.dispatchEvent(new Event('change'))
		expect(caption()).toContain('withTiles')
		expect(caption()).toContain('colourNeutral')
	})

	it('offers the picker icons only, and colours only the work apps in the brand', async () => {
		const data = answer(false)
		data.picker = ['link', 'book']
		installFetch(data)
		vi.resetModules()
		await import('../../js/dialogs/menu-settings-dialog.js?t=' + Math.random())
		const entries = ENTRIES.map((entry, i) => ({ ...entry, work: i < 2 }))
		await window.ThematiqMenuSettingsDialog.open({
			menu: { labels: LABELS },
			entries,
		})
		await flush()
		const dialog = document.querySelector('dialog.thematiq-menu-settings')

		expect(
			row(dialog, 'launchpad').querySelector(
				'.thematiq-menu-settings__app-icon--work',
			),
		).not.toBeNull()
		expect(
			row(dialog, 'files').querySelector(
				'.thematiq-menu-settings__app-icon--work',
			),
		).toBeNull()
		dialog.querySelector('[data-part="add-external"]').click()
		const icons = dialog.querySelectorAll(
			'dialog.thematiq-menu-settings--form [role="radiogroup"] [data-icon]',
		)
		expect(Array.from(icons).map((b) => b.getAttribute('data-icon'))).toEqual([
			'link',
			'book',
		])
	})

	it('lays the grid section out as the board, steppers beside the preview', async () => {
		const dialog = await openDialog(answer(true))
		dialog.querySelector('#thematiq-menu-settings-tab-configuration').click()

		const grid = dialog.querySelector(
			'section[aria-labelledby="thematiq-menu-settings-grid"]',
		)
		expect(
			grid.classList.contains('thematiq-menu-settings__section--grid'),
		).toBe(true)
		expect(
			grid.querySelector(':scope > .thematiq-menu-settings__steppers'),
		).not.toBeNull()
		expect(
			grid.querySelector(':scope > .thematiq-menu-settings__figure'),
		).not.toBeNull()
	})

	it('marks the new-tab note of the external app form with the open-in-new-tab icon', async () => {
		const dialog = await openDialog(answer(false))
		dialog.querySelector('[data-part="add-external"]').click()

		const note = dialog.querySelector(
			'dialog.thematiq-menu-settings--form .thematiq-menu-settings__note',
		)
		expect(note.textContent).toContain('alwaysNewTab')
		expect(note.querySelector('svg path').getAttribute('d')).toMatch(
			/^M14,3V5H17\.59/,
		)
	})
})
