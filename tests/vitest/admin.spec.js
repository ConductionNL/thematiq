/**
 * @vitest-environment jsdom
 *
 * Unit tests for the admin page's per-app theming list and its HTML escaping.
 *
 * The three decisions the list makes (which apps a search keeps, the themed
 * count on the trigger, and which apps are posted as excluded) live in
 * js/lib/appTheming.js and are tested directly. The wiring is tested from the
 * caller: js/admin.js is loaded against a minimal settings page and driven
 * with real DOM events, so a helper that nothing calls cannot pass here.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/admin-js-test-coverage/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import appTheming from '../../js/lib/appTheming.js'

const { matchesAppSearch, countThemed, buildDisabledAppsPayload } = appTheming

describe('matchesAppSearch', () => {
	it('keeps every app for an empty or blank query', () => {
		expect(matchesAppSearch('Files', '')).toBe(true)
		expect(matchesAppSearch('Files', '   ')).toBe(true)
		expect(matchesAppSearch('Files', undefined)).toBe(true)
	})

	it('matches a substring regardless of case and surrounding spaces', () => {
		expect(matchesAppSearch('Files', 'fil')).toBe(true)
		expect(matchesAppSearch('files', ' FIL ')).toBe(true)
		expect(matchesAppSearch('Deck', 'ec')).toBe(true)
	})

	it('drops an app whose name does not contain the query', () => {
		expect(matchesAppSearch('Files', 'mail')).toBe(false)
		expect(matchesAppSearch('', 'mail')).toBe(false)
	})
})

describe('countThemed', () => {
	it('counts zero of zero for no apps', () => {
		expect(countThemed([])).toEqual({ themed: 0, total: 0 })
		expect(countThemed(undefined)).toEqual({ themed: 0, total: 0 })
	})

	it('counts all, none and a mix', () => {
		expect(countThemed([{ checked: true }, { checked: true }])).toEqual({
			themed: 2,
			total: 2,
		})
		expect(countThemed([{ checked: false }, { checked: false }])).toEqual({
			themed: 0,
			total: 2,
		})
		expect(
			countThemed([{ checked: true }, { checked: false }, { checked: true }]),
		).toEqual({
			themed: 2,
			total: 3,
		})
	})
})

describe('buildDisabledAppsPayload', () => {
	it('excludes nothing when every app is themed', () => {
		expect(
			buildDisabledAppsPayload([
				{ id: 'files', checked: true },
				{ id: 'deck', checked: true },
			]),
		).toEqual([])
	})

	it('excludes every app when none is themed', () => {
		expect(
			buildDisabledAppsPayload([
				{ id: 'files', checked: false },
				{ id: 'deck', checked: false },
			]),
		).toEqual(['files', 'deck'])
	})

	it('lists exactly the unchecked apps, in order, and never a checked one', () => {
		const payload = buildDisabledAppsPayload([
			{ id: 'files', checked: true },
			{ id: 'deck', checked: false },
			{ id: 'mail', checked: true },
			{ id: 'talk', checked: false },
		])
		expect(payload).toEqual(['deck', 'talk'])
		expect(payload).not.toContain('files')
		expect(payload).not.toContain('mail')
	})
})

/* -------------------------------------------------------------------------
 * js/admin.js, driven from the page
 * ---------------------------------------------------------------------- */

const TOKEN_SETS = [
	{
		id: 'rijkshuisstijl',
		name: 'Rijkshuisstijl',
		theming: { primary_color: '#111111', background_color: '#222222' },
	},
	{
		id: 'gemeente-demo',
		name: 'Gemeente Demo',
		theming: { primary_color: '#0000ff', background_color: '#ffff00' },
	},
]

function installGlobals(state) {
	global.t = (app, text, params) =>
		params === undefined
			? text
			: Object.keys(params).reduce(
					(acc, key) => acc.replace('{' + key + '}', params[key]),
					text,
				)
	global.n = (app, singular, plural, count) => (count === 1 ? singular : plural)
	global.OC = {
		generateUrl: (url) => url,
		linkTo: (app, path) => path,
		requestToken: 'test-token',
		Notification: { showTemporary: vi.fn() },
		dialogs: { confirm: vi.fn() },
	}
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) =>
				Object.prototype.hasOwnProperty.call(state, key)
					? state[key]
					: fallback,
		},
	}
}

