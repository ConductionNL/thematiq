/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin.js — writing synced values back into Nextcloud's
 * OWN Theming panel, which sits on the same settings page, without a reload.
 *
 * The panel is core's Vue app and its colour pickers are not styled from an
 * attribute or a class: `apps/theming/src/components/admin/ColorPickerField.vue`
 * uses `v-bind('value')` in its scoped style, which the build compiles to a
 * hash-named custom property written inline on the field's root element:
 *
 *   .field__button[data-v-cb8f4a16]{background-color:var(--6cc639bc)!important;
 *                                   color:var(--6fa57444)!important}
 *   "6cc639bc": e.value        "6fa57444": e.usedTextColor
 *
 * Updating the button's LABEL therefore left it showing the old colour — the
 * bug these tests pin. The hash changes with every Nextcloud build, so
 * admin.js discovers it at runtime; the fixtures below use deliberately
 * different hashes to prove nothing is hard-coded.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/apply-without-reload/specs/theming-sync-dialog/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

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
		const route = byMethod || routes.filter((route) => route[2] === undefined).find(matches)
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
	await import('../../js/admin.js?t=' + Math.random())
	await flush()
}

const OPENWOO = {
	id: 'custom-openwoo',
	name: 'OpenWOO',
	design_system: 'nldesign',
	theming: {
		primary_color: '#23845c',
		background_color: '#ffffff',
		logo: 'img/logos/custom-openwoo.svg',
	},
}

/**
 * The settings page plus a stand-in for core's Theming panel, built the way
 * the compiled Vue writes it: the colour lives ONLY in an inline custom
 * property whose name is a build hash.
 *
 * @param {string} primaryHash Hash core's build gave `value` on the primary field.
 * @param {string} primaryTextHash Hash it gave `usedTextColor`.
 */
function buildDom(primaryHash, primaryTextHash) {
	installInitialState({
		tokenSets: [OPENWOO],
		currentTokenSet: 'nextcloud',
		activePreview: null,
		iconPackSource: '',
	})
	document.body.innerHTML = `
		<div id="nldesign-settings" class="section">
			<select id="nldesign-token-set-select" name="nldesign-token-set">
				<option value="nextcloud" data-design-system="none" selected>Nextcloud (default)</option>
				<option value="custom-openwoo" data-design-system="nldesign">OpenWOO</option>
			</select>
			<span id="nldesign-design-system-badge"></span>
		</div>
		<div class="nldesign-preview" id="nldesign-preview"></div>

		<!-- core's Theming panel, as its build emits it -->
		<div class="field" data-admin-theming-setting-primary-color
			 style="--${primaryHash}: #0082c9; --${primaryTextHash}: #ffffff">
			<button class="button-vue" data-admin-theming-setting-color-picker>
				<span class="button-vue__text">#0082c9</span>
			</button>
			<div class="field__color-preview" data-admin-theming-setting-color></div>
		</div>
		<div class="field" data-admin-theming-setting-background-color
			 style="--aaa11122: #0082c9; --bbb33344: #ffffff">
			<button class="button-vue" data-admin-theming-setting-color-picker>
				<span class="button-vue__text">#0082c9</span>
			</button>
			<div class="field__color-preview" data-admin-theming-setting-color></div>
		</div>
		<div class="admin-theming__preview" data-admin-theming-preview>
			<div class="admin-theming__preview-logo" data-admin-theming-preview-logo></div>
		</div>
	`
}

/**
 * Select OpenWOO and confirm the apply dialog, whose theming section is
 * checked by default — the one-confirm path an admin takes.
 *
 * @param {object} themingSnapshot What GET /settings/theming answers AFTER the sync.
 */
