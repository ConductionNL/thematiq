/**
 * @vitest-environment jsdom
 *
 * The house style per group panel (js/admin-groups-panel.js, the TqGroepen
 * board): the counts from the rows admin.js draws, the default token set and
 * the header's Add connection.
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
		<select id="nldesign-token-set-select"><option value="a">Other</option><option value="z" selected> Zuiddrecht </option></select>
		<section class="thematiq-panel" data-thematiq-panel="groups" hidden>
			<span class="thematiq-groups-panel__delegated"></span>
			<strong class="thematiq-groups-panel__default"></strong>
			<strong class="thematiq-groups-panel__linked"></strong>
			<strong class="thematiq-groups-panel__delegated-count"></strong>
			<button type="button" id="nldesign-group-theming-add"></button>
			<input type="checkbox" data-field="delegated">
		</section>
	</div>
`

function send(rows) {
	document.dispatchEvent(
		new CustomEvent('thematiq:group-theming', { detail: { rows } }),
	)
}

describe('House style per group panel', () => {
	let added = []

	beforeEach(async () => {
		window.t = (app, text, vars) =>
			Object.keys(vars || {}).reduce(
				(acc, key) => acc.replace('{' + key + '}', vars[key]),
				text,
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
		await import('../../js/admin-groups-panel.js?t=' + Math.random())
		window.history.replaceState(null, '', '#groups')
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

	const text = (selector) => document.querySelector(selector).textContent

	it('opens with the title, the short crumb and the default token set', () => {
		expect(text('.thematiq-page-header__title')).toBe('House style per group')
		expect(text('[aria-current="page"]')).toBe('Groups')
		expect(text('.thematiq-groups-panel__default')).toBe('Zuiddrecht')
	})

	it('counts the linked and the delegated groups', () => {
		const rows = [
			{ delegated: true },
			{ delegated: false },
			{ delegated: true },
			{},
		]
		send(rows)
		expect(text('.thematiq-page-header__intro')).toBe(
			'4 groups linked, 2 with delegated choice',
		)
		expect(text('.thematiq-groups-panel__delegated')).toBe(
			'2 of 4 groups choose themselves',
		)
		expect(text('.thematiq-groups-panel__linked')).toBe('4')
		rows[1].delegated = true
		document
			.querySelector('[data-field="delegated"]')
			.dispatchEvent(new Event('change', { bubbles: true }))
		expect(text('.thematiq-groups-panel__delegated-count')).toBe('3')
	})

	it('adds a row from the header button', () => {
		const clicked = vi.fn()
		document
			.getElementById('nldesign-group-theming-add')
			.addEventListener('click', clicked)
		const button = document.querySelector(
			'.thematiq-page-header__actions button',
		)
		expect(button.textContent).toBe('Add connection')
		button.click()
		expect(clicked).toHaveBeenCalled()
	})
})
