/**
 * @vitest-environment jsdom
 *
 * The Fonts panel (js/admin-fonts-panel.js, the TqLettertypen board): the
 * header line from the uploaded fonts, and the Upload font button.
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
		<section class="thematiq-panel" data-thematiq-panel="fonts" hidden>
			<input type="text" id="nldesign-font-name">
			<div class="thematiq-fonts-panel__now"></div>
			<div id="nldesign-font-library"><div class="thematiq-font-row thematiq-font-row--body"><span class="thematiq-font-row__title">Fira Sans</span></div></div>
			<div id="nldesign-font-list"><div class="thematiq-font-row thematiq-font-row--heading"><span class="thematiq-font-row__title">Zuiddrecht Sans</span></div><div class="thematiq-font-row"><span class="thematiq-font-row__title">Zuiddrecht Text</span></div></div>
		</section>
	</div>
`

const flush = () => new Promise((resolve) => setTimeout(resolve, 0))

describe('Fonts panel', () => {
	let added = []

	beforeEach(async () => {
		window.t = (app, text, vars) => String(text).replace(/\{(\w+)\}/g, (all, key) => (vars && key in vars ? String(vars[key]) : all))
		window.scrollTo = vi.fn()
		Element.prototype.scrollIntoView = vi.fn()
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
		await import('../../js/admin-fonts-panel.js?t=' + Math.random())
		window.history.replaceState(null, '', '#fonts')
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

	it('titles the header and counts the uploaded fonts, also after a change', async () => {
		expect(document.querySelector('.thematiq-page-header__title').textContent).toBe('Typefaces')
		expect(document.querySelector('.thematiq-page-header__intro').textContent).toBe('2 uploaded fonts')
		document.querySelector('#nldesign-font-list .thematiq-font-row').remove()
		await flush()
		expect(document.querySelector('.thematiq-page-header__intro').textContent).toBe('1 uploaded font')
	})

	it('says which font the headings and the body text use now', () => {
		expect(document.querySelector('.thematiq-fonts-panel__now').textContent).toBe('HeadingsZuiddrecht SansBody textFira Sans')
	})

	it('takes the admin to the upload form', () => {
		document.querySelector('.thematiq-page-header__actions button').click()
		expect(document.activeElement).toBe(document.getElementById('nldesign-font-name'))
	})
})
