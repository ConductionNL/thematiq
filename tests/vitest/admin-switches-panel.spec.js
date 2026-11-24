/**
 * @vitest-environment jsdom
 *
 * The scheduled switches panel (js/admin-switches-panel.js, the TqWissels
 * board): the header line and the table's line from what admin.js loaded,
 * the background-job mode as a pill and the header's Plan a switch button.
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
		<section class="thematiq-panel" data-thematiq-panel="switches" hidden>
			<select id="nldesign-scheduled-set"><option value="a">A</option></select>
			<span class="thematiq-switches-panel__summary"></span>
			<span class="thematiq-switches-panel__mode"></span>
		</section>
	</div>
`

const LOADED = {
	switches: [
		{ id: 'r', tokenSet: 'a', startAt: '2027-01-01T08:00:00Z', status: 'running' },
		{ id: 'p2', tokenSet: 'b', startAt: '2027-04-26T16:00:00Z', status: 'planned' },
		{ id: 'p1', tokenSet: 'c', startAt: '2027-03-02T06:00:00Z', status: 'planned' },
		{ id: 'f', tokenSet: 'd', startAt: '2026-10-01T07:00:00Z', status: 'failed' },
	],
	status: { cronMode: 'cron' },
}

function send(detail) {
	document.dispatchEvent(new CustomEvent('thematiq:scheduled-switches', { detail }))
}

describe('Scheduled switches panel', () => {
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
		await import('../../js/admin-switches-panel.js?t=' + Math.random())
		window.history.replaceState(null, '', '#switches')
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

	it('says nothing is scheduled before the list loads', () => {
		expect(document.querySelector('.thematiq-page-header__title').textContent).toBe('Scheduled switches')
		expect(document.querySelector('.thematiq-page-header__intro').textContent).toBe('Nothing scheduled')
	})

	it('counts the scheduled switches and names the first start', () => {
		send(LOADED)
		const intro = document.querySelector('.thematiq-page-header__intro').textContent
		expect(intro.startsWith('2 scheduled, next on ')).toBe(true)
		expect(intro).toContain(new Date('2027-03-02T06:00:00Z').toLocaleDateString([], { day: 'numeric', month: 'long', year: 'numeric' }))
		expect(document.querySelector('.thematiq-switches-panel__summary').textContent).toBe(
			'2 scheduled, 1 failed. A running switch sits at the top; a failed one shows the reason.',
		)
	})

	it('shows the background-job mode as a pill, marked in AJAX mode', () => {
		send(LOADED)
		const mode = document.querySelector('.thematiq-switches-panel__mode')
		expect(mode.textContent).toBe('Cron')
		expect(mode.classList.contains('thematiq-contrast-panel__pill--pass')).toBe(true)
		send({ switches: [], status: { cronMode: 'ajax' } })
		expect(mode.textContent).toBe('AJAX')
		expect(mode.classList.contains('thematiq-contrast-panel__pill--fail')).toBe(true)
		send({ switches: [], status: {} })
		expect(mode.hidden).toBe(true)
	})

	it('moves focus to the form from the header button', () => {
		const button = document.querySelector('.thematiq-page-header__actions button')
		expect(button.textContent).toBe('Plan a switch')
		button.click()
		expect(document.activeElement.id).toBe('nldesign-scheduled-set')
	})
})
