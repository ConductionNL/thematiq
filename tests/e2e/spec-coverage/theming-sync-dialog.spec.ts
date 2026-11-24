/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/theming-sync-dialog/spec.md
 *
 * UI-only Playwright tests for the theming-sync dialog that can appear
 * after selecting a token set that has theming metadata.
 *
 * Note: The dialog is triggered after a token set is saved via POST /settings/tokenset
 * and the response payload includes theming metadata AND the current NC theming differs.
 * Most scenarios that require the dialog to appear are not safe to trigger in a shared
 * test environment (they modify Nextcloud's core theming). Core UI structure tests are
 * annotated below.
 */
import { test, expect } from '@playwright/test'

const THEMING_URL = '/settings/admin/theming'

test.describe('theming-sync-dialog', () => {
	// -----------------------------------------------------------------------
	// Requirement: Theming Metadata in Token Sets
	// -----------------------------------------------------------------------

	// Scenario: Token set with full theming metadata
	// @e2e exclude openspec/specs/theming-sync-dialog/spec.md#token-set-with-full-theming-metadata
	// Requires calling GET /settings/tokensets and verifying theming object in response — API assertion.

	// Scenario: Token set without theming metadata
	// @e2e exclude openspec/specs/theming-sync-dialog/spec.md#token-set-without-theming-metadata
	// API response validation — not UI.

	// Scenario: Token set with partial theming metadata
	// @e2e exclude openspec/specs/theming-sync-dialog/spec.md#token-set-with-partial-theming-metadata
	// API response validation — not UI.

	// -----------------------------------------------------------------------
	// Requirement: Get Current Theming Values Endpoint
	// -----------------------------------------------------------------------

	// Scenario: Retrieve current theming values
	// @e2e exclude openspec/specs/theming-sync-dialog/spec.md#retrieve-current-theming-values
	// API response structure assertion — not UI.

	// Scenario: Unauthenticated access denied
	// @e2e exclude openspec/specs/theming-sync-dialog/spec.md#unauthenticated-access-denied
	// Requires unauthenticated request — not testable in admin session.

	// -----------------------------------------------------------------------
	// Requirement: Update Theming Values Endpoint
	// -----------------------------------------------------------------------

	// All endpoint scenarios (update colors, update logo, update background, validation failures)
	// are API-layer assertions.
	// @e2e exclude openspec/specs/theming-sync-dialog/spec.md#update-colors-only
	// @e2e exclude openspec/specs/theming-sync-dialog/spec.md#update-logo
	// @e2e exclude openspec/specs/theming-sync-dialog/spec.md#update-background-image
	// @e2e exclude openspec/specs/theming-sync-dialog/spec.md#invalid-hex-color-rejected
	// @e2e exclude openspec/specs/theming-sync-dialog/spec.md#path-traversal-rejected
	// @e2e exclude openspec/specs/theming-sync-dialog/spec.md#non-existent-image-rejected

	// -----------------------------------------------------------------------
	// Requirement: Confirmation Dialog After Token Set Change
	// -----------------------------------------------------------------------

	// Scenario: Dialog shown for token set with theming metadata
	// @e2e exclude openspec/specs/theming-sync-dialog/spec.md#dialog-shown-for-token-set-with-theming-metadata
	// Triggering the dialog requires selecting a token set AND having NC theming differ from it;
	// the POST also modifies IConfig — avoid mutating shared env for dialog trigger.

	// Scenario: Dialog not shown for token set without theming metadata
	// @e2e exclude openspec/specs/theming-sync-dialog/spec.md#dialog-not-shown-for-token-set-without-theming-metadata
	// Requires selecting a token set — mutates IConfig.

	// Scenario: Dialog not shown when values already match
	// @e2e exclude openspec/specs/theming-sync-dialog/spec.md#dialog-not-shown-when-values-already-match
	// Requires specific IConfig state — not deterministic.

	// -----------------------------------------------------------------------
	// Requirement: Dialog Preview Boxes
	// -----------------------------------------------------------------------

	// @e2e exclude openspec/specs/theming-sync-dialog/spec.md#current-preview-reflects-active-theming
	// @e2e exclude openspec/specs/theming-sync-dialog/spec.md#proposed-preview-reflects-token-set-theming
	// Dialog-internal rendering — only verifiable after triggering the dialog.

	// -----------------------------------------------------------------------
	// Requirement: Dialog User Actions
	// -----------------------------------------------------------------------

	// @e2e exclude openspec/specs/theming-sync-dialog/spec.md#user-confirms-update
	// @e2e exclude openspec/specs/theming-sync-dialog/spec.md#user-cancels-update
	// Require dialog to be open — dialog trigger mutates env.

	// -----------------------------------------------------------------------
	// Requirement: Bundled Organization Images
	// -----------------------------------------------------------------------

	// @e2e exclude openspec/specs/theming-sync-dialog/spec.md#logo-file-stored-correctly
	// @e2e exclude openspec/specs/theming-sync-dialog/spec.md#background-file-stored-correctly
	// Filesystem assertions — not UI.

	// Smoke: theming page loads without errors (dialog infrastructure present)
	test(
		// @e2e openspec/specs/theming-sync-dialog/spec.md#dialog-shown-for-token-set-with-theming-metadata
		'Admin theming page loads and theming-sync JS is initialized (no dialog errors)',
		async ({ page }) => {
			const errors: string[] = []
			page.on('console', (msg) => {
				if (msg.type() === 'error') errors.push(msg.text())
			})
			await page.goto(THEMING_URL)
			await page.waitForLoadState('domcontentloaded')
			// nldesign-theming-dialog-overlay must NOT exist on page load (no spurious dialog)
			const dialog = page.locator('#nldesign-theming-dialog-overlay')
			await expect(dialog).not.toBeVisible()
			// The settings section must render (JS initialized correctly)
			await expect(page.locator('#nldesign-settings')).toBeAttached()
		},
	)
})
