/**
 * @vitest-environment jsdom
 *
 * The email and documents panel (js/admin-email-panel.js, the
 * TqEmailDocumenten board): the header line and the examples filled from the
 * fields and the document profile.
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
		<section class="thematiq-panel" data-thematiq-panel="email" hidden>
			<input type="checkbox" id="nldesign-email-theming-enabled" checked>
			<input id="nldesign-email-footer-org-name" value="Gemeente Zuiddrecht">
			<input id="nldesign-documents-footer-line" value="">
			<div id="nldesign-documents-preview"></div>
			<span class="thematiq-email-panel__org"></span>
			<div class="thematiq-email-panel__letter-logo"></div>
			<span class="thematiq-email-panel__letter-foot"></span>
		</section>
	</div>
`

const flush = () => new Promise((resolve) => setTimeout(resolve, 0))

describe('Email and documents panel', () => {
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
		await import('../../js/admin-email-panel.js?t=' + Math.random())
		window.history.replaceState(null, '', '#email')
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

	const text = (selector) => document.querySelector(selector).textContent

	it('says whether the template is on and a logo is set', async () => {
		expect(text('.thematiq-page-header__title')).toBe('Email and documents')
		expect(text('.thematiq-page-header__intro')).toBe('Email template on, no document logo')
		document.getElementById('nldesign-documents-preview').innerHTML = '<img src="logo.svg" alt="Document logo">'
		await flush()
		expect(text('.thematiq-page-header__intro')).toBe('Email template on, document logo set')
		expect(document.querySelector('.thematiq-email-panel__letter-logo img').getAttribute('src')).toBe('logo.svg')
		const toggle = document.getElementById('nldesign-email-theming-enabled')
		toggle.checked = false
		toggle.dispatchEvent(new Event('change'))
		expect(text('.thematiq-page-header__intro')).toBe('Email template off, document logo set')
	})

	it('fills the examples from the organisation and the footer line', () => {
		expect(text('.thematiq-email-panel__org')).toBe('Gemeente Zuiddrecht')
		expect(text('.thematiq-email-panel__letter-foot')).toBe('Gemeente Zuiddrecht')
		const line = document.getElementById('nldesign-documents-footer-line')
		line.value = 'Postbus 100, 2990 AA Zuiddrecht'
		line.dispatchEvent(new Event('input'))
		expect(text('.thematiq-email-panel__letter-foot')).toBe('Postbus 100, 2990 AA Zuiddrecht')
	})
})
