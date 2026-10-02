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
		expect(row.textContent).toContain('Collectives: Kennisbank')
		expect(row.textContent).toContain('Contrast: WCAG AA')
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
})
