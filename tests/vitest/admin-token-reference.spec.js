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

/** The status the reference endpoint answers with. */
let referenceStatus

function install() {
	calls = []
	referenceStatus = 200
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
		calls.push({
			url,
			method: (options && options.method) || 'GET',
			headers: options && options.headers,
		})
		if (url.indexOf('/reference?') !== -1 || url.indexOf('/export') !== -1) {
			return Promise.resolve({
				status: referenceStatus,
				ok: referenceStatus < 400,
				headers: {
					get: (name) =>
						name === 'Content-Disposition'
						&& url.indexOf('download=1') !== -1
							? 'attachment; filename="amsterdam-tokens.md"'
							: null,
				},
				blob: () => Promise.resolve(new Blob(['# Amsterdam'])),
			})
		}
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
			<a id="nldesign-token-reference-link" class="nldesign-token-reference-link" target="_blank" rel="noopener noreferrer">Token reference</a>
			<a id="nldesign-token-reference-download" class="nldesign-token-reference-link">Download token reference</a>
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

	describe('fetched with the request token', () => {
		/** Click a link the way the browser does. */
		function click(link) {
			link.dispatchEvent(
				new MouseEvent('click', { bubbles: true, cancelable: true }),
			)
		}

		/** Requests to the reference endpoint. */
		function referenceCalls() {
			return calls.filter((call) => call.url.indexOf('/reference?') !== -1)
		}

		beforeEach(() => {
			URL.createObjectURL = vi.fn(() => 'blob:reference')
			URL.revokeObjectURL = vi.fn()
		})

		it('opens the reference in a new tab once it has been fetched', async () => {
			await load()
			const tab = { opener: 'page', location: { href: '' }, close: vi.fn() }
			vi.spyOn(window, 'open').mockReturnValue(tab)

			click(document.getElementById('nldesign-token-reference-link'))
			await flush()

			expect(window.open).toHaveBeenCalledWith('', '_blank')
			expect(referenceCalls()).toEqual([
				{
					url: '/apps/thematiq/api/token-sets/amsterdam/reference?format=html',
					method: 'GET',
					headers: { requesttoken: 'test-token' },
				},
			])
			expect(tab.opener).toBeNull()
			expect(tab.location.href).toBe('blob:reference')
		})

		it('shows the reference in this tab when no new tab may open', async () => {
			await load()
			vi.spyOn(window, 'open').mockReturnValue(null)
			const assign = vi.fn()
			const location = window.location
			delete window.location
			window.location = { assign }

			click(document.getElementById('nldesign-token-reference-link'))
			await flush()
			window.location = location

			expect(assign).toHaveBeenCalledWith('blob:reference')
		})

		it('closes the tab and says so when the reference is refused', async () => {
			referenceStatus = 412
			await load()
			const tab = { opener: 'page', location: { href: '' }, close: vi.fn() }
			vi.spyOn(window, 'open').mockReturnValue(tab)
			vi.spyOn(console, 'error').mockImplementation(() => {})

			click(document.getElementById('nldesign-token-reference-link'))
			await flush()

			expect(tab.close).toHaveBeenCalled()
			expect(OC.Notification.showTemporary).toHaveBeenCalledWith(
				'The token reference could not be loaded.',
			)
		})

		it('downloads the Markdown reference under the name the server gives it', async () => {
			await load()
			const saved = vi.fn()
			vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(
				function () {
					saved(this.download)
				},
			)

			click(document.getElementById('nldesign-token-reference-download'))
			await flush()

			expect(referenceCalls()[0].headers).toEqual({
				requesttoken: 'test-token',
			})
			expect(saved).toHaveBeenCalledWith('amsterdam-tokens.md')
		})

		it("fetches a custom set's reference from its row too", async () => {
			await load()
			vi.spyOn(window, 'open').mockReturnValue({
				location: { href: '' },
				close: vi.fn(),
			})

			click(
				document.querySelector(
					'#nldesign-custom-set-list .nldesign-token-reference-link',
				),
			)
			await flush()

			expect(referenceCalls()[0].url).toBe(
				'/apps/thematiq/api/token-sets/custom-gemeente-x/reference?format=html',
			)
		})

		it("downloads a custom set's own file from its row", async () => {
			await load()
			const saved = vi.fn()
			vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(
				function () {
					saved(this.download)
				},
			)

			click(
				[
					...document.querySelectorAll(
						'#nldesign-custom-set-list button.nldesign-btn',
					),
				].find((button) => button.textContent === 'Download'),
			)
			await flush()

			const request = calls.find((call) => call.url.indexOf('/export') !== -1)
			expect(request.url).toBe(
				'/apps/thematiq/settings/tokensets/custom/custom-gemeente-x/export',
			)
			expect(request.headers).toEqual({ requesttoken: 'test-token' })
			expect(saved).toHaveBeenCalledWith('custom-gemeente-x.css')
		})
	})
})
