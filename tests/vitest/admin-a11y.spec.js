/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin.js — the admin settings page script.
 *
 * admin.js is a vanilla-JS IIFE (no exports, no module system): it wires
 * itself up to the real settings-page DOM on load. There is nothing to
 * `import` and call directly, so these tests build the minimal DOM fixture
 * the script expects (mirroring templates/settings/admin.php), stub the
 * Nextcloud globals it relies on (OC, t, fetch), (re-)import the script
 * fresh per test via `vi.resetModules()`, and then drive it exactly the way
 * a user would — dispatching real DOM events — and assert on the resulting
 * DOM state. This is black-box behavioural coverage, not a refactor of the
 * script into testable units.
 *
 * Focus: the keyboard-accessibility behaviour added for the hand-rolled
 * overlay dialogs and the app-theming dropdown (WCAG 2.1.1 Keyboard / 2.4.3
 * Focus Order) — role/aria-modal wiring, initial focus placement, Tab focus
 * trapping, Escape-to-close, and focus restoration to the triggering
 * control. These behaviours have no server round-trip of their own beyond
 * the fetches already required to reach the dialog, so they are safe to
 * assert against in an offline jsdom environment.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-js-unit-test-coverage/tasks.md#task-1
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/** Build the minimal settings-page DOM the script expects, per token-set config. */
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

/** Route a fetch() call to canned JSON based on a URL substring. */
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

/**
 * Flush pending promise chains. A `setTimeout(0)` macrotask boundary drains
 * every microtask queued before it, so a handful of iterations is enough to
 * settle even a multi-hop fetch -> .then(json) -> .then(handler) -> fetch...
 * chain (each hop needs more than one microtask tick to resolve and adopt).
 */