function buildDom(currentTokenSet) {
	document.body.innerHTML = `
		<div id="nldesign-settings" class="section">
			<select id="nldesign-token-set-select" name="nldesign-token-set">
				${TOKEN_SETS.map((ts) => `<option value="${ts.id}" data-design-system="nldesign"${ts.id === currentTokenSet ? ' selected' : ''}>${ts.name}</option>`).join('')}
			</select>
			<span id="nldesign-design-system-badge"></span>
		</div>
		<div class="nldesign-preview" id="nldesign-preview"></div>
		<div class="nldesign-app-theming" id="nldesign-app-theming">
			<div id="nldesign-app-theming-list"></div>
			<button type="button" id="nldesign-app-theming-save">Save</button>
			<span id="nldesign-app-theming-feedback"></span>
		</div>
	`
}

function installFetchRouter(routes) {
	global.fetch = vi.fn((url) => {
		for (const [match, body] of routes) {
			if (url.indexOf(match) !== -1) {
				return Promise.resolve({
					status: 200,
					json: () => Promise.resolve(body),
				})
			}
		}
		return Promise.resolve({ status: 200, json: () => Promise.resolve({}) })
	})
}

async function flush(rounds = 8) {
	for (let i = 0; i < rounds; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

async function loadAdmin() {
	vi.resetModules()
	await import('../../js/lib/appTheming.js')
	await import('../../js/lib/themingPlan.js')
	await import('../../js/admin.js?t=' + Math.random())
	await flush()
}

const APPS = [
	{ id: 'files', name: 'Files', themed: true },
	{ id: 'deck', name: 'Deck', themed: false },
	{ id: 'mail', name: 'Mail', themed: true },
]

describe('admin.js app theming list', () => {
	beforeEach(() => {
		installGlobals({
			tokenSets: TOKEN_SETS,
			currentTokenSet: 'rijkshuisstijl',
			activePreview: null,
		})
		buildDom('rijkshuisstijl')
		installFetchRouter([
			['/settings/app-theming', { apps: APPS, status: 'ok' }],
			['/settings/tokenset-preview', { error: 'not applicable' }],
		])
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('labels the trigger with the themed count', async () => {
		await loadAdmin()
		const trigger = document.querySelector('.nldesign-app-dropdown-trigger')
		expect(trigger.textContent).toBe('2 of 3 apps themed')

		const deck = document.getElementById('nldesign-app-theming-deck')
		deck.checked = true
		deck.dispatchEvent(new window.Event('change', { bubbles: true }))
		expect(trigger.textContent).toBe('3 of 3 apps themed')
	})

	it('hides the apps a search does not match', async () => {
		await loadAdmin()
		const search = document.querySelector(
			'.nldesign-app-dropdown input[type="search"]',
		)
		search.value = ' MA '
		search.dispatchEvent(new window.Event('input', { bubbles: true }))

		const hidden = Array.from(document.querySelectorAll('.nldesign-app-option'))
			.filter((opt) => opt.hidden)
			.map((opt) => opt.getAttribute('data-app-name'))
		expect(hidden).toEqual(['files', 'deck'])
	})

	it('posts the unchecked apps as the exclusion list', async () => {
		await loadAdmin()
		document.getElementById('nldesign-app-theming-save').click()
		await flush()

		const post = global.fetch.mock.calls.find(
			([url, init]) =>
				url.indexOf('/settings/app-theming') !== -1
				&& init
				&& init.method === 'POST',
		)
		expect(post).toBeTruthy()
		expect(JSON.parse(post[1].body)).toEqual({ disabledApps: ['deck'] })
	})
})

describe('admin.js HTML escaping', () => {
	afterEach(() => {
		document.body.innerHTML = ''
		document.documentElement.removeAttribute('style')
		vi.restoreAllMocks()
	})

	it('renders a script-carrying value as inert text in the theming dialog', async () => {
		const payload = '<script>window.__pwned = true</script>'
		const sets = [
			TOKEN_SETS[0],
			{
				id: 'gemeente-demo',
				name: 'Gemeente Demo',
				theming: { primary_color: payload },
			},
		]
		installGlobals({
			tokenSets: sets,
			currentTokenSet: 'rijkshuisstijl',
			activePreview: null,
		})
		buildDom('rijkshuisstijl')
		installFetchRouter([
			['tokenset-preview', { error: 'not applicable' }],
			['/settings/tokenset', { status: 'ok' }],
			[
				'/settings/theming',
				{
					primary_color: '#aaaaaa',
					background_color: '#bbbbbb',
					has_custom_logo: false,
					has_custom_background: false,
				},
			],
		])
		await loadAdmin()

		const select = document.getElementById('nldesign-token-set-select')
		select.value = 'gemeente-demo'
		select.dispatchEvent(new window.Event('change', { bubbles: true }))
		await flush()

		const overlay = document.getElementById('nldesign-theming-dialog-overlay')
		expect(overlay).not.toBeNull()
		expect(overlay.querySelector('script')).toBeNull()
		expect(overlay.textContent).toContain(payload)
		expect(window.__pwned).toBeUndefined()
	})
})

describe('admin.js theming reset dialog for a stock set', () => {
	const STOCK = { id: 'nextcloud', name: 'Nextcloud', design_system: 'none', theming: { primary_color: '#00679e' } }
	const SYNCED = {
		primary_color: '#aa0000',
		background_color: '#bb0000',
		default_primary_color: '#00679e',
		default_background_color: '#0082c9',
		has_custom_logo: true,
		has_custom_background: true,
		background_url: '/bg.jpg',
		logo_url: '/logo.svg',
	}

	let posts
	let resetAnswer

	/** Answer GET /settings/theming with `current` and its POST with `resetAnswer`. */
	function installRouter(current) {
		posts = []
		global.fetch = vi.fn((url, options = {}) => {
			const method = options.method || 'GET'
			let body = {}
			if (url.indexOf('tokenset-preview') !== -1) {
				body = { error: 'not applicable' }
			} else if (url.indexOf('/settings/tokenset') !== -1) {
				body = { status: 'ok' }
			} else if (url.indexOf('/settings/theming') !== -1) {
				if (method === 'POST') {
					posts.push(options.body)
					if (resetAnswer instanceof Error) {
						return Promise.reject(resetAnswer)
					}
					body = resetAnswer
				} else {
					body = current
				}
			}
			return Promise.resolve({ status: 200, ok: true, json: () => Promise.resolve(body) })
		})
	}

	/** Load the page on rijkshuisstijl and switch to the stock set. */
	async function switchToStock(current) {
		const sets = [TOKEN_SETS[0], STOCK]
		installGlobals({ tokenSets: sets, currentTokenSet: 'rijkshuisstijl', activePreview: null })
		global.OC.imagePath = (app, path) => '/' + app + '/img/' + path
		global.OCP.Toast = { message: vi.fn() }
		window.OCP = global.OCP
		buildDom('rijkshuisstijl')
		const select = document.getElementById('nldesign-token-set-select')
		select.insertAdjacentHTML('beforeend', '<option value="nextcloud" data-design-system="none">Nextcloud</option>')
		installRouter(current)
		await loadAdmin()
		select.value = 'nextcloud'
		select.dispatchEvent(new window.Event('change', { bubbles: true }))
		await flush()
		return document.getElementById('nldesign-theming-dialog-overlay')
	}

	beforeEach(() => {
		resetAnswer = { status: 'ok' }
	})

	afterEach(() => {
		document.body.innerHTML = ''
		delete window.OCP
		vi.restoreAllMocks()
	})

	it('shows every synced setting against the Nextcloud default, with both previews', async () => {
		const overlay = await switchToStock(SYNCED)

		expect(overlay).not.toBeNull()
		expect(overlay.querySelector('h3').textContent).toBe('Reset Nextcloud theming to its defaults?')
		const rows = Array.from(overlay.querySelectorAll('tbody tr')).map((tr) => Array.from(tr.cells).map((td) => td.textContent.trim()))
		expect(rows).toEqual([
			['Primary color', '#aa0000', '#00679e Nextcloud default'],
			['Background color', '#bb0000', '#0082c9 Nextcloud default'],
			['Logo', '(custom logo)', 'Nextcloud logo'],
			['Background image', '(custom)', 'Nextcloud default'],
		])
		const [current, proposed] = overlay.querySelectorAll('.nldesign-dialog-preview-box')
		expect(current.getAttribute('style')).toContain('background-color:#bb0000')
		expect(current.getAttribute('style')).toContain('background-image:url(/bg.jpg)')
		expect(current.querySelector('img').getAttribute('src')).toBe('/logo.svg')
		expect(proposed.getAttribute('style')).toContain('background-color:#0082c9')
		expect(proposed.querySelector('img').getAttribute('src')).toBe('/core/img/logo/logo.svg')
	})

	it('falls back to the default primary for the background, and draws no current logo without one', async () => {
		const overlay = await switchToStock({ primary_color: '#aa0000', has_custom_logo: false, has_custom_background: false })

		const rows = Array.from(overlay.querySelectorAll('tbody tr')).map((tr) => tr.cells[0].textContent)
		expect(rows).toEqual(['Primary color'])
		expect(overlay.querySelector('tbody tr').cells[2].textContent).toContain('#00679e')
		const [current, proposed] = overlay.querySelectorAll('.nldesign-dialog-preview-box')
		expect(current.querySelector('img')).toBeNull()
		expect(current.getAttribute('style')).not.toContain('background-image')
		expect(proposed.getAttribute('style')).toContain('background-color:#00679e')
	})

	it('shows no dialog when nothing was synced', async () => {
		const overlay = await switchToStock({ has_custom_logo: false, has_custom_background: false })
		expect(overlay).toBeNull()
	})

	it('resets with reset=1, closes and says so', async () => {
		const overlay = await switchToStock(SYNCED)
		const confirm = overlay.querySelector('.nldesign-dialog-confirm')
		confirm.click()
		expect(confirm.disabled).toBe(true)
		expect(confirm.textContent).toBe('Resetting…')
		await flush()

		expect(posts).toEqual(['reset=1'])
		expect(document.getElementById('nldesign-theming-dialog-overlay')).toBeNull()
		expect(global.OCP.Toast.message).toHaveBeenCalledWith('Nextcloud theming reset to its defaults.')
	})

	it('closes and says so when the reset fails', async () => {
		resetAnswer = { status: 'error', error: 'nope' }
		const error = vi.spyOn(console, 'error').mockImplementation(() => {})
		const overlay = await switchToStock(SYNCED)
		overlay.querySelector('.nldesign-dialog-confirm').click()
		await flush()

		expect(document.getElementById('nldesign-theming-dialog-overlay')).toBeNull()
		expect(error).toHaveBeenCalledWith('Error resetting theming:', expect.any(Error))
		expect(global.OCP.Toast.message).toHaveBeenCalledWith('Failed to reset Nextcloud theming.')
	})

	it('closes without a request on Cancel and on a click on the backdrop, but not inside the dialog', async () => {
		let overlay = await switchToStock(SYNCED)
		overlay.querySelector('.nldesign-dialog-cancel').click()
		expect(document.getElementById('nldesign-theming-dialog-overlay')).toBeNull()

		overlay = await switchToStock(SYNCED)
		overlay.querySelector('.nldesign-dialog').click()
		expect(document.getElementById('nldesign-theming-dialog-overlay')).not.toBeNull()
		overlay.click()
		expect(document.getElementById('nldesign-theming-dialog-overlay')).toBeNull()
		expect(posts).toEqual([])
	})
})
