/**
 * @vitest-environment jsdom
 *
 * Save the overrides (js/dialogs/save-overrides-dialog.js, the
 * TqOverridesOpslaan board): the count in the title, each change with what
 * it was and what it becomes, and the do not ask again box.
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
	await import('../../js/dialogs/save-overrides-dialog.js?t=' + Math.random())
}

const dialog = () => document.getElementById('nldesign-save-dialog-overlay')
const CHANGES = [
	{
		name: '--nldesign-component-table-zebra-background-color',
		label: 'Zebra row',
		was: '#EDEFF2',
		becomes: '#F5F6F8',
	},
	{ name: '--nldesign-font-size', label: 'Font size', was: '', becomes: '16px' },
]

describe('Save the overrides dialog', () => {
	beforeEach(async () => {
		window.t = (app, text, values) =>
			String(text).replace(/\{(\w+)\}/g, (all, key) =>
				values && key in values ? String(values[key]) : all,
			)
		document.body.innerHTML = '<button id="opener">Save</button>'
		document.getElementById('opener').focus()
		await load()
	})

	afterEach(() => {
		document.body.innerHTML = ''
		delete window.ThematiqAdminDialog
		delete window.ThematiqSaveOverridesDialog
	})

	it('draws the board: the count, the set, the changes and the box', () => {
		window.ThematiqSaveOverridesDialog.open({
			setName: 'Gemeente Zuiddrecht',
			changes: CHANGES,
			onConfirm: vi.fn(),
		})

		expect(
			dialog().querySelector('.thematiq-admin-dialog__eyebrow').textContent,
		).toBe('Components and tokens')
		expect(dialog().querySelector('h2').textContent).toBe('Save 2 overrides?')
		expect(
			dialog().querySelector('.thematiq-admin-dialog__lead').textContent,
		).toContain('on top of Gemeente Zuiddrecht')
		expect(
			[...dialog().querySelectorAll('th')].map((th) => th.textContent),
		).toEqual(['Token', 'Was', 'Becomes'])
		const rows = [...dialog().querySelectorAll('tbody tr')]
		expect(rows).toHaveLength(2)
		expect(rows[0].cells[0].textContent).toBe(
			'Zebra row--nldesign-component-table-zebra-background-color',
		)
		expect(
			rows[0].cells[1].querySelector('.thematiq-admin-dialog__swatch'),
		).not.toBeNull()
		expect(rows[1].cells[1].textContent).toBe('–')
		expect(
			rows[1].cells[2].querySelector('.thematiq-admin-dialog__swatch'),
		).toBeNull()
		expect(dialog().querySelector('.nldesign-dialog-dontask').type).toBe(
			'checkbox',
		)
		expect(document.activeElement).toBe(
			dialog().querySelector('.nldesign-dialog-confirm'),
		)
	})

	it('says 1 override for one change', () => {
		window.ThematiqSaveOverridesDialog.open({
			setName: 'X',
			changes: CHANGES.slice(0, 1),
			onConfirm: vi.fn(),
		})
		expect(dialog().querySelector('h2').textContent).toBe('Save 1 override?')
	})

	it('saves and closes on Save overrides; Cancel and Escape save nothing', () => {
		const onConfirm = vi.fn()
		window.ThematiqSaveOverridesDialog.open({
			setName: 'X',
			changes: CHANGES,
			onConfirm,
		})
		dialog().querySelector('.nldesign-dialog-cancel').click()
		expect(dialog()).toBeNull()

		window.ThematiqSaveOverridesDialog.open({
			setName: 'X',
			changes: CHANGES,
			onConfirm,
		})
		dialog().dispatchEvent(
			new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }),
		)
		expect(dialog()).toBeNull()
		expect(onConfirm).not.toHaveBeenCalled()
		expect(document.activeElement.id).toBe('opener')

		window.ThematiqSaveOverridesDialog.open({
			setName: 'X',
			changes: CHANGES,
			onConfirm,
		})
		dialog().querySelector('.nldesign-dialog-confirm').click()
		expect(onConfirm).toHaveBeenCalledTimes(1)
		expect(dialog()).toBeNull()
	})
})
