/**
 * @vitest-environment jsdom
 *
 * js/admin-workspace-icons.js: one row per image (the mobile logo and the
 * home screen icon) with its preview, an upload button and, when set, a
 * remove button; the feedback says what happened.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/mobile-logo-and-touch-icon/spec.md#requirement-an-administrator-sets-a-mobile-logo-and-a-touch-icon
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const BLOCK =
	'<div id="nldesign-workspace-icons"><div id="nldesign-workspace-icons-list"></div>'
	+ '<span id="nldesign-workspace-icons-feedback" role="status"></span></div>'

const BASE = '/index.php/apps/thematiq/settings/workspace-icons'

let requests
let status
let answers

/**
 * Answer GET with the current status, and POST or DELETE with the next queued answer.
 */
function installFetch() {
	requests = []
	answers = []
	global.fetch = vi.fn((url, options = {}) => {
		requests.push({ url, method: options.method || 'GET', headers: options.headers, body: options.body })
		const answer = (options.method || 'GET') === 'GET' ? { ok: true, body: status } : answers.shift()
		return Promise.resolve({ ok: answer.ok, json: () => Promise.resolve(answer.body) })
	})
}

/**
 * Run the script on the current page.
 *
 * @return {Promise<void>}
 */
async function loadScript() {
	vi.resetModules()
	await import('../../js/admin-workspace-icons.js?t=' + Math.random())
	await flush()
}

const flush = async () => {
	for (let i = 0; i < 10; i++) {
		await Promise.resolve()
	}
}
const row = (kind) => document.querySelector('[data-kind="' + kind + '"]')
const feedback = () => document.getElementById('nldesign-workspace-icons-feedback').textContent

/**
 * Choose a file in a row's hidden input.
 *
 * @param {string} kind The row.
 * @param {Array<File>} files The chosen files.
 */
function choose(kind, files) {
	const input = row(kind).querySelector('input[type="file"]')
	Object.defineProperty(input, 'files', { value: files, configurable: true })
	input.dispatchEvent(new Event('change'))
}

