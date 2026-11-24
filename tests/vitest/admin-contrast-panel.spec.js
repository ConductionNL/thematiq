/**
 * @vitest-environment jsdom
 *
 * The Contrast report panel (js/admin-contrast-panel.js, the TqContrastrapport
 * board): totals, pairs with example, ratio, standard and verdict, the about
 * card, the header line and its downloads.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const REPORT = {
	metadata: { tokenSet: { name: 'Gemeente Zuiddrecht' }, designSystem: 'nldesign', generatedAt: '2026-10-07T12:12:00Z' },
	pairs: [
		{ foreground: '--color-primary-text', background: '--color-primary', foregroundValue: '#ffffff', backgroundValue: '#3669a5', ratio: 5.64, threshold: 4.5, basis: 'normal-text', verdict: 'pass' },
		{ foreground: '--color-border-maxcontrast', background: '--color-main-background', foregroundValue: '#cccccc', backgroundValue: '#ffffff', ratio: 1.6, threshold: 3, basis: 'ui-component', verdict: 'fail' },
	],
	summary: { passed: 1, failed: 1, unevaluated: 0, verdict: 'fail' },
}

const PAGE = `
	<section id="thematiq-page-header">
		<h1 class="thematiq-page-header__title">Themes and appearance</h1>
		<div class="thematiq-page-header__actions"></div>
		<nav class="thematiq-page-header__crumbs"></nav>
		<p class="thematiq-page-header__intro"></p>
	</section>
	<div id="nldesign-settings">
		<section class="thematiq-panel" data-thematiq-panel="contrast" hidden>
			<div class="thematiq-contrast-panel__tiles"></div>
			<table><tbody class="thematiq-contrast-panel__rows"></tbody></table>
			<a id="nldesign-compliance-report-json" hidden>JSON</a>
			<a id="nldesign-compliance-report-markdown" hidden>Markdown</a>
			<div class="thematiq-contrast-panel__about"></div>
		</section>
	</div>
`

const flush = () => new Promise((resolve) => setTimeout(resolve, 0))

describe('Contrast report panel', () => {
	let added = []

	beforeEach(async () => {
		window.t = (app, text, vars) => String(text).replace(/\{(\w+)\}/g, (all, key) => (vars && key in vars ? String(vars[key]) : all))
		window.OC = { generateUrl: (url) => url, requestToken: 'token' }
		window.scrollTo = vi.fn()
		document.documentElement.lang = 'en-GB'
		global.fetch = vi.fn(() => Promise.resolve({ json: () => Promise.resolve(REPORT) }))
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
		await import('../../js/admin-contrast-panel.js?t=' + Math.random())
		window.history.replaceState(null, '', '#contrast')
		window.dispatchEvent(new HashChangeEvent('hashchange'))
		await flush()
		await flush()
	})

	afterEach(() => {
		vi.restoreAllMocks()
		added.forEach(([target, type, listener]) => target.removeEventListener(type, listener))
		added = []
		document.body.innerHTML = ''
		delete window.ThematiqPanels
		delete document.documentElement.dataset.thematiqOpenPanel
	})

	it('reads the report as JSON with the request token', () => {
		expect(global.fetch).toHaveBeenCalledWith('/apps/thematiq/settings/compliance-report?format=json', { headers: { requesttoken: 'token' } })
	})

	it('draws the totals and the header line', () => {
		const numbers = [...document.querySelectorAll('.thematiq-contrast-panel__number')].map((n) => n.textContent)
		expect(numbers).toEqual(['2', '1', '1', '0'])
		expect(document.querySelector('.thematiq-page-header__title').textContent).toBe('Contrast report')
		expect(document.querySelector('.thematiq-page-header__intro').textContent).toBe('1 of 2 pairs pass, token set Gemeente Zuiddrecht')
	})

	it('draws each pair with its example, ratio, standard and verdict', () => {
		const rows = [...document.querySelectorAll('.thematiq-contrast-panel__rows tr')]
		expect(rows).toHaveLength(2)
		expect(rows[0].textContent).toContain('--color-primary-text')
		expect(rows[0].textContent).toContain('5.64:1')
		expect(rows[0].textContent).toContain('4.5:1 text')
		expect(rows[0].querySelector('.thematiq-contrast-panel__pill--pass').textContent).toBe('Meets')
		expect(rows[1].textContent).toContain('3.0:1 part')
		expect(rows[1].querySelector('.thematiq-contrast-panel__pill--fail').textContent).toBe('Too low')
		expect(rows[0].querySelector('.thematiq-contrast-panel__example').style.background).toContain('rgb(54, 105, 165)')
	})

	it('says what the report is about, and downloads through the report links', () => {
		expect(document.querySelector('.thematiq-contrast-panel__about').textContent).toContain('NL Design System')
		const json = document.getElementById('nldesign-compliance-report-json')
		const pressed = vi.fn()
		json.addEventListener('click', pressed)
		document.querySelectorAll('.thematiq-page-header__actions button')[1].click()
		expect(pressed).toHaveBeenCalledTimes(1)
	})
})
