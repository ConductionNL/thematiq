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
				// The app hands the primary-colour family of a set that carries
				// its own Nextcloud primary colour to the theming dialog, not to
				// this one (openTokenSetApplyDialog in js/admin.js), so a set that
				// differs only there opens no token dialog.
				let setTheming: { primary_color?: string } | undefined
				try {
					const sets = (
						window as unknown as {
							OCP: {
								InitialState: {
									loadState: (a: string, k: string, f: unknown) => unknown
								}
							}
						}
					).OCP.InitialState.loadState('thematiq', 'tokenSets', []) as Array<{
						id: string
						theming?: { primary_color?: string }
					}>
					setTheming = sets.find((ts) => ts.id === tokenSetId)?.theming
				} catch {
					setTheming = undefined
				}
				// The app also lays the set's saved overrides over the preview
				// before comparing, so a set whose overrides already hold the
				// current values (residue of an earlier run) shows no changes.
				try {
					const ocx = (
						window as unknown as {
							OC: { generateUrl: (p: string) => string; requestToken: string }
						}
					).OC
					const saved = (await (
						await fetch(
							ocx.generateUrl(
								'/apps/thematiq/settings/overrides?tokenSet='
									+ encodeURIComponent(tokenSetId),
							),
							{ headers: { requesttoken: ocx.requestToken } },
						)
					).json()) as { overrides?: Record<string, string> }
					for (const [name, value] of Object.entries(saved.overrides ?? {})) {
						if (resolved[name] !== undefined) resolved[name] = value
					}
				} catch {
					// No saved overrides: compare the preview as it is.
				}
				const keys = Object.keys(resolved).filter(
					(k) =>
						!(setTheming?.primary_color && k.indexOf('--color-primary') === 0),
				)
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
		'Closing the dialog with its close button cancels it (reverts the dropdown)',
		async ({ page }) => {
			const { overlay, select, original, target } = await openApplyDialog(page)
			// Sanity: the dropdown currently shows the (un-applied) target value.
			expect(target).not.toBe(original)
			await expect(select).toHaveValue(target)

			// The Tq dialog boards close a dialog with Escape, its round close
			// button or Cancel; a click on the backdrop does nothing.
			await overlay.getByRole('button', { name: 'Close', exact: true }).click()
			await expect(overlay).not.toBeVisible({ timeout: 5_000 })

			// cancelDialog reverts the dropdown to the originally-active token set
			// and writes nothing to the server.
			await expect(select).toHaveValue(original)
		},
	)
})