describe('the workspace icons block', () => {
	beforeEach(() => {
		document.body.innerHTML = BLOCK
		global.t = (app, text) => text
		global.OC = { generateUrl: (url) => '/index.php' + url, requestToken: 'token' }
		status = {}
		installFetch()
	})

	afterEach(() => {
		delete global.OC
		delete global.t
		delete global.fetch
		vi.restoreAllMocks()
	})

	it('draws a row per image, unset ones saying Nextcloud keeps its own and without a remove button', async () => {
		await loadScript()
		expect(requests[0]).toMatchObject({ url: BASE, method: 'GET', headers: { requesttoken: 'token' } })
		expect(Array.from(document.querySelectorAll('[data-kind]')).map((el) => el.dataset.kind)).toEqual(['mobile-logo', 'touch-icon'])

		const logo = row('mobile-logo')
		expect(logo.querySelector('label').textContent).toBe('Mobile logo')
		expect(logo.querySelector('label').getAttribute('for')).toBe('nldesign-workspace-icons-mobile-logo')
		expect(logo.querySelector('.nldesign-workspace-icons__preview').textContent).toBe("Not set: Nextcloud's own is used.")
		expect(logo.querySelector('input').accept).toBe('image/png,image/jpeg,image/webp,image/svg+xml')
		expect(row('touch-icon').querySelector('input').accept).toBe('image/png')
		expect(logo.querySelector('[id$="-remove"]')).toBeNull()

		const pick = logo.querySelector('#nldesign-workspace-icons-mobile-logo-btn')
		expect(pick.getAttribute('aria-labelledby')).toBe('nldesign-workspace-icons-mobile-logo-label nldesign-workspace-icons-mobile-logo-btn')
		expect(pick.getAttribute('aria-describedby')).toBe('nldesign-workspace-icons-mobile-logo-hint')
	})

	it('shows a set image as its preview, with a remove button', async () => {
		status = { 'touch-icon': '/icon.png' }
		await loadScript()
		const icon = row('touch-icon')
		const img = icon.querySelector('.nldesign-workspace-icons__preview img')
		expect(img.getAttribute('src')).toBe('/icon.png')
		expect(img.getAttribute('alt')).toBe('')
		expect(icon.querySelector('#nldesign-workspace-icons-touch-icon-remove').textContent).toBe('Remove image')
		expect(row('mobile-logo').querySelector('img')).toBeNull()
	})

	it('draws the rows as unset when the status cannot be read', async () => {
		global.fetch = vi.fn(() => Promise.resolve({ ok: false, json: () => Promise.resolve({ 'mobile-logo': '/x.png' }) }))
		await loadScript()
		expect(row('mobile-logo').querySelector('img')).toBeNull()
	})

	it('opens the file picker from the button', async () => {
		await loadScript()
		const input = row('mobile-logo').querySelector('input[type="file"]')
		const click = vi.spyOn(input, 'click').mockImplementation(() => {})
		row('mobile-logo').querySelector('button').click()
		expect(click).toHaveBeenCalled()
	})

	it('uploads a chosen image, says it is saved and redraws the rows', async () => {
		await loadScript()
		status = { 'mobile-logo': '/logo.svg' }
		answers.push({ ok: true, body: {} })
		const file = new File(['<svg/>'], 'logo.svg', { type: 'image/svg+xml' })
		choose('mobile-logo', [file])
		await flush()

		const upload = requests.find((r) => r.method === 'POST')
		expect(upload.url).toBe(BASE + '/mobile-logo')
		expect(upload.body.get('file')).toBeInstanceOf(File)
		expect(feedback()).toBe('Image saved.')
		expect(row('mobile-logo').querySelector('img').getAttribute('src')).toBe('/logo.svg')
	})

	it('shows the server reason when an upload is refused, and sends nothing without a file', async () => {
		await loadScript()
		choose('touch-icon', [])
		await flush()
		expect(requests.some((r) => r.method === 'POST')).toBe(false)

		answers.push({ ok: false, body: { error: 'The icon must be square.' } })
		choose('touch-icon', [new File(['x'], 'icon.png', { type: 'image/png' })])
		await flush()
		expect(feedback()).toBe('The icon must be square.')
	})

	it('removes a set image and says so, or shows the server reason', async () => {
		status = { 'mobile-logo': '/logo.svg' }
		await loadScript()
		answers.push({ ok: false, body: { error: 'Could not remove.' } })
		row('mobile-logo').querySelector('[id$="-remove"]').click()
		await flush()
		expect(requests.find((r) => r.method === 'DELETE').url).toBe(BASE + '/mobile-logo')
		expect(feedback()).toBe('Could not remove.')

		status = {}
		answers.push({ ok: true, body: {} })
		row('mobile-logo').querySelector('[id$="-remove"]').click()
		await flush()
		expect(feedback()).toBe('Image removed.')
		expect(row('mobile-logo').querySelector('[id$="-remove"]')).toBeNull()
	})

	it('does nothing without its block or without OC', async () => {
		document.body.innerHTML = '<div id="nldesign-workspace-icons-list"></div>'
		await loadScript()
		expect(global.fetch).not.toHaveBeenCalled()

		document.body.innerHTML = BLOCK
		delete global.OC
		await loadScript()
		expect(global.fetch).not.toHaveBeenCalled()
	})

	it('waits for DOMContentLoaded while the page is loading', async () => {
		const readyState = vi.spyOn(document, 'readyState', 'get').mockReturnValue('loading')
		await loadScript()
		expect(global.fetch).not.toHaveBeenCalled()

		readyState.mockReturnValue('complete')
		document.dispatchEvent(new Event('DOMContentLoaded'))
		await flush()
		expect(row('touch-icon')).not.toBeNull()
	})
})
