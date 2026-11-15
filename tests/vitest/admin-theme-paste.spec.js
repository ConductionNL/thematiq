/**
 * @vitest-environment jsdom
 *
 * js/admin.js, a theme pasted instead of uploaded (nlds-theme-converter 6.1, 6.3, 6.4): the
 * pasted text goes up as `content`, the conversion report comes back grouped by reason with
 * the table's own sentence and the counts, and the new set reaches the dropdown without a
 * reload. Harness copied from admin-multi-brand.spec.js.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/nlds-theme-converter/specs/custom-token-sets/spec.md#requirement-pasted-content-is-a-first-class-input
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
			<textarea id="nldesign-upload-content"></textarea>
			<button type="button" id="nldesign-convert-btn" class="button">Convert</button>
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

const THEME =
	'.openwoo-theme { --utrecht-button-primary-action-background-color: #23845c; --utrecht-page-max-inline-size: 1200px; }'

const CONVERTED = {
	id: 'custom-openwoo',
	imported: 30,
	skipped: [],
	inputKind: 'A',
	counts: { applied: 1, adapted: 28, kept: 0, skipped: 1 },
	report: [
		{
			source: '--utrecht-button-primary-action-background-color',
			target: '--nldesign-color-primary',
			action: 'applied',
			reason: null,
			value: '#23845c',
		},
		{
			source: '--utrecht-page-max-inline-size',
			target: '',
			action: 'skipped',
			reason: 'layout-fixed-by-nextcloud',
			value: '1200px',
		},
	],
	reasons: {
		'layout-fixed-by-nextcloud': 'Page width and paddings were not applied.',
	},
}

/**
 * Fill the paste box and the name, then press Convert.
 *
 * @param {string} name The set name.
 * @param {string} content The pasted text.
 */
async function paste(name, content) {
	document.getElementById('nldesign-upload-name').value = name
	document.getElementById('nldesign-upload-content').value = content
	document.getElementById('nldesign-convert-btn').click()
	await flush()
}

describe('admin.js pasted theme', () => {
	beforeEach(() => {
		installGlobals()
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('sends the pasted text as content, without a file', async () => {
		buildDom()
		const uploads = []
		routeBy((url, method, body) => {
			if (url.endsWith('/tokensets/upload')) {
				uploads.push(body)
				return { data: CONVERTED }
			}
			return { data: { tokenSets: [], sets: [] } }
		})
		await loadAdminScript()
		await paste('OpenWOO', THEME)

		expect(uploads).toHaveLength(1)
		expect(uploads[0].get('name')).toBe('OpenWOO')
		expect(uploads[0].get('content')).toBe(THEME)
		expect(uploads[0].get('file')).toBeNull()
	})

	it('asks for a name and for content before sending anything', async () => {
		buildDom()
		const uploads = []
		routeBy((url, method, body) => {
			if (url.endsWith('/tokensets/upload')) {
				uploads.push(body)
			}
			return { data: { tokenSets: [], sets: [] } }
		})
		await loadAdminScript()
		await paste('', THEME)
		await paste('OpenWOO', '   ')

		expect(uploads).toHaveLength(0)
		expect(global.OC.Notification.showTemporary).toHaveBeenCalledTimes(2)
	})

	it("shows the report grouped by reason, in the table's words, with the counts", async () => {
		buildDom()
		routeBy((url) =>
			url.endsWith('/tokensets/upload')
				? { data: CONVERTED }
				: { data: { tokenSets: [], sets: [] } },
		)
		await loadAdminScript()
		await paste('OpenWOO', THEME)

		const result = document.getElementById('nldesign-upload-result')
		const groups = result.querySelectorAll('.nldesign-conversion-report li')
		expect(groups).toHaveLength(1)
		expect(groups[0].textContent).toContain(
			'Page width and paddings were not applied.',
		)
		expect(groups[0].textContent).toContain('--utrecht-page-max-inline-size')
		expect(result.textContent).toContain(
			'1 applied, 28 adapted, 0 kept, 1 skipped',
		)
	})

	it('puts the new set in the dropdown from the catalogue, without a reload', async () => {
		buildDom()
		const calls = []
		routeBy((url) => {
			calls.push(url)
			return url.endsWith('/tokensets/upload')
				? { data: CONVERTED }
				: { data: { tokenSets: [], sets: [] } }
		})
		await loadAdminScript()
		const before = calls.length
		await paste('OpenWOO', THEME)

		expect(
			calls
				.slice(before)
				.some(
					(url) =>
						/tokenset|catalog/i.test(url) && !url.endsWith('/upload'),
				),
		).toBe(true)
	})
})
