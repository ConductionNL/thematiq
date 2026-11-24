/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin.js: the restore action in the theming audit log.
 * A row with a versionId gets a restore button; choosing it previews the
 * restore, shows the changes in a confirmation dialog, restores only on
 * confirm, and returns focus to the button on cancel.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/theme-versions/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const ENTRIES = [
	{
		ts: '2026-09-29T17:00:00Z',
		actor: 'admin',
		action: 'custom_set_uploaded',
		new: 'custom-bad',
		versionId: '20260929170000-0001',
	},
	{
		ts: '2026-09-29T16:40:00Z',
		actor: 'admin',
		action: 'token_set_changed',
		new: 'rijkshuisstijl',
		versionId: '20260929164000-0001',
	},
	{ ts: '2026-09-29T16:00:00Z', actor: 'admin', action: 'toggle_changed' },
]

const PREVIEW = {
	valid: true,
	errors: [],
	changes: [{ field: 'tokenSet', from: 'custom-bad', to: 'rijkshuisstijl' }],
	customTokenSets: { add: [], remove: ['custom-bad'] },
	missingFonts: [{ id: 'gone', name: 'Gone Sans', role: 'heading' }],
}

let calls
let confirmAnswer

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
	}
	global.OCP = { InitialState: { loadState: (app, key, fallback) => fallback } }
	global.fetch = vi.fn((url, options) => {
		calls.push({ url, method: (options && options.method) || 'GET' })
		let body = {}
		if (url.indexOf('/settings/audit') !== -1) {
			body = { entries: ENTRIES }
		} else if (url.indexOf('/preview') !== -1) {
			body = PREVIEW
		} else if (url.indexOf('/restore') !== -1) {
			body = Object.assign({ applied: true }, PREVIEW)
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
			<table><tbody id="nldesign-audit-table-body"></tbody></table>
			<button type="button" id="nldesign-audit-download-btn">Download</button>
		</div>
	`
	vi.resetModules()
	await import('../../js/lib/auditFormat.js?t=' + Math.random())
	await import('../../js/dialogs/admin-dialog.js?t=' + Math.random())
	await import('../../js/admin.js?t=' + Math.random())
	await flush()
}

/** Press a row's restore button and answer the dialog that opens. */
async function pressRestore(button) {
	button.click()
	await flush()
	const dialog = document.getElementById('nldesign-restore-version-dialog')
	calls.push({ dialog: dialog.textContent, el: dialog })
	dialog.querySelector(confirmAnswer ? '.nldesign-dialog-confirm' : '.nldesign-dialog-cancel').click()
	await flush()
	return dialog
}

describe('admin.js version restore', () => {
	beforeEach(() => {
		install()
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('draws each entry as the log board: date and time, the action as a pill, and announces the count', async () => {
		const rendered = vi.fn()
		document.addEventListener('thematiq:audit-rendered', rendered)
		await load()
		document.removeEventListener('thematiq:audit-rendered', rendered)

		const row = document.querySelector('#nldesign-audit-table-body tr')
		expect(row.querySelector('.nldesign-audit-date').textContent).not.toBe('')
		expect(row.querySelector('.nldesign-audit-pill')).not.toBeNull()
		expect(row.querySelector('.nldesign-audit-code').textContent).toBe(ENTRIES[0].action)
		expect(rendered).toHaveBeenCalledTimes(1)
		expect(rendered.mock.calls[0][0].detail.count).toBe(ENTRIES.length)
	})

	it('offers restore only on rows that have a version', async () => {
		await load()

		const rows = document.querySelectorAll('#nldesign-audit-table-body tr')
		expect(rows).toHaveLength(3)
		expect(rows[0].querySelector('button.nldesign-audit-restore')).not.toBeNull()
		expect(rows[1].querySelector('button.nldesign-audit-restore')).not.toBeNull()
		expect(rows[2].querySelector('button.nldesign-audit-restore')).toBeNull()
	})

	it('previews, lists the changes and restores on confirm', async () => {
		confirmAnswer = true
		await load()

		await pressRestore(document.querySelectorAll('button.nldesign-audit-restore')[1])

		const preview = calls.find(
			(c) =>
				c.url
				&& c.url.indexOf('/settings/versions/20260929164000-0001/preview')
					!== -1,
		)
		expect(preview.method).toBe('POST')
		const dialog = calls.find((c) => c.dialog).dialog
		expect(dialog).toContain('Restore this version?')
		expect(dialog).toContain('tokenSet: custom-bad becomes rijkshuisstijl')
		expect(dialog).toContain('custom-bad')
		expect(dialog).toContain('Gone Sans')
		const restore = calls.find(
			(c) =>
				c.url
				&& c.url.indexOf('/settings/versions/20260929164000-0001/restore')
					!== -1,
		)
		expect(restore.method).toBe('POST')
	})

	it('changes nothing on cancel and returns focus to the button', async () => {
		confirmAnswer = false
		await load()

		const button = document.querySelectorAll('button.nldesign-audit-restore')[1]
		await pressRestore(button)

		expect(document.getElementById('nldesign-restore-version-dialog')).toBeNull()
		expect(calls.some((c) => c.url && c.url.indexOf('/restore') !== -1)).toBe(
			false,
		)
		expect(document.activeElement).toBe(button)
	})

	it('says so and returns focus when the restore answers with an error page', async () => {
		confirmAnswer = true
		await load()
		const answer = global.fetch.getMockImplementation()
		global.fetch.mockImplementation((url, options) => {
			if (url.indexOf('/restore') !== -1) {
				return Promise.resolve({
					status: 423,
					ok: false,
					json: () => Promise.reject(new SyntaxError('not JSON')),
				})
			}
			return answer(url, options)
		})
		vi.spyOn(console, 'error').mockImplementation(() => {})

		const button = document.querySelectorAll('button.nldesign-audit-restore')[1]
		await pressRestore(button)

		expect(OC.Notification.showTemporary).toHaveBeenCalledWith(
			'The version was not restored. Nothing was changed.',
		)
		expect(document.activeElement).toBe(button)
	})

	it('names the version, warns about a missing font and closes on Escape with focus back', async () => {
		confirmAnswer = false
		await load()
		const button = document.querySelectorAll('button.nldesign-audit-restore')[1]
		button.click()
		await flush()

		const dialog = document.getElementById('nldesign-restore-version-dialog')
		expect(dialog.querySelector('.thematiq-admin-dialog__eyebrow').textContent).toMatch(/^Log, version of /)
		expect(dialog.querySelector('.thematiq-admin-dialog__notice--caution').textContent).toContain('Gone Sans')
		dialog.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))

		expect(document.getElementById('nldesign-restore-version-dialog')).toBeNull()
		expect(document.activeElement).toBe(button)
		expect(calls.some((c) => c.url && c.url.indexOf('/restore') !== -1)).toBe(false)
	})
})
