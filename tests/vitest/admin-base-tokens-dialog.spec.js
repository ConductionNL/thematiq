/**
 * @vitest-environment jsdom
 *
 * Edit Nextcloud's base tokens (js/dialogs/base-tokens-dialog.js, the
 * TqBasistokens board) and the frame's table.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

async function load() {
	vi.resetModules()
	await import('../../js/dialogs/admin-dialog.js?t=' + Math.random())
	await import('../../js/dialogs/base-tokens-dialog.js?t=' + Math.random())
}

const dialog = () => document.getElementById('nldesign-base-unlock-overlay')

describe("Edit Nextcloud's base tokens dialog", () => {
	let opener

	beforeEach(async () => {
		window.t = (app, text) => text
		document.body.innerHTML = '<input type="checkbox" id="nldesign-base-unlock">'
		opener = document.getElementById('nldesign-base-unlock')
		await load()
	})

	afterEach(() => {
		document.body.innerHTML = ''
		delete window.ThematiqAdminDialog
		delete window.ThematiqBaseTokensDialog
	})

	it('draws the board: the line, three base tokens with what reads them, the notice', () => {
		window.ThematiqBaseTokensDialog.open({ opener, onConfirm: vi.fn() })

		expect(dialog().querySelector('.thematiq-admin-dialog__eyebrow').textContent).toBe("Also edit Nextcloud's base tokens")
		expect(dialog().querySelector('h2').textContent).toBe("Edit Nextcloud's base tokens?")
		expect([...dialog().querySelectorAll('th')].map((th) => th.textContent)).toEqual(['Base token', 'Read by'])
		expect([...dialog().querySelectorAll('tbody tr')].map((tr) => tr.cells[0].textContent)).toEqual([
			'--color-main-text',
			'--color-border',
			'--border-radius-element',
		])
		expect(dialog().querySelector('.thematiq-admin-dialog__notice--info').textContent).toContain('its own rows')
		expect(document.activeElement).toBe(dialog().querySelector('.nldesign-dialog-cancel'))
	})

	it('unlocks on Edit anyway, and Cancel gives focus back to the box', () => {
		const onConfirm = vi.fn()
		window.ThematiqBaseTokensDialog.open({ opener, onConfirm })
		dialog().querySelector('.nldesign-dialog-cancel').click()
		expect(onConfirm).not.toHaveBeenCalled()
		expect(document.activeElement).toBe(opener)

		window.ThematiqBaseTokensDialog.open({ opener, onConfirm })
		dialog().querySelector('.nldesign-dialog-confirm').click()
		expect(onConfirm).toHaveBeenCalledTimes(1)
		expect(dialog()).toBeNull()
	})
})
