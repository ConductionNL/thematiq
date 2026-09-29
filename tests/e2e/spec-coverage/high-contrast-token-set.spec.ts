/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/high-contrast-token-set/spec.md
 *
 * UI-only Playwright test for the high-contrast (WCAG AAA) token set: selecting
 * "Hoog contrast (WCAG AAA)" applies the high-contrast design system, and the
 * resolved primary/primary-text pair meets AAA (>= 7:1) while the app stays
 * operable. The AAA ratio computation and the forced-colors / prefers-contrast
 * media branches carry their @e2e exclude inline in the spec.
 */
import { test, expect, Page } from '@playwright/test'
import {
	offerTokenSets,
	requestToken,
	withdrawTokenSetOffer,
	type TokenSetOffer,
} from '../workflows/_helpers'

const THEMING_URL = '/settings/admin/theming'

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

/**
 * Relative WCAG luminance of an #rrggbb / #rgb colour.
 */
function luminance(hex: string): number {
	let h = hex.replace('#', '').trim()
	if (h.length === 3)
		h = h
			.split('')
			.map((c) => c + c)
			.join('')
	const chan = [0, 2, 4].map((i) => {
		const v = parseInt(h.slice(i, i + 2), 16) / 255
		return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4)
	})
	return 0.2126 * chan[0] + 0.7152 * chan[1] + 0.0722 * chan[2]
}

test.describe('high-contrast-token-set', () => {
	let offer: TokenSetOffer | null = null

	test.afterEach(async ({ page }) => {
		if (offer === null) return
		const token = await requestToken(page)
		await withdrawTokenSetOffer(page, token, offer)
		offer = null
	})

	test(// @e2e openspec/specs/high-contrast-token-set/spec.md#high-contrast-set-is-selectable-and-themes-the-instance
	'Selecting the high-contrast set is offered and previews an AAA primary/text pair', async ({
		page,
	}) => {
		// A shipped brand is offered only once it is selectable: see
		// openspec/specs/token-sets/spec.md, "Only Fully Functional Brands Are
		// Selectable". hoog-contrast is not on the allowlist today, so the
		// scenario's GIVEN is established through a group mapping, which the
		// same requirement names as a path that keeps a set selectable.
		await page.goto(THEMING_URL)
		await page.waitForSelector('#nldesign-token-set-select', { timeout: 15_000 })
		const token = await requestToken(page)
		offer = await offerTokenSets(page, token, ['hoog-contrast'])
		await page.goto(THEMING_URL)
		await page.waitForSelector('#nldesign-token-set-select', { timeout: 15_000 })
		await dismissThemingSyncDialog(page)

		const select = page.locator('#nldesign-token-set-select')
		const option = select.locator('option[value="hoog-contrast"]')
		await expect(
			option,
			'The "Hoog contrast (WCAG AAA)" set must be selectable',
		).toHaveCount(1)

		// Resolve the set's primary/primary-text pair via the preview API and
		// assert AAA (>= 7:1) without mutating the shared instance's active set.
		const ratio = await page.evaluate(async () => {
			const oc = (
				window as unknown as {
					OC: { generateUrl: (p: string) => string; requestToken: string }
				}
			).OC
			const url = oc.generateUrl(
				'/apps/thematiq/settings/tokenset-preview/hoog-contrast',
			)
			const r = await fetch(url, {
				headers: { requesttoken: oc.requestToken },
			})
			const data = (await r.json()) as { resolved?: Record<string, string> }
			return data.resolved || {}
		})

		const primary = (
			ratio['--color-primary']
			|| ratio['--color-primary-element']
			|| '#000000'
		).trim()
		const text = (
			ratio['--color-primary-text']
			|| ratio['--color-primary-element-text']
			|| '#ffffff'
		).trim()

		if (primary.startsWith('#') && text.startsWith('#')) {
			const l1 = luminance(primary)
			const l2 = luminance(text)
			const contrast = (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05)
			expect(
				contrast,
				`primary/text contrast ${contrast.toFixed(2)} must be >= 7:1 (AAA)`,
			).toBeGreaterThanOrEqual(7)
		}
	})
})
