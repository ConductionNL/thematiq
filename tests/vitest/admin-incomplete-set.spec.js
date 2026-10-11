/**
 * @vitest-environment jsdom
 *
 * The "Incomplete set" badge and the apply dialog's fallback banner
 * (thematiq#1060).
 *
 * Every shipped set is complete since thematiq#1006, so no browser run has an
 * incomplete subject left. js/admin.js is loaded here against a minimal
 * settings page whose `tokenSets` initial state carries the warning
 * TokenSetVocabularyAuditService::warningsFor() emits (its shape is held by
 * tests/Unit/Service/TokenSetVocabularyNldesignPathTest.php), next to a
 * contrast warning, and driven with real DOM events.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/** What warningsFor() emits for an incomplete set. */
const INCOMPLETE = {
	kind: 'incomplete',
	missing: ['--nldesign-color-text', '--nldesign-color-background'],
	foreign: ['--nldesign-color-blue-40'],
	primaryMismatch: true,
	declaredPrimary: '#333333',
	cssPrimary: '#000000',
}

/** A contrast finding on the same channel: no kind. */
const CONTRAST = {
	pair: '--nldesign-color-primary vs --nldesign-color-primary-text',
	ratio: 2.1,
	threshold: 4.5,
	level: 'AA',
}

const TOKEN_SETS = [
	{
		id: 'rijkshuisstijl',
		name: 'Rijkshuisstijl',
		theming: { primary_color: '#154273', background_color: '#ffffff' },
	},
	{
		id: 'broken',
		name: 'Broken',
		warnings: [CONTRAST, INCOMPLETE],
	},
]

function installGlobals(state) {
	global.t = (app, text) => text
	global.n = (app, singular, plural, count) => (count === 1 ? singular : plural)
	global.OC = {
		generateUrl: (url) => url,
		linkTo: (app, path) => path,
		requestToken: 'test-token',
		Notification: { showTemporary: vi.fn() },
		dialogs: { confirm: vi.fn() },
	}
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) =>
				Object.prototype.hasOwnProperty.call(state, key)
					? state[key]
					: fallback,
		},
	}
}

function buildDom(current) {
	document.body.innerHTML = `
		<div id="nldesign-settings" class="section">
			<select id="nldesign-token-set-select" name="nldesign-token-set">
				${TOKEN_SETS.map((ts) => `<option value="${ts.id}" data-design-system="nldesign"${ts.id === current ? ' selected' : ''}>${ts.name}</option>`).join('')}
			</select>
			<span id="nldesign-design-system-badge"></span>
			<span id="nldesign-token-set-completeness-badge" hidden></span>
		</div>
		<div class="nldesign-preview" id="nldesign-preview"></div>
	`
}

function installFetchRouter(routes) {
	global.fetch = vi.fn((url, init) => {
		for (const [match, body] of routes) {
			if (url.indexOf(match) !== -1) {
				return Promise.resolve({
					status: 200,
					ok: true,
					json: () => Promise.resolve(body),
				})
			}
		}
		return Promise.resolve({
			status: 200,
			ok: true,
			json: () => Promise.resolve({}),
		})
	})
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

function select(id) {
	const el = document.getElementById('nldesign-token-set-select')
	el.value = id
	el.dispatchEvent(new window.Event('change', { bubbles: true }))
}

describe('admin.js surfaces an incomplete set (thematiq#1060)', () => {
	beforeEach(() => {
		installGlobals({
			tokenSets: TOKEN_SETS,
			currentTokenSet: 'rijkshuisstijl',
			activePreview: null,
		})
		buildDom('rijkshuisstijl')
		installFetchRouter([
			// A preview that differs from the page, so the apply dialog opens.
			[
				'tokenset-preview',
				{ id: 'broken', resolved: { '--nldesign-color-text': '#010203' } },
			],
			['/overrides', { overrides: {}, status: 'ok' }],
			['/settings/tokenset', { status: 'ok' }],
			['/settings/theming', { primary_color: '#aaaaaa' }],
		])
	})

	afterEach(() => {
		document.body.innerHTML = ''
		document.documentElement.removeAttribute('style')
		vi.restoreAllMocks()
	})

	// @spec openspec/specs/token-sets/spec.md#selecting-an-incomplete-set-shows-the-incomplete-set-badge
	it('shows the badge with its findings for an incomplete set and hides it for a complete one', async () => {
		await loadAdmin()
		const badge = document.getElementById(
			'nldesign-token-set-completeness-badge',
		)
		expect(badge.hidden).toBe(true)

		select('broken')
		await flush()
		expect(badge.hidden).toBe(false)
		expect(badge.textContent).toBe('Incomplete set')
		const title = badge.getAttribute('title')
		expect(title).toContain('--nldesign-color-text')
		expect(title).toContain('--nldesign-color-blue-40')
		expect(title).toContain('#000000')
		expect(title).toContain('#333333')

		// Back to a complete set: hidden entirely, no stale tooltip.
		document.getElementById('nldesign-apply-dialog-overlay')?.remove()
		select('rijkshuisstijl')
		await flush()
		expect(badge.hidden).toBe(true)
		expect(badge.textContent).toBe('')
		expect(badge.hasAttribute('title')).toBe(false)
	})

	// @spec openspec/specs/token-sets/spec.md#the-apply-dialog-explains-the-fallback
	it('explains the fallback in its own banner, apart from the contrast banner, and still applies', async () => {
		await loadAdmin()
		select('broken')
		await flush()

		const overlay = document.getElementById('nldesign-apply-dialog-overlay')
		expect(overlay).not.toBeNull()
		const banners = Array.from(
			overlay.querySelectorAll('.nldesign-contrast-warning'),
		)
		const incomplete = banners.filter((b) =>
			/Incomplete set/.test(b.querySelector('strong').textContent),
		)
		const contrast = banners.filter((b) =>
			/contrast warning/.test(b.querySelector('strong').textContent),
		)
		expect(incomplete).toHaveLength(1)
		expect(contrast).toHaveLength(1)
		expect(incomplete[0].textContent).toContain('Rijkshuisstijl defaults')
		// The contrast banner lists the contrast pair only, never the vocabulary entry.
		expect(contrast[0].querySelectorAll('li')).toHaveLength(1)
		expect(contrast[0].textContent).not.toContain('undefined')
		expect(contrast[0].textContent).not.toContain('--nldesign-color-blue-40')

		// Non-blocking: confirming applies the set.
		const confirm = overlay.querySelector('.nldesign-dialog-confirm')
		expect(confirm.disabled).toBe(false)
		confirm.click()
		await flush()
		const post = global.fetch.mock.calls.find(
			([url, init]) =>
				url.indexOf('/settings/tokenset') !== -1
				&& url.indexOf('preview') === -1
				&& init
				&& init.method === 'POST',
		)
		expect(post).toBeTruthy()
		expect(JSON.parse(post[1].body).tokenSet).toBe('broken')
	})
})
