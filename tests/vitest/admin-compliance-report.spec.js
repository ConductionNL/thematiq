/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin.js: the contrast evidence report download links.
 * Until 29 Sep 2026 the export endpoint existed and nothing on the settings
 * page linked to it. admin.js is a vanilla-JS IIFE with no exports, so the
 * test loads it against a minimal settings DOM and reads the links it fills.
 * Both downloads, the configuration bundle's and the audit log's, are fetched with the request
 * token: the endpoints are CSRF-protected, and a followed link carried none.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/compliance-evidence/spec.md
 * @spec openspec/specs/config-portability/spec.md
 * @spec openspec/specs/theming-audit/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/** The status the download endpoints answer with. */
let downloadStatus = 200

/** Minimal globals admin.js reads at load time. */
function installGlobals() {
	global.t = (app, text) => text
	global.n = (app, singular, plural, count) => (count === 1 ? singular : plural)
	global.OC = {
		generateUrl: (url) => '/index.php' + url,
		linkTo: (app, path) => path,
		requestToken: 'test-token',
		Notification: { showTemporary: vi.fn() },
		dialogs: { confirm: vi.fn() },
	}
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) => fallback,
		},
	}
	global.fetch = vi.fn((url) =>
		Promise.resolve({
			status: downloadStatus,
			ok: downloadStatus < 400,
			headers: {
				get: (name) =>
					name === 'Content-Disposition'
					&& url.indexOf('format=json') !== -1
						? 'attachment; filename="thematiq-contrast-report.json"'
						: null,
			},
			json: () => Promise.resolve({}),
			blob: () => Promise.resolve(new Blob(['{}'])),
		}),
	)
}

/** Flush pending promise chains. */
async function flush(rounds = 8) {
	for (let i = 0; i < rounds; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

describe('admin.js contrast evidence report', () => {
	beforeEach(() => {
		downloadStatus = 200
		installGlobals()
		URL.createObjectURL = vi.fn(() => 'blob:download')
		URL.revokeObjectURL = vi.fn()
		document.body.innerHTML = `
			<div id="nldesign-settings" class="section">
				<div id="nldesign-compliance-report">
					<a id="nldesign-compliance-report-json" class="button" download>JSON</a>
					<a id="nldesign-compliance-report-markdown" class="button" download>Markdown</a>
				</div>
				<button type="button" id="nldesign-config-bundle-download-btn">Download configuration</button>
				<input type="file" id="nldesign-config-bundle-input">
				<button type="button" id="nldesign-config-bundle-upload-btn">Upload configuration</button>
				<table><tbody id="nldesign-audit-table-body"></tbody></table>
				<button type="button" id="nldesign-audit-download-btn">Download full audit log</button>
			</div>
		`
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('points both links at the export endpoint with their format', async () => {
		vi.resetModules()
		await import('../../js/admin.js?t=' + Math.random())
		await flush()

		expect(
			document
				.getElementById('nldesign-compliance-report-json')
				.getAttribute('href'),
		).toBe('/index.php/apps/thematiq/settings/compliance-report?format=json')
		expect(
			document
				.getElementById('nldesign-compliance-report-markdown')
				.getAttribute('href'),
		).toBe('/index.php/apps/thematiq/settings/compliance-report?format=markdown')
	})

	/** Load admin.js and catch the file name the browser is asked to save. */
	async function loadCatchingDownloads() {
		vi.resetModules()
		await import('../../js/admin.js?t=' + Math.random())
		await flush()
		const saved = vi.fn()
		vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(
			function () {
				if (this.href.indexOf('blob:') === 0) {
					saved(this.download)
				}
			},
		)
		return saved
	}

	/** Click an element the way the browser does. */
	function click(el) {
		el.dispatchEvent(
			new MouseEvent('click', { bubbles: true, cancelable: true }),
		)
	}

	it('fetches each report with the request token and saves it', async () => {
		const saved = await loadCatchingDownloads()

		click(document.getElementById('nldesign-compliance-report-json'))
		click(document.getElementById('nldesign-compliance-report-markdown'))
		await flush()

		expect(fetch).toHaveBeenCalledWith(
			'/index.php/apps/thematiq/settings/compliance-report?format=json',
			{ headers: { requesttoken: 'test-token' } },
		)
		expect(fetch).toHaveBeenCalledWith(
			'/index.php/apps/thematiq/settings/compliance-report?format=markdown',
			{ headers: { requesttoken: 'test-token' } },
		)
		// The server's name where it gives one, the format's otherwise.
		expect(saved).toHaveBeenCalledWith('thematiq-contrast-report.json')
		expect(saved).toHaveBeenCalledWith('contrast-report.md')
	})

	it('says so when a report is refused', async () => {
		downloadStatus = 412
		const saved = await loadCatchingDownloads()
		vi.spyOn(console, 'error').mockImplementation(() => {})

		click(document.getElementById('nldesign-compliance-report-json'))
		await flush()

		expect(saved).not.toHaveBeenCalled()
		expect(OC.Notification.showTemporary).toHaveBeenCalledWith(
			'The contrast report could not be downloaded.',
		)
	})

	it('fetches the configuration bundle with the request token and saves it', async () => {
		const saved = await loadCatchingDownloads()

		click(document.getElementById('nldesign-config-bundle-download-btn'))
		await flush()

		expect(fetch).toHaveBeenCalledWith(
			'/index.php/apps/thematiq/settings/config/export',
			{ headers: { requesttoken: 'test-token' } },
		)
		expect(saved).toHaveBeenCalledWith('thematiq-config.json')
	})

	it('fetches the full audit log with the request token and saves it', async () => {
		const saved = await loadCatchingDownloads()

		click(document.getElementById('nldesign-audit-download-btn'))
		await flush()

		expect(fetch).toHaveBeenCalledWith(
			'/index.php/apps/thematiq/settings/audit/export',
			{ headers: { requesttoken: 'test-token' } },
		)
		expect(saved).toHaveBeenCalledWith('nldesign-audit.jsonl')
	})
})
