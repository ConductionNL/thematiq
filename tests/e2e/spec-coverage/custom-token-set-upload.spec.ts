/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/custom-token-sets/spec.md
 *
 * UI Playwright tests for the custom token set upload / list / download /
 * delete surface in the NL Design admin settings panel ("eigen huisstijl").
 *
 * These tests assert the admin-facing surface (form, list, actions, contrast
 * warning banner). Pure server-side validation/auth branches are covered by
 * PHPUnit and Newman and are marked @e2e exclude on their scenarios in the spec.
 */
import { test, expect, type Locator, type Page } from '@playwright/test'

import { removeE2eCustomSets } from './_fixtures'

/** The admin page's own-sets panel; its forms are hidden until it is open. */
const THEMING_URL = '/settings/admin/theming#own-sets'

/** Open a custom-set row's "…" actions menu; a second call leaves it open. */
async function openRowMenu(row: Locator): Promise<void> {
	const menu = row.locator('.nldesign-custom-set-menu')
	if (!(await menu.isVisible())) {
		await row.getByRole('button', { name: /^Actions for / }).click()
	}
	await expect(menu).toBeVisible()
}

/**
 * Delete a custom set from its row menu and confirm. Delete opens thematiq's
 * admin dialog as TqEigenSets draws it: eyebrow "Own token sets", the set in
 * the title and a red "Delete token set".
 */
async function deleteRow(page: Page, row: Locator): Promise<void> {
	await openRowMenu(row)
	await row.getByRole('button', { name: 'Delete', exact: true }).click()
	const dialog = page.locator('#thematiq-delete-custom-set-dialog')
	await expect(dialog).toBeVisible({ timeout: 10000 })
	await expect(dialog).toContainText('Own token sets')
	await expect(dialog.getByRole('heading')).toContainText(
		'Delete custom token set',
	)
	const confirmBtn = dialog.getByRole('button', {
		name: 'Delete token set',
		exact: true,
	})
	await expect(confirmBtn).toBeVisible({ timeout: 10000 })
	await confirmBtn.click()
	await expect(row).toBeHidden({ timeout: 10000 })
}

