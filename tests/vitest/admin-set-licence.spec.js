/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin-set-licence.js: each restricted house style shows
 * its warning and licence text, is confirmed only with the statement ticked,
 * and a set applied without a confirmation is announced.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-the-picker-shows-the-warning-and-the-licence-text
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

let overview
let calls
let withdrawAnswer

async function flush() {
	for (let i = 0; i < 8; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

async function loadScript() {
	vi.resetModules()
	await import('../../js/admin-set-licence.js?t=' + Math.random())
	await flush()
}

function answer(status, body) {
	return Promise.resolve({
		ok: status < 400,
		status,
		json: () => Promise.resolve(body),
	})
}

describe('House styles of other organisations', () => {
	beforeEach(() => {
		global.t = (app, text) => text
		global.OC = { generateUrl: (url) => url, requestToken: 'token' }
		calls = []
		withdrawAnswer = { status: 200, body: { status: 'ok' } }
		overview = {
			sets: [
				{
					id: 'owned',
					name: 'Gemeente Voorbeeld',
					licence: {
						id: 'LicenseRef-Voorbeeld',
						package: '@example/voorbeeld-design-tokens',
						version: '1.0.3',
						holder: 'Gemeente Voorbeeld',
						restricted: true,
					},
					confirmation: null,
				},
				{
					id: 'owned-two',
					name: 'Gemeente Tweede',
					licence: {
						id: 'LicenseRef-Tweede',
						package: '@example/tweede',
						version: '1.0.0',
						holder: 'Gemeente Tweede',
						restricted: true,
					},
					confirmation: { uid: 'ruben', at: '2026-10-12T20:15:00+00:00' },
				},
			],
			awaiting: [],
		}
		global.fetch = vi.fn((url, options) => {
			const method = (options && options.method) || 'GET'
			calls.push({ url, method, body: options && options.body })
			if (url.endsWith('/licences')) {
				return answer(200, overview)
			}
			if (url.endsWith('/licence')) {
				return answer(200, { text: 'All rights reserved.' })
			}
			if (method === 'POST') {
				overview.sets[0].confirmation = {
					uid: 'noor',
					at: '2026-10-12T21:00:00+00:00',
				}
				return answer(200, { status: 'ok' })
			}
			return answer(withdrawAnswer.status, withdrawAnswer.body)
		})
		document.body.innerHTML =
			'<select id="nldesign-token-set-select">'
			+ '<option value="nextcloud" selected>Nextcloud</option>'
			+ '<option value="owned">Gemeente Voorbeeld</option>'
			+ '<option value="owned-two">Gemeente Tweede</option></select>'
			+ '<p id="nldesign-set-licence-hint" hidden></p>'
			+ '<div id="nldesign-set-licences" hidden>'
			+ '<div id="nldesign-set-licences-awaiting" hidden></div>'
			+ '<div id="nldesign-set-licences-list"></div></div>'
	})

	afterEach(() => {
		document.body.innerHTML = ''
	})

	it('lists every restricted set with its warning, source and status', async () => {
		await loadScript()

		expect(document.getElementById('nldesign-set-licences').hidden).toBe(false)
		const owned = document.getElementById('nldesign-set-licence-owned')
		expect(owned.querySelector('summary').textContent).toBe(
			'Gemeente Voorbeeld · Not confirmed',
		)
		expect(
			owned.querySelector('.nldesign-set-licence-warning').textContent,
		).toBe(
			'This house style belongs to Gemeente Voorbeeld. Its licence allows use only by Gemeente Voorbeeld, or with its permission.',
		)
		expect(owned.textContent).toContain(
			'Source: @example/voorbeeld-design-tokens 1.0.3',
		)
		expect(
			document
				.getElementById('nldesign-set-licence-owned-two')
				.querySelector('summary').textContent,
		).toContain('Confirmed by ruben on ')
	})

	it('stays hidden when no set is restricted', async () => {
		overview.sets = []
		await loadScript()

		expect(document.getElementById('nldesign-set-licences').hidden).toBe(true)
	})

	it('shows the licence text once a set is opened', async () => {
		await loadScript()
		const owned = document.getElementById('nldesign-set-licence-owned')
		expect(calls.some((c) => c.url.endsWith('/owned/licence'))).toBe(false)

		owned.open = true
		owned.dispatchEvent(new Event('toggle'))
		await flush()

		expect(owned.querySelector('.nldesign-set-licence-text').textContent).toBe(
			'All rights reserved.',
		)
	})

	it('confirms only with the statement ticked', async () => {
		await loadScript()
		const owned = document.getElementById('nldesign-set-licence-owned')
		const check = owned.querySelector('input[type="checkbox"]')
		const button = owned.querySelector('button')
		expect(owned.querySelector('label').textContent).toBe(
			'We are this organisation, or we have its permission to use this house style.',
		)
		expect(button.disabled).toBe(true)

		check.checked = true
		check.dispatchEvent(new Event('change'))
		expect(button.disabled).toBe(false)
		button.click()
		await flush()

		const post = calls.find((c) => c.method === 'POST')
		expect(post.url).toBe(
			'/apps/thematiq/settings/tokensets/owned/licence/confirmation',
		)
		expect(JSON.parse(post.body)).toEqual({ confirmed: true })
		expect(
			document
				.getElementById('nldesign-set-licence-owned')
				.querySelector('summary').textContent,
		).toContain('Confirmed by noor on ')
	})

	it('says where a set is still in use when a withdrawal is refused', async () => {
		withdrawAnswer = {
			status: 409,
			body: {
				error: 'restricted_set_in_use',
				appliedIn: [
					{ kind: 'instance', id: '' },
					{ kind: 'group', id: 'ruimte' },
				],
			},
		}
		await loadScript()
		const two = document.getElementById('nldesign-set-licence-owned-two')

		two.querySelector('button').click()
		await flush()

		expect(calls.find((c) => c.method === 'DELETE').url).toBe(
			'/apps/thematiq/settings/tokensets/owned-two/licence/confirmation',
		)
		expect(two.querySelector('[role="status"]').textContent).toBe(
			'This house style is still in use. Choose another one there first: the whole instance, group ruimte.',
		)
	})

	it('announces a set that is applied without a confirmation', async () => {
		overview.awaiting = [
			{ tokenSet: 'owned', appliedIn: [{ kind: 'instance', id: '' }] },
		]
		await loadScript()
		const notice = document.getElementById('nldesign-set-licences-awaiting')

		expect(notice.hidden).toBe(false)
		expect(notice.textContent).toContain(
			'Gemeente Voorbeeld is in use, and nobody has confirmed that your organisation may use it. It stays applied.',
		)
		notice.querySelector('a').click()
		expect(document.getElementById('nldesign-set-licence-owned').open).toBe(true)
	})

	it('points from the picker to the confirmation of an unconfirmed set', async () => {
		await loadScript()
		const select = document.getElementById('nldesign-token-set-select')
		const hint = document.getElementById('nldesign-set-licence-hint')
		expect(hint.hidden).toBe(true)

		select.value = 'owned'
		select.dispatchEvent(new Event('change'))
		expect(hint.hidden).toBe(false)
		expect(hint.textContent).toContain(
			'Confirm first that your organisation may use this house style.',
		)

		select.value = 'owned-two'
		select.dispatchEvent(new Event('change'))
		expect(hint.hidden).toBe(true)
	})
})
