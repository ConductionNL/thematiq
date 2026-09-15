/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Visual regression for the component instrument inside the theming settings
 * panel: one shot of the stage and one of the filtered token list, per
 * component, in light and dark.
 *
 * Opt-in and non-gating, like every spec under tests/e2e/visual/:
 *
 *   PW_VISUAL=1 npx playwright test --project visual
 *   PW_VISUAL=1 npx playwright test --project visual --update-snapshots
 *
 * Baselines are host-specific (fonts, GPU) and are NOT committed from a dev
 * machine: generate them where they will be compared.
 *
 * Dark mode is `emulateMedia({ colorScheme: 'dark' })` rather than Nextcloud's
 * own dark-theme switch, because a token set's generated dark variant is scoped
 * to `prefers-color-scheme: dark` and deliberately excludes an explicitly
 * chosen theme. The variant is the thing worth a baseline.
 */
import { test, expect, type Page } from '@playwright/test'

const THEMING = '/index.php/settings/admin/theming'

/** The chips of the open tab, by their label. */
async function chipLabels(page: Page): Promise<string[]> {
	return page.$$eval('.nldesign-pg-chip', (nodes) =>
		nodes.map((node) => (node.textContent || '').trim()),
	)
}

/**
 * Open the theming panel and wait for the instrument to have replaced the
 * editor's tab strip — admin.js fetches the token registry before rendering it,
 * so there is nothing to photograph until that has happened.
 */
async function openInstrument(page: Page): Promise<void> {
	await page.goto(THEMING)
	await expect(
		page.locator('.nldesign-pg-selector .nldesign-tab-btn').first(),
	).toBeVisible()
	await expect(page.locator('.nldesign-pg-chip').first()).toBeVisible()
}

for (const scheme of ['light', 'dark'] as const) {
	test(`component instrument — every component, ${scheme}`, async ({ page }) => {
		await page.emulateMedia({ colorScheme: scheme })
		await openInstrument(page)

		for (const tab of ['login', 'content', 'status', 'typography']) {
			await page
				.locator(
					`.nldesign-pg-selector .nldesign-tab-btn[data-tab="${tab}"]`,
				)
				.click()

			const labels = await chipLabels(page)
			// The first chip is Full view, which has no stage of its own.
			for (const label of labels.slice(1)) {
				await page
					.locator('.nldesign-pg-chip', { hasText: label })
					.first()
					.click()

				await expect(page.locator('.nldesign-pg-stage')).toHaveScreenshot(
					`${scheme}-${tab}-${slug(label)}-stage.png`,
				)

				await expect(page.locator('.nldesign-pg-panel')).toHaveScreenshot(
					`${scheme}-${tab}-${slug(label)}-tokens.png`,
				)
			}
		}
	})
}

/** The same slug the instrument puts in the URL hash. */
function slug(label: string): string {
	return label
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-|-$/g, '')
}
