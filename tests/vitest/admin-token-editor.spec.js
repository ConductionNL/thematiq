/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin.js — the token editor and what saving from it does:
 * - locked rows: Nextcloud's base tokens behind a warned opt-in, and the rows
 *   "primary drives every component" owns
 * - colour rows that hold an `r, g, b` triplet, and the per-row reset
 * - where an unsaved edit is shown: the preview, and for the primary family
 *   the settings section too, so the editor's own controls follow it
 * - what switching sets offers: the set as saved, without the primary family
 *   when the set brings its own primary through the theming sync
 * - the two save confirmations (stock: new theme or over Nextcloud; any other
 *   set: a plain confirm), their "do not ask again", and the controls that
 *   turn them back on
 * - saving as a new theme, which creates, selects and applies it
 * - which set's overrides file the page loads after a swap
 * - the theming-sync rows for the navigation logo, favicon, background image
 *   and the background state a captured theme brings back
 * - "Reset theme to Nextcloud"
 *
 * Black-box, real-DOM-event style, like tests/vitest/admin-a11y.spec.js
 * (admin.js is a vanilla-JS IIFE with no exports).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/token-editor-ui/spec.md#requirement-save-action
 * @spec openspec/specs/token-editor-ui/spec.md#requirement-per-token-reset
 * @spec openspec/specs/token-editor-ui/spec.md#requirement-editable-token-input
 * @spec openspec/specs/token-editor-ui/spec.md#requirement-live-preview
 * @spec openspec/specs/token-set-apply-dialog/spec.md#requirement-resolved-value-comparison
 * @spec openspec/specs/theming-sync/spec.md#requirement-theming-sync-dialog-frontend
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const BASE = '--color-primary-element'
const BUTTON = '--nldesign-component-button-primary-background'
const INFO_RGB = '--nldesign-color-info-rgb'
const FONT = '--nldesign-font-family'

const REGISTRY = {
	[BASE]: { tab: 'content', type: 'color', label: 'Primary', group: 'brand' },
	[BUTTON]: {
		tab: 'content',
		type: 'color',
		label: 'Button',
		primary: true,
		global: BASE,
	},
	[INFO_RGB]: { tab: 'status', type: 'rgb', label: 'Info' },
	[FONT]: { tab: 'typography', type: 'text', label: 'Font' },
}

const TOKEN_SETS = [
	{ id: 'nextcloud', name: 'Nextcloud (Base)', design_system: 'none' },
	{
		id: 'rijkshuisstijl',
		name: 'Rijkshuisstijl',
		theming: { primary_color: '#154273' },
	},
]

/** Every fetch() the script made, as `{ url, method, body }`. */
let requests = []

/** Canned answers, as `[method, url substring, status, body]`, first match wins. */
let routes = []

/**
 * Stand in for Nextcloud's initial-state channel.
 *
 * @param {object} state Initial-state keys as provided by lib/Settings/Admin.php.
 */
function installInitialState(state) {
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) =>
				Object.prototype.hasOwnProperty.call(state, key)
					? state[key]
					: fallback,
		},
	}
}

/** Minimal OC / t / n globals admin.js reads at load and call time. */
function installGlobals() {
	global.t = (app, text, params) =>
		params === undefined
			? text
			: Object.keys(params).reduce(
					(acc, key) => acc.replace('{' + key + '}', params[key]),
					text,
				)
	global.n = (app, singular, plural, count) => (count === 1 ? singular : plural)
	global.OC = {
		generateUrl: (url) => url,
		linkTo: (app, path) => path,
		filePath: (app, type, file) => '/apps/' + app + '/' + type + '/' + file,
		requestToken: 'test-token',
		Notification: { showTemporary: vi.fn() },
		dialogs: { confirm: vi.fn() },
	}
}

