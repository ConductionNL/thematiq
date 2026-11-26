/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin.js — the "Group theming" section
 * (openspec/specs/per-group-theming/spec.md, openspec/specs/admin-settings/spec.md).
 *
 * Mirrors the black-box approach of tests/vitest/admin-a11y.spec.js: build
 * the minimal DOM fixture the script expects, stub the Nextcloud globals,
 * (re-)import js/admin.js fresh per test, and drive it via real DOM events.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/admin-settings/spec.md
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

describe('admin.js group theming section', () => {
	beforeEach(() => {
		installGlobals()
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('renders mapping rows in the stored priority order', async () => {
		buildDom()
		installFetchRouter([
			[
				'/settings/group-theming',
				'GET',
				{
					mapping: [
						{ group: 'gemeente-a', tokenSet: 'amsterdam' },
						{ group: 'gemeente-b', tokenSet: 'utrecht' },
					],
					groups: GROUPS,
					tokenSets: TOKEN_SETS,
				},
			],
		])

		await loadAdminScript()

		const rows = document.querySelectorAll('.nldesign-group-theming-row')
		expect(rows.length).toBe(2)
		expect(rows[0].querySelector('[data-field="group"]').value).toBe(
			'gemeente-a',
		)
		expect(rows[0].querySelector('[data-field="tokenSet"]').value).toBe(
			'amsterdam',
		)
		expect(rows[1].querySelector('[data-field="group"]').value).toBe(
			'gemeente-b',
		)
		expect(rows[1].querySelector('[data-field="tokenSet"]').value).toBe(
			'utrecht',
		)
	})

	it('shows the empty state when no mappings are configured', async () => {
		buildDom()
		installFetchRouter([
			[
				'/settings/group-theming',
				'GET',
				{ mapping: [], groups: GROUPS, tokenSets: TOKEN_SETS },
			],
		])

		await loadAdminScript()

		const listEl = document.getElementById('nldesign-group-theming-list')
		expect(listEl.textContent).toContain('No group mappings configured.')
		expect(document.querySelectorAll('.nldesign-group-theming-row').length).toBe(
			0,
		)
	})

	it('adds a row via the Add mapping button', async () => {
		buildDom()
		installFetchRouter([
			[
				'/settings/group-theming',
				'GET',
				{ mapping: [], groups: GROUPS, tokenSets: TOKEN_SETS },
			],
		])

		await loadAdminScript()

		document.getElementById('nldesign-group-theming-add').click()

		const rows = document.querySelectorAll('.nldesign-group-theming-row')
		expect(rows.length).toBe(1)
		expect(rows[0].querySelector('[data-field="group"]').value).toBe(
			'gemeente-a',
		)
		expect(rows[0].querySelector('[data-field="tokenSet"]').value).toBe(
			'amsterdam',
		)
	})

	it('keeps Add disabled until the stored mappings have loaded, so an added row is not lost', async () => {
		buildDom()
		let answer
		global.fetch = vi.fn(
			() =>
				new Promise((resolve) => {
					answer = resolve
				}),
		)

		await loadAdminScript()

		const add = document.getElementById('nldesign-group-theming-add')
		expect(add.disabled).toBe(true)

		answer({
			ok: true,
			status: 200,
			json: () =>
				Promise.resolve({
					mapping: [{ group: 'gemeente-b', tokenSet: 'utrecht' }],
					groups: GROUPS,
					tokenSets: TOKEN_SETS,
				}),
		})
		await flush()

		expect(add.disabled).toBe(false)
		add.click()
		expect(
			document.querySelectorAll('.nldesign-group-theming-row').length,
		).toBe(2)
	})

	it('leaves Add disabled when the mappings cannot be loaded', async () => {
		buildDom()
		global.fetch = vi.fn(() => Promise.reject(new Error('offline')))
		vi.spyOn(console, 'error').mockImplementation(() => {})

		await loadAdminScript()

		expect(document.getElementById('nldesign-group-theming-add').disabled).toBe(true)
	})

	it('removes a row via its remove button', async () => {
		buildDom()
		installFetchRouter([
			[
				'/settings/group-theming',
				'GET',
				{
					mapping: [
						{ group: 'gemeente-a', tokenSet: 'amsterdam' },
						{ group: 'gemeente-b', tokenSet: 'utrecht' },
					],
					groups: GROUPS,
					tokenSets: TOKEN_SETS,
				},
			],
		])

		await loadAdminScript()

		document
			.querySelectorAll('.nldesign-group-theming-row')[0]
			.querySelector('.nldesign-group-theming-remove')
			.click()

		const rows = document.querySelectorAll('.nldesign-group-theming-row')
		expect(rows.length).toBe(1)
		expect(rows[0].querySelector('[data-field="group"]').value).toBe(
			'gemeente-b',
		)
	})

	it("reorders with the keyboard alone and keeps focus on the moved row's move-up button", async () => {
		buildDom()
		installFetchRouter([
			[
				'/settings/group-theming',
				'GET',
				{
					mapping: [
						{ group: 'gemeente-a', tokenSet: 'amsterdam' },
						{ group: 'gemeente-b', tokenSet: 'utrecht' },
					],
					groups: GROUPS,
					tokenSets: TOKEN_SETS,
				},
			],
		])

		await loadAdminScript()

		const secondRowMoveUp = document
			.querySelectorAll('.nldesign-group-theming-row')[1]
			.querySelector('.nldesign-group-theming-move-up')
		secondRowMoveUp.focus()
		// A native <button> fires 'click' on Enter/Space activation — dispatch
		// it directly, which is what the browser does for keyboard activation.
		secondRowMoveUp.click()

		const rows = document.querySelectorAll('.nldesign-group-theming-row')
		expect(rows[0].querySelector('[data-field="group"]').value).toBe(
			'gemeente-b',
		)
		expect(rows[1].querySelector('[data-field="group"]').value).toBe(
			'gemeente-a',
		)
		// Focus MUST stay on the row the user just moved (WCAG 2.1.1 Keyboard /
		// 2.4.3 Focus Order — focus must never fall back to <body>). The row is
		// now first, so its move-up button is disabled and cannot hold focus;
		// the nearest operable control on the same row takes it instead.
		expect(rows[0].contains(document.activeElement)).toBe(true)
		expect(document.activeElement).toBe(
			rows[0].querySelector('.nldesign-group-theming-move-down'),
		)
	})

	it('disables move-up on the first row and move-down on the last row', async () => {
		buildDom()
		installFetchRouter([
			[
				'/settings/group-theming',
				'GET',
				{
					mapping: [
						{ group: 'gemeente-a', tokenSet: 'amsterdam' },
						{ group: 'gemeente-b', tokenSet: 'utrecht' },
					],
					groups: GROUPS,
					tokenSets: TOKEN_SETS,
				},
			],
		])

		await loadAdminScript()

		const rows = document.querySelectorAll('.nldesign-group-theming-row')
		expect(
			rows[0].querySelector('.nldesign-group-theming-move-up').disabled,
		).toBe(true)
		expect(
			rows[1].querySelector('.nldesign-group-theming-move-down').disabled,
		).toBe(true)
		expect(
			rows[0].querySelector('.nldesign-group-theming-move-down').disabled,
		).toBe(false)
		expect(
			rows[1].querySelector('.nldesign-group-theming-move-up').disabled,
		).toBe(false)
	})

	it('POSTs the mapping in the displayed order and announces success', async () => {
		buildDom()
		let postedBody = null
		global.fetch = vi.fn((url, options) => {
			const method = (options && options.method) || 'GET'
			if (url.indexOf('/settings/group-theming') !== -1 && method === 'GET') {
				return Promise.resolve({
					ok: true,
					status: 200,
					json: () =>
						Promise.resolve({
							mapping: [
								{ group: 'gemeente-a', tokenSet: 'amsterdam' },
								{ group: 'gemeente-b', tokenSet: 'utrecht' },
							],
							groups: GROUPS,
							tokenSets: TOKEN_SETS,
						}),
				})
			}
			if (url.indexOf('/settings/group-theming') !== -1 && method === 'POST') {
				postedBody = JSON.parse(options.body)
				return Promise.resolve({
					ok: true,
					status: 200,
					json: () =>
						Promise.resolve({
							status: 'ok',
							mapping: postedBody.mapping,
						}),
				})
			}
			return Promise.resolve({
				ok: true,
				status: 200,
				json: () => Promise.resolve({}),
			})
		})

		await loadAdminScript()

		// Move the second row above the first, then save.
		document
			.querySelectorAll('.nldesign-group-theming-row')[1]
			.querySelector('.nldesign-group-theming-move-up')
			.click()
		document.getElementById('nldesign-group-theming-save').click()
		await flush()

		expect(postedBody).toEqual({
			mapping: [
				{ group: 'gemeente-b', tokenSet: 'utrecht' },
				{ group: 'gemeente-a', tokenSet: 'amsterdam' },
			],
		})
		expect(
			document.getElementById('nldesign-group-theming-feedback').textContent,
		).toContain('saved')
	})

	it('surfaces a 422 validation error per entry without resetting the rows', async () => {
		buildDom()
		installFetchRouter([
			[
				'/settings/group-theming',
				'GET',
				{
					mapping: [{ group: 'gemeente-a', tokenSet: 'amsterdam' }],
					groups: GROUPS,
					tokenSets: TOKEN_SETS,
				},
			],
		])

		await loadAdminScript()

		global.fetch = vi.fn((url, options) => {
			const method = (options && options.method) || 'GET'
			if (url.indexOf('/settings/group-theming') !== -1 && method === 'POST') {
				return Promise.resolve({
					ok: false,
					status: 422,
					json: () =>
						Promise.resolve({
							error: 'invalid_mapping',
							entry: { group: 'gemeente-a', tokenSet: 'amsterdam' },
							reason: 'Group "gemeente-a" does not exist.',
						}),
				})
			}
			return Promise.resolve({
				ok: true,
				status: 200,
				json: () => Promise.resolve({}),
			})
		})

		document.getElementById('nldesign-group-theming-save').click()
		await flush()

		const feedback = document.getElementById('nldesign-group-theming-feedback')
		expect(feedback.textContent).toContain('gemeente-a')
		expect(feedback.textContent).toContain('does not exist')
		// Rows remain editable — no silent state reset.
		expect(document.querySelectorAll('.nldesign-group-theming-row').length).toBe(
			1,
		)
	})
})
