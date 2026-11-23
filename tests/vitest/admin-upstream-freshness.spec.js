/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin.js: the upstream token freshness panel. It shows
 * whether the daily check is on, when it last ran, and one notice per
 * upstream update (named after the set, or generic), each with a Dismiss
 * button; the toggle saves the setting and is put back when saving fails.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/upstream-freshness/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const URL_STATUS = '/index.php/apps/thematiq/settings/upstream-freshness'
const URL_DISMISS = URL_STATUS + '/dismiss'

let status
let saveAnswer
let toast

/** Globals admin.js reads at load time; the freshness endpoints answer from the variables above. */
function installGlobals() {
	global.t = (app, text, values = {}) => text.replace(/\{(\w+)\}/g, (match, key) => (key in values ? values[key] : match))
	global.n = (app, singular, plural, count) => (count === 1 ? singular : plural)
	toast = vi.fn()
	global.OC = {
		generateUrl: (url) => '/index.php' + url,
		linkTo: (app, path) => path,
		requestToken: 'test-token',
		Notification: { showTemporary: vi.fn() },
		dialogs: { confirm: vi.fn() },
	}
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) => (key === 'tokenSets' ? [{ id: 'utrecht', name: 'Gemeente Utrecht' }] : fallback),
		},
		Toast: { message: toast },
	}
	window.OCP = global.OCP
	global.fetch = vi.fn((url, options = {}) => {
		let answer = {}
		if (url === URL_STATUS && options.method === 'POST') {
			if (saveAnswer instanceof Error) {
				return Promise.reject(saveAnswer)
			}
			answer = saveAnswer
		} else if (url === URL_STATUS) {
			if (status instanceof Error) {
				return Promise.reject(status)
			}
			answer = status
		}
		return Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve(answer), headers: { get: () => null } })
	})
}

/** Flush pending promise chains. */
async function flush(rounds = 8) {
	for (let i = 0; i < rounds; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

async function load() {
	vi.resetModules()
	await import('../../js/admin.js?t=' + Math.random())
	await flush()
}

const $ = (id) => document.getElementById(id)
const notices = () => Array.from($('nldesign-upstream-freshness-notices').children).map((row) => row.querySelector('span').textContent)
const calls = (url, method) => global.fetch.mock.calls.filter(([u, o = {}]) => u === url && (o.method || 'GET') === method)

describe('admin.js upstream token freshness', () => {
	beforeEach(() => {
		status = {
			enabled: true,
			lastChecked: '1700000000',
			notices: [
				{ setId: 'utrecht', upstreamVersion: 'v2.1.0' },
				{ setId: 'unknown-set', headSha: 'abcdef1234567' },
				{ setId: '__generic__', headSha: '0123456789abc' },
			],
		}
		saveAnswer = { status: 'ok' }
		installGlobals()
		document.body.innerHTML = `
			<div id="nldesign-settings" class="section">
				<input type="checkbox" id="nldesign-upstream-freshness-toggle">
				<p id="nldesign-upstream-freshness-lastchecked"></p>
				<div id="nldesign-upstream-freshness-notices"></div>
			</div>
		`
	})

	afterEach(() => {
		document.body.innerHTML = ''
		delete window.OCP
		vi.restoreAllMocks()
	})

	it('shows the toggle, when the check last ran, and a notice per update', async () => {
		await load()

		expect($('nldesign-upstream-freshness-toggle').checked).toBe(true)
		expect($('nldesign-upstream-freshness-lastchecked').textContent).toBe('Last checked: ' + new Date(1700000000 * 1000).toLocaleString())
		expect(notices()).toEqual([
			'Token set Gemeente Utrecht has upstream update v2.1.0 — review & apply',
			'Token set unknown-set has upstream update abcdef1 — review & apply',
			'Upstream token sets have updates (0123456) — review & apply',
		])
		expect($('nldesign-upstream-freshness-notices').querySelectorAll('button')).toHaveLength(3)
	})

	it.each([
		['never checked', undefined, 'Not checked yet.'],
		['a date text', '2026-11-23T10:00:00Z', 'Last checked: ' + new Date(Date.parse('2026-11-23T10:00:00Z')).toLocaleString()],
		['a value that is no date', 'yesterday', 'Last checked: yesterday'],
	])('reads the last check when it was %s', async (label, lastChecked, expected) => {
		status = { enabled: false, lastChecked, notices: [] }
		await load()
		expect($('nldesign-upstream-freshness-toggle').checked).toBe(false)
		expect($('nldesign-upstream-freshness-lastchecked').textContent).toBe(expected)
		expect(notices()).toEqual([])
	})

	it('names the update with an empty version when it has neither a version nor a sha', async () => {
		status = { enabled: true, notices: [{ setId: '__generic__' }] }
		await load()
		expect(notices()).toEqual(['Upstream token sets have updates () — review & apply'])
	})

	it('dismisses a notice with its set and version, or its sha, and reloads the panel', async () => {
		await load()
		const before = calls(URL_STATUS, 'GET').length
		const buttons = $('nldesign-upstream-freshness-notices').querySelectorAll('button')
		buttons[0].click()
		await flush()
		buttons[1].click()
		await flush()

		const bodies = calls(URL_DISMISS, 'POST').map(([, options]) => JSON.parse(options.body))
		expect(bodies).toEqual([
			{ setId: 'utrecht', version: 'v2.1.0' },
			{ setId: 'unknown-set', version: 'abcdef1234567' },
		])
		expect(calls(URL_DISMISS, 'POST')[0][1].headers).toEqual({ 'Content-Type': 'application/json', requesttoken: 'test-token' })
		expect(calls(URL_STATUS, 'GET').length).toBe(before + 2)
	})

	it('saves the toggle, says so, and reloads the panel', async () => {
		await load()
		const toggle = $('nldesign-upstream-freshness-toggle')
		toggle.checked = false
		toggle.dispatchEvent(new Event('change'))
		await flush()

		const [[, options]] = calls(URL_STATUS, 'POST')
		expect(JSON.parse(options.body)).toEqual({ enabled: false })
		expect(toast).toHaveBeenCalledWith('Upstream token update checks disabled.')

		status = { enabled: true, notices: [] }
		toggle.checked = true
		toggle.dispatchEvent(new Event('change'))
		await flush()
		expect(toast).toHaveBeenCalledWith('Upstream token update checks enabled.')
	})

	it('puts the toggle back when saving fails', async () => {
		await load()
		saveAnswer = new Error('offline')
		const error = vi.spyOn(console, 'error').mockImplementation(() => {})
		const toggle = $('nldesign-upstream-freshness-toggle')
		toggle.checked = false
		toggle.dispatchEvent(new Event('change'))
		await flush()

		expect(toggle.checked).toBe(true)
		expect(error).toHaveBeenCalledWith('Error saving upstream freshness setting:', saveAnswer)
	})

	it('leaves the panel empty when the status cannot be loaded', async () => {
		status = new Error('offline')
		const error = vi.spyOn(console, 'error').mockImplementation(() => {})
		await load()
		expect(error).toHaveBeenCalledWith('Error loading upstream freshness status:', status)
		expect(notices()).toEqual([])
	})
})
