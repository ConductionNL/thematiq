/**
 * @vitest-environment jsdom
 *
 * The theme gallery panel (js/admin-gallery-panel.js, the TqGalerij board):
 * the header line from what admin.js loaded of the gallery and the upstream
 * check, and the breadcrumb's short name.
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
		<section class="thematiq-panel" data-thematiq-panel="gallery" hidden></section>
	</div>
`

function send(type, detail) {
	document.dispatchEvent(new CustomEvent(type, { detail }))
}

describe('Theme gallery panel', () => {
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
		await import('../../js/admin-gallery-panel.js?t=' + Math.random())
		window.history.replaceState(null, '', '#gallery')
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

	const intro = () =>
		document.querySelector('.thematiq-page-header__intro').textContent

	it('opens with the title, the short crumb, and the gallery off', () => {
		expect(
			document.querySelector('.thematiq-page-header__title').textContent,
		).toBe('Theme gallery and updates')
		expect(document.querySelector('[aria-current="page"]').textContent).toBe(
			'Theme gallery',
		)
		expect(intro()).toBe('Gallery off, 0 notices from the source')
	})

	it('counts the house styles and the notices as they load', () => {
		send('thematiq:gallery', { enabled: true, entries: [{}, {}, {}, {}] })
		expect(intro()).toBe('Gallery on, 4 house styles, 0 notices from the source')
		send('thematiq:upstream-freshness', { notices: [{}, {}] })
		expect(intro()).toBe('Gallery on, 4 house styles, 2 notices from the source')
	})

	it('leaves the header alone once the panel is closed', () => {
		window.ThematiqPanels.close()
		send('thematiq:gallery', { enabled: true, entries: [] })
		expect(intro()).toBe('')
	})
})
