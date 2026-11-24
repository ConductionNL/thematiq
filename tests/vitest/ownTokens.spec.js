/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/ownTokens.js over the real markup from templates/settings/admin.php:
 * the own tokens list, the add dialog, removal with its question, the deprecations list
 * with the notice-only label, the badge on token editor rows, and focus handling.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/own-tokens/spec.md#requirement-an-administrator-edits-and-removes-own-tokens
 * @spec openspec/specs/token-deprecations/spec.md#requirement-a-passed-removal-date-is-flagged-never-acted-on
 */

import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../..')

/** The section and both dialogs, cut from the template, PHP calls replaced by their text. */
function templateMarkup() {
	const php = readFileSync(resolve(ROOT, 'templates/settings/admin.php'), 'utf8')
	const start = php.lastIndexOf('<section', php.indexOf('data-thematiq-panel="own-tokens"'))
	const end =
		php.indexOf('</dialog>', php.indexOf('id="nldesign-deprecation-dialog"')) + 9
	return php
		.slice(start, end)
		.replace(/<\?php p\(\$l->t\('((?:[^'\\]|\\.)*)'\)\); \?>/g, (m, text) =>
			text.replace(/\\'/g, "'"),
		)
}

let requests = []
let routes = []

function answer(method, match, status, body) {
	routes.unshift([method, match, status, body])
}

function sent(match, method) {
	return requests.filter(
		(r) => r.url.indexOf(match) !== -1 && (!method || r.method === method),
	)
}

async function flush(rounds = 10) {
	for (let i = 0; i < rounds; i++) {
		await new Promise((r) => setTimeout(r, 0))
	}
}

const TOKENS = [
	{
		name: '--nldesign-org-old-accent',
		label: 'Old accent',
		type: 'color',
		value: '#aa0000',
		deprecation: {
			severity: 'warning',
			replacement: '--nldesign-org-brand-accent',
			removalDate: '2026-10-01',
			due: true,
			state: 'active',
		},
	},
	{
		name: '--nldesign-org-brand-accent',
		label: 'Brand accent',
		type: 'color',
		value: '#e17000',
		darkValue: '#ff9a3c',
	},
]

const DEPRECATIONS = [
	{
		token: '--nldesign-org-old-accent',
		severity: 'warning',
		replacement: '--nldesign-org-brand-accent',
		removalDate: '2026-10-01',
		message: null,
		due: true,
		state: 'active',
		own: true,
	},
	{
		token: '--nldesign-color-primary-light',
		severity: 'info',
		replacement: null,
		removalDate: null,
		message: null,
		due: false,
		state: 'active',
		own: false,
	},
]

async function mount({ tokens = TOKENS, deprecations = DEPRECATIONS } = {}) {
	document.body.innerHTML =
		'<div id="nldesign-token-editor"><div data-token-row="--nldesign-color-primary-light"><span class="nldesign-token-label-wrap"></span></div></div>'
		+ templateMarkup()
	document.querySelector('[data-thematiq-panel="own-tokens"]').hidden = false
	answer('GET', '/settings/tokens/own', 200, {
		tokens,
		prefix: '--nldesign-org-',
	})
	answer('GET', '/settings/tokens/deprecations', 200, { deprecations })
	vi.resetModules()
	await import('../../js/ownTokens.js?t=' + Math.random())
	await flush()
}

function click(node) {
	node.dispatchEvent(new window.MouseEvent('click', { bubbles: true }))
}

