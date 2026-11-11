/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin.js — dark-mode UI additions:
 * - the theming-sync dialog's informational dark-logo row (rendered iff the
 *   selected token set's `theming.logo_dark` is present, never sent to
 *   `POST /settings/theming`)
 * - the dark-variants admin toggle (POSTs to `/settings/dark-variants`)
 *
 * Mirrors the black-box, real-DOM-event style of tests/vitest/admin-a11y.spec.js
 * (admin.js is a vanilla-JS IIFE with no exports).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/theming-sync/spec.md
 * @spec openspec/specs/dark-mode/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * Stand in for Nextcloud's initial-state channel.
 *
 * js/admin.js reads its server data through `OCP.InitialState.loadState()`
 * (ADR-004), so a fixture has to hand it over the same way the settings page
 * does. Supplying it as `data-*` attributes — which is what these fixtures
 * used to do — no longer reaches the script, and the template no longer
 * emits those attributes either.
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

/** Build the minimal settings-page DOM the script expects, per token-set config. */
function buildDom(tokenSets, currentTokenSet) {
	installInitialState({
		tokenSets,
		currentTokenSet,
		activePreview: null,
		iconPackSource: '',
	})
	document.body.innerHTML = `
		<div id="nldesign-settings" class="section">
			<select id="nldesign-token-set-select" name="nldesign-token-set">
				${tokenSets.map((ts) => `<option value="${ts.id}" data-design-system="nldesign"${ts.id === currentTokenSet ? ' selected' : ''}>${ts.name}</option>`).join('')}
			</select>
			<span id="nldesign-design-system-badge"></span>
			<input type="checkbox" id="nldesign-hide-slogan">
			<input type="checkbox" id="nldesign-show-menu-labels">
			<input type="checkbox" id="nldesign-dark-variants">
		</div>
		<div class="nldesign-preview" id="nldesign-preview"></div>
		<div class="nldesign-app-theming" id="nldesign-app-theming">
			<div id="nldesign-app-theming-list"></div>
			<button type="button" id="nldesign-app-theming-save">Save</button>
			<span id="nldesign-app-theming-feedback"></span>
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

/** Route a fetch() call to canned JSON based on a URL substring; records POST bodies. */
function installFetchRouter(routes, postLog) {
	global.fetch = vi.fn((url, options) => {
		if (options && options.method === 'POST' && postLog) {
			postLog.push({ url, body: options.body })
		}
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

/** Flush pending promise chains (see admin-a11y.spec.js for the rationale). */
async function flush(rounds = 8) {
	for (let i = 0; i < rounds; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

/** (Re-)import js/admin.js as a fresh module instance, running its IIFE against the current DOM. */
async function loadAdminScript() {
	vi.resetModules()
	await import('../../js/lib/themingPlan.js')
	await import('../../js/admin.js?t=' + Math.random())
	await flush()
}

const TOKEN_SET_WITH_DARK_LOGO = {
	id: 'withlogo',
	name: 'With Logo',
	theming: {
		primary_color: '#154273',
		background_color: '#f5f6f7',
		logo: 'img/logos/withlogo.svg',
		logo_dark: 'img/logos/withlogo-dark.svg',
	},
}

const TOKEN_SET_WITHOUT_DARK_LOGO = {
	id: 'nodarklogo',
	name: 'No Dark Logo',
	theming: {
		primary_color: '#004699',
		background_color: '#ffffff',
	},
}

describe('admin.js dark mode', () => {
	beforeEach(() => {
		installGlobals()
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	describe('theming sync dialog — dark logo row', () => {
		async function openThemingDialog(tokenSet, postLog) {
			buildDom([tokenSet], 'rijkshuisstijl')
			installFetchRouter(
				[
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
				],
				postLog,
			)

			await loadAdminScript()

			const select = document.getElementById('nldesign-token-set-select')
			select.value = tokenSet.id
			select.dispatchEvent(new window.Event('change', { bubbles: true }))

			await flush()

			return document.getElementById('nldesign-theming-dialog-overlay')
		}

		it('renders the dark-logo row when theming.logo_dark is present', async () => {
			const overlay = await openThemingDialog(TOKEN_SET_WITH_DARK_LOGO)
			expect(overlay).not.toBeNull()

			const row = overlay.querySelector('.nldesign-dialog-dark-logo-row')
			expect(row).not.toBeNull()

			const img = row.querySelector('img')
			expect(img.getAttribute('src')).toBe('img/logos/withlogo-dark.svg')
		})

		it('omits the dark-logo row when theming.logo_dark is absent', async () => {
			const overlay = await openThemingDialog(TOKEN_SET_WITHOUT_DARK_LOGO)
			expect(overlay).not.toBeNull()

			expect(
				overlay.querySelector('.nldesign-dialog-dark-logo-row'),
			).toBeNull()
		})

		it('never includes logo_dark in the POST /settings/theming payload on confirm', async () => {
			const postLog = []
			const overlay = await openThemingDialog(
				TOKEN_SET_WITH_DARK_LOGO,
				postLog,
			)

			overlay
				.querySelector('.nldesign-dialog-confirm')
				.dispatchEvent(new window.Event('click', { bubbles: true }))
			await flush()

			const themingPost = postLog.find(
				(entry) => entry.url.indexOf('/settings/theming') !== -1,
			)
			expect(themingPost).toBeTruthy()
			expect(themingPost.body).not.toContain('logo_dark')
		})
	})

	describe('dark-variants toggle', () => {
		it('POSTs the checked state to /settings/dark-variants', async () => {
			const postLog = []
			buildDom([TOKEN_SET_WITHOUT_DARK_LOGO], 'nodarklogo')
			installFetchRouter([], postLog)

			await loadAdminScript()

			const checkbox = document.getElementById('nldesign-dark-variants')
			checkbox.checked = false
			checkbox.dispatchEvent(new window.Event('change', { bubbles: true }))

			await flush()

			const post = postLog.find(
				(entry) => entry.url.indexOf('/settings/dark-variants') !== -1,
			)
			expect(post).toBeTruthy()
			expect(JSON.parse(post.body)).toEqual({ enabled: false })
		})
	})
})
