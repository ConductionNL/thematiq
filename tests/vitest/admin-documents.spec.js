/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin-documents.js: the Documents block previews the
 * profile, uploads a print logo and shows the refusal of an unsafe file.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/document-house-style/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

let answers

function profile(overrides) {
	return Object.assign(
		{
			tokenSet: { id: 'rijkshuisstijl', name: 'Rijkshuisstijl' },
			logo: {
				url: '/apps/thematiq/img/logos/rijkshuisstijl.svg',
				mime: 'image/svg+xml',
			},
			colours: { primary: '#154273', text: '#000000' },
			fonts: {
				heading: { family: 'RijksoverheidSans', url: null },
				body: { family: 'RijksoverheidSans', url: null },
			},
			footer: { lines: ['Gemeente Voorbeeld'] },
			warnings: [],
		},
		overrides || {},
	)
}

async function flush() {
	for (let i = 0; i < 6; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

async function loadScript() {
	vi.resetModules()
	await import('../../js/admin-documents.js?t=' + Math.random())
	await flush()
}

describe('Documents block', () => {
	beforeEach(() => {
		global.t = (app, text) => text
		global.OC = { generateUrl: (url) => url, requestToken: 'token' }
		answers = { get: { footerLine: '', assets: {}, profile: profile() } }
		global.fetch = vi.fn((url, options) => {
			const answer =
				options && options.method
					? answers[options.method]
					: { ok: true, body: answers.get }
			return Promise.resolve({
				ok: answer.ok,
				json: () => Promise.resolve(answer.body),
			})
		})
		document.body.innerHTML = `
			<input type="file" id="nldesign-documents-logo">
			<button id="nldesign-documents-logo-remove"></button>
			<input type="file" id="nldesign-documents-cover">
			<button id="nldesign-documents-cover-remove"></button>
			<input id="nldesign-documents-footer-line">
			<button id="nldesign-documents-footer-save"></button>
			<div id="nldesign-documents-preview"></div>
			<span id="nldesign-documents-feedback"></span>`
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('previews the profile with the house style logo', async () => {
		await loadScript()
		const preview = document.getElementById('nldesign-documents-preview')
		expect(preview.querySelector('img').getAttribute('src')).toBe(
			'/apps/thematiq/img/logos/rijkshuisstijl.svg',
		)
		expect(preview.textContent).toContain('Rijkshuisstijl')
		expect(preview.textContent).toContain('Gemeente Voorbeeld')
		expect(preview.querySelector('.nldesign-documents-warning')).toBeNull()
	})

	it('uploads a print logo and shows the new profile', async () => {
		answers.POST = {
			ok: true,
			body: {
				footerLine: '',
				assets: { logo: { mime: 'image/png' } },
				profile: profile({
					logo: { url: '/print-logo', mime: 'image/png' },
				}),
			},
		}
		await loadScript()
		const input = document.getElementById('nldesign-documents-logo')
		Object.defineProperty(input, 'files', {
			value: [new File(['x'], 'logo.png')],
		})
		input.dispatchEvent(new Event('change'))
		await flush()

		const post = global.fetch.mock.calls.find(
			(c) => c[1] && c[1].method === 'POST',
		)
		expect(post[0]).toBe('/apps/thematiq/settings/document-style/logo')
		expect(post[1].body).toBeInstanceOf(FormData)
		expect(
			document
				.querySelector('#nldesign-documents-preview img')
				.getAttribute('src'),
		).toBe('/print-logo')
	})

	it('shows the refusal of an SVG with script and a contrast warning', async () => {
		answers.get.profile = profile({
			warnings: [{ code: 'text-contrast', ratio: 2 }],
		})
		answers.POST = {
			ok: false,
			body: {
				error: 'Upload a PNG, JPEG or WebP image, or an SVG without script.',
			},
		}
		await loadScript()
		expect(document.querySelector('.nldesign-documents-warning')).not.toBeNull()

		const input = document.getElementById('nldesign-documents-cover')
		Object.defineProperty(input, 'files', {
			value: [new File(['<svg><script/></svg>'], 'x.svg')],
		})
		input.dispatchEvent(new Event('change'))
		await flush()
		expect(
			document.getElementById('nldesign-documents-feedback').textContent,
		).toContain('SVG without script')
	})

	it('saves the footer line and removes an image, each with its message', async () => {
		answers.POST = { ok: true, body: { footerLine: 'Gemeente Zuiddrecht', assets: {}, profile: profile() } }
		answers.DELETE = { ok: true, body: { footerLine: 'Gemeente Zuiddrecht', assets: {}, profile: profile({ logo: null }) } }
		await loadScript()
		const feedback = document.getElementById('nldesign-documents-feedback')
		document.getElementById('nldesign-documents-footer-line').value = 'Gemeente Zuiddrecht'
		document.getElementById('nldesign-documents-footer-save').click()
		await flush()

		const post = global.fetch.mock.calls.find((c) => c[1] && c[1].method === 'POST')
		expect(post[0]).toBe('/apps/thematiq/settings/document-style')
		expect(post[1].headers).toEqual({ requesttoken: 'token', 'Content-Type': 'application/json' })
		expect(JSON.parse(post[1].body)).toEqual({ footerLine: 'Gemeente Zuiddrecht' })
		expect(feedback.textContent).toBe('Footer line saved.')

		document.getElementById('nldesign-documents-logo-remove').click()
		await flush()
		const del = global.fetch.mock.calls.find((c) => c[1] && c[1].method === 'DELETE')
		expect(del[0]).toBe('/apps/thematiq/settings/document-style/logo')
		expect(feedback.textContent).toBe('Image removed.')
		expect(document.querySelector('#nldesign-documents-preview img')).toBeNull()
	})

	it('says a save failed in its own words when the server gives none, or the request fails', async () => {
		answers.POST = { ok: false, body: {} }
		await loadScript()
		const feedback = document.getElementById('nldesign-documents-feedback')
		document.getElementById('nldesign-documents-footer-save').click()
		await flush()
		expect(feedback.textContent).toBe('The document settings could not be saved.')

		global.fetch.mockImplementation(() => Promise.reject(new Error('offline')))
		feedback.textContent = 'stale'
		document.getElementById('nldesign-documents-footer-save').click()
		await flush()
		expect(feedback.textContent).toBe('The document settings could not be saved.')
	})

	it('opens the picker from its button, shows the chosen name, and sends nothing without a file', async () => {
		document.body.insertAdjacentHTML('beforeend', '<button id="nldesign-documents-logo-btn"></button><span id="nldesign-documents-logo-name"></span>')
		await loadScript()
		const input = document.getElementById('nldesign-documents-logo')
		const click = vi.spyOn(input, 'click').mockImplementation(() => {})
		document.getElementById('nldesign-documents-logo-btn').click()
		expect(click).toHaveBeenCalled()

		Object.defineProperty(input, 'files', { value: [], configurable: true })
		input.dispatchEvent(new Event('change'))
		await flush()
		expect(document.getElementById('nldesign-documents-logo-name').textContent).toBe('No file chosen')
		expect(global.fetch.mock.calls.some((c) => c[1] && c[1].method === 'POST')).toBe(false)

		answers.POST = { ok: true, body: { footerLine: '', assets: {}, profile: profile() } }
		Object.defineProperty(input, 'files', { value: [new File(['x'], 'print.png')], configurable: true })
		input.dispatchEvent(new Event('change'))
		expect(document.getElementById('nldesign-documents-logo-name').textContent).toBe('print.png')
	})

	it('logs settings that cannot be loaded, and does nothing without its block', async () => {
		global.fetch.mockImplementation(() => Promise.reject(new Error('offline')))
		const error = vi.spyOn(console, 'error').mockImplementation(() => {})
		await loadScript()
		expect(error).toHaveBeenCalledWith('Error loading the document settings:', expect.any(Error))

		global.fetch.mockClear()
		document.body.innerHTML = '<div id="nldesign-documents-preview"></div>'
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
		expect(document.querySelector('#nldesign-documents-preview img')).not.toBeNull()
	})
})
