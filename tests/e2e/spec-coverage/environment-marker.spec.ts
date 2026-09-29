/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The environment marker on a server that declares no environment: the
 * shared CI instance leaves thematiq.environment unset in config.php, so
 * these are the two scenarios a browser can prove there. The declared-value
 * scenarios carry their own @e2e exclude with the unit test that proves them.
 */
import { test, expect } from '@playwright/test'

const THEMING_URL = '/settings/admin/theming'

test.describe('environment marker on a server with no declared environment', () => {
	// @e2e environment-marker::an-unset-value-shows-nothing
	test('an unset value shows nothing', async ({ page }) => {
		await page.goto('/apps/files/')
		await page.locator('#header, header').first().waitFor({ state: 'visible', timeout: 20_000 })

		await expect(page.locator('#thematiq-env-marker')).toHaveCount(0)
		expect(await page.title()).not.toMatch(/^\[(Development|Test|Acceptance|Unknown)\]/)
	})

	// @e2e environment-marker::an-administrator-learns-how-to-set-it
	test('an administrator learns how to set it', async ({ page }) => {
		await page.goto(THEMING_URL)
		const line = page.locator('#nldesign-environment')
		await line.waitFor({ state: 'visible', timeout: 20_000 })

		await expect(line).toContainText('No environment is set')
		await expect(line.locator('code')).toHaveText('occ config:system:set thematiq.environment --value=<environment>')
		await expect(line.locator('input, select, textarea, button')).toHaveCount(0)
	})
})
