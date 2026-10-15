/**
 * @vitest-environment jsdom
 *
 * The search field above the token set list (token-set-picker-search): it hides
 * the sets that do not match, keeps the selected set, says how many are left,
 * ignores case and accents, and empties on Escape. js/admin.js is loaded against
 * a minimal settings page and driven with real DOM events.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/token-set-picker-search/specs/token-set-picker-search/spec.md#requirement-the-token-set-list-can-be-searched-by-name
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

const TOKEN_SETS = [
	{ id: 'rijkshuisstijl', name: 'Rijkshuisstijl' },
	{ id: 'fryslan', name: 'Provinsje Fryslân' },
	{ id: 'amsterdam', name: 'Gemeente Amsterdam' },
	{ id: 'zuiddrecht', name: 'Gemeente Zuiddrecht' },
]

function installGlobals() {
	global.t = (app, text, vars) => (vars ? text.replace(/\{(\w+)\}/g, (m, k) => String(vars[k])) : text)
	global.n = (app, singular, plural, count) => (count === 1 ? singular : plural)
	global.OC = {
		generateUrl: (url) => url,
		linkTo: (app, path) => path,
		requestToken: 'test-token',
		Notification: { showTemporary: vi.fn() },
		dialogs: { confirm: vi.fn() },
	}
	const state = { tokenSets: TOKEN_SETS, currentTokenSet: 'zuiddrecht', activePreview: null }
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) => (Object.prototype.hasOwnProperty.call(state, key) ? state[key] : fallback),
		},
	}
	global.fetch = vi.fn(() => Promise.resolve({ status: 200, ok: true, json: () => Promise.resolve({}) }))
}

function buildDom() {
	document.body.innerHTML = `
		<div id="nldesign-settings" class="section">
			<input type="search" id="nldesign-token-set-search">
			<span id="nldesign-token-set-search-status"></span>
			<select id="nldesign-token-set-select">
				${TOKEN_SETS.map((ts) => `<option value="${ts.id}"${ts.id === 'zuiddrecht' ? ' selected' : ''}>${ts.name}</option>`).join('')}
			</select>
		</div>
	`
}

async function flush(rounds = 10) {
	for (let i = 0; i < rounds; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

async function loadAdmin() {
	vi.resetModules()
	await import('../../js/lib/appTheming.js')
	await import('../../js/admin.js?t=' + Math.random())
	await flush()
}

function type(text) {
	const input = document.getElementById('nldesign-token-set-search')
	input.value = text
	input.dispatchEvent(new window.Event('input', { bubbles: true }))
}

function visible() {
	return Array.from(document.getElementById('nldesign-token-set-select').options)
		.filter((option) => !option.hidden)
		.map((option) => option.value)
}

describe('the token set list can be searched', () => {
	beforeEach(async () => {
		installGlobals()
		buildDom()
		await loadAdmin()
	})

	it('shows every set before anything is typed', () => {
		expect(visible()).toEqual(['rijkshuisstijl', 'fryslan', 'amsterdam', 'zuiddrecht'])
		expect(document.getElementById('nldesign-token-set-search-status').textContent).toBe('')
	})

	it('hides what does not match and keeps the selected set', () => {
		type('amster')

		expect(visible()).toEqual(['amsterdam', 'zuiddrecht'])
		expect(document.getElementById('nldesign-token-set-search-status').textContent).toBe('1 of 4 sets match')
	})

	it('ignores case and accents, and matches the id too', () => {
		type('FRYSLAN')
		expect(visible()).toEqual(['fryslan', 'zuiddrecht'])

		type('rijks')
		expect(visible()).toEqual(['rijkshuisstijl', 'zuiddrecht'])
	})

	it('counts no match as zero and keeps the selected set', () => {
		type('nowhere')

		expect(visible()).toEqual(['zuiddrecht'])
		expect(document.getElementById('nldesign-token-set-search-status').textContent).toBe('0 of 4 sets match')
	})

	it('empties on Escape and shows every set again', () => {
		type('amster')
		const input = document.getElementById('nldesign-token-set-search')
		input.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))

		expect(input.value).toBe('')
		expect(visible()).toHaveLength(4)
		expect(document.getElementById('nldesign-token-set-search-status').textContent).toBe('')
	})
})
