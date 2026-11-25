/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/token-set-dropdown/spec.md
 *
 * Behavioral UI tests for the token-set dropdown's design-system badge. The
 * badge reflects the design system of the currently-selected token set
 * ("NL Design System" / "Stock Nextcloud") and updates optimistically when the
 * selection changes.
 *
 * Changing the dropdown to a differing set also opens the apply dialog; we
 * always Cancel it, which reverts the dropdown and writes nothing.
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

/** Map a data-design-system id to its expected badge label + modifier class. */
function expectedBadge(dsId: string): { label: string; cls: RegExp } {
	if (dsId === 'none')
		return { label: 'Stock Nextcloud', cls: /nldesign-badge--stock/ }
	if (dsId === 'nldesign')
		return { label: 'NL Design System', cls: /nldesign-badge--system/ }
	// Any other id falls through to itself with the system modifier.
	return { label: dsId, cls: /nldesign-badge--system/ }
}

test.describe('token-set-dropdown-badge', () => {
	test(
		// @e2e openspec/specs/token-set-dropdown/spec.md#dropdown-renders-with-all-token-sets
		'Design-system badge matches the currently-selected token set on load',
		async ({ page }) => {
			await page.goto(THEMING_URL)
			await page.waitForSelector('#nldesign-token-set-select', {
				timeout: 15_000,
			})
			await dismissSyncDialog(page)

			const select = page.locator('#nldesign-token-set-select')
			const current = await select.inputValue()
			const dsId =
				(await select
					.locator(`option[value="${current}"]`)
					.getAttribute('data-design-system')) ?? 'nldesign'

			const badge = page.locator('#nldesign-design-system-badge')
			const exp = expectedBadge(dsId)
			await expect(badge).toHaveText(exp.label)
			await expect(badge).toHaveClass(exp.cls)
		},
	)

	test(
		// @e2e openspec/specs/token-set-dropdown/spec.md#preview-reflects-new-selection
		'Selecting a different token set updates the design-system badge optimistically',
		async ({ page }) => {
			await page.goto(THEMING_URL)
			await page.waitForSelector('#nldesign-token-set-select', {
				timeout: 15_000,
			})
			await dismissSyncDialog(page)

			const select = page.locator('#nldesign-token-set-select')
			const current = await select.inputValue()

			// Find any other option and compute its expected badge.
			const options = await select.locator('option').all()
			let target = ''
			let targetDs = 'nldesign'
			for (const o of options) {
				const v = (await o.getAttribute('value')) ?? ''
				if (v && v !== current) {
					target = v
					targetDs =
						(await o.getAttribute('data-design-system')) ?? 'nldesign'
					break
				}
			}
			expect(target, 'need at least two token sets').toBeTruthy()

			await select.selectOption(target)

			// Badge updates immediately (updateDesignSystemBadge runs before the
			// async preview fetch / apply dialog).
			const badge = page.locator('#nldesign-design-system-badge')
			const exp = expectedBadge(targetDs)
			await expect(badge).toHaveText(exp.label)
			await expect(badge).toHaveClass(exp.cls)

			// An apply dialog may or may not appear depending on whether values
			// differ; if it did, cancel it to revert. Otherwise restore selection.
			const overlay = page.locator('#nldesign-apply-dialog-overlay')
			if (await overlay.isVisible({ timeout: 3_000 }).catch(() => false)) {
				await overlay.locator('.nldesign-dialog-cancel').first().click()
				await expect(overlay).not.toBeVisible()
			}
		},
	)
})
