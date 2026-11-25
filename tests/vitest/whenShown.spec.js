/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/lib/whenShown.js and the panel-only loaders that use it:
 * a block inside a closed panel of the Theming admin page asks the server for
 * nothing until its panel opens; a block on the overview loads at once.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

async function flush() {
	for (let i = 0; i < 6; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

async function loadHelper() {
	vi.resetModules()
	delete window.ThematiqWhenShown
	await import('../../js/lib/whenShown.js?t=' + Math.random())
}

describe('ThematiqWhenShown', () => {
	beforeEach(async () => {
		await loadHelper()
	})

	afterEach(() => {
		document.body.innerHTML = ''
	})

	it('runs at once for a block outside any panel and returns its result', () => {
		document.body.innerHTML = '<div id="block"></div>'
		const run = vi.fn(() => 'started')
		expect(window.ThematiqWhenShown(document.getElementById('block'), run)).toBe(
			'started',
		)
		expect(run).toHaveBeenCalledTimes(1)
	})

	it('runs at once for a block in an open panel', () => {
		document.body.innerHTML =
			'<section data-thematiq-panel="brands"><div id="block"></div></section>'
		const run = vi.fn()
		window.ThematiqWhenShown(document.getElementById('block'), run)
		expect(run).toHaveBeenCalledTimes(1)
	})

	it('runs at once when the block is missing, so the loader can bail out itself', () => {
		const run = vi.fn()
		window.ThematiqWhenShown(null, run)
		expect(run).toHaveBeenCalledTimes(1)
	})

	it('waits while the panel is closed and runs once when it opens', async () => {
		document.body.innerHTML =
			'<section data-thematiq-panel="brands" hidden><div id="block"></div></section>'
		const section = document.querySelector('section')
		const run = vi.fn()
		expect(
			window.ThematiqWhenShown(document.getElementById('block'), run),
		).toBeUndefined()
		await flush()
		expect(run).not.toHaveBeenCalled()

		section.hidden = false
		await flush()
		expect(run).toHaveBeenCalledTimes(1)

		section.hidden = true
		await flush()
		section.hidden = false
		await flush()
		expect(run).toHaveBeenCalledTimes(1)
	})
})

describe('panel-only loaders', () => {
	beforeEach(async () => {
		global.t = (app, text) => text
		global.OC = {
			generateUrl: (url) => url,
			linkTo: (app, path) => path,
			requestToken: 'token',
		}
		global.fetch = vi.fn(() =>
			Promise.resolve({ ok: true, json: () => Promise.resolve({}) }),
		)
		await loadHelper()
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('Brand per app asks for nothing until the brands panel opens', async () => {
		document.body.innerHTML = `
			<section data-thematiq-panel="brands" hidden>
				<div id="nldesign-app-brands-list"></div>
				<select id="nldesign-app-brands-app"></select>
				<select id="nldesign-app-brands-set"></select>
				<button id="nldesign-app-brands-add"></button>
				<span id="nldesign-app-brands-feedback"></span>
			</section>`
		await import('../../js/admin-app-brands.js?t=' + Math.random())
		await flush()
		expect(global.fetch).not.toHaveBeenCalled()

		document.querySelector('section').hidden = false
		await flush()
		expect(global.fetch).toHaveBeenCalled()
		expect(String(global.fetch.mock.calls[0][0])).toContain(
			'/apps/thematiq/settings/app-brands',
		)
	})

	it('the workspace icons block asks for nothing until the brands panel opens', async () => {
		document.body.innerHTML = `
			<section data-thematiq-panel="brands" hidden>
				<div id="nldesign-workspace-icons-list"></div>
				<span id="nldesign-workspace-icons-feedback"></span>
			</section>`
		await import('../../js/admin-workspace-icons.js?t=' + Math.random())
		await flush()
		expect(global.fetch).not.toHaveBeenCalled()

		document.querySelector('section').hidden = false
		await flush()
		expect(String(global.fetch.mock.calls[0][0])).toContain(
			'/apps/thematiq/settings/workspace-icons',
		)
	})

	it('the document style block asks for nothing until the email panel opens', async () => {
		document.body.innerHTML = `
			<section data-thematiq-panel="email" hidden>
				<div id="nldesign-documents-preview"></div>
				<span id="nldesign-documents-feedback"></span>
				<input id="nldesign-documents-footer-line">
				<button id="nldesign-documents-footer-save"></button>
			</section>`
		await import('../../js/admin-documents.js?t=' + Math.random())
		await flush()
		expect(global.fetch).not.toHaveBeenCalled()

		document.querySelector('section').hidden = false
		await flush()
		expect(String(global.fetch.mock.calls[0][0])).toContain(
			'/apps/thematiq/settings/document-style',
		)
	})

	it('the assistant mark block asks for nothing until the assistant panel opens', async () => {
		document.body.innerHTML = `
			<section data-thematiq-panel="assistant" hidden>
				<input type="checkbox" id="nldesign-assistant-mark-enabled">
				<input id="nldesign-assistant-mark-organisation">
				<input type="file" id="nldesign-assistant-mark-logo">
				<div id="nldesign-assistant-mark-preview"></div>
				<button id="nldesign-assistant-mark-save"></button>
				<span id="nldesign-assistant-mark-feedback"></span>
			</section>`
		await import('../../js/admin-assistant-mark.js?t=' + Math.random())
		await flush()
		expect(global.fetch).not.toHaveBeenCalled()

		document.querySelector('section').hidden = false
		await flush()
		expect(String(global.fetch.mock.calls[0][0])).toContain(
			'/apps/thematiq/settings/assistant-mark',
		)
	})

	it('a block on the overview still loads at once', async () => {
		document.body.innerHTML = `
			<div id="nldesign-workspace-icons-list"></div>
			<span id="nldesign-workspace-icons-feedback"></span>`
		await import('../../js/admin-workspace-icons.js?t=' + Math.random())
		await flush()
		expect(global.fetch).toHaveBeenCalled()
	})
})
