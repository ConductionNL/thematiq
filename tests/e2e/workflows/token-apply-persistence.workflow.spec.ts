/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * DEEP, DATA-DEPENDENT workflow: token-override apply + persistence.
 *
 * Unlike the spec-coverage tests (which assert the editor *renders*), this
 * workflow proves the theming FEATURE end-to-end:
 *   - editing a token in the editor and clicking "Save overrides" actually
 *     writes the value to the backend (the generated custom-overrides.css and
 *     the GET /settings/overrides response now contain it), and
 *   - after a FRESH RELOAD the editor shows the persisted override (input value
 *     + custom badge) — i.e. the override survives a round-trip, and
 *   - clearing the override removes it from the backend. (The row's reset
 *     button goes back to the SAVED value, so it is not the way to drop one.)
 *
 * It also probes whether the saved override is REFLECTED in the live CSS
 * variable on the page (the "applies the theme" promise). See the fixme block —
 * this is a genuine product behaviour that does NOT currently work for editable
 * tokens under the active design system.
 *
 * GLOBAL STATE: this spec mutates css/custom-overrides.css (instance-wide
 * theming). The prior file content is snapshotted in beforeAll and RESTORED in
 * afterAll so the dev instance is never left re-themed.
 */
import { test, expect, type Page } from '@playwright/test'
import {
	openTheming,
	openTokenTab,
	requestToken,
	getOverrides,
	setOverrides,
	getServedOverrideCss,
	liveCssVar,
} from './_helpers'

// A token we drive through the editor. --color-primary is a real, prominent
// theming token and is present in the editor's "login" tab, which is not the
// tab the editor opens on — see openTokenTab().
const TEST_TOKEN = '--color-primary'
const TEST_VALUE = '#a1b2c3' // deliberate, easily-recognised, easily-reverted value

let baselineOverrides: Record<string, string> = {}

/**
 * Unlock Nextcloud's base tokens, which --color-primary is one of: they stay
 * locked until the admin ticks the opt-in and confirms its warning.
 *
 * @param page The Playwright page, on the theming settings.
 */
async function unlockBaseTokens(page: Page): Promise<void> {
	// Nextcloud's `input.checkbox` is visually hidden; its label is the control.
	await page.locator('label[for="nldesign-base-unlock"]').click()
	await page
		.locator('#nldesign-base-unlock-overlay .nldesign-dialog-confirm')
		.click()
	await expect(page.locator('#nldesign-base-unlock')).toBeChecked()
}

/**
 * Click Save overrides and answer the dialog it may open.
 *
 * Saving asks first — on the stock Nextcloud theme whether to keep the changes
 * as a new theme, on a token set whether to lay the overrides over it. Both
 * dialogs share one overlay. This spec is about the overrides file, so it takes
 * the answer that writes it: "change the Nextcloud theme" on stock, "Save
 * overrides" on a set. "Don't ask again" is left unticked, so the instance's
 * preference is not changed by the test.
 *
 * @param page The Playwright page, on the theming settings.
 */
async function saveOverrides(page: Page): Promise<void> {
	await page.locator('#nldesign-save-btn').click()
	const dialog = page.locator('#nldesign-save-dialog-overlay')
	const asked = await dialog
		.waitFor({ state: 'visible', timeout: 5_000 })
		.then(() => true)
		.catch(() => false)
	if (!asked) return
	const overwrite = dialog.locator('.nldesign-dialog-overwrite')
	if ((await overwrite.count()) > 0) {
		await overwrite.click()
	} else {
		await dialog.locator('.nldesign-dialog-confirm').click()
	}
	await expect(dialog).toBeHidden()
}

