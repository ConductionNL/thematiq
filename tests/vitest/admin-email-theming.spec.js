/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin.js: the email template theming panel. It loads its
 * state and footer, saves them, and answers each refusal the server gives:
 * a read-only config.php (shows the occ commands), a foreign mail template
 * class (locks the switch), an invalid footer, and any other failure.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/email-theming/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const URL_EMAIL = '/index.php/apps/thematiq/settings/email-theming'

let getAnswer
let postAnswer
let toast

/** Minimal globals admin.js reads at load time; the email endpoint answers from the two variables above. */
function installGlobals() {
	global.t = (app, text, values = {}) => text.replace(/\{(\w+)\}/g, (match, key) => values[key])
	global.n = (app, singular, plural, count) => (count === 1 ? singular : plural)
	toast = vi.fn()
	global.OC = {
		generateUrl: (url) => '/index.php' + url,
		linkTo: (app, path) => path,
		requestToken: 'test-token',
		Notification: { showTemporary: vi.fn() },
		dialogs: { confirm: vi.fn() },
	}
	global.OCP = {
		InitialState: { loadState: (app, key, fallback) => fallback },
		Toast: { message: toast },
	}
	window.OCP = global.OCP
	global.fetch = vi.fn((url, options = {}) => {
		if (url === URL_EMAIL) {
			const answer = options.method === 'POST' ? postAnswer : getAnswer
			if (answer instanceof Error) {
				return Promise.reject(answer)
			}
			return Promise.resolve({ ok: answer.ok, status: answer.ok ? 200 : 400, json: () => Promise.resolve(answer.data) })
		}
		return Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve({}), headers: { get: () => null } })
	})
}

