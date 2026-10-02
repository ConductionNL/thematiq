/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/personal-group-house-style.js: a subadmin sees each
 * delegated group with its allowed sets and contrast, and a choice is saved
 * for that group only.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/per-group-theming/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

let groups
let postAnswer

async function loadScript() {
	vi.resetModules()
	await import('../../js/personal-group-house-style.js?t=' + Math.random())
	for (let i = 0; i < 4; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

describe('house style of my groups', () => {
	beforeEach(() => {
		global.t = (app, text, params) =>
			params === undefined
				? text
				: Object.keys(params).reduce(
						(acc, key) => acc.replace('{' + key + '}', params[key]),
						text,
					)
		global.OC = { generateUrl: (url) => url, requestToken: 'token' }
		postAnswer = { ok: true, body: { tokenSet: 'gemeente-a-huisstijl' } }
		global.fetch = vi.fn((url, options) => {
			if (options && options.method === 'POST') {
				return Promise.resolve({
					ok: postAnswer.ok,
					json: () => Promise.resolve(postAnswer.body),
				})
			}
			return Promise.resolve({
				ok: true,
				json: () => Promise.resolve({ groups: groups }),
			})
		})
		document.body.innerHTML =
			'<div id="thematiq-my-groups-list"></div><p id="thematiq-my-groups-feedback"></p>'
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('lists the allowed sets of a delegated group with contrast, and saves a choice', async () => {
		groups = [
			{
				group: 'gemeente-a',
				displayName: 'Gemeente A',
				tokenSet: 'rijkshuisstijl',
				allowedTokenSets: [
					{
						id: 'rijkshuisstijl',
						name: 'Rijkshuisstijl',
						primaryColor: '#154273',
						wcagLevel: 'AA',
					},
					{
						id: 'gemeente-a-huisstijl',
						name: 'Gemeente A',
						primaryColor: '#c8102e',
						wcagLevel: null,
					},
				],
			},
		]
		await loadScript()

		const legend = document.querySelector('fieldset legend')
		expect(legend.textContent).toBe('Gemeente A')
		const radios = document.querySelectorAll('input[type="radio"]')
		expect(radios.length).toBe(2)
		expect(radios[0].checked).toBe(true)
		expect(document.body.textContent).toContain('Contrast: WCAG AA')
		expect(document.body.textContent).toContain('Contrast: below WCAG AA')

		radios[1].checked = true
		radios[1].dispatchEvent(new Event('change'))
		await new Promise((resolve) => setTimeout(resolve, 0))
		await new Promise((resolve) => setTimeout(resolve, 0))

		const post = global.fetch.mock.calls.find(
			(c) => c[1] && c[1].method === 'POST',
		)
		expect(post[0]).toBe('/apps/thematiq/api/my-groups/gemeente-a/house-style')
		expect(JSON.parse(post[1].body)).toEqual({
			tokenSet: 'gemeente-a-huisstijl',
		})
		expect(
			document.getElementById('thematiq-my-groups-feedback').textContent,
		).toContain('Gemeente A')
	})

	it('says so when the server refuses', async () => {
		groups = [
			{
				group: 'gemeente-a',
				displayName: 'Gemeente A',
				tokenSet: 'rijkshuisstijl',
				allowedTokenSets: [
					{
						id: 'rijkshuisstijl',
						name: 'Rijkshuisstijl',
						primaryColor: null,
						wcagLevel: 'AA',
					},
					{
						id: 'amsterdam',
						name: 'Amsterdam',
						primaryColor: null,
						wcagLevel: 'AA',
					},
				],
			},
		]
		postAnswer = {
			ok: false,
			body: { error: 'This token set is not allowed for this group.' },
		}
		await loadScript()

		const radios = document.querySelectorAll('input[type="radio"]')
		radios[1].dispatchEvent(new Event('change'))
		await new Promise((resolve) => setTimeout(resolve, 0))
		await new Promise((resolve) => setTimeout(resolve, 0))

		expect(
			document.getElementById('thematiq-my-groups-feedback').textContent,
		).toBe('This token set is not allowed for this group.')
	})

	it('says when no group can choose', async () => {
		groups = []
		await loadScript()
		expect(document.body.textContent).toContain('None of your groups')
	})
})
