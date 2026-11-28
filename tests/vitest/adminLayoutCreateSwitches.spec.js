/**
 * @vitest-environment jsdom
 *
 * The two create switches in the Menu card of Layout and options
 * (js/admin-layout-panel.js, board TqLayout): a toggle saves only its own key
 * for everyone at once, says Saved, and on a failure says why and sets the
 * switch back; with the classic menu both are disabled.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-the-administrator-sets-the-defaults-on-thematiqs-admin-page-req-tqqt-003
 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-the-administrator-sets-the-contacts-menu-default-on-thematiqs-admin-page-req-tqcm-002
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * The Menu card as templates/settings/admin.php draws it.
 *
 * @param {boolean} classic The classic menu is chosen.
 * @return {string} The markup.
 */
function card(classic) {
	const off = classic ? ' disabled' : ''
	return `
		<section class="thematiq-panel" data-thematiq-panel="layout">
			<form>
				<div class="thematiq-create-switches"${classic ? ' data-classic-menu' : ''}>
					<div class="nldesign-option">
						<input type="checkbox" class="checkbox" role="switch" id="thematiq-showCreateButton" data-menu-key="showCreateButton" checked${off}>
						<label for="thematiq-showCreateButton">Create button in the top bar</label>
					</div>
					<div class="nldesign-option">
						<input type="checkbox" class="checkbox" role="switch" id="thematiq-showCreateRow" data-menu-key="showCreateRow" checked${off}>
						<label for="thematiq-showCreateRow">Create row in the apps menu</label>
					</div>
					<div class="nldesign-option">
						<input type="checkbox" class="checkbox" role="switch" id="thematiq-showContactsMenu" data-menu-key="showContactsMenu" data-any-menu-style checked>
						<label for="thematiq-showContactsMenu">Contacts button in the top bar</label>
					</div>
					${classic ? '<p id="thematiq-create-switches-classic">Only with the workplace menu.</p>' : ''}
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

/**
 * Load the panel script over the page.
 *
 * @param {boolean} classic The classic menu is chosen.
 * @return {Promise<void>}
 */
async function load(classic = false) {
	document.body.innerHTML = card(classic)
	vi.resetModules()
	await import('../../js/admin-layout-panel.js?t=' + Math.random())
}

const flush = () => new Promise((resolve) => setTimeout(resolve, 0))

/**
 * Toggle a switch as a person does.
 *
 * @param {string} key The menu key.
 * @return {HTMLInputElement} The switch.
 */
function toggle(key) {
	const input = document.getElementById('thematiq-' + key)
	input.checked = !input.checked
	input.dispatchEvent(new Event('change', { bubbles: true }))
	return input
}

describe('the create switches of the Menu card', () => {
	beforeEach(() => {
		window.OC = {
			requestToken: 'token',
			generateUrl: (path) => '/index.php' + path,
		}
		window.OCP = { InitialState: { loadState: (app, key, fallback) => fallback } }
	})

	afterEach(() => {
		document.body.innerHTML = ''
		delete window.OC
		delete window.OCP
		vi.unstubAllGlobals()
	})

	it('saves only the toggled key for everyone and says Saved', async () => {
		const fetch = vi.fn(() => Promise.resolve({ ok: true, json: () => Promise.resolve({}) }))
		vi.stubGlobal('fetch', fetch)
		await load()

		const input = toggle('showCreateRow')
		await flush()

		expect(fetch).toHaveBeenCalledTimes(1)
		const [url, options] = fetch.mock.calls[0]
		expect(url).toBe('/index.php/apps/thematiq/app-menu/defaults')
		expect(options.method).toBe('POST')
		expect(options.headers.requesttoken).toBe('token')
		expect(JSON.parse(options.body)).toEqual({ settings: { showCreateRow: false } })
		expect(input.checked).toBe(false)
		expect(document.querySelector('.thematiq-create-switches__live').textContent).toBe('Saved')
		expect(document.querySelector('.thematiq-create-switches__alert').hidden).toBe(true)
	})

	it('lists the change among the changes in this session', async () => {
		vi.stubGlobal('fetch', vi.fn(() => Promise.resolve({ ok: true })))
		await load()

		toggle('showCreateButton')
		await flush()

		const changes = document.querySelector('.thematiq-layout-panel__changes').textContent
		expect(changes).toContain('Create button in the top bar')
		expect(changes).toContain('off')
	})

	it('says why and sets the switch back when the save fails', async () => {
		vi.stubGlobal('fetch', vi.fn(() => Promise.resolve({ ok: false, status: 403 })))
		await load()

		const input = toggle('showCreateButton')
		await flush()

		expect(input.checked).toBe(true)
		const alert = document.querySelector('.thematiq-create-switches__alert')
		expect(alert.hidden).toBe(false)
		expect(alert.textContent).toBe('The setting could not be saved: 403')
		expect(document.querySelector('.thematiq-create-switches__live').textContent).toBe('')
	})

	it('disables the create switches with the classic menu', async () => {
		vi.stubGlobal('fetch', vi.fn())
		await load(true)

		for (const key of ['showCreateButton', 'showCreateRow']) {
			expect(document.getElementById('thematiq-' + key).disabled).toBe(true)
		}
		expect(document.getElementById('thematiq-create-switches-classic').textContent).toBe(
			'Only with the workplace menu.',
		)
	})

	it('keeps the contacts button switch enabled with the classic menu', async () => {
		const fetch = vi.fn(() => Promise.resolve({ ok: true }))
		vi.stubGlobal('fetch', fetch)
		await load(true)

		const input = document.getElementById('thematiq-showContactsMenu')
		expect(input.disabled).toBe(false)
		toggle('showContactsMenu')
		await flush()
		expect(JSON.parse(fetch.mock.calls[0][1].body)).toEqual({ settings: { showContactsMenu: false } })
		expect(document.querySelector('.thematiq-create-switches__live').textContent).toBe('Saved')
		expect(document.querySelector('.thematiq-layout-panel__changes').textContent).toContain('Contacts button in the top bar')
	})
})
