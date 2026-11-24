/**
 * @vitest-environment jsdom
 *
 * The AI assistant panel (js/admin-assistant-panel.js, the TqAiAssistent
 * board): the preview mirrored into the sample panel, the header line and
 * the save's answer as a pill.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const PAGE = `
	<section id="thematiq-page-header">
		<h1 class="thematiq-page-header__title">Themes and appearance</h1>
		<div class="thematiq-page-header__actions"></div>
		<nav class="thematiq-page-header__crumbs"></nav>
		<p class="thematiq-page-header__intro"></p>
	</section>
	<div id="nldesign-settings">
		<section class="thematiq-panel" data-thematiq-panel="assistant" hidden>
			<input type="checkbox" id="nldesign-assistant-mark-enabled" checked>
			<div id="nldesign-assistant-mark-preview"><figure>Approved by Zuiddrecht</figure></div>
			<span id="nldesign-assistant-mark-feedback"></span>
			<div class="thematiq-assistant-panel__sample-foot"></div>
		</section>
	</div>
`

const flush = () => new Promise((resolve) => setTimeout(resolve, 0))

describe('AI assistant panel', () => {
	let added = []

	beforeEach(async () => {
		window.t = (app, text) => text
		window.scrollTo = vi.fn()
		document.body.innerHTML = PAGE
		window.history.replaceState(null, '', window.location.pathname)
		for (const target of [window, document]) {
			const original = target.addEventListener.bind(target)
			vi.spyOn(target, 'addEventListener').mockImplementation(
				(type, listener, options) => {
					added.push([target, type, listener])
					original(type, listener, options)
				},
			)
		}
		vi.resetModules()
		await import('../../js/admin-panels.js?t=' + Math.random())
		await import('../../js/admin-assistant-panel.js?t=' + Math.random())
		window.history.replaceState(null, '', '#assistant')
		window.dispatchEvent(new HashChangeEvent('hashchange'))
	})

	afterEach(() => {
		vi.restoreAllMocks()
		added.forEach(([target, type, listener]) =>
			target.removeEventListener(type, listener),
		)
		added = []
		document.body.innerHTML = ''
		delete window.ThematiqPanels
		delete document.documentElement.dataset.thematiqOpenPanel
	})

	it('opens with the title and whether the mark is on, and follows the switch', () => {
		expect(
			document.querySelector('.thematiq-page-header__title').textContent,
		).toBe('AI assistant')
		expect(
			document.querySelector('.thematiq-page-header__intro').textContent,
		).toBe('On, users see the approved mark')
		const toggle = document.getElementById('nldesign-assistant-mark-enabled')
		toggle.checked = false
		toggle.dispatchEvent(new Event('change'))
		expect(
			document.querySelector('.thematiq-page-header__intro').textContent,
		).toBe('Off, users see no mark')
	})

	it('shows the preview in the footer of the sample panel, also after a change', async () => {
		expect(
			document.querySelector('.thematiq-assistant-panel__sample-foot')
				.textContent,
		).toBe('Approved by Zuiddrecht')
		document
			.getElementById('nldesign-assistant-mark-preview')
			.replaceChildren(document.createTextNode('Approved by Utrecht'))
		await flush()
		expect(
			document.querySelector('.thematiq-assistant-panel__sample-foot')
				.textContent,
		).toBe('Approved by Utrecht')
	})

	it('marks a refused save', async () => {
		const feedback = document.getElementById('nldesign-assistant-mark-feedback')
		feedback.textContent = 'Set a name first.'
		await flush()
		expect(
			feedback.classList.contains('thematiq-css-panel__feedback--error'),
		).toBe(true)
		feedback.textContent = 'AI assistant settings saved.'
		await flush()
		expect(
			feedback.classList.contains('thematiq-css-panel__feedback--error'),
		).toBe(false)
	})
})
