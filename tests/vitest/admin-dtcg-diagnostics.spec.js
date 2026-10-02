/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin.js — DTCG import diagnostics rendering and
 * recorded package version display (custom-token-sets spec, DTCG ingestion
 * hardening). Follows the same black-box, real-DOM-fixture + fetch-router
 * approach as tests/vitest/admin-a11y.spec.js: build the minimal DOM the
 * script expects, stub OC/t/fetch, (re-)import admin.js fresh, then drive it
 * exactly the way a user would.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/custom-token-sets/spec.md
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
	global.n = (app, singular, plural, count) => (count === 1 ? singular : plural)
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

describe('admin.js DTCG import diagnostics', () => {
	beforeEach(() => {
		installGlobals()
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('renders diagnostics grouped by reason and the deprecation warning after a DTCG upload', async () => {
		buildDom()
		installFetchRouter([
			[
				'/settings/tokensets/upload',
				'POST',
				{
					id: 'custom-eigen-huisstijl',
					imported: 1,
					skipped: [
						{ path: 'shadow.elevation-1', reason: 'unmapped-path' },
						{ path: 'color.other', reason: 'unmapped-path' },
					],
					errors: [
						{
							path: 'color.accent',
							reason: 'unsupported-color-space',
							detail: 'display-p3',
						},
					],
					importWarnings: [
						{
							path: 'color.primary',
							message: 'Use color.brand.primary instead',
						},
					],
					warnings: [],
					version: '2.3.1',
				},
			],
		])

		await loadAdminScript()
		document.getElementById('nldesign-upload-name').value = 'Eigen huisstijl'

		await selectUploadFile(
			'theme.tokens.json',
			'{"color":{"primary":{"$type":"color","$value":"#154273"}}}',
		)

		const resultEl = document.getElementById('nldesign-upload-result')
		expect(resultEl.style.display).toBe('block')
		expect(resultEl.textContent).toContain('2.3.1')

		const diagnosticsItems = resultEl.querySelectorAll(
			'.nldesign-diagnostics-list li',
		)
		expect(diagnosticsItems.length).toBe(2)
		const diagnosticsText = Array.from(diagnosticsItems)
			.map((li) => li.textContent)
			.join(' | ')
		expect(diagnosticsText).toContain('shadow.elevation-1')
		expect(diagnosticsText).toContain('color.other')
		expect(diagnosticsText).toContain('color.accent')

		const warningItems = resultEl.querySelectorAll(
			'.nldesign-deprecation-list li',
		)
		expect(warningItems.length).toBe(1)
		expect(warningItems[0].textContent).toContain('color.primary')
		expect(warningItems[0].textContent).toContain(
			'Use color.brand.primary instead',
		)
	})

	it('offers to record notices that name a variable, and records them only on the click', async () => {
		buildDom()
		installFetchRouter([
			[
				'/settings/tokensets/upload',
				'POST',
				{
					id: 'custom-eigen-huisstijl',
					imported: 1,
					importWarnings: [
						{
							path: 'color.primary',
							message: 'Use color.brand.primary instead',
							token: '--nldesign-color-primary',
						},
						{ path: 'typography.body', message: null },
					],
					warnings: [],
				},
			],
			[
				'/settings/tokens/deprecations/adopt',
				'POST',
				{ status: 'ok', recorded: ['--nldesign-color-primary'] },
			],
		])
		const changed = vi.fn()
		document.addEventListener('thematiq:deprecations-changed', changed)

		await loadAdminScript()
		document.getElementById('nldesign-upload-name').value = 'Eigen huisstijl'
		await selectUploadFile('theme.tokens.json', '{}')

		const adoptCalls = () =>
			global.fetch.mock.calls.filter(
				(c) => c[0].indexOf('/settings/tokens/deprecations/adopt') !== -1,
			)
		const button = document.querySelector('.nldesign-adopt-notices')
		expect(button.textContent).toBe('Record as deprecations')
		expect(adoptCalls()).toHaveLength(0)

		button.dispatchEvent(new window.MouseEvent('click', { bubbles: true }))
		await flush()

		expect(adoptCalls()).toHaveLength(1)
		expect(JSON.parse(adoptCalls()[0][1].body).notices).toEqual([
			{
				path: 'color.primary',
				message: 'Use color.brand.primary instead',
				token: '--nldesign-color-primary',
			},
		])
		expect(changed).toHaveBeenCalled()
		document.removeEventListener('thematiq:deprecations-changed', changed)
	})

	it('renders no diagnostics markup when skipped/errors/importWarnings are absent (CSS upload, backward compat)', async () => {
		buildDom()
		installFetchRouter([
			[
				'/settings/tokensets/upload',
				'POST',
				{
					id: 'custom-css-set',
					imported: 1,
					skipped: [],
					warnings: [],
				},
			],
		])

		await loadAdminScript()
		document.getElementById('nldesign-upload-name').value = 'Css Set'

		await selectUploadFile(
			'theme.css',
			':root { --nldesign-color-primary: #007bc7; }',
		)

		const resultEl = document.getElementById('nldesign-upload-result')
		expect(resultEl.querySelector('.nldesign-diagnostics-list')).toBeNull()
		expect(resultEl.querySelector('.nldesign-deprecation-list')).toBeNull()
	})

	it('renders the recorded package version in the custom-set list', async () => {
		buildDom()
		installFetchRouter([
			[
				'/settings/tokensets/custom',
				'GET',
				{
					sets: [
						{
							id: 'custom-eigen-huisstijl',
							name: 'Eigen huisstijl',
							version: '2.3.1',
							warnings: [],
						},
						{
							id: 'custom-no-version',
							name: 'No Version',
							warnings: [],
						},
					],
				},
			],
		])

		await loadAdminScript()

		const rows = document.querySelectorAll('.nldesign-custom-set-row')
		expect(rows.length).toBe(2)

		const versioned = Array.from(rows).find((row) =>
			row.textContent.includes('Eigen huisstijl'),
		)
		expect(
			versioned.querySelector('.nldesign-custom-set-version').textContent,
		).toContain('2.3.1')

		const versionless = Array.from(rows).find((row) =>
			row.textContent.includes('No Version'),
		)
		expect(versionless.querySelector('.nldesign-custom-set-version')).toBeNull()
	})
})
