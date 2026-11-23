/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin-external-apps.js: the block fills one field per
 * external app, saves them at once, and marks the refused ones.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/apps-menu-ordered-list/specs/external-apps-in-the-app-menu/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

let saveAnswer
let posted

async function flush() {
	for (let i = 0; i < 6; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

async function loadScript() {
	vi.resetModules()
	await import('../../js/admin-external-apps.js?t=' + Math.random())
	await flush()
}

const field = (key) => document.querySelector(`[data-external-app="${key}"]`)

describe('external apps block', () => {
	beforeEach(() => {
		global.t = (app, text) => text
		global.OC = { generateUrl: (url) => url, requestToken: 'token' }
		posted = null
		global.fetch = vi.fn((url, options) => {
			if (options && options.method === 'POST') {
				posted = JSON.parse(options.body)
				return Promise.resolve({
					ok: saveAnswer.ok,
					json: () => Promise.resolve(saveAnswer.body),
				})
			}
			return Promise.resolve({
				ok: true,
				json: () =>
					Promise.resolve({
						apps: [
							{ key: 'openproject', url: 'https://op.example.org' },
							{ key: 'code', url: '' },
						],
					}),
			})
		})
		document.body.innerHTML = `
			<div id="nldesign-external-apps">
				<input data-external-app="openproject">
				<input data-external-app="code">
				<button id="nldesign-external-apps-save"></button>
				<span id="nldesign-external-apps-feedback"></span>
			</div>`
	})

	afterEach(() => {
		document.body.innerHTML = ''
		delete global.fetch
		delete global.OC
		delete global.t
	})

	it('fills each field with its stored address', async () => {
		await loadScript()
		expect(field('openproject').value).toBe('https://op.example.org')
		expect(field('code').value).toBe('')
	})

	it('saves every address at once', async () => {
		await loadScript()
		field('code').value = ' https://code.example.org '
		saveAnswer = {
			ok: true,
			body: {
				apps: [
					{ key: 'openproject', url: 'https://op.example.org' },
					{ key: 'code', url: 'https://code.example.org' },
				],
			},
		}
		document.getElementById('nldesign-external-apps-save').click()
		await flush()
		expect(posted).toEqual({
			urls: {
				openproject: 'https://op.example.org',
				code: 'https://code.example.org',
			},
		})
		expect(
			document.getElementById('nldesign-external-apps-feedback').textContent,
		).toBe('Addresses saved.')
	})

	it('marks a refused address and says why', async () => {
		await loadScript()
		field('code').value = 'javascript:alert(1)'
		saveAnswer = {
			ok: false,
			body: { error: 'Enter an http or https address.', refused: ['code'] },
		}
		document.getElementById('nldesign-external-apps-save').click()
		await flush()
		expect(field('code').getAttribute('aria-invalid')).toBe('true')
		expect(field('openproject').hasAttribute('aria-invalid')).toBe(false)
		expect(
			document.getElementById('nldesign-external-apps-feedback').textContent,
		).toBe('Enter an http or https address.')
	})
})
