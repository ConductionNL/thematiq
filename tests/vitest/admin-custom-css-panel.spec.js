/**
 * @vitest-environment jsdom
 *
 * The Custom CSS panel (js/admin-custom-css-panel.js, the TqEigenCss board):
 * line numbers that follow the CSS, the header line, and the save's answer
 * as a passed or refused pill.
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
		<a href="#css">Custom CSS</a>
		<section class="thematiq-panel" data-thematiq-panel="css" hidden>
			<input type="checkbox" id="nldesign-custom-css-enabled" checked>
			<div class="thematiq-css-panel__gutter"></div>
			<textarea id="nldesign-custom-css-input" rows="3"></textarea>
			<span id="nldesign-custom-css-feedback"></span>
		</section>
	</div>
`

const flush = () => new Promise((resolve) => setTimeout(resolve, 0))

describe('Custom CSS panel', () => {
	let added = []

	beforeEach(async () => {
		window.t = (app, text, vars) =>
			String(text).replace(/\{(\w+)\}/g, (all, key) =>
				vars && key in vars ? String(vars[key]) : all,
			)
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
		await import('../../js/admin-custom-css-panel.js?t=' + Math.random())
		document.getElementById('nldesign-custom-css-input').value =
			'a {}\nb {}\nc {}\nd {}'
		window.history.replaceState(null, '', '#css')
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

	it('opens with the title, says it is on and how long, and numbers the lines', () => {
		expect(
			document.querySelector('.thematiq-page-header__title').textContent,
		).toBe('Custom CSS')
		expect(
			document.querySelector('.thematiq-page-header__intro').textContent,
		).toBe('On, 4 lines')
		expect(
			[...document.querySelectorAll('.thematiq-css-panel__gutter span')].map(
				(n) => n.textContent,
			),
		).toEqual(['1', '2', '3', '4'])
	})

	it('follows typing and the switch', () => {
		const input = document.getElementById('nldesign-custom-css-input')
		input.value = 'a {}'
		input.dispatchEvent(new Event('input'))
		const toggle = document.getElementById('nldesign-custom-css-enabled')
		toggle.checked = false
		toggle.dispatchEvent(new Event('change'))
		expect(
			document.querySelector('.thematiq-page-header__intro').textContent,
		).toBe('Off, 1 line')
		expect(
			document.querySelectorAll('.thematiq-css-panel__gutter span'),
		).toHaveLength(3)
	})

	it('marks a refused save', async () => {
		const feedback = document.getElementById('nldesign-custom-css-feedback')
		feedback.textContent = 'Refused: @import'
		feedback.style.color = 'red'
		await flush()
		expect(
			feedback.classList.contains('thematiq-css-panel__feedback--error'),
		).toBe(true)
		feedback.style.color = ''
		feedback.textContent = 'Saved.'
		await flush()
		expect(
			feedback.classList.contains('thematiq-css-panel__feedback--error'),
		).toBe(false)
	})
})
