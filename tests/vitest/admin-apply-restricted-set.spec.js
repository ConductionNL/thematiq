/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin.js: the apply dialog names the confirmation when
 * the server refuses a restricted house style that nobody confirmed.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/archive/2026-11-22-token-set-licence-gate/specs/token-set-licences/spec.md#requirement-the-picker-shows-the-warning-and-the-licence-text
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
	await import('../../js/admin.js?t=' + Math.random())
	await flush()
}

const CONFIRM_FIRST =
	'Confirm first that your organisation may use this house style. You do that under House styles of other organisations.'

function buildDom() {
	installInitialState({
		tokenSets: [{ id: 'epe', name: 'Gemeente Epe', design_system: 'nldesign' }],
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
		</div>
		<div class="nldesign-preview" id="nldesign-preview"></div>
	`
}

async function applyEpe(tokenSetAnswer) {
	installGlobals()
	buildDom()
	installFetchRouter([
		['tokenset-preview', { resolved: { '--color-main-background': '#fafafa' } }],
		[
			'tokenset-stylesheets',
			{ tokenSet: 'epe', designSystem: 'nldesign', layers: [] },
		],
		['/settings/overrides', { overrides: {}, status: 'ok' }],
		['/settings/tokenset', tokenSetAnswer],
		['/settings/theming', { status: 'ok' }, 'POST'],
		['/settings/theming', {}],
	])
	await loadAdminScript()

	const select = document.getElementById('nldesign-token-set-select')
	select.value = 'epe'
	select.dispatchEvent(new window.Event('change', { bubbles: true }))
	await flush()

	const overlay = document.getElementById('nldesign-apply-dialog-overlay')
	expect(overlay, 'the apply dialog should open').not.toBeNull()
	overlay
		.querySelector('.nldesign-dialog-confirm')
		.dispatchEvent(new window.Event('click', { bubbles: true }))
	await flush(20)
	return OC.Notification.showTemporary.mock.calls.map((call) => call[0])
}

describe('admin.js: applying a restricted house style nobody confirmed', () => {
	afterEach(() => {
		document.body.innerHTML = ''
		// The dialog's live preview writes the new values inline on <html>.
		document.documentElement.removeAttribute('style')
		vi.restoreAllMocks()
	})

	it('names the confirmation instead of a bare failure', async () => {
		vi.spyOn(console, 'error').mockImplementation(() => {})
		const messages = await applyEpe({
			error: 'restricted_set_not_confirmed',
			tokenSet: 'epe',
		})

		expect(messages).toContain(CONFIRM_FIRST)
		expect(messages).not.toContain('Failed to apply token set.')
	})

	it('keeps the plain failure for any other refusal', async () => {
		vi.spyOn(console, 'error').mockImplementation(() => {})
		const messages = await applyEpe({ error: 'Invalid token set' })

		expect(messages).toContain('Failed to apply token set.')
		expect(messages).not.toContain(CONFIRM_FIRST)
	})
})
