/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin.js: the "Planned switches" block. It lists the
 * planned switches with a cancel button each, shows when the job last ran
 * and warns on AJAX cron, plans a switch with the times converted to UTC,
 * shows a refusal, and cancels through DELETE.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/apply-scheduled-theme-switch/specs/scheduled-switch/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

let calls
let state
let createAnswer

function install() {
	calls = []
	createAnswer = { status: 201, body: { switch: { id: 'new1' } } }
	state = {
		switches: [
			{
				id: 'a1',
				tokenSet: 'koningsdag-oranje',
				startAt: '2027-04-26T16:00:00Z',
				endAt: '2027-04-28T06:00:00Z',
				status: 'planned',
			},
			{
				id: 'f1',
				tokenSet: 'custom-campagne',
				startAt: '2027-05-01T00:00:00Z',
				endAt: null,
				status: 'failed',
				failureReason: 'The token set custom-campagne no longer exists, so the switch was not applied.',
			},
		],
		status: {
			activeTokenSet: 'rijkshuisstijl',
			activeUntil: null,
			revertTo: null,
			lastRun: '2027-04-20T12:00:00Z',
			cronMode: 'ajax',
			cronWarning: true,
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
		linkTo: (app, path) => path,
		requestToken: 'test-token',
		Notification: { showTemporary: vi.fn() },
		dialogs: { confirm: vi.fn() },
	}
	global.OCP = { InitialState: { loadState: (app, key, fallback) => fallback } }
	global.fetch = vi.fn((url, options) => {
		const method = (options && options.method) || 'GET'
		calls.push({ url, method, body: options && options.body })
		let status = 200
		let body = {}
		if (url.indexOf('/settings/scheduled-switches') !== -1) {
			if (method === 'GET') {
				body = state
			} else if (method === 'POST') {
				status = createAnswer.status
				body = createAnswer.body
			} else if (method === 'DELETE') {
				body = { status: 'ok' }
			}
		}
		return Promise.resolve({
			status,
			ok: status < 400,
			json: () => Promise.resolve(body),
		})
	})
}

async function flush(rounds = 10) {
	for (let i = 0; i < rounds; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

async function load() {
	document.body.innerHTML = `
		<div id="nldesign-settings" class="section">
			<div id="nldesign-scheduled-switches">
				<p id="nldesign-scheduled-status"></p>
				<p id="nldesign-scheduled-cron-warning" hidden>AJAX</p>
				<form id="nldesign-scheduled-form">
					<select id="nldesign-scheduled-set"><option value="koningsdag-oranje">Koningsdag</option></select>
					<input type="datetime-local" id="nldesign-scheduled-start">
					<input type="datetime-local" id="nldesign-scheduled-end">
					<input type="checkbox" id="nldesign-scheduled-sync">
					<p id="nldesign-scheduled-zone"></p>
					<button type="submit" id="nldesign-scheduled-submit">Plan switch</button>
				</form>
				<ul id="nldesign-scheduled-list"></ul>
			</div>
		</div>
	`
	vi.resetModules()
	await import('../../js/admin.js?t=' + Math.random())
	await flush()
}

describe('admin.js planned switches', () => {
	beforeEach(() => {
		install()
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('lists the switches with a cancel button each and the failure reason', async () => {
		await load()

		const items = document.querySelectorAll('#nldesign-scheduled-list li')
		expect(items).toHaveLength(2)
		expect(items[0].textContent).toContain('koningsdag-oranje')
		expect(
			items[0].querySelector('button').getAttribute('aria-label'),
		).toContain('koningsdag-oranje')
		expect(items[1].textContent).toContain('no longer exists')
	})

	it('shows the last run and warns on AJAX cron', async () => {
		await load()

		expect(
			document.getElementById('nldesign-scheduled-cron-warning').hidden,
		).toBe(false)
		expect(
			document.getElementById('nldesign-scheduled-status').textContent,
		).toContain('last ran')
	})

	it('shows what is active until when during a running switch', async () => {
		state.status.activeTokenSet = 'koningsdag-oranje'
		state.status.activeUntil = '2027-04-28T06:00:00Z'
		state.status.revertTo = 'rijkshuisstijl'
		state.status.cronWarning = false
		await load()

		const status = document.getElementById('nldesign-scheduled-status')
		expect(status.textContent).toContain('koningsdag-oranje is active until')
		expect(status.textContent).toContain('rijkshuisstijl')
		expect(
			document.getElementById('nldesign-scheduled-cron-warning').hidden,
		).toBe(true)
	})

	it('plans a switch with the local times sent as UTC', async () => {
		await load()

		document.getElementById('nldesign-scheduled-start').value =
			'2027-04-26T18:00'
		document.getElementById('nldesign-scheduled-end').value = '2027-04-28T08:00'
		document.getElementById('nldesign-scheduled-sync').checked = true
		document
			.getElementById('nldesign-scheduled-form')
			.dispatchEvent(new Event('submit', { cancelable: true }))
		await flush()

		const post = calls.find((c) => c.method === 'POST')
		const body = new URLSearchParams(post.body)
		expect(body.get('tokenSet')).toBe('koningsdag-oranje')
		expect(body.get('startAt')).toBe(
			new Date('2027-04-26T18:00').toISOString(),
		)
		expect(body.get('endAt')).toBe(new Date('2027-04-28T08:00').toISOString())
		expect(body.get('syncCoreTheming')).toBe('1')
		// The list is loaded again after planning.
		expect(calls.filter((c) => c.method === 'GET')).toHaveLength(2)
	})

	it('shows the reason when a plan is refused', async () => {
		createAnswer = {
			status: 400,
			body: { error: 'This window overlaps the planned switch to x.' },
		}
		await load()

		document.getElementById('nldesign-scheduled-start').value =
			'2027-05-05T00:00'
		document
			.getElementById('nldesign-scheduled-form')
			.dispatchEvent(new Event('submit', { cancelable: true }))
		await flush()

		expect(OC.Notification.showTemporary).toHaveBeenCalledWith(
			'This window overlaps the planned switch to x.',
		)
	})

	it('cancels a switch through DELETE and reloads the list', async () => {
		await load()

		document.querySelector('#nldesign-scheduled-list li button').click()
		await flush()

		const del = calls.find((c) => c.method === 'DELETE')
		expect(del.url).toContain('/settings/scheduled-switches/a1')
		expect(calls.filter((c) => c.method === 'GET')).toHaveLength(2)
	})
})