test.describe('workflow: token-apply persistence', () => {
	test.describe.configure({ mode: 'serial', timeout: 90_000 })

	test.beforeAll(async ({ browser }) => {
		// Snapshot the current persisted overrides so afterAll can restore them.
		const page = await browser.newPage()
		await openTheming(page)
		const token = await requestToken(page)
		baselineOverrides = await getOverrides(page, token)
		await page.close()
	})

	test.afterAll(async ({ browser }) => {
		// CRITICAL: restore the instance to its prior override state.
		const page = await browser.newPage()
		await openTheming(page)
		const token = await requestToken(page)
		await setOverrides(page, token, baselineOverrides)
		const restored = await getOverrides(page, token)
		expect(restored).toEqual(baselineOverrides)
		await page.close()
	})

	test('editing a token + Save overrides PERSISTS to the backend (file + GET)', async ({
		page,
	}) => {
		await openTheming(page)
		const token = await requestToken(page)

		// Drive the real editor UI: type the value into the --color-primary text field.
		await unlockBaseTokens(page)
		await openTokenTab(page, 'login')
		const textField = page.locator(
			`.nldesign-color-text[data-token="${TEST_TOKEN}"]`,
		)
		await expect(textField).toBeVisible()
		await textField.fill(TEST_VALUE)

		// The editor marks the row dirty (custom badge appears) — UI feedback.
		await expect(
			page.locator(
				`[data-token-row="${TEST_TOKEN}"] .nldesign-token-custom-badge`,
			),
		).toHaveCount(1)
		await expect(page.locator('#nldesign-save-status')).toHaveText(
			/Unsaved changes/i,
		)

		// Click the real Save overrides button.
		await saveOverrides(page)
		// Dirty status clears on success.
		await expect(page.locator('#nldesign-save-status')).toHaveText('', {
			timeout: 10_000,
		})

		// (a) Backend write: GET /settings/overrides now returns the value.
		const persisted = await getOverrides(page, token)
		expect(
			persisted[TEST_TOKEN],
			'override should be persisted in backend GET',
		).toBe(TEST_VALUE)

		// (a') The generated custom-overrides.css file on disk contains the declaration.
		// It is emitted with !important so the user override wins the cascade over the
		// nldesign design-system stylesheets and Nextcloud core theming.
		const css = await getServedOverrideCss(page)
		expect(
			css,
			'custom-overrides.css should contain the saved declaration',
		).toContain(`${TEST_TOKEN}: ${TEST_VALUE} !important;`)
	})

	test('after a FRESH RELOAD the editor shows the persisted override', async ({
		page,
	}) => {
		// Fresh navigation — proves the value survives a round trip, not just in-memory state.
		await openTheming(page)

		const textField = page.locator(
			`.nldesign-color-text[data-token="${TEST_TOKEN}"]`,
		)
		await expect(textField).toHaveValue(TEST_VALUE)

		// The custom badge is rendered for a persisted (non-default) value.
		await expect(
			page.locator(
				`[data-token-row="${TEST_TOKEN}"] .nldesign-token-custom-badge`,
			),
		).toHaveCount(1)
	})

	/*
	 * The SAVED override IS reflected in the live CSS variable (the "applies the
	 * theme to the instance" promise): after saving an override for --color-primary
	 * and reloading, getComputedStyle(:root)['--color-primary'] equals the override.
	 *
	 * This now works because custom-overrides.css emits each user override with
	 * `!important` (CustomOverridesService::buildDeclarationLines) and loads last,
	 * so it wins the cascade over the nldesign design-system stylesheets
	 * (css/systems/nldesign/{theme,overrides,element-overrides}.css — which also
	 * use !important) and over Nextcloud's own theming stylesheet. The !important is
	 * scoped to user-set overrides only; it does not blanket-!important the full
	 * token registry.
	 */
	test('saved override is reflected in the LIVE CSS variable after reload', async ({
		page,
	}) => {
		await openTheming(page)
		const live = await liveCssVar(page, TEST_TOKEN)
		expect(live.toLowerCase()).toBe(TEST_VALUE.toLowerCase())
	})

	test('clearing the override REMOVES it from the backend', async ({ page }) => {
		await openTheming(page)
		const token = await requestToken(page)

		// Sanity: the override is present before reset.
		const before = await getOverrides(page, token)
		expect(before[TEST_TOKEN]).toBe(TEST_VALUE)

		// Empty the row's field, then Save: an emptied row is how a saved value
		// stops being saved. The reset button would restore the saved value.
		await unlockBaseTokens(page)
		await openTokenTab(page, 'login')
		await page
			.locator(`.nldesign-color-text[data-token="${TEST_TOKEN}"]`)
			.fill('')
		await saveOverrides(page)
		await expect(page.locator('#nldesign-save-status')).toHaveText('', {
			timeout: 10_000,
		})

		// Backend no longer carries the override.
		const after = await getOverrides(page, token)
		expect(
			after[TEST_TOKEN],
			'override should be removed after clearing the field and saving',
		).toBeUndefined()

		// The served file no longer contains the declaration.
		const css = await getServedOverrideCss(page)
		expect(css).not.toContain(`${TEST_TOKEN}: ${TEST_VALUE} !important;`)
		expect(css).not.toContain(TEST_VALUE)
	})
})