async function flush(rounds = 8) {
	for (let i = 0; i < rounds; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

/** (Re-)import js/admin.js as a fresh module instance, running its IIFE against the current DOM. */
async function loadAdminScript() {
	vi.resetModules()
	// The page loads js/lib/appTheming.js before admin.js; so does the fixture.
	await import('../../js/lib/appTheming.js')
	await import('../../js/admin.js?t=' + Math.random())
	// admin.js's fetch-driven init calls (initAppTheming, etc.) resolve
	// asynchronously; flush them before assertions run.
	await flush()
}

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

describe('admin.js keyboard accessibility', () => {
	beforeEach(() => {
		installGlobals()
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	describe('theming sync dialog', () => {
		/**
		 * @param {(select: HTMLSelectElement) => void} [beforeTrigger] Runs after
		 *   the DOM/script are ready but before the change event that opens the
		 *   dialog fires — lets a test grab/focus an element beforehand.
		 */
		async function openThemingDialog(beforeTrigger) {
			buildDom(TOKEN_SETS, 'rijkshuisstijl')
			installFetchRouter([
				// No apply-diff preview available -> saveTokenSet() runs directly.
				['tokenset-preview', { error: 'not applicable' }],
				['/settings/tokenset', { status: 'ok' }],
				// Current NC theming differs from the proposed set's metadata,
				// so checkAndShowThemingDialog() finds diffs and opens the dialog.
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

			await loadAdminScript()

			const select = document.getElementById('nldesign-token-set-select')
			if (typeof beforeTrigger === 'function') {
				beforeTrigger(select)
			}
			select.value = 'gemeente-demo'
			select.dispatchEvent(new window.Event('change', { bubbles: true }))

			// Flush the chained fetches: tokenset-preview -> tokenset -> theming.
			await flush()

			return document.getElementById('nldesign-theming-dialog-overlay')
		}

		it('opens with dialog role, aria-modal, and focus on the first control', async () => {
			const overlay = await openThemingDialog()
			expect(overlay).not.toBeNull()

			const dialogEl = overlay.querySelector('.nldesign-dialog')
			expect(dialogEl.getAttribute('role')).toBe('dialog')
			expect(dialogEl.getAttribute('aria-modal')).toBe('true')
			expect(dialogEl.getAttribute('aria-labelledby')).toBeTruthy()

			// Cancel is the first focusable control in the dialog markup.
			const cancelBtn = dialogEl.querySelector('.nldesign-dialog-cancel')
			expect(document.activeElement).toBe(cancelBtn)
		})

		it('closes on Escape and restores focus to the element that opened it', async () => {
			let previouslyFocused
			const overlay = await openThemingDialog((select) => {
				previouslyFocused = select
				previouslyFocused.focus()
			})
			expect(overlay).not.toBeNull()
			expect(
				document.getElementById('nldesign-theming-dialog-overlay'),
			).not.toBeNull()

			overlay.dispatchEvent(
				new window.KeyboardEvent('keydown', {
					key: 'Escape',
					bubbles: true,
				}),
			)

			expect(
				document.getElementById('nldesign-theming-dialog-overlay'),
			).toBeNull()
			expect(document.activeElement).toBe(previouslyFocused)
		})

		it('traps Tab focus within the dialog (wraps from last back to first)', async () => {
			const overlay = await openThemingDialog()
			const dialogEl = overlay.querySelector('.nldesign-dialog')
			const buttons = dialogEl.querySelectorAll('button')
			const first = buttons[0]
			const last = buttons[buttons.length - 1]

			last.focus()
			expect(document.activeElement).toBe(last)

			overlay.dispatchEvent(
				new window.KeyboardEvent('keydown', {
					key: 'Tab',
					bubbles: true,
					cancelable: true,
				}),
			)

			expect(document.activeElement).toBe(first)
		})
	})

	describe('app-theming dropdown', () => {
		async function openDropdown() {
			buildDom(TOKEN_SETS, 'rijkshuisstijl')
			installFetchRouter([
				[
					'/settings/app-theming',
					{ apps: [{ id: 'files', name: 'Files', themed: true }] },
				],
			])

			await loadAdminScript()

			const trigger = document.querySelector('.nldesign-app-dropdown-trigger')
			expect(trigger).not.toBeNull()
			trigger.click()

			return {
				trigger,
				dropdown: document.querySelector('.nldesign-app-dropdown'),
			}
		}

		it('opens with aria-expanded=true and moves focus to the search field', async () => {
			const { trigger, dropdown } = await openDropdown()

			expect(trigger.getAttribute('aria-expanded')).toBe('true')
			expect(dropdown.classList.contains('open')).toBe(true)
			expect(document.activeElement).toBe(
				dropdown.querySelector('input[type="search"]'),
			)
		})

		it('closes on Escape, sets aria-expanded=false, and returns focus to the trigger', async () => {
			const { trigger, dropdown } = await openDropdown()
			expect(dropdown.classList.contains('open')).toBe(true)

			dropdown.dispatchEvent(
				new window.KeyboardEvent('keydown', {
					key: 'Escape',
					bubbles: true,
				}),
			)

			expect(dropdown.classList.contains('open')).toBe(false)
			expect(trigger.getAttribute('aria-expanded')).toBe('false')
			expect(document.activeElement).toBe(trigger)
		})
	})

	describe('token-editor reset button', () => {
		it('carries an aria-label naming the token being reset', async () => {
			buildDom(TOKEN_SETS, 'rijkshuisstijl')
			document.body.insertAdjacentHTML(
				'beforeend',
				'<div id="nldesign-token-editor"></div>',
			)
			installFetchRouter([
				[
					'/settings/overrides',
					{
						registry: {
							'--nldesign-color-primary': {
								tab: 'login',
								type: 'color',
								label: 'Primary color',
							},
						},
						tabs: { login: 'Login' },
						overrides: {},
					},
				],
			])

			await loadAdminScript()

			const resetBtn = document.querySelector('.nldesign-reset-btn')
			expect(resetBtn).not.toBeNull()
			expect(resetBtn.getAttribute('aria-label')).toBe(
				'Reset Primary color to default',
			)
		})
	})
})
