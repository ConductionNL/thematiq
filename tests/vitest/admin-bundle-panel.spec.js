/**
 * @vitest-environment jsdom
 *
 * The Configuration bundle panel (js/admin-bundle-panel.js, the TqBundel
 * board): what the bundle holds, and the header's download and upload.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const BUNDLE = {
	config: {
		tokenSet: 'zuiddrecht',
		hideSlogan: true,
		showMenuLabels: false,
		primaryDrivesComponents: false,
		layoutOptions: { a: 1, b: 2 },
		disabledApps: ['mail', 'deck'],
		upstreamFreshnessEnabled: true,
	},
	customOverridesCss: ':root { --color-primary: #fff; --color-text: #000; }',
	customTokenSets: [{ id: 'custom-a' }],
	emailFooter: { orgName: 'Zuiddrecht' },
}

const PAGE = `
	<section id="thematiq-page-header">
		<h1 class="thematiq-page-header__title">Themes and appearance</h1>
		<div class="thematiq-page-header__actions"></div>
		<nav class="thematiq-page-header__crumbs"></nav>
		<p class="thematiq-page-header__intro"></p>
	</section>
	<div id="nldesign-settings">
		<select id="nldesign-token-set-select"><option value="zuiddrecht" selected>Gemeente Zuiddrecht</option></select>
		<section class="thematiq-panel" data-thematiq-panel="bundle" hidden>
			<div class="thematiq-bundle-panel__rows"></div>
			<button type="button" id="nldesign-config-bundle-download-btn" hidden>Download</button>
			<button type="button" id="nldesign-config-bundle-upload-btn" hidden>Upload</button>
		</section>
	</div>
`

const flush = () => new Promise((resolve) => setTimeout(resolve, 0))

describe('Configuration bundle panel', () => {
	let added = []

	beforeEach(async () => {
		window.t = (app, text, vars) =>
			String(text).replace(/\{(\w+)\}/g, (all, key) =>
				vars && key in vars ? String(vars[key]) : all,
			)
		window.OC = { generateUrl: (url) => url, requestToken: 'token' }
		window.scrollTo = vi.fn()
		global.fetch = vi.fn(() =>
			Promise.resolve({ json: () => Promise.resolve(BUNDLE) }),
		)
		document.body.innerHTML = PAGE
		window.history.replaceState(null, '', window.location.pathname)
		for (const target of [window, document]) {
			const original = target.addEventListener.bind(target)
			vi.spyOn(target, 'addEventListener').mockImplementation(
				(type, listener, options) => {
					added.push([target, type, listener])
					original(type, listener, options)
				},
			)
		}
		vi.resetModules()
		await import('../../js/admin-panels.js?t=' + Math.random())
		await import('../../js/admin-bundle-panel.js?t=' + Math.random())
		window.history.replaceState(null, '', '#bundle')
		window.dispatchEvent(new HashChangeEvent('hashchange'))
		await flush()
		await flush()
	})

	afterEach(() => {
		vi.restoreAllMocks()
		added.forEach(([target, type, listener]) =>
			target.removeEventListener(type, listener),
		)
		added = []
		document.body.innerHTML = ''
		delete window.ThematiqPanels
		delete document.documentElement.dataset.thematiqOpenPanel
	})

	it('lists what the bundle holds', () => {
		expect(global.fetch).toHaveBeenCalledWith(
			'/apps/thematiq/settings/config/export',
			{ headers: { requesttoken: 'token' } },
		)
		const rows = [
			...document.querySelectorAll('.thematiq-bundle-panel__rows > div'),
		].map((row) => row.textContent)
		expect(rows).toEqual([
			'Active token setGemeente Zuiddrecht',
			'Switches and layout5 options',
			'Theme per app2 apps off',
			'Token overrides2 tokens',
			'Own token sets1 set',
			'Email footerset',
			'Updates from the sourceon',
		])
	})

	it('downloads and uploads through the panel buttons', () => {
		const pressed = vi.fn()
		document
			.getElementById('nldesign-config-bundle-upload-btn')
			.addEventListener('click', pressed)
		const buttons = document.querySelectorAll(
			'.thematiq-page-header__actions button',
		)
		expect([...buttons].map((b) => b.textContent)).toEqual([
			'Download configuration',
			'Upload configuration',
		])
		buttons[1].click()
		expect(pressed).toHaveBeenCalledTimes(1)
	})
})