test.describe('custom-token-set-upload', () => {
	// Each upload test deletes its own set as its last step. A test that dies
	// before that step left the set behind and poisoned later runs (#181), so
	// sweep leftovers before the first test and after every test.
	test.beforeAll(async ({ browser }) => {
		await removeE2eCustomSets(browser)
	})

	test.afterEach(async ({ browser }) => {
		await removeE2eCustomSets(browser)
	})

	// -----------------------------------------------------------------------
	// Requirement: Upload Custom Token Set (CSS format)
	// -----------------------------------------------------------------------

	test(
		// @e2e openspec/specs/custom-token-sets/spec.md#admin-uploads-a-valid-css-token-set
		'Custom token set upload form is present with name field and upload button',
		async ({ page }) => {
			await page.goto(THEMING_URL)
			await page.waitForLoadState('domcontentloaded')

			const section = page.locator('#nldesign-custom-token-sets')
			await expect(section).toBeVisible()
			await expect(page.locator('#nldesign-upload-name')).toBeVisible()
			await expect(page.locator('#nldesign-upload-btn')).toBeVisible()
		},
	)

	test(
		// @e2e openspec/specs/custom-token-sets/spec.md#admin-uploads-a-valid-css-token-set
		'Uploading a valid CSS set adds it to the custom-set list and the dropdown',
		async ({ page }) => {
			await page.goto(THEMING_URL)
			await page.waitForLoadState('domcontentloaded')

			const setName = 'E2E Voorbeeld ' + Date.now()
			await page.fill('#nldesign-upload-name', setName)

			// The hidden file input is triggered by the button; set the file
			// directly so the change handler runs without the native picker.
			await page.locator('#nldesign-upload-input').setInputFiles({
				name: 'huisstijl.css',
				mimeType: 'text/css',
				buffer: Buffer.from(
					':root { --nldesign-color-primary: #007bc7; --nldesign-color-primary-text: #ffffff; }',
				),
			})

			// The result draws the TqSetUploaden board: Uploaded, the file, what was saved.
			const result = page.locator(
				'#nldesign-upload-result .thematiq-upload-result',
			)
			await expect(result).toBeVisible({ timeout: 10000 })
			await expect(
				result.locator('.thematiq-upload-result__pill--pass'),
			).toHaveText('Uploaded')
			await expect(result.locator('.thematiq-upload-result__file')).toHaveText(
				'huisstijl.css',
			)
			await expect(
				result.locator('.thematiq-upload-result__summary'),
			).toContainText(`"${setName}" is saved and is now in the list.`)
			await expect(
				result.locator('.thematiq-upload-result__summary'),
			).toContainText('imported')

			// The new set appears in the custom-set list.
			await expect(page.locator('#nldesign-custom-set-list')).toContainText(
				setName,
				{ timeout: 10000 },
			)

			// Clean up: delete the set we just created (also covers delete UI).
			const row = page.locator('.nldesign-custom-set-row', {
				hasText: setName,
			})
			await deleteRow(page, row)
		},
	)

	// Scenario: Upload endpoint is admin-only and CSRF-protected
	// @e2e exclude openspec/specs/custom-token-sets/spec.md#upload-endpoint-is-admin-only-and-csrf-protected
	// Auth-posture assertion — PHPUnit/Newman verify middleware rejection, not a UI flow.

	// Scenario: Slug collisions are rejected
	// @e2e exclude openspec/specs/custom-token-sets/spec.md#slug-collisions-are-rejected
	// Validation branch — PHPUnit on CustomTokenSetService::store (409).

	// Scenario: Shipped set ids can never be shadowed
	// @e2e exclude openspec/specs/custom-token-sets/spec.md#shipped-set-ids-can-never-be-shadowed
	// Namespace invariant — PHPUnit on the service (isCustomId / store path).

	// -----------------------------------------------------------------------
	// Requirement: CSS Validation Whitelist (all branches PHPUnit-covered)
	// -----------------------------------------------------------------------

	// @e2e exclude openspec/specs/custom-token-sets/spec.md#disallowed-css-payload-is-rejected-with-a-structured-error
	// Security validation branch — PHPUnit on the validator payload corpus.
	test(
		// @e2e openspec/specs/custom-token-sets/spec.md#external-url-in-a-token-value-is-rejected
		'A refused file is drawn as Refused file: nothing saved, and why',
		async ({ page }) => {
			await page.goto(THEMING_URL)
			await page.waitForLoadState('domcontentloaded')

			const setName = 'E2E Refused ' + Date.now()
			await page.fill('#nldesign-upload-name', setName)
			await page.locator('#nldesign-upload-input').setInputFiles({
				name: 'promo.css',
				mimeType: 'text/css',
				buffer: Buffer.from(
					':root { --nldesign-color-primary: #154273; --nldesign-color-link: javascript:alert(1); }',
				),
			})

			const refused = page.locator(
				'#nldesign-upload-result .thematiq-upload-result--refused',
			)
			await expect(refused).toBeVisible({ timeout: 10000 })
			await expect(
				refused.locator('.thematiq-upload-result__title'),
			).toHaveText('Refused file')
			await expect(refused.locator('strong')).toHaveText(
				'promo.css was refused (422). Nothing was saved.',
			)
			await expect(
				page.locator('#nldesign-custom-set-list'),
			).not.toContainText(setName)
		},
	)
	// @e2e exclude openspec/specs/custom-token-sets/spec.md#unknown-properties-are-skipped-and-counted
	// Parser behaviour — PHPUnit on the validator.
	// @e2e exclude openspec/specs/custom-token-sets/spec.md#oversized-upload-is-rejected
	// Size guard — PHPUnit/Newman on the controller (413).

	// -----------------------------------------------------------------------
	// Requirement: W3C Design Tokens JSON Import (all branches PHPUnit-covered)
	// -----------------------------------------------------------------------

	// @e2e exclude openspec/specs/custom-token-sets/spec.md#dtcg-color-tokens-map-onto-the-nldesign-vocabulary
	// Mapping logic — PHPUnit on DesignTokensMapper with DTCG fixtures.
	// @e2e exclude openspec/specs/custom-token-sets/spec.md#unmapped-dtcg-tokens-degrade-to-skipped-counts
	// Mapping tolerance — PHPUnit on the mapper.
	// @e2e exclude openspec/specs/custom-token-sets/spec.md#malformed-json-is-rejected
	// Parse guard — Newman on the controller (422).

	// -----------------------------------------------------------------------
	// Requirement: WCAG AA Contrast Warnings on Upload
	// -----------------------------------------------------------------------

	test(
		// @e2e openspec/specs/custom-token-sets/spec.md#low-contrast-upload-succeeds-with-a-warning
		'Low-contrast upload succeeds and surfaces a WCAG AA warning',
		async ({ page }) => {
			await page.goto(THEMING_URL)
			await page.waitForLoadState('domcontentloaded')

			const setName = 'E2E Lowcontrast ' + Date.now()
			await page.fill('#nldesign-upload-name', setName)
			await page.locator('#nldesign-upload-input').setInputFiles({
				name: 'low.css',
				mimeType: 'text/css',
				buffer: Buffer.from(
					':root { --nldesign-color-primary: #ffffff; --nldesign-color-primary-text: #cccccc; }',
				),
			})

			const result = page.locator('#nldesign-upload-result')
			await expect(result).toBeVisible({ timeout: 10000 })
			// Either the result message references a contrast warning, or the
			// list row shows the "Contrast warning" badge.
			const row = page.locator('.nldesign-custom-set-row', {
				hasText: setName,
			})
			await expect(row).toBeVisible({ timeout: 10000 })
			await expect(row.locator('.nldesign-badge--warning')).toBeVisible()

			await deleteRow(page, row)
		},
	)

	// Scenario: Contrast warning resurfaces when applying the set
	// Covered in token-set-contrast-audit.spec.ts, next to the shipped-set
	// variant of the same dialog warning.

	// Scenario: Compliant upload produces no warnings
	// @e2e exclude openspec/specs/custom-token-sets/spec.md#compliant-upload-produces-no-warnings
	// Computation branch — PHPUnit on ContrastService with known-ratio fixtures.

	// -----------------------------------------------------------------------
	// Requirement: Custom Set Metadata and Theming Bridge
	// -----------------------------------------------------------------------

	// Scenario: Uploaded set participates in theming sync
	// @e2e exclude openspec/specs/custom-token-sets/spec.md#uploaded-set-participates-in-theming-sync
	// The theming-sync dialog reads theming.primary_color from the discovery
	// payload; derivation is covered by CustomTokenSetServiceTest (deriveTheming)
	// and TokenSetServiceMergeTest, and the sync dialog itself by token-sync-workflow.spec.

	// Scenario: Manifest entry without a file is dropped
	// @e2e exclude openspec/specs/custom-token-sets/spec.md#manifest-entry-without-a-file-is-dropped
	// Discovery edge — PHPUnit on TokenSetService merge + CustomTokenSetService::list.

	// -----------------------------------------------------------------------
	// Requirement: Manage Custom Token Sets
	// -----------------------------------------------------------------------

	test(
		// @e2e openspec/specs/custom-token-sets/spec.md#admin-downloads-an-uploaded-set
		// @e2e openspec/specs/custom-token-sets/spec.md#admin-deletes-an-inactive-custom-set
		'Uploaded set exposes Download and Delete actions; delete removes the row',
		async ({ page }) => {
			await page.goto(THEMING_URL)
			await page.waitForLoadState('domcontentloaded')

			const setName = 'E2E Manage ' + Date.now()
			await page.fill('#nldesign-upload-name', setName)
			await page.locator('#nldesign-upload-input').setInputFiles({
				name: 'manage.css',
				mimeType: 'text/css',
				buffer: Buffer.from(
					':root { --nldesign-color-primary: #154273; --nldesign-color-primary-text: #ffffff; }',
				),
			})

			const row = page.locator('.nldesign-custom-set-row', {
				hasText: setName,
			})
			await expect(row).toBeVisible({ timeout: 10000 })
			// The row's actions sit behind its "…" menu (TqEigenSets board). By
			// exact accessible name: the menu also carries "Download as design
			// tokens" (#870), which a text match on "Download" finds as well.
			await openRowMenu(row)
			await expect(
				row.getByRole('button', { name: 'Download', exact: true }),
			).toBeVisible()
			await expect(
				row.getByRole('button', { name: 'Delete', exact: true }),
			).toBeVisible()

			await deleteRow(page, row)
		},
	)

	// Scenario: Deleting the active set falls back to nextcloud
	// @e2e exclude openspec/specs/custom-token-sets/spec.md#deleting-the-active-set-falls-back-to-nextcloud
	// Activating then deleting the active set mutates the instance-wide token_set
	// appconfig (affects every page for all users in the shared env); the
	// fallback-to-nextcloud reset is covered by CustomTokenSetServiceTest.
})
