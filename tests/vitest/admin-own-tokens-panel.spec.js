/**
 * @vitest-environment jsdom
 *
 * The own tokens panel (js/admin-own-tokens-panel.js, the TqEigenTokens
 * board): the counts from what js/ownTokens.js drew and the header's two
 * buttons, which press the panel's own.
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
		<section class="thematiq-panel" data-thematiq-panel="own-tokens" hidden>
			<span class="thematiq-own-tokens-panel__count"></span>
			<span class="thematiq-own-tokens-panel__deprecated"></span>
			<button type="button" id="nldesign-own-token-add"></button>
			<button type="button" id="nldesign-deprecation-add"></button>
		</section>
	</div>
`

describe('Own tokens panel', () => {
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
		await import('../../js/admin-own-tokens-panel.js?t=' + Math.random())
		window.history.replaceState(null, '', '#own-tokens')
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

	it('counts the own and the deprecated tokens', () => {
		expect(text('.thematiq-page-header__title')).toBe('Own tokens')
		document.dispatchEvent(
			new CustomEvent('thematiq:own-tokens', {
				detail: { tokens: [{}, {}, {}], deprecations: [{}, {}] },
			}),
		)
		expect(text('.thematiq-page-header__intro')).toBe(
			'3 own tokens, 2 deprecated',
		)
		expect(text('.thematiq-own-tokens-panel__count')).toBe('3 own tokens')
		expect(text('.thematiq-own-tokens-panel__deprecated')).toBe('2 deprecated')
		document.dispatchEvent(
			new CustomEvent('thematiq:own-tokens', {
				detail: { tokens: [{}], deprecations: [] },
			}),
		)
		expect(text('.thematiq-own-tokens-panel__count')).toBe('1 own token')
	})

	it('presses the panel buttons from the header', () => {
		const add = vi.fn()
		const deprecate = vi.fn()
		document
			.getElementById('nldesign-own-token-add')
			.addEventListener('click', add)
		document
			.getElementById('nldesign-deprecation-add')
			.addEventListener('click', deprecate)
		const buttons = document.querySelectorAll(
			'.thematiq-page-header__actions button',
		)
		expect(Array.from(buttons).map((b) => b.textContent)).toEqual([
			'Deprecate token',
			'Add token',
		])
		buttons[0].click()
		buttons[1].click()
		expect(deprecate).toHaveBeenCalled()
		expect(add).toHaveBeenCalled()
	})
})
