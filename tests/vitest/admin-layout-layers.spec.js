/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin.js: saving the layout options puts this page in the
 * state the server resolved. Each option owns a conditional stylesheet that
 * is added or removed at once, and the navigation width travels as an inline
 * `:root` variable.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/workplace-layout/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import layerSwap from '../../js/lib/layerSwap.js'

const URL_LAYOUT = '/index.php/apps/thematiq/settings/layout'

let answer
let toast

/** Globals admin.js reads at load time; POST /settings/layout answers with `answer`. */
function installGlobals() {
	global.t = (app, text) => text
	global.n = (app, singular, plural, count) => (count === 1 ? singular : plural)
	toast = vi.fn()
	global.OC = {
		generateUrl: (url) => '/index.php' + url,
		linkTo: (app, path) => path,
		filePath: (app, type, file) => '/custom_apps/' + app + '/' + type + '/' + file,
		requestToken: 'test-token',
		Notification: { showTemporary: vi.fn() },
		dialogs: { confirm: vi.fn() },
	}
	global.OCP = {
		InitialState: { loadState: (app, key, fallback) => fallback },
		Toast: { message: toast },
	}
	window.OCP = global.OCP
	window.NldesignLayerSwap = layerSwap
	global.fetch = vi.fn((url, options = {}) => {
		if (url === URL_LAYOUT && options.method === 'POST') {
			if (answer instanceof Error) {
				return Promise.reject(answer)
			}
			return Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve(answer) })
		}
		return Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve({}), headers: { get: () => null } })
	})
}

/** Flush pending promise chains. */
async function flush(rounds = 8) {
	for (let i = 0; i < rounds; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

const $ = (id) => document.getElementById(id)

/** The conditional stylesheets on the page, by file name. */
function layers() {
	return Array.from(document.head.querySelectorAll('link[rel="stylesheet"]'))
		.map((link) => /\/css\/([\w-]+)\.css/.exec(link.getAttribute('href'))[1])
		.sort()
}

/** Change one layout field and wait for the save. */
async function change(id, value) {
	$(id).value = value
	$(id).dispatchEvent(new Event('change'))
	await flush()
}

describe('admin.js layout layers', () => {
	beforeEach(async () => {
		answer = { status: 'ok', resolved: {} }
		installGlobals()
		document.head.innerHTML = ''
		document.body.innerHTML = `
			<div id="nldesign-settings" class="section">
				<select id="thematiq-workplace-layout"><option value=""></option><option value="light">Light</option><option value="classic">Classic</option></select>
				<select id="thematiq-brand-stripe"><option value=""></option><option value="on">On</option><option value="off">Off</option></select>
				<input id="thematiq-navigation-width-input" value=" 300 ">
				<select id="thematiq-navigation-active-style"><option value="soft">Soft</option><option value="solid">Solid</option></select>
				<select id="thematiq-brand-stripe-placement"><option value="both">Both</option><option value="header">Header</option><option value="login">Login</option></select>
				<select id="thematiq-login-watermark"><option value="on">On</option><option value="off">Off</option></select>
			</div>
		`
		vi.resetModules()
		await import('../../js/admin.js?t=' + Math.random())
		await flush()
	})

	afterEach(() => {
		document.head.innerHTML = ''
		document.body.innerHTML = ''
		delete window.OCP
		delete window.NldesignLayerSwap
		vi.restoreAllMocks()
	})

	it('saves every option together, trimmed, with an empty value for a field the page lacks', async () => {
		await change('thematiq-workplace-layout', 'light')

		const [[, options]] = global.fetch.mock.calls.filter(([url, o]) => url === URL_LAYOUT && o && o.method === 'POST')
		expect(options.headers).toEqual({ 'Content-Type': 'application/json', requesttoken: 'test-token' })
		expect(JSON.parse(options.body)).toEqual({
			workplaceLayout: 'light',
			brandStripe: '',
			navigationWidth: '300',
			navigationActiveStyle: 'soft',
			brandStripePlacement: 'both',
			loginWatermark: 'on',
			headerStyle: '',
		})
	})

	it('adds the stylesheets the resolved state turns on, and the width as an inline variable', async () => {
		answer = {
			status: 'ok',
			resolved: {
				workplaceLayout: 'light',
				loginWatermark: false,
				headerStyle: 'workplace',
				brandStripe: true,
				brandStripePlacement: 'header',
				navigationWidth: 300,
				navigationActiveStyle: 'soft',
			},
		}
		await change('thematiq-workplace-layout', 'light')

		expect(layers()).toEqual([
			'brand-stripe',
			'brand-stripe-header-only',
			'header-workplace',
			'login-watermark-off',
			'navigation-active-soft',
			'navigation-width',
			'workplace-layout',
		])
		const link = document.head.querySelector('link[href*="workplace-layout"]')
		expect(link.getAttribute('href')).toMatch(/^\/custom_apps\/thematiq\/css\/workplace-layout\.css\?v=\d+$/)
		expect(link.getAttribute('data-nldesign-layer')).toBe('conditional')
		const width = $('thematiq-navigation-width')
		expect(width.textContent).toBe(':root { --thematiq-navigation-width: 300px; }')
		expect(width.getAttribute('data-nldesign-layer')).toBe('conditional')
		expect(toast).toHaveBeenCalledWith('Applied.')
	})

	it('adds a stylesheet once, updates the width in place, and takes off what is turned off', async () => {
		answer = { status: 'ok', resolved: { workplaceLayout: 'light', brandStripe: true, brandStripePlacement: 'login', navigationWidth: 300 } }
		await change('thematiq-workplace-layout', 'light')
		answer = { status: 'ok', resolved: { workplaceLayout: 'light', brandStripe: true, brandStripePlacement: 'login', navigationWidth: 260 } }
		await change('thematiq-navigation-width-input', '260')

		expect(layers()).toEqual(['brand-stripe', 'brand-stripe-login-only', 'navigation-width', 'workplace-layout'])
		expect(document.querySelectorAll('#thematiq-navigation-width')).toHaveLength(1)
		expect($('thematiq-navigation-width').textContent).toBe(':root { --thematiq-navigation-width: 260px; }')

		answer = { status: 'ok', resolved: { workplaceLayout: 'classic', brandStripe: false } }
		await change('thematiq-workplace-layout', 'classic')
		expect(layers()).toEqual([])
		expect($('thematiq-navigation-width')).toBeNull()
	})

	it('leaves the page alone when the answer resolves nothing', async () => {
		answer = { status: 'ok' }
		await change('thematiq-workplace-layout', 'light')
		expect(layers()).toEqual([])
		expect(toast).toHaveBeenCalledWith('Applied.')
	})

	it('says so when the options were not saved, and leaves the page alone', async () => {
		answer = { status: 'error' }
		await change('thematiq-workplace-layout', 'light')
		expect(toast).toHaveBeenCalledWith('Failed to save setting.')

		answer = new Error('offline')
		const error = vi.spyOn(console, 'error').mockImplementation(() => {})
		await change('thematiq-brand-stripe', 'on')
		expect(error).toHaveBeenCalledWith('Error saving layout options:', answer)
		expect(toast).toHaveBeenLastCalledWith('Failed to save setting.')
		expect(layers()).toEqual([])
	})
})
