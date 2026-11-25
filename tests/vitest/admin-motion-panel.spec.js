/**
 * @vitest-environment jsdom
 *
 * Motion tokens (js/admin-motion-panel.js, the TqBeweging board), the panel at #motion: it
 * borrows the overview's token editor, reads the motion rows for its preview and readout,
 * and presses the editor's own Download and Save from the header.
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
		<select id="nldesign-token-set-select"><option value="zuiddrecht" selected>Gemeente Zuiddrecht</option></select>
		<section class="thematiq-panel thematiq-motion-panel" data-thematiq-panel="motion" hidden>
			<div class="thematiq-motion-panel__sample"></div>
			<button type="button" class="thematiq-motion-panel__play">Play motion</button>
			<span class="thematiq-motion-panel__readout"></span>
			<p class="thematiq-motion-panel__notice"></p>
			<div class="thematiq-motion-panel__editor"></div>
		</section>
		<div class="nldesign-pg-selector">
			<button class="nldesign-tab-btn active" data-tab="login">Login</button>
			<button class="nldesign-tab-btn" data-tab="content">Content area</button>
		</div>
		<div id="nldesign-token-editor">
			<input type="checkbox" id="nldesign-base-unlock">
			<input class="nldesign-duration-number" data-token="--animation-slow" value="300">
			<select class="nldesign-duration-unit" data-token="--animation-slow"><option value="ms" selected>ms</option><option value="s">s</option></select>
			<select class="nldesign-easing-select" data-token="--nldesign-animation-easing">
				<option value="ease">ease</option><option value="custom" selected>Custom curve</option>
			</select>
			<input class="nldesign-easing-point" data-token="--nldesign-animation-easing" value="0.2">
			<input class="nldesign-easing-point" data-token="--nldesign-animation-easing" value="0">
			<input class="nldesign-easing-point" data-token="--nldesign-animation-easing" value="0">
			<input class="nldesign-easing-point" data-token="--nldesign-animation-easing" value="1">
			<button id="nldesign-export-btn">Download</button>
			<button id="nldesign-save-btn">Save overrides</button>
		</div>
	</div>
`

const flush = () => new Promise((resolve) => setTimeout(resolve, 0))
let added = []

/**
 * Load the panels and the motion panel, then open a hash.
 *
 * @param {string} hash The hash to open.
 * @return {Promise<object>} The motion panel module.
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
	const panel = await import('../../js/admin-motion-panel.js?t=' + Math.random())
	window.history.replaceState(null, '', hash)
	window.dispatchEvent(new HashChangeEvent('hashchange'))
	await flush()
	return panel.default || panel
}

describe('motion panel values', () => {
	it('reads a duration in ms or s, and refuses what is not one', async () => {
		const { toMs } = await import('../../js/admin-motion-panel.js?t=values')
		expect(toMs('300', 'ms')).toBe(300)
		expect(toMs('0.25', 's')).toBe(250)
		expect(toMs('', 'ms')).toBeNull()
		expect(toMs('-1', 'ms')).toBeNull()
	})

	it('reads a keyword or a custom curve whose x values lie between 0 and 1', async () => {
		const { toEasing } = await import('../../js/admin-motion-panel.js?t=easing')
		expect(toEasing('ease-in-out', [])).toBe('ease-in-out')
		expect(toEasing('custom', ['0.2', '0', '0', '1'])).toBe('cubic-bezier(0.2, 0, 0, 1)')
		expect(toEasing('custom', ['1.2', '0', '0', '1'])).toBeNull()
		expect(toEasing('custom', ['0.2', '', '0', '1'])).toBeNull()
	})
})

describe('motion panel', () => {
	beforeEach(() => {
		window.t = (app, text, vars) => String(text).replace(/\{(\w+)\}/g, (all, key) => (vars && key in vars ? String(vars[key]) : all))
		window.scrollTo = vi.fn()
		window.matchMedia = vi.fn(() => ({ matches: false }))
		document.body.innerHTML = PAGE
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

	it('borrows the token editor on the content tab and gives it back on close', async () => {
		const content = vi.fn()
		document.querySelector('.nldesign-tab-btn[data-tab="content"]').addEventListener('click', content)
		await openAt('#motion')
		const slot = document.querySelector('.thematiq-motion-panel__editor')
		expect(slot.querySelector('#nldesign-token-editor')).not.toBeNull()
		expect(content).toHaveBeenCalledTimes(1)

		window.history.replaceState(null, '', window.location.pathname)
		window.dispatchEvent(new HashChangeEvent('hashchange'))
		expect(slot.children).toHaveLength(0)
		expect(document.getElementById('nldesign-token-editor').parentElement.id).toBe('nldesign-settings')
		expect(document.body.innerHTML).not.toContain('thematiq: motion')
	})

	it('titles the header, leads the breadcrumb through Components and presses Download and Save', async () => {
		await openAt('#motion')
		expect(document.querySelector('.thematiq-page-header__title').textContent).toBe('Motion tokens')
		const crumbs = [...document.querySelectorAll('.thematiq-page-header__crumbs > :not([aria-hidden])')]
		expect(crumbs.slice(-4).map((el) => el.textContent)).toEqual(['Token sets', 'zuiddrecht', 'Components', 'Motion'])
		const save = vi.fn()
		document.getElementById('nldesign-save-btn').addEventListener('click', save)
		const buttons = [...document.querySelectorAll('.thematiq-page-header__actions > button')]
		expect(buttons.map((b) => b.textContent)).toEqual(['Download', 'Save'])
		buttons[1].click()
		expect(save).toHaveBeenCalledTimes(1)
	})

	it('writes the readout from the rows and follows a change', async () => {
		await openAt('#motion')
		const readout = document.querySelector('.thematiq-motion-panel__readout')
		expect(readout.textContent).toBe('Duration 300 ms, easing cubic-bezier(0.2, 0, 0, 1). Replays the dialog and the notification.')
		const unit = document.querySelector('.nldesign-duration-unit')
		unit.value = 's'
		unit.dispatchEvent(new Event('change', { bubbles: true }))
		expect(readout.textContent).toContain('Duration 300000 ms')
	})

	it('writes the readout once the editor renders its rows after a deep link', async () => {
		const editor = document.getElementById('nldesign-token-editor')
		const rows = editor.innerHTML
		editor.innerHTML = ''
		await openAt('#motion')
		const readout = document.querySelector('.thematiq-motion-panel__readout')
		expect(readout.textContent).toBe('The values in the rows are not a valid motion yet.')
		editor.innerHTML = rows
		await flush()
		expect(readout.textContent).toContain('Duration 300 ms')
	})

	it('says whether the base tokens are open', async () => {
		await openAt('#motion')
		const notice = document.querySelector('.thematiq-motion-panel__notice')
		expect(notice.textContent).toContain('Tick "Also edit Nextcloud\'s base tokens"')
		const box = document.getElementById('nldesign-base-unlock')
		box.checked = true
		box.dispatchEvent(new Event('change', { bubbles: true }))
		expect(notice.textContent).toContain('They are open because')
	})

	it('plays the samples with the rows\' duration and easing, and not under reduced motion', async () => {
		await openAt('#motion')
		const sample = document.querySelector('.thematiq-motion-panel__sample')
		sample.animate = vi.fn()
		document.querySelector('.thematiq-motion-panel__play').click()
		expect(sample.animate).toHaveBeenCalledWith(expect.any(Array), { duration: 300, easing: 'cubic-bezier(0.2, 0, 0, 1)' })

		window.matchMedia = vi.fn(() => ({ matches: true }))
		document.querySelector('.thematiq-motion-panel__play').click()
		expect(sample.animate).toHaveBeenCalledTimes(1)
		expect(document.querySelector('.thematiq-motion-panel__readout').textContent).toContain('Duration 0 ms')
	})
})
