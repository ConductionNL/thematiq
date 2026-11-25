/**
 * @vitest-environment jsdom
 *
 * The panels of the Theming page (js/admin-panels.js) and the Layout panel
 * (js/admin-layout-panel.js): a hash opens a panel in place of the overview,
 * the page header carries the panel, the options are lent and given back,
 * and the Layout panel says what the theme chooses and what changed.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/design.md
 */

import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const PAGE = `
	<div class="settings-content">
		<section id="thematiq-page-header">
			<div class="thematiq-page-header__row">
				<h1 class="thematiq-page-header__title">Themes and appearance</h1>
				<div class="thematiq-page-header__actions"><button id="preview">Preview in my session</button></div>
			</div>
			<nav class="thematiq-page-header__crumbs"><span>Administration settings</span><span>/</span><span aria-current="page">Themes and appearance</span></nav>
			<p class="thematiq-page-header__intro">Nextcloud sets the name.</p>
		</section>
		<div id="nldesign-settings">
			<select id="nldesign-token-set-select"><option value="zuiddrecht" selected>Gemeente Zuiddrecht</option></select>
			<section class="thematiq-layout-card">
				<a href="#layout" id="all-options">All options</a>
				<div class="nldesign-option" id="option-layout">
					<label for="thematiq-workplace-layout">Workplace layout</label>
					<select id="thematiq-workplace-layout">
						<option value="" selected>Follow the theme</option>
						<option value="default">Default</option>
						<option value="light">Light top bar</option>
					</select>
				</div>
				<div class="nldesign-option">
					<label for="thematiq-navigation-width-input">Navigation width in pixels</label>
					<input type="number" id="thematiq-navigation-width-input" value="">
				</div>
				<div class="nldesign-option">
					<input type="checkbox" id="nldesign-hide-slogan">
					<label for="nldesign-hide-slogan">Hide Nextcloud slogan/payoff on login page</label>
				</div>
			</section>
			<section class="thematiq-panel" data-thematiq-panel="layout" hidden>
				<form>
					<div data-thematiq-slot="thematiq-workplace-layout"></div>
					<div data-thematiq-slot="thematiq-navigation-width-input"></div>
					<div data-thematiq-slot="nldesign-hide-slogan"></div>
					<span class="thematiq-layout-panel__website"></span>
					<a class="thematiq-layout-panel__website-link"></a>
				</form>
				<aside class="thematiq-panel__aside">
					<span class="thematiq-panel__count"></span>
					<span class="thematiq-layout-panel__summary"></span>
					<div class="thematiq-layout-panel__changes"></div>
				</aside>
			</section>
		</div>
	</div>
`

let added = []

/** Load the scripts, keeping their listeners so the next test starts clean. */
async function load(hash = '') {
	window.history.replaceState(
		null,
		'',
		hash === '' ? window.location.pathname : hash,
	)
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
	await import('../../js/admin-layout-panel.js?t=' + Math.random())
}

/** Change the hash without the browser's own event, then announce it once. */
async function hashTo(hash) {
	window.history.replaceState(
		null,
		'',
		hash === '#' ? window.location.pathname : hash,
	)
	window.dispatchEvent(new HashChangeEvent('hashchange'))
}

