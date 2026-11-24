/**
 * @vitest-environment jsdom
 *
 * The environment marker panel (js/admin-environment-panel.js, the
 * TqOmgevingsmarkering board): the template's line in the header and a copy
 * of the overview's Documentation link, which stays where it was.
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
		<div class="thematiq-page-header__actions"><a id="nldesign-doc-link" class="thematiq-page-header__action button" href="https://docs.example/thematiq">Documentation</a></div>
		<nav class="thematiq-page-header__crumbs"></nav>
		<p class="thematiq-page-header__intro"></p>
	</section>
	<div id="nldesign-settings">
		<section class="thematiq-panel" data-thematiq-panel="environment" hidden>
			<p class="thematiq-environment-panel__line"> Environment: test, set in config.php </p>
		</section>
	</div>
`

describe('Environment marker panel', () => {
	let added = []

	beforeEach(async () => {
		window.t = (app, text) => text
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
		await import('../../js/admin-environment-panel.js?t=' + Math.random())
		window.history.replaceState(null, '', '#environment')
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

	it('puts the template line in the header', () => {
		expect(document.querySelector('.thematiq-page-header__title').textContent).toBe('Environment marker')
		expect(document.querySelector('.thematiq-page-header__intro').textContent).toBe('Environment: test, set in config.php')
	})

	it('shows a copy of the Documentation link and gives the original back', () => {
		const link = document.querySelector('.thematiq-page-header__actions a')
		expect(link.getAttribute('href')).toBe('https://docs.example/thematiq')
		expect(link.id).toBe('')
		window.ThematiqPanels.close()
		expect(document.getElementById('nldesign-doc-link').isConnected).toBe(true)
	})
})
