/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin.js: the token reference links. Next to the Design
 * token set dropdown they follow the selected set; each custom set in the list
 * links to its reference and offers the Markdown download.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/token-reference/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

let calls

function install() {
	calls = []
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
		requestToken: 'test-token',
		Notification: { showTemporary: vi.fn() },
		dialogs: { confirm: vi.fn() },
	}
	global.OCP = { InitialState: { loadState: (app, key, fallback) => fallback } }
	global.fetch = vi.fn((url, options) => {
		calls.push({ url, method: (options && options.method) || 'GET' })
		let body = {}
		if (url.indexOf('/settings/tokensets/custom') !== -1) {
			body = {
				sets: [
					{ id: 'custom-gemeente-x', name: 'Gemeente X', warnings: [] },
				],
			}
		}
		return Promise.resolve({
			status: 200,
			ok: true,
			json: () => Promise.resolve(body),
		})
	})
}

async function flush(rounds = 10) {
	for (let i = 0; i < rounds; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

async function load() {
	document.body.innerHTML = `
		<div id="nldesign-settings" class="section">
			<select id="nldesign-token-set-select">
				<option value="amsterdam" selected>Amsterdam</option>
				<option value="utrecht">Utrecht</option>
			</select>
			<a id="nldesign-token-reference-link">Token reference</a>
			<a id="nldesign-token-reference-download">Download token reference</a>
			<input id="nldesign-upload-name" /><input type="file" id="nldesign-upload-input" /><button id="nldesign-upload-btn">Upload</button>
			<div id="nldesign-custom-set-list"></div>
		</div>
	`
	vi.resetModules()
	await import('../../js/admin.js?t=' + Math.random())
	await flush()
}

describe('admin.js token reference links', () => {
	beforeEach(() => {
		install()
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('points the links next to the dropdown at the selected set', async () => {
		await load()
		const view = document.getElementById('nldesign-token-reference-link')
		const save = document.getElementById('nldesign-token-reference-download')
		expect(view.getAttribute('href')).toBe(
			'/apps/thematiq/api/token-sets/amsterdam/reference?format=html',
		)
		expect(save.getAttribute('href')).toBe(
			'/apps/thematiq/api/token-sets/amsterdam/reference?format=md&download=1',
		)

		const select = document.getElementById('nldesign-token-set-select')
		select.value = 'utrecht'
		select.dispatchEvent(new Event('change'))

		expect(view.getAttribute('href')).toBe(
			'/apps/thematiq/api/token-sets/utrecht/reference?format=html',
		)
	})

	it('links each custom set to its reference and its download', async () => {
		await load()
		const links = document.querySelectorAll(
			'#nldesign-custom-set-list .nldesign-token-reference-link',
		)

		expect(links).toHaveLength(2)
		expect(links[0].getAttribute('href')).toBe(
			'/apps/thematiq/api/token-sets/custom-gemeente-x/reference?format=html',
		)
		expect(links[0].getAttribute('aria-label')).toBe(
			'Token reference of Gemeente X',
		)
		expect(links[1].getAttribute('href')).toBe(
			'/apps/thematiq/api/token-sets/custom-gemeente-x/reference?format=md&download=1',
		)
	})
})
