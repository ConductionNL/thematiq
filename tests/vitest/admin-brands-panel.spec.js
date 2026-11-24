/**
 * @vitest-environment jsdom
 *
 * The brand per app panel (js/admin-brands-panel.js, the TqMerkPerApp board):
 * the header line from the brands and the theme per app list, and the
 * header's Add brand.
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
		<section class="thematiq-panel" data-thematiq-panel="brands" hidden>
			<select id="nldesign-app-brands-app"><option>a</option></select>
			<div id="nldesign-app-theming-list"></div>
		</section>
	</div>
`

const flush = () => new Promise((resolve) => setTimeout(resolve, 0))

describe('Brand per app panel', () => {
	let added = []

	beforeEach(async () => {
		window.t = (app, text, vars) => Object.keys(vars || {}).reduce((acc, key) => acc.replace('{' + key + '}', vars[key]), text)
		window.scrollTo = vi.fn()
		document.body.innerHTML = PAGE
		window.history.replaceState(null, '', window.location.pathname)
		for (const target of [window, document]) {
			const original = target.addEventListener.bind(target)
			vi.spyOn(target, 'addEventListener').mockImplementation((type, listener, options) => {
				added.push([target, type, listener])
				original(type, listener, options)
			})
		}
		vi.resetModules()
		await import('../../js/admin-panels.js?t=' + Math.random())
		await import('../../js/admin-brands-panel.js?t=' + Math.random())
		window.history.replaceState(null, '', '#brands')
		window.dispatchEvent(new HashChangeEvent('hashchange'))
	})

	afterEach(() => {
		vi.restoreAllMocks()
		added.forEach(([target, type, listener]) => target.removeEventListener(type, listener))
		added = []
		document.body.innerHTML = ''
		delete window.ThematiqPanels
		delete document.documentElement.dataset.thematiqOpenPanel
	})

	const intro = () => document.querySelector('.thematiq-page-header__intro').textContent

	it('counts the brands, then the apps that follow the house style once the list is drawn', async () => {
		expect(document.querySelector('.thematiq-page-header__title').textContent).toBe('Brand per app')
		expect(intro()).toBe('0 apps with their own brand')
		document.dispatchEvent(new CustomEvent('thematiq:app-brands', { detail: { brands: { learniq: {} } } }))
		expect(intro()).toBe('1 app with its own brand')
		document.getElementById('nldesign-app-theming-list').innerHTML = '<input type="checkbox" data-app-id="a" checked><input type="checkbox" data-app-id="b">'
		await flush()
		expect(intro()).toBe('1 app with its own brand, 1 of 2 apps follow the house style')
		const box = document.querySelector('[data-app-id="b"]')
		box.checked = true
		box.dispatchEvent(new Event('change', { bubbles: true }))
		expect(intro()).toBe('1 app with its own brand, 2 of 2 apps follow the house style')
	})

	it('moves focus to the add form from the header button', () => {
		const button = document.querySelector('.thematiq-page-header__actions button')
		expect(button.textContent).toBe('Add brand')
		button.click()
		expect(document.activeElement.id).toBe('nldesign-app-brands-app')
	})
})