/** Route fetch() to the canned answers and record every call. */
function installFetch() {
	global.fetch = vi.fn((url, options = {}) => {
		const method = options.method || 'GET'
		requests.push({ url, method, body: options.body, headers: options.headers })
		for (const [m, match, status, body] of routes) {
			if (m === method && matches(url, match)) {
				if (body instanceof Error) {
					return Promise.reject(body)
				}
				return Promise.resolve({
					ok: status < 400,
					status,
					json: () =>
						body === undefined
							? Promise.reject(new Error('no body'))
							: Promise.resolve(body),
					blob: () => Promise.resolve(new window.Blob([String(body)])),
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

/**
 * Whether a URL is the one meant: a substring, or a RegExp where a substring
 * would also catch a longer path (`/settings/tokenset` vs `/settings/tokensets/upload`).
 *
 * @param {string} url The requested URL.
 * @param {string|RegExp} match A URL substring or pattern.
 * @return {boolean} True on a match.
 */
function matches(url, match) {
	return match instanceof RegExp ? match.test(url) : url.indexOf(match) !== -1
}

/** The token set commit endpoint, and only that one. */
const COMMIT = /\/settings\/tokenset$/

/**
 * Put an answer ahead of the defaults.
 *
 * @param {string} method The HTTP method.
 * @param {string|RegExp} match A URL substring or pattern.
 * @param {number} status The HTTP status.
 * @param {*} body The JSON body, an Error to reject with, or undefined for a body that is not JSON.
 */
function answer(method, match, status, body) {
	routes.unshift([method, match, status, body])
}

/** The requests to a URL substring or pattern, optionally of one method. */
function sent(match, method) {
	return requests.filter(
		(r) =>
			matches(r.url, match) && (method === undefined || r.method === method),
	)
}

/** Flush pending promise chains across a few macrotask boundaries. */
async function flush(rounds = 10) {
	for (let i = 0; i < rounds; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

/**
 * Build the settings page and load admin.js against it.
 *
 * @param {object} [options] Fixture options.
 * @param {string} [options.current] The active token set.
 * @param {object} [options.overrides] The saved overrides the editor loads.
 * @param {object} [options.state] Extra initial-state keys.
 * @param {boolean} [options.primaryDrives] Whether the primary-drives box starts ticked.
 * @param {boolean} [options.withSelect] Whether the page has the token set dropdown.
 * @param {string} [options.extraHtml] More of the settings page, inside the section.
 */
async function mount({
	current = 'rijkshuisstijl',
	overrides = {},
	state = {},
	primaryDrives = false,
	withSelect = true,
	extraHtml = '',
} = {}) {
	installInitialState({
		tokenSets: TOKEN_SETS,
		currentTokenSet: current,
		activePreview: null,
		iconPackSource: '',
		...state,
	})
	document.head.innerHTML =
		'<link rel="stylesheet" href="/apps/thematiq/css/custom-overrides.css?v=1">'
	document.body.innerHTML = `
		<div id="nldesign-settings" class="section">
			<select id="nldesign-token-set-select">
				${TOKEN_SETS.map((ts) => `<option value="${ts.id}" data-design-system="${ts.design_system || 'nldesign'}"${ts.id === current ? ' selected' : ''}>${ts.name}</option>`).join('')}
			</select>
			<span id="nldesign-design-system-badge"></span>
			<input type="checkbox" id="nldesign-primary-drives-components"${primaryDrives ? ' checked' : ''}>
			<button type="button" id="nldesign-reset-theme-btn">Reset</button>
			${extraHtml}
		</div>
		<div class="nldesign-preview" id="nldesign-preview"></div>
		<div id="nldesign-token-editor"></div>
	`
	if (withSelect === false) {
		document.getElementById('nldesign-token-set-select').remove()
	}
	answer('GET', '/settings/overrides?', 200, {
		overrides,
		registry: REGISTRY,
		tabs: { content: 'Content', status: 'Status', typography: 'Type' },
	})

	vi.resetModules()
	await import('../../js/lib/tokenTransforms.js?t=' + Math.random())
	await import('../../js/admin.js?t=' + Math.random())
	await flush()
}

/** A token row's inputs and reset button. */
function row(name) {
	const el = document.querySelector('[data-token-row="' + name + '"]')
	return {
		el,
		picker: el.querySelector('.nldesign-color-picker'),
		text: el.querySelector('.nldesign-color-text, .nldesign-text-input'),
		reset: el.querySelector('.nldesign-reset-btn'),
	}
}

/** Type a value into an input and fire what the browser fires. */
function type(input, value) {
	input.value = value
	input.dispatchEvent(new window.Event('input', { bubbles: true }))
}

/** Tick or untick a checkbox and fire `change`. */
function tick(box, checked) {
	box.checked = checked
	box.dispatchEvent(new window.Event('change', { bubbles: true }))
}

/** Click an element. */
function click(el) {
	el.dispatchEvent(new window.MouseEvent('click', { bubbles: true }))
}

/** The save dialog, if one is open. */
function saveDialog() {
	return document.getElementById('nldesign-save-dialog-overlay')
}

/** Every toast the script raised, in order. */
function toasts() {
	return OC.Notification.showTemporary.mock.calls.map((call) => call[0])
}

/** Tick "Also edit Nextcloud's base tokens" and accept its warning. */
function unlockBase() {
	tick(document.getElementById('nldesign-base-unlock'), true)
	click(
		document
			.getElementById('nldesign-base-unlock-overlay')
			.querySelector('.nldesign-dialog-confirm'),
	)
}

/**
 * Pick another set in the dropdown, with what its file resolves to and what
 * was saved for it.
 *
 * @param {string} id The token set to switch to.
 * @param {object} resolved What the set's file resolves to.
 * @param {*} [saved] The overrides answer for the set, or an Error to fail it with.
 */
async function switchTo(id, resolved, saved) {
	answer('GET', 'tokenset-preview', 200, { resolved })
	if (saved !== undefined) {
		answer('GET', 'overrides?tokenSet=' + id, 200, saved)
	}
	const select = document.getElementById('nldesign-token-set-select')
	select.value = id
	select.dispatchEvent(new window.Event('change', { bubbles: true }))
	await flush()
}

/** The apply dialog's rows, as `{ token: new value }`. */
function applyRows() {
	return Object.fromEntries(
		[
			...document.querySelectorAll(
				'#nldesign-apply-dialog-overlay .nldesign-apply-check',
			),
		].map((box) => [box.dataset.token, box.closest('tr').cells[3].textContent]),
	)
}

/** The JSON body of the last request to a URL substring. */
function lastBody(match, method = 'POST') {
	const matching = sent(match, method)
	return JSON.parse(matching[matching.length - 1].body)
}

describe('admin.js token editor', () => {
	beforeEach(() => {
		requests = []
		routes = []
		installGlobals()
		installFetch()
		if (typeof global.requestAnimationFrame !== 'function') {
			global.requestAnimationFrame = (cb) => setTimeout(cb, 0)
		}
	})

	afterEach(() => {
		document.head.innerHTML = ''
		document.body.innerHTML = ''
		// The apply dialog previews its values inline on <html>, which the
		// body reset above does not reach.
		document.documentElement.removeAttribute('style')
		delete window.NldesignLayerSwap
		delete window.ThematiqPlayground
		vi.restoreAllMocks()
	})

	describe('locked rows', () => {
		it('locks the base tokens until the admin confirms the warning', async () => {
			await mount()

			const base = row(BASE)
			expect(base.picker.disabled).toBe(true)
			expect(base.reset.disabled).toBe(true)
			expect(base.text.getAttribute('title')).toContain(
				'A Nextcloud base color',
			)
			expect(row(BUTTON).picker.disabled).toBe(false)

			const box = document.getElementById('nldesign-base-unlock')
			tick(box, true)
			const overlay = document.getElementById('nldesign-base-unlock-overlay')
			expect(overlay).not.toBeNull()
			// The box only stays ticked once the warning is accepted.
			expect(box.checked).toBe(false)

			click(overlay.querySelector('.nldesign-dialog-cancel'))
			expect(
				document.getElementById('nldesign-base-unlock-overlay'),
			).toBeNull()
			expect(row(BASE).picker.disabled).toBe(true)

			tick(box, true)
			click(
				document
					.getElementById('nldesign-base-unlock-overlay')
					.querySelector('.nldesign-dialog-confirm'),
			)
			expect(box.checked).toBe(true)
			expect(row(BASE).picker.disabled).toBe(false)
			expect(row(BASE).text.hasAttribute('title')).toBe(false)

			// Unticking locks them again straight away, no question asked.
			tick(box, false)
			expect(row(BASE).picker.disabled).toBe(true)
			expect(row(BASE).text.getAttribute('title')).toContain(
				'Tick "Also edit Nextcloud\'s base tokens" to change it.',
			)
			expect(
				document.getElementById('nldesign-base-unlock-overlay'),
			).toBeNull()
		})

		it("draws its checkboxes as Nextcloud's own input.checkbox and label pair", async () => {
			await mount()

			for (const id of [
				'nldesign-base-unlock',
				'nldesign-confirm-save-stock',
				'nldesign-confirm-save-theme',
			]) {
				const box = document.getElementById(id)
				expect(box.classList.contains('checkbox'), id).toBe(true)
				// A sibling label, not a wrapping one: core hides the input
				// and draws the box on the label.
				expect(box.closest('label'), id).toBeNull()
				expect(
					document.querySelector('label[for="' + id + '"]'),
					id,
				).not.toBeNull()
			}
		})

		it('locks the rows the primary drives, and says why', async () => {
			await mount({ primaryDrives: true })

			const button = row(BUTTON)
			expect(button.el.classList.contains('nldesign-token-row--locked')).toBe(
				true,
			)
			expect(button.picker.getAttribute('title')).toContain(
				'The primary color drives this component',
			)
		})

		it('unlocks them in place when the setting is switched off', async () => {
			answer('POST', '/settings/primary-drives-components', 200, {
				status: 'ok',
			})
			await mount({ primaryDrives: true })
			type(row(FONT).text, 'Fira Sans')

			tick(
				document.getElementById('nldesign-primary-drives-components'),
				false,
			)
			await flush()

			expect(row(BUTTON).picker.disabled).toBe(false)
			// In place, not a re-render: the unsaved edit is still there.
			expect(row(FONT).text.value).toBe('Fira Sans')
		})
	})

	describe('rows', () => {
		it('shows an unset component token as the Nextcloud colour it falls back to', async () => {
			const style = document.createElement('style')
			style.textContent = 'body { --color-primary-element: #23845c; }'
			document.head.appendChild(style)
			installInitialState({})
			document.body.innerHTML = '<div id="nldesign-token-editor"></div>'
			answer('GET', '/settings/overrides?', 200, {
				overrides: {},
				registry: { [BUTTON]: REGISTRY[BUTTON] },
				tabs: {},
			})
			// mount() rebuilds the head, so load the script here directly.
			vi.resetModules()
			await import('../../js/admin.js?t=' + Math.random())
			await flush()

			expect(row(BUTTON).text.value).toBe('#23845c')
		})

		it('keeps a quote in a saved value or label inside its attribute (#622)', async () => {
			// An imported overrides file can carry a double quote: the save
			// filter strips newlines and rejects braces and semicolons, not
			// quotes. Escaped as text only, the quote closed value="..." and the
			// rest became live attributes (onfocus + autofocus = script on load).
			const payload = 'Arial" onfocus="alert(1)" autofocus x="'
			const label = 'Font " onmouseover=\'alert(2)\' y="'
			installInitialState({})
			document.body.innerHTML = '<div id="nldesign-token-editor"></div>'
			answer('GET', '/settings/overrides?', 200, {
				overrides: { [FONT]: payload },
				registry: { [FONT]: { ...REGISTRY[FONT], tab: 'content', label } },
				tabs: {},
			})
			vi.resetModules()
			await import('../../js/admin.js?t=' + Math.random())
			await flush()

			const input = row(FONT).text
			expect(input.value).toBe(payload)
			expect(input.getAttribute('aria-label')).toBe(label)
			const editor = document.getElementById('nldesign-token-editor')
			for (const name of ['onfocus', 'autofocus', 'onmouseover', 'x', 'y']) {
				expect(editor.querySelector('[' + name + ']')).toBeNull()
			}
		})

		it('writes an r, g, b triplet from the picker of an rgb row', async () => {
			await mount()
			const info = row(INFO_RGB)

			type(info.picker, '#0a141e')

			expect(info.text.value).toBe('10, 20, 30')
			expect(
				document
					.getElementById('nldesign-preview')
					.style.getPropertyValue(INFO_RGB),
			).toBe('10, 20, 30')
		})

		it('moves the picker to a typed triplet or hex, and leaves it for anything else', async () => {
			await mount()
			const info = row(INFO_RGB)

			type(info.text, '10, 20, 30')
			expect(info.picker.value).toBe('#0a141e')

			type(info.text, '#112233')
			expect(info.picker.value).toBe('#112233')

			type(info.text, 'var(--x)')
			expect(info.picker.value).toBe('#112233')
		})

		it('resets a row to its saved value and keeps that value on the preview', async () => {
			await mount({ overrides: { [BUTTON]: '#aa0000' } })
			const button = row(BUTTON)
			const preview = document.getElementById('nldesign-preview')

			type(button.text, '#00aa00')
			expect(document.getElementById('nldesign-save-status').textContent).toBe(
				'Unsaved changes',
			)

			click(button.reset)

			expect(button.text.value).toBe('#aa0000')
			expect(button.picker.value).toBe('#aa0000')
			expect(preview.style.getPropertyValue(BUTTON)).toBe('#aa0000')
			expect(
				button.el.querySelector('.nldesign-token-custom-badge'),
			).toBeNull()
			expect(document.getElementById('nldesign-save-status').textContent).toBe(
				'',
			)
		})

		it('drops the preview value of a reset row that was never saved', async () => {
			await mount()
			const font = row(FONT)
			const preview = document.getElementById('nldesign-preview')

			type(font.text, 'Comic Sans')
			expect(preview.style.getPropertyValue(FONT)).toBe('Comic Sans')

			click(font.reset)

			expect(preview.style.getPropertyValue(FONT)).toBe('')
		})

		it("lets the editor's own controls follow an unsaved primary colour", async () => {
			await mount()
			unlockBase()
			const settings = document.getElementById('nldesign-settings')
			const preview = document.getElementById('nldesign-preview')

			type(row(BASE).text, '#112233')

			expect(preview.style.getPropertyValue(BASE)).toBe('#112233')
			expect(settings.style.getPropertyValue(BASE)).toBe('#112233')
			expect(settings.style.getPropertyPriority(BASE)).toBe('important')
			// The rest of the page still wears only what has been saved.
			expect(document.documentElement.style.getPropertyValue(BASE)).toBe('')

			type(row(BASE).text, '')

			expect(preview.style.getPropertyValue(BASE)).toBe('')
			expect(settings.style.getPropertyValue(BASE)).toBe('')
		})

		it('keeps any other edit on the preview alone', async () => {
			await mount()
			const settings = document.getElementById('nldesign-settings')

			type(row(FONT).text, 'Fira Sans')
			// Painted from the primary, but not one of its family.
			type(row(BUTTON).picker, '#123456')

			expect(settings.style.getPropertyValue(FONT)).toBe('')
			expect(settings.style.getPropertyValue(BUTTON)).toBe('')
		})

		it('resets a primary-family row on the settings section as well', async () => {
			await mount({ overrides: { [BASE]: '#aa0000' } })
			unlockBase()
			const settings = document.getElementById('nldesign-settings')

			type(row(BASE).text, '#00aa00')
			click(row(BASE).reset)

			expect(settings.style.getPropertyValue(BASE)).toBe('#aa0000')
		})

		it('drops a never-saved primary-family value from the settings section on reset', async () => {
			await mount()
			unlockBase()
			const settings = document.getElementById('nldesign-settings')

			type(row(BASE).text, '#00aa00')
			click(row(BASE).reset)

			expect(settings.style.getPropertyValue(BASE)).toBe('')
		})

		it('writes a primary colour on the page alone when there is no settings section', async () => {
			installInitialState({})
			document.body.innerHTML = '<div id="nldesign-token-editor"></div>'
			answer('GET', '/settings/overrides?', 200, {
				overrides: {},
				registry: { [BASE]: REGISTRY[BASE] },
				tabs: {},
			})
			vi.resetModules()
			await import('../../js/admin.js?t=' + Math.random())
			await flush()
			unlockBase()

			type(row(BASE).text, '#112233')

			// Without a preview either, the page root is where an edit is shown.
			expect(document.documentElement.style.getPropertyValue(BASE)).toBe(
				'#112233',
			)
		})
	})

	describe('saving on a token set', () => {
		it('asks first, naming the set, and cancelling writes nothing', async () => {
			await mount()
			type(row(FONT).text, 'Fira Sans')

			click(document.getElementById('nldesign-save-btn'))

			const dialog = saveDialog()
			expect(dialog.textContent).toContain(
				'They are applied on top of Rijkshuisstijl.',
			)
			click(dialog.querySelector('.nldesign-dialog-cancel'))
			expect(saveDialog()).toBeNull()
			expect(sent('/settings/overrides', 'POST')).toHaveLength(0)
		})

		it('writes the whole file for the set on confirm, and settles the rows', async () => {
			answer('POST', '/settings/overrides', 200, {
				status: 'ok',
				theming: { captured: true, primary_color: '#154273' },
			})
			await mount({ overrides: { [BUTTON]: '#aa0000' } })
			type(row(FONT).text, 'Fira Sans')

			click(document.getElementById('nldesign-save-btn'))
			click(saveDialog().querySelector('.nldesign-dialog-confirm'))
			await flush()

			// The untouched saved value is sent along: the endpoint replaces the file.
			expect(lastBody('/settings/overrides')).toEqual({
				overrides: { [BUTTON]: '#aa0000', [FONT]: 'Fira Sans' },
				tokenSet: 'rijkshuisstijl',
				captureTheming: true,
			})
			expect(document.querySelector('.nldesign-token-custom-badge')).toBeNull()
			expect(document.getElementById('nldesign-save-btn').disabled).toBe(false)
			expect(toasts()).toContain('Token overrides saved.')
		})

		it('stops saving a value whose field was cleared', async () => {
			answer('POST', '/settings/overrides', 200, { status: 'ok' })
			await mount({
				overrides: { [BUTTON]: '#aa0000' },
				state: { confirmSaveTheme: false },
			})
			type(row(BUTTON).text, '')

			click(document.getElementById('nldesign-save-btn'))
			await flush()

			expect(lastBody('/settings/overrides').overrides).toEqual({})
		})

		it('reports a refused save and a failed one', async () => {
			answer('POST', '/settings/overrides', 200, {
				status: 'error',
				error: ' bad value',
			})
			await mount({ state: { confirmSaveTheme: false } })
			type(row(FONT).text, 'x')

			click(document.getElementById('nldesign-save-btn'))
			await flush()
			expect(toasts()).toContain('Failed to save overrides: bad value')

			answer('POST', '/settings/overrides', 500, new Error('offline'))
			vi.spyOn(console, 'error').mockImplementation(() => {})
			click(document.getElementById('nldesign-save-btn'))
			await flush()
			expect(toasts()).toContain('Failed to save overrides.')
			expect(document.getElementById('nldesign-save-btn').disabled).toBe(false)
		})

		it('"do not ask again" stores the flag and ticks the control under the editor', async () => {
			answer('POST', '/settings/overrides', 200, { status: 'ok' })
			await mount()

			click(document.getElementById('nldesign-save-btn'))
			tick(saveDialog().querySelector('.nldesign-dialog-dontask'), true)
			await flush()

			expect(lastBody('/settings/save-confirmations')).toEqual({
				confirmSaveStock: true,
				confirmSaveTheme: false,
			})
			expect(
				document.getElementById('nldesign-confirm-save-theme').checked,
			).toBe(true)

			click(saveDialog().querySelector('.nldesign-dialog-confirm'))
			click(document.getElementById('nldesign-save-btn'))
			await flush()
			// Not asked the second time.
			expect(saveDialog()).toBeNull()
			expect(sent('/settings/overrides', 'POST')).toHaveLength(2)
		})

		it('the controls under the editor turn the questions back on, as a pair', async () => {
			await mount({
				state: { confirmSaveStock: false, confirmSaveTheme: false },
			})
			const stock = document.getElementById('nldesign-confirm-save-stock')
			const theme = document.getElementById('nldesign-confirm-save-theme')
			expect(stock.checked).toBe(true)
			expect(theme.checked).toBe(true)

			tick(stock, false)
			expect(lastBody('/settings/save-confirmations')).toEqual({
				confirmSaveStock: true,
				confirmSaveTheme: false,
			})

			tick(theme, false)
			expect(lastBody('/settings/save-confirmations')).toEqual({
				confirmSaveStock: true,
				confirmSaveTheme: true,
			})
		})

		it('logs a confirmation setting that could not be stored', async () => {
			answer('POST', '/settings/save-confirmations', 500, new Error('offline'))
			const error = vi.spyOn(console, 'error').mockImplementation(() => {})
			await mount()

			tick(document.getElementById('nldesign-confirm-save-stock'), true)
			await flush()

			expect(error).toHaveBeenCalledWith(
				'Error saving the confirmation settings:',
				expect.any(Error),
			)
		})
	})

	describe('saving on the stock set', () => {
		/** Mount on stock with one edit, the playground exporter present, and open the dialog. */
		async function openStockDialog(options = {}) {
			await mount({ current: 'nextcloud', ...options })
			window.ThematiqPlayground = {
				liveTokens: vi.fn((tokens, read) => ({
					...tokens,
					read: read('--x', 'y'),
				})),
				exportCss: vi.fn(() => ({ css: ':root {}\n', unexpressed: [] })),
			}
			type(row(FONT).text, 'Fira Sans')
			click(document.getElementById('nldesign-save-btn'))
			return saveDialog()
		}

		/** Type a name and press "save as a new theme". */
		function saveAsNew(dialog, name) {
			document.getElementById('nldesign-save-newset-name').value = name
			click(dialog.querySelector('.nldesign-dialog-confirm'))
		}

		it('writes over the Nextcloud theme when asked to', async () => {
			answer('POST', '/settings/overrides', 200, { status: 'ok' })
			const dialog = await openStockDialog()

			click(dialog.querySelector('.nldesign-dialog-overwrite'))
			await flush()

			expect(saveDialog()).toBeNull()
			expect(lastBody('/settings/overrides').tokenSet).toBe('nextcloud')
		})

		it('does not ask when told not to', async () => {
			answer('POST', '/settings/overrides', 200, { status: 'ok' })
			await mount({ current: 'nextcloud', state: { confirmSaveStock: false } })
			type(row(FONT).text, 'Fira Sans')

			click(document.getElementById('nldesign-save-btn'))
			await flush()

			expect(saveDialog()).toBeNull()
			expect(sent('/settings/overrides', 'POST')).toHaveLength(1)
		})

		it('"do not ask again" on stock stores the stock flag', async () => {
			const dialog = await openStockDialog()

			tick(dialog.querySelector('.nldesign-dialog-dontask'), true)
			await flush()

			expect(lastBody('/settings/save-confirmations').confirmSaveStock).toBe(
				false,
			)
			expect(
				document.getElementById('nldesign-confirm-save-stock').checked,
			).toBe(true)
		})

		it('refuses a new theme without a name, in the dialog', async () => {
			const dialog = await openStockDialog()

			saveAsNew(dialog, '   ')

			const error = document.getElementById('nldesign-save-newset-error')
			expect(error.hidden).toBe(false)
			expect(error.textContent).toBe('Give the new theme a name.')
			expect(
				document
					.getElementById('nldesign-save-newset-name')
					.getAttribute('aria-invalid'),
			).toBe('true')
			expect(sent('/settings/tokensets/upload')).toHaveLength(0)
		})

		it('keeps the dialog open with the server refusal, ready for another name', async () => {
			answer('POST', '/settings/tokensets/upload', 409, {
				error: 'A token set named "OpenWoo" already exists.',
			})
			const dialog = await openStockDialog()

			saveAsNew(dialog, 'OpenWoo')
			await flush()

			expect(saveDialog()).not.toBeNull()
			expect(
				document.getElementById('nldesign-save-newset-error').textContent,
			).toBe('A token set named "OpenWoo" already exists.')
			expect(dialog.querySelector('.nldesign-dialog-confirm').disabled).toBe(
				false,
			)

			// Typing a new name and trying again clears the refusal.
			answer('POST', '/settings/tokensets/upload', 200, {
				id: 'custom-openwoo-2',
			})
			saveAsNew(dialog, 'OpenWoo 2')
			expect(
				document.getElementById('nldesign-save-newset-error').hidden,
			).toBe(true)
			expect(
				document
					.getElementById('nldesign-save-newset-name')
					.hasAttribute('aria-invalid'),
			).toBe(false)
			await flush()
			expect(saveDialog()).toBeNull()
		})

		it('says what went wrong when the refusal carries no reason', async () => {
			answer('POST', '/settings/tokensets/upload', 500, undefined)
			const dialog = await openStockDialog()

			saveAsNew(dialog, 'OpenWoo')
			await flush()

			expect(
				document.getElementById('nldesign-save-newset-error').textContent,
			).toBe('The new theme could not be created (HTTP 500).')
		})

		it('says so when the request itself fails', async () => {
			answer('POST', '/settings/tokensets/upload', 0, new Error('offline'))
			vi.spyOn(console, 'error').mockImplementation(() => {})
			const dialog = await openStockDialog()

			saveAsNew(dialog, 'OpenWoo')
			await flush()

			expect(
				document.getElementById('nldesign-save-newset-error').textContent,
			).toBe('The new theme could not be created.')
		})

		it('refuses when the exporter is not on the page', async () => {
			const dialog = await openStockDialog()
			delete window.ThematiqPlayground

			saveAsNew(dialog, 'OpenWoo')

			expect(
				document.getElementById('nldesign-save-newset-error').textContent,
			).toContain('The theme exporter is not loaded on this page')
		})

		it('creates the theme as it is, on the design system the page wears, and selects it', async () => {
			answer('POST', '/settings/tokensets/upload', 200, {
				id: 'custom-openwoo',
				theming: { captured: true },
			})
			answer('POST', COMMIT, 200, { status: 'ok' })
			const dialog = await openStockDialog({
				overrides: { [BUTTON]: '#aa0000' },
				state: {
					playgroundExportTokens: {
						'--nldesign-color-primary': '#154273',
					},
					playgroundTokenSources: {
						'--color-primary': '--nldesign-color-primary',
					},
				},
			})
			window.ThematiqPlayground.exportCss.mockReturnValue({
				css: ':root {\n  --nldesign-font-family: Fira Sans;\n}\n',
				unexpressed: ['--color-header'],
			})

			saveAsNew(dialog, 'OpenWoo')
			await flush()

			const playground = window.ThematiqPlayground
			expect(playground.liveTokens.mock.calls[0][0]).toEqual({
				'--nldesign-color-primary': '#154273',
			})
			// The saved overrides AND the unsaved edit both go into the theme.
			expect(playground.exportCss.mock.calls[0][1]).toEqual({
				[BUTTON]: '#aa0000',
				[FONT]: 'Fira Sans',
			})
			expect(playground.exportCss.mock.calls[0][2]).toEqual({
				'--color-primary': '--nldesign-color-primary',
			})
			// Marked in the file as well, so the stored theme round-trips.
			expect(playground.exportCss.mock.calls[0][3]).toBe('none')

			expect(lastBody('/settings/tokensets/upload')).toEqual({
				name: 'OpenWoo',
				content: ':root {\n  --nldesign-font-family: Fira Sans;\n}\n',
				sourceName: 'Token editor',
				raw: true,
				designSystem: 'none',
				captureTheming: true,
			})
			expect(toasts()[0]).toContain('--color-header')

			expect(saveDialog()).toBeNull()
			const select = document.getElementById('nldesign-token-set-select')
			const option = select.querySelector('option[value="custom-openwoo"]')
			expect(option.textContent).toBe('OpenWoo')
			expect(option.dataset.designSystem).toBe('none')
			expect(select.value).toBe('custom-openwoo')
			expect(lastBody(COMMIT)).toEqual({ tokenSet: 'custom-openwoo' })
			expect(toasts()).toContain(
				'Theme "OpenWoo" created and set as the active theme. Reload the page to see it.',
			)
		})

		it('says so when the new theme cannot be applied', async () => {
			answer('POST', '/settings/tokensets/upload', 200, {
				id: 'custom-openwoo',
			})
			answer('POST', COMMIT, 200, { status: 'error', error: 'nope' })
			vi.spyOn(console, 'error').mockImplementation(() => {})
			const dialog = await openStockDialog()

			saveAsNew(dialog, 'OpenWoo')
			await flush()

			expect(toasts()).toContain(
				'Theme "OpenWoo" was created but could not be applied. Pick it in the theme dropdown.',
			)
		})

		it('says so when the server names no new theme', async () => {
			answer('POST', '/settings/tokensets/upload', 200, {})
			const dialog = await openStockDialog()

			saveAsNew(dialog, 'OpenWoo')
			await flush()

			expect(toasts()).toContain(
				'Theme "OpenWoo" was created but could not be applied. Pick it in the theme dropdown.',
			)
			expect(sent(COMMIT, 'POST')).toHaveLength(0)
		})
	})

	describe('applying without a reload', () => {
		/** A layer-swap module that always succeeds. */
		function installLayerSwap(result = { ok: true }) {
			window.NldesignLayerSwap = {
				swap: vi.fn(() => Promise.resolve(result)),
				pathnameOf: (href) => String(href).split('?')[0],
				refreshStylesheets: vi.fn(),
				refreshThemeStylesheets: vi.fn(),
				bumpVersion: (href) => href,
				createLayerElement: () => document.createElement('link'),
			}
		}

		it("points the overrides link at the new theme's own file after the swap", async () => {
			installLayerSwap()
			answer('POST', '/settings/tokensets/upload', 200, {
				id: 'custom-openwoo',
			})
			answer('POST', COMMIT, 200, { status: 'ok' })
			await mount({ current: 'nextcloud' })
			window.ThematiqPlayground = {
				liveTokens: (tokens) => tokens,
				exportCss: () => ({ css: ':root {}', unexpressed: [] }),
			}
			type(row(FONT).text, 'Fira Sans')
			click(document.getElementById('nldesign-save-btn'))
			document.getElementById('nldesign-save-newset-name').value = 'OpenWoo'
			click(saveDialog().querySelector('.nldesign-dialog-confirm'))
			await flush()

			expect(window.NldesignLayerSwap.swap).toHaveBeenCalled()
			expect(
				document
					.querySelector('link[href*="custom-overrides"]')
					.getAttribute('href'),
			).toMatch(
				/^\/apps\/thematiq\/css\/custom-overrides-custom-openwoo\.css\?v=\d+$/,
			)
			expect(toasts()).toContain('Theme "OpenWoo" created and applied.')
		})

		it('leaves the link alone when it already points at the right file, and refreshes it after a save', async () => {
			installLayerSwap()
			answer('POST', '/settings/overrides', 200, { status: 'ok' })
			answer('POST', COMMIT, 200, { status: 'ok' })
			await mount({ state: { confirmSaveTheme: false } })
			const select = document.getElementById('nldesign-token-set-select')
			select.value = 'rijkshuisstijl'
			// A preview answer with nothing to show applies straight away.
			answer('GET', 'tokenset-preview', 200, { error: 'none' })
			select.dispatchEvent(new window.Event('change', { bubbles: true }))
			await flush()

			expect(
				document
					.querySelector('link[href*="custom-overrides"]')
					.getAttribute('href'),
			).toBe('/apps/thematiq/css/custom-overrides.css?v=1')

			type(row(FONT).text, 'x')
			click(document.getElementById('nldesign-save-btn'))
			await flush()
			expect(window.NldesignLayerSwap.refreshStylesheets).toHaveBeenCalled()
		})

		it('does without an overrides link on the page', async () => {
			installLayerSwap()
			answer('POST', COMMIT, 200, { status: 'ok' })
			answer('GET', 'tokenset-preview', 200, { error: 'none' })
			await mount()
			document.head.innerHTML = ''
			const select = document.getElementById('nldesign-token-set-select')

			select.value = 'nextcloud'
			select.dispatchEvent(new window.Event('change', { bubbles: true }))
			await flush()

			expect(window.NldesignLayerSwap.swap).toHaveBeenCalled()
			expect(toasts()).toContain('Applied.')
		})
	})

	describe('switching to another set', () => {
		it('offers what was saved for the set, not what its file says', async () => {
			await mount({ current: 'nextcloud' })

			await switchTo(
				'rijkshuisstijl',
				{ [FONT]: 'Arial', '--color-main-background': '#ffffff' },
				{ overrides: { [FONT]: 'Fira Sans', '--not-in-file': '#000000' } },
			)

			expect(sent('overrides?tokenSet=rijkshuisstijl', 'GET')).toHaveLength(1)
			// A saved value the file does not carry is not a change to offer.
			expect(applyRows()).toEqual({
				[FONT]: 'Fira Sans',
				'--color-main-background': '#ffffff',
			})
		})

		it('warns in the apply dialog that a running switch will put its set back', async () => {
			answer('GET', '/settings/scheduled-switches', 200, {
				switches: [],
				status: {
					runningTokenSet: 'nextcloud',
					activeUntil: '2027-04-28T06:00:00Z',
				},
			})
			await mount({
				current: 'nextcloud',
				extraHtml:
					'<form id="nldesign-scheduled-form"></form><ul id="nldesign-scheduled-list"></ul>',
			})

			await switchTo('rijkshuisstijl', { [FONT]: 'Arial' }, { overrides: {} })

			const warning = document.querySelector(
				'#nldesign-apply-dialog-overlay .nldesign-apply-switch-warning',
			)
			expect(warning.textContent).toContain(
				'A planned switch to Nextcloud (Base) is running until',
			)
		})

		it('offers the file when what was saved cannot be read', async () => {
			await mount({ current: 'nextcloud' })

			await switchTo(
				'rijkshuisstijl',
				{ [FONT]: 'Arial' },
				new Error('offline'),
			)

			expect(applyRows()).toEqual({ [FONT]: 'Arial' })
		})

		it('offers the file when nothing was saved for the set', async () => {
			await mount({ current: 'nextcloud' })

			await switchTo('rijkshuisstijl', { [FONT]: 'Arial' }, { status: 'ok' })

			expect(applyRows()).toEqual({ [FONT]: 'Arial' })
		})

		it('leaves the primary family to Nextcloud when the set brings its own primary colour', async () => {
			await mount({ current: 'nextcloud' })

			await switchTo(
				'rijkshuisstijl',
				{
					'--color-primary': '#154273',
					[BASE]: '#154273',
					[FONT]: 'Arial',
				},
				{ overrides: {} },
			)

			expect(applyRows()).toEqual({ [FONT]: 'Arial' })
		})

		it('keeps the primary family of the stock set, and asks nothing saved for it', async () => {
			await mount()
			const before = sent('overrides?tokenSet=nextcloud', 'GET').length

			await switchTo('nextcloud', { '--color-primary': '#00679e' })

			expect(applyRows()).toEqual({ '--color-primary': '#00679e' })
			expect(sent('overrides?tokenSet=nextcloud', 'GET')).toHaveLength(before)
		})

		it('applies straight away when the file resolves to nothing', async () => {
			answer('POST', COMMIT, 200, { status: 'ok' })
			await mount({ current: 'nextcloud' })

			await switchTo('rijkshuisstijl', undefined, { overrides: {} })

			expect(
				document.getElementById('nldesign-apply-dialog-overlay'),
			).toBeNull()
			expect(sent(COMMIT, 'POST')).toHaveLength(1)
		})

		it('compares a set the page has no details for against what was saved for it', async () => {
			await mount({ current: 'nextcloud' })
			const option = document.createElement('option')
			option.value = 'custom-unknown'
			document.getElementById('nldesign-token-set-select').appendChild(option)

			await switchTo(
				'custom-unknown',
				{ '--color-primary': '#123456', [FONT]: 'Arial' },
				{ overrides: { [FONT]: 'Fira Sans' } },
			)

			// No theming to bring a primary, so the family stays in the diff.
			expect(applyRows()).toEqual({
				'--color-primary': '#123456',
				[FONT]: 'Fira Sans',
			})
		})
	})

	describe('without the token set dropdown', () => {
		it('works from the set the page was rendered on', async () => {
			answer('POST', '/settings/overrides', 200, {
				status: 'ok',
				theming: { captured: true },
			})
			answer('POST', '/settings/tokensets/upload', 200, {})
			await mount({
				current: 'nextcloud',
				withSelect: false,
				state: { tokenSets: [] },
			})
			window.ThematiqPlayground = {
				liveTokens: (tokens) => tokens,
				exportCss: () => ({ css: ':root {}', unexpressed: [] }),
			}

			// A plain colour row writes the picker's hex as it is.
			type(row(BUTTON).picker, '#123456')
			expect(row(BUTTON).text.value).toBe('#123456')

			// Still the stock set, read off the page, so still the stock question.
			click(document.getElementById('nldesign-save-btn'))
			click(saveDialog().querySelector('.nldesign-dialog-overwrite'))
			await flush()
			expect(lastBody('/settings/overrides').tokenSet).toBe('nextcloud')

			click(document.getElementById('nldesign-save-btn'))
			document.getElementById('nldesign-save-newset-name').value = 'OpenWoo'
			click(saveDialog().querySelector('.nldesign-dialog-confirm'))
			await flush()
			expect(lastBody('/settings/tokensets/upload').designSystem).toBe(
				'nldesign',
			)
		})
	})

	describe('download and upload', () => {
		/** Stand in for the browser's save: record the file name offered. */
		function catchDownload() {
			const saved = vi.fn()
			URL.createObjectURL = vi.fn(() => 'blob:overrides')
			URL.revokeObjectURL = vi.fn()
			vi.spyOn(window.HTMLAnchorElement.prototype, 'click').mockImplementation(
				function () {
					saved(this.download)
				},
			)
			return saved
		}

		it('says what Download holds before downloading, and Cancel downloads nothing', async () => {
			await mount({ current: 'nextcloud' })
			const saved = catchDownload()

			click(document.getElementById('nldesign-export-btn'))
			const overlay = document.getElementById(
				'nldesign-export-overrides-overlay',
			)
			expect(overlay.querySelector('h3').textContent).toBe(
				'Download the overrides of Nextcloud (Base)?',
			)
			expect(overlay.textContent).toContain('It is not a complete theme.')

			click(overlay.querySelector('.nldesign-dialog-cancel'))
			await flush()

			expect(
				document.getElementById('nldesign-export-overrides-overlay'),
			).toBeNull()
			expect(sent('/settings/overrides/export')).toHaveLength(0)
			expect(saved).not.toHaveBeenCalled()
		})

		it("downloads the edited set's own file, with the request token", async () => {
			answer('GET', '/settings/overrides/export', 200, ':root {}')
			await mount({ current: 'nextcloud' })
			const saved = catchDownload()

			click(document.getElementById('nldesign-export-btn'))
			click(
				document
					.getElementById('nldesign-export-overrides-overlay')
					.querySelector('.nldesign-dialog-confirm'),
			)
			await flush()

			const request = sent('/settings/overrides/export', 'GET')[0]
			expect(request.url).toBe(
				'/apps/thematiq/settings/overrides/export?tokenSet=nextcloud',
			)
			expect(request.headers).toEqual({ requesttoken: 'test-token' })
			expect(saved).toHaveBeenCalledWith('custom-overrides.css')
		})

		it('says so when the download is refused', async () => {
			answer('GET', '/settings/overrides/export', 412, {})
			await mount({ current: 'nextcloud' })
			const saved = catchDownload()
			vi.spyOn(console, 'error').mockImplementation(() => {})

			click(document.getElementById('nldesign-export-btn'))
			click(
				document
					.getElementById('nldesign-export-overrides-overlay')
					.querySelector('.nldesign-dialog-confirm'),
			)
			await flush()

			expect(saved).not.toHaveBeenCalled()
			expect(toasts()).toContain('The overrides could not be downloaded.')
		})

		it('says what Upload replaces before the file picker opens, and Cancel opens none', async () => {
			await mount({ current: 'nextcloud' })
			const input = document.getElementById('nldesign-import-input')
			const picker = vi.spyOn(input, 'click').mockImplementation(() => {})

			click(document.getElementById('nldesign-import-btn'))
			const overlay = document.getElementById(
				'nldesign-import-overrides-overlay',
			)
			expect(overlay.querySelector('h3').textContent).toBe(
				'Upload overrides into Nextcloud (Base)?',
			)
			expect(picker).not.toHaveBeenCalled()

			click(overlay.querySelector('.nldesign-dialog-cancel'))
			expect(picker).not.toHaveBeenCalled()

			click(document.getElementById('nldesign-import-btn'))
			click(
				document
					.getElementById('nldesign-import-overrides-overlay')
					.querySelector('.nldesign-dialog-confirm'),
			)
			expect(picker).toHaveBeenCalledTimes(1)
		})

		it("uploads into the edited set's own file", async () => {
			await mount({ current: 'nextcloud' })

			const input = document.getElementById('nldesign-import-input')
			Object.defineProperty(input, 'files', {
				value: [new window.File([':root {}'], 'x.css')],
				configurable: true,
			})
			input.dispatchEvent(new window.Event('change', { bubbles: true }))
			await flush()
			expect(
				sent('/settings/overrides/import?tokenSet=nextcloud', 'POST'),
			).toHaveLength(1)
		})
	})

	describe('reset theme to Nextcloud', () => {
		/** Press the button and answer the confirmation. */
		async function pressReset(confirmed) {
			OC.dialogs.confirm.mockImplementation((text, title, callback) =>
				callback(confirmed),
			)
			click(document.getElementById('nldesign-reset-theme-btn'))
			await flush()
		}

		it('does nothing when the admin backs out', async () => {
			await mount()

			await pressReset(false)

			expect(sent('/settings/overrides', 'POST')).toHaveLength(0)
		})

		it('asks the server to reset once confirmed', async () => {
			answer('POST', '/settings/overrides', 200, { status: 'ok' })
			// jsdom has no navigation; the reload is only reported.
			vi.spyOn(console, 'error').mockImplementation(() => {})
			await mount()

			await pressReset(true)

			expect(lastBody('/settings/overrides')).toEqual({ reset: true })
			expect(toasts()).not.toContain('The theme could not be reset.')
		})

		it('gives the button back and says so when the reset fails', async () => {
			answer('POST', '/settings/overrides', 200, { status: 'error' })
			vi.spyOn(console, 'error').mockImplementation(() => {})
			await mount()

			await pressReset(true)

			expect(
				document.getElementById('nldesign-reset-theme-btn').disabled,
			).toBe(false)
			expect(toasts()).toContain('The theme could not be reset.')
		})
	})
})

describe('admin.js theming sync rows', () => {
	beforeEach(() => {
		requests = []
		routes = []
		installGlobals()
		installFetch()
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	/**
	 * Switch to a set with the given theming while core holds `current`, and
	 * return the sync dialog that opens (or null).
	 */
	async function switchTo(theming, current) {
		const sets = [
			{ id: 'rijkshuisstijl', name: 'Rijkshuisstijl', theming: {} },
			{ id: 'custom-openwoo', name: 'OpenWoo', theming },
		]
		installInitialState({ tokenSets: sets, currentTokenSet: 'rijkshuisstijl' })
		document.body.innerHTML = `
			<select id="nldesign-token-set-select">
				${sets.map((ts) => `<option value="${ts.id}">${ts.name}</option>`).join('')}
			</select>
		`
		answer('GET', 'tokenset-preview', 200, { error: 'none' })
		answer('POST', COMMIT, 200, { status: 'ok' })
		answer('GET', '/settings/theming', 200, current)
		answer('POST', '/settings/theming', 200, { status: 'error', error: ' no' })

		vi.resetModules()
		await import('../../js/admin.js?t=' + Math.random())
		await flush()

		const select = document.getElementById('nldesign-token-set-select')
		select.value = 'custom-openwoo'
		select.dispatchEvent(new window.Event('change', { bubbles: true }))
		await flush()

		return document.getElementById('nldesign-theming-dialog-overlay')
	}

	/** The row labels of the sync dialog. */
	function labels(overlay) {
		return [...overlay.querySelectorAll('tbody tr td:first-child')].map(
			(td) => td.textContent,
		)
	}

	/** Confirm the dialog and return the form body it posted. */
	async function confirm(overlay) {
		click(overlay.querySelector('.nldesign-dialog-confirm'))
		await flush()
		const posted = sent('/settings/theming', 'POST')
		return new URLSearchParams(posted[posted.length - 1].body)
	}

	it('offers the navigation logo, favicon and background image a theme captured', async () => {
		const overlay = await switchTo(
			{
				logoheader: 'img/logos/custom-openwoo-captured-logoheader.png',
				favicon: 'img/logos/custom-openwoo-captured-favicon.ico',
				background: 'img/backgrounds/custom-openwoo-captured-background.jpg',
				background_mode: 'image',
			},
			{ has_custom_favicon: true, synced_favicon: 'img/logos/other.ico' },
		)

		expect(labels(overlay)).toEqual([
			'Navigation bar logo',
			'Favicon',
			'Background image',
		])
		expect(overlay.textContent).toContain(
			'custom-openwoo-captured-logoheader.png',
		)
		expect(overlay.textContent).toContain('(custom)')

		const body = await confirm(overlay)
		expect(body.get('logoheader')).toBe(
			'img/logos/custom-openwoo-captured-logoheader.png',
		)
		expect(body.get('favicon')).toBe(
			'img/logos/custom-openwoo-captured-favicon.ico',
		)
		expect(body.get('background')).toBe(
			'img/backgrounds/custom-openwoo-captured-background.jpg',
		)
		expect(body.get('background_mode')).toBe('image')
		expect(toasts()).toContain('Failed to update Nextcloud theming: no')
	})

	it('does not offer images the slots already hold', async () => {
		const overlay = await switchTo(
			{
				primary_color: '#154273',
				favicon: 'img/logos/f.ico',
				background: 'img/backgrounds/b.jpg',
			},
			{
				primary_color: '#000000',
				has_custom_favicon: true,
				synced_favicon: 'img/logos/f.ico',
				has_custom_background: true,
				synced_background: 'img/backgrounds/b.jpg',
			},
		)

		expect(labels(overlay)).toEqual(['Primary color'])
	})

	it('offers removing the background image when the theme was saved without one', async () => {
		const overlay = await switchTo(
			{ captured: true, background_mode: 'color' },
			{ has_custom_background: true },
		)

		expect(labels(overlay)).toEqual(['Background image'])
		expect(overlay.textContent).toContain('Removed (plain color)')

		const body = await confirm(overlay)
		expect(body.get('background_mode')).toBe('color')
	})

	it("offers Nextcloud's own background back, from a removed image or a custom one", async () => {
		let overlay = await switchTo(
			{ captured: true, background_mode: 'default' },
			{ background_mime: 'backgroundColor' },
		)
		expect(labels(overlay)).toEqual(['Background image'])
		expect(overlay.textContent).toContain('Nextcloud default')
		expect((await confirm(overlay)).get('background_mode')).toBe('default')

		document.body.innerHTML = ''
		overlay = await switchTo(
			{ captured: true, background_mode: 'default' },
			{ has_custom_background: true },
		)
		expect(labels(overlay)).toEqual(['Background image'])
	})

	it('offers nothing when the page already has the saved background state', async () => {
		const overlay = await switchTo(
			{ captured: true, background_mode: 'color' },
			{ background_mime: 'backgroundColor' },
		)

		expect(overlay).toBeNull()
	})
})
