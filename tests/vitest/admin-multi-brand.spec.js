/**
 * @vitest-environment jsdom
 *
 * js/admin.js, one source with several brands: the brand picker after the first upload,
 * the confirmed upload with the chosen brands, cancel, and the source header with
 * "Update source" and its report. Harness copied from admin-dtcg-diagnostics.spec.js.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/authoring-multi-brand-token-source/tasks.md#task-4.1
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/** Build the minimal settings-page DOM the script expects for the custom-token-set panel. */
/**
 * Stand in for Nextcloud's initial-state channel.
 *
 * js/admin.js reads its server data through `OCP.InitialState.loadState()`
 * (ADR-004), so a fixture has to hand it over the same way the settings page
 * does. The `data-*` attributes these fixtures used to carry no longer reach
 * the script, and the template no longer emits them.
 *
 * @param {object} state Initial-state keys as provided by lib/Settings/Admin.php.
 */
function installInitialState(state) {
	global.OCP = Object.assign(global.OCP || {}, {
		InitialState: {
			loadState: (app, key, fallback) =>
				Object.prototype.hasOwnProperty.call(state, key)
					? state[key]
					: fallback,
		},
	})
}

function buildDom() {
	installInitialState({
		tokenSets: [],
		currentTokenSet: '',
		activePreview: null,
		iconPackSource: '',
	})
	document.body.innerHTML = `
		<div id="nldesign-settings" class="section">
		</div>
		<div class="nldesign-upload-form">
			<input type="text" id="nldesign-upload-name" value="">
			<input type="file" id="nldesign-upload-input" accept=".css,.json,.tokens.json" style="display:none">
			<button type="button" id="nldesign-upload-btn" class="button">Upload</button>
		</div>
		<div id="nldesign-upload-result" class="nldesign-import-result" role="status" aria-live="polite" style="display:none"></div>
		<div id="nldesign-custom-set-list" class="nldesign-custom-set-list" role="group"></div>
	`
}

/** Minimal OC / t / n globals admin.js reads at load and call time. */
function installGlobals() {
	global.t = (app, text, params) => {
		if (params === undefined) {
			return text
		}
		return Object.keys(params).reduce(
			(acc, key) => acc.replace('{' + key + '}', params[key]),
			text,
		)
	}
	global.n = (app, singular, plural, count, vars) =>
		Object.keys(vars || {}).reduce(
			(acc, key) => acc.replace('{' + key + '}', vars[key]),
			count === 1 ? singular : plural,
		)
	global.OC = {
		generateUrl: (url) => url,
		linkTo: (app, path) => path,
		requestToken: 'test-token',
		Notification: { showTemporary: vi.fn() },
		dialogs: { confirm: vi.fn() },
	}
}

/** Route a fetch() call to a canned response based on a URL substring / method. */
function installFetchRouter(routes) {
	global.fetch = vi.fn((url, options) => {
		const method = (options && options.method) || 'GET'
		for (const [match, matchMethod, body, status] of routes) {
			if (
				url.indexOf(match) !== -1
				&& (matchMethod === undefined || matchMethod === method)
			) {
				return Promise.resolve({
					status: status || 200,
					json: () => Promise.resolve(body),
				})
			}
		}
		return Promise.resolve({ status: 200, json: () => Promise.resolve({}) })
	})
}

