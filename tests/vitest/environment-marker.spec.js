/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/environment-marker.js: the stripe is the first note in
 * the page, carries the label as text, and the tab title starts with the
 * short label, also after an app changes the title.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/governance-environment-marker/specs/environment-marker/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

function installState(state) {
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) =>
				app === 'thematiq' && key === 'environment' && state !== undefined
					? state
					: fallback,
		},
	}
}

async function loadScript() {
	vi.resetModules()
	await import('../../js/environment-marker.js?t=' + Math.random())
	await new Promise((resolve) => setTimeout(resolve, 0))
}

describe('environment marker', () => {
	beforeEach(() => {
		document.title = 'Files - Nextcloud'
		document.body.innerHTML = '<header id="header">header</header><main>content</main>'
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('puts the label first in the page as a note and prefixes the title', async () => {
		installState({ environment: 'test', style: 'test', label: 'Test environment', short: '[Test]' })
		await loadScript()

		const note = document.body.firstElementChild
		expect(note.getAttribute('role')).toBe('note')
		expect(note.textContent).toBe('Test environment')
		expect(note.classList.contains('thematiq-env-marker--test')).toBe(true)
		expect(document.querySelectorAll('[role="note"]')[0]).toBe(note)
		expect(document.title).toBe('[Test] Files - Nextcloud')
	})

	it('keeps the prefix when an app changes the title', async () => {
		installState({ environment: 'acceptance', style: 'acceptance', label: 'Acceptance environment', short: '[Acceptance]' })
		await loadScript()

		document.title = 'Calendar - Nextcloud'
		await new Promise((resolve) => setTimeout(resolve, 0))

		expect(document.title).toBe('[Acceptance] Calendar - Nextcloud')
	})

	it('renders nothing without state, as on production', async () => {
		installState(undefined)
		await loadScript()

		expect(document.querySelector('.thematiq-env-marker')).toBeNull()
		expect(document.title).toBe('Files - Nextcloud')
	})
})
