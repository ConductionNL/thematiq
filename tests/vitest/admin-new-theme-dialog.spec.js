/**
 * @vitest-environment jsdom
 *
 * Save these changes as a new theme (js/dialogs/new-theme-dialog.js, the
 * TqNieuwThema board): what it shows, the three routes out of it, and the
 * refusal under the name field.
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
	await import('../../js/dialogs/new-theme-dialog.js?t=' + Math.random())
}

const dialog = () => document.getElementById('nldesign-save-dialog-overlay')
const name = () => document.getElementById('nldesign-save-newset-name')

describe('Save these changes as a new theme dialog', () => {
	let opener

	beforeEach(async () => {
		window.t = (app, text) => text
		document.body.innerHTML = '<button id="opener">Save</button>'
		opener = document.getElementById('opener')
		opener.focus()
		await load()
	})

	afterEach(() => {
		document.body.innerHTML = ''
		delete window.ThematiqAdminDialog
		delete window.ThematiqNewThemeDialog
	})

	it('draws the board: notice, line, the name field with its hint, the note and three buttons', () => {
		window.ThematiqNewThemeDialog.open({ proceed: vi.fn() })

		expect(dialog().querySelector('.thematiq-admin-dialog__eyebrow').textContent).toBe('Save')
		expect(dialog().querySelector('h2').textContent).toBe('Save these changes as a new theme?')
		expect(dialog().querySelector('.thematiq-admin-dialog__notice--info').textContent).toContain('Nextcloud default theme')
		const label = dialog().querySelector('label[for="nldesign-save-newset-name"]')
		expect(label.textContent).toBe('Name of the new theme')
		expect(name().placeholder).toBe('My organisation')
		expect(document.getElementById(name().getAttribute('aria-describedby')).textContent).toBe('You then choose it in the active token set list.')
		expect(document.activeElement).toBe(name())
		expect([...dialog().querySelectorAll('.thematiq-admin-dialog__foot button')].map((b) => b.textContent)).toEqual([
			'Change the Nextcloud theme',
			'Cancel',
			'Save as new theme',
		])
	})

	it('writes over the Nextcloud theme from the link button', () => {
		const proceed = vi.fn()
		window.ThematiqNewThemeDialog.open({ proceed })

		dialog().querySelector('.nldesign-dialog-overwrite').click()

		expect(proceed).toHaveBeenCalledWith('overrides')
		expect(dialog()).toBeNull()
		expect(document.activeElement).toBe(opener)
	})

	it('refuses an empty name in the dialog and sends nothing', () => {
		const proceed = vi.fn()
		window.ThematiqNewThemeDialog.open({ proceed })

		dialog().querySelector('.nldesign-dialog-confirm').click()

		expect(proceed).not.toHaveBeenCalled()
		expect(document.getElementById('nldesign-save-newset-error').textContent).toBe('Give the new theme a name.')
		expect(name().getAttribute('aria-invalid')).toBe('true')
	})

	it('stays open on a refusal and closes once the set exists', () => {
		let route
		window.ThematiqNewThemeDialog.open({ proceed: (...args) => { route = args } })
		name().value = ' OpenWoo '

		dialog().querySelector('.nldesign-dialog-confirm').click()
		expect(route[0]).toBe('new-set')
		expect(route[1]).toBe('OpenWoo')
		expect(name().disabled).toBe(true)

		route[2].fail('A token set named "OpenWoo" already exists.')
		expect(dialog()).not.toBeNull()
		expect(name().disabled).toBe(false)
		expect(document.getElementById('nldesign-save-newset-error').hidden).toBe(false)

		dialog().querySelector('.nldesign-dialog-confirm').click()
		expect(document.getElementById('nldesign-save-newset-error').hidden).toBe(true)
		route[2].done()
		expect(dialog()).toBeNull()
	})

	it('Cancel and Escape close without acting', () => {
		const proceed = vi.fn()
		window.ThematiqNewThemeDialog.open({ proceed })
		dialog().querySelector('.nldesign-dialog-cancel').click()
		expect(dialog()).toBeNull()

		window.ThematiqNewThemeDialog.open({ proceed })
		dialog().dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
		expect(dialog()).toBeNull()
		expect(proceed).not.toHaveBeenCalled()
		expect(document.activeElement).toBe(opener)
	})
})
