/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin-assistant-mark.js: the AI assistant block fills
 * the form with the defaults as placeholders, previews the mark with its
 * logo and alternative text, and keeps the mark off when the server refuses.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/assistant-approved-mark/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

let settings
let mark
let saveAnswer

async function flush() {
	for (let i = 0; i < 6; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

async function loadScript() {
	vi.resetModules()
	await import('../../js/admin-assistant-mark.js?t=' + Math.random())
	await flush()
}

describe('AI assistant block', () => {
	beforeEach(() => {
		global.t = (app, text) => text
		global.OC = { generateUrl: (url) => url, requestToken: 'token' }
		settings = {
			enabled: false,
			organisation: '',
			organisationDefault: 'Gemeente Voorbeeld',
			logo: '',
			logoDefault: '/apps/thematiq/img/logos/voorbeeld.svg',
		}
		mark = { enabled: false }
		global.fetch = vi.fn((url, options) => {
			if (options && options.method === 'POST') {
				return Promise.resolve({
					ok: saveAnswer.ok,
					json: () => Promise.resolve(saveAnswer.body),
				})
			}
			const body = url.indexOf('/api/assistant-mark') !== -1 ? mark : settings
			return Promise.resolve({ ok: true, json: () => Promise.resolve(body) })
		})
		document.body.innerHTML = `
			<input type="checkbox" id="nldesign-assistant-mark-enabled">
			<input id="nldesign-assistant-mark-organisation">
			<input id="nldesign-assistant-mark-logo">
			<div id="nldesign-assistant-mark-preview"></div>
			<button id="nldesign-assistant-mark-save"></button>
			<span id="nldesign-assistant-mark-feedback"></span>`
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('shows the defaults as placeholders and no preview while off', async () => {
		await loadScript()
		expect(
			document.getElementById('nldesign-assistant-mark-enabled').checked,
		).toBe(false)
		expect(
			document.getElementById('nldesign-assistant-mark-organisation')
				.placeholder,
		).toBe('Gemeente Voorbeeld')
		expect(
			document.getElementById('nldesign-assistant-mark-preview').textContent,
		).toBe('')
	})

	it('turns the mark on and previews it with the logo and its alternative text', async () => {
		saveAnswer = {
			ok: true,
			body: Object.assign({}, settings, {
				enabled: true,
				preview: {
					enabled: true,
					label: 'Approved by Gemeente Voorbeeld',
					organisation: 'Gemeente Voorbeeld',
					logo: { url: '/logo.svg', alt: 'Gemeente Voorbeeld logo' },
				},
			}),
		}
		await loadScript()
		document.getElementById('nldesign-assistant-mark-enabled').checked = true
		document.getElementById('nldesign-assistant-mark-save').click()
		await flush()

		const post = global.fetch.mock.calls.find(
			(c) => c[1] && c[1].method === 'POST',
		)
		expect(JSON.parse(post[1].body)).toEqual({
			enabled: true,
			organisation: '',
			logo: '',
		})
		const preview = document.getElementById('nldesign-assistant-mark-preview')
		expect(preview.textContent).toBe('Approved by Gemeente Voorbeeld')
		expect(preview.querySelector('img').alt).toBe('Gemeente Voorbeeld logo')
	})

	it('keeps the mark off and asks for the name when the server refuses', async () => {
		saveAnswer = {
			ok: false,
			body: {
				error: 'Enter the organisation name before you turn on the approved mark.',
			},
		}
		await loadScript()
		const toggle = document.getElementById('nldesign-assistant-mark-enabled')
		toggle.checked = true
		document.getElementById('nldesign-assistant-mark-save').click()
		await flush()

		expect(toggle.checked).toBe(false)
		expect(
			document.getElementById('nldesign-assistant-mark-feedback').textContent,
		).toContain('organisation name')
	})
})