/** Flush pending promise chains (see admin-a11y.spec.js for rationale). */
async function flush(rounds = 8) {
	for (let i = 0; i < rounds; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

/** (Re-)import js/admin.js as a fresh module instance, running its IIFE against the current DOM. */
async function loadAdminScript() {
	vi.resetModules()
	await import('../../js/admin.js?t=' + Math.random())
	await flush()
}

/** Select the upload file input and dispatch a `change` event carrying one File. */
async function selectUploadFile(filename, content) {
	const input = document.getElementById('nldesign-upload-input')
	const file = new File([content], filename, { type: 'application/octet-stream' })
	Object.defineProperty(input, 'files', { value: [file], configurable: true })
	input.dispatchEvent(new window.Event('change', { bubbles: true }))
	await flush()
}

/** Route fetch() by a function, recording every call. */
function routeBy(handler) {
	global.fetch = vi.fn((url, options) => {
		const body = handler(
			url,
			(options && options.method) || 'GET',
			options && options.body,
		)
		return Promise.resolve({
			ok: (body.status || 200) < 400,
			status: body.status || 200,
			json: () => Promise.resolve(body.data || {}),
		})
	})
}

const BRANDS = [
	{ key: 'noord', name: 'Noord', tokenCount: 3, group: 'brand' },
	{ key: 'zuid', name: 'Zuid', tokenCount: 3, group: 'brand' },
	{ key: 'oost', name: 'Oost', tokenCount: 3, group: 'brand' },
]

describe('admin.js multi-brand sources', () => {
	beforeEach(() => {
		installGlobals()
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('lists the brands, ticked, and imports the chosen ones with a second upload', async () => {
		buildDom()
		const uploads = []
		routeBy((url, method, body) => {
			if (url.indexOf('/settings/tokensets/upload') !== -1) {
				const brands = body.getAll('brands[]')
				uploads.push(brands)
				return brands.length === 0
					? { data: { multiBrand: true, stored: false, brands: BRANDS } }
					: {
							data: {
								multiBrand: true,
								stored: true,
								sourceId: 'voorbeeld',
								sets: brands.map((b) => ({ brand: b })),
							},
						}
			}
			return { data: {} }
		})
		await loadAdminScript()
		document.getElementById('nldesign-upload-name').value = 'Gemeente Voorbeeld'
		await selectUploadFile('three-themes.tokens.json', '{}')

		const picker = document.querySelector('.nldesign-brand-picker')
		const legend = picker.querySelector('legend')
		expect(legend.textContent).toBe(
			'This file holds 3 brands. Choose the ones to import.',
		)
		expect(document.activeElement).toBe(legend)
		const boxes = [...picker.querySelectorAll('input[type="checkbox"]')]
		expect(boxes.map((b) => b.checked)).toEqual([true, true, true])
		boxes.forEach((box) => {
			expect(
				picker.querySelector('label[for="' + box.id + '"]'),
			).not.toBeNull()
		})
		expect(picker.textContent).toContain('Noord (3 tokens) · brand')

		boxes[2].checked = false
		picker.querySelector('.nldesign-brand-import').click()
		await flush()

		expect(uploads).toEqual([[], ['noord', 'zuid']])
		expect(document.getElementById('nldesign-upload-result').textContent).toBe(
			'2 brands imported as token sets.',
		)
	})

	it('imports nothing on cancel', async () => {
		buildDom()
		let calls = 0
		routeBy((url) => {
			if (url.indexOf('/settings/tokensets/upload') !== -1) {
				calls++
				return { data: { multiBrand: true, stored: false, brands: BRANDS } }
			}
			return { data: {} }
		})
		await loadAdminScript()
		document.getElementById('nldesign-upload-name').value = 'Voorbeeld'
		await selectUploadFile('a.css', '.a-theme{}')
		document.querySelector('.nldesign-brand-cancel').click()
		await flush()

		expect(calls).toBe(1)
		expect(document.getElementById('nldesign-upload-result').textContent).toBe(
			'Nothing was imported.',
		)
	})

	it('groups brands under their source and reports an update', async () => {
		buildDom()
		const sets = [
			{ id: 'custom-los', name: 'Los' },
			{
				id: 'custom-voorbeeld-noord',
				name: 'Voorbeeld: Noord',
				source: { id: 'voorbeeld', brand: 'noord' },
			},
			{
				id: 'custom-voorbeeld-zuid',
				name: 'Voorbeeld: Zuid',
				source: { id: 'voorbeeld', brand: 'zuid' },
			},
		]
		routeBy((url, method) => {
			if (
				url.indexOf('/settings/tokensets/sources/voorbeeld') !== -1
				&& method === 'POST'
			) {
				return {
					data: {
						status: 'ok',
						updated: ['noord'],
						missing: ['zuid'],
						new: ['west'],
					},
				}
			}
			if (url.indexOf('/settings/tokensets/custom') !== -1) {
				return { data: { sets } }
			}
			return { data: {} }
		})
		await loadAdminScript()
		await flush()

		const headers = document.querySelectorAll('.nldesign-custom-source')
		expect(headers).toHaveLength(1)
		expect(headers[0].querySelector('strong').textContent).toBe('Voorbeeld')
		expect(
			document.querySelectorAll('.nldesign-custom-set-row--brand'),
		).toHaveLength(2)

		const input = headers[0].querySelector('input[type="file"]')
		Object.defineProperty(input, 'files', {
			value: [new File(['x'], 'v2.css')],
			configurable: true,
		})
		input.dispatchEvent(new window.Event('change'))
		await flush()

		expect(document.getElementById('nldesign-upload-result').textContent).toBe(
			'Updated: noord. Missing from the file, kept as they were: zuid. New in the file, not imported: west.',
		)
	})
})
