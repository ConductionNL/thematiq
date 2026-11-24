/**
 * @vitest-environment jsdom
 *
 * The Log panel (js/admin-log-panel.js, the TqLogboek board): the counts
 * from the rendered table, the header line, and the header's download button.
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
		<section class="thematiq-panel" data-thematiq-panel="log" hidden>
			<span class="thematiq-log-panel__count"></span>
			<strong class="thematiq-log-panel__versions"></strong>
			<button type="button" id="nldesign-audit-download-btn" hidden>Download</button>
		</section>
	</div>
`

describe('Log panel', () => {
	let added = []

	beforeEach(async () => {
		window.t = (app, text, vars) => String(text).replace(/\{(\w+)\}/g, (all, key) => (vars && key in vars ? String(vars[key]) : all))
		window.scrollTo = vi.fn()
		document.documentElement.lang = 'en-GB'
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
		await import('../../js/admin-log-panel.js?t=' + Math.random())
		window.history.replaceState(null, '', '#log')
		window.dispatchEvent(new HashChangeEvent('hashchange'))
		document.dispatchEvent(new CustomEvent('thematiq:audit-rendered', { detail: { count: 7, versions: 6, since: '2026-10-02T15:50:00Z' } }))
	})

	afterEach(() => {
		vi.restoreAllMocks()
		added.forEach(([target, type, listener]) => target.removeEventListener(type, listener))
		added = []
		document.body.innerHTML = ''
		delete window.ThematiqPanels
		delete document.documentElement.dataset.thematiqOpenPanel
	})

	it('writes the counts and the header line', () => {
		expect(document.querySelector('.thematiq-page-header__title').textContent).toBe('Log')
		expect(document.querySelector('.thematiq-log-panel__count').textContent).toBe('7 of 7 changes')
		expect(document.querySelector('.thematiq-log-panel__versions').textContent).toBe('6 of 50')
		expect(document.querySelector('.thematiq-page-header__intro').textContent).toMatch(/^7 changes since .*2026$/)
	})

	it("downloads through the table's own button", () => {
		const download = document.getElementById('nldesign-audit-download-btn')
		const pressed = vi.fn()
		download.addEventListener('click', pressed)
		document.querySelector('.thematiq-page-header__actions button').click()
		expect(pressed).toHaveBeenCalledTimes(1)
	})
})