describe('panels of the Theming page', () => {
	beforeEach(() => {
		window.t = (app, text, vars) =>
			String(text).replace(/\{(\w+)\}/g, (all, key) =>
				vars && key in vars ? String(vars[key]) : all,
			)
		window.OCP = {
			InitialState: {
				loadState: () => [
					{
						id: 'zuiddrecht',
						name: 'Gemeente Zuiddrecht',
						layout: { workplace_layout: 'light', navigation_width: 264 },
					},
				],
			},
		}
		window.OC = {
			getCurrentUser: () => ({ displayName: 'Pieter Jansen' }),
			theme: { name: 'Gemeente Zuiddrecht' },
		}
		window.scrollTo = vi.fn()
		document.body.innerHTML = PAGE
	})

	afterEach(() => {
		vi.restoreAllMocks()
		added.forEach(([target, type, listener]) =>
			target.removeEventListener(type, listener),
		)
		added = []
		window.history.replaceState(null, '', window.location.pathname)
		delete window.ThematiqPanels
		document.body.innerHTML = ''
		delete document.documentElement.dataset.thematiqOpenPanel
	})

	it('opens on its hash: the panel shows, the header carries it, the options move in', async () => {
		await load()
		await hashTo('#layout')

		const panel = document.querySelector('[data-thematiq-panel="layout"]')
		expect(panel.hidden).toBe(false)
		expect(document.documentElement.dataset.thematiqOpenPanel).toBe('layout')
		expect(
			document.querySelector('.thematiq-page-header__title').textContent,
		).toBe('Layout and options')
		const crumbs = [
			...document.querySelectorAll(
				'.thematiq-page-header__crumbs > :not([aria-hidden])',
			),
		].map((el) => el.textContent)
		expect(crumbs).toEqual([
			'Administration settings',
			'Themes and appearance',
			'Thematiq',
			'Layout and options',
		])
		expect(
			document
				.querySelector('.thematiq-page-header__crumbs a')
				.getAttribute('href'),
		).toBe('#')
		expect(
			document.querySelector('.thematiq-page-header__intro').textContent,
		).toContain('now Gemeente Zuiddrecht')
		expect(
			document.querySelector('.thematiq-page-header__actions').textContent,
		).toBe('Everything follows the theme')
		expect(
			panel.querySelector(
				'[data-thematiq-slot="thematiq-workplace-layout"] #option-layout',
			),
		).not.toBeNull()
		expect(document.activeElement).toBe(
			document.querySelector('.thematiq-page-header__title'),
		)
	})

	it('opens at once on a deep link', async () => {
		await load('#layout')
		expect(document.querySelector('[data-thematiq-panel="layout"]').hidden).toBe(
			false,
		)
	})

	it('closes when the hash goes: the overview, the header and the options come back', async () => {
		await load()
		await hashTo('#layout')
		await hashTo('#')

		expect(document.querySelector('[data-thematiq-panel="layout"]').hidden).toBe(
			true,
		)
		expect(document.documentElement.dataset.thematiqOpenPanel).toBeUndefined()
		expect(
			document.querySelector('.thematiq-page-header__title').textContent,
		).toBe('Themes and appearance')
		expect(document.getElementById('preview')).not.toBeNull()
		expect(
			document.querySelector('.thematiq-layout-card #option-layout'),
		).not.toBeNull()
		expect(document.activeElement).toBe(document.getElementById('all-options'))
	})

	it('ignores a hash no panel has', async () => {
		await load()
		await hashTo('#nothing')
		expect(document.documentElement.dataset.thematiqOpenPanel).toBeUndefined()
	})

	it('says under each option what the theme chooses', async () => {
		await load()
		await hashTo('#layout')

		const lines = [...document.querySelectorAll('.thematiq-panel__now')].map(
			(el) => el.textContent,
		)
		expect(lines).toEqual([
			'The theme says: light top bar',
			'The theme says: 264 px',
		])

		const select = document.getElementById('thematiq-workplace-layout')
		select.value = 'light'
		select.dispatchEvent(new Event('change', { bubbles: true }))
		expect(document.querySelector('.thematiq-panel__now').textContent).toBe(
			'Chosen, the theme says the same',
		)
	})

	it('lists the choices of this session and draws what they come to', async () => {
		await load()
		await hashTo('#layout')
		const box = document.getElementById('nldesign-hide-slogan')
		box.checked = true
		box.dispatchEvent(new Event('change', { bubbles: true }))

		expect(document.querySelector('.thematiq-panel__count').textContent).toBe(
			'1',
		)
		expect(
			document.querySelector('.thematiq-layout-panel__summary').textContent,
		).toBe('1 choice in this session.')
		expect(
			document.querySelector('.thematiq-layout-panel__changes').textContent,
		).toBe('Hide Nextcloud slogan/payoff on login pageon')
		const titles = [
			...document.querySelectorAll('.thematiq-layout-panel__preview h2'),
		].map((el) => el.textContent)
		expect(titles).toEqual(['Before', 'After', 'Login card'])
		expect(
			document.querySelector(
				'.thematiq-layout-panel__preview--after .thematiq-preview__bar--light',
			),
		).not.toBeNull()
		expect(
			document.querySelector(
				'.thematiq-layout-panel__preview--before .thematiq-preview__bar--light',
			),
		).toBeNull()
		expect(
			document.querySelector('.thematiq-layout-panel__preview--login')
				.textContent,
		).toContain('no slogan')
	})

	it('lets every option follow the theme again from the header', async () => {
		await load()
		await hashTo('#layout')
		const select = document.getElementById('thematiq-workplace-layout')
		select.value = 'default'
		const changed = vi.fn()
		select.addEventListener('change', changed)

		document.querySelector('.thematiq-layout-panel__follow').click()

		expect(select.value).toBe('')
		expect(changed).toHaveBeenCalledTimes(1)
	})

	it('keeps the dialogs beside the panels out of the rule that hides the overview', () => {
		const css = readFileSync(
			resolve(__dirname, '../../css/admin-panels.css'),
			'utf8',
		)
		// The formatter may wrap a long selector, so compare with the whitespace collapsed.
		expect(css.replace(/\s+/g, ' ')).toContain(
			'html[data-thematiq-open-panel] #nldesign-settings > :not([data-thematiq-panel]):not(dialog) {',
		)
	})
})
