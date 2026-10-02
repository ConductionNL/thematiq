/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin-config-source.js: the configuration bundle block
 * says the house style is managed from deployment configuration, names the
 * path and revision, lists the last error, shows drift, and disables the
 * controls while the lock is on.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

let answer

async function loadScript() {
	vi.resetModules()
	await import('../../js/admin-config-source.js?t=' + Math.random())
	await new Promise((resolve) => setTimeout(resolve, 0))
	await new Promise((resolve) => setTimeout(resolve, 0))
}

describe('configuration source block', () => {
	beforeEach(() => {
		global.t = (app, text, params) =>
			params === undefined
				? text
				: Object.keys(params).reduce(
						(acc, key) => acc.replace('{' + key + '}', params[key]),
						text,
					)
		global.OC = { generateUrl: (url) => url, requestToken: 'token' }
		global.fetch = vi.fn(() =>
			Promise.resolve({ ok: true, json: () => Promise.resolve(answer) }),
		)
		document.body.innerHTML =
			'<div id="nldesign-settings"><button id="save">Save</button>'
			+ '<div id="nldesign-config-source" hidden></div></div>'
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('stays hidden when no source is set', async () => {
		answer = { managed: false }
		await loadScript()
		expect(document.getElementById('nldesign-config-source').hidden).toBe(true)
		expect(document.getElementById('save').disabled).toBe(false)
	})

	it('names the path and the revision of a managed server', async () => {
		answer = {
			managed: true,
			path: '/srv/branding',
			locked: false,
			revision: '3f2a9c1',
			appliedAt: '2026-10-02T10:00:00+00:00',
			lastError: null,
			drift: false,
		}
		await loadScript()
		const block = document.getElementById('nldesign-config-source')
		expect(block.hidden).toBe(false)
		expect(block.textContent).toContain(
			'managed from deployment configuration: /srv/branding',
		)
		expect(block.textContent).toContain('3f2a9c1')
		expect(block.querySelector('.nldesign-config-source-drift')).toBeNull()
	})

	it('lists the last error and shows drift', async () => {
		answer = {
			managed: true,
			path: '/srv/branding',
			locked: false,
			revision: null,
			appliedAt: null,
			lastError: {
				errors: [
					{ section: 'customTokenSets', message: 'Token not allowed.' },
				],
			},
			drift: true,
		}
		await loadScript()
		const block = document.getElementById('nldesign-config-source')
		expect(
			block.querySelector('.nldesign-config-source-errors li').textContent,
		).toBe('[customTokenSets] Token not allowed.')
		expect(block.querySelector('.nldesign-config-source-drift')).not.toBeNull()
	})

	it('disables the controls while locked, also ones rendered later', async () => {
		answer = { managed: true, path: '/srv/branding', locked: true, drift: false }
		await loadScript()
		expect(document.getElementById('save').disabled).toBe(true)

		const later = document.createElement('input')
		document.getElementById('nldesign-settings').appendChild(later)
		await new Promise((resolve) => setTimeout(resolve, 0))
		expect(later.disabled).toBe(true)
	})
})
