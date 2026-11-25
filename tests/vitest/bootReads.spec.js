/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/lib/bootReads.js: the Theming admin page answers its
 * blocks' first GETs from the render's `bootReads` initial state, and only
 * until the admin first acts on the page or anything writes.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const BASE = 'http://localhost/index.php/settings/admin/theming'
const READS = {
	'/settings/fonts': { fonts: [{ name: 'Barlow' }] },
	'/settings/overrides?tokenSet=nextcloud': { overrides: {} },
}

let realFetch

async function loadShim(reads) {
	vi.resetModules()
	delete window.ThematiqBootReads
	realFetch = vi.fn(() => Promise.resolve(new Response('{"from":"server"}', { status: 200 })))
	window.fetch = realFetch
	window.OCP = {
		InitialState: {
			loadState: vi.fn((app, key, fallback) => (app === 'thematiq' && key === 'bootReads' && reads !== undefined ? reads : fallback)),
		},
	}
	await import('../../js/lib/bootReads.js?t=' + Math.random())
}

describe('ThematiqBootReads.keyOf', () => {
	beforeEach(async () => {
		await loadShim(undefined)
	})

	it('keys a thematiq URL by its path after /apps/thematiq, with the query', () => {
		const keyOf = window.ThematiqBootReads.keyOf
		expect(keyOf('/index.php/apps/thematiq/settings/fonts', BASE)).toBe('/settings/fonts')
		expect(keyOf('/apps/thematiq/settings/overrides?tokenSet=nextcloud', BASE)).toBe('/settings/overrides?tokenSet=nextcloud')
		expect(keyOf('http://localhost/index.php/apps/thematiq/settings/fonts', BASE)).toBe('/settings/fonts')
	})

	it('keys nothing for another app, another origin or a request object', () => {
		const keyOf = window.ThematiqBootReads.keyOf
		expect(keyOf('/index.php/apps/files/settings/fonts', BASE)).toBeNull()
		expect(keyOf('/index.php/apps/thematiqx/settings/fonts', BASE)).toBeNull()
		expect(keyOf('/other/apps/thematiq/settings/fonts', BASE)).toBeNull()
		expect(keyOf('https://example.org/index.php/apps/thematiq/settings/fonts', BASE)).toBeNull()
		expect(keyOf({ url: '/index.php/apps/thematiq/settings/fonts' }, BASE)).toBeNull()
	})
})

describe('ThematiqBootReads.create', () => {
	beforeEach(async () => {
		await loadShim(undefined)
	})

	it('answers a GET for a path the render read, as often as it is asked, without the server', async () => {
		const server = vi.fn()
		const boot = window.ThematiqBootReads.create(server, READS, BASE)
		const first = await boot.fetch('/index.php/apps/thematiq/settings/fonts', { headers: { requesttoken: 'x' } })
		expect(first.ok).toBe(true)
		expect(await first.json()).toEqual({ fonts: [{ name: 'Barlow' }] })
		const again = await boot.fetch('/index.php/apps/thematiq/settings/fonts', { method: 'get' })
		expect(await again.json()).toEqual({ fonts: [{ name: 'Barlow' }] })
		expect(server).not.toHaveBeenCalled()
	})

	it('sends a path the render did not read to the server', () => {
		const server = vi.fn(() => 'server')
		const boot = window.ThematiqBootReads.create(server, READS, BASE)
		expect(boot.fetch('/index.php/apps/thematiq/settings/overrides?tokenSet=amsterdam')).toBe('server')
		expect(server).toHaveBeenCalledTimes(1)
	})

	it('stops answering at the first write, which itself goes to the server', () => {
		const server = vi.fn(() => 'server')
		const boot = window.ThematiqBootReads.create(server, READS, BASE)
		expect(boot.fetch('/index.php/apps/thematiq/settings/fonts', { method: 'POST', body: '{}' })).toBe('server')
		expect(boot.active()).toBe(false)
		expect(boot.fetch('/index.php/apps/thematiq/settings/fonts')).toBe('server')
		expect(server).toHaveBeenCalledTimes(2)
	})

	it('treats a GET with a body as a write', () => {
		const boot = window.ThematiqBootReads.create(vi.fn(), READS, BASE)
		boot.fetch('/index.php/apps/thematiq/settings/fonts', { body: 'x' })
		expect(boot.active()).toBe(false)
	})

	it('answers nothing when the render read nothing', () => {
		expect(window.ThematiqBootReads.create(vi.fn(), {}, BASE).active()).toBe(false)
		expect(window.ThematiqBootReads.create(vi.fn(), [], BASE).active()).toBe(false)
		expect(window.ThematiqBootReads.create(vi.fn(), null, BASE).active()).toBe(false)
	})
})

describe('the page shim', () => {
	afterEach(() => {
		delete window.OCP
	})

	it('leaves window.fetch alone when the page carries no boot reads', async () => {
		await loadShim(undefined)
		expect(window.fetch).toBe(realFetch)
	})

	it('answers a boot read from the initial state and gives fetch back at the first key press', async () => {
		await loadShim(READS)
		expect(window.fetch).not.toBe(realFetch)
		const answer = await window.fetch('/index.php/apps/thematiq/settings/fonts')
		expect(await answer.json()).toEqual({ fonts: [{ name: 'Barlow' }] })
		expect(realFetch).not.toHaveBeenCalled()

		document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab' }))
		expect(window.fetch).toBe(realFetch)
	})

	it('gives fetch back after a pointer press', async () => {
		await loadShim(READS)
		document.dispatchEvent(new Event('pointerdown'))
		expect(window.fetch).toBe(realFetch)
	})

	it('gives fetch back after a write, so a later read reaches the server', async () => {
		await loadShim(READS)
		await window.fetch('/index.php/apps/thematiq/settings/fonts', { method: 'PUT', body: '{}' })
		expect(window.fetch).toBe(realFetch)
		await window.fetch('/index.php/apps/thematiq/settings/fonts')
		expect(realFetch).toHaveBeenCalledTimes(2)
	})
})
