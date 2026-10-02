/**
 * @vitest-environment jsdom
 *
 * The token editor at scale: the count, the search, the component groups that
 * build their rows when opened, the Advanced group and its warning, Nextcloud's
 * own value and the note beside a row, and the dark field of settable and
 * internal colours.
 *
 * Black-box, real-DOM-event style, like tests/vitest/admin-token-editor.spec.js
 * (admin.js is a vanilla-JS IIFE with no exports).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/token-editor-ui/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const REGISTRY = {
	'--color-primary': {
		tab: 'login',
		type: 'color',
		label: 'Primary',
		group: 'brand',
	},
	'--color-mark': {
		tab: 'content',
		type: 'color',
		label: 'Search highlight color',
		group: 'brand',
		settable: true,
		token: '--nldesign-nc-color-mark',
		note: 'Pairs with the text colour',
		stock: { light: '#fff0c7', dark: '#4d3800' },
	},
	'--header-height': {
		tab: 'content',
		type: 'text',
		label: 'Header height',
		group: 'brand',
		settable: true,
		advanced: true,
		stock: { light: '50px', dark: '50px' },
	},
	'--nldesign-font-family': { tab: 'typography', type: 'text', label: 'Font' },
}

const INTERNAL = {
	'--nldesign-nc-dp-hover-color': {
		variable: '--dp-hover-color',
		group: 'date-picker',
		type: 'color',
		stock: '#484848',
	},
	'--nldesign-nc-dp-font-size': {
		variable: '--dp-font-size',
		group: 'date-picker',
		type: 'text',
		stock: '1rem',
	},
	'--nldesign-nc-plyr-font-size-base': {
		variable: '--plyr-font-size-base',
		group: 'media-player',
		type: 'text',
	},
}

const DUTCH = { 'Date picker': 'Datumkiezer' }

let requests = []

function installGlobals() {
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) =>
				({ tokenSets: [], currentTokenSet: 'rijkshuisstijl' })[key]
				?? fallback,
		},
	}
	global.t = (app, text, params) => {
		const base = DUTCH[text] ?? text
		return params === undefined
			? base
			: Object.keys(params).reduce(
					(acc, key) => acc.replace('{' + key + '}', params[key]),
					base,
				)
	}
	global.n = (app, singular, plural, count) =>
		(count === 1 ? singular : plural).replace('%n', String(count))
	global.OC = {
		generateUrl: (url) => url,
		linkTo: (app, path) => path,
		filePath: (app, type, file) => '/apps/' + app + '/' + type + '/' + file,
		requestToken: 'test-token',
		Notification: { showTemporary: vi.fn() },
		dialogs: { confirm: vi.fn() },
	}
	global.fetch = vi.fn((url, options = {}) => {
		requests.push({ url, method: options.method || 'GET', body: options.body })
		const body =
			String(url).indexOf('/settings/overrides') !== -1
			&& (options.method || 'GET') === 'GET'
				? {
						overrides: {},
						registry: REGISTRY,
						internal: INTERNAL,
						count: 7,
						tabs: {
							login: 'Login',
							content: 'Content',
							typography: 'Type',
						},
					}
				: { status: 'ok' }
		return Promise.resolve({
			ok: true,
			status: 200,
			json: () => Promise.resolve(body),
		})
	})
}

async function flush(rounds = 10) {
	for (let i = 0; i < rounds; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

async function mount() {
	installGlobals()
	document.body.innerHTML =
		'<div id="nldesign-settings" class="section"></div><div id="nldesign-token-editor"></div>'
	vi.resetModules()
	await import('../../js/lib/tokenTransforms.js?t=' + Math.random())
	await import('../../js/admin.js?t=' + Math.random())
	await flush()
}

const group = (id) =>
	document.querySelector('.nldesign-token-group[data-group="' + id + '"]')
const toggle = (id) => group(id).querySelector('.nldesign-token-group-toggle')
const rowOf = (name) => document.querySelector('[data-token-row="' + name + '"]')

async function search(text) {
	const field = document.getElementById('nldesign-token-search')
	field.value = text
	field.dispatchEvent(new window.Event('input'))
	await flush(2)
}

beforeEach(async () => {
	requests = []
	await mount()
})

afterEach(() => {
	document.body.innerHTML = ''
})

describe('the token editor at scale', () => {
	it('states the count the server reports', () => {
		expect(document.getElementById('nldesign-token-count').textContent).toBe(
			'7 editable tokens',
		)
	})

	it('builds no row of a collapsed group on load', () => {
		expect(rowOf('--color-primary')).not.toBeNull()
		expect(rowOf('--nldesign-nc-dp-hover-color')).toBeNull()
		expect(
			group('date-picker').querySelector('.nldesign-token-group-panel').hidden,
		).toBe(true)
		expect(toggle('date-picker').getAttribute('aria-expanded')).toBe('false')
	})

	it("lists the date picker token in its own group, by its Nextcloud name, with Nextcloud's value", () => {
		toggle('date-picker').click()

		const row = rowOf('--nldesign-nc-dp-hover-color')
		expect(row).not.toBeNull()
		expect(row.closest('.nldesign-token-group').dataset.group).toBe(
			'date-picker',
		)
		expect(row.querySelector('.nldesign-token-label').textContent).toBe(
			'--dp-hover-color',
		)
		const stock = row.querySelector('.nldesign-token-stock')
		expect(stock.textContent).toBe('Nextcloud: #484848')
		expect(
			row
				.querySelector('.nldesign-color-text')
				.getAttribute('aria-describedby'),
		).toBe(stock.id)
	})

	it('keeps an edit across closing and opening its group', async () => {
		toggle('date-picker').click()
		const field = rowOf('--nldesign-nc-dp-font-size').querySelector(
			'.nldesign-text-input',
		)
		field.value = '1.25rem'
		field.dispatchEvent(new window.Event('input'))
		toggle('date-picker').click()
		toggle('date-picker').click()

		expect(
			rowOf('--nldesign-nc-dp-font-size').querySelector('.nldesign-text-input')
				.value,
		).toBe('1.25rem')
	})

	it('puts the layout variables in a collapsed Advanced group that warns before the first field', () => {
		expect(rowOf('--header-height')).toBeNull()
		expect(toggle('advanced').getAttribute('aria-expanded')).toBe('false')

		toggle('advanced').click()

		const panel = group('advanced').querySelector('.nldesign-token-group-panel')
		expect(
			panel.firstElementChild.classList.contains(
				'nldesign-token-advanced-warning',
			),
		).toBe(true)
		expect(panel.firstElementChild.textContent).toContain('can break the layout')
		expect(rowOf('--header-height')).not.toBeNull()
	})

	it('finds a token by its CSS name and hides the groups without a match', async () => {
		await search('dp-hover')

		expect(rowOf('--nldesign-nc-dp-hover-color').hidden).toBe(false)
		expect(rowOf('--nldesign-nc-dp-font-size').hidden).toBe(true)
		expect(group('media-player').hidden).toBe(true)
		expect(rowOf('--color-primary').hidden).toBe(true)
	})

	it('finds every token of a group by its translated heading', async () => {
		await search('datumkiezer')

		expect(rowOf('--nldesign-nc-dp-hover-color').hidden).toBe(false)
		expect(rowOf('--nldesign-nc-dp-font-size').hidden).toBe(false)
		expect(group('media-player').hidden).toBe(true)
	})

	it('puts back what was open when the search is cleared', async () => {
		await search('dp-hover')
		await search('')

		expect(toggle('date-picker').getAttribute('aria-expanded')).toBe('false')
		expect(group('media-player').hidden).toBe(false)
		expect(rowOf('--color-primary').hidden).toBe(false)
	})

	it('gives the search field a label', () => {
		const label = document.querySelector('label[for="nldesign-token-search"]')
		expect(label.textContent).toBe('Search tokens')
	})

	it("shows the note and Nextcloud's dark value on a settable colour", () => {
		const row = rowOf('--color-mark')
		expect(row.querySelector('.nldesign-token-note').textContent).toBe(
			'Pairs with the text colour',
		)
		expect(
			row.querySelector('.nldesign-dark-text').getAttribute('placeholder'),
		).toBe('Nextcloud: #4d3800')
	})

	it('offers a dark value on an internal colour, not on an internal size', () => {
		toggle('date-picker').click()

		expect(
			rowOf('--nldesign-nc-dp-hover-color').querySelector(
				'.nldesign-dark-text',
			),
		).not.toBeNull()
		expect(
			rowOf('--nldesign-nc-dp-font-size').querySelector('.nldesign-dark-text'),
		).toBeNull()
	})

	it('saves an internal token and its dark value', async () => {
		toggle('date-picker').click()
		const row = rowOf('--nldesign-nc-dp-hover-color')
		const text = row.querySelector('.nldesign-color-text')
		text.value = '#e8eef5'
		text.dispatchEvent(new window.Event('input'))
		const dark = row.querySelector('.nldesign-dark-text')
		dark.value = '#333333'
		dark.dispatchEvent(new window.Event('input'))
		document.getElementById('nldesign-save-btn').click()
		await flush()
		// The save asks first while a set is active; confirm it when it does.
		const confirm = document.querySelector('.nldesign-dialog-confirm')
		if (confirm !== null) {
			confirm.click()
			await flush()
		}

		const post = requests.find(
			(r) =>
				r.method === 'POST' && r.url.indexOf('/settings/overrides') !== -1,
		)
		expect(post).toBeDefined()
		const body = JSON.parse(post.body)
		expect(body.overrides['--nldesign-nc-dp-hover-color']).toBe('#e8eef5')
		expect(body.darkOverrides['--nldesign-nc-dp-hover-color']).toBe('#333333')
	})
})
