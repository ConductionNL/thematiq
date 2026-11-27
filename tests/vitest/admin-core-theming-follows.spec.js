/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin.js: the switch "Keep the Nextcloud theme in line with the set" in the
 * Active token set card saves at once, and while it is on a change of set opens no theming sync dialog.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-settings-page-switches-nextclouds-theme-in-line-with-the-set-req-tqfs-013
 */

import { afterEach, describe, expect, it, vi } from 'vitest'

function installInitialState(state) {
	global.OCP = Object.assign(global.OCP || {}, {
		InitialState: {
			loadState: (app, key, fallback) =>
				Object.prototype.hasOwnProperty.call(state, key)
					? state[key]
					: fallback,
		},
	})
}

function installGlobals() {
	global.t = (app, text, params) => {
		if (params === undefined) {
			return text
		}
		return Object.keys(params).reduce(
			(acc, key) => acc.replace('{' + key + '}', params[key]),
			text,
		)
	}
	global.n = (app, singular, plural, count) => (count === 1 ? singular : plural)
	global.OC = {
		generateUrl: (url) => url,
		linkTo: (app, path) => path,
		imagePath: (app, path) => '/' + app + '/' + path,
		filePath: (app, type, file) => '/' + app + '/' + type + '/' + file,
		requestToken: 'test-token',
		Notification: { showTemporary: vi.fn() },
		dialogs: { confirm: vi.fn() },
	}
}

/**
 * Route fetches by URL fragment, and optionally by method.
 *
 * The method matters for `/settings/theming`, which is one URL with two
 * answers: GET returns the snapshot, POST returns `{status: 'ok'}`. Without
 * the distinction the POST was answered with the snapshot, `applyThemingPlan()`
 * saw no `status` and threw "Theming sync failed", and every assertion about
 * the panel failed on a defect of this stub rather than of admin.js.
 *
 * @param {Array<[string, object, string?]>} routes `[urlFragment, body, method?]`; a route with a method only matches that method, and is tried before the method-less ones.
 * @param {Array?} postLog Collects every request that carried options.
 */
function installFetchRouter(routes, postLog) {
	global.fetch = vi.fn((url, options) => {
		const method = (options && options.method) || 'GET'
		if (options && postLog) {
			postLog.push({ url, method, body: options.body })
		}
		const matches = (route) => url.indexOf(route[0]) !== -1
		const byMethod = routes.filter((route) => route[2] === method).find(matches)
		const route =
			byMethod
			|| routes.filter((route) => route[2] === undefined).find(matches)
		return Promise.resolve({
			ok: true,
			status: 200,
			json: () => Promise.resolve(route ? route[1] : {}),
		})
	})
}

async function flush(rounds = 10) {
	for (let i = 0; i < rounds; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

async function loadAdminScript() {
	vi.resetModules()
	// The page loads js/lib/themingPlan.js before admin.js; so does the fixture.
	await import('../../js/lib/themingPlan.js')
	await import('../../js/dialogs/admin-dialog.js?t=' + Math.random())
	await import('../../js/dialogs/theming-update-dialog.js?t=' + Math.random())
	await import('../../js/admin.js?t=' + Math.random())
	await flush()
}


function buildDom(follows) {
	installInitialState({
		tokenSets: [
			{
				id: 'epe',
				name: 'Gemeente Epe',
				design_system: 'nldesign',
				theming: { primary_color: '#00589f' },
			},
		],
		currentTokenSet: 'nextcloud',
		activePreview: null,
		iconPackSource: '',
	})
	document.body.innerHTML = `
		<div id="nldesign-settings" class="section">
			<select id="nldesign-token-set-select" name="nldesign-token-set">
				<option value="nextcloud" data-design-system="none" selected>Nextcloud (default)</option>
				<option value="epe" data-design-system="nldesign">Gemeente Epe</option>
			</select>
			<span id="nldesign-design-system-badge"></span>
			<input type="checkbox" role="switch" id="thematiq-core-theming-follows" ${follows ? 'checked' : ''}>
			<p id="thematiq-core-theming-follows-status"></p>
		</div>
		<div class="nldesign-preview" id="nldesign-preview"></div>
	`
}

async function boot(follows, tokenSetAnswer, postLog) {
	installGlobals()
	buildDom(follows)
	installFetchRouter(
		[
			// No apply-diff preview: saveTokenSet() runs directly, and the theming sync
			// dialog is the step after it.
			['tokenset-preview', { error: 'not applicable' }],
			['tokenset-stylesheets', { tokenSet: 'epe', designSystem: 'nldesign', layers: [] }],
			['/settings/overrides', { overrides: {}, status: 'ok' }],
			['/settings/core-theming-follows', { status: 'ok', follows: true, applied: ['primary_color'] }],
			['/settings/tokenset', tokenSetAnswer],
			['/settings/theming', { status: 'ok' }, 'POST'],
			['/settings/theming', { primary_color: '#aaaaaa', background_color: '#bbbbbb', has_custom_logo: false, has_custom_background: false }],
		],
		postLog,
	)
	await loadAdminScript()
}

async function chooseEpe() {
	const select = document.getElementById('nldesign-token-set-select')
	select.value = 'epe'
	select.dispatchEvent(new window.Event('change', { bubbles: true }))
	await flush(30)
}

describe('admin.js: keep the Nextcloud theme in line with the set', () => {
	afterEach(() => {
		document.body.innerHTML = ''
		document.documentElement.removeAttribute('style')
		vi.restoreAllMocks()
	})

	it('saves the switch at once and says the Nextcloud theme now follows', async () => {
		const posts = []
		await boot(false, { status: 'ok', tokenSet: 'epe' }, posts)
		const toggle = document.getElementById('thematiq-core-theming-follows')
		toggle.checked = true
		toggle.dispatchEvent(new window.Event('change', { bubbles: true }))
		await flush()

		const post = posts.find((p) => p.url.indexOf('/settings/core-theming-follows') !== -1)
		expect(post).toBeDefined()
		expect(JSON.parse(post.body)).toEqual({ follows: true })
		expect(document.getElementById('thematiq-core-theming-follows-status').textContent).toContain(
			'The Nextcloud theme now follows the set',
		)
	})

	it('opens no theming sync dialog after a change of set while it is on', async () => {
		await boot(true, { status: 'ok', tokenSet: 'epe', nextcloudFollowed: true })
		await chooseEpe()

		expect(document.getElementById('nldesign-theming-dialog-overlay')).toBeNull()
		expect(document.getElementById('thematiq-core-theming-follows-status').textContent).toBe(
			'The Nextcloud theme followed the set.',
		)
	})

	it('still asks with the theming sync dialog while it is off', async () => {
		await boot(false, { status: 'ok', tokenSet: 'epe', nextcloudFollowed: false })
		await chooseEpe()

		expect(document.getElementById('nldesign-theming-dialog-overlay')).not.toBeNull()
	})
})
