/**
 * @vitest-environment jsdom
 *
 * The admin dialog frame (js/dialogs/admin-dialog.js) and the reset dialog
 * drawn in it (js/dialogs/reset-theme-dialog.js, the TqTerugNaarNextcloud
 * board): what it lists, what its buttons do, and focus going back.
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
	await import('../../js/dialogs/reset-theme-dialog.js?t=' + Math.random())
}

function texts(selector) {
	return [...document.querySelectorAll(selector)].map((el) => el.textContent)
}

describe('Reset theme to Nextcloud dialog', () => {
	let opener

	beforeEach(async () => {
		window.t = (app, text, values) =>
			String(text).replace(/\{(\w+)\}/g, (all, key) =>
				values && key in values ? String(values[key]) : all,
			)
		document.body.innerHTML =
			'<button id="opener">Back to the Nextcloud theme</button>'
		opener = document.getElementById('opener')
		await load()
	})

	afterEach(() => {
		document.body.innerHTML = ''
		delete window.ThematiqAdminDialog
		delete window.ThematiqResetThemeDialog
	})

	it('draws the board: eyebrow, title, what goes, what stays and the warning', () => {
		window.ThematiqResetThemeDialog.open({
			opener,
			themeName: 'Gemeente Zuiddrecht',
			overrideCount: 42,
			onConfirm: vi.fn(),
		})

		const dialog = document.getElementById('thematiq-reset-theme-dialog')
		expect(dialog.tagName).toBe('DIALOG')
		expect(
			dialog.querySelector('.thematiq-admin-dialog__eyebrow').textContent,
		).toBe('Active token set')
		const title = dialog.querySelector('h2')
		expect(title.textContent).toBe('Reset theme to Nextcloud?')
		expect(dialog.getAttribute('aria-labelledby')).toBe(title.id)
		expect(texts('.thematiq-admin-dialog__subtitle')).toEqual([
			'This goes',
			'This stays',
		])
		expect(texts('.thematiq-admin-dialog__list--goes li')).toEqual([
			'The 42 overrides of the current theme Gemeente Zuiddrecht',
			'Every change written over the Nextcloud theme',
			'Colours and logo of Nextcloud: these go back to the default',
		])
		expect(texts('.thematiq-admin-dialog__list--stays li')).toEqual([
			'Own token sets',
			'Typefaces',
			'Custom CSS',
		])
		expect(
			dialog.querySelector('.thematiq-admin-dialog__notice--warning')
				.textContent,
		).toBe('This cannot be undone.')
		expect(texts('.thematiq-admin-dialog__foot button')).toEqual([
			'Cancel',
			'Reset to Nextcloud',
		])
	})

	it('words the overrides line for one override, for none and for an unknown count', () => {
		window.ThematiqResetThemeDialog.open({
			opener,
			themeName: 'Utrecht',
			overrideCount: 1,
			onConfirm: vi.fn(),
		})
		expect(
			document.querySelector('.thematiq-admin-dialog__list--goes li')
				.textContent,
		).toBe('The override of the current theme Utrecht')
		document.querySelector('.thematiq-admin-dialog__close').click()

		window.ThematiqResetThemeDialog.open({
			opener,
			themeName: 'Utrecht',
			overrideCount: null,
			onConfirm: vi.fn(),
		})
		expect(
			document.querySelector('.thematiq-admin-dialog__list--goes li')
				.textContent,
		).toBe('The overrides of the current theme Utrecht')
		document.querySelector('.thematiq-admin-dialog__close').click()

		window.ThematiqResetThemeDialog.open({
			opener,
			themeName: 'Utrecht',
			overrideCount: 0,
			onConfirm: vi.fn(),
		})
		expect(
			document.querySelector('.thematiq-admin-dialog__list--goes li')
				.textContent,
		).toBe('The overrides of the current theme Utrecht')
	})

	it('gives focus to Cancel, and Cancel closes without resetting', () => {
		const onConfirm = vi.fn()
		window.ThematiqResetThemeDialog.open({
			opener,
			themeName: 'X',
			overrideCount: 0,
			onConfirm,
		})

		const cancel = document.querySelector('.thematiq-admin-dialog__foot button')
		expect(document.activeElement).toBe(cancel)
		cancel.click()

		expect(onConfirm).not.toHaveBeenCalled()
		expect(document.getElementById('thematiq-reset-theme-dialog')).toBeNull()
		expect(document.activeElement).toBe(opener)
	})

	it('closes on Escape and gives focus back', () => {
		window.ThematiqResetThemeDialog.open({
			opener,
			themeName: 'X',
			overrideCount: 0,
			onConfirm: vi.fn(),
		})
		const dialog = document.getElementById('thematiq-reset-theme-dialog')

		dialog.dispatchEvent(
			new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }),
		)

		expect(document.getElementById('thematiq-reset-theme-dialog')).toBeNull()
		expect(document.activeElement).toBe(opener)
	})

	it('hands the open dialog to onConfirm, which can lock it', () => {
		const onConfirm = vi.fn((dialog) => dialog.busy(true))
		window.ThematiqResetThemeDialog.open({
			opener,
			themeName: 'X',
			overrideCount: 3,
			onConfirm,
		})

		document.querySelector('.thematiq-admin-dialog__button--danger').click()

		expect(onConfirm).toHaveBeenCalledTimes(1)
		const buttons = [
			...document.querySelectorAll('#thematiq-reset-theme-dialog button'),
		]
		expect(buttons.every((button) => button.disabled)).toBe(true)
		expect(
			document
				.getElementById('thematiq-reset-theme-dialog')
				.getAttribute('aria-busy'),
		).toBe('true')
	})

	it('opens nothing without the dialog frame', () => {
		delete window.ThematiqAdminDialog
		expect(
			window.ThematiqResetThemeDialog.open({
				opener,
				themeName: 'X',
				onConfirm: vi.fn(),
			}),
		).toBeNull()
	})
})
