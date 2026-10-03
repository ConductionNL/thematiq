/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin.js: the "Theme gallery" block. Off, it names the
 * index host and lists nothing; on, it lists entries with swatches, licence
 * and contrast, says so when the index cannot be reached, offers install or
 * update, and after an install refreshes the token set dropdown.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/theme-gallery/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

let calls
let gallery
let installAnswer
let catalogue

const utrecht = {
	id: 'provincie-utrecht',
	name: 'Provincie Utrecht',
	organisation: 'Provincie Utrecht',
	licence: 'EUPL-1.2',
	sourceUrl: 'https://github.com/example/utrecht-tokens',
	swatches: { primary: '#cc0000', background: '#ffffff', text: '#1a1a1a' },
	contrast: { pass: 42, fail: 0 },
	installed: null,
	updateAvailable: false,
}

function install() {
	catalogue = []
	calls = []
	installAnswer = {
		status: 200,
		body: { id: 'custom-provincie-utrecht', updated: false },
	}
	gallery = {
		enabled: false,
		host: 'raw.githubusercontent.com',
		reachable: null,
		entries: [],
	}
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
		const method = (options && options.method) || 'GET'
		calls.push({ url, method, body: options && options.body })
		let status = 200
		let body = {}
		if (url.indexOf('/install') !== -1) {
			status = installAnswer.status
			body = installAnswer.body
		} else if (url.indexOf('/settings/gallery') !== -1) {
			if (method === 'POST') {
				gallery.enabled = JSON.parse(options.body).enabled
			}
			body = gallery
		} else if (url.indexOf('/settings/tokensets') !== -1) {
			body = { tokenSets: catalogue, sets: [] }
		}
		return Promise.resolve({
			status,
			ok: status < 400,
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
			<div id="nldesign-gallery">
				<input type="checkbox" id="nldesign-gallery-toggle">
				<label for="nldesign-gallery-toggle" id="nldesign-gallery-toggle-label">Show the theme gallery</label>
				<p id="nldesign-gallery-status"></p>
				<ul id="nldesign-gallery-list"></ul>
			</div>
		</div>
	`
	vi.resetModules()
	await import('../../js/admin.js?t=' + Math.random())
	await flush()
}

function galleryCalls() {
	return calls.filter((c) => c.url.indexOf('/settings/gallery') !== -1)
}

describe('admin.js theme gallery', () => {
	beforeEach(() => {
		install()
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('is off on a fresh install, names the host and lists nothing', async () => {
		await load()

		expect(document.getElementById('nldesign-gallery-toggle').checked).toBe(
			false,
		)
		expect(
			document.getElementById('nldesign-gallery-toggle-label').textContent,
		).toBe('Show the theme gallery (contacts raw.githubusercontent.com)')
		expect(
			document.getElementById('nldesign-gallery-list').children,
		).toHaveLength(0)
		expect(galleryCalls().map((c) => c.method + ' ' + c.url)).toEqual([
			'GET /apps/thematiq/settings/gallery',
		])
	})

	it('turns the gallery on and lists the entries with swatches, licence and contrast', async () => {
		await load()
		gallery.reachable = true
		gallery.entries = [utrecht]
		const toggle = document.getElementById('nldesign-gallery-toggle')
		toggle.checked = true
		toggle.dispatchEvent(new Event('change'))
		await flush()

		const post = galleryCalls().find((c) => c.method === 'POST')
		expect(JSON.parse(post.body)).toEqual({ enabled: true })
		const item = document.querySelector('[data-gallery-id="provincie-utrecht"]')
		expect(item.textContent).toContain('Provincie Utrecht')
		expect(item.textContent).toContain('licence EUPL-1.2')
		expect(item.textContent).toContain('Contrast: all checks pass')
		expect(item.querySelectorAll('.nldesign-gallery-swatch')).toHaveLength(3)
		expect(item.querySelector('a').getAttribute('href')).toBe(utrecht.sourceUrl)
		expect(item.querySelector('button').getAttribute('aria-label')).toBe(
			'Install Provincie Utrecht',
		)
	})

	it('says so when the gallery cannot be reached', async () => {
		gallery = {
			enabled: true,
			host: 'intranet.example.nl',
			reachable: false,
			entries: [],
		}
		await load()

		expect(
			document.getElementById('nldesign-gallery-status').textContent,
		).toContain('The gallery could not be reached')
		expect(
			document.getElementById('nldesign-gallery-toggle-label').textContent,
		).toContain('intranet.example.nl')
	})

	it('offers an update for an installed set with a new checksum, and marks one that is current', async () => {
		gallery = {
			enabled: true,
			host: 'h',
			reachable: true,
			entries: [
				{
					...utrecht,
					installed: 'custom-provincie-utrecht',
					updateAvailable: true,
				},
				{
					...utrecht,
					id: 'gemeente-epe',
					name: 'Gemeente Epe',
					installed: 'custom-gemeente-epe',
					updateAvailable: false,
				},
			],
		}
		await load()

		const update = document.querySelector(
			'[data-gallery-id="provincie-utrecht"]',
		)
		expect(update.textContent).toContain('Update available')
		expect(update.querySelector('button').textContent).toBe('Update')
		const current = document.querySelector('[data-gallery-id="gemeente-epe"]')
		expect(current.querySelector('button')).toBeNull()
		expect(current.textContent).toContain('Installed')
	})

	it('installs through POST and refreshes the token set list', async () => {
		gallery = { enabled: true, host: 'h', reachable: true, entries: [utrecht] }
		await load()
		document
			.querySelector('[data-gallery-id="provincie-utrecht"] button')
			.click()
		await flush()

		expect(
			calls.some(
				(c) =>
					c.method === 'POST'
					&& c.url
						=== '/apps/thematiq/settings/gallery/provincie-utrecht/install',
			),
		).toBe(true)
		expect(
			calls.some(
				(c) =>
					c.method === 'GET'
					&& c.url === '/apps/thematiq/settings/tokensets',
			),
		).toBe(true)
	})

	it('adds an installed set to the planned-switch list without a reload', async () => {
		gallery = { enabled: true, host: 'h', reachable: true, entries: [utrecht] }
		await load()
		document
			.getElementById('nldesign-settings')
			.insertAdjacentHTML(
				'beforeend',
				'<select id="nldesign-scheduled-set"><option value="amsterdam">Amsterdam</option></select>',
			)
		const planned = document.getElementById('nldesign-scheduled-set')
		planned.value = 'amsterdam'
		catalogue = [
			{ id: 'amsterdam', name: 'Amsterdam' },
			{ id: 'custom-provincie-utrecht', name: 'Provincie Utrecht' },
		]

		document
			.querySelector('[data-gallery-id="provincie-utrecht"] button')
			.click()
		await flush()

		expect(Array.from(planned.options).map((o) => o.value)).toEqual([
			'amsterdam',
			'custom-provincie-utrecht',
		])
		expect(planned.value).toBe('amsterdam')
	})

	it('shows why an install was refused', async () => {
		gallery = { enabled: true, host: 'h', reachable: true, entries: [utrecht] }
		installAnswer = {
			status: 422,
			body: {
				error: 'The downloaded file does not match the gallery index, so nothing was installed.',
			},
		}
		await load()
		const button = document.querySelector(
			'[data-gallery-id="provincie-utrecht"] button',
		)
		button.click()
		await flush()

		expect(document.getElementById('nldesign-gallery-status').textContent).toBe(
			'The downloaded file does not match the gallery index, so nothing was installed.',
		)
		expect(button.disabled).toBe(false)
	})
})
