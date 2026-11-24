/**
 * @vitest-environment jsdom
 *
 * js/preview-banner.js: the banner of a theme preview, its Publish link and
 * its Discard button, which ends the preview or says it could not.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/theme-preview/spec.md#requirement-preview-banner
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

let reload

/**
 * Give the page the `thematiq/preview` state and the OCP and OC globals.
 *
 * @param {*} preview The preview state.
 * @param {object} extra More of OCP, such as Toast.
 */
function installState(preview, extra = {}) {
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) =>
				app === 'thematiq' && key === 'preview' ? preview : fallback,
		},
		...extra,
	}
	window.OCP = global.OCP
}

/**
 * Run the script on the current page.
 *
 * @return {Promise<void>}
 */
async function loadScript() {
	vi.resetModules()
	await import('../../js/preview-banner.js?t=' + Math.random())
}

const flush = async () => {
	for (let i = 0; i < 10; i++) {
		await Promise.resolve()
	}
}
const banner = () => document.getElementById('nldesign-preview-banner')

describe('the theme preview banner', () => {
	beforeEach(() => {
		document.body.innerHTML = ''
		global.t = (app, text, values = {}) =>
			text.replace(/\{(\w+)\}/g, (match, key) => values[key])
		global.OC = {
			generateUrl: (url) => '/index.php' + url,
			requestToken: 'token',
		}
		reload = vi.fn()
		vi.spyOn(window, 'location', 'get').mockReturnValue({ reload })
	})

	afterEach(() => {
		delete global.OCP
		delete window.OCP
		delete global.OC
		delete global.t
		delete global.fetch
		vi.restoreAllMocks()
	})

	it('shows the preview by name as a status, with a Publish link to the theming settings', async () => {
		installState({ tokenSet: 'zuiddrecht', name: 'Zuiddrecht' })
		await loadScript()

		expect(banner().getAttribute('role')).toBe('status')
		expect(
			banner().querySelector('.nldesign-preview-banner-text').textContent,
		).toBe('Preview: Zuiddrecht — this is only visible to you')
		const publish = banner().querySelector('a.nldesign-preview-banner-publish')
		expect(publish.getAttribute('href')).toBe(
			'/index.php/settings/admin/theming#nldesign-settings',
		)
		expect(publish.textContent).toBe('Publish')
		const discard = banner().querySelector(
			'button.nldesign-preview-banner-discard',
		)
		expect(discard.type).toBe('button')
		expect(discard.textContent).toBe('Discard')
	})

	it('names the token set when the preview has no name', async () => {
		installState({ tokenSet: 'rijkshuisstijl' })
		await loadScript()
		expect(banner().textContent).toContain('Preview: rijkshuisstijl')
	})

	it.each([
		['no preview', null],
		['a preview without a token set', { name: 'Zuiddrecht' }],
	])('shows nothing with %s', async (label, preview) => {
		installState(preview)
		await loadScript()
		expect(banner()).toBeNull()
	})

	it('shows nothing without OCP or its initial state', async () => {
		await loadScript()
		expect(banner()).toBeNull()

		global.OCP = { InitialState: { loadState: 'no function' } }
		await loadScript()
		expect(banner()).toBeNull()
	})

	it('waits for DOMContentLoaded while the page is loading', async () => {
		installState({ tokenSet: 'zuiddrecht' })
		const readyState = vi
			.spyOn(document, 'readyState', 'get')
			.mockReturnValue('loading')
		await loadScript()
		expect(banner()).toBeNull()

		readyState.mockReturnValue('complete')
		document.dispatchEvent(new Event('DOMContentLoaded'))
		expect(banner()).not.toBeNull()
	})

	it('discards the preview with a DELETE and reloads the page', async () => {
		installState({ tokenSet: 'zuiddrecht' })
		global.fetch = vi.fn(() => Promise.resolve({ ok: true }))
		await loadScript()
		const discard = banner().querySelector('button')
		discard.click()

		expect(discard.disabled).toBe(true)
		expect(global.fetch).toHaveBeenCalledWith(
			'/index.php/apps/thematiq/settings/preview',
			{
				method: 'DELETE',
				headers: { requesttoken: 'token' },
			},
		)
		await flush()
		expect(reload).toHaveBeenCalled()
	})

	it('enables Discard again and shows a toast when the discard fails', async () => {
		const message = vi.fn()
		installState({ tokenSet: 'zuiddrecht' }, { Toast: { message } })
		global.fetch = vi.fn(() => Promise.reject(new Error('offline')))
		const error = vi.spyOn(console, 'error').mockImplementation(() => {})
		await loadScript()
		const discard = banner().querySelector('button')
		discard.click()
		await flush()

		expect(reload).not.toHaveBeenCalled()
		expect(discard.disabled).toBe(false)
		expect(error).toHaveBeenCalled()
		expect(message).toHaveBeenCalledWith('Failed to discard theme preview.')
	})

	it('does not throw on a failed discard when there is no toast, or the toast throws', async () => {
		installState({ tokenSet: 'zuiddrecht' })
		global.fetch = vi.fn(() => Promise.reject(new Error('offline')))
		vi.spyOn(console, 'error').mockImplementation(() => {})
		const info = vi.spyOn(console, 'info').mockImplementation(() => {})
		await loadScript()
		banner().querySelector('button').click()
		await flush()
		expect(banner().querySelector('button').disabled).toBe(false)
		expect(info).not.toHaveBeenCalled()

		document.body.innerHTML = ''
		installState(
			{ tokenSet: 'zuiddrecht' },
			{
				Toast: {
					message: () => {
						throw new Error('toast')
					},
				},
			},
		)
		await loadScript()
		banner().querySelector('button').click()
		await flush()
		expect(info).toHaveBeenCalledWith(
			'[nldesign] Failed to discard theme preview.',
		)
	})
})
