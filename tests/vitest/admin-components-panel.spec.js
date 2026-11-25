/**
 * @vitest-environment jsdom
 *
 * Components and tokens (js/admin-components-panel.js, the TqComponenten
 * board), the panel at #components: it borrows the
 * overview's selector, preview and token editor, presses the editor's own
 * controls from the header.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const SELECTOR = `
	<div class="nldesign-pg-selector">
		<div class="nldesign-tabs">
			<button class="nldesign-tab-btn active" data-tab="content">Content area</button>
			<button class="nldesign-tab-btn" data-tab="own">Your components</button>
		</div>
	</div>`

const PAGE = (selector) => `
	<section id="thematiq-page-header">
		<h1 class="thematiq-page-header__title">Themes and appearance</h1>
		<div class="thematiq-page-header__actions"><button id="preview-session">Preview in my session</button></div>
		<nav class="thematiq-page-header__crumbs"></nav>
		<p class="thematiq-page-header__intro"></p>
	</section>
	<div id="nldesign-settings">
		<select id="nldesign-token-set-select"><option value="zuiddrecht" selected>Gemeente Zuiddrecht</option></select>
		<a id="nldesign-token-reference-download" href="/reference?format=md&amp;download=1">Download token overview</a>
		<section class="thematiq-panel thematiq-components-panel" data-thematiq-panel="components" hidden>
			<div class="thematiq-components-panel__instrument"></div>
		</section>
		<div id="overview-before"></div>
		${selector}
		<div class="nldesign-preview" id="nldesign-preview">preview</div>
		<div id="nldesign-token-editor">
			<p id="nldesign-token-count">714 editable tokens</p>
			<button id="nldesign-export-btn">Download</button>
			<button id="nldesign-import-btn">Upload</button>
			<button id="nldesign-pg-export-btn">Export as token set</button>
			<button id="nldesign-save-btn">Save overrides</button>
		</div>
	</div>
`

const flush = () => new Promise((resolve) => setTimeout(resolve, 0))

let added = []

/**
 * Load the panels and the components panel, then open a hash.
 *
 * @param {string} hash The hash to open.
 */
async function openAt(hash) {
	for (const target of [window, document]) {
		const original = target.addEventListener.bind(target)
		vi.spyOn(target, 'addEventListener').mockImplementation((type, listener, options) => {
			added.push([target, type, listener])
			original(type, listener, options)
		})
	}
	vi.resetModules()
	await import('../../js/admin-panels.js?t=' + Math.random())
	await import('../../js/admin-components-panel.js?t=' + Math.random())
	window.history.replaceState(null, '', hash)
	window.dispatchEvent(new HashChangeEvent('hashchange'))
	await flush()
}

/** The ids and classes of the instrument's parts, in the order they stand in the panel. */
function instrument() {
	return [...document.querySelector('.thematiq-components-panel__instrument').children].map((el) => el.id || el.className)
}

describe('components panel', () => {
	beforeEach(() => {
		window.t = (app, text, vars) => String(text).replace(/\{(\w+)\}/g, (all, key) => (vars && key in vars ? String(vars[key]) : all))
		window.scrollTo = vi.fn()
		document.body.innerHTML = PAGE(SELECTOR)
		window.history.replaceState(null, '', window.location.pathname)
	})

	afterEach(() => {
		vi.restoreAllMocks()
		added.forEach(([target, type, listener]) => target.removeEventListener(type, listener))
		added = []
		window.history.replaceState(null, '', window.location.pathname)
		document.body.innerHTML = ''
		delete window.ThematiqPanels
		delete document.documentElement.dataset.thematiqOpenPanel
	})

	it('borrows the selector, the preview and the token editor in the board\'s order', async () => {
		await openAt('#components')
		expect(document.querySelector('[data-thematiq-panel="components"]').hidden).toBe(false)
		expect(instrument()).toEqual(['nldesign-pg-selector', 'nldesign-preview', 'nldesign-token-editor'])
	})

	it('gives them back where they stood when the panel closes', async () => {
		await openAt('#components')
		window.history.replaceState(null, '', window.location.pathname)
		window.dispatchEvent(new HashChangeEvent('hashchange'))
		expect(instrument()).toEqual([])
		const order = [...document.getElementById('nldesign-settings').children].map((el) => el.id || el.className)
		expect(order.slice(-4)).toEqual(['overview-before', 'nldesign-pg-selector', 'nldesign-preview', 'nldesign-token-editor'])
		expect(document.body.innerHTML).not.toContain('thematiq: ')
	})

	it('titles the header and leads the breadcrumb through the active set', async () => {
		await openAt('#components')
		expect(document.querySelector('.thematiq-page-header__title').textContent).toBe('Components and tokens')
		const crumbs = [...document.querySelectorAll('.thematiq-page-header__crumbs > :not([aria-hidden])')]
		expect(crumbs.slice(-3).map((el) => el.textContent)).toEqual(['Token sets', 'zuiddrecht', 'Components'])
		expect(crumbs[crumbs.length - 2].getAttribute('href')).toBe('#set/zuiddrecht')
		const intro = document.querySelector('.thematiq-page-header__intro').textContent
		expect(intro).toContain('Active')
		expect(intro).toContain('714 editable tokens')
	})

	it('presses the editor\'s own Download and Save from the header', async () => {
		await openAt('#components')
		const download = vi.fn()
		const save = vi.fn()
		document.getElementById('nldesign-export-btn').addEventListener('click', download)
		document.getElementById('nldesign-save-btn').addEventListener('click', save)
		const buttons = [...document.querySelectorAll('.thematiq-page-header__actions > button')]
		expect(buttons.map((b) => b.textContent)).toEqual(['Download', 'Save'])
		buttons[0].click()
		buttons[1].click()
		expect(download).toHaveBeenCalledTimes(1)
		expect(save).toHaveBeenCalledTimes(1)
	})

	it('opens the More menu, whose items press the editor\'s controls, and Escape closes it', async () => {
		await openAt('#components')
		const upload = vi.fn()
		const exportSet = vi.fn()
		document.getElementById('nldesign-import-btn').addEventListener('click', upload)
		document.getElementById('nldesign-pg-export-btn').addEventListener('click', exportSet)
		const more = document.querySelector('.thematiq-page-header__more')
		more.click()
		const menu = document.querySelector('.thematiq-page-header__menu')
		expect(menu.hidden).toBe(false)
		const items = [...menu.querySelectorAll('[role="menuitem"]')]
		expect(items.map((item) => item.querySelector('strong').textContent)).toEqual(['Upload', 'Export as token set', 'Own component', 'Download token overview'])
		const own = vi.fn()
		document.querySelector('[data-tab="own"]').addEventListener('click', own)
		expect(items[3].getAttribute('href')).toBe('/reference?format=md&download=1')
		menu.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }))
		expect(document.activeElement).toBe(items[1])
		items[0].click()
		expect(upload).toHaveBeenCalledTimes(1)
		expect(menu.hidden).toBe(true)
		more.click()
		items[1].click()
		expect(exportSet).toHaveBeenCalledTimes(1)
		more.click()
		items[2].click()
		expect(own).toHaveBeenCalledTimes(1)
		more.click()
		menu.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
		expect(menu.hidden).toBe(true)
		expect(document.activeElement).toBe(more)
	})
})
