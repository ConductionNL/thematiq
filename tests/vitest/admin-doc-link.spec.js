/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin.js: the header's Documentation link follows the
 * design system of the set picked in the dropdown (#662). Until then the link
 * was a static https://nldesign.app for every design system, and that host no
 * longer resolves. admin.js is a vanilla-JS IIFE with no exports, so the test
 * loads it against a minimal settings DOM and reads the link.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/admin-settings/spec.md#requirement-documentation-link-follows-the-design-system
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const DOCS = {
	nldesign: 'https://nldesignsystem.nl',
	lasuite: 'https://github.com/suitenumerique/cunningham',
	'high-contrast': 'https://thematiq.conduction.nl',
}

const TOKEN_SETS = [
	{ id: 'rijkshuisstijl', name: 'Rijkshuisstijl', design_system: 'nldesign' },
	{ id: 'lasuite-violet', name: 'La Suite', design_system: 'lasuite' },
	{ id: 'mystery', name: 'Mystery', design_system: 'unknown' },
]

/** Flush pending promise chains across a few macrotask boundaries. */
async function flush(rounds = 8) {
	for (let i = 0; i < rounds; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

/**
 * Build the settings header and dropdown and load admin.js against them.
 *
 * @param {object} state The initial-state keys.
 */
async function mount(state) {
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) =>
				Object.prototype.hasOwnProperty.call(state, key)
					? state[key]
					: fallback,
		},
	}
	document.body.innerHTML = `
		<div id="nldesign-settings" class="section">
			<div class="nldesign-settings-header">
				<h2>Thematiq</h2>
				<a href="https://nldesignsystem.nl" id="nldesign-doc-link" target="_blank" rel="noopener noreferrer">Documentation</a>
			</div>
			<select id="nldesign-token-set-select">
				${TOKEN_SETS.map((ts) => `<option value="${ts.id}" data-design-system="${ts.design_system}"${ts.id === 'rijkshuisstijl' ? ' selected' : ''}>${ts.name}</option>`).join('')}
			</select>
			<span id="nldesign-design-system-badge"></span>
		</div>
	`
	vi.resetModules()
	await import('../../js/lib/tokenTransforms.js?t=' + Math.random())
	await import('../../js/admin.js?t=' + Math.random())
	await flush()
}

/** Pick a set in the dropdown and fire `change`. */
async function pick(id) {
	const select = document.getElementById('nldesign-token-set-select')
	select.value = id
	select.dispatchEvent(new window.Event('change', { bubbles: true }))
	await flush()
}

/** The link's current href. */
function href() {
	return document.getElementById('nldesign-doc-link').getAttribute('href')
}

describe('admin.js documentation link', () => {
	beforeEach(() => {
		global.t = (app, text) => text
		global.n = (app, singular, plural, count) => (count === 1 ? singular : plural)
		global.OC = {
			generateUrl: (url) => url,
			linkTo: (app, path) => path,
			filePath: (app, type, file) => '/apps/' + app + '/' + type + '/' + file,
			requestToken: 'test-token',
			Notification: { showTemporary: vi.fn() },
			dialogs: { confirm: vi.fn() },
		}
		global.fetch = vi.fn(() =>
			Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve({}) }),
		)
		if (typeof global.requestAnimationFrame !== 'function') {
			global.requestAnimationFrame = (cb) => setTimeout(cb, 0)
		}
	})

	afterEach(() => {
		document.body.innerHTML = ''
	})

	it('follows the dropdown to the docs of the picked design system', async () => {
		await mount({
			tokenSets: TOKEN_SETS,
			currentTokenSet: 'rijkshuisstijl',
			designSystemDocs: DOCS,
		})
		expect(href()).toBe('https://nldesignsystem.nl')

		await pick('lasuite-violet')
		expect(href()).toBe('https://github.com/suitenumerique/cunningham')

		await pick('rijkshuisstijl')
		expect(href()).toBe('https://nldesignsystem.nl')
	})

	it('keeps the rendered link for a design system it has no docs for', async () => {
		await mount({
			tokenSets: TOKEN_SETS,
			currentTokenSet: 'rijkshuisstijl',
			designSystemDocs: DOCS,
		})
		await pick('lasuite-violet')
		await pick('mystery')

		expect(href()).toBe('https://github.com/suitenumerique/cunningham')
	})
})