async function applyOpenwoo(themingSnapshot, postLog) {
	installFetchRouter(
		[
			// A token diff exists, so the apply dialog (with its theming
			// section) is what opens — not the standalone sync dialog.
			['tokenset-preview', { resolved: { '--color-primary': '#23845c' } }],
			['tokenset-stylesheets', { tokenSet: 'custom-openwoo', designSystem: 'nldesign', layers: [] }],
			['/settings/overrides', { overrides: {}, status: 'ok' }],
			['/settings/tokenset', { status: 'ok' }],
			// The sync itself; the GET below then answers with the post-sync
			// snapshot, which is what the panel is rebuilt from.
			['/settings/theming', { status: 'ok' }, 'POST'],
			['/settings/theming', themingSnapshot],
		],
		postLog,
	)

	await loadAdminScript()

	const select = document.getElementById('nldesign-token-set-select')
	select.value = 'custom-openwoo'
	select.dispatchEvent(new window.Event('change', { bubbles: true }))
	await flush()

	const overlay = document.getElementById('nldesign-apply-dialog-overlay')
	expect(overlay, 'the apply dialog should open').not.toBeNull()
	overlay
		.querySelector('.nldesign-dialog-confirm')
		.dispatchEvent(new window.Event('click', { bubbles: true }))
	await flush(20)
}

