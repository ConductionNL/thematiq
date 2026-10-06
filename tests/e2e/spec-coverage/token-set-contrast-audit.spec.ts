/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/token-set-contrast-audit/spec.md
 *
 * UI-only Playwright tests for the shipped-token-set contrast audit's runtime
 * surfacing: a shipped set with a sub-AA (or unevaluated) verdict raises the same
 * non-blocking contrast warning in the apply dialog that a custom upload does, and
 * the admin can still apply the set. The audit computation, verdict classification
 * and the generated report are backend/CI concerns covered by the PHPUnit gate
 * tests/Unit/TokenSetContrastAuditTest.php — excluded below.
 *
 * The two backend-only scenarios in the spec carry their @e2e exclude inline in
 * openspec/specs/token-set-contrast-audit/spec.md.
 */
import { test, expect, Page, Locator } from '@playwright/test'

import { repoFile, withUploadedSet } from './_theme-state'

const THEMING_URL = '/settings/admin/theming'

/**
 * A set that fails both pairs ContrastService measures, primary text on the
 * primary colour and the primary colour on the page: white on white, 1:1.
 */
const LOW_CONTRAST_CSS =
	':root {\n\t--nldesign-color-primary: #ffffff;\n\t--nldesign-color-primary-text: #ffffff;\n'
	+ '\t--nldesign-color-background: #ffffff;\n}\n'

/**
 * The contrast warning in the apply dialog. The incomplete-set and typeface
 * warnings share its `.nldesign-contrast-warning` box, so it is told apart by
 * its own heading.
 */
function contrastWarning(overlay: Locator): Locator {
	return overlay.locator('.nldesign-contrast-warning', {
		hasText: /contrast warning/i,
	})
}

/**
 * The shipped sets the committed audit report does not pass, the sets this
 * requirement is about. Read from the report instead of named here: a set that
 * a token sync made compliant drops out by itself (vng and noaberkracht were
 * named here and both pass now).
 */
function nonCompliantShippedSets(): string[] {
	const report = JSON.parse(repoFile('docs/reference/contrast-report.json')) as {
		sets: Record<string, { verdict: string }>
	}
	return Object.entries(report.sets)
		.filter(([, row]) => row.verdict !== 'pass')
		.map(([id]) => id)
}

/**
 * Dismiss any theming-sync dialog that may appear on page load.
 */
async function dismissThemingSyncDialog(page: Page): Promise<void> {
	await page.waitForTimeout(1500)
	const syncDialog = page.locator('#nldesign-theming-dialog-overlay')
	if (await syncDialog.isVisible().catch(() => false)) {
		const cancelBtn = syncDialog.locator('.nldesign-dialog-cancel').first()
		if (await cancelBtn.isVisible().catch(() => false)) {
			await cancelBtn.click()
		}
		await expect(syncDialog)
			.not.toBeVisible({ timeout: 5_000 })
			.catch(() => {})
	}
}

/** Open the theming page with the token-set dropdown ready. */
async function openTheming(page: Page): Promise<void> {
	await page.goto(THEMING_URL)
	await page.waitForSelector('#nldesign-token-set-select', { timeout: 15_000 })
	await dismissThemingSyncDialog(page)
}

/**
 * Choose `tokenSet` and assert the apply dialog warns about contrast without
 * blocking the apply, then cancel so the active set stays as it was.
 */
async function expectNonBlockingContrastWarning(
	page: Page,
	tokenSet: string,
): Promise<void> {
	await page.locator('#nldesign-token-set-select').selectOption(tokenSet)

	const overlay = page.locator('#nldesign-apply-dialog-overlay')
	await expect(overlay).toBeVisible({ timeout: 10_000 })

	// The contrast warning banner must be shown above the change list.
	await expect(contrastWarning(overlay)).toBeVisible()

	// The warning is non-blocking: the Apply control is still available.
	await expect(
		overlay.locator('.nldesign-dialog-apply, [class*="apply"]').first(),
	).toBeVisible()

	// Cancel to avoid mutating the shared instance's active token set.
	await overlay.locator('.nldesign-dialog-cancel').first().click()
	await expect(overlay).not.toBeVisible()
}

test.describe('token-set-contrast-audit', () => {
	// -----------------------------------------------------------------------
	// Requirement: Non-Compliant Sets Are Surfaced in the Apply Dialog
	// -----------------------------------------------------------------------

	test(// @e2e openspec/specs/token-set-contrast-audit/spec.md#applying-a-sub-aa-shipped-set-shows-a-warning-but-still-applies
	'Applying a sub-AA set shows a non-blocking contrast warning', async ({
		browser,
		page,
	}) => {
		await openTheming(page)

		// A shipped set the committed report does not pass, when one is offered.
		// The dropdown is the SELECTABLE list, not the catalogue, so a
		// non-compliant set can exist and still not be on it.
		const select = page.locator('#nldesign-token-set-select')
		let shipped = ''
		for (const id of nonCompliantShippedSets()) {
			if ((await select.locator(`option[value="${id}"]`).count()) > 0) {
				shipped = id
				break
			}
		}
		if (shipped !== '') {
			await expectNonBlockingContrastWarning(page, shipped)
			return
		}

		// Otherwise a set of the test's own, never a skip: since the token sync
		// every shipped set passes, and a skipped test would leave the warning
		// unchecked exactly while nothing ships that raises it. The banner is
		// built from the catalogue entry's `warnings` (buildContrastWarningHtml),
		// whichever audit filled them in, so an uploaded sub-AA set takes the
		// same path to the dialog: here white text on a white primary, 1:1.
		await withUploadedSet(
			browser,
			'Low contrast',
			LOW_CONTRAST_CSS,
			async (id) => {
				await openTheming(page)
				await expectNonBlockingContrastWarning(page, id)
			},
		)
	})

	test(// @e2e openspec/specs/token-set-contrast-audit/spec.md#a-compliant-shipped-set-shows-no-contrast-warning
	'A compliant shipped set shows no contrast warning', async ({ page }) => {
		await openTheming(page)

		const select = page.locator('#nldesign-token-set-select')

		// rijkshuisstijl is a documented AA-compliant set (10.2:1 / 9.43:1).
		const exists = await select.locator('option[value="rijkshuisstijl"]').count()
		test.skip(exists === 0, 'rijkshuisstijl set not present in this instance')
		// Choosing the set that is already active opens no dialog, and then
		// there is no dialog to show the absence of a warning in.
		test.skip(
			(await select.inputValue()) === 'rijkshuisstijl',
			'rijkshuisstijl is already the active set',
		)

		await select.selectOption('rijkshuisstijl')

		// Waited for, not probed: isVisible() answers at once, so the previous
		// `if (isVisible)` read a dialog that had not opened yet and asserted
		// nothing.
		const overlay = page.locator('#nldesign-apply-dialog-overlay')
		await expect(overlay).toBeVisible({ timeout: 10_000 })
		await expect(contrastWarning(overlay)).toHaveCount(0)
		await overlay.locator('.nldesign-dialog-cancel').first().click()
	})
})
