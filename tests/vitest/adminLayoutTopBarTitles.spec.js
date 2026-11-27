/**
 * @vitest-environment jsdom
 *
 * "Show titles in the top bar" in the Menu card of Layout and options
 * (js/admin-layout-panel.js, board TqLayout, apps-menu-show-titles
 * REQ-TQMT-005): the switch saves `topBarTitles` alone for everyone through
 * the menu defaults, says Saved, and on a failure says why and sets itself
 * back. It works with the classic menu too, so it is never disabled there.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/apps-menu-show-titles/specs/admin-settings/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * The switch as templates/settings/admin.php draws it.
 *
 * @param {boolean} checked Whether titles in the top bar are on.
 * @return {string} The markup.
 */
function card(checked) {
	return `
		<section class="thematiq-panel" data-thematiq-panel="layout">
			<form>
				<div class="nldesign-option thematiq-menu-switch">
					<input type="checkbox" name="nldesign-show-menu-labels" id="nldesign-show-menu-labels" class="checkbox"
						role="switch" data-menu-key="topBarTitles" aria-describedby="nldesign-show-menu-labels-help"${checked ? ' checked' : ''}>
					<label for="nldesign-show-menu-labels">Show titles in the top bar</label>
					<p class="settings-hint" id="nldesign-show-menu-labels-help">The name next to the icon of every pinned app in the top bar.</p>
					<p class="hidden-visually thematiq-create-switches__live" role="status" aria-live="polite"></p>
					<p class="thematiq-create-switches__alert" role="alert" hidden></p>
				</div>
			</form>
			<aside>
				<span class="thematiq-panel__count"></span>
				<span class="thematiq-layout-panel__summary"></span>
				<div class="thematiq-layout-panel__changes"></div>
			</aside>
		</section>`
}

async function load(checked = false) {
	document.body.innerHTML = card(checked)
	vi.resetModules()
	await import('../../js/admin-layout-panel.js?t=' + Math.random())
}

const flush = () => new Promise((resolve) => setTimeout(resolve, 0))

function toggle() {
	const input = document.getElementById('nldesign-show-menu-labels')
	input.checked = !input.checked
	input.dispatchEvent(new Event('change', { bubbles: true }))
	return input
}

describe('Show titles in the top bar on the admin page', () => {
	beforeEach(() => {
		window.OC = { requestToken: 'token', generateUrl: (path) => '/index.php' + path }
		window.OCP = { InitialState: { loadState: (app, key, fallback) => fallback } }
	})

	afterEach(() => {
		document.body.innerHTML = ''
		delete window.OC
		delete window.OCP
		vi.unstubAllGlobals()
	})

	it('saves topBarTitles alone for everyone through the menu defaults and says Saved', async () => {
		const fetch = vi.fn(() => Promise.resolve({ ok: true }))
		vi.stubGlobal('fetch', fetch)
		await load(false)

		const input = toggle()
		await flush()

		expect(fetch).toHaveBeenCalledTimes(1)
		const [url, options] = fetch.mock.calls[0]
		expect(url).toBe('/index.php/apps/thematiq/app-menu/defaults')
		expect(options.method).toBe('POST')
		expect(JSON.parse(options.body)).toEqual({ settings: { topBarTitles: true } })
		expect(input.checked).toBe(true)
		expect(input.disabled).toBe(false)
		expect(document.querySelector('.thematiq-create-switches__live').textContent).toBe('Saved')
	})

	it('no longer posts to the removed menulabels endpoint', async () => {
		const fetch = vi.fn(() => Promise.resolve({ ok: true }))
		vi.stubGlobal('fetch', fetch)
		await load(true)

		toggle()
		await flush()

		expect(fetch.mock.calls.map(([url]) => url)).not.toContain('/index.php/apps/thematiq/settings/menulabels')
		expect(JSON.parse(fetch.mock.calls[0][1].body)).toEqual({ settings: { topBarTitles: false } })
	})

	it('says why and sets the switch back when the save fails', async () => {
		vi.stubGlobal('fetch', vi.fn(() => Promise.resolve({ ok: false, status: 500 })))
		await load(false)

		const input = toggle()
		await flush()

		expect(input.checked).toBe(false)
		const alert = document.querySelector('.thematiq-create-switches__alert')
		expect(alert.hidden).toBe(false)
		expect(alert.textContent).toBe('The setting could not be saved: 500')
	})
})
