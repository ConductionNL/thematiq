/**
 * @vitest-environment jsdom
 *
 * js/search-placeholder.js: the app's words replace Nextcloud's in the
 * collapsed search label, the open field's placeholder and the search
 * dialog's field label, and stay there when the search re-renders.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/workplace-layout/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const WORDS = 'Search cases, documents or contacts'

const SEARCH =
	'<header id="header">'
	+ '<div class="unified-search-input"><span class="unified-search-input__label">Search</span>'
	+ '<input placeholder="Search apps, files, tags, messages"><input class="no-placeholder"></div>'
	+ '</header>'

/**
 * Give the page the `thematiq/search-placeholder` state.
 *
 * @param {*} value The state, or a function that throws.
 */
function installState(value) {
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) => {
				if (typeof value === 'function') {
					return value()
				}
				return app === 'thematiq' && key === 'search-placeholder'
					? value
					: fallback
			},
		},
	}
}

/**
 * Run the script on the current page.
 *
 * @return {Promise<void>}
 */
async function loadScript() {
	vi.resetModules()
	await import('../../js/search-placeholder.js?t=' + Math.random())
}

const flush = () => new Promise((resolve) => setTimeout(resolve, 0))
const label = () =>
	document.querySelector('.unified-search-input__label').textContent
const placeholder = () =>
	document.querySelector('.unified-search-input input').getAttribute('placeholder')

describe('the search placeholder', () => {
	beforeEach(() => {
		// A fresh body per test: an earlier test's observer watches the old one.
		document.documentElement.replaceChild(
			document.createElement('body'),
			document.body,
		)
		document.body.innerHTML = SEARCH
	})

	afterEach(() => {
		delete global.OCP
		vi.restoreAllMocks()
	})

	it('puts the trimmed words in the label and the placeholder, and adds no placeholder where there is none', async () => {
		installState('  ' + WORDS + '  ')
		await loadScript()
		expect(label()).toBe(WORDS)
		expect(placeholder()).toBe(WORDS)
		expect(
			document
				.querySelector('input.no-placeholder')
				.hasAttribute('placeholder'),
		).toBe(false)
	})

	it('names the search dialog field, but not another field in the dialog', async () => {
		installState(WORDS)
		await loadScript()
		const modal = document.createElement('div')
		modal.className = 'unified-search-modal'
		modal.innerHTML =
			'<div class="input-field search"><label class="input-field__label">Search apps</label>'
			+ '<input data-cy-unified-search-input placeholder="Search apps"></div>'
			+ '<div class="input-field filter"><label class="input-field__label">Filter</label><input></div>'
			+ '<label class="input-field__label">Loose</label>'
		document.body.appendChild(modal)
		await flush()

		expect(modal.querySelector('.search .input-field__label').textContent).toBe(
			WORDS,
		)
		expect(
			modal
				.querySelector('input[data-cy-unified-search-input]')
				.getAttribute('placeholder'),
		).toBe(WORDS)
		expect(modal.querySelector('.filter .input-field__label').textContent).toBe(
			'Filter',
		)
		expect(modal.querySelector(':scope > .input-field__label').textContent).toBe(
			'Loose',
		)
	})

	it('puts the words back when the search re-renders its own', async () => {
		installState(WORDS)
		await loadScript()
		document.querySelector('.unified-search-input__label').textContent = 'Search'
		document
			.querySelector('.unified-search-input input')
			.setAttribute('placeholder', 'Search apps')
		await flush()
		expect(label()).toBe(WORDS)
		expect(placeholder()).toBe(WORDS)
	})

	it('does not rewrite words that are already right, so its own change does not loop', async () => {
		installState(WORDS)
		await loadScript()
		const input = document.querySelector('.unified-search-input input')
		const setAttribute = vi.spyOn(input, 'setAttribute')
		document.body.appendChild(document.createElement('span'))
		await flush()
		expect(setAttribute).not.toHaveBeenCalled()
	})

	it.each([
		['no words', ''],
		['blank words', '   '],
		['a state that is not text', 42],
	])('changes nothing with %s', async (name, value) => {
		installState(value)
		await loadScript()
		expect(label()).toBe('Search')
		expect(placeholder()).toBe('Search apps, files, tags, messages')
	})

	it('changes nothing when the state cannot be read or OCP is missing', async () => {
		installState(() => {
			throw new Error('no state')
		})
		await loadScript()
		expect(label()).toBe('Search')

		delete global.OCP
		await loadScript()
		expect(label()).toBe('Search')

		global.OCP = { InitialState: {} }
		await loadScript()
		expect(label()).toBe('Search')
	})

	it('waits for DOMContentLoaded while the page is loading', async () => {
		installState(WORDS)
		const readyState = vi
			.spyOn(document, 'readyState', 'get')
			.mockReturnValue('loading')
		await loadScript()
		expect(label()).toBe('Search')

		readyState.mockReturnValue('complete')
		document.dispatchEvent(new Event('DOMContentLoaded'))
		expect(label()).toBe(WORDS)
	})
})
