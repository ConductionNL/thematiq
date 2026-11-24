/**
 * @vitest-environment jsdom
 *
 * The environment marker panel (js/admin-environment-panel.js, the
 * TqOmgevingsmarkering board): the header line, the marker the declared
 * environment gets, and the Documentation link kept from the overview.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const page = (environment) => `
	<section id="thematiq-page-header">
		<h1 class="thematiq-page-header__title">Themes and appearance</h1>
		<div class="thematiq-page-header__actions"><a id="nldesign-doc-link" class="thematiq-page-header__action button" href="https://docs.example/thematiq">Documentation</a></div>
		<nav class="thematiq-page-header__crumbs"></nav>
		<p class="thematiq-page-header__intro"></p>
	</section>
	<div id="nldesign-settings">
		<section class="thematiq-panel" data-thematiq-panel="environment" data-environment="${environment}" hidden>
			<span class="thematiq-environment-panel__marker"></span>
		</section>
	</div>
`

describe('Environment marker panel', () => {
	let added = []

	async function open(environment) {
		document.body.innerHTML = page(environment)
		window.history.replaceState(null, '', window.location.pathname)
		vi.resetModules()
		await import('../../js/admin-panels.js?t=' + Math.random())
		await import('../../js/admin-environment-panel.js?t=' + Math.random())
		window.history.replaceState(null, '', '#environment')
		window.dispatchEvent(new HashChangeEvent('hashchange'))
	}

	beforeEach(() => {
		window.t = (app, text, vars) => Object.keys(vars || {}).reduce((acc, key) => acc.replace('{' + key + '}', vars[key]), text)
		window.scrollTo = vi.fn()
		for (const target of [window, document]) {
			const original = target.addEventListener.bind(target)
			vi.spyOn(target, 'addEventListener').mockImplementation((type, listener, options) => {
				added.push([target, type, listener])
				original(type, listener, options)
			})
		}
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

	it('names the declared environment and its marker, and keeps the Documentation link', async () => {
		await open('test')
		expect(text('.thematiq-page-header__title')).toBe('Environment marker')
		expect(text('.thematiq-page-header__intro')).toBe('Environment: test, set in config.php')
		expect(text('.thematiq-environment-panel__marker')).toBe('Every page of this server carries the marker Test environment.')
		const link = document.querySelector('.thematiq-page-header__actions a')
		expect(link.getAttribute('href')).toBe('https://docs.example/thematiq')
		expect(link.id).toBe('')
		window.ThematiqPanels.close()
		expect(document.getElementById('nldesign-doc-link')).not.toBeNull()
	})

	it('marks an unknown value as unknown, and production as no marker', async () => {
		await open('tset')
		expect(text('.thematiq-environment-panel__marker')).toBe('Every page of this server carries the marker Unknown environment.')
		window.ThematiqPanels.close()
		await open('production')
		expect(text('.thematiq-environment-panel__marker')).toBe('No page of this server carries a marker.')
	})

	it('says when no environment is set', async () => {
		await open('')
		expect(text('.thematiq-page-header__intro')).toBe('No environment set')
	})
})
