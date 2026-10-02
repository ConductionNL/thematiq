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
 * @spec openspec/changes/authoring-token-lifecycle/tasks.md#task-4.1
 * @spec openspec/changes/authoring-token-lifecycle/tasks.md#task-4.2
 */

import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../..')

/** The section and both dialogs, cut from the template, PHP calls replaced by their text. */
function templateMarkup() {
	const php = readFileSync(resolve(ROOT, 'templates/settings/admin.php'), 'utf8')
	const start = php.indexOf('<div class="nldesign-own-tokens"')
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
		expect(rows[1].textContent).toContain('#e17000 / dark #ff9a3c')
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
})