describe('admin.js — core Theming panel after a sync', () => {
	beforeEach(() => {
		installGlobals()
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('rebinds the compiled v-bind custom property, so the picker button changes colour', async () => {
		buildDom('6cc639bc', '6fa57444')
		await applyOpenwoo({
			primary_color: '#23845c',
			background_color: '#ffffff',
			logo_url: '/apps/theming/image/logo?v=9',
			has_custom_logo: true,
			has_custom_background: false,
			default_primary_color: '#00679e',
			default_background_color: '#00679e',
		})

		const field = document.querySelector('[data-admin-theming-setting-primary-color]')
		// The colour, not just the label.
		expect(field.style.getPropertyValue('--6cc639bc').trim()).toBe('#23845c')
		// #23845c is dark, so core would put white text on it.
		expect(field.style.getPropertyValue('--6fa57444').trim()).toBe('#ffffff')
		expect(field.querySelector('.button-vue__text').textContent).toBe('#23845c')
		expect(field.querySelector('[data-admin-theming-setting-color]').style.backgroundColor)
			.toBe('rgb(35, 132, 92)')
	})

	it('discovers the hash at runtime — a different build still works', async () => {
		buildDom('deadbeef', 'cafed00d')
		await applyOpenwoo({
			primary_color: '#23845c',
			background_color: '#ffffff',
			has_custom_logo: false,
			has_custom_background: false,
			default_primary_color: '#00679e',
			default_background_color: '#00679e',
		})

		const field = document.querySelector('[data-admin-theming-setting-primary-color]')
		expect(field.style.getPropertyValue('--deadbeef').trim()).toBe('#23845c')
		expect(field.style.getPropertyValue('--cafed00d').trim()).toBe('#ffffff')
	})

	it('puts black text on a light colour, as core would', async () => {
		buildDom('6cc639bc', '6fa57444')
		await applyOpenwoo({
			primary_color: '#23845c',
			background_color: '#ffffff',
			has_custom_logo: false,
			has_custom_background: false,
			default_primary_color: '#00679e',
			default_background_color: '#00679e',
		})

		// The background field's synced value is #ffffff — white on white
		// would leave the label invisible, which is what the first attempt did.
		const field = document.querySelector('[data-admin-theming-setting-background-color]')
		expect(field.style.getPropertyValue('--aaa11122').trim()).toBe('#ffffff')
		expect(field.style.getPropertyValue('--bbb33344').trim()).toBe('#000000')
	})

	it('falls back to core defaults when a reset emptied the values', async () => {
		buildDom('6cc639bc', '6fa57444')
		await applyOpenwoo({
			// What GET /settings/theming answers after DELETE: nothing is set.
			primary_color: '',
			background_color: '',
			has_custom_logo: false,
			has_custom_background: false,
			default_primary_color: '#00679e',
			default_background_color: '#00679e',
		})

		const field = document.querySelector('[data-admin-theming-setting-primary-color]')
		expect(field.style.getPropertyValue('--6cc639bc').trim()).toBe('#00679e')
		expect(field.querySelector('.button-vue__text').textContent).toBe('#00679e')
	})

	it('updates the logo preview from the snapshot URL', async () => {
		buildDom('6cc639bc', '6fa57444')
		await applyOpenwoo({
			primary_color: '#23845c',
			background_color: '#ffffff',
			logo_url: '/apps/theming/image/logo?v=42',
			has_custom_logo: true,
			has_custom_background: false,
			default_primary_color: '#00679e',
			default_background_color: '#00679e',
		})

		const preview = document.querySelector('[data-admin-theming-preview-logo]')
		expect(preview.style.backgroundImage).toContain('/apps/theming/image/logo?v=42')
	})

	it('offers nothing when the slot already holds this set\'s logo', async () => {
		// The bug this pins: `if (proposed.logo)` never compared, so a set with
		// a logo always looked changed and the dialog could never stop
		// appearing — measured live, with primary, background AND logo already
		// synced. `synced_logo` is what core cannot tell us.
		buildDom('6cc639bc', '6fa57444')
		installFetchRouter([
			['tokenset-preview', { error: 'not applicable' }],
			['tokenset-stylesheets', { tokenSet: 'custom-openwoo', designSystem: 'nldesign', layers: [] }],
			['/settings/tokenset', { status: 'ok' }],
			[
				'/settings/theming',
				{
					primary_color: '#23845c',
					background_color: '#ffffff',
					has_custom_logo: true,
					has_custom_background: false,
					synced_logo: 'img/logos/custom-openwoo.svg',
					synced_background: '',
					default_primary_color: '#00679e',
					default_background_color: '#00679e',
				},
			],
		])

		await loadAdminScript()
		const select = document.getElementById('nldesign-token-set-select')
		select.value = 'custom-openwoo'
		select.dispatchEvent(new window.Event('change', { bubbles: true }))
		await flush(20)

		expect(document.getElementById('nldesign-theming-dialog-overlay')).toBeNull()
	})

	it('offers the logo when the slot holds a different one', async () => {
		buildDom('6cc639bc', '6fa57444')
		installFetchRouter([
			['tokenset-preview', { error: 'not applicable' }],
			['tokenset-stylesheets', { tokenSet: 'custom-openwoo', designSystem: 'nldesign', layers: [] }],
			['/settings/tokenset', { status: 'ok' }],
			[
				'/settings/theming',
				{
					primary_color: '#23845c',
					background_color: '#ffffff',
					has_custom_logo: true,
					has_custom_background: false,
					synced_logo: 'img/logos/amsterdam.svg',
					synced_background: '',
					default_primary_color: '#00679e',
					default_background_color: '#00679e',
				},
			],
		])

		await loadAdminScript()
		const select = document.getElementById('nldesign-token-set-select')
		select.value = 'custom-openwoo'
		select.dispatchEvent(new window.Event('change', { bubbles: true }))
		await flush(20)

		const overlay = document.getElementById('nldesign-theming-dialog-overlay')
		expect(overlay).not.toBeNull()
		expect(overlay.textContent).toContain('custom-openwoo.svg')
	})

	it('syncs on the apply confirm, with no second dialog', async () => {
		const postLog = []
		buildDom('6cc639bc', '6fa57444')
		await applyOpenwoo(
			{
				// A PRE-sync state: stock primary, no custom logo. The set's
				// #23845c and its wordmark therefore both belong in the POST.
				// (Handing this test the POST-sync values would mean nothing
				// differed, and the body would carry neither.)
				primary_color: '#00679e',
				background_color: '#ffffff',
				has_custom_logo: false,
				has_custom_background: false,
				default_primary_color: '#00679e',
				default_background_color: '#00679e',
			},
			postLog,
		)

		const sync = postLog.find(
			(entry) => entry.method === 'POST' && entry.url.indexOf('/settings/theming') !== -1,
		)
		expect(sync, 'the apply confirm should POST the theming sync').toBeTruthy()
		expect(sync.body).toContain('primary_color')
		expect(sync.body).toContain('logo')
		// The whole point: no follow-up modal.
		expect(document.getElementById('nldesign-theming-dialog-overlay')).toBeNull()
	})
})
