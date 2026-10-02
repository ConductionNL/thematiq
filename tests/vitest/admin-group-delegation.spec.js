/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin.js: the delegate toggle and the allowed sets picker
 * of each group mapping row (openspec/specs/per-group-theming/spec.md).
 * Harness copied from admin-group-theming.spec.js.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/per-group-theming/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/** Build the minimal settings-page DOM the script expects, plus the group-theming section. */
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
		currentTokenSet: 'nextcloud',
		activePreview: null,
		iconPackSource: '',
	})
	document.body.innerHTML = `
		<div id="nldesign-settings" class="section">
			<select id="nldesign-token-set-select" name="nldesign-token-set"></select>
			<span id="nldesign-design-system-badge"></span>
			<input type="checkbox" id="nldesign-hide-slogan">
			<input type="checkbox" id="nldesign-show-menu-labels">
		</div>
		<div class="nldesign-preview" id="nldesign-preview"></div>
		<div class="nldesign-group-theming" id="nldesign-group-theming">
			<div id="nldesign-group-theming-list"></div>
			<button type="button" id="nldesign-group-theming-add">Add mapping</button>
			<button type="button" id="nldesign-group-theming-save">Save</button>
			<span id="nldesign-group-theming-feedback"></span>
		</div>
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

/** Route a fetch() call to canned JSON based on a URL substring and method. */
function installFetchRouter(routes) {
	global.fetch = vi.fn((url, options) => {
		const method = (options && options.method) || 'GET'
		for (const [matchUrl, matchMethod, body] of routes) {
			if (url.indexOf(matchUrl) !== -1 && method === matchMethod) {
				return Promise.resolve({
					ok: true,
					status: 200,
					json: () => Promise.resolve(body),
				})
			}
		}
		return Promise.resolve({
			ok: true,
			status: 200,
			json: () => Promise.resolve({}),
		})
	})
}

async function flush(rounds = 8) {
	for (let i = 0; i < rounds; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

async function loadAdminScript() {
	vi.resetModules()
	await import('../../js/admin.js?t=' + Math.random())
	await flush()
}

const GROUPS = [
	{ id: 'gemeente-a', displayName: 'Gemeente A' },
	{ id: 'gemeente-b', displayName: 'Gemeente B' },
]
const TOKEN_SETS = [
	{ id: 'amsterdam', name: 'Amsterdam' },
	{ id: 'utrecht', name: 'Utrecht' },
]

describe('admin.js group delegation', () => {
	beforeEach(() => {
		installGlobals()
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('shows a delegated row with its allowed sets, and a locked row without', async () => {
		buildDom()
		installFetchRouter([
			[
				'/settings/group-theming',
				'GET',
				{
					mapping: [
						{
							group: 'gemeente-a',
							tokenSet: 'amsterdam',
							delegated: true,
							allowedTokenSets: ['amsterdam', 'utrecht'],
						},
						{ group: 'gemeente-b', tokenSet: 'utrecht' },
					],
					groups: GROUPS,
					tokenSets: TOKEN_SETS,
				},
			],
		])

		await loadAdminScript()

		const rows = document.querySelectorAll('.nldesign-group-theming-row')
		const toggle = rows[0].querySelector('[data-field="delegated"]')
		const label = rows[0].querySelector('label[for="' + toggle.id + '"]')
		expect(toggle.checked).toBe(true)
		expect(label.textContent).toBe('Subadmins choose')
		const picker = rows[0].querySelector('[data-field="allowedTokenSets"]')
		expect(picker.hidden).toBe(false)
		expect(picker.getAttribute('aria-label')).toContain('subadmins')
		expect(
			Array.from(picker.options)
				.filter((o) => o.selected)
				.map((o) => o.value),
		).toEqual(['amsterdam', 'utrecht'])
		expect(rows[1].querySelector('[data-field="delegated"]').checked).toBe(false)
		expect(rows[1].querySelector('[data-field="allowedTokenSets"]').hidden).toBe(
			true,
		)
	})

	it('delegates a row starting from its current set, and saves the fields', async () => {
		buildDom()
		installFetchRouter([
			[
				'/settings/group-theming',
				'GET',
				{
					mapping: [{ group: 'gemeente-b', tokenSet: 'utrecht' }],
					groups: GROUPS,
					tokenSets: TOKEN_SETS,
				},
			],
			['/settings/group-theming', 'POST', { status: 'ok', mapping: [] }],
		])

		await loadAdminScript()

		const row = document.querySelector('.nldesign-group-theming-row')
		const toggle = row.querySelector('[data-field="delegated"]')
		toggle.checked = true
		toggle.dispatchEvent(new Event('change'))
		const picker = row.querySelector('[data-field="allowedTokenSets"]')
		expect(picker.hidden).toBe(false)
		expect(
			Array.from(picker.options)
				.filter((o) => o.selected)
				.map((o) => o.value),
		).toEqual(['utrecht'])

		document.getElementById('nldesign-group-theming-save').click()
		await flush()

		const post = global.fetch.mock.calls.find(
			(call) => call[1] && call[1].method === 'POST',
		)
		expect(JSON.parse(post[1].body).mapping).toEqual([
			{
				group: 'gemeente-b',
				tokenSet: 'utrecht',
				delegated: true,
				allowedTokenSets: ['utrecht'],
			},
		])
	})
})
