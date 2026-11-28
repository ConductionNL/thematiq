/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin.js with js/lib/whenShown.js: the audit log, the
 * theme gallery, the upstream freshness block, the group theming list and the
 * email template block sit
 * in closed panels of the Theming page and ask the server for nothing until
 * their panel opens; opening it loads them once.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

function install() {
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
	global.fetch = vi.fn((url) => {
		let body = {}
		if (String(url).indexOf('/settings/audit') !== -1) {
			body = { entries: [] }
		} else if (String(url).indexOf('/settings/gallery') !== -1) {
			body = { enabled: false, entries: [] }
		} else if (String(url).indexOf('/settings/upstream-freshness') !== -1) {
			body = { enabled: false, notices: [] }
		} else if (String(url).indexOf('/settings/group-theming') !== -1) {
			body = { mapping: [] }
		} else if (String(url).indexOf('/settings/email-theming') !== -1) {
			body = {
				state: { state: 'enabled' },
				footer: { orgName: 'Gemeente Zuiddrecht' },
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

/** The GET urls fetched so far that contain `part`. */
function fetched(part) {
	return global.fetch.mock.calls
		.map((call) => String(call[0]))
		.filter((url) => url.indexOf(part) !== -1)
}

async function load(hidden) {
	const attr = hidden === true ? ' hidden' : ''
	document.body.innerHTML = `
		<div id="nldesign-settings" class="section">
			<section data-thematiq-panel="log"${attr}>
				<table><tbody id="nldesign-audit-table-body"></tbody></table>
				<button type="button" id="nldesign-audit-download-btn">Download</button>
			</section>
			<section data-thematiq-panel="gallery"${attr}>
				<input type="checkbox" id="nldesign-gallery-toggle">
				<span id="nldesign-gallery-toggle-hint"></span>
				<span id="nldesign-gallery-status"></span>
				<div id="nldesign-gallery-list"></div>
			</section>
			<section data-thematiq-panel="email"${attr}>
				<div id="nldesign-email-theming">
					<input type="checkbox" id="nldesign-email-theming-enabled">
					<input id="nldesign-email-footer-org-name">
					<button type="button" id="nldesign-email-theming-save">Save</button>
				</div>
			</section>
		</div>
	`
	vi.resetModules()
	delete window.ThematiqWhenShown
	await import('../../js/lib/whenShown.js?t=' + Math.random())
	await import('../../js/lib/auditFormat.js?t=' + Math.random())
	await import('../../js/dialogs/admin-dialog.js?t=' + Math.random())
	await import('../../js/admin.js?t=' + Math.random())
	await flush()
}

describe('admin.js panel loaders wait for their panel', () => {
	beforeEach(() => {
		install()
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('asks for no audit log and no gallery while their panels are closed', async () => {
		await load(true)
		expect(fetched('/settings/audit')).toHaveLength(0)
		expect(fetched('/settings/gallery')).toHaveLength(0)
	})

	it('loads the audit log once when the log panel opens, and not the closed gallery', async () => {
		await load(true)
		document.querySelector('section[data-thematiq-panel="log"]').hidden = false
		await flush()
		expect(fetched('/settings/audit?limit=20')).toHaveLength(1)
		expect(fetched('/settings/gallery')).toHaveLength(0)

		document.querySelector('section[data-thematiq-panel="log"]').hidden = true
		await flush()
		document.querySelector('section[data-thematiq-panel="log"]').hidden = false
		await flush()
		expect(fetched('/settings/audit?limit=20')).toHaveLength(1)
	})

	it('loads the gallery when the gallery panel opens', async () => {
		await load(true)
		document.querySelector('section[data-thematiq-panel="gallery"]').hidden =
			false
		await flush()
		expect(fetched('/settings/gallery').length).toBeGreaterThan(0)
	})

	it('loads at once when the panel is already open (a deep link)', async () => {
		await load(false)
		expect(fetched('/settings/audit?limit=20')).toHaveLength(1)
		expect(fetched('/settings/gallery').length).toBeGreaterThan(0)
	})
	it('loads the email template settings when the email panel opens, and tells the panel', async () => {
		await load(true)
		expect(fetched('/settings/email-theming')).toHaveLength(0)
		const told = vi.fn()
		document.addEventListener('thematiq:email-theming', told)
		document.querySelector('section[data-thematiq-panel="email"]').hidden = false
		await flush()
		document.removeEventListener('thematiq:email-theming', told)
		expect(fetched('/settings/email-theming')).toHaveLength(1)
		expect(
			document.getElementById('nldesign-email-theming-enabled').checked,
		).toBe(true)
		expect(document.getElementById('nldesign-email-footer-org-name').value).toBe(
			'Gemeente Zuiddrecht',
		)
		expect(told).toHaveBeenCalledTimes(1)
	})
})
