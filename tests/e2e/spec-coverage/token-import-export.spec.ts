/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/token-import-export/spec.md
 *
 * UI-only Playwright tests for the import/export controls in the NL Design
 * admin settings token editor panel.
 */
import { test, expect } from '@playwright/test'

const THEMING_URL = '/settings/admin/theming'

test.describe('token-import-export', () => {
	// -----------------------------------------------------------------------
	// Requirement: Export Current Overrides
	// -----------------------------------------------------------------------

	test(// @e2e openspec/specs/token-import-export/spec.md#admin-downloads-overrides
	'Download button is present in the token editor panel', async ({ page }) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		// The export/download control of the editor panel is the canonical
		// `#nldesign-export-btn`. Uploaded custom token sets each render their
		// own per-row "Download" button, so a text-only locator is ambiguous.
		const downloadBtn = page.locator('#nldesign-export-btn')
		await expect(downloadBtn).toBeVisible()
	})

	test(// @e2e openspec/specs/token-import-export/spec.md#download-says-what-it-does-first
	'Download explains the file before downloading, and Cancel downloads nothing', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		await page.locator('#nldesign-export-btn').click()

		const dialog = page.locator('#nldesign-export-overrides-overlay')
		await expect(dialog).toBeVisible()
		await expect(dialog).toContainText('It is not a complete theme.')

		await dialog.locator('.nldesign-dialog-cancel').click()
		await expect(dialog).toHaveCount(0)
	})

	// Scenario: Download with no custom overrides
	// @e2e exclude openspec/specs/token-import-export/spec.md#download-with-no-custom-overrides
	// Requires verifying file download content — browser download behaviour
	// requires complex intercept setup; the Download button presence is the UI surface.

	// Scenario: Download is a GET request to a dedicated endpoint
	// @e2e exclude openspec/specs/token-import-export/spec.md#download-is-a-get-request-to-a-dedicated-endpoint
	// API-layer assertion (Content-Type, Content-Disposition headers) — not UI.

	// -----------------------------------------------------------------------
	// Requirement: Import Token File
	// -----------------------------------------------------------------------

	test(// @e2e openspec/specs/token-import-export/spec.md#admin-uploads-a-valid-overrides-file
	'Upload control is present in the token editor panel', async ({ page }) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		const uploadTrigger = page.locator('#nldesign-import-btn')
		await expect(uploadTrigger).toBeVisible()
	})

	test(// @e2e openspec/specs/token-import-export/spec.md#upload-says-what-it-does-first
	'Upload explains the replace before the file picker, and Cancel opens none', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		await page.locator('#nldesign-import-btn').click()

		const dialog = page.locator('#nldesign-import-overrides-overlay')
		await expect(dialog).toBeVisible()
		await expect(dialog).toContainText(
			'replace every value saved for this theme',
		)

		await dialog.locator('.nldesign-dialog-cancel').click()
		await expect(dialog).toHaveCount(0)
	})

	// Scenario: Import replaces existing overrides
	// @e2e exclude openspec/specs/token-import-export/spec.md#import-replaces-existing-overrides
	// Requires uploading a file and verifying custom-overrides.css content — mutates
	// shared env state; backend file-write assertion.

	// -----------------------------------------------------------------------
	// Requirement: Import Validation
	// -----------------------------------------------------------------------

	// All import-validation scenarios require file upload and API response assertions.
	// @e2e exclude openspec/specs/token-import-export/spec.md#file-contains-unknown-tokens
	// @e2e exclude openspec/specs/token-import-export/spec.md#file-contains-only-unknown-tokens
	// @e2e exclude openspec/specs/token-import-export/spec.md#file-contains-excluded-tokens
	// @e2e exclude openspec/specs/token-import-export/spec.md#file-is-not-valid-css
	// @e2e exclude openspec/specs/token-import-export/spec.md#file-exceeds-size-limit
	// All five are backend validation assertions requiring file upload simulation and
	// HTTP response status checks — not testable as UI-only assertions.

	// -----------------------------------------------------------------------
	// Requirement: Import Result Feedback
	// -----------------------------------------------------------------------

	// @e2e exclude openspec/specs/token-import-export/spec.md#import-summary-is-shown
	// Requires completing a file upload to observe the result message — would mutate
	// shared env state.

	// -----------------------------------------------------------------------
	// Requirement: Upload Endpoint
	// -----------------------------------------------------------------------

	// @e2e exclude openspec/specs/token-import-export/spec.md#upload-endpoint-receives-file
	// API-layer assertion (multipart/form-data, server-side parsing) — not UI.

	// -----------------------------------------------------------------------
	// Requirement: Token Set Round Trip
	// -----------------------------------------------------------------------

	// @e2e exclude openspec/specs/token-import-export/spec.md#export-writes-only-what-the-set-declares
	// Reads the downloaded file's content — covered by the playground unit tests.

	// @e2e exclude openspec/specs/token-import-export/spec.md#a-marked-file-is-stored-as-it-arrived
	// Uploads a token set and inspects the stored file — mutates shared-env custom sets;
	// covered by the controller unit tests.
})
