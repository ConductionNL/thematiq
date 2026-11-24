/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/token-set-apply-dialog/spec.md
 *
 * Behavioral UI tests for the token-set apply dialog's Select-all / Deselect-all
 * controls and per-row checkbox toggling. The existing apply-dialog spec only
 * asserts that the controls are PRESENT; here we assert they actually change the
 * checkbox state of every row.
 *
 * The dialog is triggered by changing the token-set dropdown to a set whose
 * resolved values differ from the active CSS. We always Cancel afterwards, which
 * reverts the dropdown and the inline preview styles and never writes IConfig or
 * custom-overrides.css. No Apply is ever clicked.
 */
import { test, expect, Page } from '@playwright/test'

const THEMING_URL = '/settings/admin/theming'

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

/** Find a token set whose resolved values differ from the active CSS. */
async function findOptionWithChanges(
	page: Page,
	select: ReturnType<Page['locator']>,
	original: string,
): Promise<string> {
	for (const opt of await select.locator('option').all()) {
		const v = (await opt.getAttribute('value')) ?? ''
		if (!v || v === original) continue
		const hasDiffs = await page.evaluate(async (tokenSetId: string) => {
			const OC = (
				window as unknown as {
					OC: { generateUrl: (p: string) => string; requestToken: string }
				}
			).OC
			try {
				const r = await fetch(
					OC.generateUrl(
						'/apps/thematiq/settings/tokenset-preview/'
							+ encodeURIComponent(tokenSetId),
					),
					{
						headers: { requesttoken: OC.requestToken },
					},
				)
				const data = (await r.json()) as {
					resolved?: Record<string, string>
				}
				const resolved = data.resolved || {}
				const keys = Object.keys(resolved)
				if (keys.length === 0) return false
				const root = getComputedStyle(document.documentElement)
				return keys.some(
					(k) =>
						root.getPropertyValue(k).trim()
						!== (resolved[k] || '').trim(),
				)
			} catch {
				return false
			}
		}, v)
		if (hasDiffs) return v
	}
	return ''
}

async function openApplyDialog(page: Page) {
	await page.goto(THEMING_URL)
	await page.waitForSelector('#nldesign-token-set-select', { timeout: 15_000 })
	await dismissSyncDialog(page)
	const select = page.locator('#nldesign-token-set-select')
	const original = await select.inputValue()
	const target = await findOptionWithChanges(page, select, original)
	expect(
		target,
		'expected a token set whose values differ from the active one',
	).toBeTruthy()
	await select.selectOption(target)
	const overlay = page.locator('#nldesign-apply-dialog-overlay')
	await expect(overlay).toBeVisible({ timeout: 10_000 })
	// `original` is the active token set BEFORE the change; cancelling the dialog
	// must revert the dropdown to it.
	return { overlay, select, original, target }
}

test.describe('token-set-apply-toggle-behavior', () => {
	test(
		// @e2e openspec/specs/token-set-apply-dialog/spec.md#select-all-deselect-all
		'Deselect all unchecks every row, then Select all re-checks every row',
		async ({ page }) => {
			const { overlay } = await openApplyDialog(page)
			const checks = overlay.locator('.nldesign-apply-check')
			const count = await checks.count()
			expect(
				count,
				'apply dialog must have at least one change row',
			).toBeGreaterThan(0)

			// Default state: all checked.
			for (let i = 0; i < count; i++) await expect(checks.nth(i)).toBeChecked()

			// Deselect all → none checked.
			await overlay.locator('#nldesign-apply-deselect-all').click()
			for (let i = 0; i < count; i++)
				await expect(checks.nth(i)).not.toBeChecked()

			// Select all → all checked again.
			await overlay.locator('#nldesign-apply-select-all').click()
			for (let i = 0; i < count; i++) await expect(checks.nth(i)).toBeChecked()

			// Cancel to revert dropdown + preview, write nothing.
			await overlay.locator('.nldesign-dialog-cancel').first().click()
			await expect(overlay).not.toBeVisible()
		},
	)

	test(
		// @e2e openspec/specs/token-set-apply-dialog/spec.md#admin-unchecks-a-row
		'Unchecking a single row leaves the other rows untouched',
		async ({ page }) => {
			const { overlay } = await openApplyDialog(page)
			const checks = overlay.locator('.nldesign-apply-check')
			const count = await checks.count()
			test.skip(
				count < 2,
				'needs at least two change rows to assert independence',
			)

			await checks.first().uncheck()
			await expect(checks.first()).not.toBeChecked()
			// Every other row stays checked.
			for (let i = 1; i < count; i++) await expect(checks.nth(i)).toBeChecked()

			await overlay.locator('.nldesign-dialog-cancel').first().click()
			await expect(overlay).not.toBeVisible()
		},
	)

	test(
		// @e2e openspec/specs/token-set-apply-dialog/spec.md#dialog-closed-with-cancel
		'Clicking the overlay backdrop cancels the dialog (reverts the dropdown)',
		async ({ page }) => {
			const { overlay, select, original, target } = await openApplyDialog(page)
			// Sanity: the dropdown currently shows the (un-applied) target value.
			expect(target).not.toBe(original)
			await expect(select).toHaveValue(target)

			// Click the overlay itself (its own backdrop) — handler cancels when
			// e.target === overlay.
			await overlay.click({ position: { x: 4, y: 4 } })
			await expect(overlay).not.toBeVisible({ timeout: 5_000 })

			// cancelDialog reverts the dropdown to the originally-active token set
			// and writes nothing to the server.
			await expect(select).toHaveValue(original)
		},
	)
})
