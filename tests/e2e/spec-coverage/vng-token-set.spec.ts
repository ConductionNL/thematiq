/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/vng-token-set/spec.md
 *
 * @e2e exclude openspec/specs/vng-token-set/spec.md
 * CSS token-set / colour-value spec — scenarios describe CSS custom property
 * values, colour palette, typography tokens, and border-radius values; the
 * manifest entry and dropdown visibility are covered by admin-settings tests.
 *
 * All scenarios are excluded at the spec level.
 */
import { test, expect } from '@playwright/test'
import {
	offerTokenSets,
	requestToken,
	withdrawTokenSetOffer,
} from '../workflows/_helpers'

const THEMING_URL = '/settings/admin/theming'

test.describe('vng-token-set', () => {
	// @e2e exclude openspec/specs/vng-token-set/spec.md#vng-token-file-exists-and-loads
	// Requires selecting VNG token set — mutates IConfig.

	// @e2e exclude openspec/specs/vng-token-set/spec.md#vng-palette-tokens-are-preserved
	// CSS custom property inspection — requires selecting VNG token set.

	// @e2e exclude openspec/specs/vng-token-set/spec.md#primary-colors-use-vng-blue
	// CSS variable values — requires selecting VNG token set.

	// @e2e exclude openspec/specs/vng-token-set/spec.md#status-colors-use-vng-palette
	// CSS variable values — requires selecting VNG token set.

	// @e2e exclude openspec/specs/vng-token-set/spec.md#text-colors-use-vng-values
	// CSS variable values — requires selecting VNG token set.

	// @e2e exclude openspec/specs/vng-token-set/spec.md#font-family-is-set-to-avenir
	// CSS typography token — requires selecting VNG token set.

	// @e2e exclude openspec/specs/vng-token-set/spec.md#font-sizes-follow-vng-scale
	// CSS typography values — requires selecting VNG token set.

	// @e2e exclude openspec/specs/vng-token-set/spec.md#spacing-tokens-are-defined
	// CSS spacing tokens — requires selecting VNG token set.

	// @e2e exclude openspec/specs/vng-token-set/spec.md#border-radius-uses-vng-values
	// CSS border-radius token — requires selecting VNG token set.

	// @e2e exclude openspec/specs/vng-token-set/spec.md#header-uses-vng-dark-blue
	// CSS header token — requires selecting VNG token set.

	// @e2e exclude openspec/specs/vng-token-set/spec.md#vng-appears-in-manifest
	// token-sets.json content — not DOM-testable.

	// @e2e exclude openspec/specs/vng-token-set/spec.md#utrecht-tokens-are-absent-from-vng-file
	// CSS file content assertion — not DOM-testable.

	// Smoke: VNG option is present in the token set dropdown
	test(// @e2e openspec/specs/vng-token-set/spec.md#vng-appears-in-admin-dropdown
	'VNG token set appears as a selectable option in the admin dropdown', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		// A shipped brand is offered only once it is selectable: see
		// openspec/specs/token-sets/spec.md, "Only Fully Functional Brands Are
		// Selectable". VNG is not on the allowlist today, so the scenario's
		// GIVEN is established through a group mapping, which the same
		// requirement names as one of the paths that keep a set selectable.
		const token = await requestToken(page)
		const offer = await offerTokenSets(page, token, ['vng'])
		try {
			await page.goto(THEMING_URL)
			await page.waitForLoadState('domcontentloaded')
			const select = page.locator('#nldesign-token-set-select')
			await expect(select).toBeVisible()
			// VNG option must exist in the dropdown
			const vngOption = select.locator('option[value="vng"]')
			await expect(vngOption).toBeAttached()
			await expect(vngOption).toHaveText(/VNG/)
		} finally {
			await withdrawTokenSetOffer(page, token, offer)
		}
	})
})
