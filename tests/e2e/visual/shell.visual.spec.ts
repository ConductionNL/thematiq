/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/changes/shell-pixel-check/specs/shell-pixel-check/spec.md
 *
 * The pixel check of the shell, as the OpenProject plugin has one for its own
 * (`docs/pixel-check.md` there): the top bar and the navigation of Files at
 * 1440 x 1000 with the Zuiddrecht set, compared with reference images. It
 * tells you when a Nextcloud release, or a change here, moved something in the
 * shell. Opt-in like the other visual specs:
 *
 *   PW_VISUAL=1 npx playwright test --project visual tests/e2e/visual/shell.visual.spec.ts
 *
 * A pixel counts as different when its colour is about 24 of 255 away, as in
 * the plugin's check; the top bar may differ in 0.5% of its pixels and the
 * navigation in 1%. The references are host-specific (fonts, GPU): make them
 * again on the host that runs the check, never to make a failing check pass.
 */
import { expect, test } from '@playwright/test'
import { applyThemeState, restoreThemeState } from '../spec-coverage/_theme-state'
import { freezePage } from './_visual-helpers'

test.use({ viewport: { width: 1440, height: 1000 } })

test('the shell of Files looks as it did', async ({ page }) => {
	const snapshot = await applyThemeState(page, { tokenSet: 'zuiddrecht' })
	try {
		await page.goto('/apps/files/')
		await page.waitForSelector('tr.files-list__row', { timeout: 60_000 })
		await freezePage(page)
		// The storage line under the navigation changes with every file.
		const mask = [page.locator('[class*="quota"]'), page.locator('.notifications-button .notification__dot')]
		await expect(page).toHaveScreenshot('shell-top-bar.png', {
			clip: { x: 0, y: 0, width: 1440, height: 68 },
			animations: 'disabled',
			threshold: 0.1,
			maxDiffPixelRatio: 0.005,
			mask,
		})
		await expect(page).toHaveScreenshot('shell-navigation.png', {
			clip: { x: 0, y: 68, width: 264, height: 932 },
			animations: 'disabled',
			threshold: 0.1,
			maxDiffPixelRatio: 0.01,
			mask,
		})
	} finally {
		await restoreThemeState(page, snapshot)
	}
})
