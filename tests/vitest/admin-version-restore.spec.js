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
	{ ts: '2026-09-29T17:00:00Z', actor: 'admin', action: 'custom_set_uploaded', new: 'custom-bad', versionId: '20260929170000-0001' },
	{ ts: '2026-09-29T16:40:00Z', actor: 'admin', action: 'token_set_changed', new: 'rijkshuisstijl', versionId: '20260929164000-0001' },
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
			: Object.keys(params).reduce((acc, key) => acc.replace('{' + key + '}', params[key]), text)
	global.n = (app, singular, plural, count) => (count === 1 ? singular : plural)
	global.OC = {
		generateUrl: (url) => url,
		linkTo: (app, path) => path,
		requestToken: 'test-token',
		Notification: { showTemporary: vi.fn() },
		dialogs: {
			confirm: vi.fn((text, title, callback) => {
				calls.push({ dialog: text })
				callback(confirmAnswer)
			}),
		},
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
		return Promise.resolve({ status: 200, ok: true, json: () => Promise.resolve(body) })
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
	await import('../../js/admin.js?t=' + Math.random())
	await flush()
}

describe('admin.js version restore', () => {
	beforeEach(() => {
		install()
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
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

		document.querySelectorAll('button.nldesign-audit-restore')[1].click()
		await flush()

		const preview = calls.find((c) => c.url && c.url.indexOf('/settings/versions/20260929164000-0001/preview') !== -1)
		expect(preview.method).toBe('POST')
		const dialog = calls.find((c) => c.dialog).dialog
		expect(dialog).toContain('tokenSet: custom-bad to rijkshuisstijl')
		expect(dialog).toContain('custom-bad')
		expect(dialog).toContain('Gone Sans')
		const restore = calls.find((c) => c.url && c.url.indexOf('/settings/versions/20260929164000-0001/restore') !== -1)
		expect(restore.method).toBe('POST')
	})

	it('changes nothing on cancel and returns focus to the button', async () => {
		confirmAnswer = false
		await load()

		const button = document.querySelectorAll('button.nldesign-audit-restore')[1]
		button.click()
		await flush()

		expect(calls.some((c) => c.url && c.url.indexOf('/restore') !== -1)).toBe(false)
		expect(document.activeElement).toBe(button)
	})
})