describe('ownTokens.js', () => {
	beforeEach(() => {
		requests = []
		routes = []
		global.t = (app, text, params) =>
			params === undefined
				? text
				: Object.keys(params).reduce(
						(acc, key) => acc.replace('{' + key + '}', params[key]),
						text,
					)
		global.OC = { generateUrl: (u) => u, requestToken: 'tok' }
		global.fetch = vi.fn((url, options = {}) => {
			const method = options.method || 'GET'
			requests.push({ url, method, body: options.body })
			for (const [m, match, status, body] of routes) {
				if (m === method && url.indexOf(match) !== -1) {
					return Promise.resolve({
						ok: status < 400,
						status,
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
		// jsdom has no modal dialogs; stand in with the open attribute.
		window.HTMLDialogElement.prototype.showModal = function () {
			this.setAttribute('open', '')
		}
		window.HTMLDialogElement.prototype.close = function () {
			this.removeAttribute('open')
		}
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('lists own tokens with both values and a badge that says the severity and due date in words', async () => {
		await mount()
		const rows = document.querySelectorAll('.nldesign-own-token-row')
		expect(rows).toHaveLength(2)
		expect(rows[1].querySelector('.nldesign-own-token-value').textContent).toBe('light #e17000dark #ff9a3c')
		expect(
			rows[0].querySelector('.nldesign-deprecation-badge').textContent,
		).toBe('Deprecated: warning, Due for removal')
		expect(
			rows[0].querySelector(
				'button[aria-label="Remove --nldesign-org-old-accent"]',
			),
		).not.toBeNull()
	})

	it('adds a brand accent colour through the dialog and returns focus to the button', async () => {
		answer('POST', '/settings/tokens/own', 200, { status: 'ok' })
		await mount({ tokens: [] })
		const add = document.getElementById('nldesign-own-token-add')
		add.focus()
		click(add)
		const dialog = document.getElementById('nldesign-own-token-dialog')
		expect(dialog.hasAttribute('open')).toBe(true)
		expect(document.activeElement.id).toBe('nldesign-own-token-slug')

		const form = dialog.querySelector('form')
		form.elements.slug.value = 'brand-accent'
		form.elements.label.value = 'Brand accent'
		form.elements.value.value = '#e17000'
		form.elements.darkValue.value = '#ff9a3c'
		form.dispatchEvent(new window.Event('submit', { cancelable: true }))
		await flush()

		const body = JSON.parse(sent('/settings/tokens/own', 'POST')[0].body)
		expect(body).toMatchObject({
			slug: 'brand-accent',
			label: 'Brand accent',
			type: 'color',
			value: '#e17000',
			darkValue: '#ff9a3c',
		})
		expect(dialog.hasAttribute('open')).toBe(false)
		expect(document.activeElement).toBe(add)
	})

	it('draws the board: the usage line follows the name, the colour hint follows the type, both close buttons close', async () => {
		await mount({ tokens: [] })
		const add = document.getElementById('nldesign-own-token-add')
		add.focus()
		click(add)
		const dialog = document.getElementById('nldesign-own-token-dialog')
		expect(dialog.querySelector('.thematiq-admin-dialog__eyebrow').textContent).toBe('Own tokens')
		const form = dialog.querySelector('form')
		form.elements.slug.value = 'footer-surface'
		form.elements.slug.dispatchEvent(new window.Event('input'))
		expect(dialog.querySelector('.nldesign-own-token-usage').textContent).toBe('background: var(--nldesign-org-footer-surface);')

		form.elements.type.value = 'text'
		form.elements.type.dispatchEvent(new window.Event('change'))
		expect(dialog.querySelector('.nldesign-own-token-colour-hint').hidden).toBe(true)

		click(dialog.querySelector('.thematiq-admin-dialog__close'))
		expect(dialog.hasAttribute('open')).toBe(false)
		expect(document.activeElement).toBe(add)
	})

	it('keeps the dialog open and shows the server text on a refusal', async () => {
		answer('POST', '/settings/tokens/own', 400, {
			error: 'Use lowercase letters, digits and single dashes for the name, at most 48 characters.',
			field: 'name',
		})
		await mount({ tokens: [] })
		click(document.getElementById('nldesign-own-token-add'))
		const dialog = document.getElementById('nldesign-own-token-dialog')
		const form = dialog.querySelector('form')
		form.elements.slug.value = 'Brand_Accent'
		form.elements.label.value = 'x'
		form.elements.value.value = '#000000'
		form.dispatchEvent(new window.Event('submit', { cancelable: true }))
		await flush()

		expect(dialog.hasAttribute('open')).toBe(true)
		expect(dialog.querySelector('.nldesign-dialog-error').textContent).toContain(
			'lowercase letters',
		)
		expect(
			dialog.querySelector('.nldesign-dialog-error').getAttribute('role'),
		).toBe('alert')
	})

	it('hides the dark value for a type that is not a colour', async () => {
		await mount({ tokens: [] })
		click(document.getElementById('nldesign-own-token-add'))
		const dialog = document.getElementById('nldesign-own-token-dialog')
		const select = dialog.querySelector('select[name="type"]')
		select.value = 'duration'
		select.dispatchEvent(new window.Event('change'))

		expect(dialog.querySelector('.nldesign-own-token-dark').hidden).toBe(true)
	})

	it('names the deprecation in the removal question, and removes on yes', async () => {
		answer('DELETE', '/settings/tokens/own/', 200, { status: 'ok' })
		const confirm = vi.spyOn(window, 'confirm').mockReturnValue(true)
		await mount()
		click(
			document.querySelector(
				'button[aria-label="Remove --nldesign-org-old-accent"]',
			),
		)
		await flush()

		expect(confirm.mock.calls[0][0]).toContain('It is deprecated (warning)')
		expect(
			sent('/settings/tokens/own/--nldesign-org-old-accent', 'DELETE'),
		).toHaveLength(1)
	})

	it('removes nothing when the question is answered no', async () => {
		vi.spyOn(window, 'confirm').mockReturnValue(false)
		await mount()
		click(
			document.querySelector(
				'button[aria-label="Remove --nldesign-org-brand-accent"]',
			),
		)
		await flush()

		expect(sent('/settings/tokens/own/', 'DELETE')).toHaveLength(0)
	})

	it('says when a deprecated token goes, or that it has no date, and names a colour token by its kind and shades', async () => {
		await mount()
		expect(document.querySelector('[data-deprecated-token="--nldesign-org-old-accent"] .nldesign-deprecation-when').textContent).toBe('removed from 2026-10-01')
		expect(document.querySelector('[data-deprecated-token="--nldesign-color-primary-light"] .nldesign-deprecation-when').textContent).toBe('no date')
		const row = document.querySelector('[data-own-token="--nldesign-org-old-accent"]')
		expect(row.children[1].textContent).toBe('Colour')
		expect(row.querySelector('.nldesign-own-token-value').textContent).toContain('dark derived from light')
	})

	it('labels a shipped token as notice only and offers no remove for it', async () => {
		await mount()
		const row = document.querySelector(
			'[data-deprecated-token="--nldesign-color-primary-light"]',
		)
		expect(row.textContent).toContain(
			'Notice only: the value comes from the token set',
		)
		expect(row.textContent).not.toContain('Remove')
	})

	it('deprecates an own token with a replacement and a date', async () => {
		answer('POST', '/settings/tokens/deprecations', 200, { status: 'ok' })
		await mount()
		click(
			document.querySelector(
				'button[aria-label="Deprecate --nldesign-org-brand-accent"]',
			),
		)
		const form = document.querySelector('#nldesign-deprecation-dialog form')
		expect(form.elements.token.value).toBe('--nldesign-org-brand-accent')
		expect(form.elements.token.disabled).toBe(true)
		form.elements.severity.value = 'critical'
		form.elements.replacement.value = '--nldesign-org-old-accent'
		form.elements.removalDate.value = '2027-03-01'
		form.dispatchEvent(new window.Event('submit', { cancelable: true }))
		await flush()

		expect(
			JSON.parse(sent('/settings/tokens/deprecations', 'POST')[0].body),
		).toMatchObject({
			token: '--nldesign-org-brand-accent',
			severity: 'critical',
			replacement: '--nldesign-org-old-accent',
			removalDate: '2027-03-01',
		})
	})

	it('badges a deprecated row of the token editor', async () => {
		await mount()
		const row = document.querySelector(
			'[data-token-row="--nldesign-color-primary-light"]',
		)
		expect(row.querySelector('.nldesign-deprecation-badge').textContent).toBe(
			'Deprecated: info',
		)
	})

	it('reloads when an upload records its notices', async () => {
		await mount()
		const before = sent('/settings/tokens/deprecations', 'GET').length
		document.dispatchEvent(new CustomEvent('thematiq:deprecations-changed'))
		await flush()

		// Earlier mounts in this file left their listeners on the shared document, so count up, not exactly.
		expect(sent('/settings/tokens/deprecations', 'GET').length).toBeGreaterThan(
			before,
		)
	})

	it('shows the contrast of an own colour against the page background', async () => {
		window.NldesignTokenConverter =
			await import('../../js/lib/tokenConverter.js')
		await mount({
			tokens: [
				{
					name: '--nldesign-org-sun',
					label: 'Sun',
					type: 'color',
					value: '#ffff00',
				},
				{
					name: '--nldesign-org-ink',
					label: 'Ink',
					type: 'color',
					value: '#154273',
				},
			],
		})
		const hints = [
			...document.querySelectorAll('.nldesign-own-token-contrast'),
		].map((n) => n.textContent)
		delete window.NldesignTokenConverter

		expect(hints[0]).toBe('Contrast 1.07:1 on the page background, below 3:1.')
		expect(hints[1]).toMatch(/^Contrast [0-9.]+:1 on the page background\.$/)
	})

	it('gives every dialog field a visible label', async () => {
		await mount()
		document
			.querySelectorAll(
				'.nldesign-own-dialog input, .nldesign-own-dialog select',
			)
			.forEach((field) => {
				const label = document.querySelector('label[for="' + field.id + '"]')
				expect(label, field.name).not.toBeNull()
				expect(label.classList.contains('hidden-visually')).toBe(false)
			})
	})

	it('edits a token in its prefilled dialog with the name locked, and saves it with PUT', async () => {
		answer('PUT', '/settings/tokens/own/', 200, { status: 'ok' })
		await mount()
		const edit = document.querySelector('button[aria-label="Edit --nldesign-org-brand-accent"]')
		click(edit)
		const dialog = document.getElementById('nldesign-own-token-dialog')
		const form = dialog.querySelector('form')
		expect(dialog.querySelector('.nldesign-dialog-title').textContent).toBe('Edit --nldesign-org-brand-accent')
		expect(form.elements.slug.value).toBe('brand-accent')
		expect(form.elements.slug.disabled).toBe(true)
		expect(form.elements.darkValue.value).toBe('#ff9a3c')
		expect(form.elements.description.value).toBe('')

		form.elements.value.value = '#00689e'
		form.dispatchEvent(new window.Event('submit', { cancelable: true }))
		await flush()

		const [put] = sent('/settings/tokens/own/--nldesign-org-brand-accent', 'PUT')
		expect(JSON.parse(put.body)).toMatchObject({ value: '#00689e', darkValue: '#ff9a3c' })
		expect(document.getElementById('nldesign-own-tokens-status').textContent).toBe('Token saved.')
		// WCAG 2.4.3: the redraw replaced the Edit button; the focus goes to the new one.
		expect(edit.isConnected).toBe(false)
		expect(document.activeElement).toBe(document.querySelector('button[aria-label="Edit --nldesign-org-brand-accent"]'))
	})

	it('sends no dark value for a type that is not a colour, and names a refusal without a server text', async () => {
		answer('POST', '/settings/tokens/own', 400, {})
		await mount({ tokens: [] })
		click(document.getElementById('nldesign-own-token-add'))
		const form = document.querySelector('#nldesign-own-token-dialog form')
		form.elements.slug.value = 'fade'
		form.elements.label.value = 'Fade'
		form.elements.type.value = 'duration'
		form.elements.value.value = '200ms'
		form.elements.darkValue.value = '#000000'
		form.dispatchEvent(new window.Event('submit', { cancelable: true }))
		await flush()

		expect(JSON.parse(sent('/settings/tokens/own', 'POST')[0].body).darkValue).toBe('')
		expect(document.querySelector('#nldesign-own-token-dialog .nldesign-dialog-error').textContent).toBe('The token was not saved.')
	})

	it('says a removal failed with the server text, or in its own words', async () => {
		vi.spyOn(window, 'confirm').mockReturnValue(true)
		answer('DELETE', '/settings/tokens/own/', 409, { error: 'In use.' })
		await mount()
		click(document.querySelector('button[aria-label="Remove --nldesign-org-brand-accent"]'))
		await flush()
		expect(document.getElementById('nldesign-own-tokens-status').textContent).toBe('In use.')

		answer('DELETE', '/settings/tokens/own/', 500, {})
		click(document.querySelector('button[aria-label="Remove --nldesign-org-brand-accent"]'))
		await flush()
		expect(document.getElementById('nldesign-own-tokens-status').textContent).toBe('The token was not removed.')
	})

	it('lists every detail of a deprecation, and says when there is none', async () => {
		await mount({
			deprecations: [{ token: '--nldesign-org-gone', severity: 'critical', replacement: null, removalDate: '2026-01-01', message: 'Use the brand colour.', due: false, state: 'removed', own: true }],
		})
		const row = document.querySelector('[data-deprecated-token="--nldesign-org-gone"]')
		expect(row.textContent).toContain('Deprecated: critical')
		expect(row.querySelector('.nldesign-deprecation-when').textContent).toBe('removed')
		expect(row.querySelector('.nldesign-own-token-about').textContent).toBe('Use the brand colour.')

		await mount({ deprecations: [] })
		expect(document.getElementById('nldesign-deprecation-list').textContent).toBe('No deprecated tokens.')
	})

	it('edits a deprecation in its prefilled dialog, and withdraws one', async () => {
		answer('DELETE', '/settings/tokens/deprecations/', 200, { status: 'ok' })
		await mount()
		click(document.querySelector('button[aria-label="Edit the deprecation of --nldesign-org-old-accent"]'))
		const form = document.querySelector('#nldesign-deprecation-dialog form')
		expect(form.elements.token.value).toBe('--nldesign-org-old-accent')
		expect(form.elements.severity.value).toBe('warning')
		expect(form.elements.replacement.value).toBe('--nldesign-org-brand-accent')
		expect(form.elements.removalDate.value).toBe('2026-10-01')
		expect(form.elements.message.value).toBe('')

		click(document.querySelector('button[aria-label="Withdraw the deprecation of --nldesign-org-old-accent"]'))
		await flush()
		expect(sent('/settings/tokens/deprecations/--nldesign-org-old-accent', 'DELETE')).toHaveLength(1)
		expect(document.getElementById('nldesign-own-tokens-status').textContent).toBe('Deprecation withdrawn.')
	})

	it('opens an empty deprecation for a typed token, and keeps the dialog open on a refusal', async () => {
		answer('POST', '/settings/tokens/deprecations', 400, {})
		await mount()
		click(document.getElementById('nldesign-deprecation-add'))
		const dialog = document.getElementById('nldesign-deprecation-dialog')
		const form = dialog.querySelector('form')
		expect(form.elements.token.value).toBe('')
		expect(form.elements.token.disabled).toBe(false)
		form.elements.token.value = '--nldesign-color-primary'
		form.dispatchEvent(new window.Event('submit', { cancelable: true }))
		await flush()

		expect(dialog.hasAttribute('open')).toBe(true)
		expect(dialog.querySelector('.nldesign-dialog-error').textContent).toBe('The deprecation was not saved.')

		answer('POST', '/settings/tokens/deprecations', 200, { status: 'ok' })
		form.dispatchEvent(new window.Event('submit', { cancelable: true }))
		await flush()
		expect(dialog.hasAttribute('open')).toBe(false)
		expect(document.getElementById('nldesign-own-tokens-status').textContent).toBe('Deprecation saved.')
	})

	it('closes a dialog with Cancel or Escape and gives the focus back', async () => {
		await mount()
		const add = document.getElementById('nldesign-own-token-add')
		click(add)
		const dialog = document.getElementById('nldesign-own-token-dialog')
		click(dialog.querySelector('.nldesign-dialog-cancel'))
		expect(dialog.hasAttribute('open')).toBe(false)
		expect(document.activeElement).toBe(add)

		click(add)
		document.body.focus()
		dialog.dispatchEvent(new window.Event('cancel'))
		expect(document.activeElement).toBe(add)
	})

	it('says so when the tokens cannot be loaded', async () => {
		answer('GET', '/settings/tokens/own', 500, {})
		global.fetch.mockImplementation((url, options = {}) => {
			requests.push({ url, method: options.method || 'GET' })
			return Promise.reject(new Error('offline'))
		})
		document.body.innerHTML = templateMarkup()
		vi.resetModules()
		await import('../../js/ownTokens.js?t=' + Math.random())
		await flush()
		expect(document.getElementById('nldesign-own-tokens-status').textContent).toBe('Your own tokens could not be loaded.')
	})
})
