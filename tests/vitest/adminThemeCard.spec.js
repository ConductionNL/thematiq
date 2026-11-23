/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Nextcloud's own theming form is drawn as TqTheming's Theme card by
 * restyling core's `#settings-admin-theming` block, never by forking it.
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const css = fs
	.readFileSync(path.resolve(__dirname, '..', '..', 'css/admin-page.css'), 'utf8')
	.replace(/\/\*[\s\S]*?\*\//g, '')

/** The declarations of the blocks whose selector list holds `selector`. */
function block(selector) {
	const re = /([^{}]+)\{([^{}]*)\}/g
	let m
	const out = []
	while ((m = re.exec(css)) !== null) {
		if (m[1].split(',').map((s) => s.replace(/\s+/g, ' ').trim()).includes(selector)) {
			out.push(m[2])
		}
	}
	return out.join('\n')
}

describe('the Theme card', () => {
	it('draws core\'s theming block as a 12px card with the board\'s padding', () => {
		const card = block('#settings-admin-theming')
		expect(card).toMatch(/padding:\s*22px 24px/)
		expect(card).toMatch(/border:\s*1px solid var\(--color-border\)/)
		expect(card).toMatch(/border-radius:\s*var\(--border-radius-container-large, 12px\)/)
	})

	it('lays the fields in the board\'s grid with the label above a 40px field', () => {
		expect(block('#settings-admin-theming > .settings-section > div:has(> .input-field)')).toMatch(
			/grid-template-columns:\s*repeat\(auto-fit, minmax\(300px, 1fr\)\)/,
		)
		expect(block('#settings-admin-theming .input-field__label')).toMatch(/position:\s*absolute !important/)
		expect(block('#settings-admin-theming .input-field__input')).toMatch(/height:\s*40px !important/)
	})

	it('splits the sections with a hairline instead of core\'s own border', () => {
		expect(block('#settings-admin-theming > .settings-section')).toMatch(/border-bottom:\s*0 !important/)
		expect(block('#settings-admin-theming > .settings-section + .settings-section')).toMatch(
			/border-top:\s*1px solid var\(--color-border\)/,
		)
	})

	it('never names core\'s hashed module classes', () => {
		expect(css).not.toMatch(/\._[A-Za-z]+_[a-z0-9]{5}_/)
	})
})
