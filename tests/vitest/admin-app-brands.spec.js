/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin-app-brands.js: the Brand per app block lists each
 * branded app with its set and contrast, saves a new brand, and shows why a
 * brand is refused.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/per-app-theming/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

let state
let saveAnswer

async function flush() {
	for (let i = 0; i < 6; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

async function loadScript() {
	vi.resetModules()
	await import('../../js/admin-app-brands.js?t=' + Math.random())
	await flush()
}

describe('Brand per app block', () => {
	beforeEach(() => {
		global.t = (app, text, params) =>
			params === undefined
				? text
				: Object.keys(params).reduce(
						(acc, key) => acc.replace('{' + key + '}', params[key]),
						text,
					)
		global.OC = { generateUrl: (url) => url, requestToken: 'token' }
		state = {
			brands: {
				collectives: {
					tokenSet: 'kennisbank',
					logoLarge: null,
					logoSmall: null,
					stale: false,
				},
			},
			apps: [
				{ id: 'collectives', name: 'Collectives', themed: true },
				{ id: 'files', name: 'Files', themed: true },
			],
			tokenSets: [
				{ id: 'kennisbank', name: 'Kennisbank', wcagLevel: 'AA' },
				{ id: 'amsterdam', name: 'Amsterdam', wcagLevel: null },
			],
		}
		saveAnswer = { ok: true, body: {} }
		global.fetch = vi.fn((url, options) => {
			if (options && options.method === 'POST') {
				return Promise.resolve({
					ok: saveAnswer.ok,
					json: () => Promise.resolve(saveAnswer.body),
				})
			}
			return Promise.resolve({ ok: true, json: () => Promise.resolve(state) })
		})
		document.body.innerHTML = `
			<div id="nldesign-app-brands-list"></div>
			<select id="nldesign-app-brands-app"></select>
			<select id="nldesign-app-brands-set"></select>
			<button id="nldesign-app-brands-add"></button>
			<span id="nldesign-app-brands-feedback"></span>`
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('lists a branded app with its set, contrast and labelled logo inputs', async () => {
		await loadScript()
		const row = document.querySelector('[data-app="collectives"]')
		expect(row.querySelector('strong').textContent).toBe('Collectives')
		expect(row.querySelector('.nldesign-app-brand-set').textContent).toBe(
			'Kennisbank',
		)
		expect(row.querySelector('.nldesign-app-brand-contrast').textContent).toBe(
			'WCAG AA',
		)
		const input = row.querySelector('input[type="file"]')
		expect(
			document.querySelector('label[for="' + input.id + '"]').textContent,
		).toBe('Large logo')
	})

	it('saves a brand for the chosen app and set', async () => {
		await loadScript()
		document.getElementById('nldesign-app-brands-app').value = 'files'
		document.getElementById('nldesign-app-brands-set').value = 'amsterdam'
		document.getElementById('nldesign-app-brands-add').click()
		await flush()

		const post = global.fetch.mock.calls.find(
			(c) => c[1] && c[1].method === 'POST',
		)
		expect(post[0]).toBe('/apps/thematiq/settings/app-brands/files')
		expect(JSON.parse(post[1].body)).toEqual({ tokenSet: 'amsterdam' })
	})

	it('shows why a brand is refused', async () => {
		saveAnswer = {
			ok: false,
			body: {
				error: 'The settings pages always follow the house style. This app cannot get its own brand.',
			},
		}
		await loadScript()
		document.getElementById('nldesign-app-brands-add').click()
		await flush()
		expect(
			document.getElementById('nldesign-app-brands-feedback').textContent,
		).toContain('settings pages always follow the house style')
	})

	it('names an app and a set it does not know by their id, with the contrast below AA', async () => {
		state.brands = { deck: { tokenSet: 'gone', stale: true } }
		await loadScript()
		const row = document.querySelector('[data-app="deck"]')
		expect(row.querySelector('strong').textContent).toBe('deck')
		expect(row.querySelector('.nldesign-app-brand-set').textContent).toBe('gone')
		expect(row.querySelector('.nldesign-app-brand-contrast').textContent).toBe(
			'Below WCAG AA',
		)
		expect(row.querySelector('.nldesign-app-brand-stale').textContent).toContain(
			'Not applied',
		)
	})

	it('says no app has its own brand when there is none', async () => {
		state.brands = {}
		await loadScript()
		expect(document.getElementById('nldesign-app-brands-list').textContent).toBe(
			'No app has its own brand.',
		)
	})

	it('uploads a logo for a size, shows the file name, and says it is saved or why not', async () => {
		await loadScript()
		const input = document.getElementById(
			'nldesign-app-brands-collectives-small',
		)
		const pick = document.getElementById(
			'nldesign-app-brands-collectives-small-btn',
		)
		const click = vi.spyOn(input, 'click').mockImplementation(() => {})
		pick.click()
		expect(click).toHaveBeenCalled()
		expect(pick.getAttribute('aria-labelledby')).toBe(
			'nldesign-app-brands-collectives-small-label nldesign-app-brands-collectives-small-btn',
		)

		Object.defineProperty(input, 'files', { value: [], configurable: true })
		input.dispatchEvent(new Event('change'))
		await flush()
		expect(
			input.parentNode.querySelector('.nldesign-file-pick__name').textContent,
		).toBe('No file chosen')
		expect(
			global.fetch.mock.calls.some(([url]) => url.endsWith('/logo/small')),
		).toBe(false)

		Object.defineProperty(input, 'files', {
			value: [new File(['x'], 'logo.svg')],
			configurable: true,
		})
		input.dispatchEvent(new Event('change'))
		expect(
			input.parentNode.querySelector('.nldesign-file-pick__name').textContent,
		).toBe('logo.svg')
		await flush()
		const upload = global.fetch.mock.calls.find(
			([url]) =>
				url === '/apps/thematiq/settings/app-brands/collectives/logo/small',
		)
		expect(upload[1].method).toBe('POST')
		expect(upload[1].body.get('file')).toBeInstanceOf(File)
		expect(upload[1].headers).toEqual({ requesttoken: 'token' })
		expect(
			document.getElementById('nldesign-app-brands-feedback').textContent,
		).toBe('Logo saved.')

		saveAnswer = { ok: false, body: { error: 'The logo is larger than 1 MB.' } }
		const large = document.getElementById(
			'nldesign-app-brands-collectives-large',
		)
		Object.defineProperty(large, 'files', {
			value: [new File(['x'], 'big.png')],
			configurable: true,
		})
		large.dispatchEvent(new Event('change'))
		await flush()
		expect(
			document.getElementById('nldesign-app-brands-feedback').textContent,
		).toBe('The logo is larger than 1 MB.')
	})

	it('removes a brand and loads the list again', async () => {
		await loadScript()
		const remove = Array.from(
			document.querySelectorAll('[data-app="collectives"] button'),
		).find((b) => b.textContent === 'Remove brand')
		expect(remove.getAttribute('aria-label')).toBe(
			'Remove the brand of Collectives',
		)
		state.brands = {}
		remove.click()
		await flush()
		expect(
			global.fetch.mock.calls.find(
				([url, o]) => o && o.method === 'DELETE',
			)[0],
		).toBe('/apps/thematiq/settings/app-brands/collectives')
		expect(document.getElementById('nldesign-app-brands-list').textContent).toBe(
			'No app has its own brand.',
		)
	})

	it('logs a list that cannot be loaded, and does nothing without its block or OC', async () => {
		global.fetch = vi.fn(() => Promise.reject(new Error('offline')))
		const error = vi.spyOn(console, 'error').mockImplementation(() => {})
		await loadScript()
		expect(error).toHaveBeenCalledWith(
			'Error loading the brands per app:',
			expect.any(Error),
		)

		global.fetch = vi.fn()
		document.body.innerHTML = '<div id="nldesign-app-brands-list"></div>'
		await loadScript()
		expect(global.fetch).not.toHaveBeenCalled()
	})

	it('waits for DOMContentLoaded while the page is loading', async () => {
		const readyState = vi
			.spyOn(document, 'readyState', 'get')
			.mockReturnValue('loading')
		await loadScript()
		expect(global.fetch).not.toHaveBeenCalled()
		readyState.mockReturnValue('complete')
		document.dispatchEvent(new Event('DOMContentLoaded'))
		await flush()
		expect(document.querySelector('[data-app="collectives"]')).not.toBeNull()
	})
})
