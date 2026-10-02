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
