/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin.js: the contrast evidence report download links.
 * Until 29 Sep 2026 the export endpoint existed and nothing on the settings
 * page linked to it. admin.js is a vanilla-JS IIFE with no exports, so the
 * test loads it against a minimal settings DOM and reads the links it fills.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/accessibility-evidence-download-and-dark-logo/specs/compliance-evidence/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

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
	global.fetch = vi.fn(() =>
		Promise.resolve({ status: 200, json: () => Promise.resolve({}) }),
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
		installGlobals()
		document.body.innerHTML = `
			<div id="nldesign-settings" class="section">
				<div id="nldesign-compliance-report">
					<a id="nldesign-compliance-report-json" class="button" download>JSON</a>
					<a id="nldesign-compliance-report-markdown" class="button" download>Markdown</a>
				</div>
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
			document.getElementById('nldesign-compliance-report-json').getAttribute('href'),
		).toBe('/index.php/apps/thematiq/settings/compliance-report?format=json')
		expect(
			document
				.getElementById('nldesign-compliance-report-markdown')
				.getAttribute('href'),
		).toBe('/index.php/apps/thematiq/settings/compliance-report?format=markdown')
	})
})
