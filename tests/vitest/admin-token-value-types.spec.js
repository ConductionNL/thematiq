/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin.js: typed values in the token editor. Transparency on colour
 * rows, a dark value per colour, and duration and easing rows with a motion preview.
 * Harness copied from admin-token-editor.spec.js.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/token-editor-ui/spec.md#requirement-colour-fields-accept-transparency
 * @spec openspec/specs/token-editor-ui/spec.md#requirement-each-colour-token-has-an-optional-dark-value
 * @spec openspec/specs/token-editor-ui/spec.md#requirement-motion-tokens-are-typed-and-reach-every-transition
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const BASE = '--color-primary-element'
const QUICK = '--animation-quick'
const EASING = '--nldesign-animation-easing'
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
	[QUICK]: {
		tab: 'content',
		type: 'duration',
		label: 'Animation quick',
		group: 'brand',
	},
	[EASING]: {
		tab: 'content',
		type: 'easing',
		label: 'Animation easing',
		group: 'brand',
	},
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
		requests.push({ url, method, body: options.body })
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
 */
async function mount({
	current = 'rijkshuisstijl',
	overrides = {},
	state = {},
	primaryDrives = false,
	withSelect = true,
	darkOverrides = {},
	darkDerived = {},
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
		</div>
		<div class="nldesign-preview" id="nldesign-preview"></div>
		<div id="nldesign-token-editor"></div>
	`
	if (withSelect === false) {
		document.getElementById('nldesign-token-set-select').remove()
	}
	answer('GET', '/settings/overrides?', 200, {
		overrides,
		darkOverrides,
		darkDerived,
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

/** The JSON body of the last request to a URL substring. */
function lastBody(match, method = 'POST') {
	const matching = sent(match, method)
	return JSON.parse(matching[matching.length - 1].body)
}

function save() {
	click(document.getElementById('nldesign-save-btn'))
	click(saveDialog().querySelector('.nldesign-dialog-confirm'))
}

describe('admin.js typed token values', () => {
	beforeEach(() => {
		requests = []
		routes = []
		installGlobals()
		installFetch()
		window.matchMedia = vi.fn(() => ({
			matches: false,
			addEventListener: vi.fn(),
		}))
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	describe('transparency', () => {
		it('lets an administrator make a colour half transparent', async () => {
			await mount()
			const r = row(BASE)
			type(r.text, '#154273')
			const alpha = r.el.querySelector('.nldesign-color-alpha')
			expect(alpha.getAttribute('aria-label')).toBe('Opacity of Primary')

			type(alpha, '50')

			expect(r.text.value).toBe('#15427380')
			expect(r.el.querySelector('.nldesign-color-alpha-number').value).toBe(
				'50',
			)
		})

		it('keeps the alpha when the picker moves', async () => {
			await mount()
			const r = row(BASE)
			type(r.text, '#15427380')
			type(r.picker, '#aa0000')

			expect(r.text.value).toBe('#aa000080')
		})

		it('updates the picker and the opacity from a typed 8-digit hex', async () => {
			await mount()
			const r = row(BASE)
			type(r.text, '#15427340')

			expect(r.picker.value).toBe('#154273')
			expect(r.el.querySelector('.nldesign-color-alpha').value).toBe('25')
		})
	})

	describe('dark values', () => {
		it('shows the derived dark value and saves an own one', async () => {
			answer('POST', '/settings/overrides', 200, { status: 'ok' })
			await mount({
				overrides: { [BASE]: '#154273' },
				darkDerived: { [BASE]: '#6c95c4' },
			})
			const dark = row(BASE).el.querySelector('.nldesign-dark-text')
			expect(dark.getAttribute('placeholder')).toBe('#6c95c4')
			expect(dark.getAttribute('aria-label')).toBe('Dark value of Primary')

			type(dark, '#5b9bd5')
			save()
			await flush()

			const body = lastBody('/settings/overrides')
			expect(body.darkOverrides).toEqual({ [BASE]: '#5b9bd5' })
			expect(body.overrides[BASE]).toBe('#154273')
		})

		it('loads an own dark value into the field', async () => {
			await mount({
				overrides: { [BASE]: '#154273' },
				darkOverrides: { [BASE]: '#5b9bd5' },
			})

			expect(row(BASE).el.querySelector('.nldesign-dark-text').value).toBe(
				'#5b9bd5',
			)
		})
	})

	describe('motion', () => {
		it('saves a duration from a number and a unit', async () => {
			answer('POST', '/settings/overrides', 200, { status: 'ok' })
			await mount()
			const el = row(QUICK).el
			type(el.querySelector('.nldesign-duration-number'), '150')
			const unit = el.querySelector('.nldesign-duration-unit')
			unit.value = 'ms'
			unit.dispatchEvent(new window.Event('change', { bubbles: true }))
			save()
			await flush()

			expect(lastBody('/settings/overrides').overrides[QUICK]).toBe('150ms')
		})

		it('builds a custom easing curve from four numbers', async () => {
			answer('POST', '/settings/overrides', 200, { status: 'ok' })
			await mount()
			const el = row(EASING).el
			const select = el.querySelector('.nldesign-easing-select')
			expect(select.getAttribute('aria-label')).toBe('Animation easing')
			select.value = 'custom'
			select.dispatchEvent(new window.Event('change', { bubbles: true }))
			const fields = el.querySelectorAll('.nldesign-easing-point')
			expect(fields).toHaveLength(4)
			;['0.2', '0', '0', '1'].forEach((v, i) => type(fields[i], v))
			save()
			await flush()

			expect(lastBody('/settings/overrides').overrides[EASING]).toBe(
				'cubic-bezier(0.2, 0, 0, 1)',
			)
		})

		it('keeps the preview still and says the values under reduced motion', async () => {
			window.matchMedia = vi.fn((q) => ({
				matches: q.indexOf('reduce') !== -1,
				addEventListener: vi.fn(),
			}))
			await mount({ overrides: { [QUICK]: '400ms' } })
			click(document.querySelector('.nldesign-motion-play'))

			const block = document.querySelector('.nldesign-motion-block')
			expect(block.classList.contains('nldesign-motion-block--moved')).toBe(
				false,
			)
			expect(
				document.querySelector('.nldesign-motion-readout').textContent,
			).toContain('400ms')
		})
	})
})