/** Flush pending promise chains. */
async function flush(rounds = 8) {
	for (let i = 0; i < rounds; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

/** Load admin.js on the panel. */
async function load() {
	vi.resetModules()
	await import('../../js/admin.js?t=' + Math.random())
	await flush()
}

const $ = (id) => document.getElementById(id)
const posted = () => global.fetch.mock.calls.filter(([url, options]) => url === URL_EMAIL && options && options.method === 'POST')

async function save() {
	$('nldesign-email-theming-save').click()
	await flush()
}

describe('admin.js email template theming', () => {
	beforeEach(() => {
		getAnswer = {
			ok: true,
			data: {
				state: { state: 'enabled', configReadOnly: false, foreignClass: null },
				footer: { orgName: 'Zuiddrecht', accessibilityUrl: 'https://zuiddrecht.nl/a11y', privacyUrl: '' },
			},
		}
		postAnswer = { ok: true, data: { status: 'ok', state: { state: 'disabled', configReadOnly: true }, footer: { orgName: 'Gemeente Zuiddrecht' } } }
		installGlobals()
		document.body.innerHTML = `
			<div id="nldesign-settings" class="section">
				<div id="nldesign-email-theming" data-state="disabled">
					<p class="nldesign-email-foreign-note" role="alert"></p>
					<input type="checkbox" id="nldesign-email-theming-enabled">
					<input type="text" id="nldesign-email-footer-org-name">
					<input type="url" id="nldesign-email-footer-accessibility-url">
					<input type="url" id="nldesign-email-footer-privacy-url">
					<button type="button" id="nldesign-email-theming-save">Save</button>
					<span id="nldesign-email-theming-feedback" role="status"></span>
					<div id="nldesign-email-occ-hint" style="display:none">
						<code id="nldesign-email-occ-enable">occ old-enable</code>
						<code id="nldesign-email-occ-disable">occ old-disable</code>
					</div>
				</div>
			</div>
		`
	})

	afterEach(() => {
		document.body.innerHTML = ''
		delete window.OCP
		vi.restoreAllMocks()
	})

	it('draws the loaded state and footer', async () => {
		await load()

		const root = $('nldesign-email-theming')
		expect(global.fetch).toHaveBeenCalledWith(URL_EMAIL, { headers: { requesttoken: 'test-token' } })
		expect(root.getAttribute('data-state')).toBe('enabled')
		expect(root.getAttribute('data-config-read-only')).toBe('0')
		expect(root.getAttribute('data-foreign-class')).toBe('')
		expect($('nldesign-email-theming-enabled').checked).toBe(true)
		expect($('nldesign-email-theming-enabled').disabled).toBe(false)
		expect($('nldesign-email-footer-org-name').value).toBe('Zuiddrecht')
		expect($('nldesign-email-footer-accessibility-url').value).toBe('https://zuiddrecht.nl/a11y')
		expect($('nldesign-email-footer-privacy-url').value).toBe('')
	})

	it('locks the switch when another mail template class is configured', async () => {
		getAnswer = { ok: true, data: { state: { state: 'foreign', configReadOnly: true, foreignClass: 'OCA\\Other\\Mail' } } }
		await load()

		const root = $('nldesign-email-theming')
		expect(root.getAttribute('data-state')).toBe('foreign')
		expect(root.getAttribute('data-config-read-only')).toBe('1')
		expect(root.getAttribute('data-foreign-class')).toBe('OCA\\Other\\Mail')
		expect($('nldesign-email-theming-enabled').checked).toBe(false)
		expect($('nldesign-email-theming-enabled').disabled).toBe(true)
	})

	it('keeps the panel as it is when the state cannot be loaded', async () => {
		getAnswer = new Error('offline')
		const error = vi.spyOn(console, 'error').mockImplementation(() => {})
		await load()
		expect(error).toHaveBeenCalledWith('Error loading email theming:', getAnswer)
		expect($('nldesign-email-theming').getAttribute('data-state')).toBe('disabled')
	})

	it('saves the switch and the footer, and draws what the server answers', async () => {
		await load()
		$('nldesign-email-footer-privacy-url').value = 'https://zuiddrecht.nl/privacy'
		await save()

		const [[, options]] = posted()
		expect(options.headers).toEqual({ 'Content-Type': 'application/json', requesttoken: 'test-token' })
		expect(JSON.parse(options.body)).toEqual({
			enabled: true,
			orgName: 'Zuiddrecht',
			accessibilityUrl: 'https://zuiddrecht.nl/a11y',
			privacyUrl: 'https://zuiddrecht.nl/privacy',
		})
		expect($('nldesign-email-theming').getAttribute('data-state')).toBe('disabled')
		expect($('nldesign-email-footer-org-name').value).toBe('Gemeente Zuiddrecht')
		expect($('nldesign-email-footer-accessibility-url').value).toBe('')
		expect($('nldesign-email-theming-feedback').textContent).toBe('Email template settings saved.')
		expect(toast).toHaveBeenCalledWith('Email template settings saved.')
	})

	it('shows the occ commands when config.php is read-only', async () => {
		await load()
		postAnswer = { ok: false, data: { error: 'config_read_only', occEnable: 'occ config:system:set mail_template_class', occDisable: 'occ config:system:delete mail_template_class', footer: { orgName: 'Kept' } } }
		await save()

		expect($('nldesign-email-theming-enabled').checked).toBe(false)
		expect($('nldesign-email-occ-hint').style.display).toBe('')
		expect($('nldesign-email-occ-enable').textContent).toBe('occ config:system:set mail_template_class')
		expect($('nldesign-email-occ-disable').textContent).toBe('occ config:system:delete mail_template_class')
		expect($('nldesign-email-footer-org-name').value).toBe('Kept')
		expect(toast).toHaveBeenCalledWith('config.php is read-only; run the shown occ command manually.')
	})

	it('keeps the old occ commands when the answer has none', async () => {
		await load()
		postAnswer = { ok: false, data: { error: 'config_read_only' } }
		await save()
		expect($('nldesign-email-occ-hint').style.display).toBe('')
		expect($('nldesign-email-occ-enable').textContent).toBe('occ old-enable')
	})

	it('locks the switch and names the class when another mail template class is configured on save', async () => {
		await load()
		postAnswer = { ok: false, data: { error: 'foreign_mail_template_class', class: 'OCA\\Other\\Mail' } }
		await save()

		expect($('nldesign-email-theming-enabled').checked).toBe(false)
		expect($('nldesign-email-theming-enabled').disabled).toBe(true)
		expect(document.querySelector('.nldesign-email-foreign-note').textContent).toBe(
			'A different mail template class is already configured (OCA\\Other\\Mail); nldesign will not overwrite it.',
		)
		expect(toast).toHaveBeenCalledWith('A different mail template class is already configured.')
	})

	it.each([
		['an invalid footer', { ok: false, data: { error: 'invalid_footer' } }, 'Invalid footer URL — use an http:// or https:// address.'],
		['an unknown refusal', { ok: false, data: { error: 'something' } }, 'Failed to save email template settings.'],
		['an ok status code without status ok', { ok: true, data: {} }, 'Failed to save email template settings.'],
	])('says so on %s', async (label, answer, message) => {
		await load()
		postAnswer = answer
		await save()
		expect(toast).toHaveBeenCalledWith(message)
		expect($('nldesign-email-theming-feedback').textContent).toBe('')
	})

	it('says so when the save request fails', async () => {
		await load()
		postAnswer = new Error('offline')
		const error = vi.spyOn(console, 'error').mockImplementation(() => {})
		await save()
		expect(error).toHaveBeenCalledWith('Error saving email theming:', postAnswer)
		expect(toast).toHaveBeenCalledWith('Failed to save email template settings.')
	})
})
