/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin-website-lettering.js: each website size of the set,
 * named with its pixels and drawn at 60 percent; the card hides for a set
 * without website sizes.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

async function loadScript() {
	vi.resetModules()
	await import('../../js/admin-website-lettering.js?t=' + Math.random())
}

describe('Website: lettering', () => {
	beforeEach(() => {
		document.body.innerHTML =
			'<section id="thematiq-website-lettering"><div class="thematiq-lettering"></div></section>'
		document.body.removeAttribute('style')
	})

	it('names each size in pixels and draws it at 60 percent', async () => {
		document.body.style.setProperty(
			'--nldesign-website-heading-1-font-size',
			'44px',
		)
		document.body.style.setProperty(
			'--nldesign-website-lead-font-size',
			'1.25rem',
		)
		await loadScript()
		const rows = [...document.querySelectorAll('.thematiq-lettering__row')]
		expect(rows.map((row) => row.firstChild.textContent)).toEqual([
			'Page title 44',
			'Lead 20',
		])
		expect(rows[0].lastChild.style.fontSize).toBe('26px')
		expect(rows[1].lastChild.style.fontSize).toBe('12px')
	})

	it('hides the card for a set without website sizes', async () => {
		await loadScript()
		expect(document.getElementById('thematiq-website-lettering').hidden).toBe(
			true,
		)
	})

	it('draws again when another set is applied', async () => {
		await loadScript()
		document.body.style.setProperty(
			'--nldesign-website-heading-2-font-size',
			'40px',
		)
		document.dispatchEvent(new CustomEvent('thematiq:theme-applied'))
		expect(document.querySelector('.thematiq-lettering__name').textContent).toBe(
			'Heading 2 40',
		)
		expect(document.getElementById('thematiq-website-lettering').hidden).toBe(
			false,
		)
	})
})
