/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin.js: the simple brand form. The preview repaints with the shades
 * the server stores, the readout shows both ratios with their thresholds and warns without
 * blocking, and a taken name is reported.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/simple-brand-form/spec.md
 */

import { createRequire } from 'node:module'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

// jsdom gives import.meta.url a non-file scheme, so paths resolve from the repo root.
const require = createRequire(resolve('tests/vitest/admin-brand-form.spec.js'))
const fixture = JSON.parse(
	readFileSync(resolve('tests/Unit/fixtures/brand-form-parity.json'), 'utf8'),
)
const brandForm = require('../../js/lib/brandForm.js')

let calls
let postResponse

function install() {
	calls = []
	postResponse = {
		status: 200,
		body: {
			id: 'custom-gemeente-voorbeeld',
			textRatio: 5.9,
			uiRatio: 5.9,
			warnings: [],
		},
	}
	global.t = (app, text) => text
	global.n = (app, singular, plural, count) => (count === 1 ? singular : plural)
	global.OC = {
		generateUrl: (url) => url,
		linkTo: (app, path) => path,
		requestToken: 'test-token',
		Notification: { showTemporary: vi.fn() },
		dialogs: { confirm: vi.fn() },
	}
	global.OCP = { InitialState: { loadState: (app, key, fallback) => fallback } }
	window.NldesignBrandForm = brandForm
	global.fetch = vi.fn((url, options) => {
		const method = (options && options.method) || 'GET'
		calls.push({ url, method, body: options && options.body })
		let status = 200
		let body = {}
		if (url.indexOf('/settings/tokensets/from-colours') !== -1) {
			if (method === 'GET') {
				body = fixture.inputs
			} else {
				status = postResponse.status
				body = postResponse.body
			}
		}
		if (url.indexOf('/settings/tokensets/custom') !== -1) {
			body = { sets: [] }
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

function rgb(hex) {
	const n = parseInt(hex.slice(1), 16)
	return 'rgb(' + (n >> 16) + ', ' + ((n >> 8) & 255) + ', ' + (n & 255) + ')'
}

async function load() {
	document.body.innerHTML = `
		<div id="nldesign-settings" class="section">
			<select id="nldesign-token-set-select"><option value="amsterdam" selected>Amsterdam</option></select>
			<input id="nldesign-upload-name" /><input type="file" id="nldesign-upload-input" /><button id="nldesign-upload-btn">Upload</button>
			<div id="nldesign-brand-form">
				<input id="nldesign-brand-name" />
				<input type="color" id="nldesign-brand-primary" value="#154273" />
				<input type="color" id="nldesign-brand-background" value="#ffffff" />
				<input type="file" id="nldesign-brand-logo" />
				<span id="nldesign-brand-sample"></span><span id="nldesign-brand-sample-hover"></span>
				<p id="nldesign-brand-contrast"></p>
				<button id="nldesign-brand-save">Create house style</button>
				<div id="nldesign-brand-result" style="display:none"></div>
			</div>
			<div id="nldesign-custom-set-list"></div>
		</div>
	`
	vi.resetModules()
	await import('../../js/admin.js?t=' + Math.random())
	await flush()
}

function setPrimary(value) {
	const input = document.getElementById('nldesign-brand-primary')
	input.value = value
	input.dispatchEvent(new Event('input'))
}

describe('admin.js simple brand form', () => {
	beforeEach(() => {
		install()
	})

	afterEach(() => {
		document.body.innerHTML = ''
		delete window.NldesignBrandForm
		vi.restoreAllMocks()
	})

	it('repaints the preview with the hover shade the server stores', async () => {
		await load()
		setPrimary('#c8102e')

		const expected = brandForm.derive(
			fixture.inputs,
			'#c8102e',
			'#ffffff',
		).declarations
		expect(
			document.getElementById('nldesign-brand-sample').style.background,
		).toBe(rgb('#c8102e'))
		expect(
			document.getElementById('nldesign-brand-sample-hover').style.background,
		).toBe(rgb(expected['--nldesign-color-primary-hover']))
		expect(document.getElementById('nldesign-brand-sample').style.color).toBe(
			rgb('#ffffff'),
		)
	})

	it('shows both ratios with their thresholds and warns without blocking', async () => {
		await load()
		setPrimary('#ffd200')

		const readout = document.getElementById(
			'nldesign-brand-contrast',
		).textContent
		expect(readout).toContain('Text on primary')
		expect(readout).toContain('needs 4.5:1')
		expect(readout).toContain('needs 3:1')
		expect(readout).toContain(
			'Warning: below the threshold. You can still save.',
		)
		expect(document.getElementById('nldesign-brand-save').disabled).toBe(false)
	})

	it('posts the name and both colours, and reports a taken name', async () => {
		await load()
		postResponse = {
			status: 409,
			body: {
				error: 'A custom token set named "Gemeente Voorbeeld" already exists.',
			},
		}
		document.getElementById('nldesign-brand-name').value = 'Gemeente Voorbeeld'
		setPrimary('#c8102e')
		document.getElementById('nldesign-brand-save').click()
		await flush()

		const post = calls.find((c) => c.method === 'POST')
		expect(post.url).toBe('/apps/thematiq/settings/tokensets/from-colours')
		expect(post.body.get('name')).toBe('Gemeente Voorbeeld')
		expect(post.body.get('primary')).toBe('#c8102e')
		expect(post.body.get('background')).toBe('#ffffff')
		expect(
			document.getElementById('nldesign-brand-result').textContent,
		).toContain('already exists')
	})
})

describe('admin.js custom token set tabs', () => {
	beforeEach(() => {
		install()
	})

	afterEach(() => {
		document.body.innerHTML = ''
		delete window.NldesignBrandForm
		vi.restoreAllMocks()
	})

	async function loadTabs() {
		document.body.innerHTML = `
			<div id="nldesign-settings" class="section">
				<div class="nldesign-create-tabs" role="tablist">
					<button class="nldesign-create-tab active" role="tab" id="tab-upload" aria-selected="true" aria-controls="panel-upload">Upload a file</button>
					<button class="nldesign-create-tab" role="tab" id="tab-colours" aria-selected="false" aria-controls="panel-colours" tabindex="-1">Start from your colours</button>
				</div>
				<div id="panel-upload" role="tabpanel"></div>
				<div id="panel-colours" role="tabpanel" hidden></div>
				<div id="nldesign-custom-set-list"></div>
			</div>
		`
		vi.resetModules()
		await import('../../js/admin.js?t=' + Math.random())
		await flush()
	}

	it('shows the panel of the tab that is clicked and hides the other', async () => {
		await loadTabs()
		document.getElementById('tab-colours').click()

		expect(document.getElementById('panel-colours').hidden).toBe(false)
		expect(document.getElementById('panel-upload').hidden).toBe(true)
		expect(
			document.getElementById('tab-colours').getAttribute('aria-selected'),
		).toBe('true')
		expect(document.getElementById('tab-upload').tabIndex).toBe(-1)
	})

	it('moves to the next tab with the arrow key and wraps around', async () => {
		await loadTabs()
		const upload = document.getElementById('tab-upload')
		upload.dispatchEvent(
			new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }),
		)
		expect(document.getElementById('panel-colours').hidden).toBe(false)
		expect(document.activeElement.id).toBe('tab-colours')

		document
			.getElementById('tab-colours')
			.dispatchEvent(
				new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }),
			)
		expect(document.getElementById('panel-upload').hidden).toBe(false)
		expect(document.activeElement.id).toBe('tab-upload')
	})
})
