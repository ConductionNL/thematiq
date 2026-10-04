/**
 * @vitest-environment jsdom
 *
 * js/playground.js, "Your component": the chip in every tab, the cleaned markup in a
 * sandboxed frame, the token list that follows the code, a live repaint, the dark switch,
 * the saved component reopened from its link, and a stage that cannot build.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/own-component-preview/spec.md#requirement-the-playground-previews-a-builders-own-component
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import * as fs from 'fs'
import * as path from 'path'

const ROOT = path.resolve(__dirname, '../..')
const inventory = JSON.parse(
	fs.readFileSync(path.join(ROOT, 'js/playground/components.json'), 'utf8'),
)

const REGISTRY = {
	'--nldesign-color-primary': { tab: 'content', label: 'Primary', type: 'color' },
}

const SAVED = {
	name: 'Afvalkaart',
	slug: 'afvalkaart',
	html: '<div class="card"><h2>Afval</h2><p>Ophaaldagen</p></div>',
	css: '.card { background: var(--nldesign-color-primary); color: var(--nldesign-color-primary-text); padding: 16px; }',
	updatedAt: '',
}

let requests = []

async function flush(rounds = 12) {
	for (let i = 0; i < rounds; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

async function boot({ hash = '#', withModules = true, saved = [] } = {}) {
	const state = {
		tokenSets: [],
		currentTokenSet: 'amsterdam',
		playgroundInventory: inventory,
		playgroundVersion: 34,
		playgroundDarkTokens: { '--nldesign-color-primary': '#5ea4f7' },
	}
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) =>
				Object.prototype.hasOwnProperty.call(state, key)
					? state[key]
					: fallback,
		},
	}
	global.t = (app, text, params) =>
		params === undefined
			? text
			: Object.keys(params).reduce(
					(acc, key) => acc.replace('{' + key + '}', params[key]),
					text,
				)
	global.n = (app, singular, plural, count) => (count === 1 ? singular : plural)
	global.OC = {
		generateUrl: (url) => url,
		linkTo: (app, file) => file,
		filePath: (app, type, file) => '/apps/' + app + '/' + type + '/' + file,
		requestToken: 'test-token',
		Notification: { showTemporary: vi.fn() },
		dialogs: { confirm: vi.fn() },
	}
	requests = []
	global.fetch = vi.fn((url, options = {}) => {
		requests.push({ url, method: options.method || 'GET', body: options.body })
		let body = {}
		if (url.indexOf('/settings/overrides') !== -1) {
			body = { overrides: {}, registry: REGISTRY, tabs: {} }
		} else if (url.indexOf('/api/contrast/evaluate') !== -1) {
			body = {
				results: [{ name: 'x', ratio: 2.1, threshold: 4.5, pass: false }],
			}
		} else if (url.indexOf('/settings/playground/components') !== -1) {
			body =
				(options.method || 'GET') === 'GET'
					? { components: saved }
					: { status: 'ok', component: SAVED }
		}
		return Promise.resolve({
			ok: true,
			status: 200,
			json: () => Promise.resolve(body),
		})
	})

	document.body.innerHTML = `
		<div class="nldesign-preview" id="nldesign-preview" style="--nldesign-color-primary: #154273; --nldesign-color-primary-text: #ffffff">
			<div class="nldesign-preview-head"><h3>Preview</h3></div>
			<div class="nldesign-preview-stage" data-view="app"></div>
			<div class="nldesign-preview-stage" data-view="login" hidden></div>
		</div>
		<div id="nldesign-token-editor"></div>
	`
	window.history.replaceState(null, '', hash)

	vi.resetModules()
	delete window.NldesignMarkupSanitizer
	delete window.NldesignOwnComponentFrame
	await import('../../js/lib/tokenTransforms.js?t=' + Math.random())
	if (withModules) {
		window.NldesignMarkupSanitizer = (
			await import('../../js/lib/markupSanitizer.js?t=' + Math.random())
		).default
		window.NldesignOwnComponentFrame = (
			await import('../../js/lib/ownComponentFrame.js?t=' + Math.random())
		).default
	}
	await import('../../js/admin.js?t=' + Math.random())
	await flush()
	const playground = (await import('../../js/playground.js?t=' + Math.random()))
		.default
	playground.boot()
	await flush()
}

function chips() {
	return [...document.querySelectorAll('.nldesign-pg-chip')].map(
		(c) => c.textContent,
	)
}

function chip(title) {
	return [...document.querySelectorAll('.nldesign-pg-chip')].find(
		(c) => c.textContent === title,
	)
}

function type(field, value) {
	field.value = value
	field.dispatchEvent(new window.Event('input', { bubbles: true }))
}

describe('playground: your component', () => {
	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('ends every tab chip row with "Your component"', async () => {
		await boot()
		const tabs = [
			...document.querySelectorAll('.nldesign-pg-selector .nldesign-tab-btn'),
		]
		expect(tabs.length).toBeGreaterThan(0)
		for (const button of tabs) {
			button.click()
			expect(chips().pop(), button.dataset.tab).toBe('Your component')
		}
	})

	it('renders the cleaned markup in a sandboxed frame and reports what it removed', async () => {
		await boot()
		chip('Your component').click()
		type(
			document.getElementById('nldesign-own-html'),
			'<button onclick="alert(1)">Test</button><script>alert(2)</script>',
		)

		const frame = document.querySelector('.nldesign-pg-stage iframe')
		expect(frame.getAttribute('sandbox')).toBe('allow-same-origin')
		expect(frame.getAttribute('title')).toContain('Your component')
		expect(frame.getAttribute('srcdoc')).toContain('<button>Test</button>')
		expect(frame.getAttribute('srcdoc')).not.toContain('alert')
		const report = document.querySelector('.nldesign-own-report')
		expect(report.getAttribute('role')).toBe('status')
		expect(report.textContent).toBe('Removed: 1 event handler, 1 script')
		expect(
			document.querySelector('label[for="nldesign-own-html"]').textContent,
		).toBe('HTML')
		expect(
			document.querySelector('label[for="nldesign-own-css"]').textContent,
		).toBe('CSS')
	})

	it('gives the frame the house style fonts at addresses that load (#940)', async () => {
		const origin = window.location.origin
		vi.spyOn(document, 'styleSheets', 'get').mockReturnValue([
			{
				href: origin + '/apps/thematiq/css/systems/nldesign/fonts.css?v=34',
				cssRules: [
					{
						type: 5,
						cssText:
							'@font-face { font-family: "Fira Sans"; src: url("fonts/fira-sans.woff2") format("woff2"), url(\'../shared/fira.woff\') format("woff"); }',
					},
					{ type: 1, cssText: 'p { color: red; }' },
				],
			},
			{
				href: null,
				cssRules: [
					{
						type: 5,
						cssText:
							'@font-face { font-family: Inline; src: url(data:font/woff2;base64,AAAA); }',
					},
				],
			},
		])
		await boot()
		chip('Your component').click()
		type(document.getElementById('nldesign-own-html'), '<p>Tekst</p>')

		const srcdoc = document
			.querySelector('.nldesign-pg-stage iframe')
			.getAttribute('srcdoc')
		expect(srcdoc).toContain(
			'url("'
				+ origin
				+ '/apps/thematiq/css/systems/nldesign/fonts/fira-sans.woff2")',
		)
		expect(srcdoc).toContain(
			'url("' + origin + '/apps/thematiq/css/systems/shared/fira.woff")',
		)
		expect(srcdoc).not.toContain('url("fonts/')
		expect(srcdoc).toContain('url(data:font/woff2;base64,AAAA)')
		expect(srcdoc).not.toContain('p { color: red; }')
		// The frame's own policy lets those same-origin fonts in.
		expect(srcdoc).toContain("font-src 'self' " + origin + ' data:')
	})

	it('lists exactly the tokens the code reads, read-only where the editor cannot write', async () => {
		await boot()
		chip('Your component').click()
		type(
			document.getElementById('nldesign-own-css'),
			'.a { color: var(--nldesign-color-primary); border-color: var(--nldesign-org-brand-accent) }',
		)

		const panel = document.querySelector('.nldesign-own-panel')
		const rows = [...panel.querySelectorAll('.nldesign-pg-row')]
		expect(rows).toHaveLength(2)
		expect(rows[0].querySelector('[data-token]')).not.toBeNull()
		expect(rows[1].textContent).toContain('--nldesign-org-brand-accent')
		expect(rows[1].textContent).toContain(
			'Read-only here: this comes from the token set',
		)
	})

	it('repaints the frame when a token is edited, without rebuilding it', async () => {
		await boot()
		chip('Your component').click()
		type(
			document.getElementById('nldesign-own-css'),
			'.a { color: var(--nldesign-color-primary) }',
		)
		const frame = document.querySelector('.nldesign-pg-stage iframe')
		const srcdoc = frame.getAttribute('srcdoc')
		const root = frame.contentDocument.documentElement
		// jsdom computes no custom properties; read the inline value live edits are written as.
		vi.spyOn(window, 'getComputedStyle').mockImplementation((node) => ({
			getPropertyValue: (name) => node.style.getPropertyValue(name),
		}))

		document
			.getElementById('nldesign-preview')
			.style.setProperty('--nldesign-color-primary', '#c00000')
		document
			.querySelector('.nldesign-token-editor')
			.dispatchEvent(new window.Event('input', { bubbles: true }))
		await flush(2)

		expect(root.style.getPropertyValue('--nldesign-color-primary')).toBe(
			'#c00000',
		)
		expect(frame.getAttribute('srcdoc')).toBe(srcdoc)
	})

	it('switches the frame to the set dark values and says Nextcloud keeps its theme', async () => {
		await boot()
		chip('Your component').click()
		type(
			document.getElementById('nldesign-own-css'),
			'.a { color: var(--nldesign-color-primary) }',
		)
		const dark = document.querySelector('.nldesign-own-dark')
		dark.click()

		const root = document.querySelector('.nldesign-pg-stage iframe')
			.contentDocument.documentElement
		expect(dark.getAttribute('aria-pressed')).toBe('true')
		expect(root.style.getPropertyValue('--nldesign-color-primary')).toBe(
			'#5ea4f7',
		)
		expect(document.querySelector('.nldesign-own-dark-note').hidden).toBe(false)
	})

	it('reopens a saved card from its link, and ignores a slug that no longer exists', async () => {
		await boot({ hash: '#preview=content/own-afvalkaart', saved: [SAVED] })
		expect(document.getElementById('nldesign-own-html').value).toBe(SAVED.html)
		expect(window.location.hash).toBe('#preview=content/own-afvalkaart')
		expect(chips()).toContain('Afvalkaart')

		await boot({ hash: '#preview=content/own-weg', saved: [SAVED] })
		expect(document.getElementById('nldesign-own-html')).toBeNull()
	})

	it('shows the contrast of a text on its base, measured by the server', async () => {
		await boot()
		chip('Your component').click()
		type(
			document.getElementById('nldesign-own-css'),
			'.a { background: var(--nldesign-color-primary); color: var(--nldesign-color-primary-text) }',
		)
		await flush()

		const call = requests.find(
			(r) => r.url.indexOf('/api/contrast/evaluate') !== -1,
		)
		expect(JSON.parse(call.body).candidates[0]).toMatchObject({
			name: '--nldesign-color-primary-text',
			role: 'text',
		})
		expect(document.querySelector('.nldesign-own-contrast li').textContent).toBe(
			'--nldesign-color-primary-text on --nldesign-color-primary: 2.1:1, fails WCAG AA',
		)
	})

	it('saves the stage by name', async () => {
		await boot()
		chip('Your component').click()
		document.getElementById('nldesign-own-name').value = 'Afvalkaart'
		type(document.getElementById('nldesign-own-html'), SAVED.html)
		;[...document.querySelectorAll('.nldesign-own-save button')]
			.find((b) => b.textContent === 'Save component')
			.click()
		await flush()

		const post = requests.find(
			(r) =>
				r.method === 'POST'
				&& r.url.indexOf('/settings/playground/components') !== -1,
		)
		expect(JSON.parse(post.body)).toEqual({
			name: 'Afvalkaart',
			html: SAVED.html,
			css: '',
		})
		expect(window.location.hash).toBe('#preview=content/own-afvalkaart')
	})

	it('keeps the playground working when the stage cannot build', async () => {
		vi.spyOn(console, 'error').mockImplementation(() => {})
		await boot({ withModules: false })
		chip('Your component').click()
		expect(document.querySelector('.nldesign-own-error').textContent).toBe(
			'Your component could not be shown.',
		)

		const shipped = inventory.components.find((c) => c.tab === 'content')
		chip(shipped.title).click()
		expect(document.querySelector('.nldesign-own-error')).toBeNull()
		expect(
			document
				.querySelector('.nldesign-pg-stage')
				.getAttribute('data-thematiq-component'),
		).toBe(shipped.id)
	})
})
