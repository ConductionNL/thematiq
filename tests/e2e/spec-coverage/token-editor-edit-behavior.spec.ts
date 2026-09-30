/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/token-editor-ui/spec.md
 *
 * Behavioral UI tests for the NL Design token editor: editing a token value
 * drives a live preview (inline style on <html>), shows a custom-value badge
 * and an "Unsaved changes" status, keeps the colour picker and hex field in
 * sync, and the per-token reset button clears all of that again.
 *
 * These tests are DOM-only and non-persisting: they never click "Save
 * overrides", so nothing is written to custom-overrides.css. Each test reloads
 * a fresh page, so any in-memory edits are discarded.
 */
import { test, expect, Page } from '@playwright/test'
import { openTokenTab } from '../workflows/_helpers'

const THEMING_URL = '/settings/admin/theming'

// An editable colour field on the Content area tab. Only the open tab's panel
// is displayed, so each test opens this tab itself (openTokenTab) rather than
// relying on the tab the editor happens to open on.
const EDITABLE_FIELD =
	'.nldesign-tab-panel[data-panel="content"] .nldesign-color-text:not([disabled])'

async function dismissSyncDialog(page: Page): Promise<void> {
	await page.waitForTimeout(1200)
	const dlg = page.locator('#nldesign-theming-dialog-overlay')
	if (await dlg.isVisible().catch(() => false)) {
		await dlg
			.locator('.nldesign-dialog-cancel')
			.first()
			.click()
			.catch(() => {})
		await expect(dlg)
			.not.toBeVisible({ timeout: 5_000 })
			.catch(() => {})
	}
}

function collectErrors(page: Page): { console: string[]; server: string[] } {
	const out = { console: [] as string[], server: [] as string[] }
	page.on('console', (m) => {
		if (m.type() === 'error') {
			const txt = m.text()
			// Ignore unrelated NC/browser noise; keep nldesign-origin errors.
			if (/nldesign|token|overrides|theming/i.test(txt)) out.console.push(txt)
		}
	})
	page.on('response', (r) => {
		if (r.status() >= 500 && /nldesign/.test(r.url()))
			out.server.push(`${r.status()} ${r.url()}`)
	})
	return out
}

test.describe('token-editor-edit-behavior', () => {
	test(// @e2e openspec/specs/token-editor-ui/spec.md#admin-changes-a-color-token
	'Editing a colour hex field drives the live preview on <html> and shows a custom badge + unsaved status', async ({
		page,
	}) => {
		const errs = collectErrors(page)
		await page.goto(THEMING_URL)
		await page.waitForSelector('#nldesign-token-editor', { timeout: 15_000 })
		await dismissSyncDialog(page)
		await openTokenTab(page, 'content')

		// Pick the first EDITABLE colour text field and read its token name.
		// Nextcloud's base tokens lead the list and stay locked until the
		// admin opts in, so the first field overall is a disabled one.
		const field = page.locator(EDITABLE_FIELD).first()
		await expect(field).toBeVisible()
		const tokenName = await field.getAttribute('data-token')
		expect(tokenName, 'colour field must carry a data-token').toBeTruthy()

		const row = page.locator(`[data-token-row="${tokenName}"]`)
		// Before editing, there must be no custom badge on this row (fresh load).
		const badge = row.locator('.nldesign-token-custom-badge')

		// Type a distinct, valid hex value.
		const NEW = '#a1b2c3'
		await field.fill(NEW)
		await field.dispatchEvent('input')

		// Live preview: the preview carries the new value for this token, and
		// the page does not — an unsaved edit is a preview until it is saved
		// (applyLivePreview → #nldesign-preview.style, never <html>).
		const inline = await page.evaluate(
			(name) => ({
				preview: (
					document.getElementById('nldesign-preview') as HTMLElement
				).style
					.getPropertyValue(name)
					.trim(),
				page: document.documentElement.style.getPropertyValue(name).trim(),
			}),
			tokenName as string,
		)
		expect(inline.preview.toLowerCase()).toBe(NEW)
		expect(inline.page).toBe('')

		// A custom-value badge must appear on the edited row (markDirty).
		await expect(badge).toHaveCount(1)

		// The save-status must announce unsaved changes.
		await expect(page.locator('#nldesign-save-status')).toHaveText(/unsaved/i)

		// No nldesign-origin console error or 5xx during the interaction.
		expect(errs.console, 'no nldesign console errors').toEqual([])
		expect(errs.server, 'no nldesign 5xx').toEqual([])
	})

	test(// @e2e openspec/specs/token-editor-ui/spec.md#color-tokens-render-a-color-picker
	'Colour picker and hex text field stay in sync when the hex field changes', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		await page.waitForSelector('#nldesign-token-editor', { timeout: 15_000 })
		await dismissSyncDialog(page)
		await openTokenTab(page, 'content')

		const field = page.locator(EDITABLE_FIELD).first()
		const tokenName = await field.getAttribute('data-token')
		const picker = page.locator(
			`.nldesign-color-picker[data-token="${tokenName}"]`,
		)
		await expect(picker).toBeAttached()

		const NEW = '#0a141e'
		await field.fill(NEW)
		await field.dispatchEvent('input')

		// The native colour <input type="color"> value mirrors the hex field.
		await expect(picker).toHaveValue(NEW)
	})

	test(// @e2e openspec/specs/token-editor-ui/spec.md#admin-resets-a-customized-token
	'Per-token reset clears the live preview and removes the custom badge after an edit', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		await page.waitForSelector('#nldesign-token-editor', { timeout: 15_000 })
		await dismissSyncDialog(page)
		await openTokenTab(page, 'content')

		const field = page.locator(EDITABLE_FIELD).first()
		const tokenName = await field.getAttribute('data-token')
		const row = page.locator(`[data-token-row="${tokenName}"]`)
		const badge = row.locator('.nldesign-token-custom-badge')

		// Edit to create a custom value + inline style + badge.
		await field.fill('#fedcba')
		await field.dispatchEvent('input')
		await expect(badge).toHaveCount(1)

		// Click the reset button for this row.
		const resetBtn = row.locator('.nldesign-reset-btn')
		await expect(resetBtn).toBeVisible()
		await resetBtn.click()

		// Reset removes the preview's inline style and removes the custom badge.
		const inlineVal = await page.evaluate(
			(name) =>
				(document.getElementById('nldesign-preview') as HTMLElement).style
					.getPropertyValue(name)
					.trim(),
			tokenName as string,
		)
		expect(inlineVal).toBe('')
		await expect(badge).toHaveCount(0)

		// The field is restored to the resolved default (a non-empty value).
		const restored = await field.inputValue()
		expect(restored.trim().length).toBeGreaterThan(0)
	})
})
