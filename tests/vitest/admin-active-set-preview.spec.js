/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin-active-set-preview.js: the Workplace and Login page
 * tabs, the swatches read from the colours the page wears, and the redraw when
 * another set is applied.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

async function loadScript() {
	vi.resetModules()
	await import('../../js/admin-active-set-preview.js?t=' + Math.random())
}

describe('the preview of the active set', () => {
	beforeEach(() => {
		document.body.innerHTML = '<div id="thematiq-active-set-preview"></div>'
		document.body.style.setProperty('--nldesign-color-primary', '#3669A5')
		document.body.style.setProperty('--color-main-text', 'rgb(27, 28, 29)')
		window.OC = { theme: { name: 'Municipality of Zuiddrecht', slogan: 'Working together' }, getCurrentUser: () => ({ displayName: 'Pieter Jansen' }) }
	})

	it('draws two tabs with the Workplace tab selected', async () => {
		await loadScript()
		const tabs = [...document.querySelectorAll('[role="tab"]')]
		expect(tabs.map((tab) => tab.textContent)).toEqual(['Workplace', 'Login page'])
		expect(tabs[0].getAttribute('aria-selected')).toBe('true')
		expect(document.getElementById('thematiq-preview-panel-1').hidden).toBe(true)
	})

	it('reads the swatches from the page and writes them as hex', async () => {
		await loadScript()
		const values = [...document.querySelectorAll('.thematiq-swatch__value')].map((node) => node.textContent)
		expect(values).toHaveLength(8)
		expect(values[0]).toBe('#3669A5')
		expect(values[5]).toBe('#1B1C1D')
	})

	it('shows the instance name, the initials and the slogan', async () => {
		await loadScript()
		expect(document.querySelector('.thematiq-set-sample__name').textContent).toBe('Municipality of Zuiddrecht')
		expect(document.querySelector('.thematiq-set-sample__avatar').textContent).toBe('PJ')
		expect(document.querySelector('.thematiq-login-sample__slogan').textContent).toBe('Working together')
	})

	it('moves between the tabs with the arrow keys', async () => {
		await loadScript()
		const tabs = [...document.querySelectorAll('[role="tab"]')]
		tabs[0].dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }))
		expect(tabs[1].getAttribute('aria-selected')).toBe('true')
		expect(document.activeElement).toBe(tabs[1])
		expect(document.getElementById('thematiq-preview-panel-1').hidden).toBe(false)
		expect(document.getElementById('thematiq-preview-panel-0').hidden).toBe(true)
	})

	it('redraws the swatches when another set is applied', async () => {
		await loadScript()
		document.body.style.setProperty('--nldesign-color-primary', '#CC0000')
		document.dispatchEvent(new CustomEvent('thematiq:theme-applied', { detail: { tokenSet: 'other' } }))
		expect(document.querySelector('.thematiq-swatch__value').textContent).toBe('#CC0000')
	})
})
