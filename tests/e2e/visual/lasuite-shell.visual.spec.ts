/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/lasuite-stack/spec.md
 *
 * Screenshot baselines for the shell geometry of the `lasuite` set
 * (tests/e2e/spec-coverage/lasuite-shell-geometry.spec.ts), one per supported
 * Nextcloud major: the top bar and the app navigation of Files at 1440 x 1000.
 * The shell layer loads on Nextcloud 35 only, so the 35 baseline shows the
 * 64px La Suite header and the others show the stock header under the set's
 * colours. The baseline name carries the server's major, so each major is
 * compared with its own image.
 *
 * Opt-in like the other visual specs, and host-specific (fonts, GPU): make the
 * images on the host that runs the check, never to make a failing check pass.
 *   PW_VISUAL=1 npx playwright test --project visual tests/e2e/visual/lasuite-shell.visual.spec.ts
 */
import { expect, test } from '@playwright/test'
import { applyThemeState, restoreThemeState } from '../spec-coverage/_theme-state'
import { freezePage } from './_visual-helpers'

test.use({ viewport: { width: 1440, height: 1000 } })

test('the lasuite shell of Files looks as it did on this Nextcloud major', async ({ page }) => {
	const snapshot = await applyThemeState(page, { tokenSet: 'lasuite' })
	try {
		await page.goto('/apps/files/')
		await page.waitForSelector('tr.files-list__row', { timeout: 60_000 })
		const version = await page.evaluate(() => String((window as any).OC?.config?.version ?? ''))
		expect(version, 'the page must expose OC.config.version').not.toBe('')
		const major = version.split('.')[0]
		await freezePage(page)
		const mask = [
			page.locator('[class*="quota"]'),
			page.locator('.notifications-button .notification__dot'),
		]
		await expect(page).toHaveScreenshot(`lasuite-shell-nc${major}.png`, {
			clip: { x: 0, y: 0, width: 1440, height: 400 },
			animations: 'disabled',
			threshold: 0.1,
			maxDiffPixelRatio: 0.01,
			mask,
		})
	} finally {
		await restoreThemeState(page, snapshot)
	}
})
